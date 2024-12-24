<?php

namespace Chr15k\ResponseOptimizer\Middleware;

use Chr15k\ResponseOptimizer\Encoders\BrotliEncoder;
use Chr15k\ResponseOptimizer\Encoders\GzipEncoder;
use Closure;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CompressResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldCompress($request, $response)) {
            return $response;
        }

        return match (config('response-optimizer.compression.algorithm')) {
            'gzip' => $response = app(GzipEncoder::class)->handle($response),
            'br' => $response = app(BrotliEncoder::class)->handle($response),
            default => $response,
        };
    }

    private function shouldCompress(Request $request, Response $response): bool
    {
        return $this->enabled()
            && $this->validateRequest($request)
            && $this->validateResponse($response);
    }

    private function validateResponse(Response $response): bool
    {
        $content = $response->getContent();

        return $response->isSuccessful()
            && is_string($content)
            && strlen($content) > config('response-optimizer.compression.min_length')
            && $this->validateResponseType($response);
    }

    private function validateResponseType(Response $response): bool
    {
        return ! $response instanceof BinaryFileResponse && ! $response instanceof StreamedResponse;
    }

    private function validateRequest(Request $request): bool
    {
        return in_array(
            config('response-optimizer.compression.algorithm'),
            $request->getEncodings()
        );
    }

    private function enabled(): bool
    {
        return filter_var(
            config('response-optimizer.compression.enabled'),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
