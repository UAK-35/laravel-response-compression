<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The rails
|--------------------------------------------------------------------------
|
| A release script is mostly refusal. What it writes is a changelog heading, a commit and a tag;
| what it has to be trusted for is refusing when the tag would be a lie — cut from the wrong
| branch, over uncommitted work, over a version that already exists, on a commit CI has not built,
| or publishing notes nobody wrote.
|
| Each test here sets up exactly the condition its rail is about and asserts the refusal, then
| (where the rail has an escape) that the escape releases. The condition is a fact about a real
| repository, which is why the fixture is one.
|
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

it('refuses a changelog with no Unreleased section', function (): void {
    $run = ReleaseRepo::make()->withoutUnreleased()->release('--weigh', '--yes');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('has no `## Unreleased` section'))->toBeTrue()
        ->and($run->plan('changelog'))->toContain('not promoted');
});

it('refuses a release that would publish no notes', function (): void {
    $run = ReleaseRepo::make()->withEmptyUnreleased()->release('--weigh', '--yes');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('`## Unreleased` section is empty'))->toBeTrue();
});

it('answers with the tag, not the heading, when a version has both', function (): void {
    $repo = ReleaseRepo::make();

    // Documented *and* tagged is a version that was released from here, and the two rails overlap
    // on it. The tag rail is asked first, and it is the one that answers: a message about the
    // changelog would send a reader looking for a second promotion that never happened.
    $repo->write('CHANGELOG.md', str_replace(
        '## [v0.0.1]',
        "## [v0.1.0] - 2025-06-01\n\n### Added\n\n- Something already released.\n\n## [v0.0.1]",
        $repo->read('CHANGELOG.md'),
    ));
    $repo->commit('docs: a section for a version that is tagged');
    $repo->retagAs('v0.1.0');

    $run = $repo->release('--version=0.1.0', '--skip-ci', '--yes');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('v0.1.0 already exists'))->toBeTrue()
        ->and($run->refused('already has a section for'))->toBeFalse();
});

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

it('weighs a change to the public surface as breaking', function (): void {
    $repo = ReleaseRepo::make()->retagAs('v1.0.0');
    $repo->withoutPublicSymbol();
    $repo->withNotes("### Fixed\n\n- Nothing to see here.\n");

    $run = $repo->release('--weigh', '--skip-ci', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->plan('surface'))->toContain('breaking')
        ->and($run->plan('version'))->toBe('2.0.0');
});

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

it('refuses a version the changelog already documents, and names the way out', function (): void {
    $repo = ReleaseRepo::make();

    // The weighed release would be 0.1.0, and the changelog already has a section for it — with no
    // tag behind it, which is what an inherited heading looks like.
    $repo->write('CHANGELOG.md', str_replace(
        '## [v0.0.1]',
        "## [v0.1.0] - 2025-06-01\n\n### Added\n\n- Something already released.\n\n## [v0.0.1]",
        $repo->read('CHANGELOG.md'),
    ));
    $repo->commit('docs: a section for a version never tagged');

    $run = $repo->release('--weigh', '--skip-ci', '--yes');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('already has a section for v0.1.0'))->toBeTrue()
        // The rail is only reachable for a heading with no tag behind it, and saying so is the
        // difference between a reader who checks the tags and one who goes looking for a run that
        // promoted these notes twice.
        ->and($run->refused('no v0.1.0 tag exists'))->toBeTrue()
        // The way out is a number, not an error message: one above every version written down.
        ->and($run->refused('--version=0.2.0'))->toBeTrue();

    $released = $repo->release('--version=0.2.0', '--skip-ci', '--yes');

    expect($released->exitCode)->toBe(0, $released->describe())
        ->and($released->plan('tag'))->toBe('v0.2.0')
        ->and($repo->tags())->toContain('v0.2.0');
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
