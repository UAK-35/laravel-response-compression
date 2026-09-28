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
 * Enable or disable the response compression during testing. On by default so that the
 * suite exercises compression, and so that a host app's own test run does too.
 */
'enabled_for_testing' => env('RESPONSE_COMPRESSION_ENABLED_FOR_TESTING', true),

/**
 * The compression algorithm to use. Can be either 'gzip' or 'br' or 'zstd' if br or zstd extension is installed and enabled.
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
 * Off by default, and a diagnostic rather than a feature: the middleware runs on every API
 * response, so this switch decides whether a line is written for each decision it makes -
 * not enabled, not allowed for this request, skipped and why, and which encoding was used
 * - most of them naming the request. See docs/unwired-config.md. Deliberately not part of
 * the boot check in Config::validate(): a line in a log is not worth stopping an
 * application from starting.
 */
'enable_logging' => env('RESPONSE_COMPRESSION_LOGGING', false),

```

If zstd compression is available on both server and frontend side (when laravel is used as a backend) then you can
change the "multiple_encodings_order" to "zstd,br,gzip". For a standalone laravel app that hosts both the frontend and
backend, you can also change the "multiple_encodings_order" to "br,zstd,gzip". But, for both cases, you need to have
"ext-zstd" enabled in require block of composer.json and zstd system library (libzstd) installed on the system.

Remember to remove "ext-zstd" from require block of composer.json if you don't want to use zstd compression or it is not
available on the system.

---

## Design records

Decisions that are not obvious from the code — each with the alternatives that were
rejected, and the evidence behind the answer:

- [What should a request that names no encoding get?](docs/missing-accept-encoding.md)
- [Which encoding should the default order try first?](docs/encoding-negotiation.md)
- [How do `algorithm` and `try_multiple_encodings` relate?](docs/single-vs-multiple-encodings.md)
- [Why refuse a client by user-agent prefix?](docs/non-supporting-user-agents.md)
- [What is the smallest body worth compressing?](docs/min-length.md)
- [What should an out-of-range compression level do?](docs/level-clamping.md)
- [Why is there a second switch for tests?](docs/enabled-for-testing.md)
- [What does a value read from `.env` actually arrive as?](docs/env-types.md)
- [What should a published config key that nothing reads do?](docs/unwired-config.md)
- [What should happen to a configuration value that cannot be read?](docs/config-reading.md)
- [Which guards does this package keep, and which did it refuse?](docs/guards.md)

Releasing and pushing have files of their own: [RELEASING.md](RELEASING.md) for what a
version *is*, and [PUSHING.md](PUSHING.md) for getting it to the remote.

---

## Testing

```bash
composer checks        # the gate: nine checks, one summary, one exit code
composer test          # rector --dry-run, pint --test, phpstan, pest
composer test:unit     # the floor: 100% per src file, a ratchet per script in bin/
git config core.hooksPath .githooks   # once per clone: check what a commit carries
```

`composer checks` runs `bin/checks.php`, which is what CI runs and what to run before a
release. It is nine checks in one process: `php -l` over every PHP file, an independent
AST parse of the same files by nikic/php-parser, `composer validate --strict`,
`composer check-platform-reqs`, `yaml-lint` over `.github/`, PHPStan, Pint, Rector and
Pest. `--list` names them, `--only=` runs a subset, `--verbose` streams their output, and
`--require-all` turns a missing tool from a skip into a failure. Where `composer` is not on
`PATH` — a checkout whose Composer is the `composer.phar` beside it — the same commands run as
`php composer.phar <script>`, which is how the gate reaches Composer itself.

It calls each tool as `PHP_BINARY <entry file>` rather than through `vendor/bin`, so a
checkout whose `vendor/` was installed on another platform still runs the same way: a
shim is a shell script on Unix and a `.bat` on Windows, and neither is reliably
executable from another process.

The scripts hold to the same rule, and none of them writes a path down. A composer script is a
shell string and cannot read a manifest, so the one command a script contains that runs a tool
names `bin/tool.php` and the tool — `@php bin/tool.php pint` — and `bin/tool.php` looks the path
up in `bin/tool-paths.php`, which is the only file in this repository where a tool's path is
written. `bin/checks.php` reads the same manifest, so the gate and the scripts cannot disagree
about which file a tool is; the two copies that could are gone.

What the manifest holds is the entry file inside `vendor/`, never the `vendor/bin` shim beside
it, and `@php` is the interpreter Composer is running under — so a tool runs under the PHP that
installed the dependencies and whose platform requirements the gate checks, rather than
whichever `php` is first on `PATH`. On a machine with two PHP installs that is the whole
difference between a pass and a failure: `composer test:unit` through a `vendor/bin` shim
reported that no coverage driver was available where the gate's own Pest run covered 100.0%.
Naming the entry file outright also settles what a shim is for — it is generated for the
platform that installed the dependencies, so one generated for another platform is not a way to
reach the tool. `tests/Unit/Support/ToolPathsTest.php` reads the manifest and everything that
names a tool, and fails on a path written down a second time — in a script, or in one of the
three programs that run a tool — on a `vendor/bin` shim, on a path that is not on disk, and on a
name nothing has or nothing runs.

It runs Pest *without* coverage on purpose. The same gate is run on machines that have no
coverage driver, where a report would fail the whole run for a reason about the machine
rather than about the code. The floor therefore lives in `composer test:unit`, which CI runs
as a step of its own after the gate, and which is `bin/coverage.php` rather than Pest: every
file in `src/` at 100.0% on its own, and each script in `bin/` at a recorded floor that may
be raised and not lowered. Two files in `bin/` are outside the report — the runner, which is
the parent of the run it reads, and the manifest, which is a list rather than a script — and
the guard record above says which is which and why.

The two scripts cannot be measured from inside a test — they run on include, they call
`exit()`, and the release one commits and tags — so the suite reaches them as child
processes, and a child's coverage is not in the parent's report. Pest is therefore run with
a collector in front of every script the suite spawns, the coverage each child writes is
mapped back onto the file it was copied from by comparing contents rather than names, and
those lines are added to the report before the floor is read. Before that, both scripts
read 0.0% while sixty tests drove one of them end to end.

The run names the machine it is on before it starts, because neither half of that is in the
number. The PHP: its version, its path, and whether Composer is what started it — `@php` is
Composer's own binary, so `composer test:unit` measures under a different interpreter than a
run typed into a shell does. And the driver, or the sentence saying there is none and the one
line that would give this machine one. Both are printed at the top of every run, and repeated
at the foot of the two failures that are about a floor, so a red build says which PHP it was.
A machine with no driver is named rather than refused: the process that measures is the suite,
started as a child with an ini of its own, so a run begun with `-n` has no driver here while
the run it starts has one.

`.githooks/pre-commit` runs `bin/checks.php --staged`: the same tools pointed at the files a
commit carries — syntax, Pint, and the docs-link test when markdown is staged — so those
three cannot land broken. Enable it once per clone with `git config core.hooksPath .githooks`,
and bypass it for one commit with `git commit --no-verify`. It looks for PHP on `PATH`, in
`$RESPONSE_COMPRESSION_PHP`, or in `git config response-compression.php "<path to php>"`, and
skips — saying so — rather than blocking when it finds none.

---

## Contributing

Contributions are welcome! Submit a pull request or open an issue to discuss new features or improvements.

---

## License

The MIT License (MIT). Please see [License File](https://github.com/uak35/laravel-response-compression/blob/main/LICENSE) for more information.
