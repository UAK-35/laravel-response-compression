#!/usr/bin/env php
<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\CoverageEnvironment;
use Uak35\ResponseCompression\Tests\Support\ScriptCoverage;

/*
|--------------------------------------------------------------------------
| The coverage floor
|--------------------------------------------------------------------------
|
| `composer test:unit` runs this rather than Pest directly, because the number the floor needs is
| not the number one Pest run can produce. `src/` is measured the way it always was — a test, in
| this process, executing the class it covers — and `bin/` cannot be measured that way at all: both
| scripts run on include, both call `exit()`, and the release one commits and tags. The suite
| reaches them by planting a repository, copying the script into it and running it as a child, and
| the coverage a child collects is its own: `pest --coverage --min=100` reported 100.0% of `src/`
| and 0.0% of both scripts while sixty tests drove one of them end to end.
|
| So this runs Pest with the child collector switched on (`RC_SCRIPT_COVERAGE_DIR`, which
| `tests/Support/ReleaseRepo` turns into an `auto_prepend_file` for every script it spawns), reads
| the coverage each child wrote, maps each fixture copy back onto the file it was copied from —
| contents compared, never the name — and adds those lines to the report before it looks at the
| floor. See `tests/Support/ScriptCoverage.php`.
|
| WHAT THE FLOOR IS
| -----------------
| Every file in `src/` at 100.0% of its executable lines, each file judged on its own rather than
| as one average, because an average is what lets one class drop while another rises and still
| reads as 100%. Every file in `bin/` at the floor recorded below, which is a ratchet: it is the
| last measured value rounded down, it may be raised here, and it may not be lowered.
|
| The scripts are held to a ratchet rather than to 100% because they are driven from the outside —
| a list of scenarios, each one a planted repository — and closing the rest of those lines is
| writing more scenarios, not removing dead code. A floor that went straight to 100% would be a red
| build on every machine until that list is finished, which is a worse promise than one that only
| has to not go backwards. What this run measures is printed beside each floor, so a rise is
| visible, and raising the floor is a one-line edit.
|
| WHY THE ENVIRONMENT IS NAMED BEFORE ANYTHING RUNS
| -------------------------------------------------
| A floor has two ways to come back red on a machine that has no regression in it, and neither is
| visible in the number: the PHP, because the recorded floors were taken under one interpreter and
| `composer test:unit` runs this under Composer's own; and the driver, because with nothing loaded
| that can count a line every file measures 0.0% and every floor reports the whole package below
| itself. So the run opens by naming both — the interpreter, its path, whether Composer started it,
| and the driver — and the two failures that are about a floor repeat that line at the foot, because
| a red build is read at the bottom of two minutes of test output rather than at the top.
|
| The naming is where it stops. A machine with no driver is named and the run goes on rather than
| being refused, because the process that measures is not this one: the suite is started as a child
| with the same ini and none of this invocation's flags, so a run begun with `-n` has no driver here
| while the run it starts has one. A refusal would be a guess about a process this has not started.
|
| WHY THIS FILE IS NOT IN THE REPORT
| ----------------------------------
| It cannot measure itself: it is the parent of the run whose coverage it reads, so its own lines
| are in no file any child writes. `phpunit.xml.dist` leaves it out of the source for that reason
| and no other, and `docs/guards.md` records it.
*/

$root = str_replace('\\', '/', dirname(__DIR__));

