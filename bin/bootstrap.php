#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Set this clone up, in one command
|--------------------------------------------------------------------------
|
| A fresh clone of this package is three things short of being able to answer anything: it does
| not say which PHP this machine runs, it has no `vendor/`, and nothing has been verified on it.
| Each of those has a command of its own, they are run in a particular order, and the order is the
| part a reader has to get right - `vendor/` has to exist before anything measures the tree, and
| the machine file has to exist before the toolchain is asked which interpreter it is under. That
| is a paragraph of a runbook, and a paragraph is what gets half-followed.
|
| So it is one command:
|
|   php bin/bootstrap.php
|
| WHAT IT DOES, IN ORDER
| ----------------------
|   1. NAMES THIS MACHINE'S PHP. `.agents/render_local.py --init` is asked to write
|      `.agents/machine.local.json` - naming the interpreter this very run is under, because the
|      one that is executing this file is the one this checkout can rely on - and to render the
|      settings files from it. A clone that already has the file is left alone, and `--force` is
|      what replaces it.
|   2. INSTALLS THE DEPENDENCIES. `composer install`, run by the PHP that was just named: the
|      tools this package's gate drives are all `require-dev`, so nothing else works before this.
|   3. RUNS THE WHOLE VERIFICATION. `python .agents/verify.py`, which is the tools and the gate and
|      the agentic layer in one run - so the answer to "is this clone set up" is the same sentence
|      as "does this clone pass", rather than a hopeful silence.
|
| THE THREE NEIGHBOURS ARE FOUND, NOT WRITTEN DOWN
| -----------------------------------------------
| Python, Composer and PowerShell 7 are resolved from `PATH` (and, on Windows, from the directory
| PowerShell installs itself into) rather than written into this file: a path in a committed file
| resolves on exactly one machine, which is the rule this package keeps two guards about. Each can
| be named instead - `--python`, `--composer`, `--pwsh` - and this very interpreter can be replaced
| with `--php`. A fresh clone has no `composer.phar` (this is a library, and its dependencies are
| not installed to get one), so Composer must be reachable as `composer` on `PATH` or placed beside
| `composer.json` by hand; this program says which of the two it found and refuses, naming both
| ways, rather than installing with something that is not there.
|
| USAGE
| -----
|   php bin/bootstrap.php                  name, install, verify
|   php bin/bootstrap.php --dry-run        print the three steps and change nothing
|   php bin/bootstrap.php --skip-install   a clone whose dependencies are already in place
|   php bin/bootstrap.php --skip-verify    set the clone up and leave the verifying to you
|   php bin/bootstrap.php --force          replace .agents/machine.local.json
|   php bin/bootstrap.php --help
|
| Exit code is 0 when every step it was asked to run succeeded, 1 when one did not (or when a
| program it needs is not here), and 2 for a usage error.
*/

/**
 * @guards-index guard `bin/bootstrap.php`
 *
 * a clone that is half set up and reads as ready: the three steps a fresh checkout needs — name this machine's PHP, install the dependencies, verify the tree — in the one order that works, each ending the run when it fails rather than being followed by the next
 */
$root = str_replace('\\', '/', dirname(__DIR__));

// ─────────────────────────────────────────────────────────────────────────────
// CLI
// ─────────────────────────────────────────────────────────────────────────────

$options = [
    'php' => '',
    'pwsh' => '',
    'python' => '',
    'composer' => '',
    'force' => false,
    'install' => true,
    'verify' => true,
    'dry-run' => false,
];

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        usage();
        exit(0);
    }

    if ($argument === '--dry-run') {
        $options['dry-run'] = true;

        continue;
    }

    if ($argument === '--force') {
        $options['force'] = true;

        continue;
    }

    if ($argument === '--skip-install') {
        $options['install'] = false;

        continue;
    }

    if ($argument === '--skip-verify') {
        $options['verify'] = false;

        continue;
    }

    $taken = false;

    foreach (['php', 'pwsh', 'python', 'composer'] as $name) {
        if (str_starts_with($argument, '--'.$name.'=')) {
            $options[$name] = substr($argument, strlen($name) + 3);
            $taken = true;

            break;
        }
    }

    if ($taken) {
        continue;
    }

    // On STDERR, because a usage error is not part of a transcript of the run: a caller that
    // collected this step's output should not find a complaint about the command inside it.
    fwrite(STDERR, "bin/bootstrap.php: $argument is not an option this has.\n\n");
    usage(STDERR);
    exit(2);
}

// ─────────────────────────────────────────────────────────────────────────────
// what each step needs
// ─────────────────────────────────────────────────────────────────────────────

