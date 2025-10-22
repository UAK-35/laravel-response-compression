<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Contracts;

use Symfony\Component\HttpFoundation\Response;

/**
 * The encoder contract.
 */
interface Encoder
{
    /**
     * Handle the response compression.
     */
    public function handle(Response $response): Response;

    /**
     * Get the compression level.
     */
    public function level(): int;
}
