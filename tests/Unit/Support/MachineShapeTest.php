<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MachineShape;
use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The shape a fresh clone is opened with
|--------------------------------------------------------------------------
|
| `.agents/machine.local.json` names this machine's paths and is gitignored, so a commit never
| carries it. Two things stand in for it: `.agents/machine.local.json.example`, committed as the
| shape a reader copies, and the file `python .agents/render_local.py --init` writes — which is what
| a fresh clone is actually opened with, because `bin/bootstrap.php` runs exactly that. The two are
| written in two languages by two hands, and nothing compared them: the agentic layer's own check
| judges the machine file only where there is one, and a runner has none.
|
| So the example is read as the shape it declares, the renderer is read for the sections it opens a
| new file with and the keys it writes there, and the two are held to each other. What is asserted
| here is that they agree on this checkout, that the declaration was read before it agreed, that the
| program opens a fresh clone's file with that shape, and that a tree with a break in it is refused
| rather than answered — the four things a reading that had gone quiet would answer differently.
|
| The planted breaks live with the rest of them, in `tests/Unit/Support/MutationHarness/`.
|
*/

/**
 * @guards-index guard the machine-file shape
 *
 * the shape a fresh clone is opened with — `.agents/machine.local.json.example`, the committed shape a reader copies, and the sections `python .agents/render_local.py --init` writes — held to each other: a section declared in one and missing from the other, a key `--init` writes that the example shows nowhere, a section the example shows that no fresh clone receives, a token whose value `--init` never writes, how much of its own record a machine file has written — `--check` telling a file nobody has lived in yet from one that has recorded almost all of itself rather than giving both the same line, not keeping the fresh clone's line once a record has grown past the answers `--init` was handed, and naming the sections left either way — and the placeholder text of the values themselves, held to the key each one sits under rather than left as a token that fits any key: an angle-bracket string, a stock word, and one sentence written under two keys
 */
it('holds the committed example to the shape the renderer declares, and reports nothing where the two agree', function (): void {
    $root = str_replace('\\', '/', packageRoot());

    $sections = MachineShape::sections($root);
    $example = MachineShape::seeded($root);

    // A reading that found no sections agrees with an example that declares none, so the material is
    // checked before the empty answer is: the renderer's own declaration, the token table it is the
    // only copy of, and the example itself all have to be there for an empty reading to mean
    // anything.
    expect($sections)->toBeArray()
        ->and($sections)->toHaveKey('php')
        ->and(count($sections))->toBeGreaterThan(10)
        ->and($example)->toHaveKeys(array_keys($sections))
        ->and(MachineShape::tokens($root))->toHaveKeys(['__PHP_EXE__', '__PWSH_EXE__'])
        ->and(MachineShape::stale($root))->toBe([])
        // And the text of the values: a reader copies this file, so every placeholder has to say what
        // belongs under its own key rather than being a token that would fit under any of them.
        ->and(MachineShape::generic($root))->toBe([]);
});

it('reads the shape out of the renderer rather than keeping a copy of it', function (): void {
    $root = str_replace('\\', '/', packageRoot());

    // The declaration is the renderer's: `SECTIONS` is what a machine file holds and what each
    // section is for, `OWNED` is the keys `--init` writes from the answers it was given, and
    // `TOKENS` is the section and key each token is answered from. A copy kept here would pass
    // whether or not the renderer still agreed with it.
    expect(MachineShape::sections($root)['php'])->toBe('the interpreter the gate, the suite and bin/ run under')
        ->and(MachineShape::owned($root)['php'])->toBe(['exe', 'dir'])
        ->and(MachineShape::owned($root)['pwsh'])->toBe(['exe'])
        ->and(MachineShape::tokens($root)['__PHP_EXE__'])->toBe(['section' => 'php', 'key' => 'exe']);
});

it('opens a fresh clone\'s file with the declared sections, filled from the answers it was handed', function (): void {
    $tree = machineShapeTree();

    // Asked of the program rather than inferred from its declaration: this is the file a new machine
    // gets, so the claim the example makes about the shape is only true if `--init` writes it. The
    // three sections it owns are filled from the answers, and every other declared section is opened
    // so that the file is the shape rather than the three answers alone.
    $opened = MachineShape::opened($tree->path);

    expect(array_keys($opened))->toEqualCanonicalizing(array_keys(MachineShape::sections(packageRoot())))
        ->and($opened['php'])->toBe([
            'exe' => PHP_BINARY,
            'dir' => rtrim(str_replace('\\', '/', dirname(PHP_BINARY)), '/'),
        ])
        ->and($opened['pwsh'])->toBe(['exe' => MachineShape::PWSH])
        ->and($opened['package'])->toBe(['dir' => str_replace('\\', '/', $tree->path)])
        ->and($opened['envVars'])->toBe([]);
});

