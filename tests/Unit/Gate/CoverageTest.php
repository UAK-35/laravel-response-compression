<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\CoverageEnvironment;
use Uak35\ResponseCompression\Tests\Support\Processes;
use Uak35\ResponseCompression\Tests\Support\ReleaseRun;

/*
|--------------------------------------------------------------------------
| The floor runner
|--------------------------------------------------------------------------
|
| `bin/coverage.php` runs on include and calls `exit()`, so it is reached here the way the suite
| reaches it everywhere else: as a child process. What is asserted is what it says about the
| machine before the suite starts — the two facts the report never carried, and the ones a floor is
| read against. A floor is recorded under one interpreter, `composer test:unit` runs the script
| under Composer's own, and a run with nothing loaded that can count a line measures 0.0% for every
| file. Neither is a regression, and both read as one unless the run says which machine it was on.
|
| WHICH PROCESS IS ASKED
| ----------------------
| The driver that decides whether anything can measure is not this process' own. The suite is
| started as a child with the same ini and none of this invocation's flags, so the two can disagree
| in both directions, and the script asks the child — a second copy of itself in probe mode — rather
| than answering from the PHP it happens to run under. The `-n` run below is that difference made
| assertable: this process has no driver and the one it starts does, so a header built from the
| wrong one would send a reader to install PCOV on a machine that is already measuring.
|
| AND WHEN NOTHING CAN MEASURE
| ----------------------------
| A floor of 0.0% for every file is not a floor that was missed, and two minutes of tests to reach
| it is a red build pointing at the code instead of at the machine. So the run is stopped before
| the suite when the interpreter that would measure has no driver, with the reason on stderr, and
| the environment that makes it driverless is planted rather than assumed — an empty `php.ini` and
| a scan directory with no ini file in it, which is the arrangement a runner without PCOV has.
|
| The run that does start the suite is stopped with Pest's own `--help`, which this script forwards
| because everything it is given goes to Pest. That is not a test about Pest's help: it is the
| cheapest run that reaches the header and then collects nothing, and a run that collects nothing is
| exactly what a machine with a driver produces when the collector does not run. It is therefore
| also the failure this header has to survive — the floor is not reported at all rather than
| reported as a floor of nothing.
|
*/

/**
 * @guards-index guard `tests/Unit/Gate/CoverageTest.php`
 *
 * a floor run that keeps quiet about the machine it is on: the interpreter is named before the suite starts and again at the foot of a failure, so a floor that moved is attributable to the code or to the PHP — and a run that collected nothing says so rather than reporting every file at 0.0%
 */

/**
 * This package's own floor runner, in place rather than in a planted tree: it resolves the
 * repository from its own path, and the interpreter it names is the one the suite is running under.
 *
 * @param  list<string>  $arguments  handed to the script, and on from it to Pest
 * @param  list<string>  $php  flags for this interpreter, before the script — `-n` is how a run is
 *                             made driverless here while the child it starts keeps the machine's
 * @param  array<string, string>|null  $environment  the child's environment, or null to inherit
 */
function floorRun(array $arguments = [], array $php = [], ?array $environment = null): ReleaseRun
{
    $root = packageRoot();
    $command = [PHP_BINARY, ...$php, $root.'/bin/coverage.php', ...$arguments];

    // `Processes` rather than `proc_open()`: a start this machine refused under a pool is not a
    // finding about the coverage floor.
    [$process, $pipes] = Processes::start(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $root,
        $environment,
    );

    fclose($pipes[0]);
    $output = (string) stream_get_contents($pipes[1]);
    $error = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return new ReleaseRun(proc_close($process), $output, $error, implode(' ', $command));
}

/**
 * The environment of a machine with no coverage driver, as a variable set for one child.
 *
 * Both halves are needed and neither is enough: the empty `php.ini` takes away the extension a main
 * ini file loads, and the scan directory takes away the one a `conf.d` fragment loads, which is how
 * a runner installs it. Between them PHP starts with no extension at all — the machine this refusal
 * exists for, and the one arrangement a test cannot otherwise produce on a workstation that has a
 * driver.
 *
 * @return array<string, string>
 */
