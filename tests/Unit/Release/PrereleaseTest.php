<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The prerelease lanes
|--------------------------------------------------------------------------
|
| A prerelease is a version like any other, and the numbering is the whole of the difference: it
| is cut ahead of the release it is named after, it keeps that release's line, and its number is
| counted from the tags rather than remembered. These tests run the script in a real repository to
| hold each of those, because every one of them is a question about tags.
|
| The runs after the first pass `--skip-ci`: a second release in the same fixture is a commit the
| remote has not seen, and the rail that refuses that is tested on its own in RailsTest.
|
*/

it('cuts the first alpha of a line', function (): void {
    $repo = ReleaseRepo::make()->withRemote();

    $run = $repo->release('--prerelease=alpha', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->plan('version'))->toBe('0.1.0-alpha1')
        ->and($run->plan('tag'))->toBe('v0.1.0-alpha1')
        ->and($run->plan('stability'))->toContain('ahead of v0.1.0')
        ->and($run->said('minimum-stability'))->toBeTrue();

    expect($repo->tags())->toContain('v0.1.0-alpha1')
        ->and(trim($repo->git('log', '-1', '--format=%s')))->toBe('Release v0.1.0-alpha1')
        ->and($repo->read('CHANGELOG.md'))->toContain('## [v0.1.0-alpha1] - '.date('Y-m-d'));
});

it('numbers the lane from the tags, and keeps the line it is on', function (): void {
    $repo = ReleaseRepo::make()->withRemote();
    $repo->release('--prerelease=alpha', '--yes');

    // The notes say `### Added`, which weighs a minor. The line still does not move: 0.1.0 was
    // chosen when the first alpha was cut, and `--weigh` reads the notes rather than deciding
    // which line they belong to.
    $repo->withNotes("### Added\n\n- More of the same line.\n");

    $run = $repo->release('--prerelease=alpha', '--skip-ci', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->plan('version'))->toBe('0.1.0-alpha2');
});

it('moves the lane without moving the line', function (): void {
    $repo = ReleaseRepo::make()->withRemote();
    $repo->release('--prerelease=alpha', '--yes');
    $repo->withNotes("### Fixed\n\n- Something the alpha got wrong.\n");

    $run = $repo->release('--prerelease=beta', '--skip-ci', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->plan('version'))->toBe('0.1.0-beta1');
});

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

it('refuses a tag that already exists and names the next free number', function (): void {
    $repo = ReleaseRepo::make()->withRemote();
    $repo->release('--prerelease=alpha', '--yes');

    $run = $repo->release('--version=0.1.0-alpha1', '--skip-ci', '--yes');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('v0.1.0-alpha1 already exists'))->toBeTrue()
        ->and($run->refused('The next free number in that lane is 0.1.0-alpha2.'))->toBeTrue();
});

it('writes the suffix the way Composer reads it, and refuses the ways it does not', function (string $version, string $expected): void {
    $run = ReleaseRepo::make()->release('--version='.$version, '--yes');

    // Exit 2, not 1: the argument itself is wrong, so nothing was attempted — and a tag Composer
    // cannot parse is one nobody can install, quietly.
    expect($run->exitCode)->toBe(2, $run->describe())
        ->and($run->refused($expected))->toBeTrue();
})->with([
    ['0.1.0-alpha.1', 'the dot is a second spelling'],
    ['0.1.0-dev.1', 'is not a version Composer will read'],
    ['0.1.0-nightly', 'Composer reads a fixed vocabulary'],
    ['0.1.0-rc', 'a lane takes a number'],
    ['0.1.0-ALPHA1', 'the lane is written in lowercase'],
]);

it('accepts a bare dev suffix, which takes no number', function (): void {
    $repo = ReleaseRepo::make()->withRemote();

    $run = $repo->release('--version=0.1.0-dev', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($repo->tags())->toContain('v0.1.0-dev')
        ->and($run->said('@dev'))->toBeTrue();
});

it('refuses a lane it does not write', function (): void {
    $run = ReleaseRepo::make()->release('--prerelease=gamma');

    expect($run->exitCode)->toBe(2, $run->describe())
        ->and($run->refused('--prerelease takes alpha, beta or rc'))->toBeTrue();
});
