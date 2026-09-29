<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ChangelogPreamble;

/*
|--------------------------------------------------------------------------
| The changelog's own opening claim
|--------------------------------------------------------------------------
|
| `CHANGELOG.md` explains its numbering by naming the versions that are not part of it — the two
| inherited from the upstream this package was taken from. Names in prose go stale silently, and
| these ones go stale at a known moment: `0.1.0` is the next release here, and the file will then
| carry a released `0.1.0` while the opening paragraph still says that number is someone else's.
|
| So the preamble and the file's second-level headings are read against each other. The inherited
| `0.1.0` is a third-level heading under `## Inherited from upstream` on purpose — the number stays
| readable and is not a release of this package — and the level is what lets the guard tell one
| from the other, which is also the level the preamble is written at.
|
*/

it('keeps the changelog preamble from naming a version the file has released', function (): void {
    $content = (string) file_get_contents(packageRoot().'/CHANGELOG.md');
    $read = ChangelogPreamble::read($content);

    // Both halves have to have found something before the clean answer means anything. A preamble
    // that names no number, or a reader that lost every heading, would pass this guard while it was
    // broken — and a guard whose green tick is a scan that read nothing is one that would look the
    // same either way.
    // The answer names the version rather than counting it, because the fix is an edit to the
    // paragraph: a number released here may not go on being described as someone else's, so the
    // preamble stops naming it and the inherited heading below keeps the history.
    expect($read['preamble'])->not->toBeEmpty()
        ->and($read['sections'])->not->toBeEmpty()
        ->and(ChangelogPreamble::stale($content))->toBe([]);
});

it('reads a claim above the first heading, and a section only at the second level', function (): void {
    $stale = "# Changelog\n\n"
        ."**Versions count on from the newest tag**, and the `0.2.0` and `0.1.0` releases were\n"
        ."inherited from the upstream this package was taken from.\n\n"
        ."## Unreleased\n\n"
        ."## [v0.1.0] - 2026-09-28\n\n"
        ."### Added\n\n- A thing nobody had before.\n";

    expect(ChangelogPreamble::read($stale)['preamble'])->toBe(['0.2.0', '0.1.0'])
        ->and(ChangelogPreamble::read($stale)['sections'])->toBe(['0.1.0'])
        ->and(ChangelogPreamble::stale($stale))->toBe(['0.1.0']);

    // The same numbers with the inherited history folded: `0.1.0` is still written down, at the
    // third level under a heading that is not a version, and the file has released nothing with
    // that number. That is the state the package is in now, and it has to read as clean — the fold
    // is what makes the preamble true, so a guard that could not see the difference would be
    // arguing with the document it is protecting.
    $folded = str_replace(
        '## [v0.1.0] - 2026-09-28',
        "## Inherited from upstream\n\n### v0.1.0 - 2024-12-29",
        $stale,
    );

    expect(ChangelogPreamble::stale($folded))->toBe([])
        ->and(ChangelogPreamble::read($folded)['sections'])->toBe([]);

    // And the same section one lane earlier: the line is this repository's from its first alpha, so
    // a prerelease of the number is a section for the number.
    expect(ChangelogPreamble::stale(str_replace(
        '## [v0.1.0] - 2026-09-28',
        '## [v0.1.0-alpha1] - 2026-09-01',
        $stale,
    )))->toBe(['0.1.0']);
});

it('refuses a changelog with no heading, rather than reading nothing', function (): void {
    // A parser that quietly reads nothing is worse than no parser at all: it reports agreement with
    // a document it never understood.
    expect(fn (): array => ChangelogPreamble::read("# Changelog\n\nNothing below this line.\n"))
        ->toThrow(RuntimeException::class, 'has no `##` heading');
});
