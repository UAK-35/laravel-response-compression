<?php

use Chr15k\ResponseOptimizer\Middleware\Etag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

it('should apply ETag for successful GET requests', function (): void {
    $request = Request::create('/test', 'GET');
    $response = new Response('Hello, World!', 200);

    $middleware = new Etag;
    $response = $middleware->handle($request, fn (): Response => $response);

    expect($response->headers->get('ETag'))->toBe(md5('Hello, World!'));
});

it('should apply ETag for successful HEAD requests', function (): void {
    $request = Request::create('/test', 'HEAD');
    $response = new Response('Hello, World!', 200);

    $middleware = new Etag;
    $response = $middleware->handle($request, fn (): Response => $response);

    expect($response->headers->get('ETag'))->toBe(md5('Hello, World!'));
});

it('should not apply ETag for unsuccessful requests', function (): void {
    $request = Request::create('/test', 'GET');
    $response = new Response('Not Found', 404);

    $middleware = new Etag;
    $response = $middleware->handle($request, fn (): Response => $response);

    expect($response->headers->has('ETag'))->toBeFalse();
});

it('should return 304 Not Modified if ETag matches the client request', function (): void {
    $request = Request::create('/test', 'GET');
    $request->headers->set('If-None-Match', md5('Hello, World!'));
    $response = new Response('Hello, World!', 200);

    $middleware = new Etag;
    $response = $middleware->handle($request, fn (): Response => $response);

    expect($response->getStatusCode())->toBe(304);
    expect($response->headers->get('ETag'))->toBe(md5('Hello, World!'));
});

it('should not apply ETag for non-GET or HEAD requests', function (): void {
    $request = Request::create('/test', 'POST');
    $response = new Response('Hello, World!', 200);

    $middleware = new Etag;
    $response = $middleware->handle($request, fn (): Response => $response);

    expect($response->headers->has('ETag'))->toBeFalse();
});

it('should not apply ETag for unsuccessful GET requests', function (): void {
    $request = Request::create('/test', 'GET');
    $response = new Response('Not Found', 404);

    $middleware = new Etag;
    $response = $middleware->handle($request, fn (): Response => $response);

    expect($response->headers->has('ETag'))->toBeFalse();
});
