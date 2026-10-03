<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The one manifest of the tools this package runs, and every way a fact in it could be stated twice.
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
 * An entry is more than a path, and the two facts beside it drift in the same way. A tool is what
 * the gate measures this repository with, so the version matters as much as the file: an entry
 * carries the version the package pins the tool at, and that pin is compared with what
 * `composer.json` requires and with what `vendor/composer/installed.json` says is here. A pin
 * that moved on one side and not the other is a tool measuring something other than this tree,
 * which is the failure the paths came from, one level up — and it is checked in the direction a
 * path cannot be, because the installed side is a fact about this machine rather than about the
 * repository. What a tool is *for* is the third fact, because it is written beside the tool in
 * three places and only one of them can be right.
 *
 * WHAT IS READ, AND WHAT IS NOT
 * -----------------------------
 * The manifest is *asked*, by requiring it, rather than parsed out of its source: it returns an
 * array and does nothing else, which is the reason it is a file of its own. The programs that read
 * it cannot be asked — they run on include — so they are read as text: the names they ask for, the
 * paths the scripts write, and, for every entry the manifest holds, whether a reader writes that
 * path or the `vendor/bin` shim of that name down again. The two versions a pin is compared with
 * are read the same way the manifest is: `composer.json` and Composer's own `installed.json`, both
 * handed in rather than looked up, so this reads one tree at a time.
 *
 * The package a path is inside is derived from the path rather than carried beside it:
 * `vendor/acme/thing/bin/thing` is inside `acme/thing`, and an entry that said so a second time
 * would be one more copy to drift. It is what makes the pin one fact — the version of the package
 * a tool's file lives in — rather than a name and a version that can disagree.
 *
 * Every reader throws rather than answering with nothing when it cannot find what it reads. A
 * guard whose answer to "did you read anything?" is "no" reports agreement with a file it never
 * understood, which is the one way it could be worse than absent.
 *
 * @guards-index reading
 */
final class ToolPaths
{
    /** The one file a tool's path is written in. */
    public const string MANIFEST = 'bin/tool-paths.php';

    /** The programs that run a tool, and so have to name one without writing a path down. */
    public const array READERS = ['bin/checks.php', 'bin/coverage.php', 'bin/tool.php'];

    /** The workflow that runs this gate on a runner, which is the third place a tool could be invoked. */
    public const string WORKFLOW = '.github/workflows/main.yml';

    /**
     * The two things the runner has to run to be this gate rather than a weaker one beside it.
     *
     * `composer checks -- --require-all` is what a tag is cut behind: the whole eleven checks in one
     * process, with every skip turned into a failure, because on a runner a tool that failed to
     * install would otherwise read as a green build. `composer test:unit` is the one check that gate
     * cannot make — it runs Pest without a coverage driver, so that it works on a machine that has
     * none — and it is the floor the runner enforces instead.
     *
     * The value beside a script is the argument the command has to carry, so `--require-all` is
     * stated once here as a requirement rather than a second time in a sentence about the workflow.
     * The workflow is what is held to it; nothing is read back into this list.
     *
     * @var array<string, string>
     */
    public const array CI = [
        'checks' => '--require-all',
        'test:unit' => '',
    ];

    /**
     * What an entry holds: the file that runs the tool, the version it is pinned at, and what it
     * is for. `manifest()` refuses an entry missing any of them, because a caller that read one
     * of the three out of a full entry would otherwise have to invent the other two.
     */
    public const array KEYS = ['path', 'pin', 'why'];

    /**
     * A command that runs a program under the interpreter Composer is running under.
     *
     * `@php` is the whole reason a script does not need a shim: Composer expands it to the binary
     * that is running it, so the program runs under the PHP that installed the dependencies — the
     * same interpreter whose platform requirements the gate checks.
     */
    private const string CALL = '~^@php\s+(?<path>\S+)(?:\s+(?<arguments>.*))?$~';

