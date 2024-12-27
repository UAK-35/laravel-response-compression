<?php

namespace Chr15k\ResponseOptimizer\Encoders;

use Chr15k\ResponseOptimizer\Contracts\Encoder;
use Symfony\Component\HttpFoundation\Response;

final class BrotliEncoder implements Encoder
{
    public function handle(Response $response): Response
    {
        if (extension_loaded('brotli') && is_callable('brotli_compress')) {

            $compressed = brotli_compress((string) $response->getContent(), $this->level());

            if ($compressed) {
                $response->setContent($compressed);

                $response->headers->add([
                    'Content-Encoding' => 'br',
                    'Vary' => 'Accept-Encoding',
                    'Content-Length' => strlen($compressed),
                ]);
            }
        }

        return $response;
    }

    public function level(): int
    {
        $level = config('response-optimizer.compression.brotli.level');

        return is_int($level) && $level >= 0 && $level <= 11 ? $level : 5;
    }
}
