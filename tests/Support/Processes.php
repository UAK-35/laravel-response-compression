<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * One place a fixture starts a child: a start this machine refused is tried again, and the reason it gave is kept.
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
 * @guards-index support
 */
final class Processes
{
    /** The first pause, in microseconds; each one is three times the last, up to the cap below. */
    private const int FIRST_PAUSE = 50_000;

    /** The longest single pause, in microseconds: a refusal is waited out in steps, not in one. */
    private const int LONGEST_PAUSE = 4_000_000;

    /** How long one start may be waited out for, in seconds, before it is called a refusal. */
    private const float PATIENCE = 30.0;

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
     * The reason the last refused start gave.
     *
     * For a caller whose message is its own sentence about what it was trying to ask, rather than
     * the one `start()` writes.
     */
    public static function reason(): string
    {
        return self::$reason;
    }
}
