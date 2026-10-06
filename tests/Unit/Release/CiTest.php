<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The rails — the gate, the remote and the confirmation
|--------------------------------------------------------------------------
|
| What a release needs before it may be cut: a gate that passed, a commit the remote has built, a
| remote to compare against, a person who said yes, and arguments it understands. Each is a fact
| about a real repository and a real run of the script, so each is set up here as one.
|
| A file of its own rather than tests beside the branch rails, because a file is what `paratest`
| spreads work by: the branches and the gate are answered by the same program and have nothing to
| do with each other's fixtures.
|
| @guards-index test
|
*/

it('refuses to release over a red gate, and releases when told to skip it', function (): void {
    $repo = ReleaseRepo::make();
    $repo->failChecks();
    $repo->withRemote();

    $refused = $repo->release('--weigh', '--yes');

    expect($refused->exitCode)->toBe(1)
        ->and($refused->refused('`composer checks` did not pass'))->toBeTrue()
        ->and($refused->refused('--skip-checks'))->toBeTrue();

    $skipped = $repo->release('--weigh', '--skip-checks', '--yes');

    expect($skipped->exitCode)->toBe(0, $skipped->describe())
        ->and($skipped->plan('checks'))->toBe('not checked (--skip-checks)');
});

it('refuses a commit the remote has not seen', function (): void {
    $repo = ReleaseRepo::make()->withRemote();

    // A real change, because git refuses an empty commit and the rail under test is about what
    // the remote has built rather than about the tree.
    $repo->write('src/Extra.php', "<?php\n\ndeclare(strict_types=1);\n");
    $repo->commit('feat: something after the push');

    $refused = $repo->release('--weigh', '--yes');

    expect($refused->exitCode)->toBe(1)
        ->and($refused->refused('HEAD is not the tip of origin/main'))->toBeTrue()
        ->and($refused->refused('--skip-ci'))->toBeTrue();

    $skipped = $repo->release('--weigh', '--skip-ci', '--yes');

    expect($skipped->exitCode)->toBe(0, $skipped->describe());
});

it('refuses to release on a repository with no remote', function (): void {
    $run = ReleaseRepo::make()->release('--weigh', '--yes');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('has no head to compare against'))->toBeTrue();
});

it('refuses to commit and tag without a yes, off a terminal', function (): void {
    $run = ReleaseRepo::make()->withRemote()->release('--weigh');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('pass --yes'))->toBeTrue()
        ->and($run->refused('Nothing was released'))->toBeFalse();
});

it('refuses the arguments that are wrong before it looks at anything else', function (array $arguments): void {
    $run = ReleaseRepo::make()->release(...$arguments);

    // Exit 2, not 1: nothing was attempted, so nothing about the repository was in question.
    expect($run->exitCode)->toBe(2, $run->describe());
})->with([
    [['--patch']],
    [['--version=1.0.0', '--prerelease=alpha']],
    [['--version=1.0.0', '--minor']],
    [['--nope']],
]);
