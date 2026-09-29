<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

/**
 * Paths that climb out of this repository, found in the files a commit would carry.
 *
 * WHY THIS EXISTS
 * ---------------
 * The two IDE tool entries in `.idea/php.xml` named Pint and PHPStan by climbing out of the
 * checkout — `$PROJECT_DIR$/../../…` — which is a path that resolves in exactly one place: the
 * position the file was written in. It is not a credential and it breaks no build, and it
 * survived every guard this package had. It names no drive letter, no home directory and no
 * share, so the machine-path guard had nothing to fire at; it is not a link, so the docs guard
 * had nothing to resolve. It was found by moving the checkout and fixed by hand, which is the
 * kind of fix that comes back.
 *
 * What a relative path leaks is not a machine but a *layout*: where a checkout has to sit for
 * the path to resolve, which is a fact about the author's disk and about nobody else's. The
 * difference matters because it is invisible in the reading — `../../application/vendor/bin`
 * reads as a path wherever it is read, and nothing about it says the directory above this one
 * is somebody else's repository rather than this one's own `docs/`.
 *
 * WHAT IS A FINDING, AND WHAT IS NOT
 * ----------------------------------
 * The rule is a walk rather than a shape. Each path token is read from the depth of the file it
 * was written in, and it is a finding only when the walk goes above the root — which is what
 * lets the records keep their own links while a climb written at the root cannot be anything
 * else:
 *
 *   ../src/Support/Config.php     in docs/             one level up is the root
 *   ../../src/Support/Config.php  in tests/Support/    two levels up is the root
 *   docs/../tests/x.php           in tests/Support/    a climb walked back before it leaves
 *   ../..                         in docs/             the root, and then past it
 *   ../anywhere                   in the README        nothing is above the root to reach
 *   $PROJECT_DIR$/../..           anywhere             the marker *is* the root, so it leaves at once
 *   ..\..\tools                   in tests/Support/    the same climb, back-slashed
 *   /../../..                     in tests/Support/    written as a string, and still a climb
 *
 * The last one is why the reading has to start where the string starts rather than at a
 * separator: `__DIR__ . '/../..'` writes the same location, and a token that begins after a `/`
 * is the tail of a path that began earlier. Half a path cannot be walked, so a token preceded by
 * a separator or by the tail of a word is not read at all — which is also what keeps a URL out,
 * since every candidate inside `https://host/a/../..` begins mid-path.
 *
 * A computed climb is read too. `dirname(__DIR__, n)` is the same location written in the other
 * language this repository is written in, and `n` is compared against the depth of the file
 * holding it: `dirname(__DIR__, 2)` in `tests/Support/` lands on the root, and
 * `dirname(__DIR__, 3)` there does not. A guard that can be walked around by rewriting the same
 * location is a guard that will be.
 *
 * WHAT IS DELIBERATELY NOT READ
 * -----------------------------
 * An absolute path. A drive letter, a home directory and a share are the machine-path guard's
 * question, and it asks it of every file a commit would carry — this reading is the other half
 * of that set rather than a second copy of it. The two share one answer to "which files are those" as well: the
 * listing and the binary test come from `MachinePaths`, so they cannot come to disagree about
 * what a commit would carry or about which files are text.
 */
final class RepoEscapes
{
    /**
     * The paths this guard is not allowed to report, because the files behind them exist to
     * spell the shapes out: a detector can only be shown to fire at a climb by having one in
     * it. Each one is a hole in the guard, so the test asserts that every file here still
     * contains something the guard would otherwise fire at — remove the samples and the
     * exemption fails with them.
     */
    public const array EXEMPT = [
        'tests/Support/RepoEscapes.php' => 'the patterns and the worked examples that define the shapes',
        'tests/Unit/Support/RepoEscapesTest.php' => 'the samples that prove the detector fires at all',
    ];

    /** The IDE's marker for the root of the project being edited. */
    private const string MARKER = '$PROJECT_DIR$';

    /**
     * A relative path token: the marker where it is written, then segments separated by either
     * separator, with an optional leading one.
     *
     * A token is not read where it begins inside a word or after a separator, because that is
     * the middle of a path rather than the start of one — the lookbehind is what stops the
     * reading at `a/../..` inside an `https://` URL.
     */
    private const string TOKEN = '~(?<![A-Za-z0-9_/\\\\])(?:\$PROJECT_DIR\$[\\\\/])?[\\\\/]?[A-Za-z0-9_.@+-]+(?:[\\\\/][A-Za-z0-9_.@+-]+)*~';

