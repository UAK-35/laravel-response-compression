<?php

use Chr15k\ResponseCompression\Support\Enc;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

it('should compress text response', function (): void {

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->getContent())->not()->toBe(getLongContent())
        ->and(Enc::isGzipEncoded($result->getContent()))->toBeTrue();
});

it('should compress json response', function (): void {
    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new JsonResponse([getShortContent() => getLongContent()]),
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->getContent())->not()->toBe('{"test":"test"}')
        ->and(Enc::isGzipEncoded($result->getContent()))->toBeTrue();
});

it('should not compress json response without encoding header', function (): void {

    $content = [getShortContent() => getLongContent()];

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], []),
        new JsonResponse($content),
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getContent())->toBe(json_encode($content))
        ->and(Enc::isGzipEncoded($result->getContent()))->toBeFalse();
});

it('should not compress response without gzip header', function (): void {

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET'),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getContent())->toBe(getLongContent())
        ->and(Enc::isGzipEncoded($result->getContent()))->toBeFalse();
});

it('should not compress streamed response', function (): void {

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new StreamedResponse(fn (): string => 'ok')
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->headers->get('Content-Encoding'))->toBeNull()
        ->and(Enc::isGzipEncoded($result->getContent()))->toBeFalse();
});

it('should not compress if response is binary file', function (): void {

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new BinaryFileResponse(__DIR__.'/test.txt')
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getFile()->getContent())->toBe('test')
        ->and(Enc::isGzipEncoded($result->getContent()))->toBeFalse();
});

it('should not compress if response is not successful', function (): void {

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new Response('error', 500)
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getContent())->toBe('error')
        ->and(Enc::isGzipEncoded($result->getContent()))->toBeFalse();
});

it('should not compress if the content is below the configured minimum length', function (): void {

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new Response(getShortContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getContent())->toBe(getShortContent())
        ->and(Enc::isGzipEncoded($result->getContent()))->toBeFalse();
});

it('should brotli compress text response', function (): void {

    config()->set('response-compression.algorithm', 'br');

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'br']),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->getContent())->not()->toBe(getLongContent())
        ->and(Enc::isBrotliEncoded($result->getContent()))->toBeTrue();
});

it('should brotli compress json response', function (): void {

    config()->set('response-compression.algorithm', 'br');

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'br']),
        new JsonResponse([getShortContent() => getLongContent()]),
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->getContent())->not()->toBe('{"test":"test"}')
        ->and(Enc::isBrotliEncoded($result->getContent()))->toBeTrue();
});

it('should not affect pre-compressed content', function (): void {

    $content = gzencode(getLongContent());

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new Response($content, 200, ['Content-Type' => 'text/plain', 'Content-Encoding' => 'gzip'])
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->getContent())->toBe($content)
        ->and(Enc::isGzipEncoded($result->getContent()))->toBeTrue();
});
