<?php

namespace Chr15k\ResponseOptimizer\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use voku\helper\HtmlMin;

class MinifyHtml
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $content = $response->getContent();

        if ($this->shouldMinify($response)) {
            $response->setContent($this->minifyHtml($content));
        }

        return $response;
    }

    /**
     * Check if the response is HTML.
     */
    protected function isHtmlResponse(Response $response): bool
    {
        return str_contains(
            (string) $response->headers->get('Content-Type', ''),
            'text/html'
        );
    }

    /**
     * Check if the HTML content should be minified.
     */
    protected function shouldMinify(Response $response): bool
    {
        if (! config('response-optimizer.minify.enabled')) {
            return false;
        }

        if (! $response->isSuccessful()) {
            return false;
        }

        if (! $this->isHtmlResponse($response)) {
            return false;
        }

        return strlen((string) $response->getContent())
            >= config('response-optimizer.minify.min_size');
    }

    /**
     * Minify the HTML content.
     */
    protected function minifyHtml(string $content): string
    {
        return (new HtmlMin)
            ->doRemoveComments()
            ->doOptimizeAttributes()
            ->doSumUpWhitespace()
            ->doRemoveWhitespaceAroundTags()
            ->doRemoveHttpPrefixFromAttributes()
            ->doRemoveDefaultAttributes()
            ->doRemoveDeprecatedAnchorName()
            ->doRemoveSpacesBetweenTags(false) // Avoid rendering issues
            ->doRemoveOmittedHtmlTags(false) // Keep explicit tags
            ->minify($content);
    }
}
