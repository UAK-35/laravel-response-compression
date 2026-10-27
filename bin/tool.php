#!/usr/bin/env php
<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\PhpBinary;
use Uak35\ResponseCompression\Tests\Support\Workers;

/*
|--------------------------------------------------------------------------
| Run one of this package's tools, by the name the manifest gives it
|--------------------------------------------------------------------------
|
| `composer.json` runs a tool in four of its scripts, and a composer script is a shell string: it
| cannot read a manifest, so a path written into one would be a second copy of a path that
| `bin/tool-paths.php` already holds. That is what this is for — the script names this program
| and the tool, and the path is looked up in the one place it is written. What an entry holds
| besides the path — the version the tool is pinned at, and what it is for — belongs to the gate
| and to whoever reads this manifest, not to a run: a script that meant to invoke a different
| version would be a second pin, which is the copy this file exists to refuse.
|
|   @php bin/tool.php pint
|
| `@php` is why the path it looks up is the entry file inside `vendor/` rather than a `vendor/bin`
| shim: Composer expands it to the interpreter it is running under, so the tool runs under the PHP
| that installed the dependencies and whose platform requirements the gate checks. On Windows the
| shim is a `.bat` that runs whichever `php` is first on `PATH`, which is a second interpreter with
| a second set of extensions.
|
| The tool runs from the package root — where Composer would have run it — inherits this process'
| streams, and its exit code is the one returned, so a script that runs one tool means what that
| tool means.
|
| One argument is added rather than passed through, and it is the pool: pest asked for `--parallel`
| without a size would start one worker per core, and a worker of this suite is several processes —
| which is a start this machine refuses, `proc_open(): CreateProcess failed` from a file that passes
| when it is asked on its own. So the size is read from the one place that reads it, the same
| answer `bin/checks.php` and `bin/coverage.php` hand pest when they start a pool of their own, and
| a caller that named a pool keeps it.
|
| USAGE
| -----
|   bin/tool.php <tool> [arguments]
|
| The interpreter is the one that started it: `@php` is Composer's own, and a direct run is
| started by whatever PHP the caller used. Which PHP this repository *means* is a different
| question, decided in one place - `.agents/php-bin.sh`, and printed by `sh .agents/php-bin.sh
| --show` - and when the two are not the same install this says so on one line before the tool
| runs. That is the vendor/bin failure this file exists to avoid, one layer up: a shim runs
| whichever `php` is first on PATH, and a `@php` that resolves to a second install is the same
| mistake with the same consequence - a tool measuring something other than the tree.
|
| Exit code is the tool's own, or 2 for a usage error: no tool named, a name the manifest does not
| have, a tool that is not installed, a manifest that cannot be read.
*/

/**
 * @guards-index guard `bin/tool.php`
 *
 * a composer script that writes a tool's path down instead of naming a tool: the script names this program and the tool, the manifest answers with the path, and the exit code is the tool's own — a wrapper that swallowed it would report every script as passing
 */
$root = str_replace('\\', '/', dirname(__DIR__));
$manifest = $root.'/bin/tool-paths.php';

// A manifest that cannot be read is not "no tools installed": with nothing to look a name up in,
// every tool this package runs is unreachable, and saying so is more use than a stack trace out of
// an include.
if (! is_file($manifest)) {
    fwrite(STDERR, 'bin/tool-paths.php is missing, and it is where every tool path is written.'.PHP_EOL);
    exit(2);
}

$paths = require $manifest;

if (! is_array($paths) || $paths === []) {
    fwrite(STDERR, 'bin/tool-paths.php names no tool.'.PHP_EOL);
    exit(2);
}

$name = (string) ($argv[1] ?? '');
$arguments = array_slice($argv, 2);

// A name is a tool; anything option-shaped is a usage error, including `--help`, which has nothing
// to say that the list on the line below does not.
if ($name === '' || str_starts_with($name, '-')) {
    fwrite(STDERR, 'Usage: bin/tool.php <tool> [arguments]'.PHP_EOL);
    fwrite(STDERR, 'Tools: '.implode(', ', array_keys($paths)).PHP_EOL);
    exit(2);
}

