<?php

namespace Chr15k\ResponseOptimizer\Contracts;

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
