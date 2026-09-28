<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Uak35\ResponseCompression\Support\Enc;

it('should compress text response', function (): void {

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->getContent())->not()->toBe(getLongContent())
        ->and(Enc::isGzipEncoded($result->getContent() ?: ''))->toBeTrue();
});

it('should compress json response', function (): void {
    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new JsonResponse([getShortContent() => getLongContent()]),
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->getContent())->not()->toBe('{"test":"test"}')
        ->and(Enc::isGzipEncoded($result->getContent() ?: ''))->toBeTrue();
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
        ->and(Enc::isGzipEncoded($result->getContent() ?: ''))->toBeFalse();
});

it('should not compress response without gzip header', function (): void {

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET'),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getContent())->toBe(getLongContent())
        ->and(Enc::isGzipEncoded($result->getContent() ?: ''))->toBeFalse();
});

it('should not compress streamed response', function (): void {

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new StreamedResponse(fn (): string => 'ok')
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->headers->get('Content-Encoding'))->toBeNull()
        ->and(Enc::isGzipEncoded($result->getContent() ?: ''))->toBeFalse();
});

it('should not compress if response is binary file', function (): void {

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new BinaryFileResponse(__DIR__.'/test.txt')
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getFile()->getContent())->toBe('test')
        ->and(Enc::isGzipEncoded($result->getContent() ?: ''))->toBeFalse();
});

it('should not compress if response is not successful', function (): void {

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new Response('error', 500)
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR)
        ->and($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getContent())->toBe('error')
        ->and(Enc::isGzipEncoded($result->getContent() ?: ''))->toBeFalse();
});

it('should not compress if the content is below the configured minimum length', function (): void {

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new Response(getShortContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getContent())->toBe(getShortContent())
        ->and(Enc::isGzipEncoded($result->getContent() ?: ''))->toBeFalse();
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
        ->and(Enc::isGzipEncoded($result->getContent() ?: ''))->toBeTrue();
});

it('should zstd compress text response', function (): void {
    config()->set('response-compression.algorithm', 'zstd');

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'zstd']),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->getContent())->not()->toBe(getLongContent())
        ->and(Enc::isZstdEncoded($result->getContent()))->toBeTrue();
});

it('should zstd compress json response', function (): void {
    config()->set('response-compression.algorithm', 'zstd');

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'zstd']),
        new JsonResponse([getShortContent() => getLongContent()]),
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->getContent())->not()->toBe('{"test":"test"}')
        ->and(Enc::isZstdEncoded($result->getContent()))->toBeTrue();
});

it('should not compress when compression is disabled', function (): void {
    config()->set('response-compression.enabled', false);

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getContent())->toBe(getLongContent());
});

it('should compress for a client that accepts any encoding', function (): void {
    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => '*']),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->headers->get('Content-Encoding'))->toBe('gzip')
        ->and(Enc::isGzipEncoded($result->getContent() ?: ''))->toBeTrue();
});

it('should not compress for a client that accepts only another encoding', function (): void {
    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'deflate']),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getContent())->toBe(getLongContent());
});

it('should not compress for a user agent that cannot decode brotli', function (): void {
    config()->set('response-compression.algorithm', 'br');

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], [
            'HTTP_ACCEPT_ENCODING' => 'br',
            'HTTP_USER_AGENT' => 'axios/1.7.2',
        ]),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getContent())->toBe(getLongContent());
});

it('should use the first configured encoding the client accepts', function (): void {
    config()->set('response-compression.try_multiple_encodings', true);
    config()->set('response-compression.multiple_encodings_order', 'br,zstd,gzip');
    config()->set('response-compression.algorithm', 'br');

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'br']),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->headers->get('Content-Encoding'))->toBe('br')
        ->and(Enc::isBrotliEncoded($result->getContent()))->toBeTrue();
});

it('should skip a configured encoding the client does not accept', function (): void {
    config()->set('response-compression.try_multiple_encodings', true);
    config()->set('response-compression.multiple_encodings_order', 'br,zstd');
    config()->set('response-compression.algorithm', 'zstd');

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'zstd']),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->headers->get('Content-Encoding'))->toBe('zstd')
        ->and(Enc::isZstdEncoded($result->getContent()))->toBeTrue();
});

it('should leave the response alone when no configured encoding is accepted', function (): void {
    config()->set('response-compression.try_multiple_encodings', true);
    config()->set('response-compression.multiple_encodings_order', 'br');

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getContent())->toBe(getLongContent());
});

it('should not compress when the configured algorithm is not one it can encode', function (): void {
    config()->set('response-compression.algorithm', 'lz4');

    $result = runCompressResponseMiddleware(
        Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => '*']),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->headers->get('Content-Encoding'))->toBeNull()
        ->and($result->getContent())->toBe(getLongContent());
});

/*
 * The switch is a diagnostic, so both of these matter: one line per decision while it is on, and
 * not one line while it is off. Without the second, a logger that had gone inert again — the state
 * `enable_logging` was published in, see docs/unwired-config.md — would keep every test green.
 *
 * `Log` is spied rather than written to: the assertions are about the messages this middleware
 * composes, and a host app's log file is not a place to read them back from.
 */
it('should log every compression decision when enable_logging is on', function (): void {
    config()->set('response-compression.enable_logging', true);
    Log::spy();

    $result = runCompressResponseMiddleware(
        Request::create('/api/trips', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    // Still compressed: the switch decides whether a line is written, never what the middleware
    // does with the response.
    expect($result->headers->get('Content-Encoding'))->toBe('gzip');

    // The two ends of one request's path: that compression was considered, and which encoding it
    // settled on. Both name the request, which is the part that makes the line worth writing.
    Log::shouldHaveReceived('debug')
        ->withArgs(fn (string $message, array $context = []): bool => $message === '[COMPR-RESP] Response compression checking - uri: /api/trips')
        ->once();

    Log::shouldHaveReceived('debug')
        ->withArgs(fn (string $message, array $context = []): bool => $message === '[COMPR-RESP] >> Compressing response using first available encoding = gzip - uri: /api/trips')
        ->once();
});

it('should write no log line at all when enable_logging is off', function (): void {
    // Off is the shipped default, and setting it here rather than relying on it is the point: a
    // test that inherits the default stops testing it the moment the default moves.
    config()->set('response-compression.enable_logging', false);
    Log::spy();

    $result = runCompressResponseMiddleware(
        Request::create('/api/trips', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->headers->get('Content-Encoding'))->toBe('gzip');

    // The same request as the test above, decision for decision: the only difference is the flag.
    Log::shouldNotHaveReceived('debug');
});