function driverlessEnvironment(): array
{
    $directory = sys_get_temp_dir().'/rc-driverless-'.bin2hex(random_bytes(6));

    if (! mkdir($directory, 0o777, true)) {
        throw new RuntimeException('Could not create '.$directory);
    }

    file_put_contents($directory.'/php.ini', '');

    register_shutdown_function(static function () use ($directory): void {
        @unlink($directory.'/php.ini');
        @rmdir($directory);
    });

    // The rest of the environment is passed on rather than replaced: the shell this package's
    // interpreter resolver runs is reached by `PATH`, and a child that could not be asked which PHP
    // this repository means would be a second difference planted by the test rather than the one
    // under test.
    $environment = getenv();

    $environment['PHPRC'] = $directory;
    $environment['PHP_INI_SCAN_DIR'] = $directory;

    // The four raw facts are asserted empty below, and Composer's own variable is the one of them
    // that is set by whoever started the suite rather than by the machine: `composer test:unit`
    // exports it into this process, and a child handed it would answer with a Composer the floor
    // run never saw. Dropped, so the assertion reads the driverless machine rather than the
    // invocation that asked about it.
    unset($environment['COMPOSER_BINARY']);

    return $environment;
}

it('names the PHP it is running under, and the driver, before and after a run that measured nothing', function (): void {
    $run = floorRun(['--help']);
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

it('asks the interpreter that will run the suite rather than the one this run happens to be under', function (): void {
    $running = CoverageEnvironment::detect()->driver;

    if ($running === '') {
        $this->markTestSkipped('No coverage driver is loaded, so there is none for the child to name.');
    }

    // `-n` is the cleanest way to make this process driverless while the child it starts keeps the
    // machine's ini. A header built from this process' own answer would read as a machine that
    // cannot measure — the failure that would send a reader to install the driver the suite is
    // about to measure with.
    $blind = floorRun(['--help'], ['-n']);

    expect($blind->output)
        ->toContain('Coverage floor, measured under:')
        ->toContain($running)
        ->not->toContain('to fix it');
});

it('refuses before the suite when the interpreter that would measure cannot count a line', function (): void {
    $run = floorRun(['--help'], environment: driverlessEnvironment());

    expect($run->exitCode)->toBe(1)

        // The header is still printed, and still says what would give this machine a driver: the
        // refusal is read under it, not instead of it.
        ->and($run->output)->toContain('Coverage floor, measured under:')
        ->and($run->output)->toContain('coverage driver')
        ->and($run->output)->toContain('pcov is not loaded')
        ->and($run->output)->toContain('to fix it')

        // The refusal itself, on the error stream, and it says what did not happen.
        ->and($run->error)->toContain('Nothing this run can start can measure a line')
        ->and($run->error)->toContain('the suite was not started')

        // Stopped before Pest: none of the suite's output is here, and neither is the report the
        // run after a suite that collected nothing prints. That report is what this refusal exists
        // to replace, so it must not be reachable from it.
        ->and($run->output)->not->toContain('Pest Testing Framework')
        ->and($run->output)->not->toContain('No script coverage was collected')
        ->and($run->error)->not->toContain('below its floor');
});

it('has a probe mode that answers with this interpreter and stops there', function (): void {
    $run = floorRun(['--probe']);
    $observed = json_decode(trim($run->output), true);

    expect($run->exitCode)->toBe(0)
        ->and($observed)->toBeArray()
        ->and(array_keys((array) $observed))->toBe(['extensions', 'pcov_enabled', 'xdebug_mode', 'composer'])
        ->and($observed['extensions'])->toBeArray()
        ->and($observed['pcov_enabled'])->toBeString()
        ->and($observed['xdebug_mode'])->toBeString()
        ->and($observed['composer'])->toBeString()

        // The probe is the child half of the header, so it prints the four facts and nothing else:
        // a run that also printed the header would have started the floor's machinery to answer a
        // question that is asked before any of it runs.
        ->and($run->output)->not->toContain('Coverage floor')

        // And it is the same reading the header is built from, which is what makes asking a child a
        // question with one reading rather than a second implementation of it.
        ->and($observed['extensions'])->toBe(CoverageEnvironment::observed()['extensions']);

    // The driverless environment, asked the same way: the four facts are what the parent judges, so
    // a probe that reported this process' extensions for the child's would put the driver back.
    $blind = floorRun(['--probe'], environment: driverlessEnvironment());

    expect(json_decode(trim($blind->output), true))
        ->toBe(['extensions' => [], 'pcov_enabled' => '', 'xdebug_mode' => '', 'composer' => '']);
});
