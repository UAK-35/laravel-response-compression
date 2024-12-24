<?php

use Chr15k\ResponseOptimizer\Encoders\GzipEncoder;
use Symfony\Component\HttpFoundation\Response;

it('should compress text response', function (): void {

    $result = app(GzipEncoder::class)->handle(
        new Response(getLongContent(), 200, ['Content-Type' => 'text/plain'])
    );

    expect($result->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($result->getContent())->not()->toBe(getLongContent())
        ->and(isGzipEncoded($result->getContent()))->toBeTrue();
});
