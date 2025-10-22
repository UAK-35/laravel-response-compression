<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Response;
use Uak35\ResponseCompression\Encoders\GzipEncoder;
use Uak35\ResponseCompression\Support\Enc;

it('should compress text response', function (): void {

    $result = app(GzipEncoder::class)->handle(
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->getContent())->not()->toBe(getLongContent())
        ->and(Enc::isGzipEncoded($result->getContent()))->toBeTrue();
});
