<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Handoff;

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
 * the environment `AGENTS.md` §2 states — and the same section of the handoff beside it — held to `.agents/machine.local.json`: a name the renderer has never heard of, a token the machine file holds no value for, a recorded path that is not there, and a machine file naming another checkout while reading as a complete answer
 */
it('holds the environment table to this machine, and reports nothing where the two agree', function (): void {
    $root = str_replace('\\', '/', packageRoot());

    $tokens = Handoff::tokens($root);
    $machine = Handoff::machine($root);
    $named = Handoff::named((string) file_get_contents($root.'/'.Handoff::RULES), Handoff::TABLE);

    // A reading that found no tokens agrees with a table that names none, so the material is
    // checked before the empty answer is: the renderer's own table, the machine file, and the
    // names the document writes have all to be there for an empty reading to mean anything.
    expect($named)->toBeArray()
        ->and(array_keys($named ?? []))->toContain('__PHP_EXE__')
        ->and($tokens)->toHaveKeys(['__PHP_EXE__', '__PHP_DIR__', '__PWSH_EXE__', '__PACKAGE_DIR__'])
        ->and($machine)->toBeArray()
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
