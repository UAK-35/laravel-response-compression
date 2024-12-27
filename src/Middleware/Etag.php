<?php

namespace Chr15k\ResponseOptimizer\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class Etag
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->enabled()) {
            return $response;
        }

        if ($this->isReadOperation($request)) {
            return $this->handleIfNoneMatch($request, $response);
        }

        if ($this->isWriteOperation($request)) {
            return $this->handleIfMatch($request, $response);
        }

        return $response;
    }

    private function enabled(): bool
    {
        return filter_var(
            config('response-optimizer.cache.etag.enabled'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Determine if the request is a read operation.
     */
    private function isReadOperation(Request $request): bool
    {
        return in_array(strtoupper($request->getMethod()), ['GET', 'HEAD']);
    }

    /**
     * Determine if the request is a write operation.
     */
    private function isWriteOperation(Request $request): bool
    {
        return in_array(strtoupper($request->getMethod()), ['PUT', 'PATCH', 'DELETE']);
    }

    /**
     * Handle If-None-Match for read operations.
     */
    private function handleIfNoneMatch(Request $request, Response $response): Response
    {
        if (! $response->isSuccessful()) {
            return $response;
        }

        $etag = $this->generateETag($response);

        if ($request->headers->get('If-None-Match') === $etag) {
            return new Response('', Response::HTTP_NOT_MODIFIED, ['ETag' => $etag]);
        }

        $response->headers->set('ETag', $etag);

        return $response;
    }

    /**
     * Handle If-Match for write operations.
     *
     * The If-Match header is used with PUT requests to prevent overwriting
     * a resource that has changed since the client last retrieved it.
     *
     * If the resource's ETag does not match the one provided in the If-Match
     * header, the server responds with a 412 Precondition Failed.
     */
    private function handleIfMatch(Request $request, Response $response): Response
    {
        $clientETag = $request->headers->get('If-Match');
        $currentETag = $this->generateETag($response);

        if ($clientETag !== null && $clientETag !== $currentETag) {
            return new Response(
                Response::$statusTexts[Response::HTTP_PRECONDITION_FAILED],
                Response::HTTP_PRECONDITION_FAILED,
                ['ETag' => $currentETag]
            );
        }

        return $response;
    }

    /**
     * Generate an ETag for the response content.
     */
    private function generateETag(Response $response): string
    {
        return $this->algorithm()($response->getContent());
    }

    /**
     * Get the ETag hashing algorithm.
     */
    private function algorithm(): callable
    {
        return match (config('response-optimizer.cache.etag.algorithm')) {
            'md5' => fn (string $content): string => md5($content),
            'sha1' => fn (string $content): string => sha1($content),
            default => fn (string $content): string => md5($content),
        };
    }
}
