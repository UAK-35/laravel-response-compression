<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

/**
 * The diagnostics the compression middleware writes, read as shapes.
 *
 * WHY THIS EXISTS
 * ---------------
 * Two of the middleware's skip checks sat next to each other and wrote the same sentence, so an
 * unsuccessful response was recorded as a binary file — in the one output `enable_logging` exists to
 * produce, which is where anyone looks to find out why a response was left alone. It was found by
 * reading the file and fixed by hand, which is the kind of fix that comes back.
 *
 * Nothing at runtime can see it. One line among many looks like one line, and a test that pins a
 * message pins it one branch at a time, so a third branch copied from one of them — message and all —
 * passes every one of them. What can see it is what the branches have in common: the text they write.
 * So every `logDebugStatus(...)` call in the file is read, and turned into a **shape**.
 *
 * A SHAPE IS THE TEXT, WITH THE VALUES TAKEN OUT
 * ----------------------------------------------
 *   'Response is small ('.$length.' < '.$minimum.') - Response compression skipped - uri: '.$uri
 *
 * becomes
 *
 *   Response is small ({} < {}) - Response compression skipped - uri: {}
 *
 * The words, the punctuation and the spacing between them are kept exactly as written, because they
 * are what a person reads; the values are dropped, because they are what differs between two requests
 * rather than between two branches. Two branches a reader of the log could not tell apart therefore
 * have the same shape, whatever order their variables happen to be in.
 *
 * WHAT IT CANNOT READ, IT SAYS SO
 * -------------------------------
 * A call whose message is built from no literal at all — `logDebugStatus($message)` — is read as the
 * single opaque shape `{}`. That is deliberately not a shape anything else can equal by accident: the
 * test refuses an opaque message rather than letting two of them compare equal here for the wrong
 * reason. A guard is only worth what the text it can see is worth, and two variables are not text.
 */
final class LogMessages
{
    /** The file whose branches write the diagnostics. */
    public const string SOURCE = 'src/Middleware/CompressResponse.php';

    /** What `logDebugStatus` puts in front of every line, so the prefix is not part of a shape. */
    public const string PREFIX = '[COMPR-RESP] ';

    /** The shape of a message built from variables alone. */
    public const string OPAQUE = '{}';

    /** A single-quoted or double-quoted PHP literal, in the file's own source. */
    private const string LITERAL = '~\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"~';

    /**
     * The text of the shipped middleware, read from the package root.
     *
     * The file is named here rather than in the test so that one place decides which file the guard
     * is about, and the test can be given a fixture instead by calling `in()` directly.
     */
    public static function source(): string
    {
        return (string) file_get_contents(packageRoot().'/'.self::SOURCE);
    }

    /**
     * One entry per `logDebugStatus(...)` call in a source: where it is, and what it writes.
     *
     * @return list<array{line: int, shape: string}>
     */
    public static function in(string $source): array
    {
        $messages = [];
        $offset = 0;

        while (($at = strpos($source, 'logDebugStatus(', $offset)) !== false) {
            $offset = $at + strlen('logDebugStatus(');

            // The method's own signature is not a call to it.
            if (str_ends_with(rtrim(substr($source, 0, $at)), 'function')) {
                continue;
            }

            $arguments = self::arguments($source, $offset);

            $messages[] = [
                'line' => substr_count(substr($source, 0, $at), "\n") + 1,
                'shape' => self::shape($arguments[0] ?? ''),
            ];
        }

        return $messages;
    }

    /**
     * The shapes a source writes, in the order the calls appear in it.
     *
     * @return list<string>
     */
    public static function shapes(string $source): array
    {
        return array_column(self::in($source), 'shape');
    }

    /**
     * The lines two or more calls write the same way, one entry per collision.
     *
     * @param  list<array{line: int, shape: string}>  $messages
     * @return list<string>
     */
    public static function duplicates(array $messages): array
    {
        $at = [];

        foreach ($messages as $message) {
            $at[$message['shape']][] = $message['line'];
        }

        $duplicates = [];

        foreach ($at as $shape => $lines) {
            if (count($lines) < 2) {
                continue;
            }

            sort($lines);

            $duplicates[] = sprintf('lines %s both write: %s', implode(' and ', $lines), $shape);
        }

        return $duplicates;
    }

