<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MachinePaths;
use Uak35\ResponseCompression\Tests\Support\MutationHarness;

/*
|--------------------------------------------------------------------------
| The tree, broken on purpose
|--------------------------------------------------------------------------
|
| A guard that stopped checking anything does not fail. It reports the same nothing it reports
| when there is nothing to report, and it goes on doing it in the index while it does.
|
| Every reading in this package is held to its own substance at least once, which catches the
| half of that where the input went empty — a glob that stopped matching, a directory that moved,
| a listing that answered with nothing. What no amount of that can catch is a reading that still
| has its input and stopped asking the right question of it: a pattern loosened until no path
| matches it, a class of file dropped from a walk, an operator changed to the one that answers
| yes. Those read exactly like a working guard.
|
| So each mutation plants one break in a copy of this checkout and requires the guard's own
| reading to name it. The reading is the one the guard's test asserts on rather than a second
| implementation of it, and it is taken twice on the same tree: once as the tree was planted,
| which is this checkout with nothing wrong with it, and once with the break in it. The mutation
| is the only difference between the two answers — and the second is asserted for the marker
| rather than for being non-empty, because a reading that went blind answers with nothing both
| times and an assertion on "something" would be answered by either.
|
| The harness accounts for every guard in `docs/guards.md`: each row's test is either mutated
| here or declined with a reason, and both lists are read back against the index, so adding a
| guard is a decision rather than a silence.
|
*/

beforeEach(function (): void {
    MutationHarness::restore();
});

it('plants this checkout in a temporary tree, with an index of it', function (): void {
    $root = MutationHarness::tree();
    $files = MachinePaths::files($root);

    // A tree that was never planted agrees with every mutation in the list, because the readings
    // are over "the files a commit would carry" and no files at all is a clean tree. The listing
    // is therefore checked for substance before any reading of it is believed — and the index is
    // checked for itself, because `MachinePaths::files()` asks git, and a planted tree with no
    // index answers with nothing.
    expect($files)->toContain('composer.json')
        ->and($files)->toContain('config/response-compression.php')
        ->and($files)->toContain('tests/Support/MachinePaths.php')
        ->and(is_dir($root.'/.git'))->toBeTrue()
        ->and(is_dir($root.'/vendor'))->toBeFalse();
});

foreach (MutationHarness::mutations() as $mutation) {
    it(sprintf('turns %s red when this checkout is broken under it', $mutation['guard']), function () use ($mutation): void {
        $root = MutationHarness::tree();
        $read = $mutation['read'];

        expect(implode(PHP_EOL, $read($root)))->toBe('');

        $marker = ($mutation['plant'])($root);

        expect($marker)->not->toBe('')
            ->and(implode(PHP_EOL, $read($root)))->toContain($marker);
    });
}

it('accounts for every guard the index records, and names the ones it does not mutate', function (): void {
    // The set comes from `docs/guards.md` rather than from a scan of this directory, because that
    // document is where a guard is recorded — the guard-index test already refuses a guard that is
    // not in it, and every row's link is resolved — so reading it here is what makes adding a guard
    // a decision: its test is either mutated by this harness or declined with a reason.
    $index = (string) file_get_contents(MutationHarness::package().'/'.MutationHarness::INDEX);

    preg_match_all('#tests/Unit/[A-Za-z0-9/]+Test\.php#', $index, $matches);

    $indexed = array_values(array_unique($matches[0]));
    $covered = array_column(MutationHarness::mutations(), 'test');
    $declined = array_keys(MutationHarness::UNAUDITED);
    $gone = array_values(array_filter(
        [...$covered, ...$declined],
        static fn (string $file): bool => ! is_file(MutationHarness::package().'/'.$file),
    ));

    // Three claims, and the second and third are what keep the first from being satisfied by a
    // list nobody is on: every guard in the index is accounted for, nothing is accounted for that
    // the index does not record — so a mutation for a guard that was removed fails rather than
    // sitting there — and every file named is still there, so a rename cannot leave a line
    // pointing at nothing.
    expect($indexed)->not->toBeEmpty()
        ->and(array_values(array_diff($indexed, [...$covered, ...$declined])))->toBe([])
        ->and(array_values(array_diff([...$covered, ...$declined], $indexed)))->toBe([])
        ->and($gone)->toBe([]);
});

it('gives every guard it declines a reason rather than a silence', function (): void {
    $silent = array_keys(array_filter(
        MutationHarness::UNAUDITED,
        static fn (string $reason): bool => trim($reason) === '',
    ));

    expect(MutationHarness::UNAUDITED)->not->toBeEmpty()
        ->and($silent)->toBe([]);
});
