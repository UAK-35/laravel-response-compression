<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;
use Uak35\ResponseCompression\Tests\Support\ReleaseRun;

/*
|--------------------------------------------------------------------------
| The runner the composer scripts use
|--------------------------------------------------------------------------
|
| `composer lint` is `@php bin/tool.php pint`, and the path behind that name is looked up in
| `bin/tool-paths.php`. Two things are worth pinning: that a name resolves to the entry file the
| manifest holds, and that the tool's exit code comes back out — a script that runs one tool has to
| mean what that tool means, or a red tool upstream reads as a green build here.
|
| The manifest is the one file this program cannot do without, so the ways it can fail to name a
| tool are refusals of its own: a file that is not there, and a file that returns no name. Both
| would otherwise be a fatal out of a `require`, which is PHP's words about a file rather than this
| program's about the tree.
|
| The dispatch is driven against a planted tree rather than the package's own manifest, because a
| manifest is a file and the tools it names can then be files of the test's own choosing. What the
| tool it plants does is write down where it was run from and exit with a code nothing else uses,
| so the working directory and the status are read rather than inferred, and no assertion here
| turns on whether Pest happens to be installed.
|
| The one question a fixture cannot answer is whether the manifest this package really ships
| resolves, so that is asked of the real tree too: a path that is not there fails in the tool's own
| words rather than reading as a check that was skipped.
|
*/

/**
 * A tree with the runner in it, a manifest of its own, and one tool behind it.
 */
function toolRepo(): ReleaseRepo
{
    $repo = ReleaseRepo::plain();

    $repo->write('bin/tool.php', (string) file_get_contents(ReleaseRepo::package().'/bin/tool.php'));
    $repo->write('bin/tool-paths.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ['doer' => 'vendor/doer/bin/doer'];\n");
    // Written as a string rather than a nowdoc because the content has no indentation of its own
    // and none is wanted: this file runs as a script, and its lines start where a script's do.
    $repo->write('vendor/doer/bin/doer', "<?php\n\n"
        ."fwrite(STDOUT, 'doer in '.(string) getcwd().PHP_EOL);\n"
        ."fwrite(STDERR, 'doer said'.PHP_EOL);\n"
        ."exit(7);\n");

    return $repo;
}

/**
 * This package's own runner, in place, for the one question a planted tree cannot ask.
 */
function toolRun(string ...$arguments): ReleaseRun
{
    $command = [PHP_BINARY, packageRoot().'/bin/tool.php', ...$arguments];

    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        packageRoot(),
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Could not run '.implode(' ', $command));
    }

    fclose($pipes[0]);
    $output = (string) stream_get_contents($pipes[1]);
    $error = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return new ReleaseRun(proc_close($process), $output, $error, implode(' ', $command));
}

it('runs the entry file the manifest names, from the package root, and returns its exit code', function (): void {
    $repo = toolRepo();
    $run = $repo->script('bin/tool.php', 'doer', '--so', 'many');

    // 7 is a code nothing in the runner uses, so it can only have come back from the tool: a wrapper
    // that swallowed a tool's status would report every script as passing.
    expect($run->exitCode)->toBe(7)

        // And both streams are the tool's own, inherited rather than collected — where a tool writes
        // is part of what it is worth, and a wrapper that merged them would hide half of it.
        ->and(str_replace('\\', '/', $run->output))->toContain(str_replace('\\', '/', $repo->path))
        ->and($run->error)->toContain('doer said');
});

it('refuses a tool that is not installed, and a name the manifest does not have', function (): void {
    $repo = toolRepo();

    // The manifest names a file this tree does not have. That is a missing dependency, and it is the
    // one failure a wrapper has to explain rather than pass through as PHP's own words about a file
    // it could not open.
    $repo->delete('vendor/doer/bin/doer');

    $missing = $repo->script('bin/tool.php', 'doer');

    expect($missing->exitCode)->toBe(2)
        ->and($missing->error)->toContain('doer is not installed')
        ->and($missing->error)->toContain('vendor/doer/bin/doer')
        ->and($missing->error)->toContain('composer install');

    // A name the manifest never had, which is a typo in a composer script far more often than it is
    // anything else: the names it does have are the answer, so they are what the refusal prints.
    $unknown = $repo->script('bin/tool.php', 'pint');

    expect($unknown->exitCode)->toBe(2)
        ->and($unknown->error)->toContain('pint is not a tool this package runs')
        ->and($unknown->error)->toContain('Tools: doer')
        ->and($unknown->output)->toBe('');
});

it('refuses a manifest it cannot read, rather than quietly having no tools', function (): void {
    $repo = toolRepo();

    // A manifest that is not there is not "no tools installed": with nothing to look a name up in,
    // every tool this package runs is unreachable, and that is worth saying rather than handing back
    // PHP's own complaint about a file it could not include.
    $repo->delete('bin/tool-paths.php');

    $gone = $repo->script('bin/tool.php', 'doer');

    expect($gone->exitCode)->toBe(2)
        ->and($gone->error)->toContain('bin/tool-paths.php is missing')
        ->and($gone->error)->toContain('every tool path is written')
        ->and($gone->output)->toBe('');

    // And a manifest that is there but names nothing: an empty array is how a list with no rows in
    // it reads from the outside, and a string is what a file written in the wrong shape returns.
    // Both are the same answer, because in both no name can be looked up.
    foreach (["<?php\n\nreturn [];\n", "<?php\n\nreturn 'vendor/doer/bin/doer';\n"] as $manifest) {
        $repo->write('bin/tool-paths.php', $manifest);

        $empty = $repo->script('bin/tool.php', 'doer');

        expect($empty->exitCode)->toBe(2)
            ->and($empty->error)->toContain('bin/tool-paths.php names no tool');
    }
});

it('says how it is used when it is given no tool, and runs one from this package when it is', function (): void {
    $repo = toolRepo();

    // Nothing, and something that looks like an option: both are usage errors, and the list of names
    // is the line that answers either of them.
    foreach ([[], ['--help']] as $arguments) {
        $run = $repo->script('bin/tool.php', ...$arguments);

        expect($run->exitCode)->toBe(2)
            ->and($run->error)->toContain('Usage: bin/tool.php <tool>')
            ->and($run->error)->toContain('Tools: doer');
    }

    // The manifest this package ships, asked for the cheapest tool it names: a path that did not
    // resolve would fail in the tool's own words, which is the half of this a fixture cannot see.
    // Pest is the one to ask because `--version` starts it, prints one line and stops.
    $real = toolRun('pest', '--version');

    expect($real->exitCode)->toBe(0)
        ->and($real->output)->toContain('Pest')
        ->and($real->error)->toBe('');
});
