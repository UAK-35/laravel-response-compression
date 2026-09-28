<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRun;

/*
|--------------------------------------------------------------------------
| The floor runner
|--------------------------------------------------------------------------
|
| `bin/coverage.php` runs on include and calls `exit()`, so it is reached here the way the suite
| reaches it everywhere else: as a child process. What is asserted is the header it opens with —
| the two facts the report never carried, and the ones a floor is read against. A floor is recorded
| under one interpreter, `composer test:unit` runs the script under Composer's own, and a run with
| nothing loaded that can count a line measures 0.0% for every file. Neither is a regression, and
| both read as one unless the run says which machine it was on.
|
| The run is stopped before the suite with Pest's own `--help`, which this script forwards because
| everything it is given goes to Pest. That is not a test about Pest's help: it is the cheapest run
| that reaches the header and then collects nothing, and a run that collects nothing is exactly what
| a machine with no driver produces. It is therefore also the failure this header has to survive —
| the floor is not reported at all rather than reported as a floor of nothing.
|
*/

/**
 * This package's own floor runner, in place rather than in a planted tree: it resolves the
 * repository from its own path, and the interpreter it names is the one the suite is running under.
 */
function floorRun(string ...$arguments): ReleaseRun
{
    $root = packageRoot();
    $command = [PHP_BINARY, $root.'/bin/coverage.php', ...$arguments];

    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root,
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

it('names the PHP it is running under, and the driver, before and after a run that measured nothing', function (): void {
    $run = floorRun('--help');
    $binary = str_replace('\\', '/', PHP_BINARY);

    expect($run->output)
        ->toContain('Coverage floor, measured under:')

        // The interpreter, as this run found it, by version and by path: two PHP installs of the same
        // version are the case the path is for, and a floor that fails a script by a point is
        // attributable to one of them rather than to the tree.
        ->toContain(PHP_VERSION)
        ->toContain($binary)

        // And the driver. Which answer this row has depends on the machine, so the row is asserted
        // rather than its value: the test has to pass on a runner with PCOV and on a workstation
        // with none, because the second is the machine the header exists for.
        ->toContain('coverage driver')

        // The interpreter again at the foot of the failure. `--help` collects nothing, so this is the
        // end of the run, and a log is read from the bottom: the reason has to be there rather than
        // two minutes of output above it.
        ->and($run->exitCode)->toBe(1)
        ->and($run->error)->toContain('No script coverage was collected')
        ->and($run->error)->toContain(PHP_VERSION)
        ->and($run->error)->toContain($binary)

        // A failure to measure is not a floor that was missed, and it must not report one: no file was
        // measured, so no file may be named as below its floor.
        ->and($run->error)->not->toContain('below its floor');
});
