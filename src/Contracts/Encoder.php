<?php

namespace Chr15k\ResponseOptimizer\Contracts;

use Symfony\Component\HttpFoundation\Response;

interface Encoder
{
    public function handle(Response $response): Response;
}
