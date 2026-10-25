<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Ignored;
use Uak35\ResponseCompression\Tests\Support\Notes;
use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| What the ignore rules are holding
|--------------------------------------------------------------------------
|
| `MachinePaths` and `RepoEscapes` read the files a commit would carry, and an ignored file is in
| neither answer. That is right for a rule doing its job and wrong for one holding somebody else's
| machine record — which is what `*.txt` did here until it was narrowed: it kept
| `.agents/sample.machine.local.json.txt` out of `git status`, out of both path readings, and out of
| every word a session reads.
|
| So the reading is over the other set: the files git does not have and the rules keep out of a
| commit. It answers with names rather than findings, because a clone with no spill must stay green,
| and the record that is here is left in place by a decision rather than adopted. What is held here
| is that the names are said — on a fixture that has a spill, in the note channel a run prints, and
| from this checkout's own listing, where the answer today is nothing because the record is once
| again a file a commit would carry.
|
| The breaks a mutated tree would have to plant are not this test's: the reading is a note rather
| than a guard, so the mutation harness has nothing to turn red, and the substance is asked here —
| a record that is somebody else's, a record that is this checkout's, an ignored file that is not a
| record at all, and a tree where git cannot be asked.
|
*/

/**
 * @guards-index test
 *
 * the files this checkout ignores — read as a set of their own, so a machine record another package left in `.agents/` is named rather than hidden by the rule that keeps it out of a commit: what a record looks like, which checkout one belongs to, and what a run prints
 */
it('names a machine record another checkout left in a file this one ignores', function (): void {
    $repo = ReleaseRepo::make();

    // Assembled rather than written out: this file is a file a commit would carry, and a literal
    // path into somebody's drive would be a finding of the guard whose subject is exactly that.
    $elsewhere = '/elsewhere/laravel-package-beside-this-one';

    $repo->write('.gitignore', "*.txt\n");
    $repo->write('.agents/sample.machine.local.json.txt', machineRecord($elsewhere));

    $named = Ignored::foreign($repo->path);

    expect(array_keys($named))->toBe(['.agents/sample.machine.local.json.txt'])
        ->and($named['.agents/sample.machine.local.json.txt'])->toContain($elsewhere)
        ->and($named['.agents/sample.machine.local.json.txt'])->toContain('no commit carries it');

    // And the listing is checked for substance before the answer is believed: a reading over "the
    // files this checkout ignores" that had read no files would satisfy the claim about the spill by
    // having nothing to report it from.
    expect(Ignored::files($repo->path))->toContain('.agents/sample.machine.local.json.txt')
        ->and(Ignored::files($repo->path))->not->toContain('composer.json');
});

it('excuses this checkout\'s own record by the checkout it names, not by its path', function (): void {
    $repo = ReleaseRepo::make();

    // The one file here allowed to hold a machine's paths: `.agents/machine.local.json` says which
    // checkout it describes, so a copy of it anywhere in the tree is still this checkout's record
    // and a file describing somewhere else is not, whatever it is called and wherever it sits.
    $repo->write('.gitignore', "*.txt\n*.json\n");
    $repo->write('.agents/machine.local.json', machineRecord($repo->path));

    expect(Ignored::foreign($repo->path))->toBe([]);

    // The copy is somebody else's the moment it names another checkout — the same bytes, moved.
    $repo->write('.agents/kept-copy.txt', machineRecord('/elsewhere/another-checkout'));

    expect(array_keys(Ignored::foreign($repo->path)))->toBe(['.agents/kept-copy.txt']);
});

it('says nothing about the ignored files that record no machine', function (): void {
    $repo = ReleaseRepo::make();

    // Three shapes an ignore rule holds and this reading must not mistake for a record: a JSON
    // document with neither section, one with a section but not the keys a machine file carries, and
    // a text file that is not JSON at all. A reading that asked only whether the file was ignored
    // would name all three.
    $repo->write('.gitignore', "*.txt\n*.json\n");
    $repo->write('.agents/notes.txt', "a session's scratch copy\n");
    $repo->write('src/composer-copy.json', json_encode(['name' => 'someone/else', 'require' => []])."\n");
    $repo->write('src/half-record.json', json_encode(['php' => ['exe' => '/usr/bin/php']])."\n");

    expect(Ignored::files($repo->path))->toContain('.agents/notes.txt')
        ->and(Ignored::files($repo->path))->toContain('src/composer-copy.json')
        ->and(Ignored::foreign($repo->path))->toBe([]);
});

it('refuses a tree it cannot ask git about, rather than reporting that nothing is there', function (): void {
    // The answer a reading must not give twice: "there is nothing ignored here" and "git was never
    // asked" are the same empty list, and only one of them is a reading. A tree with no repository
    // around it is the case, and the refusal is what the test asserts on.
    $tree = ReleaseRepo::plain();

    expect(fn (): array => Ignored::files($tree->path))
        ->toThrow(RuntimeException::class);

    expect(fn (): array => Ignored::foreign($tree->path))
        ->toThrow(RuntimeException::class);
});

it('names what it finds in this checkout, in the channel a run prints', function (): void {
    // The other half of "named rather than hidden": the reading's answer about this checkout is put
    // where a session reads it — the suite prints the note, `bin/checks.php` lifts it into the
    // summary, and `python .agents/verify.py` prints what the checks printed. A clone with no spill
    // adds no note at all, which is the same answer the reading gives.
    Notes::clear();

    // The listing once, because asking git for it is the expensive half on a machine with its
    // dependencies installed: this checkout has thirteen thousand ignored files under `vendor/`.
    $files = Ignored::files(packageRoot());
    $named = Ignored::foreign(packageRoot());

    foreach ($named as $sentence) {
        Notes::add(Ignored::class, $sentence, packageRoot());
    }

    // Every name it answered with has to be a file this checkout ignores: the reading's subject is
    // that listing, and a name from anywhere else would be a second, unseen source of truth.
    expect(array_values(array_diff(array_keys($named), $files)))->toBe([])
        ->and(Notes::all())->toBe(array_map(
            static fn (string $sentence): string => Notes::MARKER.' Ignored: '.$sentence,
            array_values($named),
        ));
});

/**
 * A machine record in the shape `render_local.py --init` writes, naming one checkout.
 *
 * Written as JSON rather than copied from a file here, because the shape is the reading's subject and
 * a copy taken from this machine would name this machine. Two sections is what the reading asks for
 * — `php.exe` and `package.dir` — and the rest of a real record is not read.
 */
function machineRecord(string $checkout): string
{
    return json_encode([
        'php' => ['exe' => '/usr/bin/php', 'dir' => '/usr/bin'],
        'pwsh' => ['exe' => 'pwsh'],
        'package' => ['dir' => $checkout],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
}
