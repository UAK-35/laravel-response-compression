<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
 * `extra.branch-alias` gives the two dev lanes a version to resolve as, so a consumer requiring
 * `^0.0` can install `dev-main` as readily as a tag. Both aliases name the *line* being
 * developed, so a patch release leaves them alone and a release that opens a line moves them —
 * which is the whole rule, and the reason the tool owns exactly those two keys and no others.
 */

it('points the dev lanes at the new line when a release opens one', function (): void {
    $repo = ReleaseRepo::make();

    $run = $repo->release('--version=0.1.0', '--skip-ci', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->plan('branch-alias'))->toContain('-> 0.1.x-dev')
        ->and($run->plan('branch-alias'))->toContain('(updated)');

    // Only the two values are touched: the rest of composer.json is left byte for byte, because a
    // release commit that reformats the file is a diff nobody can review.
    expect($repo->read('composer.json'))->toContain('"dev-main": "0.1.x-dev"')
        ->and($repo->read('composer.json'))->toContain('"dev-development": "0.1.x-dev"')
        ->and($repo->read('composer.json'))->toContain('"description": "Release fixture"')
        ->and($repo->read('composer.json'))->toContain('"php": "^8.4"');

    expect($repo->git('show', '--stat', '--format=', 'HEAD'))->toContain('composer.json');
});

it('leaves the alias alone for a patch on the line already being developed', function (): void {
    // The weighing decides whether the aliases need moving, so the notes are what make this a
    // patch: the fixture's own `### Added` would open a new line and move them.
    $repo = ReleaseRepo::make()->withNotes("### Fixed\n\n- A defect, fixed.\n");

    $run = $repo->release('--weigh', '--skip-ci', '--yes');

    expect($run->plan('version'))->toBe('0.0.2')
        ->and($run->plan('branch-alias'))->toContain('-> 0.0.x-dev')
        ->and($run->plan('branch-alias'))->toContain('(unchanged)')
        // Not written, so not in the commit: rewriting composer.json to say what it already said
        // is a release commit nobody asked for.
        ->and($repo->git('show', '--stat', '--format=', 'HEAD'))->not()->toContain('composer.json');
});

it('says it owns no alias rather than inventing one', function (): void {
    $repo = ReleaseRepo::make();

    $repo->write('composer.json', "{\n    \"name\": \"uak35/laravel-response-compression\",\n    \"type\": \"library\"\n}\n");
    $repo->commit('chore: no branch aliases');

    $run = $repo->release('--weigh', '--skip-ci', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->plan('branch-alias'))->toBe('none for the dev lanes in composer.json — left alone')
        ->and($repo->read('composer.json'))->not()->toContain('branch-alias');
});
