<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * The one manifest of tool paths, and the three ways a second copy of a path could come back.
 *
 * WHY THIS EXISTS
 * ---------------
 * `composer.json`, `bin/checks.php` and `bin/coverage.php` all have to run a tool and none of them
 * can ask another for the path to it: a composer script is a shell string, and the two programs
 * run on include. So the paths were written down twice, and the copies drifted — the scripts named
 * their tools through `vendor/bin` while the gate named the file inside `vendor/`, and
 * `vendor/bin/pest` and `vendor/pestphp/pest/bin/pest` both read as Pest. It was not invisible in
 * what it did: on Windows the shim is a `.bat` that runs whichever `php` is first on `PATH` — a
 * second interpreter, with a second set of extensions — which is how `composer test:unit` came
 * back with no coverage driver available on the machine where the gate's own Pest run covered
 * 100.0%.
 *
 * `bin/tool-paths.php` is that copy, and the only one. What can go wrong with one is a different
 * question from what can go wrong with two, and it is what this reads for:
 *
 *   - a path written down again, in a script or in one of the programs — the same defect, in the
 *     place that would do it quietly now that nothing compares two lists any more;
 *   - a path that no longer resolves, or resolves to a shim: a wrong path fails in the tool's own
 *     words, and a shim is the wrong interpreter;
 *   - a name nothing has, or nothing uses: a name is what a script hands to `bin/tool.php` and
 *     what the gate asks the manifest for, and a name that is not in it reads as "not installed"
 *     — a skip in the summary that looks like a machine without the tool rather than a typo.
 *
 * WHAT IS READ, AND WHAT IS NOT
 * -----------------------------
 * The manifest is *asked*, by requiring it, rather than parsed out of its source: it returns an
 * array and does nothing else, which is the reason it is a file of its own. The programs that read
 * it cannot be asked — they run on include — so they are read as text: the names they ask for, the
 * paths the scripts write, and, for every entry the manifest holds, whether a reader writes that
 * path or the `vendor/bin` shim of that name down again.
 *
 * Every reader throws rather than answering with nothing when it cannot find what it reads. A
 * guard whose answer to "did you read anything?" is "no" reports agreement with a file it never
 * understood, which is the one way it could be worse than absent.
 */
final class ToolPaths
{
    /** The one file a tool's path is written in. */
    public const string MANIFEST = 'bin/tool-paths.php';

    /** The programs that run a tool, and so have to name one without writing a path down. */
    public const array READERS = ['bin/checks.php', 'bin/coverage.php', 'bin/tool.php'];

    /**
     * A command that runs a program under the interpreter Composer is running under.
     *
     * `@php` is the whole reason a script does not need a shim: Composer expands it to the binary
     * that is running it, so the program runs under the PHP that installed the dependencies — the
     * same interpreter whose platform requirements the gate checks.
     */
    private const string CALL = '~^@php\s+(?<path>\S+)(?:\s+(?<arguments>.*))?$~';

    /**
     * The manifest, as name => path.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException when the file is not there, returns something that is not an array,
     *                          or returns none — the three ways this guard could read nothing and
     *                          call it agreement
     */
    public static function manifest(string $file): array
    {
        if (! is_file($file)) {
            throw new RuntimeException($file.' is not there.');
        }

        $paths = require $file;

        if (! is_array($paths) || $paths === []) {
            throw new RuntimeException($file.' returns no name => path array to read.');
        }

        return $paths;
    }

    /**
     * Every command a composer script runs under `@php`, in the order the scripts are written.
     *
     * @return list<array{script: string, path: string, arguments: string}>
     *
     * @throws RuntimeException when the text has no `scripts` block to read
     */
    public static function scriptsIn(string $composerJson): array
    {
        $composer = json_decode($composerJson, true);

        if (! is_array($composer) || ! is_array($composer['scripts'] ?? null)) {
            throw new RuntimeException('The composer.json handed to this reader has no `scripts` block to read.');
        }

        $found = [];

        foreach ($composer['scripts'] as $script => $commands) {
            // A script is one command or a list of them. Both are read, because the list form is
            // how `test` runs the four tools one after another.
            foreach (is_array($commands) ? $commands : [$commands] as $command) {
                if (! is_string($command) || preg_match(self::CALL, trim($command), $call) !== 1) {
                    continue;
                }

                $found[] = [
                    'script' => (string) $script,
                    'path' => $call['path'],
                    // The arguments are carried rather than dropped so a failure can quote the line
                    // that has to change, and so a call with none is not confused with a call this
                    // reader failed to split.
                    'arguments' => $call['arguments'] ?? '',
                ];
            }
        }

        return $found;
    }

