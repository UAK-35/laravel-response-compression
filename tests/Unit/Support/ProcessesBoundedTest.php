<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Processes;
use Uak35\ResponseCompression\Tests\Support\ReleaseRun;

/* @guards-index test */

/*
|--------------------------------------------------------------------------
| A start that succeeds, and then never lets the parent go
|--------------------------------------------------------------------------
|
| WHAT WENT WRONG: every fixture in `tests/Support` answered a child by reading its pipe with
| `stream_get_contents()` and then calling `proc_close()`. Neither call has a clock in it, and both
| can block for reasons about the machine rather than about the program under test. The read returns
| at EOF, and EOF is every writer closing the pipe — a grandchild that inherited the handle keeps it
| open after the child it belongs to has exited. The close waits for an exit, and a `git` waiting on
| `index.lock` or a script waiting on a console never performs one. `proc_open()` had already
| succeeded in both cases, so the refusal retry answered nothing, and `phpunit.xml` sets no per-test
| timeout: the worker stopped reporting progress and stayed that way until the job's own timeout.
|
| WHAT IS TESTED NOW: `Processes::bounded()` reads through a reader child while this process polls
| `proc_get_status()` against a deadline, and stops the tree past it. Both directions are held here,
| because they are different failures with different answers. A child that never exits is a timeout:
| the run comes back as `TIMED_OUT` and says which bound stopped it. A child that exits while a
| grandchild still holds its pipe is *not* a timeout — its own exit code is the answer, the reader is
| given a bounded grace, and a partial capture is read as the output. The second case asserts the
| grandchild's later write is absent, which is what makes it a capture cut by the bound rather than
| one completed at EOF.
|
| `stream_set_blocking()` is not the repair, and the first case says why it cannot be: measured on
| this machine, it answers `false` for a `proc_open` pipe, leaves `stream_get_meta_data()` saying
| `blocked`, and a first `fread()` on an empty one waited 2.1 seconds for its byte.
*/

/**
 * A fixture program in a temporary file, named on its own so the two cases below read as what they
 * are rather than as escaping.
 */
function boundedFixture(string $body): string
{
    $path = sys_get_temp_dir().'/rc-bounded-fixture-'.bin2hex(random_bytes(6)).'.php';

    file_put_contents($path, "<?php\n".$body);

    return $path;
}

/**
 * The run, or the reason there was none — a refusal is a fact this file has to report rather than
 * assert through, since every expectation below is about what a started child did.
 */
function boundedStart(array $command, float $deadline): ReleaseRun
{
    $run = Processes::bounded($command, null, null, $deadline);

    if (! $run instanceof ReleaseRun) {
        throw new RuntimeException('the bounded runner refused to start the fixture: '.Processes::reason());
    }

    return $run;
}

it('stops a child that starts and never exits, and reports the bound rather than waiting', function (): void {
    $script = boundedFixture(<<<'PHP_BODY'
    // No output of its own, no exit: the shape a `git` holding `index.lock` takes, and the one
    // `proc_close()` waits on with no clock at all.
    usleep(30000000);
    PHP_BODY);

    $began = microtime(true);
    $run = boundedStart([PHP_BINARY, $script], 2.0);
    $elapsed = microtime(true) - $began;

    @unlink($script);

    expect($run->timedOut)->toBe(true, $run->describe())
        ->and($run->exitCode)->toBe(Processes::TIMED_OUT)

        // Generous, because the point is that the call comes back at all: a wait with no clock in it
        // never reaches this line, and the failure it produces is the job's timeout, not an assertion.
        ->and($elapsed)->toBeLessThan(25.0)
        ->and($run->describe())->toContain('stopped after 2.0s');
});

it('answers a child that exits while a grandchild holds its pipe, instead of reading to an EOF that never comes', function (): void {
    $mark = sys_get_temp_dir().'/rc-bounded-mark-'.bin2hex(random_bytes(6));
    $script = boundedFixture(<<<'PHP_BODY'
    // A grandchild that inherits this child's stdout — the pipe the parent is draining — and holds it
    // open for six seconds. The child itself exits at once, so the child's end is not the question
    // here: the EOF the old read waited for is, and it cannot arrive while this process lives.
    proc_open(
        [PHP_BINARY, '-r', 'usleep(6000000); fwrite(STDOUT, "late\n"); file_put_contents($argv[1], "late");', $argv[1]],
        [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w']],
        $pipes,
    );

    fwrite(STDOUT, "Done\n");
    exit(0);
    PHP_BODY);

    $began = microtime(true);
    $run = boundedStart([PHP_BINARY, $script, $mark], 30.0);
    $elapsed = microtime(true) - $began;

    @unlink($script);

    // The child's own exit code is the answer: a grandchild still writing is not a timeout, and
    // reading it as one would report a bound where the program had simply finished.
    expect($run->timedOut)->toBe(false, $run->describe())
        ->and($run->exitCode)->toBe(0)
        ->and($run->output)->toContain('Done')

        // What the grandchild wrote six seconds in never reached the capture, and could not have:
        // the reader was ended at the grace, so the bytes it had are the answer. A read to EOF would
        // have waited for this write and, past a deadline, had no way to say it had waited.
        ->and($run->output)->not->toContain('late')
        ->and(file_exists($mark))->toBeFalse()
        ->and($elapsed)->toBeLessThan(25.0);
});
