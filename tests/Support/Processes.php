<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * One place a fixture starts a child: a start this machine refused is tried again, the reason it gave is kept,
 * and a start that succeeds and then blocks is stopped at a deadline with its tree.
 *
 * ## Why a refused start is worth trying again
 *
 * Under a parallel pool on Windows, `proc_open()` answers `CreateProcess failed: The parameter is
 * incorrect` sporadically, before a child exists. Measured at sixteen workers: 42 failed tests on
 * the run that raised it, then 29, then 26 — never an assertion about the work. Every one of them
 * is a file whose first test was refused while building its fixture tree, which then failed every
 * test after it in the same file for the same reason, and every one of them is green when re-run
 * alone. A child that starts and then exits non-zero is a different thing entirely, and is never
 * retried here.
 *
 * The distinction is what makes a retry honest rather than a papered-over failure: no process was
 * created, so a second attempt repeats no effect. It is also why this is one place instead of a
 * loop in every helper — the reading that could not start `git` and the one that could not start
 * `bash` were failing for the same reason, and the answer should not depend on which file asked.
 *
 * ## The ladder, and what it is not
 *
 * 50 milliseconds, then three times that each pause up to four seconds, for as long as thirty
 * seconds: nothing on a green run, because the ordinary path is the first attempt and only a
 * refused start pays. The ladder is that long because two shorter versions were beaten by
 * measurement. Three attempts with 60 milliseconds of pauses lost a run of 29 failed tests, all one
 * refused start per file. Five attempts with just over two seconds on the table lost a run of 41,
 * every one of them reported as `no reason was given` — which is what a refusal with no message of
 * its own comes back as, and the absence rather than the exception is the finding — and that is the
 * run that set the ceiling. The fault is bursty rather than
 * uniform: the next run at the same width recorded 948 starts and not one refusal, so a ceiling
 * read off a green run would have been read off nothing.
 *
 * The pauses are also the end of a search that started from the obvious suspects, each of them
 * removed by measurement rather than by argument. It is not the descriptors: a run with every
 * stream on a file instead of a pipe failed the same way. It is not the count of children: sixteen
 * processes each starting `git` a hundred times failed none. It is not exhaustion, the working
 * directory or the environment block: at the moment of refusal the worker held eleven resources and
 * 23 MB, its directory existed, and `error_get_last()` was empty. What is left is the machine
 * declining `CreateProcess` for a stretch of time, which a retry outlasts and nothing else here can.
 *
 * `start()` throws when the last attempt was refused too, with the reason beside the command it
 * could not run, so a fixture that genuinely cannot start `git` says why instead of leaving a
 * warning nobody read. `attempt()` answers `null` for the readings that treat "this machine cannot
 * start it" as an answer of its own — a shell that reports no core count is a bound, not a failure.
 *
 * ## Why a start is not the whole question
 *
 * `start()` and `attempt()` answer a child; they say nothing about one that starts, runs, and then
 * never lets the parent go. Every helper here used to read that child with
 * `stream_get_contents()` and then call `proc_close()`, and neither call has a clock in it: the read
 * waits for EOF, which every writer must close — including a grandchild that inherited the handle —
 * and the close waits for an exit that a `git` holding `index.lock` or a shell waiting on a console
 * never performs. A run that succeeded at `proc_open()` and then blocked stalled the worker with
 * nothing to report, and there is no per-test timeout in `phpunit.xml` to end it.
 *
 * `bounded()` is that half: it reads through a reader child, polls the child it started against a
 * deadline, and stops the tree when the deadline is reached. Every helper in this layer that spawns
 * a child goes through it, so the two ways a start can hang — EOF that never arrives, and a child
 * that never exits — are answered in one place rather than nine.
 *
 * @guards-index support
 */
final class Processes
{
    /** How long one bounded run may take, in seconds, before it and everything it started are stopped. */
    public const float DEADLINE = 300.0;

    /**
     * The status a stopped run reports.
     *
     * The shell's own code for "the command timed out", so a stopped run cannot be read as a child's
     * answer: no program here exits 124, and `timedOut` says so again beside it.
     */
    public const int TIMED_OUT = 124;

