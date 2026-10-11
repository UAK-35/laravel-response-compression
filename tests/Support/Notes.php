<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

/**
 * the channel a reading writes to when it declines to ask a question, so that a skip is printed where a clean answer and a question nobody could ask would otherwise look the same
 *
 * ---------------------------------------------
 * WHY A DECLINE NEEDS SOMEWHERE TO BE SAID
 * -----------------------------------------
 * Half of this directory's readings ask a question some machines cannot answer, and the answer they
 * give in that case is the one they give when there is nothing wrong: no finding. `Handoff` holds
 * the environment table to `.agents/machine.local.json`, and a CI runner has no such file, so the
 * value half of that reading is never asked; `PhpBinary` walks its ladder only where there is a
 * machine file or an environment override, so on the same runner it answers from the fallback
 * without asking. Both are right to answer that way — failing a runner for the absence of a file
 * that no clone has until somebody runs `--init` would be a red build for the shape of the machine
 * rather than for anything in the package — and both are wrong to say it in the same voice as a
 * clean reading, because then a reading that has stopped asking anything reads exactly like a
 * reading that asked and agreed.
 *
 * So the decline is written down here and printed. The reading does not print it: a reading is also
 * used inside programs — `bin/tool.php` and `bin/coverage.php` both ask `PhpBinary`, and
 * `bin/tool.php` prints a line of its own about the interpreter — and a class that wrote to the
 * terminal where it was merely asked a question would be putting its voice into somebody else's
 * output. What prints is the harness: the suite prints whatever a test made, and `bin/checks.php`
 * lifts the marked lines out of a check's output into its summary, which is the channel
 * `python .agents/verify.py` already prints its own notes on.
 *
 * ------------------------------------------------
 * WHY THE CHANNEL IS ALSO A FILE
 * ------------------------------------------------
 * Run by hand, the suite is one process and its error stream is the terminal, so a note written
 * there is read. Run by `bin/checks.php` — which is every run in CI, and every commit — the suite is
 * `paratest`: one PHPUnit process per worker, and everything a worker prints goes into what PHPUnit
 * calls that test's unexpected output, which it prints for a test that did not pass and drops for one
 * that did. So the note about a machine with no file to read would be invisible on exactly the
 * machine this channel was built for — and no other stream fixes it, because a worker's two streams
 * are both dropped: that was measured, not assumed, by printing to each of them from a passing test
 * under `--parallel` and finding neither line in the run's output.
 *
 * What crosses a worker boundary is a file. So when the environment names one — `Notes::SINK`, named
 * by the program about to run the suite, which is the program that will read it back — the note is
 * written into it as well. Written *as well*, and not instead: the reading's voice is the same in
 * both places, the terminal keeps working for the run that has no sink, and no reader can be shown a
 * line twice, because every one of them collects sentences into a set. A sink that cannot be written
 * to costs nothing for the same reason — the note is already where it was going anyway.
 *
 * A NOTE IS NOT A FINDING, AND NOT AN EXCUSE
 * ------------------------------------------
 * Nothing here can turn a red run green: a finding is a disagreement about this package, and a note
 * is a question that could not be put. What it costs is stated in the note's own words, and what it
 * prevents is the one thing a skip can do silently — be read as agreement. So the readings that
 * write here are held to it by tests: a fixture with no machine file has to come back with a note
 * naming the file, and a fixture with one has to come back without it, which is what makes this a
 * channel rather than a comment.
 *
 * @guards-index support
 */
final class Notes
{
    /**
     * What a printed note starts with, read by whoever lifts notes out of a run's output.
     *
     * One place, because the producer and the reader are different programs: the marker is written
     * by a reading inside the suite and matched by `bin/checks.php` in the gate, and two copies of
     * it would be two chances for a note to be printed in a shape nobody looks for.
     */
    public const string MARKER = '[NOTE]';

    /**
     * The variable a program names its sink in, when it is about to run the suite itself.
     *
     * Read here and quoted in `bin/checks.php`, which has to spell it out for the trees it is pointed
     * at: the gate runs against fixtures with no `vendor/`, where this class cannot be loaded at all.
     * What keeps the two spellings one is a test rather than a comment — the planted check in
     * `tests/Unit/Gate/ChecksTest.php` writes to the variable it is handed, and the summary has to
     * carry the line back.
     */
    public const string SINK = 'RESPONSE_COMPRESSION_NOTES';

    /**
     * The notes, in the order they were made, with the repeat of one not kept.
     *
     * A reading asks its question once per token it is asked about, so the same sentence arrives
     * dozens of times; a channel that kept every one would print a paragraph about the machine.
     *
     * @var list<array{reading: string, note: string}>
     */
    private static array $notes = [];

    /**
     * A reading declining to ask something, in its own words.
     *
     * The reading names itself as its class, and the label a reader sees is the last piece of that
     * name: a reading that had to spell out a short name of its own would be a second name to keep
     * in step with the first.
     */
    public static function add(string $reading, string $note): void
    {
        $label = self::label($reading);

        foreach (self::$notes as $held) {
            if ($held['reading'] === $label && $held['note'] === $note) {
                return;
            }
        }

        self::$notes[] = ['reading' => $label, 'note' => $note];

        // The same sentence into the file a runner named, where there is one. The write is not
        // allowed to decide anything about the terminal half: a note that a sink took is still a note
        // this process will hand its own harness, and a sink that refused it is not a special case to
        // be reported here — neither reader is the authority on the other.
        $sink = self::sink();

        if ($sink !== null) {
            @file_put_contents($sink, self::line($label, $note).PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * Everything said so far, as the lines a harness prints. Nothing is forgotten.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(
            static fn (array $note): string => self::line($note['reading'], $note['note']),
            self::$notes,
        );
    }

    /**
     * The lines this process still owes the terminal, with the channel emptied.
     *
     * A harness prints these after each test, so a note is printed by the test that made it rather
     * than by whichever test happens to run last. A sink does not change that: a run is told
     * everywhere it can be heard, and whoever reads both places collects sentences rather than lines.
     *
     * @return list<string>
     */
    public static function take(): array
    {
        $lines = self::all();
        self::$notes = [];

        return $lines;
    }

    /** The whole channel, forgotten. A test that is about the channel starts from here. */
    public static function clear(): void
    {
        self::$notes = [];
    }

    /** The line one note is printed as, in the one place both of its readers can agree on. */
    private static function line(string $reading, string $note): string
    {
        return self::MARKER.' '.$reading.': '.$note;
    }

    /**
     * The file a program named, if it named one and this process can see the variable at all.
     *
     * Asked at the moment of writing rather than remembered, because the variable is set by whoever
     * starts the run and a test that arranges a sink is setting the same thing a runner does.
     */
    private static function sink(): ?string
    {
        $path = getenv(self::SINK);

        return $path === false || $path === '' ? null : $path;
    }

    /** The last piece of a class name, which is the name a reader knows a reading by. */
    private static function label(string $reading): string
    {
        $short = strrchr($reading, '\\');

        return $short === false ? $reading : substr($short, 1);
    }
}
