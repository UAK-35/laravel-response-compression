<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * The numbers `docs/guards.md` states, read out of it and checked against the code that produces them.
 *
 * WHY THIS EXISTS
 * ---------------
 * The two lists of files on the index are a rendering now: the directory is the list, a file says
 * which list it is in, and `bin/index.php` writes the rows — so a file that moved cannot leave the
 * page describing a directory that is no longer there. The numbers in the prose were left out of
 * that, and a number in a sentence is the same defect one layer up: "nine checks in one process" is
 * a fact about the `$checks` array in `bin/checks.php`, "100.0% per file for `src/` and a ratchet
 * for each script" is a fact about two constants in `bin/coverage.php`, and the count of guards the
 * page is about is a fact about the table above it. Each was true the day it was typed, and nothing
 * would have told the sentence if the code moved — an eleventh check, a floor raised, a row added.
 *
 * So each number is read out of the document it is stated in, and compared with the code that
 * produces it: the check keys are parsed out of `bin/checks.php`, the floors out of
 * `bin/coverage.php`, and the counts out of the directory, the rendered lists and the table itself.
 * A number is stated once and verified rather than repeated and believed, which is the same choice
 * `ConfigClaims` makes about a claim and `GuardIndex` makes about a row.
 *
 * THE SAME CLAIM, SAID ELSEWHERE
 * ------------------------------
 * `N checks in one process` is this package's own phrase for the gate's size, and it is written in
 * five places — this page, `AGENTS.md`, `README.md`, the gate command and the workflow — which are
 * one claim rather than five facts. The phrase is checked wherever a commit would carry it, so the
 * sentence in the README cannot go on saying nine while the gate runs ten. The `CHANGELOG` is not
 * read, because it is a record of the past by construction: a number it wrote about a release that
 * was cut is history rather than a claim about today's tree.
 *
 * WHAT IT DOES NOT DO
 * -------------------
 * It does not rewrite. `bin/index.php` owns tables, because a table is a rendering of a directory;
 * a number inside a sentence is prose, and a program that rewrote sentences would be an editor of
 * arguments rather than a reader of facts. A stale number is therefore reported with the file, the
 * line and the value the code produces, and the sentence is corrected by hand — which is what
 * "verified rather than trusted" means here: the number is still written down, and it cannot be
 * wrong without the suite saying so.
 *
 * Nor does it check every number in the repository. It checks the ones the index states, because
 * the index is the page this package keeps its own account of itself on, and the phrase that names
 * the gate's size wherever it appears. A count in a release note, a version in a runbook and a line
 * count in a changelog are other questions with other answers.
 *
 * @guards-index reading
 */
final class IndexNumbers
{
    /** The document whose numbers are checked: the index of the guards. */
    public const string DOCUMENT = 'docs/guards.md';

    /** The program that defines the checks, and therefore the gate's size. */
    public const string CHECKS = 'bin/checks.php';

    /** The program that defines the floors. */
    public const string COVERAGE = 'bin/coverage.php';

    /** The file whose header states how many `docs/` records there are. */
    public const string RECORDS = 'tests/Support/Docs.php';

    /**
     * The number words a document may state a count with.
     *
     * Spelled rather than numeric because that is how this package writes a count in a sentence —
     * "nine checks", "a ratchet for each script" — and a reader would otherwise have to accept
     * either spelling as a claim about a different fact. Above twenty the digits are used.
     */
    private const array NUMBER_WORDS = [
        0 => 'zero',
        1 => 'one',
        2 => 'two',
        3 => 'three',
        4 => 'four',
        5 => 'five',
        6 => 'six',
        7 => 'seven',
        8 => 'eight',
        9 => 'nine',
        10 => 'ten',
        11 => 'eleven',
        12 => 'twelve',
        13 => 'thirteen',
        14 => 'fourteen',
        15 => 'fifteen',
        16 => 'sixteen',
        17 => 'seventeen',
        18 => 'eighteen',
        19 => 'nineteen',
        20 => 'twenty',
    ];

    /**
     * The checks the gate runs, in the order `bin/checks.php` declares them.
     *
     * Read out of the program rather than asked of it: running `bin/checks.php --list` would
     * measure the package, and the question here is a fact about the file — how many entries its
     * `$checks` array has. The keys are the top-level ones, at the array's own indentation, which
     * is what keeps the nested `title`, `skip` and `run` of each check from being read as a check.
     *
     * @return list<string>
     */
    public static function checks(string $root): array
    {
        $source = self::read($root, self::CHECKS);

        preg_match_all("/^ {4}'([a-z][a-z0-9-]*)' => \\[$/m", $source, $matches);

        $checks = array_values(array_unique($matches[1]));

        // A parse that found nothing, or one entry, is not a gate: it is a changed shape, and a
        // reading that answered "one check" would hold every document to a number it made up.
        if (count($checks) < 2) {
            throw new RuntimeException(sprintf(
                '%s: its `$checks` array reads as %d check(s), so the gate this reads the number out of is not there.',
                self::CHECKS,
                count($checks),
            ));
        }

        return $checks;
    }

