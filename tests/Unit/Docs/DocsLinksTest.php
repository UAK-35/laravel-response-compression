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

it('resolves every relative link in every markdown file the package ships', function (): void {
    // packageRoot() is a native path — backslashes on Windows — and a resolved link comes
    // back forward-slashed, so the root is normalised the same way before the two are
    // compared. Both still work as paths; only the comparison needed them to agree.
    $root = str_replace('\\', '/', packageRoot());
    $broken = [];

    foreach (Docs::files($root) as $file) {
        foreach (Docs::links($file) as $link) {
            $target = Docs::resolve($root, $file, $link['target']);

            // Null is a link to somewhere that is not this checkout — an absolute URL, a
            // mailto, or an anchor within the same page.
            if ($target === null) {
                continue;
            }

            if (! file_exists($target)) {
                $broken[] = sprintf(
                    '%s:%d -> %s',
                    Docs::relative($root, $file),
                    $link['line'],
                    $link['target'],
                );
            }
        }
    }

    expect($broken)->toBe([]);
});

it('links every design record from the README', function (): void {
    $root = str_replace('\\', '/', packageRoot());
    $readme = $root.'/README.md';

    $linked = [];

    foreach (Docs::links($readme) as $link) {
        $target = Docs::resolve($root, $readme, $link['target']);

        if ($target !== null && str_starts_with($target, $root.'/docs/')) {
            $linked[] = basename($target);
        }
    }

    sort($linked);

    // A guard that reads nothing agrees with a README that links nothing, so the records
    // found on disk are checked for substance before they are compared.
    expect(Docs::records($root))->not->toBeEmpty()
        ->and($linked)->toBe(Docs::records($root));
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
