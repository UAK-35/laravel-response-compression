#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Run one of this package's tools, by the name the manifest gives it
|--------------------------------------------------------------------------
|
| `composer.json` runs a tool in four of its scripts, and a composer script is a shell string: it
| cannot read a manifest, so a path written into one would be a second copy of a path that
| `bin/tool-paths.php` already holds. That is what this is for — the script names this program
| and the tool, and the path is looked up in the one place it is written.
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
| USAGE
| -----
|   php bin/tool.php <tool> [arguments]
|
| Exit code is the tool's own, or 2 for a usage error: no tool named, a name the manifest does not
| have, a tool that is not installed, a manifest that cannot be read.
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
    fwrite(STDERR, 'Usage: php bin/tool.php <tool> [arguments]'.PHP_EOL);
    fwrite(STDERR, 'Tools: '.implode(', ', array_keys($paths)).PHP_EOL);
    exit(2);
}

if (! isset($paths[$name])) {
    fwrite(STDERR, $name.' is not a tool this package runs.'.PHP_EOL);
    fwrite(STDERR, 'Tools: '.implode(', ', array_keys($paths)).PHP_EOL);
    exit(2);
}

$tool = $root.'/'.ltrim((string) $paths[$name], '/');

// The manifest is checked by the suite, so this is the case where the dependencies were never
// installed — where the useful answer is the one below rather than PHP's, about a file it could
// not open.
if (! is_file($tool)) {
    fwrite(STDERR, $name.' is not installed: '.$paths[$name].' is not there.'.PHP_EOL);
    fwrite(STDERR, 'Run `composer install`.'.PHP_EOL);
    exit(2);
}

exit(run([PHP_BINARY, $tool, ...$arguments], $root));

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
