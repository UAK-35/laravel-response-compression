<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Docs;

/*
|--------------------------------------------------------------------------
| The markdown this package ships
|--------------------------------------------------------------------------
|
| Two ways a document goes wrong without anyone noticing, both guarded here:
|
|  - a relative link that resolves to nothing — a renamed test, a doc moved, a path
|    written from the package root instead of from the file it sits in. A reader who
|    follows one of those gets a 404 from GitHub and reports nothing;
|  - a design record nothing links to. The docs/ records are how this package explains
|    its decisions, so one that is unreachable is one that was never explained.
|
| The links are read as text and resolved against the file they were written in, because
| that is the only way to catch the second kind of mistake: a path that looks right and is
| wrong.
|
*/

/**
 * @guards-index guard the docs-link test
 *
 * a broken link between the records, and a record nothing links to
 */
it('resolves every relative link in every markdown file the package ships', function (): void {
    // The walk lives in `Docs::broken()` rather than here, so the mutation harness can ask the same
    // call with a broken link planted in a copy of the tree — a walk written out a second time in
    // the harness would be a guard wearing this one's name. The root is normalised inside the call,
    // because a resolved link comes back forward-slashed while `packageRoot()` is a native path.
    $root = str_replace('\\', '/', packageRoot());

    // A walk that reached no file agrees with a tree whose every link resolves, so the files the
    // reading is about are checked for substance before its clean answer is believed.
    expect(Docs::files($root))->not->toBeEmpty()
        ->and(Docs::broken($root))->toBe([]);
});

it('links every design record from the README', function (): void {
    // The walk lives in `Docs::unlinkedRecords()` rather than here, so the test and the mutation
    // harness ask the same call — a record added to `docs/` that no link reaches is the break it
    // has to name. The README is read rather than every document, because it is the page a reader
    // starts from: a record it does not name is one nobody is sent to.
    $root = str_replace('\\', '/', packageRoot());

    // A guard that reads nothing agrees with a README that links nothing, so the records
    // found on disk are checked for substance before the reading's clean answer is believed.
    expect(Docs::records($root))->not->toBeEmpty()
        ->and(Docs::unlinkedRecords($root))->toBe([]);
});

it('reads links and resolves them against the file they are written in', function (): void {
    // The guard above is only worth its green tick if the reader behind it works, so the
    // reader is exercised on its own: an anchor and a URL are not paths, and a path is
    // resolved from the document rather than from the package root.
    $root = Docs::normalise('/pkg');

    expect(Docs::resolve($root, '/pkg/docs/a.md', 'https://example.test/x'))->toBeNull()
        ->and(Docs::resolve($root, '/pkg/docs/a.md', 'mailto:x@example.test'))->toBeNull()
        ->and(Docs::resolve($root, '/pkg/docs/a.md', '#a-heading'))->toBeNull()
        ->and(Docs::resolve($root, '/pkg/docs/a.md', 'b.md'))->toBe('/pkg/docs/b.md')
        ->and(Docs::resolve($root, '/pkg/docs/a.md', '../tests/Unit/X.php'))->toBe('/pkg/tests/Unit/X.php')
        ->and(Docs::resolve($root, '/pkg/docs/a.md', 'b.md#a-heading'))->toBe('/pkg/docs/b.md');
});
