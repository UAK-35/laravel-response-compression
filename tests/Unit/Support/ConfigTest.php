<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Uak35\ResponseCompression\Encoders\BrotliEncoder;
use Uak35\ResponseCompression\Encoders\GzipEncoder;
use Uak35\ResponseCompression\Encoders\ZstdEncoder;
use Uak35\ResponseCompression\Exceptions\InvalidConfigurationException;
use Uak35\ResponseCompression\Support\Config;

/**
 * `Config::validate()` as a callable, so an expectation can be made about a call that
 * returns nothing.
 */
function validateConfiguration(): void
{
    Config::validate();
}

/*
|--------------------------------------------------------------------------
| Reading the configuration
|--------------------------------------------------------------------------
|
| Every value this package reads used to be replaced with a default when it could not be
| read, and the two fallbacks that mattered were both wrong in the same direction:
| `min_length` became 0 — so everything was compressed — and a level became the documented
| default, so setting it did nothing. Nothing reported either.
|
| So these tests hold two lines rather than one. A value the key requires is *read*,
| including the digit string `.env` actually produces; a value that is not is *refused*,
| with the key and the value in the message. The digit string is the case worth being
| precise about: refusing every string would break the package's own published `env()`
| defaults, and accepting every string would be the silent substitution over again.
|
*/

it('reads an integer', function (): void {
    config()->set('response-compression.min_length', 4096);

    expect(Config::int('response-compression.min_length'))->toBe(4096);
});

it('reads the digit string a value from .env arrives as', function (): void {
    // What env('RESPONSE_COMPRESSION_MIN_LENGTH', 2048) returns for `=4096`. See
    // docs/env-types.md for the evidence, and test below for why this is read rather
    // than refused.
    config()->set('response-compression.min_length', '4096');

    expect(Config::int('response-compression.min_length'))->toBe(4096);
});

it('refuses a string that is not a number rather than truncating it to one', function (): void {
    config()->set('response-compression.min_length', '4096a');

    expect(fn (): int => Config::int('response-compression.min_length'))
        ->toThrow(
            InvalidConfigurationException::class,
            "response-compression.min_length is set to the string '4096a', which is not an integer.",
        );
});

it('refuses an integer key that is not set at all', function (): void {
    config()->offsetUnset('response-compression.min_length');

    expect(fn (): int => Config::int('response-compression.min_length'))
        ->toThrow(InvalidConfigurationException::class, 'response-compression.min_length is not set.');
});

it('takes the default only when the key is absent', function (): void {
    config()->offsetUnset('response-compression.min_length');

    expect(Config::intOr('response-compression.min_length', 1024))->toBe(1024);
});

it('refuses a present value even where a default is offered', function (): void {
    config()->set('response-compression.min_length', []);

    expect(fn (): int => Config::intOr('response-compression.min_length', 1024))
        ->toThrow(InvalidConfigurationException::class, 'is set to an array of 0 item(s)');
});

it('takes the documented default for a level outside the library range', function (): void {
    // Readable, and merely wrong: 40 is a typo, and the libraries each answer a typo
    // differently. docs/level-clamping.md holds that decision.
    config()->set('response-compression.gzip.level', 40);

    expect(Config::intInRange('response-compression.gzip.level', -1, 9, 5))->toBe(5);
});

it('refuses a level that cannot be read', function (): void {
    config()->set('response-compression.gzip.level', 'high');

    expect(fn (): int => Config::intInRange('response-compression.gzip.level', -1, 9, 5))
        ->toThrow(InvalidConfigurationException::class, "the string 'high'");
});

it('reads the booleans env() hands over', function (string $written, bool $expected): void {
    config()->set('response-compression.enabled', $written);

    expect(Config::boolOr('response-compression.enabled', false))->toBe($expected);
})->with([
    ['true', true],
    ['false', false],
    ['1', true],
    ['0', false],
    ['on', true],
    ['off', false],
    ['yes', true],
    ['no', false],
    ['', false],
]);

it('refuses a boolean that is none of those', function (): void {
    // filter_var() alone answers `false` here, which would silently disable compression
    // for a config typo nobody would ever see.
    config()->set('response-compression.try_multiple_encodings', 'maybe');

    expect(fn (): bool => Config::boolOr('response-compression.try_multiple_encodings', false))
        ->toThrow(InvalidConfigurationException::class, "is set to the string 'maybe', which is not a boolean.");
});

