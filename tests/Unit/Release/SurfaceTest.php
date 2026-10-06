<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The rails — the public surface
|--------------------------------------------------------------------------
|
| Two readings of the tree answer what a release removed: the surface reads symbols, and the
| inventory has a column for the ones it can stamp. Where they agree, a removal the notes do not
| declare is a refusal; where only one of them saw it, the notes are where it has to be said. A
| symbol that moved between files is neither, and a change to the surface is breaking whatever the
| notes say.
|
| A file of its own, because a file is what `paratest` spreads work by, and because every test here
| is a release run whose answer is about the diff rather than about the tags.
|
| @guards-index test
|
*/

it('refuses notes that leave out a removal the surface and the inventory agree on', function (): void {
    $repo = ReleaseRepo::make();

    // A release first, because the inventory is evidence only while its stamp names the tag being
    // released from: a fixture that has never written one has nothing for the second reading to say.
    $released = $repo->release('--weigh', '--skip-ci', '--yes');

    expect($released->exitCode)->toBe(0, $released->describe());

    // A public method gone, and the entry filed as a fix. The number is not what is wrong here — a
    // removal weighs as breaking and the bump is a minor whatever the notes claim — the notes are.
    $repo->withNotes("### Fixed\n\n- A defect, fixed.\n");
    $repo->withoutPublicMethod();

    // A plan is not a release: it reports the refusal it is avoiding rather than taking it.
    $planned = $repo->release('--weigh', '--skip-ci', '--dry-run');

    expect($planned->exitCode)->toBe(0, $planned->describe())
        ->and($planned->said('a real run would refuse without --ignore-policy'))->toBeTrue();

    $refused = $repo->release('--weigh', '--skip-ci', '--yes');

    expect($refused->exitCode)->toBe(1)
        ->and($refused->refused('`## Unreleased` does not declare a removal'))->toBeTrue()
        // Both readings are named, because the refusal is worth listening to only while they agree.
        ->and($refused->refused('removed public method Uak35\\ResponseCompression\\Thing::handle()'))->toBeTrue()
        ->and($refused->refused('### Removed'))->toBeTrue()
        ->and($refused->refused('--ignore-policy'))->toBeTrue();

    // The escape is the one the policy rail takes, and the plan says what it was asked to pass.
    $overridden = $repo->release('--weigh', '--ignore-policy', '--skip-ci', '--yes');

    expect($overridden->exitCode)->toBe(0, $overridden->describe())
        ->and($overridden->said('--ignore-policy: releasing with a removal the notes do not declare'))->toBeTrue()
        ->and($overridden->plan('version'))->toBe('0.2.0');
});

it('leaves a removal only one reading saw to the notes', function (): void {
    $repo = ReleaseRepo::make();
    $repo->release('--weigh', '--skip-ci', '--yes');

    // A public constant is a symbol the surface reads and the inventory has no column for: one
    // reading rather than two, which is the state the rail above deliberately does not refuse. The
    // weighing still calls it breaking, and the notes are still where it has to be declared.
    $repo->withNotes("### Fixed\n\n- A defect, fixed.\n");
    $repo->withoutPublicSymbol();

    $run = $repo->release('--weigh', '--skip-ci', '--dry-run');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->plan('surface'))->toContain('1 removed')
        ->and($run->said('nothing removed, renamed or added since v0.1.0'))->toBeTrue()
        ->and($run->plan('version'))->toBe('0.2.0');
});

it('reads a symbol that moved between files as a move rather than a removal', function (): void {
    // The notes are a patch on purpose: if the move still read as a removal, the surface signal
    // would call it breaking and the version would open a minor line instead of staying on this
    // one — which is the whole difference the reading makes.
    $repo = ReleaseRepo::make()->withNotes("### Fixed\n\n- A defect, fixed.\n");
    $repo->moveClass('src/Thing.php', 'src/Moved.php');

    $run = $repo->release('--weigh', '--dry-run');

    // The symbol is the same one in a different file, which is nothing to import differently: under
    // PSR-4 the path and the class name are one fact, and only the name is what a consumer uses.
    expect($run->plan('surface'))->toContain('moved between files')
        ->and($run->plan('surface'))->toContain('Thing')
        ->and($run->plan('surface'))->not()->toContain('removed')
        ->and($run->plan('version'))->toBe('0.0.2');
});

it('weighs a change to the public surface as breaking', function (): void {
    $repo = ReleaseRepo::make()->retagAs('v1.0.0');
    $repo->withoutPublicSymbol();
    $repo->withNotes("### Fixed\n\n- Nothing to see here.\n");

    $run = $repo->release('--weigh', '--skip-ci', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->plan('surface'))->toContain('breaking')
        ->and($run->plan('version'))->toBe('2.0.0');
});
