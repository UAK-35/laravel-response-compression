<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * A config file — or the README's copy of one — read as data.
 *
 * WHY THIS EXISTS
 * ---------------
 * The README publishes the whole config file, and a published default is a contract:
 * an operator reads `min_length` here and sizes an endpoint against it, and reads
 * `algorithm` here and decides whether to install the brotli extension. Nothing in the
 * suite used to notice when the file and the README disagreed, which is how this
 * package shipped a README claiming `algorithm` defaults to `gzip` and `min_length` to
 * `1024` while the config said `br` and `2048`.
 *
 * So both sides are read by this same reader and compared key by key. That is what
 * makes the comparison mean something: parsing one side and restating the other would
 * compare the README with itself, and a default retyped into a test is a third copy of
 * the value rather than a check on the first two.
 *
 * The comment above each key is read with it, as its `docblock`. A leaf and its list are
 * where a key is *published*; the comment above it is where the key is *described*, and
 * the two can disagree without either one looking wrong on its own — this package shipped
 * a docblock saying a key was not yet read long after the middleware had started reading
 * it. Reading the comment in the same walk is what lets a claim about a key be compared
 * with the source that does or does not read it, without a second parser to keep in step.
 *
 * The reader is deliberately strict about the shapes it accepts and raises on anything
 * else. A parser that quietly reads nothing is worse than no parser at all: it reports
 * agreement with a document it never understood.
 */
final class ConfigDoc
{
    /**
     * The prefixes a line can open with and still carry no structure: a comment, the PHP
     * opening tag, the `return`, and the outermost bracket.
     */
    private const array SKIPPED_PREFIXES = ['//', '#', '/*', '*', '<?php', 'declare(', 'return [', '['];

    /**
     * The config file the package ships.
     *
     * @return array{leaves: array<string, string>, lists: array<string, list<string>>, docblocks: array<string, string>}
     *
     * @throws RuntimeException when the file is missing or holds a shape this reader does not accept
     */
    public static function shipped(string $file): array
    {
        return self::parse(self::lines($file), $file);
    }

    /**
     * The fenced `php` block under `$heading` in the README.
     *
     * @return array{leaves: array<string, string>, lists: array<string, list<string>>, docblocks: array<string, string>}
     *
     * @throws RuntimeException when the README, the heading or the fence is not there
     */
    public static function documented(string $readme, string $heading): array
    {
        return self::parse(
            self::fence(self::lines($readme), $heading, $readme),
            sprintf('%s [%s]', $readme, $heading),
        );
    }

    /**
     * Every leaf and every list, keyed by its own dotted path.
     *
     * Leaves read as `env('NAME') => <default>` so a failure names the variable and the
     * value rather than a position; lists read as the items in the order written. Every key
     * also gets a `docblocks` slot, holding the comment written above it — empty when there
     * is none, because "the key has no comment" and "the reader lost the key" are different
     * facts and only one of them is a reason to change the file.
     *
     * @param  list<string>  $lines
     * @return array{leaves: array<string, string>, lists: array<string, list<string>>, docblocks: array<string, string>}
     */
    private static function parse(array $lines, string $source): array
    {
        $path = [];
        $leaves = [];
        $lists = [];
        $docblocks = [];
        $comment = [];

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $line = rtrim(trim($line), ',');

            // A blank line ends a comment block: the comment above a key and the comment
            // above the key before it are then not the same comment.
            if ($line === '') {
                $comment = [];

                continue;
            }

            if (self::skip($line)) {
                $text = self::comment($line);

                // A comment is collected; the opening tag, `declare(` and the array brackets
                // are not comments at all, so they end the block rather than extend it.
                $comment = $text === null ? [] : [...$comment, $text];

                continue;
            }

            // A closing bracket, with or without the file's own terminator: back out of
            // one level. The outermost pair has no key to pop, so it is tolerated
            // rather than tracked.
            if (preg_match('/^\](;)?$/', $line) === 1) {
                $path = array_slice($path, 0, max(0, count($path) - 1));
                $comment = [];

                continue;
            }

            if (preg_match("/^'([^']+)'\s*=>\s*\[$/", $line, $match) === 1) {
                $path[] = $match[1];
                $key = implode('.', $path);
                $lists[$key] ??= [];
                $docblocks[$key] = self::block($comment);
                $comment = [];

                continue;
            }

            if (preg_match("/^'([^']+)'\s*=>\s*(.+)$/", $line, $match) === 1) {
                $key = implode('.', [...$path, $match[1]]);
                $leaves[$key] = self::value($match[2], $source, $number);
                $docblocks[$key] = self::block($comment);
                $comment = [];

                continue;
            }

            if (preg_match("/^'([^']*)'$/", $line, $match) === 1) {
                if ($path === []) {
                    throw new RuntimeException(sprintf('%s line %d lists an item outside any array: %s', $source, $number, $line));
                }

                $lists[implode('.', $path)][] = $match[1];

                continue;
            }

            throw new RuntimeException(sprintf(
                "%s line %d is not a shape this reader understands: %s\nIt accepts 'key' => env('NAME', default), 'key' => [, a quoted list item, and the closing bracket.",
                $source,
                $number,
                $line,
            ));
        }

