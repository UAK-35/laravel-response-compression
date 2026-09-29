<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The collector a spawned script runs under — an instrument rather than a reading
|--------------------------------------------------------------------------
|
| `bin/release.php` and `bin/checks.php` are never reached from inside the suite: a test plants a
| repository, copies the script into it and runs it as a child process. That is the right way to
| test a program that commits and tags, and it is invisible to the floor — coverage collected in
| a child process is not in the parent's report, so both scripts sat at 0.0% while sixty tests
| drove one of them end to end.
|
| This file is what `tests/Support/ReleaseRepo` hands a child as `auto_prepend_file` when
| `RC_SCRIPT_COVERAGE_DIR` is set, which is what `bin/coverage.php` sets before it runs Pest. It
| starts PCOV before the script has run a line and writes what the process covered at shutdown —
| shutdown rather than the end of the file, because the script exits, which is how it returns a
| status and how a refusal stops it.
|
| Nothing collects unless that variable is set, so a plain `pest`, the gate's own test step and a
| developer's run are all exactly what they were.
|
| WHAT IS WRITTEN
| ---------------
| `pcov\collect()` answers with `[file => [line => count]]` for the files this process ran, which
| for a copied script is the copy in the fixture rather than the file in the repository. So the
| payload carries a hash of each of those files as well, taken here, while the file is still on
| disk — the fixture is swept when the test that planted it is done, and the merge happens after
| the whole suite has finished. A name is not evidence of anything: the fixture's `bin/checks.php`
| is usually a three-line stub, and reading it as the gate would hand the gate 100.0% coverage for
| a script that exits on line three. See `ScriptCoverage`.
|
| @guards-index support
*/

$directory = getenv('RC_SCRIPT_COVERAGE_DIR');

// No directory, no pcov, no collection. A machine without a coverage driver can still run the
// suite; it is the floor that needs one, and it says so itself.
if (! is_string($directory) || $directory === '' || ! function_exists('pcov\start')) {
    return;
}

pcov\start();

register_shutdown_function(static function () use ($directory): void {
    pcov\stop();
    $data = pcov\collect();

    // A child that ran nothing this process can attribute — a script that refused its arguments
    // before a file was loaded, or one whose lines are all outside the collected scope.
    if ($data === []) {
        return;
    }

    $hashes = [];

    foreach (array_keys($data) as $path) {
        // Hashed now rather than when the merge reads this, because by then the fixture this file
        // was copied into has been swept: the evidence has to be taken while there is a file to
        // take it from. A path that is already gone is evidence of nothing, and says so.
        $hashes[$path] = is_file($path) ? hash_file('sha256', $path) : null;
    }

    // A file per process. The suite runs sixteen workers, each spawning children of its own, and
    // several of those children are alive at the same moment: one shared file would be a torn
    // write rather than a merge, and a lock would serialise the suite behind its own coverage.
    @file_put_contents(
        sprintf('%s/%s-%s.cov', rtrim($directory, '/\\'), getmypid(), bin2hex(random_bytes(4))),
        serialize(['hashes' => $hashes, 'lines' => $data]),
    );
});
