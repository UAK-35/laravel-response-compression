#!/usr/bin/env php
<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\CoverageEnvironment;
use Uak35\ResponseCompression\Tests\Support\PhpBinary;
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
| The interpreter is named twice over, because there are two of them to tell apart: the one this
| run happens to be under, and the one this repository means. Which PHP this checkout uses is decided
| in one place, `.agents/php-bin.sh`, and `PhpBinary` asks it; when the two differ the header says
| so, since a floor recorded under one install and measured under another is the first explanation
| to rule out and the last one a reader would think of. A rung that is a fallback - the `php` on a
| runner's PATH - is not printed: that is the arrangement on a runner rather than a second opinion.
|
| WHAT IS ASKED, AND WHEN THE RUN STOPS
| -------------------------------------
| The process that measures is not this one. The suite is started as a child with the same ini and
| none of this invocation's flags, so the two can disagree about the driver in both directions: a
| run begun with `-n` has no driver here while the run it starts has one, and a run begun with
| `-d pcov.enabled=1` has one here while the child that would count the lines does not. So the
| question is put to the child: this program is started again under that interpreter in its probe
| mode, the four raw facts it reports are handed to the same `CoverageEnvironment`, and what the
| header names is the environment that will measure rather than the one it was typed in.
|
| When that answer is that nothing can count a line, the suite is not started. A floor of 0.0% for
| every file is not a floor that was missed, and running two minutes of tests to reach it is a red
| build that points at the code instead of at the machine. The refusal is made from the child's own
| answer, which is the difference between stopping here and guessing about a process never started.
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
 * `bin/release.php` 79.7% either way, `bin/tool.php` 91.1% either way, `bin/index.php` 83.3%, and
 * `bin/push.php` 81.3%,
 * from `tests/Unit/Gate/ChecksTest.php`, `tests/Unit/Gate/ToolTest.php`, `tests/Unit/Docs/` and the
 * release rails together. What `bin/tool.php` does not reach is what nothing can: the two header
 * lines every measured file carries — the shebang and `<?php` — and the body of a `proc_open`
 * failure, which needs the operating system to refuse a process that exists. `bin/index.php` is
 * driven end to end in a planted tree, so its remainder is the same two header lines, the branch
 * that reports a write the operating system refused, and the reading it takes after writing — which
 * fires only when the program itself is wrong, a defect rather than a scenario. The release
 * script's remaining fifth is what writing the release scenarios nobody has written yet would buy.
 *
 * `bin/push.php` measured 81.3% when it was added to this list, from `tests/Unit/Release/PushTest.php`.
 * It is held at that measurement rather than at a gap, because 26 of the 29 lines it does not reach
 * carry no runtime opcode at all - the entries of a constant array, which the compiler folds into
 * one, and the literal arguments and closing brackets of a call written across several lines are
 * statements PHPUnit counts per line while PCOV reports the statement once, which is the same reason
 * `bin/tool-paths.php` has no floor. The other three are the two header lines every measured file
 * carries and the body of a `proc_open` failure. So its remainder is a ceiling under this counting
 * rather than work a scenario would close.
 *
 * `bin/bootstrap.php` measured 77.3% when it was added to this list, from
 * `tests/Unit/Bootstrap/BootstrapTest.php`, which drives it in an empty checkout: every step, a
 * refusal before any step has run, a step that fails, `--dry-run`, `--force`, `--skip-install`,
 * `--skip-verify` and `--help`. It is held at 75% rather than at the measurement because half of
 * what a fixture cannot arrange is the environment: a machine with none of Python, Composer or
 * PowerShell 7 on `PATH` (the named-and-missing branch is walked, the nothing-found one is not),
 * the rung of Composer that is a program rather than a phar, and the directory PowerShell 7
 * installs itself into. The rest of the remainder is the usage text, which is a heredoc: PHPUnit
 * counts each of its lines as a statement while PCOV reports the statement once, which is the same
 * counting difference that leaves `bin/tool-paths.php` without a floor.
 *
 * `bin/tool-paths.php` has no floor and needs none: it is a list of records rather than a script,
 * and a line count of a `return [...]` literal is a fact about how two tools count a statement
 * rather than about the file. It is left out of the report in `phpunit.xml.dist` for that reason,
 * and `tests/Unit/Support/ToolPathsTest.php` is what reads it — the paths, and the version each
 * tool is pinned at, which that reading holds against what `composer.json` requires and against
 * what is installed here.
 *
 * @var array<string, int>
 */
