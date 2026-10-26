<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The commit path
|--------------------------------------------------------------------------
|
| `bin/commit.php` is a named set with four promises: it carries exactly the paths it was told
| about and nothing else, it refuses a set that carries nothing of its own, it writes the changelog
| bullet with the change, and it runs the staged checks before it commits. Each test here puts the
| fixture in the state one of those promises is about — a stray path in the index, a set whose paths
| are already what `HEAD` holds, a changelog with no Unreleased section, a failing gate — and asserts
| the refusal, because a guard that only ever passes is a guard nobody can tell from one that
| stopped looking.
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

it('refuses a named set whose paths already hold what HEAD has', function (): void {
    $repo = commitFixture();
    $head = trim($repo->git('rev-parse', 'HEAD'));
    $before = $repo->read('CHANGELOG.md');

    // The state this refusal exists for: the work is already committed, and the set names the files
    // it left behind. Nothing in the tree is wrong, so every check the rest of this program runs
    // would pass — and the one thing the commit would carry is the bullet it was asked to write,
    // which is the changelog describing a change that is already in the history.
    $run = $repo->script(
        'bin/commit.php',
        '-m', 'fix: a thing',
        '--changelog', 'A thing.',
        '--skip-checks',
        '--', 'bin/commit.php',
    );

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('carries no change'))->toBeTrue()
        ->and($run->refused('bin/commit.php'))->toBeTrue()
        // A refusal that left the bullet behind would be worse than no guard: the changelog would
        // hold an entry for a commit nobody made.
        ->and($repo->read('CHANGELOG.md'))->toBe($before)
        ->and(trim($repo->git('rev-parse', 'HEAD')))->toBe($head)
        ->and(trim($repo->git('diff', '--cached', '--name-only')))->toBe('');

    // And the set that names nothing but the changelog, which is the same state said the other way:
    // the file is the one this program writes, so it cannot be the evidence that it should.
    $alone = $repo->script(
        'bin/commit.php',
        '-m', 'fix: a thing',
        '--changelog', 'A thing.',
        '--skip-checks',
        '--', 'CHANGELOG.md',
    );

    expect($alone->exitCode)->toBe(1)
        ->and($alone->refused('CHANGELOG.md alone'))->toBeTrue()
        ->and($repo->read('CHANGELOG.md'))->toBe($before);
});

it('refuses a set whose only change is the changelog an earlier group wrote', function (): void {
    $repo = commitFixture();
    $head = trim($repo->git('rev-parse', 'HEAD'));

    // The shape the eight groups had: every named path holds what `HEAD` has, and the changelog is
    // the one file that moved — because the group before this one put its bullet there and
    // `--no-changelog` hands the file to the caller, which is exactly how a session cutting groups
    // hands it over. A guard that read the changelog as evidence answered yes to a set with nothing
    // of its own in it, and the commit it cut published the earlier group's bullet a second time,
    // under a subject about work that was already in the history.
    $edited = str_replace(
        "## Unreleased\n\n",
        "## Unreleased\n\n- **A bullet the group before this one wrote.**\n",
        $repo->read('CHANGELOG.md'),
    );
    $repo->write('CHANGELOG.md', $edited);

    $run = $repo->script(
        'bin/commit.php',
        '-m', 'fix: a group with nothing left in it',
        '--no-changelog',
        '--skip-checks',
        '--', 'CHANGELOG.md', 'bin/commit.php', 'composer.json', 'src/Thing.php',
    );

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('carries no change of its own'))->toBeTrue()
        ->and($run->refused('src/Thing.php'))->toBeTrue()
        // A refusal leaves the entry where it found it: the commit that publishes it is the one whose
        // paths say what it is about, which is the eight-group reading of the same rule.
        ->and($repo->read('CHANGELOG.md'))->toBe($edited)
        ->and(trim($repo->git('rev-parse', 'HEAD')))->toBe($head)
        ->and(trim($repo->git('diff', '--cached', '--name-only')))->toBe('');

    // And the one shape where a changelog edit *is* the commit still commits: named alone, with the
    // bullet handed over rather than written, because there is nothing else the set could be about.
    $alone = $repo->script('bin/commit.php', '-m', 'docs: the entry is the change', '--no-changelog', '--skip-checks', '--', 'CHANGELOG.md');

    expect($alone->exitCode)->toBe(0, $alone->describe())
        ->and(trim($repo->git('show', '--name-only', '--format=', 'HEAD')))->toBe('CHANGELOG.md');
});

it('commits a changelog edit of its own when no bullet is being written', function (): void {
    $repo = commitFixture();
    $before = $repo->read('CHANGELOG.md');

    // `--no-changelog` is the caller saying the changelog is theirs this time, so an edit to it is
    // evidence like any other path: a changelog fix is a commit, and a guard that refused it would
    // be answering for more than the state it asks about.
    $repo->write('CHANGELOG.md', $before."\n- A typo fixed.\n");

    $run = $repo->script('bin/commit.php', '-m', 'docs: fix a typo', '--no-changelog', '--skip-checks', '--', 'CHANGELOG.md');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($repo->git('show', 'HEAD:CHANGELOG.md'))->toContain('- A typo fixed.');
});