    /** The first pause, in microseconds; each one is three times the last, up to the cap below. */
    private const int FIRST_PAUSE = 50_000;

    /** The longest single pause, in microseconds: a refusal is waited out in steps, not in one. */
    private const int LONGEST_PAUSE = 4_000_000;

    /** How long one start may be waited out for, in seconds, before it is called a refusal. */
    private const float PATIENCE = 30.0;

    /** The bounded time a reader is given to finish a capture once the child it drained is gone. */
    private const float GRACE = 5.0;

    /** How long one poll of a child sleeps, in microseconds. */
    private const int POLL = 10_000;

    /** The reader: copy stdin — the child's stdout pipe — into the capture file named in argv[1]. */
    private const string READER = 'stream_copy_to_stream(STDIN, fopen($argv[1], "w"));';

    /** The reason the most recent refusal gave, read by the caller that writes its own sentence. */
    private static string $reason = 'no reason was given';

    /**
     * Start one child, and answer it — or throw, naming the command and the reason it was refused.
     *
     * @param  list<string>  $command
     * @param  array<int, array<int, string>>  $descriptors
     * @param  array<string, string>|null  $environment
     * @return array{0: resource, 1: array<int, resource>}
     *
     * @throws RuntimeException when every attempt was refused
     */
    public static function start(
        array $command,
        array $descriptors,
        ?string $cwd = null,
        ?array $environment = null,
    ): array {
        $started = self::attempt($command, $descriptors, $cwd, $environment);

        if ($started === null) {
            throw new RuntimeException(sprintf(
                'Could not start %s: %s',
                implode(' ', $command),
                self::$reason,
            ));
        }

        return $started;
    }

    /**
     * Start one child, retrying only a start that was refused, and answer `null` when none was.
     *
     * The pipes are built afresh for every attempt: a refused start leaves behind nothing to close,
     * and the array a caller receives is the one from the attempt that worked. Every attempt is a
     * fresh call to `proc_open()` — there is nothing to reset between them, because a refused start
     * produced nothing — and the waiting ends at `PATIENCE` seconds, which is the only clock here.
     *
     * @param  list<string>  $command
     * @param  array<int, array<int, string>>  $descriptors
     * @param  array<string, string>|null  $environment
     * @return array{0: resource, 1: array<int, resource>}|null
     */
    public static function attempt(
        array $command,
        array $descriptors,
        ?string $cwd = null,
        ?array $environment = null,
    ): ?array {
        $deadline = microtime(true) + self::PATIENCE;
        $pause = self::FIRST_PAUSE;

        while (true) {
            $pipes = [];
            $refusal = null;

            // The refusal is taken by a handler of this class's own rather than by `@`, and that is
            // the difference between a warning nobody reads and a warning nobody records. `@` stops
            // PHP from *printing* it and nothing else: the message is still raised, and a runner's
            // handler still sees it — so Pest recorded it against the test that made the start, and
            // `phpunit.xml.dist` carries `failOnWarning="true"`, which is a failing test on a tree
            // with nothing wrong with it. Measured: a `@proc_open()` at a program that is not there
            // comes back as one `proc_open(): CreateProcess failed` warning against the test that
            // made the call, and a pooled run had 42 tests carrying it.
            //
            // The handler answers `true` for the one call below, so nothing else is raised while the
            // attempt is made, and it goes back on the way out whichever way the call went: a handler
            // left installed would swallow every warning after it, in a suite whose config fails the
            // run on one. What it keeps is this class's reason — `start()` throws it beside the
            // command it could not run, which is the report that replaces the warning.
            set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$refusal): bool {
                $refusal = $message;

                return true;
            });

            try {
                $process = proc_open($command, $descriptors, $pipes, $cwd, $environment);
            } finally {
                restore_error_handler();
            }

            if (is_resource($process)) {
                return [$process, $pipes];
            }

            self::$reason = $refusal ?? 'no reason was given';

            if (microtime(true) + $pause / 1_000_000 > $deadline) {
                return null;
            }

