<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * Paths that only resolve on the machine they were written on, found in the files a commit
 * would carry.
 *
 * WHY THIS EXISTS
 * ---------------
 * PUSHING.md went into this repository — which is public — carrying two of them: a
 * `C:/Users/<name>/.gitconfig` in a table of evidence, and a `cd` line in its push recipe that
 * named a whole workspace on the author's disk. Neither one is a credential and neither breaks
 * a build, which is exactly why neither would have been caught by reading a diff: a path that
 * works on the machine it was written on reads like a path.
 *
 * What they cost is the reader. A runbook whose recipe opens with `cd` to a directory only its
 * author has cannot be followed by anyone else, so the line that makes the recipe usable is the
 * one line that is wrong for every other checkout — and the path in the evidence table says
 * which account to look under, which is a fact about a person rather than about the package.
 * Both were removed by hand, which is the kind of fix that comes back.
 *
 * WHAT IS A FINDING, AND WHAT IS NOT
 * ----------------------------------
 * A path is a finding when it names the machine it was written on. These do:
 *
 *   C:/Users/<name>/project     a drive letter and the path after it
 *   E:\workspace\project        the same, back-slashed, which is how a Windows recipe writes it
 *   /home/<name>/.gitconfig     an absolute home directory
 *   /Users/<name>/.gitconfig    the same, as macOS spells it
 *   /e/workspace/project        the same drive as a POSIX shell in this workspace reaches it —
 *   /c/Users/<name>/project     `/e/...` (MSYS, Git Bash, WSL without the prefix, Cygwin), which
 *                               is where a rewrite *out* of a drive letter lands: the sweep
 *                               that replaced every `E:\` in a tree walked straight past the
 *                               runbooks written in the shells this package is tested from
 *   \\fileserver\share          a network share, which resolves on one network
 *
 * and six things that look like them are not:
 *
 *   C:/Windows/System32/...     the same on every Windows install and names nobody. The one
 *                               path of this shape in the repository is this one, in
 *                               PUSHING.md's evidence table, and it is deliberate: it is the
 *                               evidence for the row above it, not a way into someone's disk.
 *   /home/runner/work/pkg       the same on every GitHub runner, for the same reason.
 *   /c/Windows/System32/...     the same directory as the row above it, written the way a shell
 *                               that mounts drives as directories writes it, and it names
 *                               nobody for the same reason. The mount spelling of a tolerated
 *                               path is tolerated by the same rule rather than a second one.
 *   /mnt/laravel/html           a container path: the name after `/mnt/` is not a letter, so it
 *                               names no drive on anybody's machine and resolves inside a
 *                               container for everyone who runs one.
 *   /e/workspace                one segment after the root, which no rule can tell from
 *                               prose — `/a/b` in a sentence is not a path — so the mount
 *                               pattern asks for a second one before it fires. The vocabulary
 *                               it therefore misses is a bare mount root, and a leak written
 *                               that way is short enough to read.
 *   ~/.gitconfig                the portable way to write a home directory, which is what the
 *                               same table uses where it can.
 *   Uak35\ResponseCompression   a namespace, and a path that resolves for everyone.
 *
 * `/usr/local/bin/php` is not a finding either. What makes a path a leak is the person or the
 * checkout in it, not the separators, which is why the rule is written around what a path
 * names rather than around the fact that it is absolute.
 *
 * WHY THE LIST COMES FROM GIT
 * ---------------------------
 * `git ls-files --cached --others --exclude-standard` is the set of files a commit would
 * carry: every tracked file, and every untracked one the repository has not ignored. Reading
 * the tracked set alone would be the wrong set twice over — an edit that is still unstaged is
 * not in the last commit, and a file that has just been written is not in either, which is
 * exactly the moment a path gets copied out of a terminal. `vendor/`, `.idea/`, the caches
 * and everything else the repository has decided is local are absent, so this class does not
 * have to keep a second copy of that decision to agree with it.
 *
 * It throws rather than reporting nothing when git cannot be asked. A guard whose answer to
 * "did you read anything?" is "no" is worth less than no guard at all.
 *
 * @guards-index reading
 */
final class MachinePaths
{
    /**
     * The two files this guard may not report, because the shapes are spelled out in them: a
     * detector can only be shown to fire at a path by having one in it. Each one is a hole in the
     * guard, so the test asserts that every file here still contains something the guard would
     * otherwise fire at — remove the samples and the exemption fails with them.
     *
     * @var array<string, string>
     */
    public const array EXEMPT = [
        'tests/Support/MachinePaths.php' => 'the patterns and the worked examples that define the shapes',
        'tests/Unit/Support/MachinePathsTest.php' => 'the samples that prove the detector fires at all',
    ];

    /**
     * One segment of a path: what ends it is a character no segment of a path carries — a
     * separator, a space, or one of the marks a sentence puts beside a path.
     *
     * Written once because the drive rule needs it twice, and a character added to one copy but
     * not the other is exactly the drift this file exists to refuse.
     */
    private const string SEGMENT = "[^\s/\\\\\"'`()<>|*?,;%]+";

    /**
     * A segment that may carry a space — a directory whose name has one is still one directory, so
     * `Program Files` is a single segment rather than an end to the path.
     *
     * One space, and never the last segment. A run of two or more spaces is the prose a path is
     * written in rather than part of it: `a.txt and C:/...` is two paths in a sentence, and a rule
     * that read the run as one segment would report the sentence instead of the paths — which is
     * the whole job backwards. The cost is a directory whose name carries two spaces, a shape this
     * tree does not have and a boundary this rule does not need.
     */
    private const string SPACED = self::SEGMENT.'(?: '.self::SEGMENT.')?';

    /**
     * A drive letter and the path after it, as Windows writes it either way round.
     *
     * The drive letter is matched only where it is not the tail of a word, which is what keeps
     * `https://` out: the `s` before its colon is preceded by a `p`, so no scheme is ever read
     * as a drive. It is also not matched after a `%`, because `%s:\n%s` — a format specifier
     * followed by an escape — is the one shape in this suite's own source that a single letter
     * and a colon would otherwise be read from, and `%` is in the path no further: a literal
     * path is not written with a variable in the middle of it.
     *
     * The path after the letter runs to the first character a path cannot carry, so both
     * separators are carried and a finding reads as the path that was written. The one exception is
     * a space between name characters: a directory whose name carries one — a program's install
     * root, say — is still one segment rather than the end of the path, which is why the rule is
     * written as a segment and a run of separators rather than as a run of characters. A segment
     * that carries a space is never the last one, and no segment carries two.
     */
    private const string DRIVE = '~(?<![A-Za-z0-9%])([A-Za-z]:[\\\\/]{1,2}(?:'.self::SPACED.'[\\\\/]{1,2})*'.self::SEGMENT.')~';

    /**
     * The same drive, spelled the way a POSIX shell reaches it.
     *
     * `/e/...` is `E:\...` in MSYS and Git Bash, `/mnt/e/...` is the same in WSL, and
     * `/cygdrive/e/...` is the same in Cygwin — three spellings of one location, and the one
     * shape that a rewrite out of a drive letter produces without looking wrong: `E:/workspace`
     * and `/e/workspace` both read as paths, and only one of them was in the pattern.
     *
     * Two things beyond the root are what keep this from firing at every slash in the tree: the
     * segment right after the root has to be a single letter, which is what makes it a mount
     * rather than a directory, and there has to be a segment after that, which is what makes it a
     * path rather than prose (`/a/b`). A segment is a name, so it admits a space only between
     * name characters — `Program Files` is one segment, and the slashes of a regular expression
     * are not a path. A container path is excluded by the same rule: `/mnt/laravel/html` has a
     * name after `/mnt/` rather than a letter.
     */
    private const string MOUNT = '~(?<![A-Za-z0-9_.:/-])((?:/mnt|/cygdrive)?/[A-Za-z]/[A-Za-z0-9_.-]+(?: [A-Za-z0-9_.-]+)*(?:/[A-Za-z0-9_.-]+)+)~';

    /**
     * An absolute home directory, with the name it belongs to.
     *
     * The leading `/home` or `/Users` has to be the start of the path rather than something
     * inside a longer one, which is what excludes `https://host/home/name` — the `/` there is
     * preceded by a letter. That is the whole reason this pattern has a lookbehind at all.
     */
    private const string HOME = "~(?<![A-Za-z0-9_.:/-])(/(?:Users|home)/[^\\s\"'`()<>|*?,;]*)~";

    /**
     * A UNC share, which resolves on the network it was written on and nowhere else.
     *
     * Two backslashes that are not themselves escaped, which is what separates a share from
     * the doubled backslash of a namespace in JSON or in a PHP string — `"Uak35\\Response\\"`
     * is preceded by a letter at every pair, so it is not read as a host.
     *
     * A host and a share are both required to be more than one character, which is what keeps
     * the escapes out of this suite's own regular expressions: `\\s`, `\\d` and `\\n\\n` are two
     * backslashes and a letter each, and a share is never named with one.
     */
    private const string SHARE = "~(?<![A-Za-z0-9_\\\\])(\\\\\\\\[A-Za-z0-9][A-Za-z0-9_.-]*\\\\[A-Za-z0-9_.-]{2,}[^\\s\"'`()<>|*?,;]*)~";

    /**
     * Every machine-local path in one text, with the line it is on.
     *
     * @return list<array{line: int, path: string}>
     */
    public static function in(string $text): array
    {
        $found = [];

        // An array rather than a line number counter, so a text with a mixture of endings is
        // split the same way the rest of the suite splits one.
        foreach (array_values(preg_split('/\R/', $text) ?: []) as $index => $line) {
            foreach (self::matches($line) as $path) {
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
        $files = self::files($root);
        $found = [];
        $exempt = [];

        foreach ($files as $file) {
            // A file that is tracked but gone from the working tree has nothing to leak.
            $contents = @file_get_contents($root.'/'.$file);

            if ($contents === false) {
                continue;
            }

            // A binary would be read as text, with its bytes spelled differently.
            if (self::binary($contents)) {
                continue;
            }

            foreach (self::in($contents) as $hit) {
                $record = ['file' => $file, 'line' => $hit['line'], 'path' => $hit['path']];

                isset(self::EXEMPT[$file]) ? $exempt[] = $record : $found[] = $record;
            }
        }

        return ['files' => $files, 'found' => $found, 'exempt' => $exempt];
    }

    /**
     * The files a commit would carry, as paths relative to the repository root.
     *
     * @return list<string>
     */
    public static function files(string $root): array
    {
        return self::listing($root, ['--cached', '--others', '--exclude-standard']);
    }

    /**
     * The half of that set git does not have yet: untracked, and not ignored.
     *
     * Split out because a caller can need the difference, and one does: a planted tree carries
     * this package's own work, and only an untracked file can be somebody else's spill — a tracked
     * one is already in a commit, and an ignored one is in neither answer. Asking git here rather
     * than working the difference out from timestamps or paths keeps the two answers to "what would
     * a commit carry" in one place.
     *
     * @return list<string>
     */
    public static function untracked(string $root): array
    {
        return self::listing($root, ['--others', '--exclude-standard']);
    }

    /**
     * Whether a file's contents are binary, read the way git reads them: a NUL byte in the
     * opening bytes.
     *
     * Public because the repository-escape reading reads the same set of files and skips them
     * the same way rather than keeping a second copy of either decision: what a commit would
     * carry and what a text is are one question each, and a file is text whichever of the two
     * is asking.
     */
    public static function binary(string $contents): bool
    {
        return str_contains(substr($contents, 0, 8000), "\0");
    }

    /**
     * One `git ls-files` question, in the shape every caller reads: NUL-separated, sorted.
     *
     * @param  list<string>  $arguments
     * @return list<string>
     */
    private static function listing(string $root, array $arguments): array
    {
        $process = proc_open(
            ['git', '-C', $root, 'ls-files', ...$arguments, '-z'],
            [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not ask git for the files a commit would carry in '.$root);
        }

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit = proc_close($process);

        if ($exit !== 0) {
            throw new RuntimeException('`git ls-files` failed in '.$root.":\n".$output);
        }

        $files = array_filter(explode("\0", $output), static fn (string $file): bool => $file !== '');
        $files = array_values($files);
        sort($files);

        return $files;
    }

    /**
     * The machine-local paths in one line, in the order they are written.
     *
     * @return list<string>
     */
    private static function matches(string $line): array
    {
        $found = [];

        foreach ([self::DRIVE, self::HOME, self::SHARE, self::MOUNT] as $pattern) {
            if (preg_match_all($pattern, $line, $hits) === 0) {
                continue;
            }

            // preg_match_all() leaves `$hits` without the capture group at all when there is
            // nothing to put in it, and the suite fails on the notice that reading it would
            // otherwise raise.
            if ($hits[1] === []) {
                continue;
            }

            foreach ($hits[1] as $path) {
                if (! self::tolerated($path)) {
                    $found[] = $path;
                }
            }
        }

        return $found;
    }

    /**
     * Whether a path names nobody, which is the only thing that stops it being a finding.
     *
     * Each of these is the same path on every machine of its kind, so it identifies nobody —
     * and each is written down here rather than left out of the patterns, so that the
     * exception is visible where the rule is.
     */
    private static function tolerated(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        // A mount spelling is the same directory as the drive-letter spelling of it, so it is
        // tolerated by the same rule rather than a second one that could drift from it:
        // `/c/Windows/...`, `/mnt/c/Windows/...` and `/cygdrive/c/Windows/...` all become
        // `c:/Windows/...` here, where the one pattern already knows what to do with it.
        $path = preg_replace('~^/(?:mnt/|cygdrive/)?([A-Za-z])/~', '$1:/', $path) ?? $path;

        return preg_match('~^[A-Za-z]:/Windows(?:/|$)~i', $path) === 1
            || preg_match('~^/(?:Users|home)/runner(?:/|$)~i', $path) === 1;
    }
}