/**
 * The floor for each script in `bin/`, as a whole percentage of its executable lines.
 *
 * Written as the measured value less a point or two, and only ever raised. The margin is what the
 * environment costs: `bin/checks.php` decides whether to run `composer validate` by looking for a
 * Composer it can reach, and a run that can reach one covers the check while a run that cannot
 * covers the skip instead — the same tests, ten lines apart, depending on whether the suite was
 * started through `composer test:unit` or the runner directly. A floor set to the measurement
 * would redden the other configuration for a branch that is not a regression, and a regression is
 * worth more than a point or two.
 *
 * `bin/checks.php` measured 55.4% through Composer and 57.8% without it when this was written,
 * `bin/release.php` 79.7% either way, `bin/tool.php` 87.5% either way, and `bin/index.php` 83.3%,
 * from `tests/Unit/Gate/ChecksTest.php`, `tests/Unit/Gate/ToolTest.php`, `tests/Unit/Docs/` and the
 * release rails together. What `bin/tool.php` does not reach is what nothing can: the two header
 * lines every measured file carries — the shebang and `<?php` — and the body of a `proc_open`
 * failure, which needs the operating system to refuse a process that exists. `bin/index.php` is
 * driven end to end in a planted tree, so its remainder is the same two header lines, the branch
 * that reports a write the operating system refused, and the reading it takes after writing — which
 * fires only when the program itself is wrong, a defect rather than a scenario. The release
 * script's remaining fifth is what writing the release scenarios nobody has written yet would buy.
 *
 * `bin/tool-paths.php` has no floor and needs none: it is a list rather than a script, and a line
 * count of a `return [...]` literal is a fact about how two tools count a statement rather than
 * about the file. It is left out of the report in `phpunit.xml.dist` for that reason, and
 * `tests/Unit/Support/ToolPathsTest.php` is what reads it.
 *
 * @var array<string, int>
 */
const FLOORS = [
    'bin/checks.php' => 54,
    'bin/index.php' => 81,
    'bin/release.php' => 78,
    'bin/tool.php' => 86,
];

/**
 * The floor for every file in `src/`, which is not a number anyone has to maintain.
 */
const SOURCE_FLOOR = 100.0;

$autoload = $root.'/vendor/autoload.php';

if (! is_file($autoload)) {
    fwrite(STDERR, 'composer install first: this runs the suite, and the suite needs its tools.'.PHP_EOL);
    exit(2);
}

require $autoload;

// Which Pest to run comes from the manifest the gate reads: a tool's path is written down in one
// place, and both halves of this package's test story have to be running the same one. A manifest
// that is not there is a damaged checkout rather than a missing install, and the fatal names it.
$tools = require $root.'/bin/tool-paths.php';
$pest = $root.'/'.ltrim((string) ($tools['pest'] ?? ''), '/');

// Before the suite, not after it fails: both answers are about the machine this happens to be
// running on, and neither is in the number a floor reads. The note at the top of this file says why
// they are named here rather than acted on.
$environment = CoverageEnvironment::detect();

fwrite(STDOUT, PHP_EOL.$environment->describe().PHP_EOL);

$collected = sys_get_temp_dir().'/rc-coverage-'.bin2hex(random_bytes(6));
$clover = $collected.'-clover.xml';

if (! mkdir($collected, 0o777, true)) {
    fwrite(STDERR, 'Could not create '.$collected.PHP_EOL);
    exit(2);
}

// Exported rather than passed, because it has to reach the scripts the suite spawns and those are
// grandchildren of this process: `pest` starts the workers, and a test starts the script.
putenv('RC_SCRIPT_COVERAGE_DIR='.$collected);

// The parent is asked for `src/` and only `src/`. It never executes anything in `bin/`, so
// collecting there would buy memory and nothing else — those files' denominators come from
// PHPUnit's own static analysis of the source list, and their lines from the children.
$status = run([
    PHP_BINARY,
    '-d', 'pcov.directory='.$root.'/src',
    $pest,
    '--colors=always',
    '--parallel',
    '--coverage-clover='.$clover,
    ...array_slice($argv, 1),
]);

$read = ScriptCoverage::read($collected, $root);

