# Laravel Response Optimizer

Boost your Laravel application's performance by optimizing HTTP responses with middleware for compression and HTML minification.

## Key Features

**Compression**: Shrink response payloads using Gzip or Brotli compression.
**HTML Minification**: Reduce the size of HTML responses by eliminating unnecessary whitespace and formatting.

These optimizations work seamlessly with your existing Laravel application, requiring minimal setup.

---

## Installation

Install the package via Composer:

```bash
composer require chr15k/laravel-response-optimizer
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Chr15k\ResponseOptimizer\ResponseOptimizerServiceProvider"
```

---

## Middleware Overview

This package provides the following middleware:

### 1. Compression Middleware

Applies Gzip or Brotli compression to HTTP responses based on client support. This reduces the size of the response payload and enhances load times.

Ideal For: Large JSON responses, static files, or data-intensive endpoints.

### 2. HTML Minification Middleware

Minifies HTML responses by removing unnecessary whitespace and formatting to reduce payload size.

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
        \Chr15k\ResponseOptimizer\Middleware\MinifyHtml::class,
        \Chr15k\ResponseOptimizer\Middleware\CompressResponse::class,
    ]);
})
```

#### Route Middleware

Alternatively, register it as route middleware for selective application:

```php
use Chr15k\ResponseOptimizer\Middleware\CompressResponse;

Route::get('/profile', function () {
    // ...
})->middleware(CompressResponse::class);
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

The MIT License (MIT). Please see [License File](https://github.com/chr15k/laravel-response-optimizer/blob/main/LICENSE) for more information.