    /**
     * A climb computed from the directory of the file that writes it.
     *
     * `dirname(__DIR__)` is not matched on purpose: it is the directory above the file's own,
     * which only leaves the repository when there is nothing left to climb.
     */
    private const string DIRNAME = '~dirname\s*\(\s*__DIR__\s*,\s*(\d+)\s*\)~';

    /**
     * Every path in one text that climbs out of the repository, with the line it is on.
     *
     * `$depth` is how many directories below the root the file holding the text sits, because
     * that is what a relative path is relative to: the README is 0, `docs/env-types.md` is 1,
     * `tests/Support/Docs.php` is 2.
     *
     * @return list<array{line: int, path: string}>
     */
    public static function in(string $text, int $depth): array
    {
        $found = [];

        // An array rather than a line number counter, so a text with a mixture of endings is
        // split the same way the rest of the suite splits one.
        foreach (array_values(preg_split('/\R/', $text) ?: []) as $index => $line) {
            foreach (self::matches($line, $depth) as $path) {
                $found[] = ['line' => $index + 1, 'path' => $path];
            }
        }

        return $found;
    }

    /**
     * What the scan of a repository finds, what it read, and what it set aside.
     *
     * `files` is returned rather than counted so a caller can prove the listing is the real
     * one — the two files the samples live in have to be in it — and `exempt` is returned
     * rather than dropped so a caller can prove the exemptions are still earning their place.
     *
     * @return array{
     *     files: list<string>,
     *     found: list<array{file: string, line: int, path: string}>,
     *     exempt: list<array{file: string, line: int, path: string}>
     * }
     */
    public static function scan(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $files = MachinePaths::files($root);
        $found = [];
        $exempt = [];

        foreach ($files as $file) {
            // A file that is tracked but gone from the working tree has nothing to leak.
            $contents = @file_get_contents($root.'/'.$file);

            if ($contents === false || MachinePaths::binary($contents)) {
                continue;
            }

            // Where a relative path is relative to: the directory holding the file, counted
            // from the root, so `PUSHING.md` starts at 0 and a record in `docs/` starts at 1.
            $depth = substr_count($file, '/');

            foreach (self::in($contents, $depth) as $hit) {
                $record = ['file' => $file, 'line' => $hit['line'], 'path' => $hit['path']];

                isset(self::EXEMPT[$file]) ? $exempt[] = $record : $found[] = $record;
            }
        }

        return ['files' => $files, 'found' => $found, 'exempt' => $exempt];
    }

    /**
     * The climbs in one line, in the order they are written.
     *
     * @return list<string>
     */
    private static function matches(string $line, int $depth): array
    {
        $found = [];

        if (preg_match_all(self::TOKEN, $line, $hits) > 0) {
            foreach ($hits[0] as $token) {
                if (self::leaves($token, $depth)) {
                    $found[] = $token;
                }
            }
        }

        if (preg_match_all(self::DIRNAME, $line, $hits) > 0) {
            // preg_match_all() leaves `$hits` without the capture group at all when there is
            // nothing to put in it, and the suite fails on the notice that reading it would
            // otherwise raise.
            if ($hits[1] === []) {
                return $found;
            }

            foreach ($hits[1] as $index => $levels) {
                // `__DIR__` is the depth of the file's own directory, so `dirname(__DIR__, n)`
                // is n levels above it — and the root is where that count reaches zero.
                if ((int) $levels <= $depth) {
                    continue;
                }

                $found[] = $hits[0][$index];
            }
        }

        return $found;
    }

    /**
     * Whether a token walks above the root, read from the depth of the file that wrote it.
     *
     * A segment that is `..` climbs, an empty one or a `.` stands still, and anything else
     * descends. The walk stops the moment it is above the root, so nothing past the escape is
     * reasoned about — and the marker starts the walk at the root rather than at the file,
     * because a marker stands for the root wherever it is written.
     */
    private static function leaves(string $token, int $depth): bool
    {
        $fromRoot = str_starts_with($token, self::MARKER);
        $level = $fromRoot ? 0 : $depth;

        foreach (preg_split('~[\\\\/]~', $token) ?: [] as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            $level += $segment === '..' ? -1 : 1;

            if ($level < 0) {
                return true;
            }
        }

        return false;
    }
}
