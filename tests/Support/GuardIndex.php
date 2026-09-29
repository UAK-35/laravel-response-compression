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
 * WHAT IT DOES NOT DO
 * -------------------
 * It does not read a link anywhere else, and it does not decide what a guard *is* past that
 * directory. What each guard refuses stays in the document, in prose, where a reader looks and
 * where a name that has drifted is visible; what is enforced is only that the document and the
 * directory agree. Nor is the header taken on trust: a table whose columns moved is a table this
 * would read wrongly rather than not at all, so its header is asserted before a row is read.
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
        $table = self::table($markdown);

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
        return self::table($markdown)['header'];
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
     * The table under the guards heading: its header row and its body rows, as written.
     *
     * The table ends where a non-table line follows it, so the prose after it is not read as rows —
     * and the section ends at the next second-level heading, which is why the sections below the
     * table are not read at all.
     *
     * @return array{header: list<string>, rows: list<array{cells: list<string>, line: int}>}
     */
    private static function table(string $markdown): array
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
                $table = rtrim($line) === self::TABLE;
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