it('says which half of its own record the machine file has written, and says nothing once the record is complete', function (): void {
    $tree = machineShapeTree();

    // The state a fresh clone is in, made by the program that makes it: the whole shape, with
    // nothing recorded under the sections `--init` was not asked about.
    MachineShape::opened($tree->path);

    $declared = array_keys(MachineShape::sections(packageRoot()));
    $filled = MachineShape::owned($tree->path);

    $opened = MachineShape::checked($tree->path);

    // A file that holds the shape reads as complete to every other reading, so the report has to say
    // which state the record is in rather than count the sections: a machine nobody has lived in yet
    // and a machine that has recorded all but one of them are not the same answer, and "sections are
    // empty" is the sentence that reads the same for both. This is the first: the three answers
    // `--init` was handed, and the record beside them still to be written.
    expect($opened['exit'])->toBe(0)
        ->and(implode(PHP_EOL, $opened['lines']))
        ->toContain(sprintf(
            'the %d sections `--init` was handed answers for are here and the other %d have recorded nothing',
            count($filled),
            count($declared) - count($filled),
        ))
        // Not the sentence a record that has been grown gets, and not the one a file with nothing at
        // all in it gets: the three states are told apart rather than each starting the same way.
        ->not->toContain(' are recorded; still empty: ')
        ->not->toContain('none of the '.count($declared).' sections')
        ->toContain('envVars is empty')
        ->toContain('python is empty')
        // The three sections `--init` was handed answers for are the three it does not report.
        ->not->toContain('php is empty')
        ->not->toContain('package is empty');

    // The same question as a reading of its own, on a tree nothing has opened yet. It answers with
    // nothing here, and its hold in `tests/Unit/Support/MutationHarness/MachineShapeTest.php` is what
    // requires it to name the sentence when the renderer stops telling the two apart - which is the
    // difference between a reading and a test that asserts the reading returned an empty array.
    expect(MachineShape::state(machineShapeTree()->path))->toBe([]);

    // And the other direction the report has to tell apart: a record that has grown one section past
    // the answers `--init` was handed. This reading answers with nothing here, and its hold is what
    // requires it to name the fresh clone's sentence when the renderer prints that sentence for a
    // record that is no longer one.
    expect(MachineShape::recorded(machineShapeTree()->path))->toBe([]);

    // The shape with every section opened and nothing under any of them: a file that was wiped, or
    // one written from the declaration alone. A machine that has not started, which is not the clone
    // `--init` opens, and the third answer the summary owes. Its exit is 1 for a reason about the run
    // rather than about the record - the three sections the tokens are answered from are empty too,
    // so nothing resolves and nothing renders - which is why the sentence is the part asserted, and
    // why it says nothing has been recorded rather than reporting a count of what is left.
    $unstarted = machineShapeTree();
    MachineShape::opened($unstarted->path);
    $wiped = json_decode($unstarted->read(MachineShape::MACHINE), true);
    $wiped = is_array($wiped) ? $wiped : [];

    foreach ($declared as $section) {
        $wiped[$section] = [];
    }

    $unstarted->write(MachineShape::MACHINE, machineShapeJson($wiped));

    $nothing = MachineShape::checked($unstarted->path);

    expect(implode(PHP_EOL, $nothing['lines']))
        ->toContain(sprintf('none of the %d sections the shape declares has been recorded', count($declared)))
        ->not->toContain('`--init` was handed')
        ->not->toContain(' are recorded; still empty: ')
        ->toContain('php is empty');

    // And a record that has been grown: one section beyond the three filled in, ten still empty. This
    // is the state that used to read exactly like the fresh clone, and it is the one where "how much
    // is left" is the answer a reader needs.
    $growing = machineShapeTree();
    MachineShape::opened($growing->path);
    $partial = json_decode($growing->read(MachineShape::MACHINE), true);
    $partial = is_array($partial) ? $partial : [];
    $partial['python'] = ['version' => 'the version this machine reports'];
    $growing->write(MachineShape::MACHINE, machineShapeJson($partial));

    $grown = MachineShape::checked($growing->path);

    expect($grown['exit'])->toBe(0)
        ->and(implode(PHP_EOL, $grown['lines']))
        ->toContain(sprintf(
            '%d of the %d sections the shape declares are recorded; still empty: ',
            count($filled) + 1,
            count($declared),
        ))
        ->not->toContain('`--init` was handed')
        ->not->toContain('none of the '.count($declared).' sections')
        ->not->toContain('python is empty');

    // And a machine that has recorded itself gets the other answer: one fact in each declared
    // section is enough, the settings files were rendered from values that have not moved, so there
    // is nothing left for the report to say. This is the half that keeps the notes from being
    // printed at every run of every machine.
    $recorded = json_decode($tree->read(MachineShape::MACHINE), true);
    $recorded = is_array($recorded) ? $recorded : [];

    foreach (array_keys(MachineShape::sections(packageRoot())) as $section) {
        $held = $recorded[$section] ?? null;
        $recorded[$section] = is_array($held) && $held !== [] ? $held : ['recorded' => 'the fact this machine has about it'];
    }

    $tree->write(MachineShape::MACHINE, machineShapeJson($recorded));

    $settled = MachineShape::checked($tree->path);

    expect($settled['exit'])->toBe(0)
        ->and($settled['lines'])->toBe([]);

    // A machine that has never named itself is a different answer, and it is already said twice —
    // the tokens that resolve to nothing, and how to write them. Ten more notes about a file that
    // is not there would be the report burying that.
    $tree->delete(MachineShape::MACHINE);

    $unwritten = MachineShape::checked($tree->path);

    expect($unwritten['exit'])->toBe(1)
        ->and(implode(PHP_EOL, $unwritten['lines']))->toContain('has no value for')
        ->and(array_filter($unwritten['lines'], static fn (string $line): bool => str_starts_with($line, '[NOTE]')))->toBe([]);
});

