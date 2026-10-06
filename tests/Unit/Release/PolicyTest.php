<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The rails — the number the notes call for
|--------------------------------------------------------------------------
|
| What the script writes is a version, and what it may not do is write one below what the changes
| call for: a declared bump, a version named outright and the notes themselves are each weighed, and
| `--ignore-policy` is the decision that overrides the weighing rather than a way around it.
|
| The weighing is what the notes are read for, so the `BREAKING` marker is here twice — the claim and
| the mention — because a reading that stopped telling one from the other would be wrong in both
| directions. It is the heaviest test in the release suite and it has a file to itself for that
| reason: a file is what a worker is handed.
|
| @guards-index test
|
*/

it('refuses a declared bump that undersells the changes', function (): void {
    $repo = ReleaseRepo::make()->retagAs('v1.0.0');
    $repo->withNotes("### Removed\n\n- The old way of doing it.\n");

    // Past 1.0.0 a breaking change is a major, and the notes are what declare it: no flag talks
    // the script out of `### Removed`.
    $refused = $repo->release('--minor', '--skip-ci', '--yes');

    expect($refused->exitCode)->toBe(1)
        ->and($refused->refused('--minor was declared, but the changes call for a major release.'))->toBeTrue()
        ->and($refused->refused('CHANGELOG'))->toBeTrue();

    $overridden = $repo->release('--minor', '--ignore-policy', '--skip-ci', '--yes');

    expect($overridden->exitCode)->toBe(0, $overridden->describe())
        ->and($overridden->said('--ignore-policy'))->toBeTrue()
        ->and($overridden->plan('version'))->toBe('1.1.0');
});

it('weighs a BREAKING claim, and not a note that only names the marker', function (): void {
    // `BREAKING` is the word the notes signal reads, and it is also the word a note about the
    // signal has to write down: quoting it mid-sentence is naming it rather than claiming one, and
    // `str_contains()` could not tell the two apart — past 1.0.0 that difference is a major nobody
    // asked for. A claim is the marker written as one: a line of its own, with the bullet or the
    // emphasis a changelog puts around it. Both directions are pinned here, because a reading that
    // stopped seeing mentions would also stop seeing the breaks they describe.
    $mentions = [
        "### Fixed\n\n- A defect, fixed, and a `BREAKING` marker named in the sentence.\n",
        "### Fixed\n\n- `BREAKING` is the word this signal reads.\n",
        "### Fixed\n\n- An example, fenced:\n\n  ```\n  BREAKING: the old way of doing it is gone.\n  ```\n",
    ];

    foreach ($mentions as $notes) {
        $repo = ReleaseRepo::make()->retagAs('v1.0.0');
        $repo->withNotes($notes);

        $run = $repo->release('--weigh', '--dry-run');

        expect($run->exitCode)->toBe(0, $run->describe())
            ->and($run->plan('bump'))->toStartWith('patch')
            ->and($run->plan('version'))->toBe('1.0.1');
    }

    $claims = [
        "### Fixed\n\n- A defect, fixed.\n\nBREAKING: the old way of doing it is gone.\n",
        "### Fixed\n\n- **BREAKING**: the old way of doing it is gone.\n",
        "### Fixed\n\n- A defect, fixed.\n\n> BREAKING: the old way of doing it is gone.\n",
    ];

    foreach ($claims as $notes) {
        $repo = ReleaseRepo::make()->retagAs('v1.0.0');
        $repo->withNotes($notes);

        // A claim is a major past 1.0.0, so the declared minor is refused with the signal named.
        $refused = $repo->release('--minor', '--skip-ci', '--yes');

        expect($refused->exitCode)->toBe(1, $refused->describe())
            ->and($refused->refused('--minor was declared, but the changes call for a major release.'))->toBeTrue()
            ->and($refused->refused('CHANGELOG'))->toBeTrue();
    }
});

it('holds a version named outright to the same floor as a declared bump', function (): void {
    $repo = ReleaseRepo::make();

    // 0.0.1 to 0.0.2 is a patch however the notes read, and these notes are `### Added`. A
    // version used to be the one declaration the policy did not measure: `--minor` was held to
    // the weighing and `--version` was not, so naming a number was a way around the rail rather
    // than a way of choosing inside it.
    $refused = $repo->release('--version=0.0.2', '--skip-ci', '--yes');

    expect($refused->exitCode)->toBe(1)
        ->and($refused->refused('--version=0.0.2 was declared, but the changes call for a minor release.'))->toBeTrue();

    // Overridden out loud rather than silently: a number below what the notes call for is a
    // decision, and the plan is where the decision is printed.
    $overridden = $repo->release('--version=0.0.2', '--ignore-policy', '--skip-ci', '--yes');

    expect($overridden->exitCode)->toBe(0, $overridden->describe())
        ->and($overridden->said('--ignore-policy: releasing 0.0.2, below the minor the changes call for'))->toBeTrue()
        ->and($overridden->plan('bump'))->toBe('patch  (declared: --version=0.0.2)')
        ->and($repo->tags())->toContain('v0.0.2');
});
