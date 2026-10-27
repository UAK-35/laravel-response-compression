<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Handoff;
use Uak35\ResponseCompression\Tests\Support\Notes;
use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The environment a reader is handed
|--------------------------------------------------------------------------
|
| `AGENTS.md` §2 is where a session takes this checkout's environment from, and its rows are tokens
| rather than paths on purpose: no committed file may name a machine, so the table names
| `__PHP_EXE__` and the value lives in the gitignored `.agents/machine.local.json`. Nothing held the
| two together, and the failure that comes of that is quiet in both directions — a row pointing at a
| token nothing supplies reads as an environment that is described, and a machine file whose record
| has moved resolves every command in every skill to a tree or an interpreter that is not there.
|
| So the table is read for the names it writes, the renderer for the names that exist and the key
| each one is read from, and the machine file for the values. What is asserted here is that the two
| agree on this machine, that the reading had something to read before it agreed, and that a tree
| with nothing in it is refused rather than answered — the three things a reading that had gone
| quiet would answer differently.
|
| The planted breaks live with the rest of them, in `tests/Unit/Support/MutationHarness/`.
|
*/

/**
 * @guards-index guard the environment table
 *
 * the environment `AGENTS.md` §2 states — and the same section of the handoff beside it — held to `.agents/machine.local.json`: a name the renderer has never heard of, a token the machine file holds no value for, a recorded path that is not there, a machine file naming another checkout while reading as a complete answer, and a checkout with no machine file at all, where the value half cannot be asked and the reading has to say so rather than answer that everything is in order
 */
it('holds the environment table to the machine file where there is one, and reports nothing where the two agree', function (): void {
    $root = str_replace('\\', '/', packageRoot());

    $tokens = Handoff::tokens($root);
    $machine = Handoff::machine($root);
    $named = Handoff::named((string) file_get_contents($root.'/'.Handoff::RULES), Handoff::TABLE);

    // A reading that found no tokens agrees with a table that names none, so the material is checked
    // before the empty answer is: the renderer's own table and the names the document writes are the
    // half every machine has. The machine file is the half a runner has not, and the value half is
    // asked only where there is a file to hold a value — the test below is what that case answers
    // with — so what is asserted of it here is that the two agree about whether it is there at all.
    expect($named)->toBeArray()
        ->and(array_keys($named ?? []))->toContain('__PHP_EXE__')
        ->and($tokens)->toHaveKeys(['__PHP_EXE__', '__PHP_DIR__', '__PWSH_EXE__', '__PACKAGE_DIR__'])
        ->and(is_array($machine))->toBe(is_file($root.'/'.Handoff::MACHINE))
        ->and(Handoff::stale($root))->toBe([]);
});

it('reads the token table out of the renderer rather than keeping a copy of it', function (): void {
    $tokens = Handoff::tokens(str_replace('\\', '/', packageRoot()));

    // The shape is the renderer's: each token against the section and key of the machine file it is
    // read from, which is what makes this reading a comparison rather than a second definition. A
    // copy kept here would pass whether or not the renderer still agreed with it.
    expect($tokens['__PHP_EXE__'])->toBe(['section' => 'php', 'key' => 'exe'])
        ->and($tokens['__PACKAGE_DIR__'])->toBe(['section' => 'package', 'key' => 'dir']);
});

it('answers null for a section that is not there rather than reading nothing', function (): void {
    // A heading that moved is not a document with no tokens in its environment section: the reading
    // has to be able to tell the two apart, because only one of them is a document worth believing.
    expect(Handoff::named("# Something\n\n`__PHP_EXE__`\n", Handoff::TABLE))->toBeNull();
});

it('refuses a tree it cannot read the table or the token list out of', function (): void {
    // The one answer a guard must not give: a reading with nothing to read reporting that nothing is
    // wrong. Both halves refuse, and the refusal is the answer this test asserts on.
    expect(fn (): array => Handoff::stale('/nowhere'))
        ->toThrow(RuntimeException::class);

    expect(fn (): array => Handoff::tokens('/nowhere'))
        ->toThrow(RuntimeException::class);
});

it('reads a handoff as a document that can be taken away', function (): void {
    $root = str_replace('\\', '/', packageRoot());

    // The folder is untracked and its own first paragraph says it can go, so the reading answers
    // `null` for a checkout without one rather than treating the absence as a document that says
    // nothing — and a tree that does have one is read for the same names.
    expect(Handoff::handoff($root))->toBeString()
        ->and(Handoff::named((string) Handoff::handoff($root), Handoff::SECTION))
        ->toHaveKey('__PHP_EXE__');
});

/**
 * A tree with the two files this reading reads, and no machine file unless one is asked for.
 *
 * The table and the renderer are copied rather than written: the question this reading asks is what
 * this package's own documents say, and a hand-written fixture of them would be a second copy of the
 * thing under test. What the fixture decides is the one variable the note turns on — whether there
 * is a file for a value to be missing from.
 */
function handoffTree(bool $machine): ReleaseRepo
{
    $repo = ReleaseRepo::plain();

    foreach ([Handoff::RULES, Handoff::RENDERER] as $file) {
        $repo->write($file, (string) file_get_contents(ReleaseRepo::package().'/'.$file));
    }

    if ($machine) {
        $repo->write(Handoff::MACHINE, "{}\n");
    }

    return $repo;
}

it('says which half it could not ask, and only where there is no machine file', function (): void {
    // A runner, and a checkout nobody has run `--init` in: the table and the renderer are each
    // other's answer, so the reading comes back with an empty list — which is exactly what a tree in
    // step answers with. The note is the only thing that tells a reader which of the two happened,
    // and it is this reading that has to write it.
    Notes::clear();

    // With a machine file — even one that holds nothing — the value half is asked, so what is
    // missing from that file is a finding about the file rather than a question nobody could put.
    // Asserted first, because the channel is read rather than drained here: the note the other half
    // makes is left where it is, for the harness that prints what a test made.
    Handoff::stale(handoffTree(machine: true)->path);

    expect(Notes::all())->toBe([]);

    // And the half that could not ask says so, in the words a reader is shown. The line is asserted
    // and *left* — not taken and not cleared — so a run of the suite echoes it as well, which is the
    // property this whole channel exists for.
    expect(Handoff::stale(handoffTree(machine: false)->path))->toBe([])
        ->and(Notes::all())->toHaveCount(1)
        ->and(Notes::all()[0])->toContain('Handoff: ')
        ->and(Notes::all()[0])->toContain(Handoff::MACHINE);
});