it('names the section a declaration is missing, whichever side it is missing from', function (): void {
    $declared = MachineShape::sections(packageRoot());

    // A section the renderer opens a machine file with and the example never shows: what a shape
    // looks like when it is written ahead of the program that writes it, and the half a reader pays
    // for — a machine file copied from this example is missing it.
    $short = machineShapeTree();
    $without = MachineShape::seeded(packageRoot());
    unset($without['envVars']);
    $short->write(MachineShape::EXAMPLE, machineShapeJson($without));

    expect(implode(PHP_EOL, MachineShape::stale($short->path)))
        ->toContain('the example shows no `envVars` section')
        ->toContain($declared['envVars']);

    // And the other direction: a section the example shows that `--init` has never opened, which
    // reads as part of the shape and is a section no fresh clone receives.
    $extra = machineShapeTree();
    $grown = MachineShape::seeded(packageRoot());
    $grown['editor'] = ['settings' => '<where an editor keeps them>'];
    $extra->write(MachineShape::EXAMPLE, machineShapeJson($grown));

    expect(implode(PHP_EOL, MachineShape::stale($extra->path)))
        ->toContain('the `editor` section is shown here')
        ->toContain(MachineShape::RENDERER.' does not declare it');
});

it('names a key `--init` writes that the example shows nowhere, and a section shown empty', function (): void {
    $tree = machineShapeTree();
    $thinned = MachineShape::seeded(packageRoot());
    $php = is_array($thinned['php'] ?? null) ? $thinned['php'] : [];

    // The key is where a token's value is written, so an example without it hands a reader a machine
    // file that resolves nothing — and the finding names the token rather than the key alone.
    unset($php['dir']);
    $thinned['php'] = $php;
    $thinned['search'] = [];
    $tree->write(MachineShape::EXAMPLE, machineShapeJson($thinned));

    $findings = implode(PHP_EOL, MachineShape::stale($tree->path));

    expect($findings)
        ->toContain('the `php` section shows no `dir`, and it is a key `--init` writes from an answer it was handed — the value `__PHP_DIR__` is answered from.')
        // A section with nothing under it is a heading rather than a shape: there is nothing for a
        // reader to copy, and nothing for `--init` to keep.
        ->toContain('the `search` section is shown with no key under it');
});

it('leaves the record sections alone, because the renderer declares no keys for them', function (): void {
    $tree = machineShapeTree();
    $thinned = MachineShape::seeded(packageRoot());
    $git = is_array($thinned['git'] ?? null) ? $thinned['git'] : [];

    // The boundary, held so that it is a decision rather than a gap: `--init` owns three sections and
    // the rest are this machine's own record, whose keys the renderer never declares. An example
    // that dropped `git.origin` is therefore not a disagreement with the program — nothing here is
    // the source of truth for a key no program writes — and a reading that invented one would be a
    // second copy of the example rather than a comparison with the renderer.
    unset($git['origin']);
    $thinned['git'] = $git;
    $tree->write(MachineShape::EXAMPLE, machineShapeJson($thinned));

    expect(implode(PHP_EOL, MachineShape::stale($tree->path)))->not->toContain('origin');
});

