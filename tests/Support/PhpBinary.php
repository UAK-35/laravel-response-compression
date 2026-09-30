<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

/**
 * which PHP this repository runs, read the one way it is decided.
 *
 * WHY THIS EXISTS
 * ---------------
 * Four things here used to answer "which PHP" separately: the pre-commit hook climbed a ladder of
 * its own, the workflow installed a version written into it, `bin/tool.php` ran whatever started
 * it, and `bin/coverage.php` measured its floors under whatever Composer runs. `.agents/php-bin.sh`
 * is that ladder in one place now, and the two PHP programs above are the half of the question a
 * shell script cannot answer for them: they are *already running* under an interpreter, and the
 * useful thing to say is whether it is the one this machine names.
 *
 * So this is the reading, not a second resolver. It asks the resolver for the interpreter and the
 * rung that answered - one call, `--report`, so the two can never come from two different answers -
 * and compares the result with `PHP_BINARY`.
 *
 * THE RUNG IS THE WHOLE POINT
 * ---------------------------
 * A disagreement is worth a line only when the answer was a *decision about this machine*, which is
 * the environment override or `.agents/machine.local.json`. The two rungs below those are
 * fallbacks, and a fallback disagreeing is not a finding: on a CI runner there is no machine file,
 * the resolution comes from PATH, and the PHP that installed the dependencies is the first `php` on
 * PATH as often as not - so a run on a runner would print a note about a difference that is the
 * arrangement, not a mistake. A machine whose own file names one interpreter while Composer runs
 * another is the case this is for.
 *
 * WHY THE ANSWER CAN BE `null`
 * ----------------------------
 * The resolver is a shell script, because the hook needs an interpreter before it can resolve one.
 * Where there is no `sh` to run it with, no resolver file, or no answer, this reading says so
 * rather than guessing: `agrees()` is `null` and `disagreement()` is empty, which is a different
 * statement from `false` and from silence. A reading that answered `false` everywhere it could not
 * look would put a note at the head of every run on such a machine.
 *
 * THE OBSERVATION IS AN ARGUMENT
 * ------------------------------
 * `named()` asks when it is called with nothing, and a test hands it the resolver's own line.
 * The branches that matter here - a rung that is a declaration disagreeing, a rung that is a
 * fallback disagreeing, a resolver that cannot be reached at all - are arrangements no machine can
 * be made to have on demand, so they are handed in the way `CoverageEnvironment` takes its
 * extensions.
 *
 * @guards-index reading
 */
