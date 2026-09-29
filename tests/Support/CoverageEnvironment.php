<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

/**
 * The PHP a coverage run is measured under, and the driver that can count a line.
 *
 * WHY THIS IS NAMED AT ALL
 * ------------------------
 * A floor has two ways to come back red on a machine that has no regression in it, and neither is
 * visible in the report it prints. The first is the interpreter: the floors were recorded under
 * one PHP, `composer test:unit` runs this under Composer's own, and a run that fails a script by a
 * point is more often a different binary than a different tree. The second is the driver: with
 * nothing loaded that can count a line, every file measures 0.0% and every floor reports the whole
 * package below itself, which reads as a regression in the code rather than as a machine that
 * cannot measure.
 *
 * So both are named before the suite starts, in the header of the run rather than in the report at
 * the end of it.
 *
 * WHY THE DECISION TAKES ITS OBSERVATIONS AS ARGUMENTS
 * ---------------------------------------------------
 * `detect()` asks the running PHP when it is called with nothing, and a test hands it what it wants
 * counted. Two of these branches cannot be produced on a runner that installs PCOV and nothing
 * else — a PCOV that is loaded but switched off, and an Xdebug left in `develop` mode, which covers
 * nothing while looking like a driver — and they are the two worth being sure of, because each one
 * is a floor of 0.0% that says "driver loaded" if the question is asked carelessly.
 *
 * @guards-index reading
 */