    /**
     * The floor every file is held to: one for `src/`, and the recorded ratchet for each script.
     *
     * @return array{source: float, scripts: array<string, float>}
     */
    public static function floors(string $root): array
    {
        $source = self::read($root, self::COVERAGE);

        $scripts = [];
        $block = [];

        if (preg_match('/const\s+FLOORS\s*=\s*\[(.*?)\];/s', $source, $block) === 1) {
            preg_match_all("/'([^']+)'\s*=>\s*([0-9]+(?:\.[0-9]+)?)/", $block[1], $pairs);

            foreach ($pairs[1] as $index => $file) {
                $scripts[$file] = (float) $pairs[2][$index];
            }
        }

        $floor = [];

        if (preg_match('/const\s+SOURCE_FLOOR\s*=\s*([0-9]+(?:\.[0-9]+)?)/', $source, $floor) !== 1) {
            throw new RuntimeException(sprintf(
                '%s: there is no `SOURCE_FLOOR` constant, so the floor every `src/` file is held to cannot be read out of it.',
                self::COVERAGE,
            ));
        }

        if ($scripts === []) {
            throw new RuntimeException(sprintf(
                '%s: the `FLOORS` array reads as nothing, so a document that states a floor per script would be checked against an empty list.',
                self::COVERAGE,
            ));
        }

        return ['source' => (float) $floor[1], 'scripts' => $scripts];
    }

    /**
     * The counts the index is about: the guards it records, the two lists of files below it, and
     * the `docs/` records the package ships.
     *
     * @return array{guards: int, readings: int, support: int, files: int, records: int}
     */
    public static function counts(string $root): array
    {
        $root = self::root($root);
        $lists = GuardIndex::rendered($root);

        return [
            'guards' => count(GuardIndex::rows(self::document($root))),
            'readings' => count($lists['reading']),
            'support' => count($lists['support']),
            'files' => count(GuardIndex::readings($root)),
            'records' => count(Docs::records($root)),
        ];
    }

    /**
     * The number, as a count is written in a sentence: "nine" where the word exists, the digits
     * above it.
     */
    public static function word(int $number): string
    {
        return self::NUMBER_WORDS[$number] ?? (string) $number;
    }

    /**
     * Every number the index states that no longer matches the code that produces it.
     *
     * Empty is the whole of the reading when the document and the code agree, which is the half
     * every assertion is made on — and a claim that is no longer *stated* is a finding too, because
     * a number removed from the page is a number nothing holds any more. That is the difference
     * between this and a scan: a scan that found nothing answers the same thing as a scan that was
     * never looking.
     *
     * @return list<string>
     */
    public static function drift(string $root): array
    {
        $root = self::root($root);
        $markdown = self::document($root);
        $checks = self::checks($root);
        $floors = self::floors($root);
        $counts = self::counts($root);

        $findings = [
            ...self::gate($markdown, $checks),
            ...self::statedCounts($markdown, $counts),
            ...self::statedFloors($markdown, $floors),
            ...self::statedRecords($root, $counts['records']),
            ...self::phrase($root, $checks),
        ];

        return array_values(array_unique($findings));
    }

    /**
     * The row that says how many checks the gate runs.
     *
     * @param  list<string>  $checks
     * @return list<string>
     */
    private static function gate(string $markdown, array $checks): array
    {
        $row = array_find(GuardIndex::rows($markdown), fn ($candidate): bool => trim($candidate['guard'], '` ') === self::CHECKS);
        if ($row === null) {
            return [sprintf(
                '%s: the row for `%s` is gone, and it is the row that says how many checks the gate runs.',
                self::DOCUMENT,
                self::CHECKS,
            )];
        }

        if (preg_match('/^(\w+)\s+checks?\s+in\s+one\s+process\b/', $row['catches'], $matches) !== 1) {
            return [sprintf(
                '%s:%d: the `%s` row no longer says how many checks the gate runs, so nothing holds the number to the program.',
                self::DOCUMENT,
                $row['line'],
                self::CHECKS,
            )];
        }

        if (self::isCount($matches[1], count($checks))) {
            return [];
        }

        return [sprintf(
            '%s:%d: the row says "%s checks in one process", and %s defines %d (%s).',
            self::DOCUMENT,
            $row['line'],
            $matches[1],
            self::CHECKS,
            count($checks),
            self::word(count($checks)),
        )];
    }

