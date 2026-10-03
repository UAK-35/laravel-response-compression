<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use FilesystemIterator;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The two halves of the package's own config contract: what the config file publishes, and
 * what the source asks it for.
 *
 * WHY THIS EXISTS
 * ---------------
 * Two of this package's defects were a key and a reader disagreeing, and neither was visible
 * from either side alone:
 *
 *   response-compression.brotli.level   read by BrotliEncoder, published by nobody — the
 *                                       config ships the section as `br`, so the lookup
 *                                       returned null and the level was always the default,
 *                                       whatever the config or `.env` said;
 *   enable_logging                      published, read by nobody — a key an operator could
 *                                       set that changed nothing at all, until the middleware's
 *                                       debug logging was wired to it (docs/unwired-config.md).
 *
 * Reading the source and the config as two sets turns both failures into one comparison, in
 * both directions. A reader that tolerates an absent key is deliberately *not* exempt from
 * the first direction: `intInRange` returning a default for a key that is not there is
 * exactly what the brotli defect was, and a lookup that can miss silently is the defect
 * rather than an exception to it.
 *
 * WHY THE TOKENIZER AND NOT A PATTERN
 * -----------------------------------
 * A regex over `'([^']*)'` pairs quotes, and the source has apostrophes in its own comments
 * — "`br` is the section this encoder's settings live under" is one, in the very docblock
 * that talks about this key. An apostrophe in a comment pairs with the opening quote of the
 * next string literal and the literal disappears into the middle of a longer match, which is
 * how a scan of this package reported the brotli key as read when the code no longer read it.
 * `PhpToken::tokenize()` knows what a comment is, so a string literal is a string literal.
 *
 * THE ONE SHAPE THAT IS NOT A PATH
 * --------------------------------
 * `Config::validate()` reads one setting across every algorithm section —
 * `'response-compression.'.$algorithm.'.non_supporting_user_agent_prefixes'` — so it is read
 * as a suffix and not as a path: reading it means some published key ends with it, and a key
 * is read by it when it does.
 *
 * @guards-index reading
 */
final class ConfigKeys
{
    /** The namespace every key of this package lives under. */
    public const string NAMESPACE = 'response-compression';

    /** The one file whose reader names are the list above. */
    public const string SOURCE = 'src/Support/Config.php';

    /**
     * The readers `Config` offers. The scan reads a reader's *name*, so a reader that is not
     * here would be a read invisible to both directions — and the test that checks this list
     * against the class is what keeps it complete.
     */
    public const array READERS = [
        'int',
        'intOr',
        'string',
        'stringOr',
        'boolOr',
        'intInRange',
        'stringListOr',
        'commaList',
    ];

    /**
     * Keys the config file publishes that nothing reads, each with the record that says so —
     * because a key like this is a decision rather than an oversight, and it is only a
     * decision once something is written down.
     *
     * Empty, and kept as the empty case rather than deleted: the only key that was ever in it,
     * `enable_logging`, is read by the middleware's debug logging now
     * (docs/unwired-config.md). Every entry here is a hole in "nothing is published that goes
     * unread", so an empty array is the state this guard wants to be in.
     */
    public const array UNWIRED = [];

    /**
     * Every key the config file publishes, without the `response-compression.` prefix, sorted.
     *
     * @return list<string>
     */
    public static function published(string $configFile): array
    {
        $parsed = ConfigDoc::shipped($configFile);
        $keys = array_keys($parsed['leaves'] + $parsed['lists']);

        sort($keys);

        return $keys;
    }

