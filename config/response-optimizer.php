<?php

return [

    'cache' => [

        'control' => [

            /**
             * Enable or disable the cache control.
             */
            'enabled' => env('RESPONSE_OPTIMIZER_CACHE_ENABLED', true),

            /**
             * The cache control directive.
             */
            'directive' => env('RESPONSE_OPTIMIZER_CACHE_CONTROL_DIRECTIVE', 'public, max-age=31536000'),

        ],

    ],

    'compression' => [

        /**
         * Enable or disable the response compression.
         */
        'enabled' => env('RESPONSE_OPTIMIZER_COMPRESSION_ENABLED', true),

        /**
         * The compression algorithm to use. Can be either 'gzip' or 'br'.
         */
        'algorithm' => env('RESPONSE_OPTIMIZER_COMPRESSION_ALGORITHM', 'gzip'),

        /**
         * The minimum length of the response content to be compressed.
         */
        'min_length' => env('RESPONSE_OPTIMIZER_COMPRESSION_MIN_LENGTH', 1024),

        'gzip' => [
            /**
             * The level of compression. Can be given as 0 for no compression up to 9
             * for maximum compression. If not given, the default compression level will
             * be the default compression level of the zlib library.
             *
             * @see https://www.php.net/manual/en/function.gzencode.php
             */
            'level' => env('RESPONSE_OPTIMIZER_COMPRESSION_GZIP_LEVEL', 5),
        ],

        'br' => [
            /**
             * The level of compression. Can be given as 0 for no compression up to 11
             * for maximum compression. If not given, the default compression level will
             * be the default compression level of the brotli library.
             *
             * @see https://www.php.net/manual/en/function.brotli-compress.php
             */
            'level' => env('RESPONSE_OPTIMIZER_COMPRESSION_BROTLI_LEVEL', 5),
        ],

    ],

];
