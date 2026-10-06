<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The links a release repoints, and the push it offers
|--------------------------------------------------------------------------
|
| A changelog that keeps its compare links is one whose `[Unreleased]` link has to move to the
| version just cut, and whose new heading needs one of its own — where a changelog with no links
| gets none added, because a release writes the section rather than a convention nobody asked for.
| What the script says about pushing is the other half of the same shape: it offers the command and
| leaves the push to the person, unless `--push` was passed.
|
| A file of its own, because a file is what `paratest` spreads work by, and because every test here
| is about the text a release leaves in the file rather than about the tag it writes.
|
| @guards-index test
*/
it('repoints the compare links when the changelog keeps them', function (): void {
    $repo = ReleaseRepo::make()->withLinkReferences()->withRemote();

    $run = $repo->release('--weigh', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe());

    $changelog = $repo->read('CHANGELOG.md');

    expect($changelog)->toContain('[Unreleased]: https://example.test/repo/compare/v0.1.0...HEAD')
        ->and($changelog)->toContain('[v0.1.0]: https://example.test/repo/compare/v0.0.1...v0.1.0');
});

it('adds no compare links to a changelog that has none', function (): void {
    $repo = ReleaseRepo::make()->withRemote();

    $run = $repo->release('--weigh', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($repo->read('CHANGELOG.md'))->not->toContain('[v0.1.0]:');
});

it('pushes the branch and the tag when asked', function (): void {
    $repo = ReleaseRepo::make()->withRemote();

    $run = $repo->release('--weigh', '--yes', '--push');

    expect($run->exitCode)->toBe(0, $run->describe());

    $remote = $repo->git('ls-remote', '--tags', 'origin');

    expect($remote)->toContain('v0.1.0')
        ->and($remote)->toContain('v0.0.1');
});

it('says how to push when it was not asked to', function (): void {
    $repo = ReleaseRepo::make()->withRemote();

    $run = $repo->release('--weigh', '--yes');

    expect($run->said('git push origin main --follow-tags'))->toBeTrue()
        ->and($run->said('Packagist publishes from the tag'))->toBeTrue();
});