    /**
     * What the source reads, as one record per read.
     *
     * Per read rather than per key, because the same key is read in more than one place on
     * purpose: `Config::validate()` reads everything the middleware and the encoders read, so
     * that a bad value stops the application at boot. Keying by path would let one of those
     * two reads stand in for the other, and a typo in the one that was overwritten would be
     * exactly as invisible as it was before this class existed.
     *
     * `reads` is one record per written key. `built` is one per key assembled at runtime —
     * `'response-compression.'.$algorithm.'.non_supporting_user_agent_prefixes'` — which names
     * one setting across all the algorithm sections rather than a path into one of them.
     *
     * @return array{
     *     reads: list<array{path: string, reader: string, file: string, line: int}>,
     *     built: list<array{suffix: string, reader: string, file: string, line: int}>
     * }
     */
    public static function read(string $source): array
    {
        $reads = [];
        $built = [];
        $prefix = self::NAMESPACE.'.';

        foreach (self::phpFiles($source) as $file) {
            $relative = self::relative($source, $file);
            $tokens = self::significantTokens($file);

            foreach ($tokens as $index => $token) {
                if ($token->id !== T_CONSTANT_ENCAPSED_STRING) {
                    continue;
                }

                $value = self::unquote($token->text);

                if (! str_starts_with($value, $prefix)) {
                    continue;
                }

                $reader = self::readerBefore($tokens, $index);
                $path = substr($value, strlen($prefix));
                $where = ['reader' => $reader, 'file' => $relative, 'line' => $token->line];

                if (preg_match('/^[a-z_]+(?:\.[a-z_]+)*$/', $path) === 1) {
                    $reads[] = ['path' => $path, ...$where];

                    continue;
                }

                // `'response-compression.'.$algorithm.'.level'`: the literal is the namespace on
                // its own, and the setting it names is the literal on the far side of the
                // variable. The shape in between is asserted rather than assumed, so a namespace
                // written for another concatenation is not read as a key.
                if ($path !== '') {
                    continue;
                }

                $suffix = self::assembledSuffix($tokens, $index);

                if ($suffix !== null) {
                    $built[] = ['suffix' => $suffix, ...$where];
                }
            }
        }

        return ['reads' => $reads, 'built' => $built];
    }

    /**
     * Every key a file in `src/` reads that the config file does not publish.
     *
     * The first direction of the contract, as one call: the reading the config-key guard asserts
     * on, so the test and the mutation harness ask the same thing rather than each walking `src/`
     * and comparing it on its own. A key assembled at run time is read as a suffix, because
     * `Config::validate()` names one setting across every algorithm section.
     *
     * @return list<string>
     */
    public static function unknown(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $published = self::published($root.'/config/response-compression.php');
        $source = self::read($root.'/src');
        $unknown = [];

        foreach ($source['reads'] as $read) {
            if (in_array($read['path'], $published, true)) {
                continue;
            }

            $unknown[] = sprintf(
                '%s.%s is read by %s() in %s:%d, and the config does not publish it',
                self::NAMESPACE,
                $read['path'],
                $read['reader'],
                $read['file'],
                $read['line'],
            );
        }

        foreach ($source['built'] as $built) {
            foreach ($published as $path) {
                if (str_ends_with($path, '.'.$built['suffix'])) {
                    continue 2;
                }
            }

            $unknown[] = sprintf(
                'the key assembled in %s:%d by %s() ends in %s, which no published key does',
                $built['file'],
                $built['line'],
                $built['reader'],
                $built['suffix'],
            );
        }

        return $unknown;
    }

    /**
     * Every key the config file publishes that nothing in `src/` reads.
     *
     * The other direction, as one call. A key in `UNWIRED` is exempt, because an exemption is a
     * decision and the record that makes it one is held by the test beside this reading.
     *
     * @return list<string>
     */
    public static function unread(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $published = self::published($root.'/config/response-compression.php');
        $source = self::read($root.'/src');
        $read = array_column($source['reads'], 'path');
        $built = array_column($source['built'], 'suffix');
        $unread = [];

        foreach ($published as $path) {
            if (in_array($path, $read, true) || array_key_exists($path, self::UNWIRED)) {
                continue;
            }

            // A key assembled at run time reads every section's copy of one setting.
            foreach ($built as $suffix) {
                if (str_ends_with($path, '.'.$suffix)) {
                    continue 2;
                }
            }

            $unread[] = sprintf('%s.%s is published and never read', self::NAMESPACE, $path);
        }

        return $unread;
    }