    /**
     * The manifest, as name => its entry.
     *
     * @return array<string, array{path: string, pin: string, why: string}>
     *
     * @throws RuntimeException when the file is not there, returns something that is not an array,
     *                          or returns none — the ways this guard could read nothing and call
     *                          it agreement — and when an entry is not a record with all three
     *                          facts in it, which is a shape no caller can read a path out of
     */
    public static function manifest(string $file): array
    {
        if (! is_file($file)) {
            throw new RuntimeException($file.' is not there.');
        }

        $paths = require $file;

        if (! is_array($paths) || $paths === []) {
            throw new RuntimeException($file.' returns no name => entry array to read.');
        }

        $manifest = [];

        foreach ($paths as $name => $entry) {
            $missing = [];

            foreach (self::KEYS as $key) {
                if (! is_array($entry) || ! is_string($entry[$key] ?? null) || trim($entry[$key]) === '') {
                    $missing[] = $key;
                }
            }

            if (! is_string($name) || $missing !== []) {
                throw new RuntimeException(sprintf(
                    '%s: %s is not an entry with a path, a pin and what it is for (missing: %s).',
                    $file,
                    is_string($name) ? $name : gettype($name),
                    implode(', ', $missing),
                ));
            }

            $manifest[$name] = [
                'path' => (string) $entry['path'],
                'pin' => (string) $entry['pin'],
                'why' => (string) $entry['why'],
            ];
        }

        return $manifest;
    }

    /**
     * The package a tool's file is inside, derived from the path itself.
     *
     * A path inside `vendor/` starts with the two segments every Composer package is named by, so
     * `vendor/acme/thing/bin/thing` is inside `acme/thing` — and an entry that carried the package
     * name beside the path would be a second copy of what the path already says. A path this rule
     * cannot answer for is one no package is inside, which is a path `problems()` refuses: it is
     * not a file inside `vendor/`, or it is the `vendor/bin` shim, which is not inside a package at
     * all. The answer for such a path is therefore never read — it is only ever a name to print.
     */
    public static function package(string $path): string
    {
        $segments = array_values(array_filter(
            explode('/', str_replace('\\', '/', ltrim($path, '/'))),
            static fn (string $segment): bool => $segment !== '',
        ));

        return implode('/', array_slice($segments, 1, 2));
    }

    /**
     * What `composer install` put in the tree, as package => version.
     *
     * Composer's own record rather than a second reading of a directory, and it is the only side of
     * a pin that is a fact about this machine: `composer.json` says what this package asks for, and
     * this says what it got. Composer writes the list under `packages` in Composer 2; a bare list is
     * the older shape, and both are read for the same reason.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException when the file is not there, is not a list of packages, or names no
     *                          package — the ways this could answer "nothing is installed" for a
     *                          tree that has everything installed
     */
    public static function installed(string $installedJson): array
    {
        if (! is_file($installedJson)) {
            throw new RuntimeException($installedJson.' is not there.');
        }

        $data = json_decode((string) file_get_contents($installedJson), true);
        $packages = is_array($data) && is_array($data['packages'] ?? null) ? $data['packages'] : $data;

        if (! is_array($packages)) {
            throw new RuntimeException($installedJson.' is not a list of installed packages to read.');
        }

        $versions = [];

        foreach ($packages as $package) {
            if (is_array($package) && is_string($package['name'] ?? null) && is_string($package['version'] ?? null)) {
                $versions[$package['name']] = self::version($package['version']);
            }
        }

        if ($versions === []) {
            throw new RuntimeException($installedJson.' names no installed package.');
        }

        return $versions;
    }

