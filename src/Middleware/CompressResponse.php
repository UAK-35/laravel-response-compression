<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Uak35\ResponseCompression\Encoders\BrotliEncoder;
use Uak35\ResponseCompression\Encoders\GzipEncoder;
use Uak35\ResponseCompression\Encoders\ZstdEncoder;
use Uak35\ResponseCompression\Exceptions\InvalidConfigurationException;
use Uak35\ResponseCompression\Support\AcceptEncoding;
use Uak35\ResponseCompression\Support\Config;
use Uak35\ResponseCompression\Support\Diagnostic;

/**
 * Compresses a response with an encoding the request accepted.
 *
 * Every configured value is read strictly: one that is set but cannot be read stops the
 * response with an InvalidConfigurationException rather than being replaced with a
 * default. See Uak35\ResponseCompression\Support\Config for what is read, what is refused,
 * and what still takes a documented default.
 */
final class CompressResponse
{
    /**
     * The encodings this package can produce, in the spelling the configuration uses.
     *
     * `multiple_encodings_order` is a configuration list rather than a closed set, so it can name
     * something no encoder here answers for. Such a name is not a candidate - see produces().
     *
     * @var list<string>
     */
    private const array PRODUCIBLE = ['gzip', 'br', 'zstd'];

    /** The fallback behaviour that compresses with `fallback_encoding` instead of leaving the body. */
    private const string FALLBACK_COMPRESS = 'compress';

    /** The fallback behaviour that leaves the body alone, which is the shipped default. */
    private const string FALLBACK_IGNORE = 'ignore';

    private bool $userAgentConsulted = false;

    /**
     * Handle an incoming request.
     *
     * The closure hands back a Response rather than a controller's raw return value,
     * because the route dispatcher converts it before the innermost pipeline step runs
     * (Illuminate\Routing\Router::runRouteWithinStack). That is what lets the body be
     * read here instead of being narrowed with a runtime guard.
     *
     * @param  Closure(SymfonyRequest): Response  $next
     */
    public function handle(SymfonyRequest $request, Closure $next): Response
    {
        // early exit if compression is disabled
        if (! $this->enabled()) {
            $this->logDebugStatus(Diagnostic::CompressionDisabled);

            return $next($request);
        }

        $requestUri = $request->getRequestUri();

        $this->logDebugStatus(Diagnostic::CompressionChecking, ['uri' => $requestUri]);

        // The encoding is decided before the response exists, because every rule that decides it
        // is about the request. The response then has one question of its own - is it a body worth
        // coding - and the encoding chosen here is the one it is coded with.
        $encoding = $this->selectEncoding($request, $requestUri);

        if ($encoding === null) {
            $this->logDebugStatus(Diagnostic::CompressionNotAllowed);

            return $this->vary($next($request));
        }

        $response = $next($request);

        if (! $this->validateResponse($response, $requestUri)) {
            $this->logDebugStatus(Diagnostic::ResponseSkipped, ['uri' => $requestUri]);

            return $this->vary($response);
        }

        return $this->vary($this->encode($encoding, $response, $requestUri));
    }

    /**
     * Mark a response as varying by Accept-Encoding.
     *
     * A shared cache keys a stored response on the request headers named in `Vary`, so a response
     * this middleware decided not to compress still has to say that the body it holds is a
     * function of the request's Accept-Encoding: the same URL is compressed for the next client
     * that asks for the encoding, and without this header a cache is free to hand this body to
     * that client - or a compressed body to a client that asked for `identity`. The encoders set
     * the header when they compress; this is the other half of that, on every path that leaves
     * the body alone.
     *
     * It is idempotent, which is what lets it be applied where an encoder may already have run:
     * an existing `Accept-Encoding` entry is left alone, and an app's other `Vary` values are
     * kept rather than replaced - the header is appended to, never rewritten.
     */
    private function vary(Response $response): Response
    {
        // Check if Accept-Encoding is already present
        $hasAcceptEncoding = false;
        foreach ($response->headers->all('Vary') as $header) {
            foreach (explode(',', (string) $header) as $value) {
                if (strcasecmp(trim($value), 'Accept-Encoding') === 0) {
                    $hasAcceptEncoding = true;
                    break 2;
                }
            }
        }

        // Add Accept-Encoding if not present
        if (! $hasAcceptEncoding) {
            $response->setVary('Accept-Encoding', false);
        }

        // Add User-Agent if the flag indicates we consulted it
        if ($this->userAgentConsulted) {
            $varyValues = array_filter(array_map(trim(...), explode(',', (string) $response->headers->get('Vary', ''))));
            $hasUserAgent = array_any($varyValues, static fn (string $value): bool => strcasecmp($value, 'User-Agent') === 0);

            if (! $hasUserAgent) {
                $varyValues[] = 'User-Agent';
                $response->headers->set('Vary', implode(', ', $varyValues));
            }
        }

        return $response;
    }

