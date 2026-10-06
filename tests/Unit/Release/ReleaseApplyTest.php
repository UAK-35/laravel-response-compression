<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| What a release leaves behind
|--------------------------------------------------------------------------
|
| The plan is only half of it. These tests run the script for real, in a throwaway repository, and
| then read the repository rather than the report: the CHANGELOG's promoted heading, a commit whose
| subject names the version, an annotated tag, and a tree that is clean afterwards — because a
| release that plans the right number and writes the wrong file is the failure a plan-only test
| cannot see.
|
| stdin is closed for every run, so every run is non-interactive: `--yes` is what a test that wants
| the apply path passes, and it is the same flag CI has to pass. The links a changelog keeps, and
| the plan a dry run prints, are written in the files beside this one.
|
| @guards-index test
*/
it('promotes the notes, commits and tags', function (): void {
    $repo = ReleaseRepo::make()->withRemote();

    $run = $repo->release('--weigh', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->plan('version'))->toBe('0.1.0')
        ->and($run->plan('tag'))->toBe('v0.1.0');

    $changelog = $repo->read('CHANGELOG.md');

    // The notes moved rather than being copied: the fresh Unreleased section is above them and
    // the entries appear once.
    expect($changelog)->toContain('## Unreleased')
        ->and($changelog)->toContain('## [v0.1.0] - '.date('Y-m-d'))
        ->and(substr_count($changelog, '- A thing nobody had before.'))->toBe(1)
        ->and(strpos($changelog, '## Unreleased'))->toBeLessThan(strpos($changelog, '## [v0.1.0]'))
        ->and($changelog)->toContain('## [v0.0.1] - 2025-01-01');

    expect(trim($repo->git('log', '-1', '--format=%s')))->toBe('Release v0.1.0')
        ->and($repo->tags())->toContain('v0.1.0')
        // Annotated, not lightweight: Packagist publishes from the tag, and a release script
        // that writes the other kind leaves a version nobody agreed to.
        ->and(trim($repo->git('cat-file', '-t', 'v0.1.0')))->toBe('tag')
        ->and(trim($repo->git('status', '--porcelain', '--untracked-files=no')))->toBe('');
});

it('weighs a patch release when the notes are only fixes', function (): void {
    $repo = ReleaseRepo::make();
    $repo->notes("### Fixed\n\n- A ported defect, fixed.\n");
    $repo->commit('fix: the ported defect');

    // The remote is added last on purpose: it is pushed from HEAD, and the CI rail refuses a
    // commit the remote has not seen.
    $repo->withRemote();

    $run = $repo->release('--weigh', '--yes');

    expect($run->plan('version'))->toBe('0.0.2')
        ->and($run->plan('bump'))->toContain('patch')
        ->and($run->exitCode)->toBe(0, $run->describe());
});

it('leaves one blank line under the promoted heading', function (): void {
    // The notes arrive with the blank line that follows the Unreleased heading on their front. The
    // two headings the promotion assembles around them write their own spacing, so a body that
    // keeps its own puts two blank lines in the file — which is a diff against every hand-written
    // section above it.
    $repo = ReleaseRepo::make()->withRemote();

    expect($repo->release('--weigh', '--yes')->exitCode)->toBe(0);

    $changelog = $repo->read('CHANGELOG.md');

    expect($changelog)->toContain("## Unreleased\n\n## [v0.1.0] - ".date('Y-m-d')."\n\n### Added\n")
        ->and(preg_match('/\n{3,}/', $changelog))->toBe(0);
});

it('keeps the line endings the changelog was written with', function (): void {
    $repo = ReleaseRepo::make();

    // A CRLF file, which is what this package's own changelog is on the machine it is written
    // on. A release must not rewrite every line of it.
    $repo->write('CHANGELOG.md', str_replace("\n", "\r\n", $repo->read('CHANGELOG.md')));
    $repo->commit('docs: write the changelog on Windows');
    $repo->withRemote();

    $run = $repo->release('--weigh', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe());

    $changelog = $repo->read('CHANGELOG.md');

    expect($changelog)->toContain("\r\n")
        ->and($changelog)->toContain('## [v0.1.0] - '.date('Y-m-d'))
        ->and(preg_match('/(?<!\r)\n/', $changelog))->toBe(0);
});
