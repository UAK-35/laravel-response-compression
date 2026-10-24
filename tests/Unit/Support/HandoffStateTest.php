<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\HandoffState;
use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The state a handoff is handed over with
|--------------------------------------------------------------------------
|
| `handoff/HANDOFF.md` §3 opens by saying that `bash handoff/state.sh` outranks it, and nothing held
| the two together: the section carried `CHANGELOG.md` as a file "`bin/commit.php` can only write a
| bullet into while it is LF" for five days after the writer started taking the ending from the
| heading it matched, and the script contradicted it on every run. A snapshot cannot be held to the
| tree it was taken from — the live `HEAD` moves with every commit — so what is held is the half that
| does not move (the branch, the gate, the changelog's endings), and the half that does has to say
| when it looked.
|
| The planted breaks live with the rest of them, in `tests/Unit/Support/MutationHarness/`.
|
*/

/**
 * @guards-index guard the handoff's state section
 *
 * the state section of the handoff, held to what `handoff/state.sh` measures: its branch, its gate wiring and its changelog endings, every count and colour it states dated to the moment it was measured, and a section that says the script outranks it — so the snapshot a reader is handed cannot go on saying what the checkout has stopped doing
 */
it('holds the state section to the script, and reports nothing where the two agree', function (): void {
    $root = str_replace('\\', '/', packageRoot());

    // A reading that parsed nothing agrees with a section that says nothing, so the measurement is
    // checked before the empty answer is: the branch the script answered with, all thirteen rows of
    // its file list, and the line it prints about the changelog are what the comparison needs to be
    // a comparison at all.
    $measured = HandoffState::measured($root);

    expect($measured['branch'])->not->toBe('')
        ->and($measured['files'])->toHaveCount(13)
        ->and($measured['changelog'])->toBeArray()
        ->and(HandoffState::stale($root))->toBe([]);
});

it('reads the three claims out of the document rather than keeping a copy of them', function (): void {
    $root = str_replace('\\', '/', packageRoot());

    $stated = HandoffState::stated($root);
    $measured = HandoffState::measured($root);

    // The branch, the setting the hook runs from and the ending count are the document's own words:
    // a copy of them kept here would pass whether or not the section still said them, which is the
    // failure this reading exists to refuse rather than to repeat.
    expect($stated['branch']['value'])->toBe($measured['branch'])
        ->and($stated['hooks']['value'])->toBe($measured['hooks'])
        ->and($stated['changelog']['cr'])->toBe($measured['changelog']['cr'])
        ->and($stated['dated'])->toBeTrue()
        ->and($stated['deference'])->toBeTrue()
        ->and($stated['undated'])->toBe([]);
});

it('names the ending the writer cannot use, when the section still says it cannot', function (): void {
    // The claim as it stood before `ca776f0`: true the day it was typed, and false from the commit
    // that made the writer take the heading's own ending. It is the drift this reading was written
    // for, planted back.
    $tree = handoffStateTree(static fn (string $text): string => str_replace(
        'and `bin/commit.php` can write a bullet into either',
        'and `bin/commit.php` can only write a bullet into either',
        $text,
    ));

    expect(implode(PHP_EOL, HandoffState::stale($tree->path, handoffStateMeasured())))
        ->toContain(HandoffState::DOCUMENT)
        ->toContain('says `bin/commit.php` cannot write a bullet into `CHANGELOG.md`')
        ->toContain('handoff/state.sh says it can');
});

it('names a count that says what it measured and not when', function (): void {
    // `HEAD` and how far ahead the branch is move with every commit, so a bullet that states them
    // has to say which moment it read: a number with no date is a claim about now, and now belongs
    // to the script. Planted back in the shape the document used to write it in.
    //
    // The hash and the distance are read out of the section rather than written here: today's `HEAD`
    // is exactly the drift this reading exists to name, and a literal would stop planting the break
    // the first time the branch moved — a fixture that stops editing the document reads as a
    // reading that found nothing.
    $document = (string) file_get_contents(ReleaseRepo::package().'/'.HandoffState::DOCUMENT);

    preg_match('/this was `([0-9a-f]{7,40})`/', $document, $head);

    $hash = $head[1] ?? '';

    expect($hash)->not->toBe('');

    $tree = handoffStateTree(static fn (string $text): string => (string) preg_replace(
        '/measured \d{4}-\d{2}-\d{2},[ \t\r]*\n[ \t]*this was `([0-9a-f]{7,40})`, \*\*(\d+) ahead and 0 behind\*\*, with nothing pushed;/',
        'HEAD `$1`, **$2 commits ahead of `origin/development` and 0 behind**, nothing pushed;',
        $text,
    ));

    expect(implode(PHP_EOL, HandoffState::stale($tree->path, handoffStateMeasured())))
        ->toContain('HEAD '.$hash)
        ->toContain('with no date');
});

it('dates a tally by the heading it sits under, and refuses one that no heading dates', function (): void {
    // The colour is the half no script measures, so the rule is the date rather than the value: a
    // bullet that says how the suite came out is a measurement of a moment, and the moment may be
    // written in the bullet or in the heading above it. Planting the same sentence in both places
    // is what tells a reading that compares the two apart from one that merely matches words.
    $undated = handoffStateTree(static fn (string $text): string => str_replace(
        '- gate wired: `core.hooksPath` is `.githooks`',
        "- the suite reads **466 passed**, so the colour is green;\n- gate wired: `core.hooksPath` is `.githooks`",
        $text,
    ));

    expect(implode(PHP_EOL, HandoffState::stale($undated->path, handoffStateMeasured())))
        ->toContain('states 466 passed with no date');

    // The same sentence under §3.2, whose heading carries the day it was measured on: the claim is
    // dated, so the reading has nothing to say about it.
    // The heading is matched rather than quoted, so the plant survives the day it is re-measured:
    // the test is that a dated heading covers the tally beneath it, and which day it names is the
    // document's business.
    $dated = handoffStateTree(static fn (string $text): string => (string) preg_replace(
        '/^### 3\.2 The suite, measured [^\r\n]*/m',
        "$0\n\n- the suite reads **466 passed**, so the colour is green;",
        $text,
    ));

    expect(HandoffState::stale($dated->path, handoffStateMeasured()))->toBe([]);
});

it('names a section that has stopped saying the script outranks it, and one that lost its date', function (): void {
    $deference = handoffStateTree(static fn (string $text): string => str_replace(
        '`bash handoff/state.sh` prints all of the below as it is at the moment it runs, so it outranks this',
        '`bash handoff/state.sh` prints all of the below as it is at the moment it runs, and this',
        $text,
    ));

    expect(implode(PHP_EOL, HandoffState::stale($deference->path, handoffStateMeasured())))
        ->toContain('does not say that `bash handoff/state.sh` outranks it');

    // The colour this section carries is a tally from a run of the suite, which no script measures:
    // a heading without a date presents it as the tree's colour today, which is the claim that goes
    // stale in silence.
    $dated = handoffStateTree(static fn (string $text): string => (string) preg_replace(
        '/^'.preg_quote(HandoffState::STATE, '/').'[^\r\n]*/m',
        HandoffState::STATE,
        $text,
    ));

    expect(implode(PHP_EOL, HandoffState::stale($dated->path, handoffStateMeasured())))
        ->toContain('does not say when it was measured');
});

it('names a branch this checkout is not on, and a gate it is not wired through', function (): void {
    $tree = handoffStateTree(static fn (string $text): string => str_replace(
        ['- branch `development`.', '`core.hooksPath` is `.githooks`'],
        ['- branch `main`.', '`core.hooksPath` is `.husky`'],
        $text,
    ));

    $findings = implode(PHP_EOL, HandoffState::stale($tree->path, handoffStateMeasured()));

    // Both are facts a checkout can have stopped having — a branch renamed, a clone whose
    // `core.hooksPath` was never set — and both make the section's sentence about the tree false
    // without changing a word of it.
    expect($findings)->toContain('names the branch `main`')
        ->toContain('answers `.githooks`')
        ->toContain('`.husky`');
});

it('answers nothing where the folder has gone, and refuses a document it cannot read', function (): void {
    // The folder is removable — `AGENTS.md` §3 has the line to delete with it — so a checkout
    // without one is a checkout with no snapshot, not a snapshot with something wrong in it.
    expect(HandoffState::stale(handoffStateTree()->path.'-absent', handoffStateMeasured()))->toBe([])
        ->and(HandoffState::stated(handoffStateTree()->path.'-absent'))->toBeNull();

    // A document with the section taken out, and one with no script beside it: both are refusals
    // rather than empty answers, because neither can be compared with anything.
    $headless = handoffStateTree(static fn (string $text): string => (string) preg_replace(
        '/^'.preg_quote(HandoffState::STATE, '/').'[^\r\n]*/m',
        '## 3. The state that was measured',
        $text,
    ));

    expect(fn (): array => HandoffState::stale($headless->path, handoffStateMeasured()))
        ->toThrow(RuntimeException::class, 'has no `## 3. State` section');

    expect(fn (): array => HandoffState::stale(handoffStateTree()->path))
        ->toThrow(RuntimeException::class, 'handoff/state.sh is not here');
});

/**
 * A tree holding this checkout's handoff, with one break planted in it where an edit is given.
 *
 * The document is copied rather than written: the question is what this package's own handoff says,
 * and a hand-written fixture of it would be a second copy of the thing under test — the edit is the
 * only difference between the fixture and the document a reader is handed.
 */
function handoffStateTree(?callable $edit = null): ReleaseRepo
{
    $repo = ReleaseRepo::plain();
    $document = (string) file_get_contents(ReleaseRepo::package().'/'.HandoffState::DOCUMENT);

    $repo->write(HandoffState::DOCUMENT, $edit === null ? $document : $edit($document));

    return $repo;
}

/**
 * What the script measures in this checkout, taken once for the fixtures above.
 *
 * A planted tree has no history for the script to measure, so the comparison is handed the
 * measurement of the tree the document is actually about. That is the same comparison either way:
 * `stale()` runs the script itself when its caller has not, which is what the guard does.
 *
 * @return array<string, mixed>
 */
function handoffStateMeasured(): array
{
    return HandoffState::measured(str_replace('\\', '/', packageRoot()));
}
