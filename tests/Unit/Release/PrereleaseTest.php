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
| The line a prerelease opens, and the vocabulary its suffix is drawn from, are written in the files
| beside this one: a file is what `paratest` spreads work by, and each of these is a release run in
| a repository of its own.
|
| @guards-index test
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
