<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Stringable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Uak35\ResponseCompression\Encoders\BrotliEncoder;
use Uak35\ResponseCompression\Encoders\GzipEncoder;
use Uak35\ResponseCompression\Encoders\ZstdEncoder;
use Uak35\ResponseCompression\Support\Config;

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
            $this->logDebugStatus('Response compression not enabled...');

            return $next($request);
        }

        $requestUri = $request->getRequestUri();

        $this->logDebugStatus('Response compression checking - uri: '.$requestUri);

        // early exit if compression is not allowed for this request
        if (! $this->validateRequest($request, $requestUri)) {
            $this->logDebugStatus('Response compression not allowed for this request...');

            return $next($request);
        }

        $response = $next($request);

        if ($this->tryMultipleEncodings()) {
            foreach ($this->encodingOrder() as $encoding) {
                if ($this->shouldCompressForAlgo($encoding, $request, $response, $requestUri)) {
                    $response = $this->encode($encoding, $response, $requestUri);
                }
            }

            return $response;
        }

        if (! $this->validateResponse($response, $requestUri)) {
            $this->logDebugStatus('Response compression skipped - uri: '.$requestUri);

            return $response;
        }

        return $this->encode($this->algorithm(), $response, $requestUri);
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
            $this->logDebugStatus('Response is either binary file or a stream - Response compression skipped - uri: '.$requestUri);

            return false;
        }

        if (! $response->isSuccessful()) {
            $this->logDebugStatus('Response is not successful - Response compression skipped - uri: '.$requestUri);

            return false;
        }

        $content = $response->getContent();

        if (! is_string($content)) {
            $this->logDebugStatus('Response is not a string - Response compression skipped - uri: '.$requestUri);

            return false;
        }

        $contentLength = strlen($content);
        $allowedMinLengthOfContent = $this->minLength();
        if ($contentLength <= $allowedMinLengthOfContent) {
            $this->logDebugStatus('Response is small ('.$contentLength.' < '.$allowedMinLengthOfContent.') - Response compression skipped - uri: '.$requestUri);

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
     * Whether the response should be compressed for one of the configured encodings.
     */
    private function shouldCompressForAlgo(string $compressionAlgorithm, SymfonyRequest $request, Response $response, string $requestUri): bool
    {
        $requestAccepts = $this->validateRequestPerAlgoAndUserAgent(
            $compressionAlgorithm,
            $request->getEncodings(),
            $request->headers->get('user-agent'),
            $requestUri,
        );

        return $requestAccepts && $this->validateResponse($response, $requestUri);
    }

    /**
     * Compress the response with one encoding, leaving it alone when the encoding is
     * not one this package can produce.
     */
    private function encode(string $encoding, Response $response, string $requestUri): Response
    {
        $encoded = match ($encoding) {
            'gzip' => app(GzipEncoder::class)->handle($response),
            'br' => app(BrotliEncoder::class)->handle($response),
            'zstd' => app(ZstdEncoder::class)->handle($response),
            default => $response,
        };

        if (! in_array($encoding, ['gzip', 'br', 'zstd'])) {
            $this->logDebugStatus('Invalid encoding - Response compression skipped - uri: '.$requestUri);
        }

        $this->logDebugStatus('>> Compressing response using first available encoding = '.$encoding.' - uri: '.$requestUri);

        return $encoded;
    }

    /**
     * Whether the request accepts a given encoding and comes from a user agent that
     * can decode it.
     *
     * @param  string[]  $requestEncodings
     */
    private function validateRequestPerAlgoAndUserAgent(
        string $compressionAlgorithm,
        array $requestEncodings,
        ?string $requestUserAgent,
        string $requestUri,
    ): bool {
        // A request that names no encoding has not asked for one. An absent header and a
        // wildcard are different requests: Symfony reads a missing Accept-Encoding as an
        // empty list, while `*` is the client saying it accepts anything.
        if (! in_array('*', $requestEncodings, true) && ! in_array($compressionAlgorithm, $requestEncodings, true)) {
            $this->logDebugStatus('Response encoding not supported - Response compression skipped - uri: '.$requestUri);

            return false;
        }

        // A user agent identified by a common prefix is known to decode this encoding
        // badly over a non-secure connection, so it is served the body as-is. `array_all`
        // is the PHP 8.4 spelling of "none of these match".
        $didNotMatched = array_all(
            $this->nonSupportingUserAgentPrefixes($compressionAlgorithm),
            static fn (string $prefix): bool => $requestUserAgent === null || ! str_starts_with($requestUserAgent, $prefix),
        );

        if (! $didNotMatched) {
            $this->logDebugStatus('Disallowed user-agent found ( '.$requestUserAgent.') - Response compression skipped - uri: '.$requestUri);

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
     * @param  array<string, mixed>  $context
     */
    private function logDebugStatus(string|Stringable $message, array $context = []): void
    {
        if (! $this->enableLogging()) {
            return;
        }
        \Illuminate\Support\Facades\Log::debug('[COMPR-RESP] '.$message, $context);
    }
}
