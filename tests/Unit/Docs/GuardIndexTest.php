<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Docs;
use Uak35\ResponseCompression\Tests\Support\GuardIndex;

/*
|--------------------------------------------------------------------------
| The index of the guards
|--------------------------------------------------------------------------
|
| `docs/guards.md` is where a guard is put in front of whoever adds the next one, and it had
| one direction watched and one not. A row that links a test which has since been renamed is
| caught already: the row's link is a link, and the docs-link guard resolves every link in every
| document this package ships. A guard added with *no row at all* is caught by nothing, and
| cannot be — no test knew what a guard is, so the next one could arrive unrecorded and read as
| though it had always been part of the list.
|
| So the table is read as a claim about a directory. Every row has to link a file the repository
| has, and every file in `tests/Support/` has to be named in the document — a reading in the
| section that lists them, or support in the section saying it is not a guard. The reading is
| exercised on its own at the foot of this file, because a reader that found no rows agrees with
| a table that has none.
|
*/

it('links every row of the guard table to a file that exists', function (): void {
    [$root, $document, $markdown] = guardIndexDocument();

    // The header is pinned to the words rather than to the constant that reads them: a table
    // whose columns moved would be read wrongly rather than not at all, and the constant moving
    // with it would hide exactly that.
    expect(GuardIndex::header($markdown))->toBe(['Guard', 'What it catches', 'Where'])
        ->and(GuardIndex::rows($markdown))->not->toBeEmpty();

    $broken = [];
    $unnamed = [];

    foreach (GuardIndex::rows($markdown) as $row) {
        $paths = guardIndexPaths($root, $document, $row['where']);

        foreach ($paths as $target => $path) {
            if (! file_exists($path)) {
                $broken[] = sprintf(
                    'docs/guards.md:%d -> %s (the row for %s)',
                    $row['line'],
                    $target,
                    $row['guard'],
                );
            }
        }

        // A cell that only names the row above it resolves nothing: the Where column is where a
        // reader is sent from, so a row that names no file sends nobody.
        if ($paths === []) {
            $unnamed[] = sprintf('docs/guards.md:%d (the row for %s)', $row['line'], $row['guard']);
        }
    }

    expect($broken)->toBe([])
        ->and($unnamed)->toBe([]);
});

it('names every file in tests/Support in the index', function (): void {
    [$root, $document, $markdown] = guardIndexDocument();

    $readings = GuardIndex::readings($root);

    // A directory that was never read agrees with a document that names nothing, so the listing
    // is checked for substance first — and the file named here is this guard's own reading, which
    // no hand-written list would have had before this change.
    expect($readings)->not->toBeEmpty()
        ->and($readings)->toContain('tests/Support/GuardIndex.php');

    $named = GuardIndex::spans($markdown);

    foreach (Docs::linksIn($markdown) as $link) {
        $path = Docs::resolve($root, $document, $link['target']);

        if ($path !== null) {
            $named[] = Docs::relative($root, $path);
        }
    }

    $unnamed = array_values(array_filter(
        $readings,
        static fn (string $file): bool => ! in_array($file, $named, true),
    ));

    expect($unnamed)->toBe([]);
});

it('resolves every path it names under tests/Support', function (): void {
    [$root, , $markdown] = guardIndexDocument();

    // The other half of being named: a reading that is renamed leaves its old name behind in the
    // document, and a name that no longer resolves is how the index starts describing a file that
    // is not there.
    $stale = [];

    foreach (GuardIndex::spans($markdown) as $span) {
        if (str_starts_with($span, 'tests/Support/') && ! file_exists($root.'/'.$span)) {
            $stale[] = $span;
        }
    }

    expect($stale)->toBe([]);
});

it('reads the table it is given, and nothing under it', function (): void {
    $document = implode("\n", [
        '## Kept',
        '',
        '| Guard | What it catches | Where |',
        '|---|---|---|',
        '| the first | one thing | [`../tests/Unit/Docs/DocsLinksTest.php`](../tests/Unit/Docs/DocsLinksTest.php) |',
        '| the second | another | the same workflow |',
        '',
        '## The readings the guards are built from',
        '',
        '| Reading | What it reads |',
        '|---|---|',
        '| `tests/Support/Docs.php` | the markdown this package ships |',
        '',
    ]);

    $rows = GuardIndex::rows($document);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['guard'])->toBe('the first')
        ->and($rows[0]['catches'])->toBe('one thing')
        ->and($rows[0]['line'])->toBe(5)
        ->and($rows[1]['where'])->toBe('the same workflow')
        ->and($rows[1]['line'])->toBe(6)
        ->and(Docs::linksIn($rows[0]['where']))->toHaveCount(1)
        ->and(Docs::linksIn($rows[1]['where']))->toBe([])
        ->and(GuardIndex::spans($document))->toContain('tests/Support/Docs.php');

    // The second table is under its own heading and is not read as a guard: the rows above it end
    // where the section does, which is what keeps a reading from being counted as a guard.
    expect(array_column($rows, 'guard'))->toBe(['the first', 'the second']);

    // And the two ways the reading can have nothing to read, neither of which may come back as a
    // pass: no table at all, and a table whose columns are not the ones a row is read by.
    expect(fn (): array => GuardIndex::rows("# Not the index\n"))
        ->toThrow(RuntimeException::class);

    expect(fn (): array => GuardIndex::rows("## Kept\n\n| Guard | Where | What it catches |\n|---|---|---|\n| a | b | c |\n"))
        ->toThrow(RuntimeException::class);
});

/**
 * The index, and the two values every test here needs besides it: the root as the forward-slashed
 * path a link is resolved against, and the document itself.
 *
 * @return array{0: string, 1: string, 2: string}
 */
function guardIndexDocument(): array
{
    $root = str_replace('\\', '/', packageRoot());
    $document = $root.'/docs/guards.md';

    return [$root, $document, (string) file_get_contents($document)];
}

/**
 * The files a cell links, as the target it was written as and the path it resolves to.
 *
 * A link that points somewhere this checkout does not have — a URL, a `mailto:`, an anchor — is
 * left out: the question here is which files the row names, and those name none.
 *
 * @return array<string, string>
 */
function guardIndexPaths(string $root, string $document, string $cell): array
{
    $paths = [];

    foreach (Docs::linksIn($cell) as $link) {
        $path = Docs::resolve($root, $document, $link['target']);

        if ($path !== null) {
            $paths[$link['target']] = $path;
        }
    }

    return $paths;
}
