# Laravel Response Optimizer

Enhance your Laravel application's performance by optimizing HTTP responses with middleware for ETags, compression, and cache control.

## Introduction

Laravel Response Optimizer is a powerful package that leverages middleware to improve the delivery and efficiency of your application's HTTP responses. By implementing key optimizations like ETags, compression, and cache control headers, it reduces bandwidth usage and improves the client-side experience.

### Key Features

-   **ETags**: Enables efficient caching and validation of responses.
-   **Compression**: Reduces the size of response payloads using Gzip or Brotli compression.
-   **Cache Control**: Adds headers to manage response caching effectively.

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

The package includes the following middleware:

### 1. ETag Middleware

Generates ETag headers for HTTP responses to enable efficient client-side caching. Clients can use the If-None-Match header to determine if the resource has changed, reducing bandwidth usage.

-   Use Case: Ideal for APIs and static content where responses may remain unchanged for multiple requests.

### 2. Compression Middleware

Compresses HTTP responses using Gzip or Brotli, depending on the client's capabilities. This significantly reduces the size of response payloads, improving load times.

-   Use Case: Perfect for large JSON responses, static content, or any data-heavy endpoints.

### 3. Cache Control Middleware

Adds Cache-Control headers to responses, instructing clients and proxies on how to cache responses.

-   Use Case: Ensures predictable caching behavior for dynamic and static content.

---

## Setup

### Register Middleware

To enable the middleware, register it in app/Http/Kernel.php:

#### Global Middleware

Apply the middleware globally to all requests:

```php
// bootstrap/app.php

->withMiddleware(function (Middleware $middleware) {
    ...
    $middleware->web(append: [
        ...
        \Chr15k\ResponseOptimizer\Middleware\ETag::class,
        \Chr15k\ResponseOptimizer\Middleware\CompressResponse::class,
        \Chr15k\ResponseOptimizer\Middleware\CacheControl::class,
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

---