    /**
     * Every reader the class declares that the list does not know, and every name in the list the
     * class does not declare.
     *
     * The scan reads a reader's *name*, so a reader missing from `READERS` would be a key read
     * invisibly in both directions at once. The list is held to the class in that order — a name the
     * class declares and the list does not is the one that reads a key behind the scan's back — and
     * the other direction is reported too, because a name left in the list after the method it named
     * was renamed is a reader the scan thinks exists.
     *
     * `validate()` is the one method left out: it reads no key of its own, it reads everything the
     * middleware and the encoders read, so it is not a reader the scan has to know.
     *
     * @return list<string>
     */
    public static function unknownReaders(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $source = (string) file_get_contents($root.'/'.self::SOURCE);

        preg_match_all('/public static function (\w+)\(/', $source, $matched);

        $declared = array_values(array_filter(
            $matched[1],
            static fn (string $name): bool => $name !== 'validate',
        ));
        sort($declared);

        $known = self::READERS;
        sort($known);

        $findings = [];

        foreach (array_diff($declared, $known) as $name) {
            $findings[] = sprintf('%s declares %s(), which ConfigKeys::READERS does not know', self::SOURCE, $name);
        }

        foreach (array_diff($known, $declared) as $name) {
            $findings[] = sprintf('ConfigKeys::READERS names %s(), which %s does not declare', $name, self::SOURCE);
        }

        return $findings;
    }

    /**
     * A file's tokens, with the whitespace and the comments taken out so the code can be walked
     * by index: `Config::string(` is then four tokens, not seven.
     *
     * @return list<PhpToken>
     */
    private static function significantTokens(string $file): array
    {
        $tokens = PhpToken::tokenize((string) file_get_contents($file));

        return array_values(array_filter($tokens, static fn (PhpToken $token): bool => ! $token->isIgnorable()));
    }

    /**
     * The call a literal is an argument of, which is the reader's name — `stringListOr` for
     * `Config::stringListOr('response-compression.…')`. Empty when the literal is not one.
     *
     * @param  list<PhpToken>  $tokens
     */
    private static function readerBefore(array $tokens, int $index): string
    {
        if (($tokens[$index - 1]->text ?? '') !== '(') {
            return '';
        }

        $before = $tokens[$index - 2] ?? null;

        return $before !== null && $before->id === T_STRING ? $before->text : '';
    }

    /**
     * The setting a runtime-assembled key names, or null when the literal is not the start of
     * one. The shape is `'.' $variable '.' 'suffix'`.
     *
     * @param  list<PhpToken>  $tokens
     */
    private static function assembledSuffix(array $tokens, int $index): ?string
    {
        // A kind is matched against the token's id or its text, because `.` is punctuation and
        // has no id, while `$algorithm` and the literal do.
        $shape = ['.', T_VARIABLE, '.', T_CONSTANT_ENCAPSED_STRING];
        $cursor = $index;

        foreach ($shape as $kind) {
            $cursor++;
            $token = $tokens[$cursor] ?? null;

            if ($token === null || ($kind !== $token->id && $kind !== $token->text)) {
                return null;
            }
        }

        $suffix = ltrim(self::unquote($tokens[$cursor]->text), '.');

        return preg_match('/^[a-z_]+(?:\.[a-z_]+)*$/', $suffix) === 1 ? $suffix : null;
    }

    /**
     * A string literal's contents, with the quoting the token carries taken off.
     */
    private static function unquote(string $text): string
    {
        $inner = substr($text, 1, -1);

        // A single-quoted literal escapes two characters and no more; anything else in it is
        // literal, which is why this is not `stripcslashes()`. A double-quoted one is left as
        // it is: no key in this package is written with one, and misreading an escape would be
        // worse than not reading the literal at all.
        return $text[0] === "'" ? str_replace(['\\\\', "\\'"], ['\\', "'"], $inner) : $inner;
    }

    /**
     * A file's path as it is named in a report, relative to the scanned directory and with
     * forward slashes on every platform.
     */
    private static function relative(string $directory, string $file): string
    {
        return str_replace('\\', '/', ltrim(substr($file, strlen(rtrim($directory, '/\\'))), '/\\'));
    }

    /**
     * Every `.php` file under `$directory`, sorted so a report reads the same twice.
     *
     * @return list<string>
     */
    private static function phpFiles(string $directory): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
