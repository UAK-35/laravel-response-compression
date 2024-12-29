<?php

use Chr15k\ResponseCompression\Support\Enc;

it('is gzip encoded', function (): void {

    $content = gzencode('Hello World', 9);

    expect(Enc::isGzipEncoded($content))->toBeTrue();
});

it('is not gzip encoded', function (): void {

    $content = 'Hello World';

    expect(Enc::isGzipEncoded($content))->toBeFalse();
});

it('is brotli encoded', function (): void {

    $content = brotli_compress('Hello World', 11);

    expect(Enc::isBrotliEncoded($content))->toBeTrue();
});

it('is not brotli encoded', function (): void {

    $content = 'Hello World';

    expect(Enc::isBrotliEncoded($content))->toBeFalse();
});

it('is not brotli encoded when empty', function (): void {

    $content = '';

    expect(Enc::isBrotliEncoded($content))->toBeFalse();
});

it('is deflate encoded', function (): void {

    $content = gzdeflate('Hello World', 9);

    expect(Enc::isDeflateEncoded($content))->toBeTrue();
});

it('is not deflate encoded', function (): void {

    $content = 'Hello World';

    expect(Enc::isDeflateEncoded($content))->toBeFalse();
});
