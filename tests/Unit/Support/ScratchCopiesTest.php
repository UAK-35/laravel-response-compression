<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Ignored;
use Uak35\ResponseCompression\Tests\Support\MachinePaths;
use Uak35\ResponseCompression\Tests\Support\Notes;
use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;
use Uak35\ResponseCompression\Tests\Support\ScratchCopies;

/*
|--------------------------------------------------------------------------
| The copies in the tree that git does not carry
|--------------------------------------------------------------------------
|
| A file is broken to prove a guard fires and then put back by copying, because a `git checkout`
| would take a session's own uncommitted work with it — so `build/head/<path>` and
| `build/authored/<path>` hold what the file was before and after, and `build` is ignored, so
| nothing about either appears in `git status`. `MachinePaths` and `RepoEscapes` read the files a
| commit would carry and `Ignored` reads what a rule holds that records a machine elsewhere, so a
| copy of this package's own file is in none of the three answers.
|
| So the reading is over the other set: every file git does not have, untracked or ignored, read
| for the two shapes a copy is named by. It answers with names rather than findings, because a
| clone with no copy has to stay green and the copies that are here are this repository's own way
| of working rather than a mistake in it. What is held here is that the names are said — on
| fixtures for each shape, on the files that are named after nothing, and from this checkout's own
| listing, written into the note channel a run prints.
|
| The breaks a mutated tree would have to plant are not this test's: the reading is a note rather
| than a guard, so the mutation harness has nothing to turn red, and the substance is asked here —
| a copy of either shape, a copy that has been committed, the files whose names copy nothing, and a
| tree where git cannot be asked.
|
*/

/**
 * @guards-index test
 *
 * the files git does not have that are named after ones it does — read as a set of their own, so a
 * scratch copy of a tracked file is named rather than left where only the ignore rules can see it:
 * the two shapes a copy is read by, what is not a copy, and what a run prints
 */
it('names a copy a scratch directory mirrors and a copy left beside the file', function (): void {
    $repo = ReleaseRepo::make();

    $repo->write('.gitignore', "build\n");
    $repo->write('src/Thing.php', "<?php\n\n// the file this package owns\n");
    $repo->commit('feat: a file worth copying');

    // The two shapes, which is what the workaround actually leaves: a whole checkout's worth of
    // files copied under a scratch directory, and the one copy left beside the file it was taken
    // from when a single file was put back.
    $repo->write('build/head/src/Thing.php', "<?php\n\n// what HEAD held\n");
    $repo->write('src/Thing.php.orig', "<?php\n\n// what this session had written\n");

    expect(ScratchCopies::copies($repo->path))->toBe([
        'build/head/src/Thing.php' => 'src/Thing.php',
        'src/Thing.php.orig' => 'src/Thing.php',
    ]);

    // And the listing is checked for substance before the answer is believed: a reading over "the
    // files this tree does not carry" that read no files would satisfy the claim about the copies
    // by having nothing to report them from.
    expect(ScratchCopies::files($repo->path))->toContain('build/head/src/Thing.php')
        ->and(ScratchCopies::files($repo->path))->toContain('src/Thing.php.orig')
        // The ignored half is the one the two neighbouring readings are reading, which is why this
        // one reads both: `MachinePaths::untracked()` cannot see the copy under `build/`, and
        // `Ignored::files()` cannot see the one beside the file.
        ->and(MachinePaths::untracked($repo->path))->not->toContain('build/head/src/Thing.php')
        ->and(Ignored::files($repo->path))->toContain('build/head/src/Thing.php')
        ->and(Ignored::files($repo->path))->not->toContain('src/Thing.php.orig');
});