        ksort($leaves);

        $lists = array_filter($lists, static fn (array $items): bool => $items !== []);
        ksort($lists);

        return ['leaves' => $leaves, 'lists' => $lists, 'docblocks' => $docblocks];
    }

    /**
     * One comment line's text, or null when the line is not a comment.
     *
     * The delimiters come off so the block reads as the sentence it was written as: a claim
     * is matched against "Not yet wired.", not against " * Not yet wired.".
     */
    private static function comment(string $line): ?string
    {
        if (preg_match('#^(?://+|\#|/\*+|\*+)#', $line) !== 1) {
            return null;
        }

        return trim((string) preg_replace('#^(?://+|\#+|/\*+|\*+/?)#', '', $line));
    }

    /**
     * The collected comment as one block of text — empty when there was none.
     *
     * @param  list<string>  $comment
     */
    private static function block(array $comment): string
    {
        return trim(implode("\n", array_filter($comment, static fn (string $line): bool => $line !== '')));
    }

    /**
     * One `key => value` pair as a comparable string.
     *
     * @throws RuntimeException when the value is not a shape this reader accepts
     */
    private static function value(string $text, string $source, int $number): string
    {
        if (preg_match("/^env\('([^']+)',\s*(.+)\)$/", $text, $match) === 1) {
            return sprintf("env('%s') => %s", $match[1], self::literal($match[2], $source, $number));
        }

        return 'literal => '.self::literal($text, $source, $number);
    }

    /**
     * A leaf's default: a bool, a number or a quoted string, and nothing else.
     *
     * @throws RuntimeException when the default is none of those
     */
    private static function literal(string $text, string $source, int $number): string
    {
        return match (true) {
            $text === 'true', $text === 'false', $text === 'null' => $text,
            preg_match('/^-?\d+$/', $text) === 1 => $text,
            preg_match('/^-?\d+\.\d+$/', $text) === 1 => $text,
            preg_match("/^'([^']*)'$/", $text, $match) === 1 => var_export($match[1], true),
            default => throw new RuntimeException(sprintf(
                '%s line %d has a default this reader does not accept: %s (expected a bool, a number or a single-quoted string)',
                $source,
                $number,
                $text,
            )),
        };
    }

    /**
     * Whether a line carries no structure.
     */
    private static function skip(string $line): bool
    {
        return array_any(
            self::SKIPPED_PREFIXES,
            static fn (string $prefix): bool => str_starts_with($line, $prefix),
        );
    }

    /**
     * The lines of the first `php` fence under `$heading`, and nothing else.
     *
     * A fence in another language is skipped rather than read, and a fence left open or
     * found empty raises: both would otherwise let this reader report agreement with
     * nothing.
     *
     * @param  list<string>  $lines
     * @return list<string>
     *
     * @throws RuntimeException when the heading is missing, or the fence is empty or unclosed
     */
    private static function fence(array $lines, string $heading, string $source): array
    {
        $start = null;

        foreach ($lines as $index => $line) {
            if (trim($line) === trim($heading)) {
                $start = $index;

                break;
            }
        }

        if ($start === null) {
            throw new RuntimeException(sprintf('%s has no [%s] heading to read a fence from.', $source, trim($heading)));
        }

        $inside = false;
        $body = [];

        foreach (array_slice($lines, $start + 1) as $line) {
            $trimmed = trim($line);

            if (! str_starts_with($trimmed, '```')) {
                if ($inside) {
                    $body[] = $line;
                }

                continue;
            }

            if (! $inside) {
                $inside = $trimmed === '```php';

                continue;
            }

            if ($body === []) {
                throw new RuntimeException(sprintf('%s has an empty fence under [%s].', $source, trim($heading)));
            }

            return $body;
        }

        throw new RuntimeException(sprintf('%s leaves the fence under [%s] unclosed.', $source, trim($heading)));
    }

    /**
     * @return list<string>
     *
     * @throws RuntimeException when the file is not there
     */
    private static function lines(string $file): array
    {
        $raw = @file_get_contents($file);

        if ($raw === false) {
            throw new RuntimeException('No file to read a config from: '.$file);
        }

        return explode("\n", str_replace("\r\n", "\n", $raw));
    }
}
