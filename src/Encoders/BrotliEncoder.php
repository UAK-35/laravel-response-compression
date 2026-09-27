<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Encoders;

use Symfony\Component\HttpFoundation\Response;
use Uak35\ResponseCompression\Contracts\Encoder;
use Uak35\ResponseCompression\Support\Config;

final class BrotliEncoder implements Encoder
{
    public function handle(Response $response): Response
    {
        if (extension_loaded('brotli')) {

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

    /**
     * The configured level, or the documented default of 5 when the value is outside the
     * range `brotli_compress()` accepts. A level that cannot be read at all is refused
     * rather than defaulted — see Uak35\ResponseCompression\Support\Config.
     *
     * Read from `response-compression.br.level`: `br` is the section this encoder's
     * settings live under, and the name the middleware uses for this algorithm. This
     * method used to ask for `response-compression.brotli.level`, which is not a key, so
     * the lookup returned null on every call and the level could never be anything but
     * the default whatever the config or `.env` said.
     */
    public function level(): int
    {
        return Config::intInRange('response-compression.br.level', 0, 11, 5);
    }
}