            usleep($pause);
            $pause = min($pause * 3, self::LONGEST_PAUSE);
        }
    }

    /**
     * Run one child to its end, or to a deadline — and answer the run, or `null` when no start was
     * possible.
     *
     * ## What this replaces
     *
     * `stream_get_contents($pipe)` followed by `proc_close()`. Both waits are unbounded for reasons
     * that have nothing to do with the program under test: the read returns at EOF, and a grandchild
     * that inherited the handle can hold the pipe open long after the child it belongs to has
     * exited; the close returns when the child exits, and a `git` waiting on `index.lock` never
     * does. On Windows the pipe cannot be made non-blocking as a way out — measured here,
     * `stream_set_blocking()` answers `false` for a `proc_open` pipe, `stream_get_meta_data()` still
     * says `blocked`, and a first `fread()` waited 2.1 seconds for a byte — and stdout cannot be a
     * file, because `bin/bootstrap.php` hands `STDOUT` to composer and the verifier, and a run's last
     * write was measured landing at the capture file's first byte, ahead of its children's lines.
     *
     * So the read moves one process out: a reader child copies the pipe into a capture file and
     * blocks, which is its whole job, while this process polls `proc_get_status()`, the one question
     * about a child that is asked rather than waited on. A poll can look at a clock, which is what
     * makes a deadline possible at all.
     *
     * ## What is stopped, and what is only reported
     *
     * Past the deadline the child and everything below it are stopped, because a bound that left a
     * tree running would leave it writing into a tree the next test copies. Windows has
     * `taskkill /F /T` for that and it goes first there, because `/T` finds descendants through the
     * parent that is still there; elsewhere the process table is walked downwards, because
     * `proc_open()` starts no process group. `/T` enumerates before it terminates, so a grandchild
     * created while it works is not in the list and outlives the parent: what is still parented to
     * the dead child is therefore swept by pid once it is gone.
     *
     * A grandchild holding the pipe after the child exited is *not* a timeout. The child's own exit
     * code is the answer, the reader is given `GRACE` seconds to finish and then ended, and a partial
     * capture is read as the output — a run that answered is a run that was not hung.
     *
     * @param  list<string>  $command
     * @param  array<string, string>|null  $environment
     */
    public static function bounded(
        array $command,
        ?string $cwd = null,
        ?array $environment = null,
        float $deadline = self::DEADLINE,
    ): ?ReleaseRun {
        $stdout = self::captureFile();
        $stderr = self::captureFile();

        $started = self::attempt(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $stderr, 'w']],
            $cwd,
            $environment,
        );

        if ($started === null) {
            self::forget($stdout);
            self::forget($stderr);

            return null;
        }

        [$process, $pipes] = $started;

        fclose($pipes[0]);

        // The reader is handed the pipe itself, not a copy of a file: a duplicate is what makes EOF
        // arrive when the last writer closes it — the child, or a grandchild that inherited `STDOUT`
        // — and it is why this process closes its own handle before it waits.
        $readerStarted = self::attempt(
            [PHP_BINARY, '-r', self::READER, $stdout],
            [0 => $pipes[1], 1 => self::discard(), 2 => self::discard()],
        );

        fclose($pipes[1]);

        if ($readerStarted === null) {
            // Nothing is draining the child, so a child that writes past a pipe buffer would block
            // for as long as this process did not read: it is stopped rather than left waiting for
            // a reader that never came.
            self::stop($process);
            self::forget($stdout);
            self::forget($stderr);

            return null;
        }

        [$reader] = $readerStarted;

        $timedOut = false;

        try {
            $exitCode = self::await($process, $reader, $deadline);
            $timedOut = $exitCode === null;

            $output = self::read($stdout);
            $error = self::read($stderr);
        } finally {
            proc_close($reader);
            proc_close($process);
            self::forget($stdout);
            self::forget($stderr);
        }

        return new ReleaseRun(
            $timedOut ? self::TIMED_OUT : (int) $exitCode,
            $output,
            $error,
            implode(' ', $command),
            $timedOut,
            $deadline,
        );
    }

    /**
     * The reason the last refused start gave.
     *
     * For a caller whose message is its own sentence about what it was trying to ask, rather than
     * the one `start()` writes.
     */
    public static function reason(): string
    {
        return self::$reason;
    }

    /**
     * Wait for one child, and answer its exit status — or `null` when the deadline stopped it.
     *
     * The clock is the point of this method. `proc_get_status()` is the one call that asks about a
     * child without waiting on it, so a loop that asks it is a loop that can decide a run has taken
     * too long; the blocking read lives in the reader, which is why this process never enters one.
     *
     * @param  resource  $process
     * @param  resource  $reader
     */
    private static function await($process, $reader, float $deadline): ?int
    {
        $until = microtime(true) + $deadline;

        while (true) {
            $status = proc_get_status($process);

            if (! $status['running']) {
                self::settle($reader);

                return (int) $status['exitcode'];
            }

            if (microtime(true) >= $until) {
                self::stop($process);
                self::settle($reader);

                return null;
            }

            usleep(self::POLL);
        }
    }

    /**
     * Give the reader the bounded time it needs to finish the capture, then end it.
     *
     * A child that has exited can leave a grandchild holding the pipe, so the reader is waited on
     * rather than assumed finished; past the grace it is ended and whatever it has written is read
     * as the output, which is a partial capture rather than a hang.
     *
     * @param  resource  $reader
     */
    private static function settle($reader): void
    {
        $until = microtime(true) + self::GRACE;

        while (proc_get_status($reader)['running'] && microtime(true) < $until) {
            usleep(self::POLL);
        }

        proc_terminate($reader);
    }

    /**
     * Stop a child that has run past its deadline, and everything it started.
     *
     * The tree is the half `proc_terminate()` cannot answer: a release script's children are `git`,
     * and the bootstrap fixture's are composer and the verifier, and a bound that left those
     * running would leave them writing into a tree the next test copies — the cleanup the report's
     * finding asked for, rather than only the clock. Windows has `taskkill /T` for it, and there it
     * goes first: `/T` finds a child's descendants through the parent that is still there, so
     * stopping the child first would leave nothing to enumerate the tree by. Elsewhere the process
     * table is walked downwards, because `proc_open()` starts no process group for `kill -pid` to
     * name.
     *
     * `/T` is a snapshot and a child can outrun it: it enumerates the tree first and terminates it
     * after, so a descendant created in between is not in the list and outlives the parent that was
     * killed. That is the shape a loaded machine finds — the child's boot takes seconds, the
     * deadline lands mid-boot, and the grandchild is started while `taskkill` is working. So once
     * the child is gone, what is still parented to it is swept by pid. Past the child's exit nothing
     * can create another descendant, which is what makes that pass a guarantee rather than a second
     * race.
     *
     * @param  resource  $process
     */
    private static function stop($process): void
    {
        $pid = (int) proc_get_status($process)['pid'];

        if ($pid > 0 && PHP_OS_FAMILY === 'Windows') {
            exec(sprintf('taskkill /F /T /PID %d 2>NUL', $pid));
        } elseif ($pid > 0) {
            foreach (array_reverse(self::descendants($pid)) as $descendant) {
                self::signal($descendant);
            }

            self::signal($pid);
        }

        proc_terminate($process);

        // A kill is a request; this waits, bounded, for it to have been carried out. Without it the
        // wait is `proc_close()` in the caller, and that one has no clock — the same shape as the
        // read the deadlock was made of.
        $until = microtime(true) + self::GRACE;

        while (proc_get_status($process)['running'] && microtime(true) < $until) {
            usleep(self::POLL);
        }

        if ($pid > 0) {
            self::sweepOrphans($pid);
        }
    }

    /**
     * Stop whatever is still parented to a child that has exited, on either platform.
     *
     * One round would do for the shape this exists for — nothing can be spawned by a dead parent —
     * and a second is kept for the descendant that had a child of its own in the same window: the
     * sweep kills each orphan with `/T` on Windows and deepest-first elsewhere, so that grandchild's
     * children go with it, and a round that finds nothing is the end of it either way.
     */
    private static function sweepOrphans(int $pid): void
    {
        for ($round = 0; $round < 2; $round++) {
            $orphans = PHP_OS_FAMILY === 'Windows'
                ? self::orphans($pid)
                : self::descendants($pid);

            if ($orphans === []) {
                return;
            }

            if (PHP_OS_FAMILY === 'Windows') {
                // One `taskkill` for the set: `/PID` repeats, and a call per orphan cost more than
                // the wait it was protecting — measured, a fixture spawning a grandchild every 20ms
                // had its survivors write their markers while the sweep was still working through
                // them one at a time.
                $pids = implode(' ', array_map(static fn (int $orphan): string => '/PID '.$orphan, $orphans));

                exec(sprintf('taskkill /F /T %s 2>NUL', $pids));
            } else {
                foreach (array_reverse($orphans) as $orphan) {
                    self::signal($orphan);
                }
            }
        }
    }

    /**
     * The processes whose parent is one that is gone, read from the machine's own process table.
     *
     * Windows keeps the parent id a process was created under, so a child outlives its parent with
     * the dead parent's id still on it — which is the handle this whole sweep needs. `wmic` used to
     * be the cheapest way to ask and is gone from current Windows, so the question goes to the same
     * CIM table PowerShell reads; a machine that cannot answer returns nothing, and the direct child
     * is stopped by `proc_terminate()` either way.
     *
     * @return list<int>
     */
    private static function orphans(int $pid): array
    {
        $listing = @shell_exec(sprintf(
            'powershell -NoProfile -NonInteractive -Command "Get-CimInstance Win32_Process -Filter \'ParentProcessId=%d\' | ForEach-Object { $_.ProcessId }" 2>NUL',
            $pid,
        ));

        if (! is_string($listing)) {
            return [];
        }

        $orphans = [];

        foreach (preg_split('/\R/', $listing) ?: [] as $line) {
            if (preg_match('/^\s*(\d+)\s*$/', $line, $match) === 1) {
                $orphans[] = (int) $match[1];
            }
        }

        return $orphans;
    }

    /**
     * A descriptor that throws away what is written to it, for a process whose output is not the
     * subject.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private static function discard(): array
    {
        return ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'];
    }

    /**
     * Kill one process, through `posix` where the extension answers and through `kill` where it is
     * not installed — Windows never reaches this, because `taskkill` is the whole tree there.
     */
    private static function signal(int $pid): void
    {
        if (function_exists('posix_kill')) {
            @posix_kill($pid, 9);

            return;
        }

        exec(sprintf('kill -9 %d 2>/dev/null', $pid));
    }

    /**
     * Every process below one, breadth first, read from the machine's own process table.
     *
     * `ps -e -o pid=,ppid=` is the form both Linux and macOS answer, and a machine that cannot be
     * asked answers with an empty list rather than failing: the direct child is stopped by
     * `proc_terminate()` either way, so what an unreadable table costs is the tree, not the stop.
     *
     * @return list<int>
     */
    private static function descendants(int $pid): array
    {
        $listing = @shell_exec('ps -e -o pid=,ppid= 2>/dev/null');

        if (! is_string($listing)) {
            return [];
        }

        /** @var array<int, int> $parents pid => ppid */
        $parents = [];

        foreach (preg_split('/\R/', $listing) ?: [] as $line) {
            if (preg_match('/^\s*(\d+)\s+(\d+)\s*$/', $line, $match) !== 1) {
                continue;
            }

            $parents[(int) $match[1]] = (int) $match[2];
        }

        $found = [];
        $queue = [$pid];

        while ($queue !== []) {
            $parent = array_shift($queue);

            foreach ($parents as $child => $ppid) {
                if ($ppid === $parent) {
                    $found[] = $child;
                    $queue[] = $child;
                }
            }
        }

        return $found;
    }

    /** A fresh capture path, beside the machine's other temporary files rather than in the tree. */
    private static function captureFile(): string
    {
        return sys_get_temp_dir().'/rc-bounded-'.bin2hex(random_bytes(8)).'.log';
    }

    /**
     * What the child wrote to one of its two capture files, read once it has exited.
     *
     * A file that is not there reads as an empty stream rather than as a failure: both are created
     * with the child, so its absence is a start that never happened, which `attempt()` has already
     * answered with `null`.
     */
    private static function read(string $path): string
    {
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /** Put a capture file away, whether or not the run that made it got as far as writing one. */
    private static function forget(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
