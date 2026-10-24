<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * a machine record for another checkout, found among the files this one ignores — the rule that keeps a spilled file out of a commit, and the reading that says what it is holding
 *
 * WHY THIS EXISTS
 * ---------------
 * `MachinePaths` and `RepoEscapes` read the files a commit would carry, which is the right set for
 * a path that could reach a commit and the wrong set for a file this repository has decided not to
 * carry. `.agents/sample.machine.local.json.txt` is the case in hand: a machine record for the
 * package beside this one, full of drive paths. `*.txt` keeps it out of `git status` and out of both
 * path readings, so a rule written to hold a session's scratch copy also keeps somebody else's
 * machine file from being mentioned at all — in the directory this repository's own machine file is
 * kept in, which is the one place a stray record is least surprising and worst placed.
 *
 * The shape of that failure is worth stating plainly: an ignore rule answering a guard. The file is
 * out of the set the guards read, so the guards are green, and nothing says the rule is what keeps
 * them green. What is read here is the other half of the sentence — the files the rules hold are a
 * set of their own, and this asks what is in it.
 *
 * WHY IT NAMES RATHER THAN FAILS
 * ------------------------------
 * A green run on a clone with no spill has to stay green, and the file that is here was left in
 * place by a decision this repository made and wrote down. So the answer is names, not findings: what
 * a caller does with them is the caller's, and `IgnoredTest` writes them into the note channel a run
 * prints, which is where a session sees this checkout's own answer without having to ask for it.
 *
 * WHAT COUNTS AS A RECORD
 * -----------------------
 * A JSON document carrying `php.exe` and `package.dir` — the shape `render_local.py --init` writes,
 * and the only shape that records a machine. A dependency's `composer.json` is not one, a rendered
 * settings file is not one, and a scratch copy of either is not worth a word. `package.dir` is then
 * what decides whose record it is: this checkout's own file names this root and is excused by that
 * fact rather than by its path, and one that names anything else is another checkout's, which is the
 * only thing this reading reports.
 *
 * @guards-index reading
 */
final class Ignored
{
    /**
     * The names worth opening. A record of a machine is a JSON document, and the spilled copy of one
     * is routinely renamed to keep it from being parsed on sight — `.json.txt` is the case here — so
     * the two suffixes a record is found under are both read.
     *
     * @var list<string>
     */
    private const array SUFFIXES = ['.json', '.txt'];

    /**
     * The size past which a file is something else: a dependency's changelog, a dump, a lock file.
     *
     * A machine file is a handful of kilobytes — one section per tool this machine runs — so this is
     * generous by two orders of magnitude, and it is here so that a tree with a large ignored file in
     * it cannot make this reading slow rather than quick.
     */
    private const int LIMIT = 262144;

    /**
     * The files this checkout ignores, as paths relative to its root.
     *
     * Returned rather than counted because a caller has to be able to see what the reading read: a
     * reading whose subject is "files git ignores" answers with nothing both on a tree that has none
     * and on one where the question was never put, and this is the half that tells them apart.
     *
     * @return list<string>
     *
     * @throws RuntimeException when git cannot be asked — a tree with no repository, or no git
     */
    public static function files(string $root): array
    {
        return self::listing(self::root($root));
    }

    /**
     * The machine records among them that are not this checkout's, as path => the sentence naming it.
     *
     * Keyed by the file so a caller can check that every name came from the listing rather than from
     * somewhere else, and valued with the sentence because prose is what gets printed: the reader
     * needs to know which checkout the file belongs to, and a path alone does not say whether that
     * is this one.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException when git cannot be asked
     */
    public static function foreign(string $root): array
    {
        $root = self::root($root);
        $records = [];

        foreach (self::files($root) as $file) {
            $record = self::record($root.'/'.$file);

            // Nothing a file is not, and this checkout's own file: excused by the checkout it names
            // rather than by its path, so a machine file that has been moved or copied somewhere
            // else in the tree is still this checkout's.
            if ($record === null || $record === $root) {
                continue;
            }

            $records[$file] = sprintf(
                '%s is a machine record for %s — a checkout that is not this one — and no commit carries it',
                $file,
                $record,
            );
        }

        ksort($records);

        return $records;
    }

    /**
     * The checkout a file records, or null where the file is not a record of one.
     *
     * The two keys are asked for together because either alone is a coincidence: a `composer.json`
     * has neither, a project's config has one or the other under some other name, and only the
     * section `--init` writes has both under the names it writes them in. `package.dir` is returned
     * normalised, so the comparison in `foreign()` is between two paths in one form.
     */
    private static function record(string $path): ?string
    {
        // The name first, because it is the cheap half: an installed tree holds twelve thousand
        // files under `vendor/`, and asking the disk about each of them to discard it would make a
        // reading over "what is ignored" into a walk of a dependency tree.
        if (! in_array(self::suffix($path), self::SUFFIXES, true)) {
            return null;
        }

        if (! is_file($path) || filesize($path) > self::LIMIT) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return null;
        }

        $php = $decoded['php'] ?? null;
        $package = $decoded['package'] ?? null;

        if (! is_array($php) || ! is_array($package)) {
            return null;
        }

        $exe = $php['exe'] ?? null;
        $dir = $package['dir'] ?? null;

        if (! is_string($exe) || ! is_string($dir) || $exe === '' || $dir === '') {
            return null;
        }

        return self::root($dir);
    }

    /**
     * What `git ls-files` answers about the ignored half of the tree, NUL-separated so a path with a
     * space in it survives the answer, and sorted so two runs of this reading compare.
     *
     * `--others` with `--ignored` and no `--exclude-standard` alternative: these are the files git
     * does not have and the repository's own rules keep out of a commit — the complement of
     * `MachinePaths::files()`, which is exactly what makes the two readings a pair.
     *
     * @return list<string>
     *
     * @throws RuntimeException when git is not there, the tree is not a repository, or it refuses
     */
    private static function listing(string $root): array
    {
        $process = proc_open(
            ['git', '-C', $root, 'ls-files', '--others', '--ignored', '--exclude-standard', '-z'],
            [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not ask git for the files this checkout ignores in '.$root);
        }

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit = proc_close($process);

        if ($exit !== 0) {
            throw new RuntimeException(sprintf(
                'git could not list the files this checkout ignores in %s, so nothing was read there: %s',
                $root,
                trim($output),
            ));
        }

        $files = array_filter(explode("\0", $output), static fn (string $file): bool => $file !== '');
        $files = array_values($files);
        sort($files);

        return $files;
    }

    /** The suffix a path is read by: the last extension, dot included, or nothing at all. */
    private static function suffix(string $path): string
    {
        $dot = strrpos($path, '.');

        return $dot === false ? '' : substr($path, $dot);
    }

    /** One path in the form two of them are compared in, whichever platform wrote them down. */
    private static function root(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
