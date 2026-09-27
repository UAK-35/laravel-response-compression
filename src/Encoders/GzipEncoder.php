<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Encoders;

use Symfony\Component\HttpFoundation\Response;
use Uak35\ResponseCompression\Contracts\Encoder;
use Uak35\ResponseCompression\Support\Config;

final class GzipEncoder implements Encoder
{
    public function handle(Response $response): Response
    {
        $compressed = gzencode((string) $response->getContent(), $this->level());

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

    /**
     * The configured level, or the documented default of 5 when the value is outside the
     * range `gzencode()` accepts. A level that cannot be read at all is refused rather
     * than defaulted — see Uak35\ResponseCompression\Support\Config.
     */
    public function level(): int
    {
        return Config::intInRange('response-compression.gzip.level', -1, 9, 5);
    }
}