// The interpreter this run is under is the one this checkout should be named after: it exists, it
// is on this machine, and it is the one that just started this program. A `--php` is a deliberate
// replacement of that.
$php = $options['php'] !== '' ? $options['php'] : str_replace('\\', '/', PHP_BINARY);

$python = $options['python'] !== '' ? $options['python'] : find(['python3', 'python', 'py']);
$pwsh = $options['pwsh'] !== '' ? $options['pwsh'] : powershell();

$composer = $options['composer'] !== '' ? $options['composer'] : composer($root);

$machine = $root.'/.agents/machine.local.json';
$missing = [];

// Each line is what is missing *and* how to give it, because the reader of a refusal on a fresh
// clone is somebody who has just cloned this and has neither of the two answers yet.
if (! is_file($php)) {
    $missing[] = "php: $php is not a file, so the interpreter this clone would be named after is not here";
}

if ($python === '') {
    $missing[] = 'python: no Python 3 on PATH - name one with --python=<path to python>'
        .' (the renderer that writes the machine file, and the agentic checks, are Python)';
} elseif (! is_file($python)) {
    $missing[] = "python: $python is not a file";
}

if ($options['install'] && $composer === '') {
    $missing[] = 'composer: no `composer` on PATH and no composer.phar beside composer.json'
        .' - install Composer, or put composer.phar in the package root, or name one with --composer=<path>';
} elseif ($options['install'] && ! is_file($composer)) {
    $missing[] = "composer: $composer is not a file";
}

// PowerShell is deliberately not in that list. A machine with no PowerShell 7 is a machine that
// runs the Windows-only snippets nowhere, and this file has nothing to add to that: the renderer's
// own default is written down, and `python .agents/verify.py` is what reports it if it matters.

if ($missing !== []) {
    fwrite(STDERR, "bin/bootstrap.php: this clone cannot be set up yet.\n");

    foreach ($missing as $line) {
        fwrite(STDERR, "  - $line\n");
    }

    exit(1);
}

// ─────────────────────────────────────────────────────────────────────────────
// the three steps
// ─────────────────────────────────────────────────────────────────────────────

$total = 1 + ($options['install'] ? 1 : 0) + ($options['verify'] ? 1 : 0);
$step = 0;

step(++$step, $total, 'name this machine\'s PHP');

if (is_file($machine) && ! $options['force']) {
    note('.agents/machine.local.json is already here - pass --force to name this machine again');
} else {
    $command = [
        $python, '.agents/render_local.py', '--init', '--yes',
        '--php='.$php,
        '--pwsh='.$pwsh,
        '--package='.$root,
    ];

    if ($options['force']) {
        $command[] = '--force';
    }

    if ($options['dry-run']) {
        note(implode(' ', $command));
    } elseif (run($command, $root) !== 0) {
        refusals("the machine file could not be written by $python .agents/render_local.py --init");
    }
}

if ($options['install']) {
    step(++$step, $total, 'install the dependencies');

    if ($options['dry-run']) {
        note('composer install');
    } elseif (run(install($php, $composer), $root) !== 0) {
        refusals('composer install failed, so there is nothing for the gate to run against');
    }
}

if ($options['verify']) {
    step(++$step, $total, 'run the whole verification');

    if ($options['dry-run']) {
        note('python .agents/verify.py');
    } elseif (run([$python, '.agents/verify.py'], $root) !== 0) {
        refusals('python .agents/verify.py reported a finding, so this clone is not green');
    }
}

echo "\nbin/bootstrap.php: done.\n";

if ($options['dry-run']) {
    echo "  nothing was changed: this was the plan.\n";
}

exit(0);

// ─────────────────────────────────────────────────────────────────────────────
// helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * One step, as a reader follows it: which of how many, and what it is about to do.
 */
function step(int $number, int $total, string $title): void
{
    echo "\n--- $number/$total $title\n";
}

/** A line about a step, rather than a line about a failure. */
function note(string $message): void
{
    echo "    $message\n";
}

/**
 * A step that failed, as the end of the run.
 *
 * Every refusal says which step it was, because the three are ordered and the one that failed is
 * the one that answers what to fix - a second run continues from where this one stopped.
 */
function refusals(string $message): never
{
    fwrite(STDERR, "\n[FAIL] $message\n");

    exit(1);
}

/**
 * The command that installs the dependencies, from wherever Composer turned out to be.
 *
 * `composer` on `PATH` is a program of its own - a phar with a shebang, or a wrapper - so it is run
 * as one. The `composer.phar` beside `composer.json` is not: it is a file PHP opens, so it is run
 * by the interpreter this clone is being named after rather than by whichever `php` a shebang
 * would reach.
 */
function install(string $php, string $composer): array
{
    if (str_ends_with($composer, '.phar')) {
        return [$php, $composer, 'install', '--no-interaction'];
    }

    return [$composer, 'install', '--no-interaction'];
}