it('names the placeholder text that says nothing about the key it sits under', function (): void {
    // The example is copied and filled in, so a value's text is the only instruction its key gets.
    // Three failures, planted one at a time: an angle-bracket token, which is a string a reader can
    // copy and leave — the brackets become the value, and a machine file records an instruction as a
    // fact; a stock word, which is the same token with the brackets taken off; and one sentence under
    // two keys, which cannot be about either. Each is asked on its own tree, because a reading that
    // caught only the first would still be green on the two beside it.
    $bracketed = machineShapeTree();
    $shape = MachineShape::seeded(packageRoot());
    $php = is_array($shape['php'] ?? null) ? $shape['php'] : [];
    $php['exe'] = '<absolute path to php.exe>';
    $shape['php'] = $php;
    $bracketed->write(MachineShape::EXAMPLE, machineShapeJson($shape));

    expect(implode(PHP_EOL, MachineShape::generic($bracketed->path)))
        ->toContain('`php.exe` is written as `<absolute path to php.exe>`');

    $stocky = machineShapeTree();
    $shape = MachineShape::seeded(packageRoot());
    $checks = is_array($shape['checks'] ?? null) ? $shape['checks'] : [];
    $checks['count'] = 'value';
    $shape['checks'] = $checks;
    $stocky->write(MachineShape::EXAMPLE, machineShapeJson($shape));

    expect(implode(PHP_EOL, MachineShape::generic($stocky->path)))
        ->toContain('`checks.count` is `value`');

    $shared = machineShapeTree();
    $shape = MachineShape::seeded(packageRoot());
    $python = is_array($shape['python'] ?? null) ? $shape['python'] : [];
    $composer = is_array($shape['composer'] ?? null) ? $shape['composer'] : [];
    $python['version'] = 'the version it reports';
    $composer['version'] = 'the version it reports';
    $shape['python'] = $python;
    $shape['composer'] = $composer;
    $shared->write(MachineShape::EXAMPLE, machineShapeJson($shape));

    expect(implode(PHP_EOL, MachineShape::generic($shared->path)))
        ->toContain('`composer.version` repeats the text written for `python.version`');
});

it('refuses a tree it cannot read the shape or the program out of', function (): void {
    // The one answer a guard must not give: a reading with nothing to read reporting that nothing is
    // wrong. Every half refuses, and the refusal is the answer this test asserts on.
    $example = machineShapeTree();
    $example->delete(MachineShape::EXAMPLE);

    expect(fn (): array => MachineShape::stale($example->path))
        ->toThrow(RuntimeException::class);

    expect(fn (): array => MachineShape::seeded($example->path))
        ->toThrow(RuntimeException::class);

    // An example that is not JSON at all is the same answer, and not an example that holds nothing.
    $broken = machineShapeTree();
    $broken->write(MachineShape::EXAMPLE, "not json\n");

    expect(fn (): array => MachineShape::stale($broken->path))
        ->toThrow(RuntimeException::class);

    // And a tree with no renderer has no declaration to compare the example with, which is not the
    // same answer as a declaration that agrees with it.
    $rendererless = ReleaseRepo::plain();

    expect(fn (): array => MachineShape::sections($rendererless->path))
        ->toThrow(RuntimeException::class);

    expect(fn (): array => MachineShape::opened($rendererless->path))
        ->toThrow(RuntimeException::class);
});

/**
 * A tree with this repository's renderer in it, the templates it renders, and the example beside them.
 *
 * The renderer is copied rather than written: the question this reading asks is what this package's
 * own shape is, and a hand-written fixture of a Python declaration would be a second copy of the
 * thing under test. What the fixture decides is the one variable each test turns on — the example it
 * hands the reading, which is either this package's own or that one with a break in it.
 */
function machineShapeTree(): ReleaseRepo
{
    $package = ReleaseRepo::package();
    $tree = ReleaseRepo::plain();

    $tree->write(MachineShape::RENDERER, (string) file_get_contents($package.'/'.MachineShape::RENDERER));
    $tree->write(MachineShape::EXAMPLE, (string) file_get_contents($package.'/'.MachineShape::EXAMPLE));

    foreach (MachineShape::TEMPLATES as $template) {
        $tree->write($template, (string) file_get_contents($package.'/'.$template));
    }

    return $tree;
}

/**
 * An example as the file it is: what a planted break hands the reading.
 *
 * Written back as JSON rather than edited as text, because a break is about a section or a key being
 * there rather than about a line, and a fixture that spelled the shape out again would be a second
 * copy of the file under test — one this test would then have to keep in step with it.
 *
 * @param  array<string, mixed>  $shape
 */
function machineShapeJson(array $shape): string
{
    return json_encode($shape, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
}