final readonly class CoverageEnvironment
{
    /**
     * @param  string  $php  e.g. `8.4.26 (cli)`
     * @param  string  $binary  this interpreter's own path, forward slashes only
     * @param  string  $composer  `COMPOSER_BINARY`, or '' when no Composer script started this
     * @param  string  $driver  `PCOV 1.0.12`, or '' when nothing here can count a line
     * @param  string  $why  what is loaded, said as what is missing; '' when there is a driver
     * @param  string  $hint  the one line that would give this machine a driver; '' when it has one
     */
    private function __construct(
        public string $php,
        public string $binary,
        public string $composer,
        public string $driver,
        public string $why,
        public string $hint,
    ) {}

    /**
     * What this machine is, as observed — or as passed in, which is how a test reaches the
     * combinations no runner can be made to have.
     *
     * @param  array<string, string>|null  $extensions  loaded extension => its version, or null to ask the running PHP
     * @param  string|null  $pcovEnabled  the `pcov.enabled` ini value, or null to ask
     * @param  string|null  $xdebugMode  the `xdebug.mode` ini value, or null to ask
     * @param  string|null  $composer  `COMPOSER_BINARY`, or null to ask
     */
    public static function detect(
        ?array $extensions = null,
        ?string $pcovEnabled = null,
        ?string $xdebugMode = null,
        ?string $composer = null,
    ): self {
        $extensions ??= self::loaded();
        $pcovEnabled = trim($pcovEnabled ?? (string) ini_get('pcov.enabled'));
        $xdebugMode = trim($xdebugMode ?? (string) ini_get('xdebug.mode'));
        // Both paths are written the way the rest of this package's output writes them: forward
        // slashes only, so a report reads the same on either platform.
        $composer = str_replace('\\', '/', trim($composer ?? (string) getenv('COMPOSER_BINARY')));

        $pcov = $extensions['pcov'] ?? null;
        $xdebug = $extensions['xdebug'] ?? null;

        $driver = match (true) {
            $pcov !== null && self::on($pcovEnabled) => 'PCOV '.$pcov,
            $xdebug !== null && self::covers($xdebugMode) => 'Xdebug '.$xdebug.' (xdebug.mode='.$xdebugMode.')',
            default => '',
        };

        return new self(
            php: PHP_VERSION.' ('.PHP_SAPI.')',
            binary: str_replace('\\', '/', PHP_BINARY),
            composer: $composer,
            driver: $driver,
            why: $driver === '' ? self::why($pcov, $xdebug, $xdebugMode) : '',
            hint: $driver === '' ? self::hint($pcov, $xdebug) : '',
        );
    }

    /**
     * The header: the three answers, as rows, in the shape the report below them uses.
     */
    public function describe(): string
    {
        $rows = [
            ['php', $this->php.'  '.$this->binary],
            ['started by', $this->composer === ''
                ? 'a direct run (COMPOSER_BINARY is unset) - the PHP above is the one you invoked'
                : 'composer (COMPOSER_BINARY='.$this->composer.') - the PHP above is the one it runs under'],
            ['coverage driver', $this->driver === '' ? 'none - '.$this->why : $this->driver],
        ];

        // The fix is a row of its own rather than the tail of the one above it: it is an instruction
        // where the others are observations, and a header that reads as one block of observations
        // should not carry the one line that is meant to be acted on.
        if ($this->driver === '') {
            $rows[] = ['to fix it', $this->hint];
        }

        $lines = ['Coverage floor, measured under:'];

        foreach ($rows as [$label, $value]) {
            $lines[] = sprintf('  %-17s %s', $label, $value);
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * The same two facts on one line, for the foot of a failure.
     *
     * A floor that moved is either the code that changed or the PHP that ran it, and a red build is
     * read at the bottom of two minutes of test output rather than at the top: the line is repeated
     * there so the reason can be ruled out without scrolling back through a suite.
     */
    public function summary(): string
    {
        return $this->driver === ''
            ? sprintf('PHP %s at %s, with no coverage driver in it.', $this->php, $this->binary)
            : sprintf('PHP %s at %s, coverage by %s.', $this->php, $this->binary, $this->driver);
    }

    /**
     * The two extensions that can measure a line, by the name the ini files use for them.
     *
     * @return array<string, string>
     */
    private static function loaded(): array
    {
        $extensions = [];

        foreach (['pcov', 'xdebug'] as $name) {
            if (extension_loaded($name)) {
                $extensions[$name] = (string) phpversion($name);
            }
        }

        return $extensions;
    }

    /**
     * Whether a boolean ini was left on. `ini_get` answers '' for off as readily as it answers '0',
     * and a driver that is loaded and switched off counts nothing.
     */
    private static function on(string $value): bool
    {
        return ! in_array(strtolower($value), ['', '0', 'off', 'false', 'no'], true);
    }

    /**
     * Whether an `xdebug.mode` list has `coverage` in it.
     *
     * The mode is the whole answer for Xdebug: a driver loaded for step debugging is a driver that
     * counts nothing, so "xdebug is loaded" beside a floor of 0.0% is the sentence that sends a
     * reader looking in the wrong place. PHP 8.4 requires Xdebug 3, which is the version that has
     * `xdebug.mode` — there is no older one to ask a different question of.
     */
    private static function covers(string $mode): bool
    {
        return in_array('coverage', array_map(trim(...), explode(',', strtolower($mode))), true);
    }

    /**
     * What is loaded, said as the thing that is missing.
     *
     * PCOV's ini is not printed: when this is reached with PCOV loaded, it is the reason — the
     * caller only asks for this when the driver came back empty, and PCOV returns early otherwise.
     */
    private static function why(?string $pcov, ?string $xdebug, string $xdebugMode): string
    {
        $found = [$pcov === null
            ? 'pcov is not loaded'
            : 'pcov '.$pcov.' is loaded but pcov.enabled is off'];

        $found[] = match (true) {
            $xdebug === null => 'xdebug is not loaded',
            $xdebugMode === '' => 'xdebug '.$xdebug.' is loaded with xdebug.mode empty, which counts nothing',
            default => 'xdebug '.$xdebug.' is loaded with xdebug.mode='.$xdebugMode.', which has no coverage in it',
        };

        return implode('; ', $found);
    }

    /**
     * The one line that would give this machine a driver, chosen by what is already installed:
     * nobody should be told to install the driver that is loaded and switched off.
     *
     * The ini is named rather than a `-d` flag, because the process that measures is not this one —
     * it is the suite, started as a child with the same ini and none of this invocation's flags.
     */
    private static function hint(?string $pcov, ?string $xdebug): string
    {
        return match (true) {
            $pcov !== null => 'set pcov.enabled=1 in this PHP\'s ini',
            $xdebug !== null => 'add coverage to xdebug.mode in this PHP\'s ini',
            default => 'install PCOV - it is what CI installs (`coverage: pcov` in .github/workflows/main.yml)',
        };
    }
}
