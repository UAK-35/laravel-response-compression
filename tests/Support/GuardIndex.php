<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

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
 * It does not read a link anywhere else, and it does not decide what a guard *is* past that
 * directory. What each guard refuses stays in the document, in prose, where a reader looks and
 * where a name that has drifted is visible; what is enforced is only that the document and the
 * directory agree. Nor is the header taken on trust: a table whose columns moved is a table this
 * would read wrongly rather than not at all, so its header is asserted before a row is read.
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
            $block = self::block($contents);
            $declaration = $block === null ? null : self::declaration($contents, $block);

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
     * The one reading the suite asserts on: what the document lists, against what the directory
     * says — empty when the two agree.
     *
     * Every finding names the file it is about, or the table, so that a document which has stopped
     * being a rendering of the directory cannot be read as an answer of "nothing is wrong".
     *
     * @return list<string>
     */
    public static function drift(string $root): array
    {
        $root = self::root($root);
        $markdown = @file_get_contents($root.'/'.self::DOCUMENT);

        if ($markdown === false) {
            return [sprintf('%s is not here, so there is nothing holding the two lists', self::DOCUMENT)];
        }

        return self::compare(self::rendered($root), self::listed($root, $markdown));
    }

    /**
     * The document with its two tables replaced by what the directory produces.
     *
     * The prose around them is not this program's business — only the rows are, and the headings
     * they sit under are prose. A section whose table is not there at all is a refusal rather than
     * an insertion: where a missing list would go is a decision about the document, and a program
     * that guessed it would be writing a paragraph nobody wrote.
     *
     * @throws RuntimeException when the document has no table under one of the two headings
     */
    public static function rewritten(string $root, string $markdown): string
    {
        $rows = self::rendered($root);
        $eol = str_contains($markdown, "\r\n") ? "\r\n" : "\n";
        $lines = explode("\n", str_replace("\r\n", "\n", $markdown));

        foreach (self::LISTS as $section => $list) {
            $lines = self::replaceTable($lines, $list['heading'], $list['columns'], $rows[$section]);
        }

        $rewritten = implode("\n", $lines);

        return $eol === "\n" ? $rewritten : str_replace("\n", "\r\n", $rewritten);
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
     * The table under one heading, swapped for the one the directory produces.
     *
     * @param  list<string>  $lines
     * @param  list<string>  $columns
     * @param  array<string, string>  $rows
     * @return list<string>
     */
    private static function replaceTable(array $lines, string $heading, array $columns, array $rows): array
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

        array_splice($lines, $first, $last - $first + 1, self::tableLines($columns, $rows));

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
     * The first comment block in a file and where it starts, because both styles this package
     * writes are the file's own header: the `/**` docblock above a class, and the `|`-ruled banner
     * a script opens with. Reading them the same way is what keeps a second rule for the second
     * style from being a second place for the two to disagree.
     *
     * @return array{text: string, at: int}|null
     */
    private static function block(string $contents): ?array
    {
        $at = strpos($contents, '/*');

        if ($at === false) {
            return null;
        }

        $end = strpos($contents, '*/', $at);

        if ($end === false) {
            return null;
        }

        return ['text' => substr($contents, $at, $end - $at), 'at' => $at];
    }

    /**
     * The declaration a block carries, and the line it is on.
     *
     * @param  array{text: string, at: int}  $block
     * @return array{section: string, line: int}|null
     */
    private static function declaration(string $contents, array $block): ?array
    {
        $pattern = '/^[\s\/*|]*'.preg_quote(self::DECLARATION, '/').'\s+(\S+)\s*$/m';

        if (preg_match($pattern, $block['text'], $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $at = $block['at'] + $match[0][1];

        return [
            'section' => $match[1][0],
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
            $lines[] = (string) preg_replace('/^[\s\/*|]+/', '', $line);
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
