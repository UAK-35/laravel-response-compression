<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Uak35\ResponseCompression\Middleware\CompressResponse;
use Uak35\ResponseCompression\Tests\Support\Notes;
use Uak35\ResponseCompression\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit')
    ->afterEach(function (): void {
        foreach (Notes::take() as $note) {
            fwrite(STDERR, $note.PHP_EOL);
        }
    });

expect()->extend('toBeOne', fn () => $this->toBe(1));

function runCompressResponseMiddleware(Request $request, Response $response): Response
{
    return app(CompressResponse::class)->handle($request, fn (): Response => $response);
}

function getShortContent(): string
{
    return 'Hello World';
}

function getLongContent(): string
{
    return str_repeat('Hello World', 1000);
}

function getShortHtmlContent(): string
{
    return '<html><head><title>Test</title></head><body><p>Test</p></body></html>';
}

/**
 * The package root, for the tests that read a file the package ships rather than a
 * fixture — the README and the config it publishes.
 */
function packageRoot(): string
{
    return dirname(__DIR__);
}

/**
 * Generate deterministic incompressible content.
 *
 * Uses a seeded PRNG to generate pseudo-random bytes that compress poorly.
 * Deterministic for reproducibility across test runs.
 */
function incompressibleContent(int $bytes = 2048): string
{
    mt_srand(42);
    $chunks = [];

    $remaining = $bytes;
    while ($remaining > 0) {
        $chunkSize = min(1024, $remaining);
        $chunk = '';
        for ($i = 0; $i < $chunkSize; $i++) {
            $chunk .= chr(mt_rand(0, 255));
        }
        $chunks[] = $chunk;
        $remaining -= $chunkSize;
    }

    mt_srand();

    return implode('', $chunks);
}
