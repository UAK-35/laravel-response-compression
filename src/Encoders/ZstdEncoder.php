<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Encoders;

use Symfony\Component\HttpFoundation\Response;
use Uak35\ResponseCompression\Contracts\Encoder;

final class ZstdEncoder implements Encoder
{
    /**
     * @param Response $response
     * @return Response
     */
    public function handle(Response $response): Response
    {
        if (extension_loaded('zstd') && is_callable('zstd_compress')) {

            $compressed = zstd_compress((string) $response->getContent(), $this->level());

            if ($compressed) {
                $response->setContent($compressed);

                $response->headers->add([
                    'Content-Encoding' => 'zstd',
                    'Vary' => 'Accept-Encoding',
                    'Content-Length' => strlen($compressed),
                ]);
            }
        }

        return $response;
    }

    public function level(): int
    {
        $level = config('response-compression.zstd.level');

        return is_int($level) && $level >= 1 && $level <= 22 ? $level : 3;
    }
}
