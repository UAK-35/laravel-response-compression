<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The suffix Composer reads
|--------------------------------------------------------------------------
|
| A prerelease's number is chosen by the script, but its *spelling* is what a consumer installs
| against: Composer reads a fixed vocabulary, in lowercase, with the number attached the one way it
| expects — and a tag it cannot read is a version nobody can install, quietly. The refusals here are
| exit 2, because the argument itself is what is wrong.
|
| A file of its own, because a file is what `paratest` spreads work by, and because these tests ask
| the argument parser and the tags rather than a release's side effects.
|
| @guards-index test
*/
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