const FLOORS = [
    'bin/bootstrap.php' => 75,
    'bin/checks.php' => 54,
    'bin/index.php' => 81,
    'bin/push.php' => 81,
    'bin/release.php' => 78,
    'bin/tool.php' => 89,
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

// The probe: this same program, asked what *this* process can measure, printing the four raw
// facts so the parent can judge them with the same reading. It is the child half of `observe()`
// below, and it exits before any of the floor's machinery because it is asked by a run that is
// about to start it.
if (($argv[1] ?? null) === '--probe') {
    fwrite(STDOUT, json_encode(CoverageEnvironment::observed(), JSON_THROW_ON_ERROR).PHP_EOL);

    exit(0);
}

// Which Pest to run comes from the manifest the gate reads: a tool's path is written down in one
// place, and both halves of this package's test story have to be running the same one. An entry is
// a record rather than a path — the tool's path, the version it is pinned at, and what it is for —
// and this half needs the first of the three. A manifest that is not there is a damaged checkout
// rather than a missing install, and the fatal names it.
$tools = require $root.'/bin/tool-paths.php';
$pest = $root.'/'.ltrim((string) ($tools['pest']['path'] ?? ''), '/');

// Before the suite, not after it fails: every answer here is about the machine that will measure,
// and none of them is in the number a floor reads. The interpreter that will measure is asked
// rather than this one - see `observe()` - and the repository's own answer is asked rather than
// assumed: `$root` is this checkout, and a fixture copy of this script sits in a tree with no
// resolver in it, which `PhpBinary` reports as unknown rather than as a disagreement.
$observations = observe($root);

$environment = CoverageEnvironment::detect(
    $observations['extensions'],
    $observations['pcov_enabled'],
    $observations['xdebug_mode'],
    $observations['composer'],
    PhpBinary::named($root),
);

fwrite(STDOUT, PHP_EOL.$environment->describe().PHP_EOL);

// A floor is a ratio, and nothing here can form one. Stopping before the suite is the difference
// between a build that says the machine cannot measure and one that runs the whole suite to report
// every file at 0.0% - which reads as the package having regressed rather than as a machine with
// nothing installed to count a line.
if ($environment->driver === '') {
    fwrite(STDERR, PHP_EOL.'Nothing this run can start can measure a line, so the suite was not started.'.PHP_EOL);
    fwrite(STDERR, 'Every floor below is covered lines over executable ones; with no driver in the process that runs the suite, every one of them reads 0.0%.'.PHP_EOL);
    fwrite(STDERR, $environment->summary().PHP_EOL);
    exit(1);
}

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

    // The interpreter, who Composer is running it under, and the driver again, at the foot of the
    // failure: a floor that moved is either the code that changed or the PHP that ran it, and this
    // is where it gets read.
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

/**
 * The observations of the process that will measure, asked of that process rather than this one.
 *
 * The suite is started as a child with the same ini and none of this invocation's flags, so this
 * process' own answer is about the wrong one - see the note at the top of this file. The child is
 * asked by running this program under it in its probe mode, which is the smallest form of the
 * interpreter that will run the suite, and the four facts come back as data for the same
 * `CoverageEnvironment` the header is built from.
 *
 * @return array{extensions: array<string, string>, pcov_enabled: string, xdebug_mode: string, composer: string}
 */
function observe(string $root): array
{
    $observations = probe([PHP_BINARY, '-d', 'pcov.directory='.$root.'/src', __FILE__, '--probe']);

    if ($observations === null) {
        // The child could not be asked, which is not the same as an answer that says it cannot
        // measure. What this process can see is read instead, and the difference is said out loud
        // rather than left for the header to imply.
        fwrite(STDERR, 'Could not ask the interpreter that would run the suite whether it can measure; reading this one instead.'.PHP_EOL);

        return CoverageEnvironment::observed();
    }

    return $observations;
}

/**
 * Run the probe child and read its four observations back, or null when it could not be asked.
 *
 * @param  list<string>  $command
 * @return array{extensions: array<string, string>, pcov_enabled: string, xdebug_mode: string, composer: string}|null
 */
function probe(array $command): ?array
{
    $process = proc_open(
        $command,
        [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    if (! is_resource($process)) {
        return null;
    }

    $output = (string) stream_get_contents($pipes[1]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        return null;
    }

    $decoded = json_decode(trim($output), true);

    if (! is_array($decoded)) {
        return null;
    }

    $raw = $decoded['extensions'] ?? null;
    $pcovEnabled = $decoded['pcov_enabled'] ?? null;
    $xdebugMode = $decoded['xdebug_mode'] ?? null;
    $composer = $decoded['composer'] ?? null;

    if (! is_array($raw) || ! is_string($pcovEnabled) || ! is_string($xdebugMode) || ! is_string($composer)) {
        return null;
    }

    $extensions = [];

    foreach ($raw as $name => $version) {
        if (is_string($name) && is_string($version)) {
            $extensions[$name] = $version;
        }
    }

    return [
        'extensions' => $extensions,
        'pcov_enabled' => $pcovEnabled,
        'xdebug_mode' => $xdebugMode,
        'composer' => $composer,
    ];
}