it('commits a set when one of its paths has moved', function (): void {
    $repo = commitFixture();
    $repo->write('src/Thing.php', "<?php\n\n// new\n");

    // The guard asks whether *any* named path moved, not whether all of them did. `bin/commit.php`
    // is named here and is untouched, and a file that stages nothing beside one that stages its
    // content is an ordinary commit — the refusal is about a set that carries nothing at all.
    $run = $repo->script(
        'bin/commit.php',
        '-m', 'feat: a thing',
        '--changelog', 'A thing.',
        '--skip-checks',
        '--', 'bin/commit.php', 'src/Thing.php',
    );

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($repo->git('show', '--name-only', '--format=', 'HEAD'))->toContain('src/Thing.php');
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

it('writes a bullet into a changelog that arrived with CRLF endings', function (): void {
    $repo = commitFixture();
    $repo->write('CHANGELOG.md', str_replace("\n", "\r\n", $repo->read('CHANGELOG.md')));
    $repo->commit('docs: a changelog saved with Windows endings');
    $repo->write('src/Thing.php', "<?php\n\n// changed\n");

    $run = $repo->script('bin/commit.php', '-m', 'fix: a thing', '--changelog', 'A thing.', '--type', 'fixed', '--skip-checks', '--', 'src/Thing.php');
    $changelog = $repo->read('CHANGELOG.md');

    // The pin in `.gitattributes` decides what a checkout writes, not what a tree already holds, so
    // the section is found in the endings the file has and the bullet is joined with those same
    // endings. The lone-LF assertion is the half a fix that only tolerated `\r\n` would fail: a
    // bullet written with `\n` into a CRLF file is the whole file in the next diff.
    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($changelog)->toContain("## Unreleased\r\n\r\n### Fixed\r\n\r\n- A thing.\r\n\r\n### Added")
        ->and($changelog)->not->toMatch('/(?<!\r)\n/');
});

it('joins a CRLF changelog section that is already there', function (): void {
    $repo = commitFixture();
    $repo->write('CHANGELOG.md', str_replace("\n", "\r\n", $repo->read('CHANGELOG.md')));
    $repo->commit('docs: a changelog saved with Windows endings');
    $repo->write('src/Thing.php', "<?php\n\n// changed\n");

    // `### Added` is the section the fixture already carries, so this is the other branch: the
    // blank line under the heading is stepped over and the bullet lands above the older entry.
    $run = $repo->script('bin/commit.php', '-m', 'feat: a thing', '--changelog', 'A thing.', '--type', 'added', '--skip-checks', '--', 'src/Thing.php');
    $changelog = $repo->read('CHANGELOG.md');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($changelog)->toContain("### Added\r\n\r\n- A thing.\r\n- A thing nobody had before.")
        ->and($changelog)->not->toMatch('/(?<!\r)\n/');
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

it('leaves no bullet behind when the staged checks abandon the run', function (): void {
    $repo = commitFixture();
    $repo->write('bin/checks.php', "#!/usr/bin/env php\n<?php\n\nexit(1);\n");
    $repo->commit('chore: break the gate');
    $repo->write('src/Thing.php', "<?php\n\n// changed\n");

    $head = trim($repo->git('rev-parse', 'HEAD'));
    $before = $repo->read('CHANGELOG.md');

    $run = $repo->script('bin/commit.php', '-m', 'fix: a thing', '--changelog', 'A thing.', '--', 'src/Thing.php');

    // The bullet is written before the checks run, so a red gate is the one state where the
    // changelog already says something the commit does not: an entry for work nobody committed, in
    // the file everybody reads to find out what was. Both halves are asserted, because either one
    // alone leaves a tree that reads as if the commit happened.
    expect($run->exitCode)->toBe(1)
        ->and($run->refused('the staged checks failed'))->toBeTrue()
        ->and($run->refused('put back as this run found them'))->toBeTrue()
        ->and($repo->read('CHANGELOG.md'))->toBe($before)
        ->and($repo->read('CHANGELOG.md'))->not->toContain('A thing.')
        // And the index is where it was: nothing staged, because this run staged and then stopped.
        ->and(trim($repo->git('diff', '--cached', '--name-only')))->toBe('')
        ->and(trim($repo->git('rev-parse', 'HEAD')))->toBe($head)
        // The edit itself is still the caller's to commit — an abandoned run is not a revert.
        ->and($repo->read('src/Thing.php'))->toContain('// changed');
});

it('puts the index back with exactly what it found staged', function (): void {
    $repo = commitFixture();
    $repo->write('bin/checks.php', "#!/usr/bin/env php\n<?php\n\nexit(1);\n");
    $repo->commit('chore: break the gate');

    // A set the guard allows to be half staged already — a path in the set, staged before the run
    // — beside one that is only in the working tree. Restoring the index by emptying it would pass
    // the test above and be wrong here: what this run found is one staged path, not none.
    $repo->write('src/Thing.php', "<?php\n\n// staged before the run\n");
    $repo->git('add', 'src/Thing.php');
    $repo->write('src/Other.php', "<?php\n\n// only on disk\n");
    $before = $repo->read('CHANGELOG.md');

    $run = $repo->script(
        'bin/commit.php',
        '-m', 'fix: two things',
        '--changelog', 'Two things.',
        '--', 'src/Other.php', 'src/Thing.php',
    );

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('the staged checks failed'))->toBeTrue()
        // The index holds the one path it held, and the other is back to a working-tree edit the
        // caller can still see in `git status` rather than a staged change nobody made.
        ->and(trim($repo->git('diff', '--cached', '--name-only')))->toBe('src/Thing.php')
        // `src/Other.php` is a file git has never held, so what it has to be back to is untracked:
        // `git diff` does not name one of those, and only the status can say it is still there.
        ->and($repo->git('status', '--porcelain'))->toContain('src/Other.php')
        ->and($repo->read('src/Other.php'))->toContain('// only on disk')
        ->and($repo->read('CHANGELOG.md'))->toBe($before);
});

it('refuses a command line it does not understand', function (): void {
    $run = commitFixture()->script('bin/commit.php', '--teleport', '--', 'src/Thing.php');

    expect($run->exitCode)->toBe(2)
        ->and($run->refused('unknown option'))->toBeTrue();
});