it('refuses a string key that holds an array', function (): void {
    config()->set('response-compression.algorithm', ['br', 'gzip']);

    expect(fn (): string => Config::stringOr('response-compression.algorithm', 'gzip'))
        ->toThrow(InvalidConfigurationException::class, 'is set to an array of 2 item(s), which is not a string.');
});

it('splits a comma-separated list, dropping the empty items', function (): void {
    config()->set('response-compression.multiple_encodings_order', ' br , , zstd ,gzip ');

    expect(Config::commaList('response-compression.multiple_encodings_order', 'br,zstd,gzip'))
        ->toBe(['br', 'zstd', 'gzip']);
});

it('refuses a comma-separated list that is not a string', function (): void {
    config()->set('response-compression.multiple_encodings_order', ['br', 'zstd']);

    expect(fn (): array => Config::commaList('response-compression.multiple_encodings_order', 'br,zstd,gzip'))
        ->toThrow(InvalidConfigurationException::class, 'is not a string');
});

it('takes the default for a user agent prefix list that is absent', function (): void {
    // `gzip` ships no prefix list, so absent is the normal case rather than a fault.
    config()->offsetUnset('response-compression.gzip.non_supporting_user_agent_prefixes');

    expect(Config::stringListOr('response-compression.gzip.non_supporting_user_agent_prefixes', []))
        ->toBe([]);
});

it('refuses a user agent prefix list with a member that is not a string', function (): void {
    config()->set('response-compression.br.non_supporting_user_agent_prefixes', ['axios/', null]);

    expect(fn (): array => Config::stringListOr('response-compression.br.non_supporting_user_agent_prefixes', []))
        ->toThrow(InvalidConfigurationException::class, 'is not a list of strings');
});

it('accepts the configuration the package ships', function (): void {
    expect(validateConfiguration(...))->not->toThrow(InvalidConfigurationException::class);
});

it('refuses the whole configuration when one value cannot be read', function (): void {
    config()->set('response-compression.min_length', 'nope');

    expect(validateConfiguration(...))
        ->toThrow(InvalidConfigurationException::class, 'response-compression.min_length');
});

it('keeps the logger switch out of the boot check', function (): void {
    // `enable_logging` is read by the middleware rather than at boot (docs/unwired-config.md),
    // so a value that cannot be read must not be able to stop the application from starting.
    config()->set('response-compression.enable_logging', 'whatever');

    expect(validateConfiguration(...))->not->toThrow(InvalidConfigurationException::class);
});

/*
|--------------------------------------------------------------------------
| The readers the package actually uses
|--------------------------------------------------------------------------
*/

it('honours a floor set as a digit string', function (): void {
    // The defect this replaces: `is_int('4096')` is false, so the floor became 0 and this
    // response was compressed even though it is well under the configured 4096.
    config()->set('response-compression.algorithm', 'gzip');
    config()->set('response-compression.min_length', '4096');

    $response = runCompressResponseMiddleware(
        Request::create('/test', 'GET', server: ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new Response(str_repeat('a', 3000)),
    );

    expect($response->headers->get('Content-Encoding'))->toBeNull();
});

it('stops the response when the floor cannot be read', function (): void {
    config()->set('response-compression.algorithm', 'gzip');
    config()->set('response-compression.min_length', 'huge');

    expect(fn (): Response => runCompressResponseMiddleware(
        Request::create('/test', 'GET', server: ['HTTP_ACCEPT_ENCODING' => 'gzip']),
        new Response(getLongContent()),
    ))->toThrow(InvalidConfigurationException::class, 'response-compression.min_length');
});

it('honours a level set as a digit string', function (): void {
    config()->set('response-compression.gzip.level', '9');

    expect(app(GzipEncoder::class)->level())->toBe(9);
});

it('reads the brotli level from the key the config ships', function (): void {
    // Read from `brotli.level` until now, which is not a key: the lookup returned null and
    // this answered 5 whatever the config said.
    config()->set('response-compression.algorithm', 'br');
    config()->set('response-compression.br.level', 9);

    expect(app(BrotliEncoder::class)->level())->toBe(9);
});

it('falls back to the encoder default for an out of range zstd level', function (): void {
    config()->set('response-compression.zstd.level', 23);

    expect(app(ZstdEncoder::class)->level())->toBe(3);
});
