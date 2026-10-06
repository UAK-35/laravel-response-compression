<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The rails — where a release may be cut from
|--------------------------------------------------------------------------
|
| A release script is mostly refusal, and the first thing it has to be trusted for is refusing a
| release cut from the wrong place: a directory that is not a repository, a tree with uncommitted
| work in it, a branch that is not the lane, a lane the repository has not declared. Each of those
| is a fact about a real repository, which is why the fixture is one.
|
| The rails about what a release publishes, and about the number it takes, are asked the same way
| in the files beside this one — a rail each, because a file is what `paratest` spreads work by and
| what the mutation harness hands to a child process. This is the file the branch rail is taken out
| of, so the rails that are about the branch are the ones that stay in it.
|
*/

/**
 * @guards-index guard the release rails
 *
 * the rails of `bin/release.php` that decide where a release may be cut from — no repository, uncommitted work, the wrong branch, the lane the repository declares — each refusing the condition it is about through the fixture hand that puts a rewritten releaser in front of this test, which is the file the mutation harness takes the branch rail out of; the rails about what a release publishes and the number it takes are asked the same way in the files beside it
 */
it('refuses to release where there is no repository', function (): void {
    $run = ReleaseRepo::plain()->release('--weigh', '--yes');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('is not a git repository'))->toBeTrue();
});

it('refuses a dirty tree, and releases it when told to', function (): void {
    $repo = ReleaseRepo::make()->withRemote();

    // Uncommitted, so the tag would point at a commit that does not contain this.
    $repo->write('src/Thing.php', "<?php\n\n// not committed\n");

    $refused = $repo->release('--weigh', '--yes');

    expect($refused->exitCode)->toBe(1)
        ->and($refused->refused('Tracked files have uncommitted changes'))->toBeTrue()
        ->and($refused->refused('--allow-dirty'))->toBeTrue();

    $released = $repo->release('--weigh', '--allow-dirty', '--yes');

    expect($released->exitCode)->toBe(0, $released->describe());
});

it('refuses to release from the wrong branch', function (): void {
    $run = ReleaseRepo::make()->release('--branch=release', '--weigh', '--yes');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('A release is cut from release, and HEAD is on main.'))->toBeTrue();
});

it('releases from the lane a repository declares, with no flag to say it', function (): void {
    $repo = ReleaseRepo::make();

    // A tree on `development` with the lane declared in `.release-lane`: the two together are what a
    // bare run has to read. `withRemote()` cannot be used - it pushes `main` - so the run is a plan,
    // which publishes nothing and reports the CI rail rather than enforcing it.
    $repo->git('checkout', '-b', 'development');
    $repo->write('.release-lane', "development\n");
    $repo->commit('chore: declare the lane');

    $run = $repo->release('--weigh', '--dry-run');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->plan('branch'))->toBe('development');
});

it('falls back to main when a repository declares no lane', function (): void {
    $repo = ReleaseRepo::make();
    $repo->git('checkout', '-b', 'development');

    // The same tree without the file: the rail is built from `main`, the name a release is cut from by
    // convention and the answer this program gave before there was a file to read.
    $run = $repo->release('--weigh', '--dry-run');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('A release is cut from main, and HEAD is on development.'))->toBeTrue();
});
