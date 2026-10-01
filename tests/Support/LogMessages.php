<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;
use Uak35\ResponseCompression\Support\Diagnostic;

/**
 * The diagnostics the compression middleware writes, each declared once with a code it logs under.
 *
 * WHY THIS EXISTS
 * ---------------
 * Two of the middleware's skip checks sat next to each other and wrote the same sentence, so an
 * unsuccessful response was recorded as a binary file — in the one output `enable_logging` exists to
 * produce, which is where anyone looks to find out why a response was left alone. It was found by
 * reading the file and fixed by hand, which is the kind of fix that comes back.
 *
 * The middleware no longer holds the text of a line at all: a branch names a
 * `Uak35\ResponseCompression\Support\Diagnostic`, that reason's sentence is written once, and every
 * line the log holds carries the reason's code in front of it. That closed most of the hole by
 * construction — a branch cannot write a sentence of its own, and two lines can never be read as one
 * another — and what is left is what this reads: the two ways the one file can still be written
 * wrong.
 *
 *   - **two cases with one code.** The code is written by hand, because the name a log wants is not
 *     the case name spelled differently by a rule two people would agree on, and nothing in PHP
 *     refuses two cases of one backed enum the same value: the error only arrives when the enum is
 *     asked for a case by value, which this package never does.
 *   - **two cases with one sentence.** Naming a reason is a decision; naming two reasons that say
 *     the same words is the defect above, one file along.
 *
 * WHY IT READS THE FILE RATHER THAN ASKING THE TYPE
 * -------------------------------------------------
 * The cases and their sentences are the file, so `Diagnostic::cases()` would answer the same question
 * more cheaply. It is not asked, because a reading that asks the type cannot be *mutated*: the class
 * is loaded once in the process running these tests, so a copy of the tree with a collision planted
 * in it would be read as the tree it was loaded from — and the mutation harness, which plants one
 * break at a time and requires the guard to name it, would be checking nothing.
 *
 * The text is read instead, one declaration and one `match` arm to a line, which is the shape the
 * file is written in. A line this cannot read is reported rather than skipped: a case with no
 * sentence, an arm with no case, and a file with neither throw. A reader whose answer to "did you
 * read anything?" is "no" agrees with a file it never understood — and `tests/Unit/Middleware/
 * LogMessagesTest.php` holds it to the other side as well, by requiring the codes it read to be the
 * codes the type declares.
 *
 * @guards-index reading
 */
final class LogMessages
{
    /** The file whose reasons carry the diagnostics, one sentence each. */
    public const string SOURCE = 'src/Support/Diagnostic.php';

    /** What every line starts with, so the prefix is not part of a sentence. */
    public const string PREFIX = Diagnostic::PREFIX;

    /** A case declaration, one to a line: `case Name = 'code';`. */
    private const string CASE = '~^\s*case ([A-Za-z_][A-Za-z0-9_]*) = \'((?:[^\'\\\\]|\\\\.)*)\';\s*$~';

    /** One arm of the `match` that writes the sentences, one to a line: `self::Name => 'sentence',`. */
    private const string ARM = '~^\s*self::([A-Za-z_][A-Za-z0-9_]*) => \'((?:[^\'\\\\]|\\\\.)*)\',\s*$~';

    /**
     * The shipped type, read from the package root.
     *
     * The file is named here rather than in the test so that one place decides which file the guard
     * is about, and the test can be given a fixture instead by calling `declaredIn()` directly.
     */
    public static function source(): string
    {
        return (string) file_get_contents(packageRoot().'/'.self::SOURCE);
    }

    /**
     * One entry per reason the file declares: its case, its code and its sentence.
     *
     * @return list<array{case: string, code: string, template: string}>
     *
     * @throws RuntimeException when the text declares no case, writes no sentence, or leaves one of
     *                          the two on its own — each a way this could report agreement with a
     *                          file it never understood
     */
    public static function declaredIn(string $source): array
    {
        $codes = [];
        $templates = [];

        foreach (preg_split('~\R~', $source) ?: [] as $line) {
            if (preg_match(self::CASE, $line, $found) === 1) {
                $codes[$found[1]] = self::literal($found[2]);
            } elseif (preg_match(self::ARM, $line, $found) === 1) {
                $templates[$found[1]] = self::literal($found[2]);
            }
        }

        if ($codes === [] || $templates === []) {
            throw new RuntimeException(sprintf(
                '%s declares %d case(s) and writes %d sentence(s) to read.',
                self::SOURCE,
                count($codes),
                count($templates),
            ));
        }

        $declared = [];

        foreach ($codes as $case => $code) {
            if (! isset($templates[$case])) {
                throw new RuntimeException(sprintf('%s declares %s and says nowhere what it writes.', self::SOURCE, $case));
            }

            $declared[] = ['case' => $case, 'code' => $code, 'template' => $templates[$case]];
        }

        foreach (array_keys($templates) as $case) {
            if (! isset($codes[$case])) {
                throw new RuntimeException(sprintf('%s writes a sentence for %s, which it does not declare.', self::SOURCE, $case));
            }
        }

        return $declared;
    }

    /**
     * The reasons that can still be read as one another, one entry per collision.
     *
     * Codes first and sentences second, each in the order the file declares them, so a report of the
     * same collision reads the same way twice.
     *
     * @param  list<array{case: string, code: string, template: string}>  $declared
     * @return list<string>
     */
    public static function duplicates(array $declared): array
    {
        $byCode = [];
        $byTemplate = [];

        foreach ($declared as $reason) {
            $byCode[$reason['code']][] = $reason['case'];
            $byTemplate[$reason['template']][] = $reason['case'];
        }

        $duplicates = [];

        foreach ($byCode as $code => $cases) {
            if (count($cases) > 1) {
                $duplicates[] = sprintf('%s both carry the code %s', implode(' and ', $cases), $code);
            }
        }

        foreach ($byTemplate as $template => $cases) {
            if (count($cases) > 1) {
                $duplicates[] = sprintf('%s write the same line: %s', implode(' and ', $cases), $template);
            }
        }

        return $duplicates;
    }

    /**
     * What a single-quoted PHP literal stands for, where `\\` and `\'` are the only escapes.
     *
     * The file is read as text, so its escapes are still in it: a sentence holding an apostrophe
     * arrives as `It\'s`, which is not what the log would say.
     */
    private static function literal(string $source): string
    {
        return str_replace(['\\\\', "\\'"], ['\\', "'"], $source);
    }
}
