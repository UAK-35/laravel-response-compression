<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| A child's output is captured whatever order and volume it writes in
|--------------------------------------------------------------------------
|
| WHAT WENT WRONG: `ReleaseRepo::script()` gave the child stdout and stderr pipes and read stdout
| to EOF before it read stderr. A child that fills the stderr pipe buffer — a few KB — before it
| writes a byte of stdout blocks in `fwrite()`, this process blocks in `fread()` waiting for that
| first stdout byte, and neither side can move. The fix that shipped beside this test was
| `stream_set_blocking()` on both pipes, which does nothing on Windows: a `proc_open` pipe is a
| `STDIO` stream there and the wrapper refuses the option, so it answers `false`, leaves the
| stream blocked, and the drain loop is a blocking read that never reaches its iteration cap.
| Measured on this machine: the call returns `false`, `stream_get_meta_data()['blocked']` is still
| `true`, and the first `fread()` on stdout was where a run of this case stopped, permanently.
|
| WHAT IS TESTED NOW: the two streams are temporary files beside the fixture, which have no buffer
| to fill, so a child may write either one first and write any volume. This case is the one that
| starved the pipe — a megabyte on stderr before a byte on stdout — so it holds the shape as a
| regression rather than describing a bug that is still there.
|
| @guards-index test
*/

/**
 * The volume a pipe buffer cannot hold, in the order that used to leave this process waiting for a
 * byte the child could not get to: the first assertion is that the call returns at all.
 */
it('captures a megabyte of stderr written before stdout, which no pipe buffer could hold', function (): void {
    $repo = ReleaseRepo::plain();

    $testScript = <<<'PHP'
<?php
// The volume a pipe buffer cannot hold, written before stdout is touched at all
$chunk = str_repeat('E', 1024); // 1KB chunk
for ($i = 0; $i < 1024; $i++) {
    fwrite(STDERR, $chunk);
}

// Then write to stdout
fwrite(STDOUT, "Done\n");
exit(0);
PHP;

    $repo->write('test-stderr-large.php', $testScript);

    $run = $repo->script('test-stderr-large.php');

    // Both streams whole: the capture is a file, so nothing here depends on which stream the child
    // filled first, and a run that returned is the half a deadlocked read could never reach. The
    // expectations carry no sentence of their own because `toContain()` is variadic — it asserts
    // every argument it is handed — so a message there is read as a second needle, and the run's
    // own streams are in the failure output either way.
    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->output)->toContain('Done')
        ->and($run->error)->not->toBeEmpty()
        ->and(strlen($run->error))->toBeGreaterThanOrEqual(1024 * 1024);
});