final readonly class PhpBinary
{
    /** The one file in this checkout that decides which PHP anything runs. */
    public const string RESOLVER = '.agents/php-bin.sh';

    /** The rung that is this machine's own answer rather than a fallback. */
    public const string MACHINE = '.agents/machine.local.json';

    /** The other rung that is a decision: an override for one command. */
    public const string ENVIRONMENT = 'the environment';

    /** The two rungs that are fallbacks, kept here so a reader of this file can see the split. */
    public const array FALLBACKS = ['git config', 'PATH'];

    /**
     * @param  string  $names  the interpreter the repository names, or '' when nothing could be resolved
     * @param  string  $rung  the rung that answered - one of the four above, or '' when none did
     */
    private function __construct(
        public string $names,
        public string $rung,
    ) {}

    /**
     * The repository's answer, asked of the resolver - or handed in, which is how a test reaches the
     * rungs no machine can be arranged into.
     *
     * @param  string|null  $report  the resolver's own `--report` line, `rung<TAB>value`, or null to ask it
     */
    public static function named(string $root, ?string $report = null): self
    {
        $report ??= self::ask($root);

        if ($report === '' || ! str_contains($report, "\t")) {
            return self::unknown();
        }

        [$rung, $names] = explode("\t", $report, 2);

        return new self(trim($names), trim($rung));
    }

    /** Nothing could be asked, or nothing answered. */
    public static function unknown(): self
    {
        return new self('', '');
    }

    /** The interpreter running this process, in the one spelling this package writes paths in. */
    public static function running(): string
    {
        return str_replace('\\', '/', PHP_BINARY);
    }

    /**
     * Whether the interpreter running this process is the one the repository names.
     *
     * `null` is "not asked", which is not the same answer as "no": a caller that treated the two
     * alike would report every machine without a shell as a machine with the wrong PHP.
     */
    public function agrees(): ?bool
    {
        if ($this->names === '' || $this->rung === '') {
            return null;
        }

        return $this->same(self::running(), $this->names);
    }

    /** Whether the answer came from a decision about this machine rather than from a fallback. */
    public function decided(): bool
    {
        return $this->rung === self::MACHINE || $this->rung === self::ENVIRONMENT;
    }

    /**
     * What the rung is, in the words a reader needs: the file, or the variable, rather than the
     * short name the rung is keyed by.
     */
    public function source(): string
    {
        return match ($this->rung) {
            self::ENVIRONMENT => 'RESPONSE_COMPRESSION_PHP',
            default => $this->rung,
        };
    }

    /**
     * The one sentence worth printing about a run measured under an interpreter that is not the one
     * this machine names - or '' when there is nothing worth saying.
     *
     * Empty in three cases, and each of them is a reason not to print rather than a reason to
     * guess: the resolver could not be asked, it named nothing, or it answered from a fallback.
     */
    public function disagreement(): string
    {
        if (! $this->decided() || $this->agrees() !== false) {
            return '';
        }

        return sprintf(
            'this ran under %s, and %s names %s',
            self::running(),
            $this->source(),
            $this->names,
        );
    }

    /**
     * The resolver's answer for one tree, as it writes it: `rung<TAB>value`, or '' when it could not
     * be asked at all.
     *
     * The shell is looked up rather than spelled out, and the resolver is run by its own path, so a
     * tree is asked about through the file that tree holds. Nothing here reads
     * `.agents/machine.local.json`: its shape belongs to `render_local.py`, and a second reader
     * would be a second thing to keep in step with it.
     */
    private static function ask(string $root): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $resolver = $root.'/'.self::RESOLVER;

        if (! is_file($resolver) || ! self::worthAsking($root)) {
            return '';
        }

        $process = @proc_open(
            [self::shell(), $resolver, '--report'],
            [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w']],
            $pipes,
            $root,
        );

        if (! is_resource($process)) {
            return '';
        }

        $report = trim((string) stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        proc_close($process);

        return $report;
    }

    /**
     * Whether there is any point asking.
     *
     * The two rungs that can produce a sentence are the environment override and the machine file;
     * where neither is there - a CI runner, a fixture tree, a checkout nobody has run `--init` in -
     * the answer can only be a fallback, which `disagreement()` prints nothing about. So the
     * resolution is skipped rather than paid for, and it is worth saying what that costs: asking
     * means starting a shell and, through it, the renderer, which is a fifth of a second on a
     * machine with a native Python and a couple of seconds behind a store-shimmed one. `bin/tool.php`
     * asks once per tool it runs, so the check is what keeps that off the machines where the answer
     * could not have changed anything.
     *
     * This decides nothing about which PHP runs: it is about whether a question is worth asking, and
     * the question itself is still answered in one place.
     */
    private static function worthAsking(string $root): bool
    {
        return getenv('RESPONSE_COMPRESSION_PHP') !== false
            || is_file($root.'/'.self::MACHINE);
    }

    /**
     * The shell to ask with. Not a path: a path into a Git installation is a fact about one machine,
     * and this package's own machine-path guard is right about why. A machine with no `sh` on its
     * PATH is a machine where the question goes unasked, which is an answer this class can carry.
     */
    private static function shell(): string
    {
        return 'sh';
    }

    /**
     * Whether two spellings are the same interpreter.
     *
     * Compared through `realpath()` when both sides are files, because a symlinked `php` and the
     * binary it points at are one interpreter and two strings - which is the usual arrangement on a
     * Unix runner, where the answer from PATH is the symlink and `PHP_BINARY` is the resolution of
     * it. Case is ignored on Windows for the same reason it is on that filesystem.
     */
    private function same(string $one, string $other): bool
    {
        $one = rtrim($one, '/');
        $other = rtrim($other, '/');

        if ($one === $other) {
            return true;
        }

        $realOne = realpath($one);
        $realOther = realpath($other);

        if ($realOne !== false && $realOther !== false) {
            $one = str_replace('\\', '/', $realOne);
            $other = str_replace('\\', '/', $realOther);
        }

        return PHP_OS_FAMILY === 'Windows' ? strcasecmp($one, $other) === 0 : $one === $other;
    }
}