// A report of zero processes and a report of everything are the same empty array, and the first is
// how this quietly stops measuring `bin/` at all — no driver in the child, a variable that did not
// survive the trip through the runner. A floor of nothing would look exactly the same, so it is a
// failure instead.
if ($read['processes'] === 0) {
    fwrite(STDERR, PHP_EOL.'No script coverage was collected, so the floor cannot be reported on.'.PHP_EOL);
    fwrite(STDERR, 'It needs a driver (PCOV or Xdebug) and the child collector to have run.'.PHP_EOL);
    fwrite(STDERR, $environment->summary().PHP_EOL);
    cleanup($collected, $clover);
    exit(1);
}

// Checked here rather than inside `measure()`: both of the ways this run can end without a report
// have to take the temporary directory with them, and it belongs to this block rather than to a
// function that was handed the file to read.
if (! is_file($clover)) {
    fwrite(STDERR, 'The run wrote no clover report, so there is nothing to measure.'.PHP_EOL);
    fwrite(STDERR, $environment->summary().PHP_EOL);
    cleanup($collected, $clover);
    exit(1);
}

$measured = measure($clover, $read['lines']);
$failures = [];

foreach ($measured as $file => $counts) {
    $floor = floorFor($file);

    if ($counts['percent'] < $floor) {
        $failures[$file] = sprintf(
            '%s is at %.1f%% (%d of %d lines), below its floor of %.0f%%',
            $file,
            $counts['percent'],
            $counts['covered'],
            $counts['statements'],
            $floor,
        );
    }
}

report($measured, $read, $failures);

cleanup($collected, $clover);

if ($failures !== []) {
    fwrite(STDERR, 'The floor is not met:'.PHP_EOL.PHP_EOL);

    foreach ($failures as $failure) {
        fwrite(STDERR, '  - '.$failure.PHP_EOL);
    }

    // The interpreter and the driver again, at the foot of the failure: a floor that moved is
    // either the code that changed or the PHP that ran it, and this is where it gets read.
    fwrite(STDERR, PHP_EOL.$environment->summary().PHP_EOL);

    exit(1);
}

// The suite's own status last, and it is what is returned: a run whose tests failed is not a run
// whose coverage means anything, because the number would be the coverage of the tests that got
// as far as running.
exit($status);

/**
 * The floor for one file: 100% for anything the package ships, and the recorded ratchet for the
 * scripts in `bin/`.
 */
function floorFor(string $file): float
{
    return str_starts_with($file, 'src/') ? SOURCE_FLOOR : (float) (FLOORS[$file] ?? 0.0);
}

/**
 * Every file the report knows about, with what it covered and what that is worth.
 *
 * The scripts in `bin/` gain the lines their spawned children covered on top of what the parent
 * ran, which for them is nothing at all.
 *
 * @param  array<string, array<int, int>>  $collected
 * @return array<string, array{statements: int, covered: int, percent: float}>
 */
function measure(string $clover, array $collected): array
{
    // The report format rather than the library that wrote it: clover is a document this reads once
    // and a shape that does not move when the pinned tools are unpinned, which is what the monthly
    // run does to them.
    $report = @simplexml_load_file($clover);

    if ($report === false) {
        fwrite(STDERR, 'The clover report could not be read.'.PHP_EOL);
        exit(1);
    }

    $measured = [];

    // Every `<file>` in the document, not the ones directly under `<project>`: the format groups
    // the files that belong to a namespace into a `<package>` element, so a walk of one level
    // would quietly measure `bin/` — the two scripts are in no namespace — and nothing in `src/`,
    // which is the half of the floor this is here for. Nothing asserts which files it read, so a
    // walk that missed them would leave `src/` unmeasured and still report every floor met.
    foreach ($report->xpath('//file[@name]') ?: [] as $file) {
        // Two spellings of the same file, because they are used for two things: the report names
        // it absolutely, and the collected lines are keyed the same way, while the floor is
        // reported per file and has to read as the repository names it.
        $absolute = str_replace('\\', '/', (string) $file->attributes()->name);
        $name = relative($absolute);
        $statements = 0;
        $covered = 0;

        foreach ($file->line as $line) {
            // Statement lines only: those are the lines PHPUnit counts, and the denominator is
            // whatever PHPUnit's own analysis of the file says it has. A method signature is a
            // line the report carries and not a line this floor is about.
            if ((string) $line->attributes()->type !== 'stmt') {
                continue;
            }

            $statements++;

            // What the parent ran, or what any child that was executing this file ran. The two are
            // the same line of the same file, which is the whole point of mapping the copies back
            // before adding them: without that, this reads 0.0% and says nothing is wrong.
            if ((int) $line->attributes()->count > 0 || isset($collected[$absolute][(int) $line->attributes()->num])) {
                $covered++;
            }
        }

        if ($statements === 0) {
            continue;
        }

        $measured[$name] = [
            'statements' => $statements,
            'covered' => $covered,
            'percent' => round($covered / $statements * 100, 1),
        ];
    }

    ksort($measured);

    return $measured;
}

