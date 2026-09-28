<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Support;

use Uak35\ResponseCompression\Exceptions\InvalidConfigurationException;

/**
 * This package's configuration, read as the types the code needs and refused when it
 * cannot be.
 *
 * WHY THIS EXISTS
 * ---------------
 * A config value that could not be read used to be replaced with a default, and both of
 * the defaults were wrong in the same direction:
 *
 *   min_length       a `.env` value is a string, `is_int()` rejected it, and the floor
 *                    became **0** — so every response was compressed, which is the
 *                    opposite of what a floor is for;
 *   gzip/zstd level  the same rejection, silently replaced by the documented default, so
 *                    setting a level did nothing at all;
 *   br level         read from a key that does not exist, so the default was the only
 *                    value it could ever return.
 *
 * None of them said anything. Each is now either read — a value that *is* what the key
 * requires, whatever PHP type `.env` handed over — or refused, loudly, with the key, the
 * value and the file to fix it in.
 *
 * THREE OUTCOMES, NOT TWO
 * -----------------------
 *   absent      the key is not in the merged configuration at all. Refused by the
 *               required readers (`int`, `string`), because the package always ships
 *               those keys: their absence means the config was never merged or published.
 *   unreadable  set, but not a value of that type. Refused by every reader — a default
 *               would be a substitution for something the operator did write.
 *   out of range  an integer the compression library will not accept. This one still
 *               takes the documented default, and deliberately: the value is readable,
 *               and a level of 40 is a typo, not a broken configuration. See
 *               docs/level-clamping.md for why a known default beats each library's own
 *               clamping behaviour.
 *
 * WHY `.env` IS NOT SIMPLY REJECTED
 * ---------------------------------
 * `env()` hands back a numeric value as a **string** (docs/env-types.md has the evidence),
 * so refusing every string would make the package's own published `env()` defaults
 * unusable — the value would be right and the type would be wrong. A digit string is
 * therefore read, exactly, never coerced around: `'4096'` is 4096, and `'4096a'` is
 * refused rather than truncated to 4096 or replaced with the default.
 *
 * The readers ending in `Or` differ from the others in one way only: a default applies
 * when the key is **absent**. A key that is present and unreadable is refused by both.
 */
final class Config
{
    /**
     * An integer, with no default: the keys this reads are ones the package always ships.
     */
    public static function int(string $key): int
    {
        $value = self::value($key);

        if ($value === null) {
            throw InvalidConfigurationException::absent($key);
        }

        return self::asInt($key, $value);
    }

    /**
     * An integer, or `$default` when the key is not set.
     */
    public static function intOr(string $key, int $default): int
    {
        $value = self::value($key);

        return $value === null ? $default : self::asInt($key, $value);
    }

    /**
     * An integer inside a range the compression library accepts, or `$default` when the
     * key is not set **or** the value is outside the range.
     */
    public static function intInRange(string $key, int $min, int $max, int $default): int
    {
        $value = self::intOr($key, $default);

        return $value >= $min && $value <= $max ? $value : $default;
    }

    /**
     * A boolean, or `$default` when the key is not set.
     *
     * A boolean is the one type `.env` does convey — `env()` itself turns `true` and
     * `false` into booleans — so the strings accepted here are PHP's own vocabulary
     * (`1`, `0`, `true`, `false`, `on`, `off`, `yes`, `no`, `''`) and nothing wider. A
     * filter that answers `false` for `'maybe'` would be the silent substitution this
     * class exists to end.
     */
    public static function boolOr(string $key, bool $default): bool
    {
        $value = self::value($key);

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($boolean !== null) {
                return $boolean;
            }
        }

        throw InvalidConfigurationException::unreadable($key, 'a boolean', $value);
    }

    /**
     * A string, with no default: the keys this reads are ones the package always ships.
     */
    public static function string(string $key): string
    {
        $value = self::value($key);

        if ($value === null) {
            throw InvalidConfigurationException::absent($key);
        }

        if (! is_string($value)) {
            throw InvalidConfigurationException::unreadable($key, 'a string', $value);
        }

        return $value;
    }

    /**
     * A string, or `$default` when the key is not set.
     */
    public static function stringOr(string $key, string $default): string
    {
        $value = self::value($key);

        if ($value === null) {
            return $default;
        }

        if (! is_string($value)) {
            throw InvalidConfigurationException::unreadable($key, 'a string', $value);
        }

        return $value;
    }

    /**
     * A list of strings, or `$default` when the key is not set.
     *
     * Every item has to be a string: a prefix list with a `null` in it would otherwise
     * mean "and also compare against nothing", which is a filter that silently matches
     * nothing for the reason a config typo is made of.
     *
     * @param  list<string>  $default
     * @return list<string>
     */
    public static function stringListOr(string $key, array $default): array
    {
        $value = self::value($key);

        if ($value === null) {
            return $default;
        }

        if (! is_array($value)) {
            throw InvalidConfigurationException::unreadable($key, 'a list of strings', $value);
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw InvalidConfigurationException::unreadable($key, 'a list of strings', $value);
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * A comma-separated list, or `$default` when the key is not set.
     *
     * Splitting is not substituting: an empty item is dropped because `a,,b` and `a, b`
     * are the same list written by two different people, while an item that is not a
     * string at all is refused.
     *
     * @return list<string>
     */
    public static function commaList(string $key, string $default): array
    {
        $value = self::stringOr($key, $default);

        return array_values(array_filter(
            array_map(trim(...), explode(',', $value)),
            static fn (string $item): bool => $item !== '',
        ));
    }

    /**
     * Every key this package reads, so a bad value stops the application at boot rather
     * than the first request that needs it.
     *
     * `enable_logging` is deliberately left out of this list. It is read by the middleware
     * rather than at boot (docs/unwired-config.md), and a key whose only effect is a line in
     * a log should not be able to stop an application from starting. Its reader is still
     * `boolOr()`, so a value it cannot read stops the response that needed the decision
     * instead of the boot — the trade this exclusion makes, stated rather than implied.
     *
     * @throws InvalidConfigurationException on the first key that cannot be read
     */
    public static function validate(): void
    {
        self::string('response-compression.algorithm');
        self::int('response-compression.min_length');

        self::boolOr('response-compression.enabled', true);
        self::boolOr('response-compression.enabled_for_testing', true);
        self::boolOr('response-compression.try_multiple_encodings', false);

        self::commaList('response-compression.multiple_encodings_order', 'br,zstd,gzip');

        self::intInRange('response-compression.gzip.level', -1, 9, 5);
        self::intInRange('response-compression.br.level', 0, 11, 5);
        self::intInRange('response-compression.zstd.level', 1, 22, 3);

        foreach (['gzip', 'br', 'zstd'] as $algorithm) {
            self::stringListOr('response-compression.'.$algorithm.'.non_supporting_user_agent_prefixes', []);
        }
    }

    /**
     * One config value, by its dotted path.
     */
    private static function value(string $key): mixed
    {
        return config($key);
    }

    /**
     * An integer, or the refusal.
     *
     * The digit-string form is accepted because that is what `env()` produces; an
     * arbitrary string cast with `(int)` would turn `'abc'` into 0, which is the
     * substitution this exists to prevent.
     */
    private static function asInt(string $key, mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[+-]?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        throw InvalidConfigurationException::unreadable($key, 'an integer', $value);
    }
}
