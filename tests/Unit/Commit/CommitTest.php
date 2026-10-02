<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The commit path
|--------------------------------------------------------------------------
|
| `bin/commit.php` is a named set with three promises: it carries exactly the paths it was told
| about and nothing else, it writes the changelog bullet with the change, and it runs the staged
| checks before it commits. Each test here puts the fixture in the state one of those promises is
| about — a stray path in the index, a changelog with no Unreleased section, a failing gate — and
| asserts the refusal, because a guard that only ever passes is a guard nobody can tell from one
| that stopped looking.
|
| @guards-index test
*/

/**
 * A fixture with this package's commit tool in it, committed so every test starts from a clean tree.
 */
function commitFixture(): ReleaseRepo
{
    $repo = ReleaseRepo::make();
    $repo->write('bin/commit.php', (string) file_get_contents(ReleaseRepo::package().'/bin/commit.php'));
    $repo->commit('chore: add the commit tool');

    return $repo;
}

it('commits the named set and writes the changelog bullet', function (): void {
    $repo = commitFixture();
    $repo->write('src/Thing.php', "<?php\n\n// changed\n");

    $run = $repo->script(
        'bin/commit.php',
        '-m', 'fix: a thing',
        '--changelog', 'A thing was fixed.',
        '--type', 'fixed',
        '--skip-checks',
        '--', 'src/Thing.php',
    );

    $files = $repo->git('show', '--name-only', '--format=', 'HEAD');
    $changelog = $repo->read('CHANGELOG.md');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($repo->git('log', '-1', '--format=%s'))->toContain('fix: a thing')
        ->and($files)->toContain('CHANGELOG.md')
        ->and($files)->toContain('src/Thing.php')
        ->and($changelog)->toContain('### Fixed')
        ->and($changelog)->toContain('- A thing was fixed.');
});

it('writes a section that is not there yet under the Unreleased heading', function (): void {
    $repo = commitFixture();
    $repo->write('src/Thing.php', "<?php\n\n// changed\n");

    $repo->script('bin/commit.php', '-m', 'fix: a thing', '--changelog', 'A thing.', '--type', 'fixed', '--skip-checks', '--', 'src/Thing.php');

    // The blank line after `## Unreleased`, and the one between the new entry and the older list.
    expect($repo->read('CHANGELOG.md'))
        ->toContain("## Unreleased\n\n### Fixed\n\n- A thing.\n\n### Added");
});

it('refuses an index that carries a path it was not told about', function (): void {
    $repo = commitFixture();
    $repo->write('src/Thing.php', "<?php\n\n// changed\n");
    $repo->write('stray.txt', "not part of this commit\n");
    $repo->git('add', 'stray.txt');

    $head = trim($repo->git('rev-parse', 'HEAD'));

    $run = $repo->script(
        'bin/commit.php',
        '-m', 'fix: a thing',
        '--changelog', 'A thing.',
        '--skip-checks',
        '--', 'src/Thing.php',
    );

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('was not told about'))->toBeTrue()
        ->and($run->refused('stray.txt'))->toBeTrue()
        ->and(trim($repo->git('rev-parse', 'HEAD')))->toBe($head)
        // A refusal changes nothing, so the stray stays staged exactly as it was found.
        ->and(trim($repo->git('diff', '--cached', '--name-only')))->toBe('stray.txt');
});

it('commits what is on disk rather than the copy that was staged', function (): void {
    $repo = commitFixture();
    $repo->write('src/Thing.php', "<?php\n\n// staged\n");
    $repo->git('add', 'src/Thing.php');
    $repo->write('src/Thing.php', "<?php\n\n// on disk\n");

    $run = $repo->script('bin/commit.php', '-m', 'fix: a thing', '--changelog', 'A thing.', '--skip-checks', '--', 'src/Thing.php');

    $committed = $repo->git('show', 'HEAD:src/Thing.php');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($committed)->toContain('// on disk')
        ->and($committed)->not->toContain('// staged');
});

it('refuses a named set with no paths', function (): void {
    $run = commitFixture()->script('bin/commit.php', '-m', 'fix: a thing', '--changelog', 'A thing.');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('no named paths'))->toBeTrue();
});

it('refuses a path outside the repository', function (): void {
    $repo = commitFixture();

    $absolute = commitFixture()->script('bin/commit.php', '-m', 'x', '--changelog', 'y', '--', '/etc/passwd');
    $climb = $repo->script('bin/commit.php', '-m', 'x', '--changelog', 'y', '--', '../outside.txt');

    expect($absolute->exitCode)->toBe(1)
        ->and($absolute->refused('not inside this repository'))->toBeTrue()
        ->and($climb->exitCode)->toBe(1)
        ->and($climb->refused('../outside.txt'))->toBeTrue();
});

it('refuses to write a bullet when none was given', function (): void {
    $run = commitFixture()->script('bin/commit.php', '-m', 'fix: a thing', '--', 'src/Thing.php');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('no changelog bullet'))->toBeTrue();
});

