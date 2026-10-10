<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * `docs/guards.md` read as a claim about a directory.
 *
 * WHY THIS EXISTS
 * ---------------
 * The index is where a guard is put in front of whoever adds the next one: the table of what is
 * kept, and the prose saying where each of them came from. One direction of it was already watched
 * and one was not. A row that links a test which has since been renamed is caught, because a row's
 * link is a link and the docs-link guard resolves every one of them. A guard added with *no row at
 * all* is not caught by anything, and cannot be: no test knew what a guard is, so a new one could
 * arrive unrecorded and read as though it had always been part of the list.
 *
 * So the table is read as a claim about a directory. Every row has to carry a link that resolves to
 * a file the repository has, and every file in `tests/Support/` has to be named in the document —
 * a reading in the section it is listed in, or support in the section saying it is not a guard.
 * A new file in that directory therefore costs one line of the index, and that line is the decision
 * the index exists to record.
 *
 * THE GUARDS THEMSELVES
 * ---------------------
 * That was the guard's *reading* side, and the two lists below it. The guards table was left by
 * hand — what a guard refuses is an argument, so it was reasoned that the argument could not be
 * rendered. It can be sourced, though, and the source is the file the guard lives in: a guard is
 * declared in the file its row names, with one comment block carrying `@guards-index guard <name>`
 * and the first sentence of that block saying what it catches. The file's own path is the `Where`
 * cell, so a guard that moves moves its row with it. The block is read in whichever comment the
 * file writes in — a PHP banner or docblock, a run of `#` lines, or an XML comment — because a
 * guard lives wherever it lives rather than only in a `.php` file.
 *
 * So the table is rendered like the two lists, from the files a commit would carry. A guard
 * arrives with no declaration and is a finding; a declaration names no file that is here and is a
 * finding; a declared name that is not in the table is a finding. And a test under `tests/Unit/`
 * still declares itself: `@guards-index guard <name>` names its own row, `@guards-index test` says
 * the table is not about it, and a test that says nothing is refused rather than left out — an
 * ordinary test that stayed silent and a guard that forgot to speak are the same file read from the
 * outside, and the second is the one that would land unrecorded.
 *
 * THE TWO LISTS OF FILES
 * ----------------------
 * The section below the table is where the directory is written down — the readings the guards
 * are built from, and the files beside them that are fixtures rather than guards — and those two
 * lists were prose: a name that moved had to be moved by hand in three places, and a file added
 * to the directory was in no list until somebody remembered it.
 *
 * So both are rendered from the files themselves. A file in `tests/Support/` carries one line in
 * its own header, `@guards-index reading` or `@guards-index support`, and the first sentence of
 * that header is the words the table prints beside it. The directory is the list of files, the
 * file is the decision about itself, and the document is a rendering of the two — `bin/index.php`
 * writes it, `drift()` says where it has stopped agreeing with it, and the suite asserts that the
 * answer is empty. A file that declares nothing, declares a list the document does not have, or
 * has no sentence to quote is refused rather than left out, because "not in the list" and "in it
 * and correct" are the two answers this exists to tell apart.
 *
 * WHAT IT DOES NOT DO
 * -------------------
 * It does not read a link anywhere else, and it does not decide what a guard *is* past the files
 * that declare one. What each guard refuses is written once, in the guard's own file, and the
 * document is a rendering of it; what is enforced is only that the document and the files agree.
 * Nor is the header taken on trust: a table whose columns moved is a table this would read wrongly
 * rather than not at all, so its header is asserted before a row is read.
 *
 * @guards-index reading
 */
final class GuardIndex
{
    /**
     * The heading the guards are listed under. The table under it is the one read here; the
     * sections that follow it are the document's other business.
     */
    public const string TABLE = '## Kept';

    /**
     * The table's header, in the order a row is read: the guard, what it catches, and where it is.
     *
     * @var list<string>
     */
    public const array COLUMNS = ['Guard', 'What it catches', 'Where'];

    /** The document the two lists of files are written in. */
    public const string DOCUMENT = 'docs/guards.md';

    /** Where the files the lists name live, as the document's own links spell it. */
    public const string DIRECTORY = 'tests/Support';

    /**
     * The two lists of files, by the word a file declares itself with: the heading each is written
     * under, and the columns a row of it is read by.
     *
     * @var array<string, array{heading: string, columns: list<string>}>
     */
    public const array LISTS = [
        'reading' => ['heading' => '## The readings the guards are built from', 'columns' => ['Reading', 'What it reads']],
        'support' => ['heading' => '## Support that is not a guard', 'columns' => ['File', 'What it is']],
    ];

    /**
     * The line a file declares itself with, in its own header.
     *
     * In the file rather than in a list here on purpose: a list of names is the thing that goes
     * stale, and the file knows which of the two it is. The document's sections are the headings
     * above, so this word and one of those keys are the whole vocabulary.
     */
    public const string DECLARATION = '@guards-index';

    /** The directory of tests the `## Kept` table is a claim about. */
    public const string TESTS = 'tests/Unit';

    /** A guard: `@guards-index guard <name>` declares the row the file's argument is about. */
    public const string GUARD = 'guard';

    /** A test of the code: what the table is not about, and what a guard's reading is not. */
    public const string TEST = 'test';

    /**
     * The two things a test under `tests/Unit/` can declare itself to be.
     *
     * @var list<string>
     */
    public const array KINDS = [self::GUARD, self::TEST];

