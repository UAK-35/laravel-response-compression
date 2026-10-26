<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

/**
 * How many workers a parallel test run may start, and the flags that say so: half the cores the machine has, and never fewer than one.
 *
 * WHY THE POOL HAS A SIZE OF ITS OWN
 * ----------------------------------
 * `--parallel` is paratest, and paratest starts one worker per core unless it is told otherwise. A
 * worker is not one process: it is a `php` process that loads the framework and a test file, and
 * this suite's files are process spawns of their own — a planted git repository, a release script
 * started against it, a nested `pest` run under an overlay — so a pool of one per core is a
 * machine being asked for several processes per core. What that looks like from the gate is not a
 * failure: a file whose worker never started is reported as an error, `bin/checks.php` reads those
 * off the JUnit log and asks each one again alone, and the run finishes green with a note blaming
 * the machine. True, and useless — eight files were named that way on this machine, every one of
 * them green when asked on its own.
 *
 * The coverage floor has no such retry, and there the same storm is not a slow run but a wrong one:
 * a test that could not start the script it measures leaves that script's lines uncovered, so
 * `bin/coverage.php` reported `bin/commit.php` at 0.0% and `bin/release.php` at 72.5% against a
 * floor of 78% on a machine with nothing wrong with it. With the pool sized below, the same run
 * measured 78.2% and 80.3% and every floor was met.
 *
 * MEASURED, RATHER THAN ASSUMED
 * -----------------------------
 * On the machine this was measured on, sixteen cores: a pool of fifteen held thirty-three `php`
 * processes while it ran, which is two and a half per worker, and the same suite on a pool of eight
 * held eighteen. Both runs passed all 499 tests with no file asked again — and the eight-worker run
 * took 242 seconds where the fifteen-worker one took 214, which is the price of the bound: thirteen
 * per cent, for a pool that leaves the other half of the machine to the gate, to the children a
 * test starts, and to whatever else is running on it. A run of the full pool while something else
 * was running a gate of its own is the shape of the failure above, and it is the reason to leave
 * that half rather than to take one core off and call the pool sized.
 *
 * WHY IT IS NOT MEMORY
 * --------------------
 * The first reading here asked the machine's available memory and divided it by a per-worker
 * budget, and the measurement refused it: the suite passed all 499 tests with a pool of fifteen
 * when the machine reported 286 MB available, and the same figure swung between 286 MB and 6 GB
 * across runs on an otherwise idle host. A memory bound would have cut that pool to two workers on
 * a reading that predicted a failure that did not happen, and the gate would have taken four times
 * as long for it. What a worker needs is a process, and the machine that can create sixty-four of
 * them at once is not short of them; what the pool has to fit inside is the cores.
 *
 * A platform that reports no core count gets one worker rather than a guess: a slow gate is a gate,
 * and a guess about how many processes a machine can start is the failure this class exists to
 * stop.
 *
 * @guards-index support
 */
final class Workers
{
    /** The pool's size on the machine this is running on. */
    public static function count(): int
    {
        return self::bound(self::cores());
    }

    /**
     * The flags that start a pool of that size, as pest is handed them.
     *
     * One place rather than two, because both programs that start a pool — the gate and the coverage
     * floor — have to say the same thing about the same machine, and a second copy of the option is
     * a second place for it to be written differently. The number goes on the option itself
     * (`--processes=8`), and that is measured rather than chosen: pest rebuilds the arguments before
     * paratest sees them, so `--processes 8` arrives as an option with nothing after it and the run
     * stops on "The `--processes` option requires a value", while `-p 8` is refused as a second
     * path. `--processes=8` is the form that arrives intact.
     *
     * @return list<string>
     */
    public static function arguments(): array
    {
        return ['--parallel', '--processes='.self::count()];
    }

    /**
     * The pool's size, from the core count rather than from the machine.
     *
     * Split out because the machine's own answer is not testable and the arithmetic is: a dataset
     * hands this function a number of cores and asks what the pool would be, and no test has to
     * pretend to be a machine with eight of them. `count()` is the same arithmetic over the one
     * read, which is the only place the two meet.
     *
     * The floor of one is what keeps a one-core machine — and a core count that could not be read,
     * which answers the same way — running a suite rather than a pool of zero.
     */
    public static function bound(int $cores): int
    {
        return max(1, intdiv($cores, 2));
    }

    /**
     * The cores this machine has, as the platform spells them.
     *
     * `NUMBER_OF_PROCESSORS` first because Windows sets it and because a runner sets it too, which is
     * the answer that needs no file and no child process; `/proc/cpuinfo` next; `sysctl` for macOS,
     * which is the one platform of the three that has to be asked; and one core for anything left,
     * which is a slow gate rather than a guess.
     */
    public static function cores(): int
    {
        $reported = getenv('NUMBER_OF_PROCESSORS');

        if (is_string($reported) && is_numeric($reported) && (int) $reported > 0) {
            return (int) $reported;
        }

        $info = @file_get_contents('/proc/cpuinfo');

        if (is_string($info) && preg_match_all('/^processor\s*:/m', $info, $found) > 0) {
            return count($found[0]);
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            $counted = trim(self::shell(['sysctl', '-n', 'hw.ncpu']));

            if (is_numeric($counted) && (int) $counted > 0) {
                return (int) $counted;
            }
        }

        return 1;
    }

    /**
     * One command, and whatever it wrote to its output stream.
     *
     * A child that could not be started, or one that answered with nothing, is an empty string rather
     * than an exception: this reading has a bound for that case, and the gate is the wrong place to
     * stop because a platform would not report its cores.
     *
     * @param  list<string>  $command
     */
    private static function shell(array $command): string
    {
        // No output is this reading's answer to a shell that could not be asked, so a refused start —
        // waited out by `Processes` — comes back as an empty count rather than as a throw.
        $started = Processes::attempt($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']]);

        if ($started === null) {
            return '';
        }

        [$process, $pipes] = $started;

        $output = (string) stream_get_contents($pipes[1]);

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $output;
    }
}