it('leaves the changelog alone when told there is nothing to write', function (): void {
    $repo = commitFixture();
    $repo->write('src/Thing.php', "<?php\n\n// changed\n");
    $before = $repo->read('CHANGELOG.md');

    $run = $repo->script('bin/commit.php', '-m', 'fix: a thing', '--no-changelog', '--skip-checks', '--', 'src/Thing.php');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($repo->read('CHANGELOG.md'))->toBe($before)
        ->and($repo->git('show', '--name-only', '--format=', 'HEAD'))->not->toContain('CHANGELOG.md');
});

it('refuses a changelog with no Unreleased section', function (): void {
    $repo = commitFixture();
    $repo->write('CHANGELOG.md', "# Changelog\n\n## [v0.0.1] - 2025-01-01\n\n- done\n");
    $repo->commit('docs: no unreleased section');
    $repo->write('src/Thing.php', "<?php\n\n// changed\n");

    $run = $repo->script('bin/commit.php', '-m', 'fix: a thing', '--changelog', 'A thing.', '--skip-checks', '--', 'src/Thing.php');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('no `## Unreleased` section'))->toBeTrue();
});

it('prints the plan and writes nothing on a dry run', function (): void {
    $repo = commitFixture();
    $repo->write('src/Thing.php', "<?php\n\n// changed\n");
    $head = trim($repo->git('rev-parse', 'HEAD'));
    $before = $repo->read('CHANGELOG.md');

    $run = $repo->script('bin/commit.php', '-m', 'fix: a thing', '--changelog', 'A thing.', '--skip-checks', '--dry-run', '--', 'src/Thing.php');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->said('src/Thing.php'))->toBeTrue()
        ->and($run->said('CHANGELOG.md'))->toBeTrue()
        ->and($repo->read('CHANGELOG.md'))->toBe($before)
        ->and(trim($repo->git('rev-parse', 'HEAD')))->toBe($head);
});

it('lists the resolved paths', function (): void {
    $run = commitFixture()->script('bin/commit.php', '--list', '--no-changelog', '--', './src/Thing.php');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and(trim($run->output))->toBe('src/Thing.php');
});

it('reads more paths from a file', function (): void {
    $repo = commitFixture();
    $repo->write('src/Thing.php', "<?php\n\n// changed\n");
    $repo->write('config/response-compression.php', "<?php\n\nreturn ['enabled' => false];\n");
    $repo->write('paths.txt', "src/Thing.php\n# a comment\nconfig/response-compression.php\n");

    $run = $repo->script('bin/commit.php', '-m', 'fix: two things', '--changelog', 'Two things.', '--skip-checks', '--paths-from-file', 'paths.txt');

    $files = $repo->git('show', '--name-only', '--format=', 'HEAD');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($files)->toContain('src/Thing.php')
        ->and($files)->toContain('config/response-compression.php')
        // The paths file itself is not part of the set unless it was named.
        ->and($files)->not->toContain('paths.txt');
});

it('reads the message from a file', function (): void {
    $repo = commitFixture();
    $repo->write('src/Thing.php', "<?php\n\n// changed\n");
    $repo->write('msg.txt', "fix: a thing\n\nThe body says why.\n");

    $run = $repo->script('bin/commit.php', '-F', 'msg.txt', '--changelog', 'A thing.', '--skip-checks', '--', 'src/Thing.php');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($repo->git('log', '-1', '--format=%B'))->toContain('The body says why.');
});

it('runs the staged checks and refuses a commit they fail', function (): void {
    $repo = commitFixture();
    $repo->write('src/Thing.php', "<?php\n\n// changed\n");
    $head = trim($repo->git('rev-parse', 'HEAD'));

    $run = $repo->script('bin/commit.php', '-m', 'fix: a thing', '--changelog', 'A thing.', '--', 'src/Thing.php');

    // The fixture's own gate is a stub that exits 0, so this is the passing half: the run reached
    // the checks and committed. The failing half is below.
    expect($run->exitCode)->toBe(0, $run->describe())
        ->and(trim($repo->git('rev-parse', 'HEAD')))->not->toBe($head);

    $broken = commitFixture();
    $broken->write('bin/checks.php', "#!/usr/bin/env php\n<?php\n\nexit(1);\n");
    $broken->commit('chore: break the gate');
    $broken->write('src/Thing.php', "<?php\n\n// changed\n");
    $brokenHead = trim($broken->git('rev-parse', 'HEAD'));

    $refused = $broken->script('bin/commit.php', '-m', 'fix: a thing', '--changelog', 'A thing.', '--', 'src/Thing.php');

    expect($refused->exitCode)->toBe(1)
        ->and($refused->refused('the staged checks failed'))->toBeTrue()
        ->and(trim($broken->git('rev-parse', 'HEAD')))->toBe($brokenHead);
});

it('refuses a command line it does not understand', function (): void {
    $run = commitFixture()->script('bin/commit.php', '--teleport', '--', 'src/Thing.php');

    expect($run->exitCode)->toBe(2)
        ->and($run->refused('unknown option'))->toBeTrue();
});
