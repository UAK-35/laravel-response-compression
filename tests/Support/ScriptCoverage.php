<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

/**
 * What the spawned scripts covered, read back out of the files they wrote and mapped onto the
 * repository's own copies.
 *
 * WHY THIS EXISTS
 * ---------------
 * `bin/release.php` and `bin/checks.php` cannot be reached from inside a test: they run on include,
 * they call `exit()`, and the release script commits and tags. So the suite plants a repository,
 * copies the script into it and runs it as a child process — which is the honest way to test a
 * program like that, and invisible to the floor: the coverage a child collects is its own, and the
 * parent's report knows nothing about it. Both scripts sat at 0.0% under a floor that read 100.0%
 * of `src/`, while sixty tests drove the release script end to end.
 *
 * `tests/Support/collect-coverage.php` is what changes that: it is handed to each child as
 * `auto_prepend_file`, starts PCOV before the script runs a line, and writes what the process
 * covered at shutdown. This class reads those files back.
 *
 * WHY A COPY IS NOT ASSUMED TO BE THE FILE
 * ----------------------------------------
 * The path a child reports is the path it executed — inside a fixture, in a temporary directory,
 * and named after the fixture rather than the repository. Mapping one onto the other by name is
 * what would get this wrong: a fixture's `bin/checks.php` is usually **not** the gate. The release
 * rails need a gate that fails on cue, so they plant a three-line `exit(1)` stub there instead.
 * Reading that as the 1,003-line gate would report the gate's own lines as covered by a stub — the
 * worst possible outcome for a guard, since it would look like progress.
 *
 * So a copy is read as the repository's file only when the contents are **identical**, byte for
 * byte. The stub maps to nothing and is reported as set aside instead, which is also what makes
 * this class safe to point at a directory of files written by anything.
 *
 * The two hashes being compared are taken at different moments, because only one of them can be:
 * the child hashed the file it was running, and the fixture that file lived in is swept when the
 * test that planted it ends. A merge that hashed the file it was told about would find it gone and
 * conclude — wrongly — that nothing was covered there.
 */
final class ScriptCoverage
{
    /**
     * The scripts a fixture is allowed to hold a copy of.
     *
     * `bin/` and nothing else: those are the files the suite plants, and they are the files this
     * exercise exists to measure. A wider net would let a covered `src/` file — which the parent
     * reports on its own — be added to from a child that does not matter.
     */
    private const string SCRIPTS = 'bin/*.php';

    /**
     * Everything the collected files say, with each fixture copy mapped onto the file it came from.
     *
     * `processes` and `ignored` are returned for the caller to be able to show its work: a merge of
     * nothing and a merge of everything are the same empty array, and the second one is the state
     * this whole mechanism exists to reach. `ignored` is every collected path that is not this
     * repository's file — a stubbed gate, a script a test modified — reported rather than dropped.
     *
     * @return array{lines: array<string, array<int, int>>, processes: int, ignored: list<string>}
     */
    public static function read(string $directory, string $root): array
    {
        $lines = [];
        $ignored = [];
        $processes = 0;

        foreach (glob(rtrim($directory, '/\\').'/*.cov') ?: [] as $file) {
            $contents = @file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            // A file another process is still writing, or one from an older run: unserialize()
            // reports both as a failure rather than as an exception, and neither should stop a
            // merge of everything else.
            $data = @unserialize($contents);

            if (! is_array($data) || ! is_array($data['lines'] ?? null) || ! is_array($data['hashes'] ?? null)) {
                continue;
            }

            $processes++;

            foreach ($data['lines'] as $path => $hits) {
                if (! is_string($path) || ! is_array($hits)) {
                    continue;
                }

                $hash = $data['hashes'][$path] ?? null;

                $mapped = self::map($path, is_string($hash) ? $hash : null, $root);

                if ($mapped === null) {
                    // Reported the way every other path in this class is, so that a finding reads as
                    // one path rather than as the mixture of separators the child happened to have.
                    $ignored[] = str_replace('\\', '/', $path);

                    continue;
                }

                foreach ($hits as $line => $count) {
                    // `pcov\collect()` answers in Xdebug's shape: a positive count is a line that
                    // ran, and a negative one is a line that could not. Only the first kind is
                    // coverage, and a merge that kept the rest would report a file as read because
                    // it was parsed.
                    if (! is_int($line) || ! is_int($count) || $count <= 0) {
                        continue;
                    }

                    $lines[$mapped][$line] = ($lines[$mapped][$line] ?? 0) + $count;
                }
            }
        }

        ksort($lines);
        $ignored = array_values(array_unique($ignored));
        sort($ignored);

        return ['lines' => $lines, 'processes' => $processes, 'ignored' => $ignored];
    }

    /**
     * The repository file a collected path is a copy of, or null when it is not one.
     *
     * Identical contents are the whole test, and a file with no hash to compare is not one: the
     * name only finds the candidate, and the bytes decide. Nothing here reads the collected path
     * itself — it is a temporary directory that the suite has already taken away.
     */
    public static function map(string $path, ?string $hash, string $root): ?string
    {
        if ($hash === null) {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $root), '/');

        foreach (glob($root.'/'.self::SCRIPTS) ?: [] as $script) {
            $script = str_replace('\\', '/', $script);

            if (! str_ends_with($path, substr($script, strlen($root) + 1))) {
                continue;
            }

            if (is_file($script) && hash_file('sha256', $script) === $hash) {
                return $script;
            }
        }

        return null;
    }
}
