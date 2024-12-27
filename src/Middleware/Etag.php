<?php

namespace Chr15k\ResponseOptimizer\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Etag
{
    public function handle(Request $request, Closure $next): Response
    {
        // Get the response for the request
        $response = $next($request);

        // Only apply ETag for successful GET or HEAD requests
        if (! $this->shouldApplyETag($request, $response)) {
            return $response;
        }

        // Generate the ETag based on the response content
        $etag = $this->generateETag($response);

        // Check if the ETag matches the client request
        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', Response::HTTP_NOT_MODIFIED, ['ETag' => $etag]);
        }

        // Attach the ETag to the response
        $response->headers->set('ETag', $etag);

        return $response;
    }

    /**
     * Determine if the ETag should be applied to the response.
     */
    private function shouldApplyETag(Request $request, Response $response): bool
    {
        // Apply ETag only for GET and HEAD requests with successful responses
        return in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'])
            && $response->isSuccessful();
    }

    /**
     * Generate an ETag for the response content.
     */
    private function generateETag(Response $response): string
    {
        return md5((string) $response->getContent());
    }
}