/**
 * Composer, as this checkout can reach it: the phar beside `composer.json` when there is one, and
 * otherwise the program on `PATH`. Empty when neither is here, which is what the caller refuses.
 *
 * The order is deliberate. A phar in the package root is a decision somebody made about *this*
 * checkout - a version to install with - where `PATH` is whatever the machine happens to have.
 */
function composer(string $root): string
{
    $phar = $root.'/composer.phar';

    if (is_file($phar)) {
        return $phar;
    }

    return find(['composer']);
}

/**
 * PowerShell 7, as this machine has it: on `PATH`, and then where the installer puts it.
 *
 * The second rung is why this is not just `find()`: PowerShell 7 installs itself beside the other
 * Windows programs and does not always add itself to `PATH`, so a machine whose shell is not
 * named on the path is the ordinary case rather than an odd one. The value is read out of the
 * environment rather than written here, because the directory it holds is a path that resolves on
 * one machine - which is the whole reason this repository has a machine file and two guards.
 *
 * With neither, the renderer's own default is answered: a token with no value is reported by the
 * renderer, and a name that is not on the path is a finding `python .agents/verify.py` makes in
 * its own words, which is better than this file inventing a second opinion about a Windows
 * installation.
 */
function powershell(): string
{
    $found = find(['pwsh', 'pwsh.exe']);

    if ($found !== '') {
        return $found;
    }

    foreach (['ProgramFiles', 'ProgramW6432'] as $variable) {
        $base = getenv($variable);

        if (! is_string($base) || $base === '') {
            continue;
        }

        $candidate = rtrim(str_replace('\\', '/', $base), '/').'/PowerShell/7/pwsh.exe';

        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return 'pwsh.exe';
}

/**
 * The first of these names that is a program on `PATH`, as a path rather than as a name.
 *
 * The names are tried in the order they are given rather than the directories being tried in
 * order, which is how `.agents/php-bin.sh` resolves Python too: a machine with `python3` and a
 * stale `python` should answer with the first, whatever order its `PATH` happens to be in. On
 * Windows an executable carries an extension, and only `PATHEXT` knows which ones - so the bare
 * name is tried first (a `.exe` on the path is often reachable without one) and then the name with
 * each extension the machine lists.
 *
 * Empty means nothing was found, which every caller treats as its own refusal rather than as a
 * fallback: an empty string is not a program.
 */
function find(array $names): string
{
    $directories = array_values(array_filter(
        explode(PATH_SEPARATOR, (string) getenv('PATH')),
        static fn (string $directory): bool => $directory !== '',
    ));

    $extensions = [''];

    if (windows()) {
        foreach (explode(';', (string) getenv('PATHEXT')) as $extension) {
            if ($extension !== '') {
                $extensions[] = $extension;
            }
        }
    }

    foreach ($names as $name) {
        foreach ($directories as $directory) {
            foreach ($extensions as $extension) {
                $candidate = rtrim(str_replace('\\', '/', $directory), '/').'/'.$name.$extension;

                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }
    }

    return '';
}

/** Whether this is the platform whose executables carry an extension. */
function windows(): bool
{
    return DIRECTORY_SEPARATOR === '\\';
}

/**
 * One child process, with its own streams.
 *
 * The child inherits this run's three streams rather than having them collected: Composer's
 * progress and the verification's findings are the reason somebody ran this, and a program that
 * swallowed them to print a summary would be reporting that a step passed without showing what it
 * said. The exit code is the whole of what this reads.
 */
function run(array $command, string $directory): int
{
    $process = @proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $directory);

    if (! is_resource($process)) {
        return 1;
    }

    return proc_close($process);
}

/** The three steps, and the options that shape them. */
function usage($stream = STDOUT): void
{
    fwrite($stream, <<<'USAGE'
Usage: php bin/bootstrap.php [--dry-run] [--force] [--skip-install] [--skip-verify]
                             [--php=PATH] [--pwsh=PATH] [--python=PATH] [--composer=PATH]

  (no option)      name this machine's PHP, install the dependencies, verify the clone
  --dry-run        print the three steps and change nothing
  --force          write .agents/machine.local.json again, even though it is already here
  --skip-install   a clone whose dependencies are already in place
  --skip-verify    set the clone up and leave the verifying to the caller
  --php=PATH       the interpreter this clone is named after (default: the one running this)
  --pwsh=PATH      PowerShell 7 (default: found on PATH, then where its installer puts it)
  --python=PATH    Python 3 (default: python3, python, py - on PATH, in that order)
  --composer=PATH  Composer (default: composer.phar beside composer.json, then `composer`)
  --help           this

Exit code is 0 when every step asked for succeeded, 1 when one did not, 2 for a usage error.

USAGE);
}
