<?php

use Chr15k\ResponseOptimizer\Middleware\CacheControl;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('should set the cache control directive', function (): void {

    $middleware = app(CacheControl::class);

    $request = new Request;
    $response = new Response;

    $response = $middleware->handle($request, fn (): Response => $response);

    expect($response->headers->get('Cache-Control'))->toBe('max-age=31536000, public');
});

it('should not set the cache control directive if it is already set', function (): void {

    $middleware = app(CacheControl::class);

    $request = new Request;
    $response = new Response;

    $response->headers->set('Cache-Control', 'max-age=3600, private');

    $response = $middleware->handle($request, fn (): Response => $response);

    expect($response->headers->get('Cache-Control'))->toBe('max-age=3600, private');
});

it('should not set the cache control directive if the response is of code 4.x', function (): void {

    $middleware = app(CacheControl::class);

    $request = new Request;
    $response = new Response;

    $response->setStatusCode(404);

    $response = $middleware->handle($request, fn (): Response => $response);

    expect($response->headers->get('Cache-Control'))
        ->toBe('must-revalidate, no-cache, no-store, private');
});

it('should not set the cache control directive if the response is of code 5.x', function (): void {

    $middleware = app(CacheControl::class);

    $request = new Request;
    $response = new Response;

    $response->setStatusCode(500);

    $response = $middleware->handle($request, fn (): Response => $response);

    expect($response->headers->get('Cache-Control'))
        ->toBe('must-revalidate, no-cache, no-store, private');
});

it('should not set the cache control directive if the response is redirection', function (): void {

    $middleware = app(CacheControl::class);

    $request = new Request;
    $response = new Response;

    $response->setStatusCode(301);

    $response = $middleware->handle($request, fn (): Response => $response);

    expect($response->headers->get('Cache-Control'))->toBe('no-store, private');
});

it('should not set the cache control directive if the cache control is disabled', function (): void {

    config()->set('response-optimizer.cache.control.enabled', false);

    $middleware = app(CacheControl::class);

    $request = new Request;
    $response = new Response;

    $response = $middleware->handle($request, fn (): Response => $response);

    expect($response->headers->get('Cache-Control'))->toBe('no-cache, private');
});
