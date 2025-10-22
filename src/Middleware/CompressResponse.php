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
        return in_array(
            config('response-compression.algorithm'),
            $request->getEncodings()
        );
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
}