    /**
     * How many calls a source holds, counted without parsing it.
     *
     * The reader above decides where a call begins and where its argument ends; this count only looks
     * for the text. The two agreeing is what says the reader saw every call in the file rather than
     * the ones it happened to understand — which is the difference between a guard and a guess.
     */
    public static function callsIn(string $source): int
    {
        return substr_count($source, 'logDebugStatus(') - substr_count($source, 'function logDebugStatus(');
    }

    /**
     * The text a call writes, with everything it interpolates replaced by `{}`.
     */
    public static function shape(string $argument): string
    {
        // `PREG_UNMATCHED_AS_NULL`, because which of the two quote styles matched is what decides
        // how the literal is read, and a group that never matched is otherwise left out entirely.
        $found = preg_match_all(self::LITERAL, $argument, $matches, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);

        if ($found === false || $found === 0) {
            return self::OPAQUE;
        }

        $shape = '';
        $cursor = 0;

        for ($index = 0; $index < $found; $index++) {
            $at = $matches[0][$index][1];

            // Everything between two literals is something the code put there — a variable, a call,
            // a property — and it is one placeholder however much of it there is.
            if (trim(substr($argument, $cursor, $at - $cursor)) !== '') {
                $shape .= '{}';
            }

            $doubleQuoted = $matches[2][$index][0];

            $shape .= is_string($doubleQuoted)
                ? self::interpolated($doubleQuoted)
                : self::singleQuoted((string) $matches[1][$index][0]);

            $cursor = $at + strlen($matches[0][$index][0]);
        }

        if (trim(substr($argument, $cursor)) !== '') {
            $shape .= '{}';
        }

        return trim($shape);
    }

    /**
     * One argument per comma at the call's own depth.
     *
     * @return list<string>
     */
    private static function arguments(string $source, int $offset): array
    {
        $depth = 1;
        $at = $offset;
        $quote = null;
        $length = strlen($source);

        while ($at < $length) {
            $character = $source[$at];

            if ($quote !== null) {
                if ($character === '\\') {
                    $at += 2;

                    continue;
                }

                if ($character === $quote) {
                    $quote = null;
                }
            } elseif ($character === "'" || $character === '"') {
                $quote = $character;
            } elseif ($character === '(' || $character === '[') {
                $depth++;
            } elseif ($character === ')' || $character === ']') {
                $depth--;

                if ($depth === 0) {
                    break;
                }
            }

            $at++;
        }

        return self::split(substr($source, $offset, $at - $offset));
    }

    /**
     * The argument list split on its top-level commas.
     *
     * @return list<string>
     */
    private static function split(string $arguments): array
    {
        $parts = [];
        $depth = 0;
        $quote = null;
        $start = 0;
        $length = strlen($arguments);

        for ($at = 0; $at < $length; $at++) {
            $character = $arguments[$at];

            if ($quote !== null) {
                if ($character === '\\') {
                    $at++;

                    continue;
                }

                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;
            } elseif ($character === '(' || $character === '[') {
                $depth++;
            } elseif ($character === ')' || $character === ']') {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $parts[] = substr($arguments, $start, $at - $start);
                $start = $at + 1;
            }
        }

        $parts[] = substr($arguments, $start);

        return $parts;
    }

    /**
     * What a single-quoted PHP literal stands for, where `\\` and `\'` are the only escapes.
     */
    private static function singleQuoted(string $literal): string
    {
        return str_replace(['\\\\', "\\'"], ['\\', "'"], $literal);
    }

    /**
     * What a double-quoted PHP literal stands for, with what it interpolates replaced by `{}`.
     *
     * A message written this way is read rather than skipped: `"… uri: {$uri}"` is the same
     * diagnostic as `'… uri: '.$uri`, and a guard that could not see one of them would pass a
     * duplicate written in it.
     */
    private static function interpolated(string $literal): string
    {
        return (string) preg_replace(
            '/\{?\$[A-Za-z_][A-Za-z0-9_]*(?:->[A-Za-z_][A-Za-z0-9_]*|\[[^\]]*\])*\}?/',
            self::OPAQUE,
            stripcslashes($literal),
        );
    }
}