if (! isset($paths[$name])) {
    fwrite(STDERR, $name.' is not a tool this package runs.'.PHP_EOL);
    fwrite(STDERR, 'Tools: '.implode(', ', array_keys($paths)).PHP_EOL);
    exit(2);
}

$entry = $paths[$name];
$path = is_array($entry) ? ($entry['path'] ?? '') : '';

// A name with no path in it is an entry this program cannot run, and it is its own refusal rather
// than a "not installed": the file the manifest should have named is missing from the manifest,
// which is a different thing from a dependency that was never installed, and `composer install`
// would not fix it.
if (! is_string($path) || $path === '') {
    fwrite(STDERR, $name.' is named by bin/tool-paths.php without a path to run.'.PHP_EOL);
    exit(2);
}

$tool = $root.'/'.ltrim($path, '/');

// The manifest is checked by the suite, so this is the case where the dependencies were never
// installed — where the useful answer is the one below rather than PHP's, about a file it could
// not open.
if (! is_file($tool)) {
    fwrite(STDERR, $name.' is not installed: '.$path.' is not there.'.PHP_EOL);
    fwrite(STDERR, 'Run `composer install`.'.PHP_EOL);
    exit(2);
}

// One line about the interpreter, when it is not the one this checkout means. Asked through the
// suite's reading rather than by parsing anything here, so the question is answered in one place;
// looked for rather than assumed, because a tree with no dependencies installed is a tree that
// still has to run this far and say the tool is not installed - the case below.
$autoload = $root.'/vendor/autoload.php';

if (is_file($autoload)) {
    require_once $autoload;
}

if (class_exists(PhpBinary::class)) {
    $interpreter = PhpBinary::named($root);

    if ($interpreter->disagreement() !== '') {
        fwrite(STDERR, 'bin/tool.php: '.$interpreter->disagreement().'.'.PHP_EOL);
        fwrite(STDERR, '  sh .agents/php-bin.sh --show says which PHP this checkout means.'.PHP_EOL);
    }
}

$arguments = pool($name, $arguments);

exit(run([PHP_BINARY, $tool, ...$arguments], $root));

/**
 * The arguments the tool is handed, with this package's answer to a pool that was asked for without
 * a size.
 *
 * `--parallel` is paratest, and paratest starts one worker per core unless it is told otherwise. A
 * worker here is not one process: a test that starts a child — and most of this suite's files do —
 * makes every worker two or three, so a pool of one per core is the machine being asked for several
 * processes per core, and what it answers is a refused start. That refusal is the whole of the
 * finding, and it is a fact about the pool rather than about the file that hit it: the same file
 * passes when it is asked on its own. `Tests\Support\Workers` is where the size is read, and the
 * two programs that start a pool of their own ask it the same question — a number written here
 * would be a second number, and this is the one door a hand-run tool goes through.
 *
 * A caller that named a pool keeps it: `--processes` and `-p` are answers of their own, whatever
 * they say, and this reads rather than overrides them. Anything that is not pest, and any pest run
 * that did not ask for a pool, is handed its arguments exactly as they came — there is nothing
 * general here, only the one option shape this package has measured a machine refuse. A tree with
 * no dependencies installed has no class to read either, and then the arguments go as they came
 * rather than the run stopping over a size nobody could look up.
 *
 * The number goes on the option (`--processes=8`) for the reason `Workers` records: pest rebuilds
 * the arguments before paratest sees them, and the value written after the option arrives stripped.
 *
 * @param  list<string>  $arguments
 * @return list<string>
 */
function pool(string $name, array $arguments): array
{
    if ($name !== 'pest' || ! in_array('--parallel', $arguments, true)) {
        return $arguments;
    }

    foreach ($arguments as $argument) {
        if ($argument === '-p' || str_starts_with($argument, '--processes')) {
            return $arguments;
        }
    }

    if (! class_exists(Workers::class)) {
        return $arguments;
    }

    return [...$arguments, ...array_slice(Workers::arguments(), 1)];
}

/**
 * Run the tool with its streams going where this process' streams go, from the package root.
 *
 * @param  list<string>  $command
 */
function run(array $command, string $cwd): int
{
    $process = @proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $cwd);

    if (! is_resource($process)) {
        fwrite(STDERR, 'Could not run '.implode(' ', $command).PHP_EOL);
        exit(2);
    }

    return proc_close($process);
}
