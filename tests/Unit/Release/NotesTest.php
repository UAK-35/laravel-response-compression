<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The rails — the notes a release would publish
|--------------------------------------------------------------------------
|
| A release publishes the `## Unreleased` section and promotes it under the tag. So the notes are
| what the rails have to be able to read: a section that is there, a section with something in it,
| a version the changelog has not already documented, and — where a version has both a heading and
| a tag — the tag's own answer rather than the changelog's.
|
| A file of its own, because a file is what `paratest` spreads work by, and because these rails
| share a fixture shape nothing else here has: a changelog edited by hand.
|
| @guards-index test
|
*/

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
        ->and($run->refused('--version=0.2.0'))->toBeTrue()
        // And the other number: the tag sequence's own next step, which is the one this package
        // took — above the newest tag, and claimed by no heading.
        ->and($run->refused('--version=0.0.2'))->toBeTrue();

    $released = $repo->release('--version=0.2.0', '--skip-ci', '--yes');

    expect($released->exitCode)->toBe(0, $released->describe())
        ->and($released->plan('tag'))->toBe('v0.2.0')
        ->and($repo->tags())->toContain('v0.2.0');
});