    /**
     * The declaration line, read for the word that follows it and, for a guard row, the name the
     * row carries. The characters before the declaration are the comment decoration of whichever
     * style the file writes in — a `|`-ruled banner, a `*` docblock, a `#` run, an XML comment — so
     * one pattern reads the declaration out of all of them.
     *
     * The line's end is `[ \t\r]*$` rather than `[ \t]*$`, and that one `\r` is the difference
     * between an index that reads a Windows checkout and one that refuses it. Every file here is
     * read as the bytes on disk, while `* text=auto` in `.gitattributes` and a `core.autocrlf` of
     * `true` put CRLF in a working tree — so a checkout that is byte-identical to `HEAD`, and that
     * `git status` calls clean, is one where `$` does not match in front of the `\r`. The whole of
     * `docs/guards.md` went unreadable on this machine that way, with five test files failing on a
     * file that says exactly what it is supposed to say.
     */
    private const string DECLARATION_PATTERN = '/^[\s\/*|#<!-]*@guards-index\s+(\S+)(?:[ \t]+(.+?))?[ \t\r]*$/m';

    /**
     * The directories a filesystem walk of a planted tree leaves alone: a checkout's history and
     * the installed dependencies are not where a guard is declared, and reading them would make
     * the walk slower without making it more true.
     *
     * @var list<string>
     */
    private const array WALK_PAST = ['.git', 'vendor', 'node_modules', '__pycache__'];

    /**
     * The table's rows, in the order the document has them.
     *
     * @return list<array{guard: string, catches: string, where: string, line: int}>
     *
     * @throws RuntimeException when there is no table, or when its header is not the one a row is
     *                          read by — the reading having nothing to read is the one failure a
     *                          guard must not report as a pass
     */
    public static function rows(string $markdown): array
    {
        $table = self::table($markdown, self::TABLE);

        if ($table['header'] === []) {
            throw new RuntimeException('There is no `'.self::TABLE.'` table in the index: a guard with nothing to read is a guard that reports every tree as recorded.');
        }

        $header = array_slice($table['header'], 0, count(self::COLUMNS));

        if ($header !== self::COLUMNS) {
            throw new RuntimeException(sprintf(
                'The guard table reads `| %s |`, not `| %s |`: a row is read by its columns, so a table whose columns moved would be read wrongly rather than not at all.',
                implode(' | ', $table['header']),
                implode(' | ', self::COLUMNS),
            ));
        }

        return array_map(static fn (array $row): array => [
            'guard' => $row['cells'][0] ?? '',
            'catches' => $row['cells'][1] ?? '',
            'where' => $row['cells'][2] ?? '',
            'line' => $row['line'],
        ], $table['rows']);
    }

    /**
     * The header the table actually has, so a table whose columns moved can be reported rather than
     * read wrongly. Empty when the document has no such table.
     *
     * @return list<string>
     */
    public static function header(string $markdown): array
    {
        return self::table($markdown, self::TABLE)['header'];
    }

    /**
     * Every file in `tests/Support/`, as a path from the package root.
     *
     * The directory is read rather than listed here: a list of names is the thing that goes stale,
     * and the point of the guard is that the document and the directory are compared, not that a
     * second copy of the directory is kept beside it.
     *
     * @return list<string>
     */
    public static function readings(string $root): array
    {
        $root = self::root($root);
        $files = [];

        foreach (glob($root.'/tests/Support/*.php') ?: [] as $file) {
            $files[] = Docs::relative($root, $file);
        }

        sort($files);

        return $files;
    }

    /**
     * Every test under `tests/Unit/`, as a path from the package root.
     *
     * @return list<string>
     */
    public static function tests(string $root): array
    {
        return self::php(self::root($root), self::TESTS);
    }

