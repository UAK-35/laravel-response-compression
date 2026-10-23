<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MachinePaths;
use Uak35\ResponseCompression\Tests\Support\MutationHarness;
use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

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
| This file is the spine of that: it plants the tree the breaks go into, and it holds the two
| questions that are about the set of guards rather than about any one of them — that every guard
| in `docs/guards.md` is either held or declined with a reason, and that every guard the harness
| holds is carried in a file of its own. Here rather than beside the holds, because the holds are
| spread one file per guard so `paratest` can run them on different workers, and this is the one
| place the whole set is read at once.
|
*/

/**
 * @guards-index guard the mutation harness
 *
 * the guards above, going quiet: one break at a time is planted in a copy of this checkout — the package's own work, with a spill from the checkout beside it left out rather than read through — a reading's detector is rewritten under its own tests, and a rail is taken out of the program whose test holds it — so a guard that stopped asking the right question, or a test that stopped asserting, fails there instead of reporting nothing forever
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

it('plants this package\'s own work, and leaves a spill from the checkout beside it out of the tree', function (): void {
    // A package developed beside this one has left files in this folder before now, and each was
    // untracked and not ignored — so each was a file a commit here would carry, and each made the
    // path readings name a file this package did not write. On the real tree that is the right
    // colour. In the planted tree it is not: the pristine read would come back naming somebody
    // else's file, and every hold would be red for a reason no mutation planted.
    // The drive path and the climb are assembled rather than written out, because this test file is
    // itself a file a commit would carry: a literal drive path in it would be a finding of the very
    // guard the fixture is about, and the reading would name the test that proves it works.
    $drive = 'C:'.'/tools/php/php.exe';

    $repo = ReleaseRepo::make();
    $repo->write('own-note.md', "# A note this package wrote\n");
    $repo->write('spill.machine.json', json_encode(['php' => $drive])."\n");
    $repo->write('spill-script.md', 'See [the package next door]('.'../'.'../sibling/notes.md).'."\n");

    $files = MutationHarness::carried($repo->path);

    // The listing is checked for substance before the exclusion is believed: a `carried()` that
    // answered with nothing would satisfy both "not the spill" claims without carrying anything.
    expect($files)->toContain('composer.json')
        ->toContain('own-note.md')
        ->not->toContain('spill.machine.json')
        ->not->toContain('spill-script.md');
});

it('carries every guard it holds in a file of its own, one guard each', function (): void {
    // The holds are driven one file per guard, so `paratest` — which spreads work by file and by
    // nothing finer — spreads the guards rather than running one file's worth of them while every
    // other worker waits. What makes that safe rather than a hand-kept list of files is this: each
    // file asks the harness for the guard it carries, the harness reads that name out of the file,
    // and the two sets have to be the same one. A guard that arrived with no file is a finding
    // here, and a file that asked for a guard nobody holds raises inside `claimed()` rather than
    // running no tests — a run of nothing reports the same green as a run of everything.
    expect(MutationHarness::claimed())->toBe(MutationHarness::subjects());
});

it('accounts for every guard the index records, and names the ones it does not mutate', function (): void {
    // The set comes from `docs/guards.md` rather than from a scan of this directory, because that
    // document is where a guard is recorded — the guard-index test already refuses a guard that is
    // not in it, and every row's link is resolved — so reading it here is what makes adding a guard
    // a decision: its test is either mutated by this harness or declined with a reason.
    $index = (string) file_get_contents(MutationHarness::package().'/'.MutationHarness::INDEX);

    preg_match_all('#tests/Unit/[A-Za-z0-9/]+Test\.php#', $index, $matches);

    $indexed = array_values(array_unique($matches[0]));

    // A guard is held three ways — a break planted in the tree, a reading's detector rewritten, and
    // a rail taken out of the program its tests run — and they are read as one set here, because
    // the question this asks is whether the index and this harness agree about which guards exist.
    $covered = [
        ...array_column(MutationHarness::mutations(), 'test'),
        ...array_column(MutationHarness::programs(), 'test'),
    ];
    $declined = array_keys(MutationHarness::UNAUDITED);
    $gone = array_values(array_filter(
        [...$covered, ...$declined],
        static fn (string $file): bool => ! is_file(MutationHarness::package().'/'.$file),
    ));

    // Three claims, and the second and third are what keep the first from being satisfied by a
    // list nobody is on: every guard in the index is accounted for, nothing is accounted for that
    // the index does not record — so a hold for a guard that was removed fails rather than sitting
    // there — and every file named is still there, so a rename cannot leave a line pointing at
    // nothing.
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
