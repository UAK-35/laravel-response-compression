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
            return $next($request);
        }

        // early exit if compression is not allowed for this request
        if (! $this->validateRequest($request)) {
            return $next($request);
        }

        $response = $next($request);

        if ($this->tryMultipleEncodings()) {
            foreach ($this->encodingOrder() as $encoding) {
                if ($this->shouldCompressForAlgo($encoding, $request, $response)) {
                    $response = $this->encode($encoding, $response);
                }
            }

            return $response;
        }

        if (! $this->validateResponse($response)) {
            return $response;
        }

        return $this->encode($this->algorithm(), $response);
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
    private function validateRequest(SymfonyRequest $request): bool
    {
        return $this->validateRequestPerAlgoAndUserAgent(
            $this->algorithm(),
            $request->getEncodings(),
            $request->headers->get('user-agent'),
        );
    }

    /**
     * Whether the response may be compressed at all.
     */
    private function validateResponse(Response $response): bool
    {
        // A binary file or a streamed response has no in-memory body to encode, and
        // Response::getContent() reports `false` for one. Nothing may read the body
        // before this has been ruled out.
        if (! $this->validateResponseType($response)) {
            return false;
        }

        if (! $response->isSuccessful()) {
            return false;
        }

        $content = $response->getContent();

        if (! is_string($content)) {
            return false;
        }

        return strlen($content) > $this->minLength();
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
    private function shouldCompressForAlgo(string $compressionAlgorithm, SymfonyRequest $request, Response $response): bool
    {
        $requestAccepts = $this->validateRequestPerAlgoAndUserAgent(
            $compressionAlgorithm,
            $request->getEncodings(),
            $request->headers->get('user-agent'),
        );

        return $requestAccepts && $this->validateResponse($response);
    }

    /**
     * Compress the response with one encoding, leaving it alone when the encoding is
     * not one this package can produce.
     */
    private function encode(string $encoding, Response $response): Response
    {
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
     * @param  string[]  $requestEncodings
     */
    private function validateRequestPerAlgoAndUserAgent(
        string $compressionAlgorithm,
        array $requestEncodings,
        ?string $requestUserAgent,
    ): bool {
        // A request that names no encoding has not asked for one. An absent header and a
        // wildcard are different requests: Symfony reads a missing Accept-Encoding as an
        // empty list, while `*` is the client saying it accepts anything.
        if (! in_array('*', $requestEncodings, true) && ! in_array($compressionAlgorithm, $requestEncodings, true)) {
            return false;
        }

        // A user agent identified by a common prefix is known to decode this encoding
        // badly over a non-secure connection, so it is served the body as-is. `array_all`
        // is the PHP 8.4 spelling of "none of these match".
        return array_all(
            $this->nonSupportingUserAgentPrefixes($compressionAlgorithm),
            static fn (string $prefix): bool => $requestUserAgent === null || ! str_starts_with($requestUserAgent, $prefix),
        );
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
}
