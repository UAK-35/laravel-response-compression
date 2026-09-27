<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Exceptions;

use InvalidArgumentException;

/**
 * A configuration value this package cannot read.
 *
 * Thrown instead of falling back to a default, because the two failures it replaces were
 * both silent and both in the wrong direction: a `min_length` read as a string became a
 * floor of **0**, so every response was compressed, and a level read as a string became
 * the default, so the setting was ignored. Neither said anything.
 *
 * The message is written to be the whole diagnosis: which key, what it holds, what it
 * would have to hold, and where to look next.
 */
final class InvalidConfigurationException extends InvalidArgumentException
{
    /**
     * The key is not set at all.
     */
    public static function absent(string $key): self
    {
        return new self(sprintf(
            "%s is not set.\n\n"
            ."This package's own config is merged in by ResponseCompressionServiceProvider, so a key\n"
            ."that is missing means that config is not there: publish it with\n"
            .'`php artisan vendor:publish --tag=response-compression-config`, or add the key to the'
            ."\nconfig file you have.",
            $key,
        ));
    }

    /**
     * The key is set to something that is not the type it has to be.
     */
    public static function unreadable(string $key, string $expected, mixed $value): self
    {
        return new self(sprintf(
            "%s is set to %s, which is not %s.\n\n"
            ."A value is read, never replaced: a key that is set but cannot be read is refused rather\n"
            ."than quietly given a default. Fix it in config/response-compression.php — and if the value\n"
            ."comes from .env, read docs/env-types.md first: every .env value arrives as a string, and\n"
            .'only the ones that are actually what the key requires are accepted.',
            $key,
            self::describe($value),
            $expected,
        ));
    }

    /**
     * A value as a short phrase, so the message can say what it found rather than only
     * that it failed. A string is quoted, because `'0'` and `0` are the two the
     * distinction exists for.
     */
    private static function describe(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'the boolean true' : 'the boolean false',
            is_int($value) => 'the integer '.$value,
            is_float($value) => 'the float '.$value,
            is_string($value) => 'the string '.var_export($value, true),
            is_array($value) => 'an array of '.count($value).' item(s)',
            is_object($value) => 'an instance of '.$value::class,
            default => 'a value of type '.get_debug_type($value),
        };
    }
}
