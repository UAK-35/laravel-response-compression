<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;
use Uak35\ResponseCompression\Tests\Support\ReleaseRun;

/*
|--------------------------------------------------------------------------
| The gate
|--------------------------------------------------------------------------
|
| `bin/checks.php` is the script everything else is measured by, and until now no test had ever
| run it. The release rails plant a three-line `exit(1)` stub where it should be whenever they need
| a gate that fails on cue, which is the right stub to plant and the reason the real gate read 0.0%
| on the coverage floor: it was never executed by the suite at all.
|
| So these run it. A planted tree is the right place for that — the gate is a program about a
| directory: it walks it, parses it, and answers one exit code — and half of what it does on such a
| tree is refuse politely, because a tree without `vendor/` has none of the tools it runs. The
| skips are the point of the gate on a developer's machine, and they are the half nobody could see.
|
| Every assertion here is about a fact the tree decides, so none of them turn on where this runs:
| the tool paths are the planted tree's own — the manifest is copied in beside the gate — and a
| tree that was never installed has none of them on any platform.
|
*/

/**
 * A planted tree with the real gate in it, in place of the stub the release rails plant.
 *
 * The manifest comes with it, because a gate and the paths it runs are one arrangement, and this
 * fixture is the arrangement this package ships: a tree carrying the gate but not
 * `bin/tool-paths.php` is a tree of a different shape, and the two are told apart by a test rather
 * than by accident.
 */
function gateRepo(): ReleaseRepo
{
    $repo = ReleaseRepo::make();
    $repo->write('bin/checks.php', (string) file_get_contents(ReleaseRepo::package().'/bin/checks.php'));
    $repo->write('bin/tool-paths.php', (string) file_get_contents(ReleaseRepo::package().'/bin/tool-paths.php'));

    return $repo;
}

function gate(ReleaseRepo $repo, string ...$arguments): ReleaseRun
{
    return $repo->script('bin/checks.php', ...$arguments);
}

it('lists what it would run without running any of it', function (): void {
    $run = gate(gateRepo(), '--list');

    expect($run->exitCode)->toBe(0)
        ->and($run->said('PHP syntax (php -l)'))->toBeTrue()
        ->and($run->said('Test suite (pest)'))->toBeTrue();
});

it('refuses an option it does not have, and says what it does have', function (): void {
    $run = gate(gateRepo(), '--nope');

    // A usage error is not a failed check, so it is a different code: 2, which is what a caller
    // scripting this has to be able to tell apart from "the tree is bad". The complaint is on the
    // error stream because it is a complaint, and the usage is on the output stream because it is
    // the answer to anyone who reads the screen — which is where it has to be to be readable.
    expect($run->exitCode)->toBe(2)
        ->and($run->error)->toContain('Unknown option')
        ->and($run->output)->toContain('Usage:');
});

it('passes a tree that parses, and names the file that does not', function (): void {
    $repo = gateRepo();

    $clean = gate($repo, '--only=syntax');

    expect($clean->exitCode)->toBe(0)
        ->and($clean->output)->toContain('no syntax errors');

    // The half that matters: a file that cannot be parsed is a failure rather than a note, and the
    // summary names it, because the whole point of the gate is being able to believe the tick.
    $repo->write('src/Broken.php', "<?php\n\nfunction nope(\n");

    $broken = gate($repo, '--only=syntax');

    expect($broken->exitCode)->toBe(1)
        ->and($broken->output)->toContain('src/Broken.php');
});

it('skips a tool the tree does not have, and refuses to when it is told to', function (): void {
    $repo = gateRepo();

    // A tree that was never installed has no `vendor/`, so every tool the gate runs is absent —
    // which is a skip on a workstation and a hole in the build on a runner.
    $skipped = gate($repo, '--only=pint');

    expect($skipped->exitCode)->toBe(0)
        ->and($skipped->output)->toContain('is not installed');

    $required = gate($repo, '--only=pint', '--require-all');

    expect($required->exitCode)->toBe(1);
});

it('reads a tree with no manifest as a tree with no tools', function (): void {
    // The gate is not only pointed at this package. `--only=pint` on a tree that has neither the
    // manifest nor a `vendor/` is the shape a checkout whose dependencies were never installed has,
    // and it is the shape every planted fixture here has: no manifest is no tool, which skips —
    // where an include of a file that is not there would end the run before the first check.
    $repo = gateRepo();
    $repo->delete('bin/tool-paths.php');

    $run = gate($repo, '--only=pint');

    expect($run->exitCode)->toBe(0)
        ->and($run->output)->toContain('laravel/pint is not installed')

        // And the list is still the answer to "what would this run?", which is the half a tree with
        // nothing in it can still give.
        ->and(gate($repo, '--list')->output)->toContain('Code style (pint --test)');
});

it('checks only what a commit carries, and says so when that is nothing', function (): void {
    // `--staged` is what the pre-commit hook runs, and on a tree with nothing staged the three
    // checks it narrows to have nothing to read. Reporting that as a pass is the whole reason the
    // hook can run in front of every commit without blocking one.
    $run = gate(gateRepo(), '--staged');

    expect($run->exitCode)->toBe(0)
        ->and($run->output)->toContain('no staged');
});
