<?php

use Chr15k\ResponseOptimizer\Middleware\MinifyHtml;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    config()->set('response-optimizer.minify.min_size', 1);
});

it('minifies the HTML content', function (): void {
    $middleware = new MinifyHtml;

    $html = '<html> <head> <title>Test</title> </head> <body> <p>Test</p> </body> </html>';

    $request = Request::create('/');
    $response = new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);

    $minifiedResponse = $middleware->handle($request, fn (): Response => $response);

    expect($minifiedResponse->getContent())
        ->toBe('<html><head><title>Test</title></head> <body><p>Test</p></body></html>');
});

it('does not minify the HTML content if the size is less than the minimum', function (): void {
    $middleware = new MinifyHtml;

    $html = '<html> <head> <title>Test</title> </head> <body> <p>Test</p> </body> </html>';

    $request = Request::create('/');
    $response = new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);

    config()->set('response-optimizer.minify.min_size', 1000);

    $minifiedResponse = $middleware->handle($request, fn (): Response => $response);

    expect($minifiedResponse->getContent())->toBe($html);
});

it('does not minify the HTML content if the response is not HTML', function (): void {
    $middleware = new MinifyHtml;

    $html = '<html> <head> <title>Test</title> </head> <body> <p>Test</p> </body> </html>';

    $request = Request::create('/');
    $response = new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/plain']);

    $minifiedResponse = $middleware->handle($request, fn (): Response => $response);

    expect($minifiedResponse->getContent())->toBe($html);
});

it('does not minify the HTML content if the response is empty', function (): void {
    $middleware = new MinifyHtml;

    $request = Request::create('/');
    $response = new Response(null, Response::HTTP_OK, ['Content-Type' => 'text/html']);

    $minifiedResponse = $middleware->handle($request, fn (): Response => $response);

    expect($minifiedResponse->getContent())->toBe('');
});

it('does not minify the HTML content if the response is not successful', function (): void {
    $middleware = new MinifyHtml;

    $html = '<html> <head> <title>Test</title> </head> <body> <p>Test</p> </body> </html>';

    $request = Request::create('/');
    $response = new Response($html, Response::HTTP_INTERNAL_SERVER_ERROR, ['Content-Type' => 'text/html']);

    $minifiedResponse = $middleware->handle($request, fn (): Response => $response);

    expect($minifiedResponse->getContent())->toBe($html);
});

it('should not minimize the HTML content if the minify option is disabled', function (): void {
    $middleware = new MinifyHtml;

    $html = '<html> <head> <title>Test</title> </head> <body> <p>Test</p> </body> </html>';

    $request = Request::create('/');
    $response = new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);

    config()->set('response-optimizer.minify.enabled', false);

    $minifiedResponse = $middleware->handle($request, fn (): Response => $response);

    expect($minifiedResponse->getContent())->toBe($html);
});
