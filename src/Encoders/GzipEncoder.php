<?php

namespace Chr15k\ResponseOptimizer\Encoders;

use Chr15k\ResponseOptimizer\Contracts\Encoder;
use Symfony\Component\HttpFoundation\Response;

final class GzipEncoder implements Encoder
{
    public function handle(Response $response): Response
    {
        $compressed = gzencode((string) $response->getContent(), $this->getGzipLevel());

        if ($compressed) {
            $response->setContent($compressed);

            $response->headers->add([
                'Content-Encoding' => 'gzip',
                'Vary' => 'Accept-Encoding',
                'Content-Length' => strlen($compressed),
            ]);
        }

        return $response;
    }

    private function getGzipLevel(): int
    {
        $level = config('response-optimizer.compression.gzip.level');

        return is_int($level) && $level >= -1 && $level <= 9 ? $level : 5;
    }
}