    /**
     * The pin compared with the two versions it is a statement about.
     *
     * An entry's `pin` is the version of the package its `path` is inside, and it is one fact
     * written down where two other files already state it: `composer.json`'s `require-dev` is what
     * this package asks Composer for, and `installed.json` is what it got. Either half disagreeing
     * means the gate would measure this repository with a tool other than the one it pinned — with
     * a linter that formats differently, a Pest that counts differently — and neither half is
     * visible in the path.
     *
     * @param  array<string, array{path: string, pin: string, why: string}>  $manifest
     * @param  array<string, string>  $required  `composer.json`'s `require-dev`
     * @param  array<string, string>  $installed  what `composer install` put in `vendor/`
     * @return list<string>
     */
    public static function drift(array $manifest, array $required, array $installed): array
    {
        $found = [];

        foreach ($manifest as $name => $entry) {
            $package = self::package($entry['path']);

            if (! array_key_exists($package, $required)) {
                $found[] = sprintf(
                    '%s runs %s, which composer.json does not require at a version',
                    self::MANIFEST,
                    $package,
                );
            } elseif (self::version($required[$package]) !== $entry['pin']) {
                $found[] = sprintf(
                    'composer.json requires %s at %s, and %s pins %s at %s',
                    $package,
                    $required[$package],
                    self::MANIFEST,
                    $name,
                    $entry['pin'],
                );
            }

            if (! array_key_exists($package, $installed)) {
                $found[] = sprintf(
                    '%s pins %s at %s, and no version of %s is installed here',
                    self::MANIFEST,
                    $name,
                    $entry['pin'],
                    $package,
                );
            } elseif ($installed[$package] !== $entry['pin']) {
                $found[] = sprintf(
                    '%s pins %s at %s, and %s is installed — a tool that moved measures something else',
                    self::MANIFEST,
                    $name,
                    $entry['pin'],
                    $installed[$package],
                );
            }
        }

        return $found;
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
     * The commands a workflow's steps run, as the YAML has them, one entry per `run:` step.
     *
     * Parsed rather than searched, because this file discusses the gate in its own words — the step
     * that runs `composer checks -- --require-all` explains itself in a comment beside it — and a
     * text search cannot tell a note from an invocation. A shell comment inside a block is dropped
     * for the same reason: `# composer test:unit` is something a reader wrote, and a reading that
     * counted it would answer "the runner runs the gate" about a step that does not.
     *
     * @return list<array{step: string, command: string}>
     *
     * @throws RuntimeException when the text is not YAML, has no `jobs` to read, or has no step
     *                          that runs anything — each a way this could answer "nothing is wrong"
     *                          about a file it never understood
     */
    public static function stepsIn(string $workflow): array
    {
        try {
            $parsed = Yaml::parse($workflow);
        } catch (ParseException $error) {
            throw new RuntimeException(
                'The workflow handed to this reader is not YAML: '.$error->getMessage(),
                0,
                $error,
            );
        }

        $jobs = is_array($parsed) && is_array($parsed['jobs'] ?? null) ? $parsed['jobs'] : [];

        if ($jobs === []) {
            throw new RuntimeException('The workflow handed to this reader has no `jobs` to read.');
        }

        $steps = [];

        foreach ($jobs as $job => $spec) {
            $listed = is_array($spec) && is_array($spec['steps'] ?? null) ? $spec['steps'] : [];

            foreach ($listed as $index => $step) {
                if (! is_array($step) || ! is_string($step['run'] ?? null)) {
                    continue;
                }

                $named = $step['name'] ?? null;

                $steps[] = [
                    'step' => is_string($named) ? $named : $job.' step '.((int) $index + 1),
                    'command' => self::shell($step['run']),
                ];
            }
        }

        if ($steps === []) {
            throw new RuntimeException('The workflow handed to this reader has no step that runs a command.');
        }

        return $steps;
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
     * @param  array<string, array{path: string, pin: string, why: string}>  $manifest
     * @param  array<string, string>  $sources  reader => its text
     * @return list<string>
     */
    public static function secondCopies(array $manifest, array $sources): array
    {
        $found = [];

        foreach ($sources as $reader => $source) {
            foreach ($manifest as $name => $entry) {
                foreach ([$entry['path'], 'vendor/bin/'.$name] as $written) {
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
     * Every tool path written down somewhere other than the manifest.
     *
     * The reading the tool-path guard asserts on, as one call, so the test and the mutation harness
     * ask the same question rather than each walking the same three places: a composer script that
     * runs a path or a `vendor/bin` shim instead of handing a name to `bin/tool.php`, one of the
     * programs in `bin/` that writes the path down again, and the runner — which is the third place
     * a tool can be invoked and the one that had no reader at all.
     *
     * The two facts beside a path (the pin and what the tool is for) are not read here: they are
     * about an installed tree rather than about a path written down, and the tests that hold them
     * to `composer.json` and to what is installed are the ones that read them.
     *
     * @return list<string>
     */
    public static function copies(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $manifest = self::manifest($root.'/'.self::MANIFEST);
        $scripts = self::scriptsIn((string) file_get_contents($root.'/composer.json'));

        $workflow = (string) file_get_contents($root.'/'.self::WORKFLOW);

        return [
            ...self::foreignScripts($scripts),
            ...self::secondCopies($manifest, self::readerSources($root)),
            ...self::secondCopies($manifest, [self::WORKFLOW => $workflow]),
        ];
    }

    /**
     * The programs that run a tool, as path => the text it is read from.
     *
     * The reader walk the tool-path guard is about, written here so the test, the mutation harness
     * and `copies()` share one definition of "the programs that run a tool". A reader that moved
     * would otherwise be a path somebody writes down beside a list that no longer names it.
     *
     * @return array<string, string>
     */
    public static function readerSources(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $sources = [];

        foreach (self::READERS as $reader) {
            $sources[$reader] = (string) file_get_contents($root.'/'.$reader);
        }

        return $sources;
    }

    /**
     * Every tool something in this package asks the manifest for, and what asked: a script, which
     * hands the name to `bin/tool.php`, or one of the programs, which looks the name up itself.
     *
     * The name walk the guard is about, as one call: a name that is missing reads as "not installed"
     * at runtime, which is a skip in the summary that looks like a machine without the tool rather
     * than a typo here. The walk lives here rather than in the test so the test and the mutation
     * harness ask the same thing.
     *
     * @return list<array{from: string, tool: string}>
     */
    public static function asked(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $asked = [];

        foreach (self::scriptsIn((string) file_get_contents($root.'/composer.json')) as $script) {
            if ($script['path'] !== 'bin/tool.php') {
                continue;
            }

            $asked[] = ['from' => 'the '.$script['script'].' script', 'tool' => (string) strtok($script['arguments'], ' ')];
        }

        foreach (self::readerSources($root) as $reader => $source) {
            foreach (self::namesIn($source) as $name) {
                $asked[] = ['from' => $reader, 'tool' => $name];
            }
        }

        return $asked;
    }

    /**
     * Every name something asks for that the manifest does not hold, and every name nothing asks for.
     *
     * The guard's name half, as one call: the manifest and the askers are gathered here so the test
     * and the mutation harness ask the same thing rather than each reading the two sides on its own.
     *
     * @return list<string>
     */
    public static function unsharedNames(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');

        return self::unshared(self::manifest($root.'/'.self::MANIFEST), self::asked($root));
    }

    /**
     * Every pin that disagrees with `composer.json` or with what is installed here.
     *
     * The guard's pin half, as one call: the manifest, the requirement and Composer's own record of
     * what it installed are gathered here so the test and the mutation harness ask the same thing
     * rather than each reading the three sides on its own.
     *
     * @return list<string>
     */
    public static function pinDrift(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');

        return self::drift(
            self::manifest($root.'/'.self::MANIFEST),
            self::required((string) file_get_contents($root.'/composer.json')),
            self::installed($root.'/vendor/composer/installed.json'),
        );
    }

    /**
     * What `composer.json` asks for, as package => constraint: the first half a pin is compared with.
     *
     * Read here rather than in the test so the requirement has one definition, and a mutation can
     * move a constraint and ask the pin reading to name it.
     *
     * @return array<string, string>
     */
    public static function required(string $composerJson): array
    {
        $composer = json_decode($composerJson, true);

        return is_array($composer) && is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : [];
    }

    /**
     * What the runner is missing, held to this gate rather than to a list of the gate's tools.
     *
     * The runner once ran a gate of its own — the four-step `composer test` subset — and the fix was
     * to run the whole thing rather than to keep two lists in step. What makes that a fact rather
     * than a comment in the file is this reading: the scripts the runner has to run, and the
     * argument one of them has to carry, are stated once in `CI`, and they are looked for in the
     * steps the workflow really runs. A workflow that dropped `composer checks`, ran it without
     * `--require-all`, or stopped enforcing the floor would be a build weaker than the gate a tag is
     * cut behind — and weaker without saying so.
     *
     * @param  list<array{step: string, command: string}>  $steps
     * @return list<string>
     */
    public static function gateGaps(array $steps): array
    {
        $found = [];

        foreach (self::CI as $script => $argument) {
            $ran = false;
            $bare = false;

            foreach ($steps as $step) {
                if (preg_match(self::invocation($script), $step['command']) !== 1) {
                    continue;
                }

                $ran = true;

                if ($argument !== '' && preg_match(self::flag($argument), $step['command']) !== 1) {
                    $bare = true;
                }
            }

            if (! $ran) {
                $found[] = sprintf(
                    'the runner never runs `composer %s`, so what it gates is weaker than the gate a tag is cut behind',
                    $script,
                );
            } elseif ($bare) {
                $found[] = sprintf(
                    'the runner runs `composer %s` without `%s`, so a check that skipped reads as a green build',
                    $script,
                    $argument,
                );
            }
        }

        return $found;
    }

    /** The manifest's own rules: a path inside `vendor/`, not a shim, on disk, an exact pin, and a
     * line saying what the tool is for.
     *
     * The pin is read as an exact version rather than as a constraint, because it is the version a
     * tool is run at: `^4.7` describes a set, and a set is what `composer.json` already states. The
     * line about what a tool is for is read for the same reason it exists — the gate prints it, so
     * an entry without one is a tool the gate has nothing to say about.
     *
     * @param  array<string, array{path: string, pin: string, why: string}>  $manifest
     * @return list<string>
     */
    public static function problems(array $manifest, string $root): array
    {
        $found = [];

        foreach ($manifest as $name => $entry) {
            $path = $entry['path'];

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

            if (preg_match('/^[0-9]+(?:\.[0-9]+)*$/', $entry['pin']) !== 1) {
                $found[] = sprintf('%s pins %s at %s, which is not the version itself', $name, self::package($path), $entry['pin']);
            }

            if (trim($entry['why']) === '') {
                $found[] = sprintf('%s does not say what %s is for', self::MANIFEST, $name);
            }
        }

        return $found;
    }

    /**
     * Names something asks for that the manifest does not hold, and names nothing asks for.
     *
     * @param  array<string, array{path: string, pin: string, why: string}>  $manifest
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

    /** A `composer <script>` invocation, wherever it sits in a step's command. */
    private static function invocation(string $script): string
    {
        return '~(?<![\w./-])composer\s+'.preg_quote($script, '~').'(?![\w:.-])~';
    }

    /** A flag a command has to carry, as its own word rather than as the beginning of another. */
    private static function flag(string $argument): string
    {
        return '~(?<![\w-])'.preg_quote($argument, '~').'(?![\w-])~';
    }

    /**
     * A `run:` block as the command it is, with the shell comments taken out of it.
     *
     * What a step does is its command; a `#` line is what somebody wrote beside it. The difference
     * is made once, here, because a note naming the gate inside a script that does not run it is
     * exactly the answer this reading must not give.
     */
    private static function shell(string $run): string
    {
        $lines = [];

        foreach (preg_split('~\R~', $run) ?: [] as $line) {
            if (preg_match('~^\s*#~', $line) !== 1) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * A version as Composer writes it, in the form the pin is written in.
     *
     * Composer records some packages with the `v` their tag carries and some without, and
     * `composer.json` is written without it. One spelling is the version's, and this is the one.
     */
    private static function version(string $version): string
    {
        return ltrim($version, 'v');
    }
}
