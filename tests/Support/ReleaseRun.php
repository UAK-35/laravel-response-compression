<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

/**
 * What one run of a `bin/` script did: its exit code and the two streams, kept apart on
 * purpose.
 *
 * The report goes to stdout and a refusal goes to stderr, so a test that asserts on the wrong
 * one passes for the wrong reason — a release that refused the wrong version still prints a
 * plan. Keeping them apart is what makes `refused()` mean something, and `plan()` reads the
 * value of a named line out of the plan rather than matching its spacing.
 *
 * @guards-index support
 */
final readonly class ReleaseRun
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $error,
        public string $command = '',
    ) {}

    /**
     * The value of one plan line, e.g. `plan('version')` → `0.1.0`. Empty when the run never got
     * as far as printing that line, which is itself the answer to a precondition that refused
     * first.
     */
    public function plan(string $key): string
    {
        if (preg_match('/^\s+'.preg_quote($key, '/').'\s{2,}(.+)$/m', $this->output, $match) !== 1) {
            return '';
        }

        return trim($match[1]);
    }

    /** Something the run said on stdout — a note, or the weighing. */
    public function said(string $needle): bool
    {
        return str_contains($this->output, $needle);
    }

    /** Something the run refused with, on stderr. */
    public function refused(string $needle): bool
    {
        return str_contains($this->error, $needle);
    }

    /** The whole run, for the message of a failed assertion. */
    public function describe(): string
    {
        return sprintf(
            "%s\nexit %d\n--- stdout ---\n%s\n--- stderr ---\n%s",
            $this->command,
            $this->exitCode,
            $this->output,
            $this->error,
        );
    }
}
