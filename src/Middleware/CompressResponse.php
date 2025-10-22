<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Middleware;

use Closure;
use Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Uak35\ResponseCompression\Encoders\BrotliEncoder;
use Uak35\ResponseCompression\Encoders\GzipEncoder;
use Uak35\ResponseCompression\Encoders\ZstdEncoder;

final class CompressResponse
{
    /**
     * Handle an incoming request.
     * @param SymfonyRequest $request
     * @param Closure $next
     * @return Response
     * @throws Exception
     */
    public function handle(SymfonyRequest $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$this->tryMultipleEncodings()) {
            if (! $this->shouldCompress($request, $response)) {
                return $response;
            }

            return match (config('response-compression.algorithm')) {
                'gzip' => app(GzipEncoder::class)->handle($response),
                'br' => app(BrotliEncoder::class)->handle($response),
                'zstd' => app(ZstdEncoder::class)->handle($response),
                default => $response,
            };
        }

        $multipleEncodings = explode(',', config('response-compression.multiple_encodings_order'));
        foreach ($multipleEncodings as $encoding) {
            if ($this->shouldCompressForAlgo($encoding, $request, $response)) {
                $response = match ($encoding) {
                    'gzip' => app(GzipEncoder::class)->handle($response),
                    'br' => app(BrotliEncoder::class)->handle($response),
                    'zstd' => app(ZstdEncoder::class)->handle($response),
                    default => $response,
                };
            }
        }

        return $response;
    }

    /**
     * Check if the response should be compressed.
     */
    private function shouldCompress(SymfonyRequest $request, Response $response): bool
    {
        return $this->enabled()
            && $this->validateRequest($request)
            && $this->validateResponse($response);
    }

    /**
     * Validate the response content.
     */
    private function validateResponse(Response $response): bool
    {
        $content = $response->getContent();

        return $response->isSuccessful()
            && is_string($content)
            && strlen($content) > config('response-compression.min_length')
            && $this->validateResponseType($response);
    }

    /**
     * Validate the response type.
     */
    private function validateResponseType(Response $response): bool
    {
        return ! $response instanceof BinaryFileResponse && ! $response instanceof StreamedResponse;
    }

    /**
     * Validate the request.
     */
    private function validateRequest(SymfonyRequest $request): bool
    {
        $compressionAlgorithm = config('response-compression.algorithm');
        return $this->validateRequestForAlgo($compressionAlgorithm, $request);
    }

    /**
     * Check if compression is enabled.
     */
    private function enabled(): bool
    {
        return filter_var(
            config('response-compression.enabled'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Check if multiple encodings should be tried.
     */
    private function tryMultipleEncodings(): bool
    {
        return filter_var(
            config('response-compression.try_multiple_encodings'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function shouldCompressForAlgo(string $compressionAlgorithm, SymfonyRequest $request, Response $response): bool
    {
        return $this->enabled()
            && $this->validateRequestForAlgo($compressionAlgorithm, $request)
            && $this->validateResponse($response);
    }


    /**
     * @param mixed $compressionAlgorithm
     * @param SymfonyRequest $request
     * @return bool
     */
    public function validateRequestForAlgo(mixed $compressionAlgorithm, SymfonyRequest $request): bool
    {
        $requestHasThisEncoding = in_array($compressionAlgorithm, $request->getEncodings());
        $requestUserAgent = $request->headers->get('user-agent');

        $nonSupportingUserAgentPrefixes = config('response-compression.' . $compressionAlgorithm . '.non_supporting_user_agent_prefixes');
        if (!empty($nonSupportingUserAgentPrefixes)) {
            $userAgentHasThisPrefix = array_reduce(
                $nonSupportingUserAgentPrefixes,
                fn(bool $hasPrefix, string $prefix) => $hasPrefix || str_starts_with($requestUserAgent, $prefix),
                false
            );
        } else {
            $userAgentHasThisPrefix = false;
        }

        return $requestHasThisEncoding && !$userAgentHasThisPrefix;
    }
}
