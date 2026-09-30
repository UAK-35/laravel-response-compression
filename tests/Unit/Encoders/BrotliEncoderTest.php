<?php

declare(strict_types=1);

/* @guards-index test */

use Symfony\Component\HttpFoundation\Response;
use Uak35\ResponseCompression\Encoders\BrotliEncoder;
use Uak35\ResponseCompression\Support\Enc;

it('should compress text response', function (): void {

    $result = app(BrotliEncoder::class)->handle(
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->headers->get('Content-Encoding'))->toBe('br')
        ->and($result->headers->get('Vary'))->toBe('Accept-Encoding')
        ->and($result->headers->get('Content-Length'))->toBeGreaterThan(0)
        ->and($result->getContent())->toBeString()
        ->and(Enc::isBrotliEncoded($result->getContent()))->toBeTrue();
});
