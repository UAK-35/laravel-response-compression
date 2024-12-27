<?php

namespace Chr15k\ResponseOptimizer\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CacheControl
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldSetCacheControl($response)) {
            return $response;
        }

        $cacheControlRules = [
            'isSuccessful' => $this->directive(),
            'isRedirection' => 'no-store',
            'isClientError' => 'no-store, no-cache, must-revalidate',
            'isServerError' => 'no-store, no-cache, must-revalidate',
        ];

        foreach ($cacheControlRules as $method => $headerValue) {
            if (method_exists($response, $method) && $response->{$method}()) {
                $response->headers->set('Cache-Control', $headerValue);
                break;
            }
        }

        return $response;
    }

    private function shouldSetCacheControl(Response $response): bool
    {
        return $this->enabled() && $this->validateResponse($response);
    }

    private function validateResponse(Response $response): bool
    {
        // If the cache control directive is already set, we don't need to set it again.
        return ! str_contains((string) $response->headers->get('Cache-Control'), 'max-age');
    }

    private function directive(): mixed
    {
        return config('response-optimizer.cache.control.directive');
    }

    private function enabled(): mixed
    {
        return config('response-optimizer.cache.control.enabled');
    }
}
