<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Encoders;

use Symfony\Component\HttpFoundation\Response;
use Uak35\ResponseCompression\Contracts\Encoder;
use Uak35\ResponseCompression\Support\Config;

final class ZstdEncoder implements Encoder
{
    public function handle(Response $response): Response
    {
        if (extension_loaded('zstd')) {

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

    /**
     * The configured level, or the documented default of 3 when the value is outside the
     * range `zstd_compress()` accepts. A level that cannot be read at all is refused
     * rather than defaulted — see Uak35\ResponseCompression\Support\Config.
     */
    public function level(): int
    {
        return Config::intInRange('response-compression.zstd.level', 1, 22, 3);
    }
}