    /**
     * Whether compression is enabled, taking the testing switch into account.
     *
     * The decision itself is a pure function so that both of its outcomes are
     * exercised: PHPUnit always reports runningUnitTests() as true, which would leave
     * the non-testing branch of an inline check unexecuted.
     */
    private function enabled(): bool
    {
        $enabled = Config::boolOr('response-compression.enabled', true);
        $enabledForTesting = Config::boolOr('response-compression.enabled_for_testing', true);
        $underTest = App::environment('testing') || app()->runningUnitTests();

        return $this->compressionAllowed($enabled, $enabledForTesting, $underTest);
    }

    /**
     * The compression decision, with every input given to it.
     */
    private function compressionAllowed(bool $enabled, bool $enabledForTesting, bool $underTest): bool
    {
        return $enabled && (! $underTest || $enabledForTesting);
    }

    /**
     * Whether the request accepts the configured encoding.
     */
    private function validateRequest(SymfonyRequest $request, string $requestUri): bool
    {
        return $this->validateRequestPerAlgoAndUserAgent(
            $this->algorithm(),
            $request->getEncodings(),
            $request->headers->get('Accept-Encoding'),
            $request->headers->get('user-agent'),
            $requestUri,
        );
    }

    /**
     * Whether the response may be compressed at all.
     */
    private function validateResponse(Response $response, string $requestUri): bool
    {
        // A binary file or a streamed response has no in-memory body to encode, and
        // Response::getContent() reports `false` for one. Nothing may read the body
        // before this has been ruled out.
        if (! $this->validateResponseType($response)) {
            $this->logDebugStatus(Diagnostic::ResponseIsStream, ['uri' => $requestUri]);

            return false;
        }

        if (! $response->isSuccessful()) {
            $this->logDebugStatus(Diagnostic::ResponseNotSuccessful, ['uri' => $requestUri]);

            return false;
        }

        if ($response->headers->has('Content-Encoding')) {
            $this->logDebugStatus(Diagnostic::ResponseSkipped, [
                'reason' => 'already_encoded',
                'uri' => $requestUri,
            ]);

            return false;
        }

        if ($response->getStatusCode() === 206 || $response->headers->has('Content-Range')) {
            $this->logDebugStatus(Diagnostic::ResponseSkipped, [
                'reason' => 'partial_content',
                'uri' => $requestUri,
            ]);

            return false;
        }

        $content = $response->getContent();

        if (! is_string($content)) {
            $this->logDebugStatus(Diagnostic::ResponseNotString, ['uri' => $requestUri]);

            return false;
        }

        $contentLength = strlen($content);
        $allowedMinLengthOfContent = $this->minLength();
        if ($contentLength <= $allowedMinLengthOfContent) {
            $this->logDebugStatus(Diagnostic::ResponseTooSmall, [
                'length' => (string) $contentLength,
                'minimum' => (string) $allowedMinLengthOfContent,
                'uri' => $requestUri,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Whether the response carries a body this middleware can read.
     */
    private function validateResponseType(Response $response): bool
    {
        return ! $response instanceof BinaryFileResponse && ! $response instanceof StreamedResponse;
    }

    /**
     * Whether this package can produce an encoding at all.
     *
     * A name the walk cannot produce is skipped rather than chosen: the encoders answer only for
     * `gzip`, `br` and `zstd`, so choosing any other name returns the body untouched, and because
     * the walk stops at the first encoding that compressed the body, that would end it with
     * nothing compressed while a later entry was both accepted and producible - which is what a
     * client sending `Accept-Encoding: *` used to be served when the list began with one.
     */
    private function produces(string $encoding): bool
    {
        return in_array($encoding, self::PRODUCIBLE, true);
    }

    /**
     * The encoding this request is served with, or null when it is served as it is.
     *
     * Single-encoding mode asks the acceptance rule about the configured `algorithm` and nothing
     * else. Multiple-encoding mode walks `multiple_encodings_order` and answers with the first
     * entry that is both producible and accepted - which is why `Accept-Encoding: *` is served the
     * first entry rather than refused, and why a name no encoder here answers for is skipped
     * instead of ending the walk. When that walk finds nothing, the configured fallback decides:
     * `compress` names one encoding to try, `ignore` leaves the body alone.
     */
    private function selectEncoding(SymfonyRequest $request, string $requestUri): ?string
    {
        if (! $this->tryMultipleEncodings()) {
            return $this->validateRequest($request, $requestUri) ? $this->algorithm() : null;
        }

        foreach ($this->encodingOrder() as $encoding) {
            if ($this->produces($encoding) && $this->acceptsEncoding($encoding, $request, $requestUri)) {
                return $encoding;
            }
        }

        return $this->fallbackEncoding($request, $requestUri);
    }

    /**
     * The encoding the configured fallback names, or null when the body is served as it is.
     *
     * `fallback_behavior: compress` is a promise to compress with one named encoding when the
     * order list has nothing to offer, and a promise that cannot be kept is a log line rather than
     * a failed response: an unset, empty or unproducible `fallback_encoding`, or one the client
     * does not accept, turns the answer into `ignore` and says which of those it was.
     */
    private function fallbackEncoding(SymfonyRequest $request, string $requestUri): ?string
    {
        if ($this->fallbackBehavior() !== self::FALLBACK_COMPRESS) {
            return null;
        }

        $encoding = $this->configuredFallbackEncoding();

        if (! $this->produces($encoding)) {
            $this->logDebugStatus(Diagnostic::FallbackIgnored, [
                'reason' => $encoding === ''
                    ? 'no fallback encoding is configured'
                    : sprintf("'%s' is not one this package can produce", $encoding),
                'uri' => $requestUri,
            ]);

            return null;
        }

        if (! $this->acceptsEncoding($encoding, $request, $requestUri)) {
            $this->logDebugStatus(Diagnostic::FallbackIgnored, [
                'reason' => sprintf("the client does not accept '%s'", $encoding),
                'uri' => $requestUri,
            ]);

            return null;
        }

        return $encoding;
    }

    /**
     * What the configured fallback asks for, refused rather than defaulted when it is a word this
     * package does not know.
     */
    private function fallbackBehavior(): string
    {
        $behavior = strtolower(Config::stringOr('response-compression.fallback_behavior', self::FALLBACK_IGNORE));

        if (! in_array($behavior, [self::FALLBACK_COMPRESS, self::FALLBACK_IGNORE], true)) {
            throw InvalidConfigurationException::unreadable(
                'response-compression.fallback_behavior',
                "one of the strings 'compress' or 'ignore'",
                $behavior,
            );
        }

        return $behavior;
    }

    /**
     * The encoding the fallback names, lowercase and trimmed, or an empty string when none is set.
     */
    private function configuredFallbackEncoding(): string
    {
        return strtolower(trim(Config::stringOr('response-compression.fallback_encoding', '')));
    }

    /**
     * Whether the client accepts one encoding and comes from an agent that can decode it.
     */
    private function acceptsEncoding(string $encoding, SymfonyRequest $request, string $requestUri): bool
    {
        return $this->validateRequestPerAlgoAndUserAgent(
            $encoding,
            $request->getEncodings(),
            $request->headers->get('Accept-Encoding'),
            $request->headers->get('user-agent'),
            $requestUri,
        );
    }

    /**
     * Compress the response with one encoding, leaving it alone when the encoding is
     * not one this package can produce.
     */
    private function encode(string $encoding, Response $response, string $requestUri): Response
    {
        if (! $this->produces($encoding)) {
            $this->logDebugStatus(Diagnostic::EncodingUnknown, ['uri' => $requestUri]);

            return $response;
        }

        $this->logDebugStatus(Diagnostic::EncodingCompressing, ['encoding' => $encoding, 'uri' => $requestUri]);

        return match ($encoding) {
            'gzip' => app(GzipEncoder::class)->handle($response),
            'br' => app(BrotliEncoder::class)->handle($response),
            'zstd' => app(ZstdEncoder::class)->handle($response),
            default => $response,
        };
    }

    /**
     * Whether the request accepts a given encoding and comes from a user agent that
     * can decode it.
     *
     * The client may also have asked, in the same header, for the body as it is - see
     * AcceptEncoding - and that request is honoured before anything else here is.
     *
     * @param  string[]  $requestEncodings
     */
    private function validateRequestPerAlgoAndUserAgent(
        string $compressionAlgorithm,
        array $requestEncodings,
        ?string $acceptEncoding,
        ?string $requestUserAgent,
        string $requestUri,
    ): bool {
        // A client that named `identity` has asked for the body with no content coding applied to
        // it, and that is a coding this package cannot produce: honouring the request means leaving
        // the body alone. Read before the acceptance rule below, so the line this writes records
        // the client's own reason rather than the absence of a match.
        if (AcceptEncoding::prefersIdentity($acceptEncoding, $compressionAlgorithm)) {
            $this->logDebugStatus(Diagnostic::IdentityRequested, ['uri' => $requestUri]);

            return false;
        }

        // A request that names no encoding has not asked for one. An absent header and a
        // wildcard are different requests: Symfony reads a missing Accept-Encoding as an
        // empty list, while `*` is the client saying it accepts anything.
        // Normalize both sides to lowercase for case-insensitive comparison per HTTP spec (Bug 9 fix).
        $requestEncodings = array_map(strtolower(...), $requestEncodings);
        if (! in_array('*', $requestEncodings, true) && ! in_array(strtolower($compressionAlgorithm), $requestEncodings, true)) {
            $this->logDebugStatus(Diagnostic::EncodingNotAccepted, ['uri' => $requestUri]);

            return false;
        }

        $preferences = AcceptEncoding::preferences($acceptEncoding);
        $quality = $preferences[$compressionAlgorithm]
            ?? $preferences[AcceptEncoding::ANY]
            ?? 1.0;

        if ($quality <= 0.0) {
            $this->logDebugStatus(Diagnostic::EncodingNotAccepted, [
                'encoding' => $compressionAlgorithm,
                'quality' => (string) $quality,
                'uri' => $requestUri,
            ]);

            return false;
        }

        // A user agent identified by a common prefix is known to decode this encoding
        // badly over a non-secure connection, so it is served the body as-is. `array_all`
        // is the PHP 8.4 spelling of "none of these match".
        $prefixes = $this->nonSupportingUserAgentPrefixes($compressionAlgorithm);
        if (count($prefixes) > 0) {
            $this->userAgentConsulted = true;
        }

        $didNotMatched = array_all(
            $prefixes,
            static fn (string $prefix): bool => $requestUserAgent === null || ! str_starts_with($requestUserAgent, $prefix),
        );

        if (! $didNotMatched) {
            $this->logDebugStatus(Diagnostic::UserAgentNotSupported, [
                'agent' => (string) $requestUserAgent,
                'uri' => $requestUri,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Whether multiple encodings are attempted in sequence.
     */
    private function tryMultipleEncodings(): bool
    {
        return Config::boolOr('response-compression.try_multiple_encodings', false);
    }

    /**
     * The configured encodings, in the order they are attempted.
     *
     * @return list<string>
     */
    private function encodingOrder(): array
    {
        return Config::commaList('response-compression.multiple_encodings_order', 'br,zstd,gzip');
    }

    /**
     * The user agent prefixes the configured encoding is known not to work with.
     *
     * @return list<string>
     */
    private function nonSupportingUserAgentPrefixes(string $compressionAlgorithm): array
    {
        return Config::stringListOr(
            'response-compression.'.$compressionAlgorithm.'.non_supporting_user_agent_prefixes',
            [],
        );
    }

    /**
     * The compression algorithm to use.
     *
     * Refused rather than defaulted when it cannot be read: the algorithm is the
     * `Content-Encoding` every response carries, so a guess here is a header that lies
     * about the body beside it.
     */
    private function algorithm(): string
    {
        return Config::string('response-compression.algorithm');
    }

    /**
     * The smallest body worth compressing.
     *
     * Refused rather than defaulted when it cannot be read. The fallback this replaced was
     * `0`, which is the one value a floor must never quietly become: everything gets
     * compressed, including the responses the floor exists to leave alone.
     */
    private function minLength(): int
    {
        return Config::int('response-compression.min_length');
    }

    private function enableLogging(): bool
    {
        return Config::boolOr('response-compression.enable_logging', false);
    }

    /**
     * Write one diagnostic, when logging is on: the reason, its values, and any context.
     *
     * @param  array<string, string>  $values  one value per hole in the reason's sentence
     * @param  array<string, mixed>  $context
     */
    private function logDebugStatus(Diagnostic $reason, array $values = [], array $context = []): void
    {
        if (! $this->enableLogging()) {
            return;
        }
        \Illuminate\Support\Facades\Log::debug($reason->line($values), $context);
    }
}
