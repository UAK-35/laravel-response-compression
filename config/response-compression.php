<?php

declare(strict_types=1);

return [

    /**
     * Enable or disable the response compression.
     */
    'enabled' => env('RESPONSE_COMPRESSION_ENABLED', true),

    /**
     * Enable or disable the response compression during testing.
     *
     * Enabled by default. A disabled default made `enabled()` return false for every
     * test, so no test could exercise compression at all: the suite asserted the
     * uncompressed body and passed, whatever the middleware did.
     */
    'enabled_for_testing' => env('RESPONSE_COMPRESSION_ENABLED_FOR_TESTING', true),

    /**
     * The compression algorithm to use. Can be either 'gzip', 'br' or 'zstd' if br or zstd extension is installed and enabled.
     */
    'algorithm' => env('RESPONSE_COMPRESSION_ALGORITHM', 'br'),

    /**
     * The minimum length of the response content to be compressed.
     */
    'min_length' => env('RESPONSE_COMPRESSION_MIN_LENGTH', 2048),

    'gzip' => [
        /**
         * The level of compression. Can be given as 0 for no compression up to 9
         * for maximum compression. If not given, the default compression level will
         * be the default compression level of the zlib library.
         *
         * @see https://www.php.net/manual/en/function.gzencode.php
         */
        'level' => env('RESPONSE_COMPRESSION_GZIP_LEVEL', 5),
    ],

    'br' => [
        /**
         * The level of compression. Can be given as 0 for no compression up to 11
         * for maximum compression. If not given, the default compression level will
         * be the default compression level of the brotli library.
         *
         * @see https://www.php.net/manual/en/function.brotli-compress.php
         */
        'level' => env('RESPONSE_COMPRESSION_BROTLI_LEVEL', 5),

        'non_supporting_user_agent_prefixes' => [
            'ELB-HealthChecker/',
            'PostmanRuntime/',
            'axios/',
            'Dart/',
        ],
    ],

    'zstd' => [
        /**
         * The level of compression. Can be given as 0 for no compression up to 22
         * for maximum compression. If not given, the default compression level will
         * be the default compression level of the zstd library.
         *
         * @see https://github.com/kjdev/php-ext-zstd
         */
        'level' => env('RESPONSE_COMPRESSION_ZSTD_LEVEL', 3),

        'non_supporting_user_agent_prefixes' => [
            'ELB-HealthChecker/',
            'PostmanRuntime/',
            'axios/',
            'Dart/',
            'IntelliJ HTTP Client/',
        ],
    ],

    'try_multiple_encodings' => env('RESPONSE_COMPRESSION_TRY_MULTIPLE_ENCODINGS', false),

    'multiple_encodings_order' => env('RESPONSE_COMPRESSION_MULTIPLE_ENCODINGS_ORDER', 'br,zstd,gzip'),

    /**
     * Enable or disable the debug logging of every compression decision.
     *
     * NOT YET WIRED - nothing reads this key. The middleware currently carries no logger,
     * so setting it changes nothing. It stays published because a host app may already set
     * it in .env, and removing a public key is a breaking change. See
     * docs/unwired-config.md.
     */
    'enable_logging' => env('RESPONSE_COMPRESSION_LOGGING', false),

];
