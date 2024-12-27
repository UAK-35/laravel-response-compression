<?php

namespace Chr15k\ResponseOptimizer\Support;

class Enc
{
    public static function isGzipEncoded(string $content): bool
    {
        return str_starts_with($content, "\x1f\x8b");
    }

    public static function isBrotliEncoded(string $data): bool
    {
        if ($data === '' || $data === '0') {
            return false;
        }

        try {
            $uncompressed = brotli_uncompress($data);

            return ! in_array($uncompressed, ['', '0'], true) && $uncompressed !== false;
        } catch (\Exception|\Error) {
            return false;
        }
    }

    public static function isDeflateEncoded(string $content): bool
    {
        $decompressed = @gzinflate($content);

        return $decompressed !== false;
    }
}
