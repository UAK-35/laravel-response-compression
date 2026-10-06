<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The line a prerelease opens, and where it ends
|--------------------------------------------------------------------------
|
| A lane keeps the line it is on, and a line opens when the bump is declared rather than when the
| notes change: the same 0.1.0 carries alpha, beta and rc until something says the line is done, and
| the release that ends it promotes those notes to a version rather than opening another.
|
| A file of its own, because a file is what `paratest` spreads work by, and because every test here
| is a second or third release in one repository — the fixture state the lane's numbering is asked
| about.
|
| @guards-index test
*/
it('promotes a prerelease to its release', function (): void {
    $repo = ReleaseRepo::make()->withRemote();
    $repo->release('--prerelease=alpha', '--yes');
    $repo->withNotes("### Fixed\n\n- Something the alpha got wrong.\n");

    $run = $repo->release('--weigh', '--skip-ci', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->plan('version'))->toBe('0.1.0')
        ->and($run->plan('stability'))->toBe('stable')
        ->and($run->said('promotes its line to a release'))->toBeTrue()
        ->and($repo->tags())->toContain('v0.1.0')
        ->and(trim($repo->git('cat-file', '-t', 'v0.1.0')))->toBe('tag');
});

it('opens a new line when the bump is declared', function (): void {
    $repo = ReleaseRepo::make()->withRemote();
    $repo->release('--prerelease=alpha', '--yes');
    $repo->withNotes("### Added\n\n- Enough of it to call the line done.\n");

    $run = $repo->release('--minor', '--prerelease=alpha', '--skip-ci', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->plan('version'))->toBe('0.2.0-alpha1');
});

it('refuses a prerelease once its release exists', function (): void {
    // v0.1.0 is out, so 0.1.0-alpha1 precedes a version that is already published: a prerelease
    // is not newer than the release it is named after.
    $repo = ReleaseRepo::make()->retagAs('v0.1.0');

    $run = $repo->release('--version=0.1.0-alpha1', '--skip-ci', '--yes');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('cannot be cut once the release it is named after exists'))->toBeTrue();
});