    /**
     * The counts the index states about itself: the guards it records, and the two lists of files
     * below the table.
     *
     * @param  array{guards: int, readings: int, support: int, files: int, records: int}  $counts
     * @return list<string>
     */
    private static function statedCounts(string $markdown, array $counts): array
    {
        $findings = self::stated(
            $markdown,
            '/^The\s+table\s+records\s+\*{0,2}(\d+)\*{0,2}\s+guards\b/m',
            'how many guards the table records',
            (string) $counts['guards'],
        );

        if (preg_match(
            '/^The\s+two\s+lists\s+name\s+\*{0,2}(\d+)\*{0,2}\s+readings\s+and\s+\*{0,2}(\d+)\*{0,2}\s+files\s+that\s+are\s+not\s+guards/m',
            $markdown,
            $matches,
            PREG_OFFSET_CAPTURE,
        ) !== 1) {
            $findings[] = sprintf(
                '%s: no longer states how many readings and how many files that are not guards the two lists hold, so nothing holds those counts to the directory.',
                self::DOCUMENT,
            );
        } else {
            $readings = (int) $matches[1][0];
            $support = (int) $matches[2][0];

            if ($readings !== $counts['readings'] || $support !== $counts['support']) {
                $findings[] = sprintf(
                    '%s:%d: says "%d readings and %d files that are not guards", and the directory holds %d and %d.',
                    self::DOCUMENT,
                    self::lineOf($markdown, $matches[0][1]),
                    $readings,
                    $support,
                    $counts['readings'],
                    $counts['support'],
                );
            }
        }

        return [
            ...$findings,
            ...self::stated(
                $markdown,
                '/\b\*{0,2}(\d+)\*{0,2}\s+files\s+in\s+`tests\/Support\/`\s+in\s+all\b/',
                'how many files `tests/Support/` holds',
                (string) $counts['files'],
            ),
        ];
    }