it('says nothing about a copy that is itself in the commit', function (): void {
    $repo = ReleaseRepo::make();

    $repo->write('.gitignore', "build\n");
    $repo->write('src/Thing.php', "<?php\n\n// the file this package owns\n");
    $repo->commit('feat: a file worth copying');

    // The same path, and git has it: an index entry is listed by `git ls-files --cached` whatever
    // the ignore rules say, so a copy that has been committed is a file a commit carries rather
    // than a copy sitting in the tree, and this reading reads only the files git does not have.
    $repo->write('build/head/src/Thing.php', "<?php\n\n// what HEAD held\n");
    $repo->git('add', '-f', 'build/head/src/Thing.php');
    $repo->commit('chore: keep the copy');

    expect(ScratchCopies::copies($repo->path))->toBe([])
        ->and(ScratchCopies::files($repo->path))->not->toContain('build/head/src/Thing.php')
        ->and(MachinePaths::files($repo->path))->toContain('build/head/src/Thing.php');
});

it('says nothing about the files git does not have that are named after nothing', function (): void {
    $repo = ReleaseRepo::make();

    $repo->write('.gitignore', "build\nvendor\n");
    $repo->write('README.md', "# The package\n");
    $repo->write('src/Thing.php', "<?php\n\n// the file this package owns\n");
    $repo->commit('feat: a file worth copying');

    // Four shapes a rule written around names has to leave alone. A mark with no tracked path in
    // front of it, which is the scratch a session leaves that copies nothing. A root-level name
    // mirrored under a directory, which is every dependency's readme. And the copy whose name says
    // nothing about the file it copies: the same bytes, kept under a name of their own — the one
    // thing a reading that never opens a file cannot be shown.
    $repo->write('build/notes.md', "# Notes\n");
    $repo->write('build/thing.txt', "a session's scratch copy of nothing in particular\n");
    $repo->write('vendor/acme/README.md', "# A dependency's readme\n");
    $repo->write('build/kept-under-another-name', $repo->read('src/Thing.php'));

    expect(ScratchCopies::copies($repo->path))->toBe([])
        ->and(ScratchCopies::files($repo->path))->toContain('build/notes.md')
        ->and(ScratchCopies::files($repo->path))->toContain('build/thing.txt')
        ->and(ScratchCopies::files($repo->path))->toContain('vendor/acme/README.md')
        ->and(ScratchCopies::files($repo->path))->toContain('build/kept-under-another-name');
});

it('refuses a tree it cannot ask git about, rather than reporting that nothing is there', function (): void {
    // The answer a reading must not give twice: "there is no copy here" and "git was never asked"
    // are the same empty list, and only one of them is a reading. A tree with no repository around
    // it is the case, and the refusal is what the test asserts on.
    $tree = ReleaseRepo::plain();

    expect(fn (): array => ScratchCopies::files($tree->path))
        ->toThrow(RuntimeException::class);

    expect(fn (): array => ScratchCopies::copies($tree->path))
        ->toThrow(RuntimeException::class);
});

it('names what it finds in this checkout, in the channel a run prints', function (): void {
    // The other half of "named rather than hidden": the reading's answer about this checkout is put
    // where a session reads it — the suite prints the note, `bin/checks.php` lifts it into the
    // summary, and `python .agents/verify.py` prints what the checks printed. A clone with no copy
    // adds no note at all, which is the same answer the reading gives.
    Notes::clear();

    // The listing once, because asking git for it is the expensive half on a machine with its
    // dependencies installed: this checkout has thirteen thousand files under `vendor/`, and the
    // commits of the untracked half are work this reading does over the whole of it.
    $files = ScratchCopies::files(packageRoot());
    $copies = ScratchCopies::copies(packageRoot());

    $sentence = static fn (string $copy, string $original): string => sprintf(
        '%s is a copy of %s — a scratch copy of a tracked file, and no commit carries it',
        $copy,
        $original,
    );

    foreach ($copies as $copy => $original) {
        Notes::add(ScratchCopies::class, $sentence($copy, $original), packageRoot());
    }

    // Every copy it answered with has to be a file git does not have: the reading's subject is that
    // listing, and a name from anywhere else would be a second, unseen source of truth.
    expect(array_values(array_diff(array_keys($copies), $files)))->toBe([])
        ->and(Notes::all())->toBe(array_map(
            static fn (string $copy): string => Notes::MARKER.' ScratchCopies: '.$sentence($copy, $copies[$copy]),
            array_keys($copies),
        ));
});