    /**
     * The tool names one of this package's own programs asks the manifest for.
     *
     * @return list<string>
     */
    public static function namesIn(string $source): array
    {
        preg_match_all('~\$tools\[\'(?<name>[a-z0-9-]+)\'\]~', $source, $names);

        return array_values(array_unique($names['name']));
    }

    /**
     * Scripts that run something other than a program this repository keeps in `bin/`.
     *
     * A script that writes a tool's path down is the defect the manifest exists to stop, and it is
     * invisible in the file that does it: `@php vendor/pestphp/pest/bin/pest` reads as entirely
     * reasonable. There is one form a script may use — `@php bin/tool.php <tool>` — and anything
     * else is either a path written down twice or a program that is not in the package.
     *
     * @param  list<array{script: string, path: string, arguments: string}>  $scripts
     * @return list<string>
     */
    public static function foreignScripts(array $scripts): array
    {
        $found = [];

        foreach ($scripts as $script) {
            if (str_starts_with($script['path'], 'bin/')) {
                continue;
            }

            $found[] = sprintf(
                'the %s script runs %s, which is a path written down rather than a tool named',
                $script['script'],
                $script['path'],
            );
        }

        return $found;
    }

    /**
     * A path a reader writes down again — or the shim of a name it should be looking up.
     *
     * The manifest is the whole comparison now, so a second copy has to be looked for against it:
     * the entry's own path, and the `vendor/bin` spelling of the same name, which is the mistake
     * that started this. Both are matched as the strings they are, which is what keeps a sentence
     * in a docblock from being read as a path.
     *
     * @param  array<string, string>  $manifest
     * @param  array<string, string>  $sources  reader => its text
     * @return list<string>
     */
    public static function secondCopies(array $manifest, array $sources): array
    {
        $found = [];

        foreach ($sources as $reader => $source) {
            foreach ($manifest as $name => $path) {
                foreach ([$path, 'vendor/bin/'.$name] as $written) {
                    if (str_contains($source, $written)) {
                        $found[] = sprintf(
                            '%s writes %s down; %s is where a tool path is written',
                            $reader,
                            $written,
                            self::MANIFEST,
                        );
                    }
                }
            }
        }

        return $found;
    }

    /**
     * The manifest's own rules: an entry file inside `vendor/`, not a shim, and on disk.
     *
     * @param  array<string, string>  $manifest
     * @return list<string>
     */
    public static function problems(array $manifest, string $root): array
    {
        $found = [];

        foreach ($manifest as $name => $path) {
            if (! str_starts_with($path, 'vendor/')) {
                $found[] = sprintf('%s names %s, which is not a file inside vendor/', $name, $path);
            } elseif (str_starts_with($path, 'vendor/bin/')) {
                // A shim is a file inside `vendor/`, so it is asked about second: `vendor/bin/pint`
                // is a shim and not a tool, and one sentence about it is enough.
                $found[] = sprintf('%s names the %s shim, which runs whichever php is first on PATH', $name, $path);
            }

            if (! is_file($root.'/'.ltrim($path, '/'))) {
                $found[] = sprintf('%s names %s, which is not there', $name, $path);
            }
        }

        return $found;
    }

    /**
     * Names something asks for that the manifest does not hold, and names nothing asks for.
     *
     * @param  array<string, string>  $manifest
     * @param  list<array{from: string, tool: string}>  $asked
     * @return list<string>
     */
    public static function unshared(array $manifest, array $asked): array
    {
        $found = [];
        $wanted = [];

        foreach ($asked as $request) {
            $wanted[$request['tool']] = true;

            if (! isset($manifest[$request['tool']])) {
                $found[] = sprintf(
                    '%s asks for %s, which %s does not name',
                    $request['from'],
                    $request['tool'],
                    self::MANIFEST,
                );
            }
        }

        foreach (array_keys($manifest) as $name) {
            if (! isset($wanted[$name])) {
                $found[] = sprintf('%s names %s, which nothing runs', self::MANIFEST, $name);
            }
        }

        return $found;
    }
}