    /**
     * The floors the index states: the one every `src/` file is held to, and the ratchet recorded
     * for each script.
     *
     * @param  array{source: float, scripts: array<string, float>}  $floors
     * @return list<string>
     */
    private static function statedFloors(string $markdown, array $floors): array
    {
        $findings = self::stated(
            $markdown,
            '/\bThe\s+floors\s+are\s+\*{0,2}(\d+(?:\.\d+)?)\*{0,2}%?\s+per\s+file\s+for\s+`src\//',
            'the floor every `src/` file is held to',
            self::decimal($floors['source']),
        );

        preg_match_all(
            '/`(bin\/[a-z0-9-]+\.php)`\s+at\s+\*{0,2}(\d+(?:\.\d+)?)\*{0,2}%/',
            $markdown,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        $stated = [];

        foreach ($matches as $match) {
            $stated[$match[1][0]] = ['floor' => (float) $match[2][0], 'line' => self::lineOf($markdown, $match[0][1])];
        }

        foreach ($floors['scripts'] as $file => $floor) {
            if (! array_key_exists($file, $stated)) {
                $findings[] = sprintf(
                    '%s: states no floor for `%s`, and %s records one at %s%%.',
                    self::DOCUMENT,
                    $file,
                    self::COVERAGE,
                    self::decimal($floor),
                );

                continue;
            }

            if ($stated[$file]['floor'] !== $floor) {
                $findings[] = sprintf(
                    '%s:%d: says `%s` is at %s%%, and %s records %s%%.',
                    self::DOCUMENT,
                    $stated[$file]['line'],
                    $file,
                    self::decimal($stated[$file]['floor']),
                    self::COVERAGE,
                    self::decimal($floor),
                );
            }
        }

        foreach (array_diff_key($stated, $floors['scripts']) as $file => $entry) {
            $findings[] = sprintf(
                '%s:%d: states a floor for `%s` at %s%%, and %s records none.',
                self::DOCUMENT,
                $entry['line'],
                $file,
                self::decimal($entry['floor']),
                self::COVERAGE,
            );
        }

        return $findings;
    }

    /**
     * How many `docs/` records there are, as the reading that names them states it.
     *
     * @return list<string>
     */
    private static function statedRecords(string $root, int $records): array
    {
        $source = self::read($root, self::RECORDS);

        if (preg_match(
            '/records\s+are\s+the\s+package\'s\s+explanation\s+of\s+itself:\s+(\w+)\s+of\s+them/',
            $source,
            $matches,
            PREG_OFFSET_CAPTURE,
        ) !== 1) {
            return [sprintf(
                '%s: no longer states how many `docs/` records the package ships, so the count is unchecked.',
                self::RECORDS,
            )];
        }

        if (self::isCount($matches[1][0], $records)) {
            return [];
        }

        return [sprintf(
            '%s:%d: says "%s of them", and `docs/` holds %d (%s).',
            self::RECORDS,
            self::lineOf($source, $matches[0][1]),
            $matches[1][0],
            $records,
            self::word($records),
        )];
    }

    /**
     * The gate's size stated as this package's own phrase, wherever a commit would carry it.
     *
     * The phrase is the claim, so the file it is written in does not matter: the README, the
     * canonical rules, the gate's command and the workflow are the same sentence in four places,
     * and each of them should go red on the day the gate gains a check. `docs/guards.md` is left to
     * `gate()`, which reads it as a row rather than as a sentence; the `CHANGELOG` is left alone
     * because a released note is history; and `tests/` is left alone because a test is a fixture
     * rather than a claim — a case that says "eleven checks in one process" to prove this reading
     * notices is not a statement about the gate, and neither is the pattern that reads it.
     *
     * @param  list<string>  $checks
     * @return list<string>
     */
    private static function phrase(string $root, array $checks): array
    {
        $findings = [];
        $number = count($checks);

        foreach (MachinePaths::files($root) as $file) {
            if ($file === self::DOCUMENT || str_starts_with($file, 'CHANGELOG.md') || str_starts_with($file, 'tests/')) {
                continue;
            }

            $contents = @file_get_contents($root.'/'.$file);

            if ($contents === false) {
                continue;
            }

            if (preg_match_all(
                '/([A-Za-z0-9-]+)\s+checks?\s+in\s+one\s+process/i',
                $contents,
                $matches,
                PREG_OFFSET_CAPTURE,
            ) === 0) {
                continue;
            }

            foreach ($matches[1] as [$token, $offset]) {
                if (self::isCount($token, $number)) {
                    continue;
                }

                $findings[] = sprintf(
                    '%s:%d: says "%s checks in one process", and %s defines %d (%s).',
                    $file,
                    self::lineOf($contents, $offset),
                    $token,
                    self::CHECKS,
                    $number,
                    self::word($number),
                );
            }
        }

        return $findings;
    }

    /**
     * A claim with one number in it: a finding when it is no longer stated, a finding when it
     * states something other than what the code produces, and nothing when the two agree.
     *
     * @return list<string>
     */
    private static function stated(string $markdown, string $pattern, string $what, string $expected): array
    {
        if (preg_match($pattern, $markdown, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return [sprintf(
                '%s: no longer states %s, so nothing holds it to the code that produces it.',
                self::DOCUMENT,
                $what,
            )];
        }

        if ((float) $matches[1][0] === (float) $expected) {
            return [];
        }

        return [sprintf(
            '%s:%d: says "%s" for %s, and the code produces %s.',
            self::DOCUMENT,
            self::lineOf($markdown, $matches[0][1]),
            $matches[1][0],
            $what,
            $expected,
        )];
    }

    /**
     * Whether a token is the count as written: the digits, or the number word.
     *
     * @return list<string>
     */
    private static function tokens(int $number): array
    {
        $tokens = [(string) $number];

        if (isset(self::NUMBER_WORDS[$number])) {
            $tokens[] = self::NUMBER_WORDS[$number];
        }

        return $tokens;
    }

    /** Whether a document states a count with this token. */
    private static function isCount(string $token, int $number): bool
    {
        return in_array(strtolower($token), self::tokens($number), true);
    }

    /** A whole percentage, as the document and the constants spell it — `100.0`, `54`. */
    private static function decimal(float $value): string
    {
        return $value === floor($value) ? (string) (int) $value : (string) $value;
    }

    /** The line an offset falls on, in the file as it was written rather than as it was read. */
    private static function lineOf(string $text, int $offset): int
    {
        return substr_count($text, "\n", 0, max($offset, 1)) + 1;
    }

    /** One file this reading is asked about, or a complaint naming it. */
    private static function read(string $root, string $file): string
    {
        $contents = @file_get_contents(self::root($root).'/'.$file);

        if ($contents === false) {
            throw new RuntimeException(sprintf(
                '%s is not here, so the number it defines cannot be read out of it.',
                $file,
            ));
        }

        return $contents;
    }

    /** The index, read as text. */
    private static function document(string $root): string
    {
        return self::read($root, self::DOCUMENT);
    }

    /** The package root in the form every path read here is compared in. */
    private static function root(string $root): string
    {
        return rtrim(str_replace('\\', '/', $root), '/');
    }
}
