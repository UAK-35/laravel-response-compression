# Laravel Response Compression

[![Latest Stable Version](https://poser.pugx.org/uak35/laravel-response-compression/v)](https://packagist.org/packages/uak35/laravel-response-compression) [![Total Downloads](https://poser.pugx.org/uak35/laravel-response-compression/downloads)](https://packagist.org/packages/uak35/laravel-response-compression) [![Latest Unstable Version](https://poser.pugx.org/uak35/laravel-response-compression/v/unstable)](https://packagist.org/packages/uak35/laravel-response-compression) [![License](https://poser.pugx.org/uak35/laravel-response-compression/license)](https://packagist.org/packages/uak35/laravel-response-compression) [![PHP Version Require](https://poser.pugx.org/uak35/laravel-response-compression/require/php)](https://packagist.org/packages/uak35/laravel-response-compression)

Boost your Laravel application's performance by optimizing HTTP responses with middleware for compression.

---

## Installation

Install the package via Composer:

```bash
composer require uak35/laravel-response-compression
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Uak35\ResponseCompression\ResponseCompressionServiceProvider"
```

---

## Middleware Overview

This package provides the following middleware:

#### Compression Middleware

Applies **Gzip**, **Brotli**, or **Zstd** compression to HTTP responses based on client support. This reduces the size of the response payload and enhances load times.

**Ideal For**: Large JSON responses, static files, or data-intensive endpoints.

> [!NOTE]
> To use Brotli effectively, ensure that the Brotli PHP extension is properly installed.
> https://pecl.php.net/package/brotli

> [!WARNING]
> When using Brotli, a client-side decoding error may occur with non-secure connections, as modern browsers generally support Brotli compression only over HTTPS.

> [!NOTE]
> To use Zstandard (ZSTD) effectively, ensure that the ZSTD PHP extension is properly installed.
> https://pecl.php.net/package/zstd

---

## Setup

### Register Middleware

#### Global Middleware

Apply the middleware globally to all requests:

```php
// bootstrap/app.php

->withMiddleware(function (Middleware $middleware) {
    ...
    $middleware->web(append: [
        ...
        \Uak35\ResponseCompression\Middleware\CompressResponse::class,
    ]);
})
```

#### Route Middleware

Alternatively, register it as route middleware for selective application:

```php
use Uak35\ResponseCompression\Middleware\CompressResponse;

Route::get('/profile', function () {
    // ...
})->middleware(CompressResponse::class);
```

---

## Config

```php
/**
 * Enable or disable the response compression.
 */
'enabled' => env('RESPONSE_COMPRESSION_ENABLED', true),

/**
 * The compression algorithm to use. Can be either 'gzip' or 'br'.
 */
'algorithm' => env('RESPONSE_COMPRESSION_ALGORITHM', 'gzip'),

/**
 * The minimum length of the response content to be compressed.
 */
'min_length' => env('RESPONSE_COMPRESSION_MIN_LENGTH', 1024),

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

'multiple_encodings_order' => env('RESPONSE_COMPRESSION_MULTIPLE_ENCODINGS_ORDER', 'br,zstd,gzip')

```

---

## Testing

```bash
composer test
```

---

## Contributing

Contributions are welcome! Submit a pull request or open an issue to discuss new features or improvements.

---

## License

The MIT License (MIT). Please see [License File](https://github.com/uak35/laravel-response-compression/blob/main/LICENSE) for more information.
