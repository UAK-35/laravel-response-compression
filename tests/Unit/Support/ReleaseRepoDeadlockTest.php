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
| WHAT IS TESTED NOW: stderr is a temporary file beside the fixture, which has no buffer to fill,
| and stdout is the pipe it always was, drained into a second file by a reader child so that this
| process never blocks on it. A child may therefore write either stream first and write any volume.
| This case is the one that starved the pipe — a megabyte on stderr before a byte on stdout — so it
| holds the shape as a regression rather than describing a bug that is still there, and a second
| case holds the volume on both sides at once — a megabyte each, written in turn — because a case
| that fills one stream only is the half the finding asked for. (stdout could
| not become a file: `bin/bootstrap.php` hands `STDOUT` to composer and the verifier, and a run's
| last write landed at the capture file's first byte — measured, `bin/bootstrap.php: done.` in
| front of its children's lines — so a second writer does not append whatever mode it is opened
| in.)
|
| AND HOW LONG A RUN MAY TAKE: moving the streams to files removed the starvation and left the
| wait with no clock in it — `stream_get_contents()` to EOF, then `proc_close()`, either of which
| a child that never exits holds forever. That read cannot simply be made non-blocking either
| (measured: `stream_set_blocking()` answers `false` for a `proc_open` pipe and the first `fread()`
| still waited for its byte), so `script()` moves the read into a reader child and polls
| `proc_get_status()` here against a deadline — 300 seconds, or whatever `within()` asks for —
| stopping the child *and its descendants* when a run reaches it. The second case holds both
| halves: the call comes back, and the grandchild the fixture started — it announces itself, so the
| absence of its later marker is a tree that was stopped rather than a tree that never appeared —
| never writes. `taskkill /T` enumerates the tree before it terminates it, so a descendant spawned
| while that is happening is not in the list and outlives the parent; the stop therefore sweeps what
| is still parented to the dead child, and this case waits from the grandchild's own start rather
| than from the moment the run returned, so a late spawn cannot read as an absent one.
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

/**
 * Both streams at their limit rather than one: a megabyte on stdout and a megabyte on stderr, a
 * kilobyte at a time in turn, which is the "large output on each stream" the finding asked for and
 * the shape the first case could not reach. Every turn is one write each, so whichever stream the
 * child fills first, the other is already past its pipe buffer when it comes back to it — the old
 * read made that a deadlock in both directions rather than one.
 */
it('captures a megabyte on each stream at once, written in turn', function (): void {
    $repo = ReleaseRepo::plain();

    $testScript = <<<'PHP'
<?php
// A kilobyte each way, a thousand and twenty-four times: at the end of every turn both streams are
// over a pipe buffer's worth of unread bytes.
$out = str_repeat('O', 1024);
$err = str_repeat('E', 1024);

for ($i = 0; $i < 1024; $i++) {
    fwrite(STDOUT, $out);
    fwrite(STDERR, $err);
}

fwrite(STDOUT, "Done\n");
exit(0);
PHP;

    $repo->write('test-both-large.php', $testScript);

    $began = microtime(true);
    $run = $repo->script('test-both-large.php');
    $elapsed = microtime(true) - $began;

    // Whole on both sides rather than each one reaching some threshold: a capture that lost either
    // tail is a read that stopped early, and a read that stops early is what this case is for. The
    // clock is asserted too, generously, because the failure this replaced was a wait with no end
    // in it rather than a slow answer.
    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->timedOut)->toBe(false)
        ->and(strlen($run->output))->toBe(1024 * 1024 + strlen("Done\n"))
        ->and(strlen($run->error))->toBe(1024 * 1024)
        ->and(str_ends_with($run->output, "Done\n"))->toBeTrue()
        ->and($elapsed)->toBeLessThan(60.0);
});

/**
 * A bound nobody can outlast here: the fixture sleeps for thirty seconds, the deadline is three,
 * and the grandchild would write at six — so a run that returned is a run that was stopped, and a
 * grandchild marker that is absent is a tree that was stopped with it.
 *
 * The three seconds are headroom for the fixture's own start rather than part of what is asserted:
 * the deadline counts from `proc_open()` of the child, while the grandchild exists only once that
 * child's PHP has booted and started it, which under the gate's eight workers was measured taking
 * more than the one second this case first asked for — the tree the stop was meant to catch had not
 * been started yet, and the run was stopped before there was anything to stop. So the deadline is
 * generous, the grandchild announces itself the moment it exists, and that announcement is asserted
 * beside the first marker: the later marker's absence then means a tree was stopped, not that a
 * tree had yet to appear.
 */
it('stops a script that never ends, and the child it started with it', function (): void {
    $repo = ReleaseRepo::plain();
    $mark = $repo->path.'.mark';

    $script = <<<'PHP_WRAP'
    <?php
    $mark = $argv[1];
    file_put_contents($mark.'.started', 'yes');
    
    // A grandchild that outlives the fixture unless the whole tree is stopped: it says it exists at
    // once, then writes six seconds in — three seconds past the deadline this test hands the run —
    // and its stdout goes to the null device because nothing here reads it.
    proc_open(
        [PHP_BINARY, '-r', 'file_put_contents($argv[1].".grandchild-started", "yes"); usleep(6000000); file_put_contents($argv[1].".grandchild", "yes");', $mark],
        [
            0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
            1 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'],
            2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'],
        ],
        $pipes,
    );
    
    usleep(30000000);
    file_put_contents($mark.'.finished', 'yes');
    PHP_WRAP;

    $repo->write('test-never-ends.php', $script);

    $began = microtime(true);
    $run = $repo->within(3.0)->script('test-never-ends.php', $mark);
    $elapsed = microtime(true) - $began;

    // The status the run reports is the bound's rather than the script's, and the message says so:
    // a reader who saw `124` beside an empty stream would have no way to tell a stop from a failure.
    expect($run->timedOut)->toBe(true, $run->describe())
        ->and($run->exitCode)->toBe(124)
        ->and($elapsed)->toBeLessThan(20.0)
        ->and($run->describe())->toContain('stopped after 3.0s')

        // It got as far as its first marker and its grandchild's, and never reached its last one.
        ->and(file_exists($mark.'.started'))->toBeTrue()
        ->and(file_exists($mark.'.grandchild-started'))->toBeTrue()
        ->and(file_exists($mark.'.finished'))->toBeFalse();

    // Past the grandchild's own six seconds, counted from when it says it started rather than from
    // when this run came back: under the gate's workers the child can take seconds to boot, so the
    // grandchild may be spawned most of the deadline late, and a wait measured from here would
    // check while it was still asleep — an orphan this case is about would read as absent. A stop
    // that killed only the direct child leaves the later marker written, and the next test to copy
    // the tree inherits a process still working in it: the cleanup half of the bound, which is the
    // half a kill of the child alone misses.
    $since = (float) filemtime($mark.'.grandchild-started') + 7.0 - microtime(true);

    if ($since > 0) {
        usleep((int) ($since * 1_000_000));
    }

    expect(file_exists($mark.'.grandchild'))->toBeFalse();

    foreach (['.started', '.finished', '.grandchild-started', '.grandchild'] as $suffix) {
        ReleaseRepo::remove($mark.$suffix);
    }
});