    /**
     * Every inline code span, which is how the document names a file it is not linking.
     *
     * Read as written and not resolved: a span is a name, and the two forms that count are the path
     * from the package root and a link that resolves to the same file. Both are compared by the
     * caller, which is where the two are known to mean the same thing.
     *
     * @return list<string>
     */
    public static function spans(string $markdown): array
    {
        preg_match_all('/`([^`\n]+)`/', $markdown, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Every file in `tests/Support/`, by the list it declares and the sentence the table quotes.
     *
     * The file is the subject here rather than the document: which list a file belongs to and what
     * it is are facts about the file, and reading them out of its own header is what makes the
     * document a rendering rather than a second copy. The first sentence of that header is the
     * words the table prints, so a file's own opening line is the only place the description of it
     * exists — there is nothing beside it to drift from.
     *
     * @return array<string, array{file: string, section: string, summary: string, line: int}>
     *
     * @throws RuntimeException when a file declares no list, declares one this package does not
     *                          have, or has nothing to quote — a file nothing can be rendered for
     *                          is the one case that must not come back as an unchanged document
     */
    public static function declared(string $root): array
    {
        $root = self::root($root);
        $entries = [];

        foreach (glob($root.'/'.self::DIRECTORY.'/*.php') ?: [] as $file) {
            $contents = (string) file_get_contents($file);
            $file = Docs::relative($root, $file);
            // The declaring block among all of them, and the block it was found in: a script opens
            // with a shebang, and a reading that took the first block would read `#!` rather than
            // the banner under it. A block that names a list wins over the declaration of another
            // kind a helper might carry.
            $block = null;
            $declaration = null;
            $other = null;

            foreach (self::blocks($contents) as $candidate) {
                $declared = self::declaration($contents, $candidate);

                if ($declared === null) {
                    continue;
                }

                $other ??= $declared;

                if (array_key_exists($declared['section'], self::LISTS)) {
                    $block = $candidate;
                    $declaration = $declared;

                    break;
                }
            }

            $declaration ??= $other;

            if ($declaration === null) {
                throw new RuntimeException(sprintf(
                    '%s carries no `%s` line, so it says nothing about which list it is in. Add `%s reading` or `%s support` to the header above the class — or to the banner a script opens with.',
                    $file,
                    self::DECLARATION,
                    self::DECLARATION,
                    self::DECLARATION,
                ));
            }

            if (! array_key_exists($declaration['section'], self::LISTS)) {
                throw new RuntimeException(sprintf(
                    '%s declares `%s`, and this package has no such list. The two it has are %s.',
                    $file,
                    $declaration['section'],
                    implode(' and ', array_keys(self::LISTS)),
                ));
            }

            // What the file claims about itself, against what it contains: a reading or a fixture
            // is run by the tests rather than written as one, so a test case in it is the claim
            // this refuses.
            if (self::testCase($contents)) {
                throw new RuntimeException(sprintf(
                    '%s declares itself a %s and carries a test: a reading and a fixture are run by the tests rather than written as one, so the declaration is about a kind of file this is not.',
                    $file,
                    $declaration['section'],
                ));
            }

            $summary = self::summary($block === null ? [] : self::prose($block['text']));

            if ($summary === '') {
                throw new RuntimeException(sprintf(
                    '%s declares a list and has no first sentence for the table to quote. The words beside a row are the file\'s own opening line, so the header needs one.',
                    $file,
                ));
            }

            $entries[$file] = [
                'file' => $file,
                'section' => $declaration['section'],
                'summary' => $summary,
                'line' => $declaration['line'],
            ];
        }

        ksort($entries);

        return $entries;
    }

    /**
     * Every test under `tests/Unit/`, by what it says it is.
     *
     * The declaration is where a test answers the one question the index cannot: whether it is a
     * guard, which the table has to record, or a test of the code, which the table is not about.
     * A file that declares nothing is refused rather than left out — an ordinary test that stayed
     * silent and a guard that forgot are the same file read from the outside, and the second is
     * the one that would land unrecorded.
     *
     * @return array<string, array{kind: string, line: int}>
     *
     * @throws RuntimeException when a test declares nothing, or declares something that is neither
     *                          a guard nor a test of the code
     */
    public static function guards(string $root): array
    {
        $root = self::root($root);
        $entries = [];

        foreach (self::tests($root) as $file) {
            $contents = (string) file_get_contents($root.'/'.$file);
            $declaration = null;

            // The declaring block rather than the first block: a helper's docblock can sit above
            // the banner, and a reading that stopped at the first block would find the helper and
            // refuse the test for declaring nothing. A block that names one of the two kinds wins
            // over one that does not, so a helper carrying an unrelated `@guards-index` cannot
            // decide what a test is.
            foreach (self::blocks($contents) as $block) {
                $found = self::declaration($contents, $block);

                if ($found === null) {
                    continue;
                }

                $declaration ??= $found;

                if (in_array($found['section'], self::KINDS, true)) {
                    $declaration = $found;

                    break;
                }
            }

            if ($declaration === null) {
                throw new RuntimeException(sprintf(
                    '%s carries no `%s` line, so it says nothing about whether it is a guard. Add `%s %s` and a name for a test the table records, or `%s %s` for a test of the code.',
                    $file,
                    self::DECLARATION,
                    self::DECLARATION,
                    self::GUARD,
                    self::DECLARATION,
                    self::TEST,
                ));
            }

            if (! in_array($declaration['section'], self::KINDS, true)) {
                throw new RuntimeException(sprintf(
                    '%s declares `%s`, and a test under %s/ is either a %s or a %s.',
                    $file,
                    $declaration['section'],
                    self::TESTS,
                    self::GUARD,
                    self::TEST,
                ));
            }

            if ($declaration['section'] === self::GUARD && $declaration['name'] === null) {
                throw new RuntimeException(sprintf(
                    '%s declares `%s %s` with no name, and a guard row is named: the name is what the table prints in its first cell.',
                    $file,
                    self::DECLARATION,
                    self::GUARD,
                ));
            }

            // And what the file claims about itself, against what it contains: a file that says it
            // is a guard or a test of the code is a test, and a claim nothing in the file bears out
            // is the one this refuses.
            if (! self::testCase($contents)) {
                throw new RuntimeException(sprintf(
                    '%s declares `%s %s` and carries no test: a file under %s/ that says it is a guard or a test of the code is a test, so a claim the file does not bear out is refused rather than recorded.',
                    $file,
                    self::DECLARATION,
                    $declaration['section'],
                    self::TESTS,
                ));
            }

            $entries[$file] = ['kind' => $declaration['section'], 'line' => $declaration['line']];
        }

        return $entries;
    }

    /**
     * What the two tables in the document would say, from the directory alone.
     *
     * @return array<string, array<string, string>> the section, then the file, then the words
     */
    public static function rendered(string $root): array
    {
        /** @var array<string, array<string, string>> $rows */
        $rows = [];

        foreach (array_keys(self::LISTS) as $section) {
            $rows[$section] = [];
        }

        foreach (self::declared($root) as $file => $entry) {
            $rows[$entry['section']][$file] = $entry['summary'];
        }

        return $rows;
    }

    /**
     * What the two tables in the document do say: the file each row names, and the words beside it.
     *
     * The file is read out of the row's link rather than out of its text, because the link is the
     * part a reader is sent by and the part that resolves. A row whose cell names no file in the
     * directory is kept under the words the cell holds, so that the comparison can report it as a
     * row rather than losing it.
     *
     * @return array<string, array{header: list<string>, rows: array<string, string>}>
     */
    public static function listed(string $root, string $markdown): array
    {
        $root = self::root($root);
        $document = $root.'/'.self::DOCUMENT;
        $listed = [];

        foreach (self::LISTS as $section => $list) {
            $table = self::table($markdown, $list['heading']);
            $rows = [];

            foreach ($table['rows'] as $row) {
                $cell = $row['cells'][0] ?? '';
                $rows[self::pathOf($root, $document, $cell) ?? $cell] = $row['cells'][1] ?? '';
            }

            $listed[$section] = ['header' => $table['header'], 'rows' => $rows];
        }

        return $listed;
    }

    /**
     * Every file the guard table's `Where` cells link, as paths from the package root.
     *
     * A cell links what a reader is sent to, which is a guard's test or the program it is — so the
     * table and the test side are compared through the links the page already carries, rather than
     * through a second list of the guards kept beside it.
     *
     * @return list<string>
     */
    public static function named(string $root, string $markdown): array
    {
        $root = self::root($root);
        $document = $root.'/'.self::DOCUMENT;
        $named = [];

        foreach (self::rows($markdown) as $row) {
            foreach (Docs::linksIn($row['where']) as $link) {
                $path = Docs::resolve($root, $document, $link['target']);

                if ($path !== null) {
                    $named[] = Docs::relative($root, $path);
                }
            }
        }

        $named = array_values(array_unique($named));
        sort($named);

        return $named;
    }

    /**
     * The guard rows the files declare, in the order the files are walked.
     *
     * A guard is declared where it lives: one comment block in the file its row names carries
     * `@guards-index guard <name>`, and the block's own first sentence is what the guard catches.
     * The file is the `Where` cell, so a guard that moves takes its row with it — which is the
     * defect this replaces, a row that went on linking a path that had moved.
     *
     * The walk is over the files a commit would carry, because a guard lives wherever it lives and
     * not only under one directory; the comment style is whichever the file writes in. A declaration
     * is held to the kind of file it is in, too: a reading and a fixture live under `tests/Support/`,
     * a test under `tests/Unit/`, and a guard row wherever the guard is — which is never a reading or
     * a fixture, so a `guard` line in that directory is refused rather than rendered as a row. A
     * guard that is declared twice, declares no name, or has nothing to quote is refused as well,
     * because a row that cannot be written is the one thing a rendering must not answer quietly.
     *
     * @return array<string, array{name: string, catches: string, file: string, line: int}>
     *
     * @throws RuntimeException when a declaration is about a kind of file it is not, when a guard is
     *                          declared twice, declares no name, or has nothing to quote
     */
    public static function guardRows(string $root): array
    {
        $root = self::root($root);
        $rows = [];

        foreach (self::repository($root) as $file) {
            $contents = @file_get_contents($root.'/'.$file);

            // A markdown record writes headings with `#`, which are not comments about the file,
            // and a binary would be read as text with its bytes spelled differently.
            if ($contents === false || str_ends_with($file, '.md') || MachinePaths::binary($contents)) {
                continue;
            }

            foreach (self::blocks($contents) as $block) {
                $declaration = self::declaration($contents, $block);

                if ($declaration === null) {
                    continue;
                }

                // A declaration is held to where it is written: a reading and a fixture live under
                // `tests/Support/`, a test under `tests/Unit/`, and a guard row wherever the guard
                // is. A line about a kind of file this is not is refused rather than read as one.
                if ($declaration['section'] === self::GUARD) {
                    if (str_starts_with($file, self::DIRECTORY.'/')) {
                        throw new RuntimeException(sprintf(
                            '%s declares the guard `%s`, and %s/ holds readings and fixtures rather than guards. A guard row is declared in the program it is, or in the test under %s/ that is about it.',
                            $file,
                            $declaration['name'] ?? '',
                            self::DIRECTORY,
                            self::TESTS,
                        ));
                    }
                } elseif ($declaration['section'] === self::TEST && ! str_starts_with($file, self::TESTS.'/')) {
                    throw new RuntimeException(sprintf(
                        '%s declares `%s %s`, and %s/ is where a test is declared: the line is about a kind of file this is not.',
                        $file,
                        self::DECLARATION,
                        self::TEST,
                        self::TESTS,
                    ));
                }

                if ($declaration['section'] !== self::GUARD) {
                    continue;
                }

                if ($declaration['name'] === null) {
                    throw new RuntimeException(sprintf(
                        '%s declares `%s %s` with nothing after it. A guard row is named: `%s %s <name>` is what the table prints in its first cell.',
                        $file,
                        self::DECLARATION,
                        self::GUARD,
                        self::DECLARATION,
                        self::GUARD,
                    ));
                }

                $catches = self::summary(self::prose($block['text']));

                if ($catches === '') {
                    throw new RuntimeException(sprintf(
                        '%s declares the guard `%s` and has no first sentence for the table to quote. The words a guard catches with are the block\'s own opening line.',
                        $file,
                        $declaration['name'],
                    ));
                }

                $name = $declaration['name'];

                if (isset($rows[$name])) {
                    throw new RuntimeException(sprintf(
                        '%s and %s both declare the guard `%s`: a row is one file, and two declarations of one name are two places the same row is written.',
                        $rows[$name]['file'],
                        $file,
                        $name,
                    ));
                }

                $rows[$name] = [
                    'name' => $name,
                    'catches' => $catches,
                    'file' => $file,
                    'line' => $declaration['line'],
                ];
            }
        }

        return $rows;
    }

    /**
     * The guards table as the files produce it: each name, what it catches, and the link to the
     * file the guard is declared in.
     *
     * @return array<string, array{catches: string, where: string}>
     */
    public static function guardRendered(string $root): array
    {
        $rows = [];

        foreach (self::guardRows($root) as $name => $row) {
            $rows[$name] = ['catches' => $row['catches'], 'where' => self::linkTo($row['file'])];
        }

        return $rows;
    }

    /**
     * The guards table as the document has it: each row's name, then what it says it catches and
     * where it says the guard is.
     *
     * @return array<string, array{catches: string, where: string}>
     */
    public static function guardListed(string $markdown): array
    {
        $rows = [];

        foreach (self::rows($markdown) as $row) {
            $rows[$row['guard']] = ['catches' => $row['catches'], 'where' => $row['where']];
        }

        return $rows;
    }

    /**
     * What the guards table and the files that declare the guards disagree about.
     *
     * The test side is asked first, because a test under `tests/Unit/` that declares nothing is the
     * other half of the same question — and `guards()` throws rather than answering. Then the rows
     * are read in both directions: a guard with no row, a row with no guard, a row whose words or
     * link have drifted, and rows that are no longer in the order the files declare them in.
     *
     * @return list<string>
     */
    public static function guardDrift(string $root, string $markdown): array
    {
        $root = self::root($root);

        self::guards($root);

        $expected = self::guardRendered($root);
        $listed = self::guardListed($markdown);
        $findings = [];

        foreach ($expected as $name => $row) {
            if (! array_key_exists($name, $listed)) {
                $findings[] = sprintf(
                    '%s: no row declares the guard `%s`, which %s declares. A guard is declared where it lives, so the table is a rendering of the files.',
                    self::DOCUMENT,
                    $name,
                    $row['where'],
                );
            }
        }

        foreach ($listed as $name => $row) {
            if (! array_key_exists($name, $expected)) {
                $findings[] = sprintf(
                    '%s: a row names `%s`, and no file declares that guard. A row is a rendering of the file the guard lives in.',
                    self::DOCUMENT,
                    $name,
                );
            }
        }

        foreach ($expected as $name => $row) {
            if (! isset($listed[$name])) {
                continue;
            }

            if ($listed[$name]['catches'] !== $row['catches']) {
                $findings[] = sprintf(
                    '%s: the row for `%s` says "%s", and the file declares "%s".',
                    self::DOCUMENT,
                    $name,
                    $listed[$name]['catches'],
                    $row['catches'],
                );
            }

            if ($listed[$name]['where'] !== $row['where']) {
                $findings[] = sprintf(
                    '%s: the row for `%s` links %s, and the guard is declared in %s.',
                    self::DOCUMENT,
                    $name,
                    $listed[$name]['where'],
                    $row['where'],
                );
            }
        }

        // The order is the files' order. The same rows in another order are a table somebody wrote
        // by hand: the membership is right and the one source is not.
        $expectedNames = array_keys($expected);
        $listedNames = array_keys($listed);
        $sortedExpected = $expectedNames;
        $sortedListed = $listedNames;
        sort($sortedExpected);
        sort($sortedListed);

        if ($sortedExpected === $sortedListed && $expectedNames !== $listedNames) {
            $findings[] = sprintf(
                '%s: the guards are not in the order the files declare them in — `%s` first.',
                self::DOCUMENT,
                (string) ($expectedNames[0] ?? ''),
            );
        }

        return $findings;
    }

    /**
     * The one reading the suite asserts on: what the document lists, against what the directory
     * says, and what the guards table says, against the files that declare the guards — empty when
     * all of them agree.
     *
     * Every finding names the file it is about, or the table, so that a document which has stopped
     * being a rendering of the tree cannot be read as an answer of "nothing is wrong".
     *
     * @return list<string>
     */
    public static function drift(string $root): array
    {
        $root = self::root($root);
        $markdown = @file_get_contents($root.'/'.self::DOCUMENT);

        if ($markdown === false) {
            return [sprintf('%s is not here, so there is nothing holding the tables', self::DOCUMENT)];
        }

        return [
            ...self::lists($root, $markdown),
            ...self::guardDrift($root, $markdown),
        ];
    }

    /**
     * What the two rendered lists disagree about, against the directory that declares them.
     *
     * This is the half a program can repair: `bin/index.php` writes these rows from
     * `tests/Support/`, so a document that has stopped agreeing with the directory is a document it
     * can write again.
     *
     * @return list<string>
     */
    public static function lists(string $root, string $markdown): array
    {
        return self::compare(self::rendered($root), self::listed($root, $markdown));
    }

    /**
     * The document with its three tables replaced by what the tree produces.
     *
     * The prose around them is not this program's business — only the rows are, and the headings
     * they sit under are prose. A section whose table is not there at all is a refusal rather than
     * an insertion: where a missing table would go is a decision about the document, and a program
     * that guessed it would be writing a paragraph nobody wrote.
     *
     * @throws RuntimeException when the document has no table under one of the three headings
     */
    public static function rewritten(string $root, string $markdown): string
    {
        $rows = self::rendered($root);
        $eol = str_contains($markdown, "\r\n") ? "\r\n" : "\n";
        $lines = explode("\n", str_replace("\r\n", "\n", $markdown));

        $lines = self::replaceTable($lines, self::TABLE, self::guardLines(self::guardRendered($root)));

        foreach (self::LISTS as $section => $list) {
            $lines = self::replaceTable($lines, $list['heading'], self::tableLines($list['columns'], $rows[$section]));
        }

        $rewritten = implode("\n", $lines);

        return $eol === "\n" ? $rewritten : str_replace("\n", "\r\n", $rewritten);
    }

    /**
     * The files a commit would carry, which is the set a guard can be declared in.
     *
     * `git ls-files` answers it for a checkout and is what the two path readings use too, so the
     * three cannot come to disagree about which files are the repository's. A planted tree the
     * suite writes has no history to ask, so it is walked instead.
     *
     * @return list<string>
     */
    private static function repository(string $root): array
    {
        if (is_dir($root.'/.git')) {
            try {
                return MachinePaths::files($root);
            } catch (RuntimeException) {
                // A checkout whose git cannot be asked is walked rather than left unread.
            }
        }

        return self::walked($root);
    }

    /**
     * The files of a tree read without git, for the roots a fixture writes.
     *
     * @return list<string>
     */
    private static function walked(string $root): array
    {
        $files = [];
        $walker = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($walker as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = Docs::relative($root, $file->getPathname());

            foreach (self::WALK_PAST as $past) {
                if (str_starts_with($path, $past.'/') || str_contains($path, '/'.$past.'/')) {
                    continue 2;
                }
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }

    /**
     * Every PHP file under a directory of the package, as paths from the package root.
     *
     * The walk is recursive because a test is a file at whatever depth the suite puts it —
     * `tests/Unit/Docs/`, `tests/Unit/Support/` — and a listing that stopped at one level would
     * read as a directory with no tests in it rather than as a walk that went wrong.
     *
     * @return list<string>
     */
    private static function php(string $root, string $directory): array
    {
        $base = $root.'/'.$directory;

        if (! is_dir($base)) {
            return [];
        }

        $files = [];
        $walker = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($walker as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = Docs::relative($root, $file->getPathname());
            }
        }

        sort($files);

        return $files;
    }

    /**
     * One row of a table, as the document writes it: the file as a link to it, then the words.
     *
     * The link text is the path rather than a title, because the document sits in `docs/` and a
     * reader scanning the first column is scanning a list of files.
     */
    private static function row(string $file, string $summary): string
    {
        return '| [`../'.$file.'`](../'.$file.') | '.$summary.' |';
    }

    /**
     * The rows a table holds, as the table's lines.
     *
     * @param  list<string>  $columns
     * @param  array<string, string>  $rows
     * @return list<string>
     */
    private static function tableLines(array $columns, array $rows): array
    {
        $lines = [
            '| '.implode(' | ', $columns).' |',
            '|'.str_repeat('---|', count($columns)),
        ];

        foreach ($rows as $file => $summary) {
            $lines[] = self::row($file, $summary);
        }

        return $lines;
    }

    /**
     * The guards table's lines, from the rows the files declare.
     *
     * The name is the first cell rather than a link, because a guard's name is what it is called —
     * a program, a test, a key — and the `Where` cell is where a reader is sent; the file is a link
     * to the guard's own file either way.
     *
     * @param  array<string, array{catches: string, where: string}>  $rows
     * @return list<string>
     */
    private static function guardLines(array $rows): array
    {
        $lines = [
            '| '.implode(' | ', self::COLUMNS).' |',
            '|'.str_repeat('---|', count(self::COLUMNS)),
        ];

        foreach ($rows as $name => $row) {
            $lines[] = '| '.$name.' | '.$row['catches'].' | '.$row['where'].' |';
        }

        return $lines;
    }

    /** A file as the document links it: the path as the link text, relative to `docs/`. */
    private static function linkTo(string $file): string
    {
        return '[`../'.$file.'`](../'.$file.')';
    }

    /**
     * The table under one heading, swapped for the rows a rendering produced.
     *
     * @param  list<string>  $lines
     * @param  list<string>  $table
     * @return list<string>
     */
    private static function replaceTable(array $lines, string $heading, array $table): array
    {
        $headingAt = array_search($heading, array_map(rtrim(...), $lines), true);

        if ($headingAt === false) {
            throw new RuntimeException(sprintf(
                '%s has no `%s` heading, so there is no table to write under it. The heading is prose this program does not own.',
                self::DOCUMENT,
                $heading,
            ));
        }

        $first = null;
        $last = null;
        $counter = count($lines);

        for ($at = $headingAt + 1; $at < $counter; $at++) {
            if (! str_starts_with($lines[$at], '|')) {
                if ($first !== null) {
                    break;
                }

                continue;
            }

            $first ??= $at;
            $last = $at;
        }

        if ($first === null || $last === null) {
            throw new RuntimeException(sprintf(
                '%s has no table under `%s`, so there is nothing to rewrite. The rows are the whole of what this program writes.',
                self::DOCUMENT,
                $heading,
            ));
        }

        array_splice($lines, $first, $last - $first + 1, $table);

        return $lines;
    }

    /**
     * What the two lists disagree about, as findings.
     *
     * Read file by file rather than row by row, because the file is the unit both sides are about:
     * a row for a file the directory does not have and a file the document does not list are the
     * same disagreement seen from the two ends, and a reader wants one line about the file rather
     * than two about the table.
     *
     * @param  array<string, array<string, string>>  $expected
     * @param  array<string, array{header: list<string>, rows: array<string, string>}>  $listed
     * @return list<string>
     */
    private static function compare(array $expected, array $listed): array
    {
        $findings = [];

        foreach (self::LISTS as $section => $list) {
            if ($listed[$section]['header'] !== $list['columns']) {
                $findings[] = sprintf(
                    '%s: the table under `%s` reads `| %s |`, not `| %s |` — its rows are read by their columns.',
                    self::DOCUMENT,
                    $list['heading'],
                    implode(' | ', $listed[$section]['header']),
                    implode(' | ', $list['columns']),
                );
            }
        }

        foreach (self::files($expected, $listed) as $file) {
            $findings = [...$findings, ...self::about($file, $expected, $listed)];
        }

        foreach (self::LISTS as $section => $list) {
            $held = array_keys($listed[$section]['rows']);
            $want = array_keys($expected[$section]);

            if ($held !== $want && array_diff($held, $want) === [] && array_diff($want, $held) === []) {
                $findings[] = sprintf(
                    '%s: the rows under `%s` are not in the order the directory lists them in — `%s` first.',
                    self::DOCUMENT,
                    $list['heading'],
                    (string) ($want[0] ?? ''),
                );
            }
        }

        return $findings;
    }

    /**
     * Every file either side knows about, once, in one order.
     *
     * @param  array<string, array<string, string>>  $expected
     * @param  array<string, array{header: list<string>, rows: array<string, string>}>  $listed
     * @return list<string>
     */
    private static function files(array $expected, array $listed): array
    {
        $files = array_keys($expected['reading'] + $expected['support']);

        foreach (array_keys(self::LISTS) as $section) {
            $files = [...$files, ...array_keys($listed[$section]['rows'])];
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /**
     * One file's disagreement with the document, which is at most one of these.
     *
     * @param  array<string, array<string, string>>  $expected
     * @param  array<string, array{header: list<string>, rows: array<string, string>}>  $listed
     * @return list<string>
     */
    private static function about(string $file, array $expected, array $listed): array
    {
        $declares = array_key_exists($file, $expected['reading']) ? 'reading' : 'support';
        $wanted = $expected[$declares][$file] ?? null;
        $holds = [];

        foreach (array_keys(self::LISTS) as $section) {
            if (array_key_exists($file, $listed[$section]['rows'])) {
                $holds[] = $section;
            }
        }

        // A row naming something that is not a file in the directory: the cell is quoted as it was
        // written, which is what a reader can search for.
        if (! str_starts_with($file, self::DIRECTORY.'/')) {
            return [sprintf(
                '%s: a row names `%s`, and that is not a file in %s/ — a row is read by the file it links.',
                self::DOCUMENT,
                $file,
                self::DIRECTORY,
            )];
        }

        if ($wanted === null) {
            return [sprintf(
                '%s: the row naming %s is under `%s`, and the directory has no such file.',
                self::DOCUMENT,
                $file,
                self::LISTS[$holds[0] ?? 'reading']['heading'],
            )];
        }

        if ($holds === []) {
            return [sprintf(
                '%s: %s declares itself a %s and is in neither list.',
                self::DOCUMENT,
                $file,
                $declares,
            )];
        }

        if (! in_array($declares, $holds, true)) {
            return [sprintf(
                '%s: %s is listed under `%s`, and the file says it is a %s.',
                self::DOCUMENT,
                $file,
                self::LISTS[$holds[0]]['heading'],
                $declares,
            )];
        }

        if ($listed[$declares]['rows'][$file] !== $wanted) {
            return [sprintf(
                '%s: the row for %s says "%s", and the file\'s own first sentence says "%s".',
                self::DOCUMENT,
                $file,
                $listed[$declares]['rows'][$file],
                $wanted,
            )];
        }

        return [];
    }

    /**
     * The file a row's first cell names, as a path from the package root — or `null` when the cell
     * names no file this checkout has.
     */
    private static function pathOf(string $root, string $document, string $cell): ?string
    {
        foreach (Docs::linksIn($cell) as $link) {
            $path = Docs::resolve($root, $document, $link['target']);

            if ($path !== null) {
                return Docs::relative($root, $path);
            }
        }

        return null;
    }

    /**
     * Whether a file carries a Pest test.
     *
     * The one thing a claim to be a guard or a test of the code can be held to from the outside: a
     * test is a file with a test in it, and a reading or a fixture is a file that is run by one.
     * The call is matched at the start of a line, so a sentence about a test in a docblock — `it(`
     * written as prose — does not answer for the file.
     */
    private static function testCase(string $contents): bool
    {
        return preg_match('/^\s*(it|test)\(/m', $contents) === 1;
    }

    /**
     * Every comment block in a file that opens at the start of a line, in the order they are
     * written.
     *
     * A block that opens mid-line is not a header: a `/**` written inside a string literal is text
     * a fixture carries rather than a comment about the file, and reading it as a block would let a
     * test's own fixture decide what the test declares. A file's header, and every banner beside
     * it, opens at the start of a line.
     *
     * Three comment styles are read, because a guard is declared in the file it lives in and that
     * file is not always PHP: a slash-star banner or docblock, a run of `#` lines (a shell script, a
     * NEON file, an attribute file, a workflow), and an XML comment. One block is one declaration's
     * worth of argument, which is why a guard needs a block of its own rather than a line in a
     * header that says several things.
     *
     * @return list<array{text: string, at: int}>
     */
    private static function blocks(string $contents): array
    {
        $blocks = [...self::banners($contents), ...self::hashes($contents), ...self::xml($contents)];

        usort($blocks, static fn (array $left, array $right): int => $left['at'] <=> $right['at']);

        return $blocks;
    }

    /**
     * The slash-star blocks of a file, which is the style every PHP file and most scripts use.
     *
     * @return list<array{text: string, at: int}>
     */
    private static function banners(string $contents): array
    {
        $blocks = [];

        if (preg_match_all('/^[ \t]*\/\*/m', $contents, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return $blocks;
        }

        foreach ($matches[0] as [$open, $at]) {
            $end = strpos($contents, '*/', $at + strlen($open));

            if ($end === false) {
                continue;
            }

            $blocks[] = ['text' => substr($contents, $at, $end - $at), 'at' => $at];
        }

        return $blocks;
    }

    /**
     * The runs of `#` lines of a file: a shell script and a configuration file write a comment that
     * way, and a run is one block because a blank line ends it.
     *
     * @return list<array{text: string, at: int}>
     */
    private static function hashes(string $contents): array
    {
        $blocks = [];
        $start = null;
        $end = null;
        $at = 0;

        foreach (preg_split('/(?<=\n)/', $contents) ?: [$contents] as $line) {
            // A shebang is a `#` line, and it is the interpreter asking for a program rather than
            // a comment about the file: reading it as a block would put `#!/usr/bin/env php` in
            // front of the banner a script opens with.
            if (preg_match('/^[ \t]*#(?!!)/', $line) === 1) {
                $start ??= $at;
                $end = $at + strlen($line);
            } elseif ($start !== null) {
                $blocks[] = ['text' => substr($contents, $start, $end - $start), 'at' => $start];
                $start = null;
            }

            $at += strlen($line);
        }

        if ($start !== null) {
            $blocks[] = ['text' => substr($contents, $start, (int) $end - $start), 'at' => $start];
        }

        return $blocks;
    }

    /**
     * The `<!-- ... -->` blocks of a file, which is how an XML record writes a comment.
     *
     * @return list<array{text: string, at: int}>
     */
    private static function xml(string $contents): array
    {
        $blocks = [];

        if (preg_match_all('/<!--/', $contents, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return $blocks;
        }

        foreach ($matches[0] as [, $at]) {
            $end = strpos($contents, '-->', $at);

            if ($end === false) {
                continue;
            }

            $blocks[] = ['text' => substr($contents, $at, $end - $at), 'at' => $at];
        }

        return $blocks;
    }

    /**
     * The declaration a block carries, and the line it is on.
     *
     * @param  array{text: string, at: int}  $block
     * @return array{section: string, line: int}|null
     */
    private static function declaration(string $contents, array $block): ?array
    {
        if (preg_match(self::DECLARATION_PATTERN, $block['text'], $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $at = $block['at'] + $match[0][1];
        $name = isset($match[2]) ? trim($match[2][0]) : '';

        return [
            'section' => $match[1][0],
            'name' => $name === '' ? null : $name,
            'line' => substr_count($contents, "\n", 0, max($at, 1)) + 1,
        ];
    }

    /**
     * A block's lines with the comment decoration taken off, which is the whole difference between
     * a docblock and a banner.
     *
     * @return list<string>
     */
    private static function prose(string $block): array
    {
        $lines = [];

        foreach (explode("\n", str_replace("\r\n", "\n", $block)) as $line) {
            // The decoration of every style a block is written in: the `|` and dashes of a ruled
            // banner, the `*` of a docblock, the `#` of a shell or config comment, and the `<!--`
            // and `-->` an XML comment opens and closes with.
            $lines[] = (string) preg_replace('/^[\s\/*|#<!-]+/', '', $line);
        }

        return $lines;
    }

    /**
     * The sentence a table prints beside a row: the first paragraph of the file's header, as one
     * line, written the way a cell is written.
     *
     * A rule of dashes and an empty line are the two things a paragraph ends at, because both
     * styles use them for that — and a tagged line is not prose, so it neither ends a paragraph nor
     * starts one.
     *
     * @param  list<string>  $lines
     */
    private static function summary(array $lines): string
    {
        $summary = [];

        foreach ($lines as $line) {
            $line = rtrim($line);

            if ($line === '' || self::ruleOfDashes($line)) {
                if ($summary !== []) {
                    break;
                }

                continue;
            }

            if (str_starts_with($line, '@')) {
                continue;
            }

            $summary[] = trim($line);
        }

        $first = implode(' ', $summary);

        return $first === '' ? '' : lcfirst(rtrim($first, '.'));
    }

    /** Whether a line is one of the dashes a comment block rules itself off with. */
    private static function ruleOfDashes(string $line): bool
    {
        return preg_match('/^[-=]{3,}$/', $line) === 1;
    }

    /**
     * The table under the guards heading: its header row and its body rows, as written.
     *
     * The table ends where a non-table line follows it, so the prose after it is not read as rows —
     * and the section ends at the next second-level heading, which is why the sections below the
     * table are not read at all.
     *
     * @return array{header: list<string>, rows: list<array{cells: list<string>, line: int}>}
     */
    private static function table(string $markdown, string $heading): array
    {
        $header = [];
        $rows = [];
        $table = false;
        $body = false;

        foreach (self::lines($markdown) as $index => $line) {
            if (str_starts_with($line, '## ')) {
                // The heading is matched whole rather than by prefix: a section added later that
                // begins with the same words is a different section, and a table under it is not
                // this one.
                $table = rtrim($line) === $heading;
                $body = false;

                continue;
            }

            if (! $table) {
                continue;
            }

            if (! str_starts_with($line, '|')) {
                if ($header !== [] && trim($line) !== '') {
                    $table = false;
                }

                continue;
            }

            $cells = self::cells($line);

            if ($header === []) {
                $header = $cells;

                continue;
            }

            if (! $body && self::rule($cells)) {
                $body = true;

                continue;
            }

            $body = true;
            $rows[] = ['cells' => $cells, 'line' => $index + 1];
        }

        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * The document's lines, with the ending normalised: a file written on Windows is the same
     * document, and a row's line number is what a failure is looked up by.
     *
     * @return list<string>
     */
    private static function lines(string $markdown): array
    {
        return explode("\n", str_replace("\r\n", "\n", $markdown));
    }

    /**
     * One table line's cells. The pipe that opens and closes the line is not a cell of it.
     *
     * @return list<string>
     */
    private static function cells(string $line): array
    {
        return array_map(trim(...), explode('|', trim(trim($line), '|')));
    }

    /**
     * The row of dashes that separates a header from its body — the one line of a table that is not
     * a row. A table written without one still reads: its first line after the header is a row.
     *
     * @param  list<string>  $cells
     */
    private static function rule(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (preg_match('/^:?-{2,}:?$/', $cell) !== 1) {
                return false;
            }
        }

        return $cells !== [];
    }

    /**
     * The package root in the form the paths read here are compared in.
     */
    private static function root(string $root): string
    {
        return rtrim(str_replace('\\', '/', $root), '/');
    }
}
