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

it('weakens a strong ETag, because the body it labelled is no longer the body that arrived', function (): void {
    // An ETag validates the bytes the response carries, and a strong one says two responses with
    // the same tag are byte-identical — which compressing makes false. This encoder is the thing
    // that changes the body, so the weakening happens beside the change rather than in the caller.
    $result = app(BrotliEncoder::class)->handle(
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain', 'ETag' => '"abc123"'])
    );

    expect($result->headers->get('ETag'))->toBe('W/"abc123"');
});