/**
 * A path as the report writes it, named the way the repository names it.
 */
function relative(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $prefix = str_replace('\\', '/', dirname(__DIR__)).'/';

    return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
}

/**
 * The two things a reader needs: what each file came to, and whether it cleared its floor.
 *
 * @param  array<string, array{statements: int, covered: int, percent: float}>  $measured
 * @param  array{lines: array<string, array<int, int>>, processes: int, ignored: list<string>}  $read
 * @param  array<string, string>  $failures
 */
function report(array $measured, array $read, array $failures): void
{
    $lines = 0;

    foreach ($read['lines'] as $hits) {
        $lines += count($hits);
    }

    fwrite(STDOUT, PHP_EOL.'Coverage, with the scripts counted from the processes that ran them:'.PHP_EOL.PHP_EOL);
    fwrite(STDOUT, sprintf(
        '  %d process(es) reported %d covered line(s) across %d file(s)',
        $read['processes'],
        $lines,
        count($read['lines']),
    ).PHP_EOL);

    // A copy that is not this repository's file is counted rather than dropped quietly: it is how
    // a stubbed gate reads, and it is also how a fixture that plants a modified script shows up.
    // Counted by name rather than listed, because the release rails plant a stub of the same gate
    // in every fixture they build, and thirty identical lines is not a report anyone reads.
    $setAside = [];

    foreach ($read['ignored'] as $ignored) {
        $name = basename($ignored);
        $setAside[$name] = ($setAside[$name] ?? 0) + 1;
    }

    foreach ($setAside as $name => $count) {
        fwrite(STDOUT, sprintf(
            '  set aside: %d copy/copies of %s, which are not this repository\'s file'.PHP_EOL,
            $count,
            $name,
        ));
    }

    fwrite(STDOUT, PHP_EOL);

    foreach ($measured as $file => $counts) {
        fwrite(STDOUT, sprintf(
            '  %-26s %6.1f%%  %4d/%-4d lines  floor %.0f%%%s'.PHP_EOL,
            $file,
            $counts['percent'],
            $counts['covered'],
            $counts['statements'],
            floorFor($file),
            isset($failures[$file]) ? '  <-- below' : '',
        ));
    }

    fwrite(STDOUT, PHP_EOL);
}

/**
 * Leave nothing behind: the collected files, the report and the directory they were written to.
 */
function cleanup(string $collected, string $clover): void
{
    foreach (glob($collected.'/*.cov') ?: [] as $file) {
        @unlink($file);
    }

    @unlink($clover);
    @rmdir($collected);
}

/**
 * Run a command with its output going where this process' output goes, and answer with its status.
 *
 * @param  list<string>  $command
 */
function run(array $command): int
{
    $process = proc_open(
        $command,
        [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR],
        $pipes,
    );

    if (! is_resource($process)) {
        fwrite(STDERR, 'Could not run '.implode(' ', $command).PHP_EOL);
        exit(2);
    }

    return proc_close($process);
}
