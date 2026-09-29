<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Docs;
use Uak35\ResponseCompression\Tests\Support\GuardIndex;
use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;
use Uak35\ResponseCompression\Tests\Support\ReleaseRun;

/*
|--------------------------------------------------------------------------
| The index of the guards
|--------------------------------------------------------------------------
|
| `docs/guards.md` is where a guard is put in front of whoever adds the next one, and it had
| one direction watched and one not. A row that links a test which has since been renamed is
| caught already: the row's link is a link, and the docs-link guard resolves every link in every
| document this package ships. A guard added with *no row at all* is caught by nothing, and
| cannot be — no test knew what a guard is, so the next one could arrive unrecorded and read as
| though it had always been part of the list.
|
| So the table is read as a claim about a directory. Every row has to link a file the repository
| has, and every file in `tests/Support/` has to be named in the document — a reading in the
| section that lists them, or support in the section saying it is not a guard. The reading is
| exercised on its own at the foot of this file, because a reader that found no rows agrees with
| a table that has none.
|
| The two lists below that table are the same question one step further on. They were prose, and
| prose about a directory is a copy of it: a file that moved had to be moved by hand in the
| document as well, and a file that arrived was in no list until somebody remembered it. So each
| file declares which list it is in, in its own header, and its own first sentence is the words
| the row carries — `bin/index.php` writes the two tables from that, and the test here holds the
| document to what the directory produces. `tests/Support/MutationHarness.php` breaks the tree
| under the reading for the same reason it breaks it under the others: a rendering that answered
| "nothing to write" for every tree would pass every assertion about a document in step.
|
*/

it('links every row of the guard table to a file that exists', function (): void {
    [$root, $document, $markdown] = guardIndexDocument();

    // The header is pinned to the words rather than to the constant that reads them: a table
    // whose columns moved would be read wrongly rather than not at all, and the constant moving
    // with it would hide exactly that.
    expect(GuardIndex::header($markdown))->toBe(['Guard', 'What it catches', 'Where'])
        ->and(GuardIndex::rows($markdown))->not->toBeEmpty();

    $broken = [];
    $unnamed = [];

    foreach (GuardIndex::rows($markdown) as $row) {
        $paths = guardIndexPaths($root, $document, $row['where']);

        foreach ($paths as $target => $path) {
            if (! file_exists($path)) {
                $broken[] = sprintf(
                    'docs/guards.md:%d -> %s (the row for %s)',
                    $row['line'],
                    $target,
                    $row['guard'],
                );
            }
        }

        // A cell that only names the row above it resolves nothing: the Where column is where a
        // reader is sent from, so a row that names no file sends nobody.
        if ($paths === []) {
            $unnamed[] = sprintf('docs/guards.md:%d (the row for %s)', $row['line'], $row['guard']);
        }
    }

    expect($broken)->toBe([])
        ->and($unnamed)->toBe([]);
});

it('names every file in tests/Support in the index', function (): void {
    [$root, $document, $markdown] = guardIndexDocument();

    $readings = GuardIndex::readings($root);

    // A directory that was never read agrees with a document that names nothing, so the listing
    // is checked for substance first — and the file named here is this guard's own reading, which
    // no hand-written list would have had before this change.
    expect($readings)->not->toBeEmpty()
        ->and($readings)->toContain('tests/Support/GuardIndex.php');

    $named = GuardIndex::spans($markdown);

    foreach (Docs::linksIn($markdown) as $link) {
        $path = Docs::resolve($root, $document, $link['target']);

        if ($path !== null) {
            $named[] = Docs::relative($root, $path);
        }
    }

    $unnamed = array_values(array_filter(
        $readings,
        static fn (string $file): bool => ! in_array($file, $named, true),
    ));

    expect($unnamed)->toBe([]);
});

it('resolves every path it names under tests/Support', function (): void {
    [$root, , $markdown] = guardIndexDocument();

    // The other half of being named: a reading that is renamed leaves its old name behind in the
    // document, and a name that no longer resolves is how the index starts describing a file that
    // is not there.
    $stale = [];

    foreach (GuardIndex::spans($markdown) as $span) {
        if (str_starts_with($span, 'tests/Support/') && ! file_exists($root.'/'.$span)) {
            $stale[] = $span;
        }
    }

    expect($stale)->toBe([]);
});

it('reads the table it is given, and nothing under it', function (): void {
    $document = implode("\n", [
        '## Kept',
        '',
        '| Guard | What it catches | Where |',
        '|---|---|---|',
        '| the first | one thing | [`../tests/Unit/Docs/DocsLinksTest.php`](../tests/Unit/Docs/DocsLinksTest.php) |',
        '| the second | another | the same workflow |',
        '',
        '## The readings the guards are built from',
        '',
        '| Reading | What it reads |',
        '|---|---|',
        '| `tests/Support/Docs.php` | the markdown this package ships |',
        '',
    ]);

    $rows = GuardIndex::rows($document);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['guard'])->toBe('the first')
        ->and($rows[0]['catches'])->toBe('one thing')
        ->and($rows[0]['line'])->toBe(5)
        ->and($rows[1]['where'])->toBe('the same workflow')
        ->and($rows[1]['line'])->toBe(6)
        ->and(Docs::linksIn($rows[0]['where']))->toHaveCount(1)
        ->and(Docs::linksIn($rows[1]['where']))->toBe([])
        ->and(GuardIndex::spans($document))->toContain('tests/Support/Docs.php');

    // The second table is under its own heading and is not read as a guard: the rows above it end
    // where the section does, which is what keeps a reading from being counted as a guard.
    expect(array_column($rows, 'guard'))->toBe(['the first', 'the second']);

    // And the two ways the reading can have nothing to read, neither of which may come back as a
    // pass: no table at all, and a table whose columns are not the ones a row is read by.
    expect(fn (): array => GuardIndex::rows("# Not the index\n"))
        ->toThrow(RuntimeException::class);

    expect(fn (): array => GuardIndex::rows("## Kept\n\n| Guard | Where | What it catches |\n|---|---|---|\n| a | b | c |\n"))
        ->toThrow(RuntimeException::class);
});

it('renders both lists from the directory, and the document is what they produce', function (): void {
    [$root] = guardIndexDocument();

    $rendered = GuardIndex::rendered($root);

    // The reading, and then the substance under it: a directory that was never read renders two
    // empty tables, which is what a document with two empty tables says as well.
    expect(GuardIndex::drift($root))->toBe([])
        ->and($rendered['reading'])->not->toBeEmpty()
        ->and($rendered['support'])->not->toBeEmpty()
        ->and($rendered['reading'])->toHaveKey('tests/Support/GuardIndex.php')
        ->and($rendered['support'])->toHaveKey('tests/Support/ReleaseRun.php');

    // And every file in the directory is in exactly one of the two lists. Two lists that between
    // them name a file twice leave as much room for a file to hide as two that name it not at all.
    $listed = [...array_keys($rendered['reading']), ...array_keys($rendered['support'])];
    sort($listed);

    expect($listed)->toBe(GuardIndex::readings($root));
});

it('reads a file\'s list and its words out of the file, in both header styles', function (): void {
    $tree = guardIndexTree();

    $declared = GuardIndex::declared($tree['root']);

    // `One.php` closes its header with a class docblock and `Two.php` is a script, which opens
    // with the ruled banner `tests/Support/collect-coverage.php` uses. Both are read by one rule,
    // because the block is the file's own header either way.
    expect($declared)->toHaveKey('tests/Support/One.php')
        ->and($declared['tests/Support/One.php']['section'])->toBe('reading')
        ->and($declared['tests/Support/One.php']['summary'])->toBe('a reading the guards are built from')
        ->and($declared['tests/Support/Two.php']['section'])->toBe('reading')
        ->and($declared['tests/Support/Two.php']['summary'])->toBe('a second reading, written as a script')
        ->and($declared['tests/Support/Three.php']['section'])->toBe('support')
        ->and($declared['tests/Support/Three.php']['summary'])->toBe('a fixture the guards are run against');

    // A tree in step answers with nothing, which is the half every assertion below turns on.
    expect(GuardIndex::drift($tree['root']))->toBe([]);
});

it('names the file when a row and the directory stop agreeing', function (): void {
    $tree = guardIndexTree();
    $root = $tree['root'];

    // A file the document has no row for: the guard that arrived unrecorded, which is the one
    // thing the index cannot do for itself.
    guardIndexWrite($root, 'tests/Support/Four.php', guardIndexHeader('A reading nobody listed', 'reading'));

    expect(implode(PHP_EOL, GuardIndex::drift($root)))
        ->toContain('tests/Support/Four.php')
        ->toContain('is in neither list');

    unlink($root.'/tests/Support/Four.php');

    // A row that no longer says what the file says: the drift a reader of the page cannot see,
    // because the row is still there and still links to a file that is still there.
    guardIndexWrite($root, 'tests/Support/One.php', guardIndexHeader('A reading the guards are built from, reworded', 'reading'));

    expect(implode(PHP_EOL, GuardIndex::drift($root)))
        ->toContain('tests/Support/One.php')
        ->toContain('reworded');

    // The file that moved to the other list, which is membership rather than presence.
    guardIndexWrite($root, 'tests/Support/One.php', guardIndexHeader('A reading the guards are built from', 'support'));

    expect(implode(PHP_EOL, GuardIndex::drift($root)))
        ->toContain('is listed under `## The readings the guards are built from`, and the file says it is a support');

    // A row for a file the directory does not have: the other end of the same disagreement.
    unlink($root.'/tests/Support/One.php');

    expect(implode(PHP_EOL, GuardIndex::drift($root)))->toContain('the directory has no such file');

    // And the same rows in the wrong order, which is the one difference a reading that compared
    // the two as sets would answer with nothing.
    guardIndexPlant($root);

    guardIndexWrite($root, 'docs/guards.md', str_replace(
        guardIndexRow('One.php', 'a reading the guards are built from')."\n".guardIndexRow('Two.php', 'a second reading, written as a script'),
        guardIndexRow('Two.php', 'a second reading, written as a script')."\n".guardIndexRow('One.php', 'a reading the guards are built from'),
        (string) file_get_contents($tree['document']),
    ));

    expect(implode(PHP_EOL, GuardIndex::drift($root)))->toContain('are not in the order');
});

it('writes the two tables back from the directory, and a second pass changes nothing', function (): void {
    $tree = guardIndexTree();
    $root = $tree['root'];

    guardIndexWrite($root, 'tests/Support/Four.php', guardIndexHeader('A reading nobody listed', 'reading'));

    $before = (string) file_get_contents($tree['document']);
    $after = GuardIndex::rewritten($root, $before);

    // The row is the file's own sentence as a clause, which is the form every cell in the document
    // is written in: first letter down, no full stop.
    expect($after)->toContain('a reading nobody listed')
        ->and($after)->not->toBe($before);

    // The headings and the prose around them are not this program's business: only the rows are.
    expect($after)->toContain('## The readings the guards are built from')
        ->and($after)->toContain('# Which guards does this package keep?');

    file_put_contents($tree['document'], $after);

    expect(GuardIndex::drift($root))->toBe([])
        // And asked again, with the document it just wrote as the input: the same directory and the
        // same document answer the same document. A rendering that moved on every run would leave
        // the suite red on a tree nobody had touched.
        ->and(GuardIndex::rewritten($root, $after))->toBe($after);
});

it('refuses a file that says nothing about which list it is in, or names one there is not', function (): void {
    $tree = guardIndexTree();
    $root = $tree['root'];

    // A file with a header and no declaration. Left out of both lists it would be the file nobody
    // decided about, which is the state this reading exists to make impossible.
    guardIndexWrite($root, 'tests/Support/Five.php', "<?php\n\ndeclare(strict_types=1);\n\n/**\n * A file that never says where it belongs.\n */\nfinal class Five\n");

    expect(fn (): array => GuardIndex::declared($root))
        ->toThrow(RuntimeException::class, '@guards-index');

    // A declaration naming a list this package does not have: a typo would otherwise be a file in
    // a table that is not rendered at all.
    guardIndexWrite($root, 'tests/Support/Five.php', guardIndexHeader('A file in a list that does not exist', 'both'));

    expect(fn (): array => GuardIndex::declared($root))
        ->toThrow(RuntimeException::class, 'no such list');

    // And a header with nothing to quote: the row would carry an empty cell, which is a row that
    // tells a reader nothing about the file it names.
    guardIndexWrite($root, 'tests/Support/Five.php', "<?php\n\n/**\n *\n * @guards-index reading\n */\nfinal class Five\n");

    expect(fn (): array => GuardIndex::declared($root))
        ->toThrow(RuntimeException::class, 'no first sentence');
});

it('is the same reading as a command, and the command writes the document it reported', function (): void {
    $repo = indexRepo();

    $clean = $repo->script('bin/index.php', '--check');

    expect($clean->exitCode)->toBe(0)
        ->and($clean->output)->toContain('lists exactly what')
        ->and($clean->error)->toBe('');

    // A file the document does not list. `--check` is the half a program that only wrote would
    // hide: the differences are reported and the document is left exactly as it was.
    $before = $repo->read('docs/guards.md');

    $repo->write('tests/Support/Four.php', guardIndexHeader('A reading nobody listed', 'reading'));

    $drifted = $repo->script('bin/index.php', '--check');

    expect($drifted->exitCode)->toBe(1)
        ->and($drifted->error)->toContain('tests/Support/Four.php')
        ->and($drifted->error)->toContain('bin/index.php')
        ->and($drifted->output)->toBe('')
        ->and($repo->read('docs/guards.md'))->toBe($before);

    // And the run that writes it, which reports what it replaced rather than only that it wrote.
    $wrote = $repo->script('bin/index.php');

    expect($wrote->exitCode)->toBe(0)
        ->and($wrote->output)->toContain('Rewrote the two lists')
        ->and($wrote->output)->toContain('tests/Support/Four.php')
        ->and($repo->read('docs/guards.md'))->toContain('a reading nobody listed');

    // What it wrote is what the reading says now — the assertion is the program's own claim,
    // read back through the same command a reader would use.
    expect($repo->script('bin/index.php', '--check')->exitCode)->toBe(0);

    // A file that cannot be read as a row is refused rather than written around: the document
    // is not touched, and the message names what is missing.
    $repo->write('tests/Support/Five.php', "<?php\n\n/**\n * No declaration at all.\n */\nfinal class Five\n");

    $refused = $repo->script('bin/index.php');

    expect($refused->exitCode)->toBe(1)
        ->and($refused->error)->toContain('@guards-index')
        ->and($repo->read('docs/guards.md'))->not->toContain('Five.php');

    $repo->delete('tests/Support/Five.php');

    // Usage: one option this program has, and the two answers to being asked wrong.
    $unknown = $repo->script('bin/index.php', '--nonsense');

    expect($unknown->exitCode)->toBe(2)
        ->and($unknown->error)->toContain('is not an option');

    $help = $repo->script('bin/index.php', '--help');

    expect($help->exitCode)->toBe(0)
        ->and($help->error)->toContain('Usage:');

    // A document that is not there, and one with no table under a heading: the rows are what this
    // writes, and where a table would go is a decision it does not make.
    $repo->delete('docs/guards.md');

    expect($repo->script('bin/index.php')->exitCode)->toBe(1)
        ->and($repo->script('bin/index.php')->error)->toContain('is not here');

    $repo->write('docs/guards.md', "## The readings the guards are built from\n\n## Support that is not a guard\n");

    expect($repo->script('bin/index.php')->error)->toContain('has no table under');

    $repo->write('docs/guards.md', "# Some other document\n");

    expect($repo->script('bin/index.php')->error)->toContain('heading');

    // And a checkout that has never been installed: the reading is a class this program asks the
    // autoloader for, and a missing one is worth a sentence about installing rather than a fatal.
    $repo->delete('vendor/autoload.php');

    $installed = $repo->script('bin/index.php');

    expect($installed->exitCode)->toBe(2)
        ->and($installed->error)->toContain('composer install first');
});

it('is in step in this checkout, under this package\'s own autoloader', function (): void {
    // Everything above is a tree the test wrote. The one question a fixture cannot ask is whether
    // the document this package ships is what its own `tests/Support/` produces, resolved through
    // Composer's autoloader rather than a stub's `require`: a file whose class stopped being
    // autoloadable would fail here and nowhere else.
    $run = indexRun('--check');

    expect($run->exitCode)->toBe(0)
        ->and($run->output)->toContain('docs/guards.md')
        ->and($run->error)->toBe('');
});

/**
 * The index, and the two values every test here needs besides it: the root as the forward-slashed
 * path a link is resolved against, and the document itself.
 *
 * @return array{0: string, 1: string, 2: string}
 */
function guardIndexDocument(): array
{
    $root = str_replace('\\', '/', packageRoot());
    $document = $root.'/docs/guards.md';

    return [$root, $document, (string) file_get_contents($document)];
}

/**
 * A package root of the test's own: three files for the directory to hold, and the document that
 * lists them.
 *
 * Written rather than copied. This checkout is in step by definition, and the questions worth
 * asking of this reading are about its edges — a file that declares nothing, a row that moved to
 * the other list, two rows in the wrong order — none of which can be arranged in a tree the suite
 * has to leave as it found it.
 *
 * @return array{root: string, document: string}
 */
function guardIndexTree(): array
{
    $root = sys_get_temp_dir().'/rc-guard-index-'.bin2hex(random_bytes(6));

    guardIndexPlant($root);

    register_shutdown_function(static function () use ($root): void {
        ReleaseRepo::remove($root);
    });

    return ['root' => $root, 'document' => $root.'/docs/guards.md'];
}

/**
 * The three files and the document that lists them, written into a tree.
 *
 * Two readings and one fixture, because both of the lists have to have something in them: a
 * rendering of an empty section agrees with a document that has one, which is the half of this
 * that has to be seen rather than assumed.
 */
function guardIndexPlant(string $root): void
{
    foreach (guardIndexSources() as $file => $contents) {
        guardIndexWrite($root, $file, $contents);
    }
}

/**
 * What a planted tree holds.
 *
 * `One.php` closes its header with a class docblock and `Two.php` is a script, which opens with the
 * ruled banner `tests/Support/collect-coverage.php` carries — one list, written in the two styles
 * the directory really contains.
 *
 * @return array<string, string>
 */
function guardIndexSources(): array
{
    return [
        'tests/Support/One.php' => guardIndexHeader('A reading the guards are built from', 'reading'),
        'tests/Support/Two.php' => "<?php\n\ndeclare(strict_types=1);\n\n/*\n|--------------------------------------------------------------------------\n| A second reading, written as a script\n|--------------------------------------------------------------------------\n|\n| @guards-index reading\n*/\n\n\$nothing = 1;\n",
        'tests/Support/Three.php' => guardIndexHeader('A fixture the guards are run against', 'support'),
        'docs/guards.md' => guardIndexText(),
    ];
}

/**
 * A file of the shape the directory holds: one header line, one declaration, and nothing else the
 * reading looks at.
 */
function guardIndexHeader(string $summary, string $section): string
{
    return "<?php\n\ndeclare(strict_types=1);\n\n/**\n * ".$summary.".\n *\n * @guards-index ".$section."\n */\nfinal class Planted\n";
}

/**
 * The document a planted tree starts with: the two headings the reader looks for, one table under
 * each, and the rows in step with the three files beside it.
 *
 * Hand-written rather than rendered, because this is the document the reading is asked to agree
 * with: a fixture built by the renderer would agree with it by construction and the format would
 * never be read by anything.
 */
function guardIndexText(): string
{
    return implode("\n", [
        '# Which guards does this package keep?',
        '',
        '## The readings the guards are built from',
        '',
        '| Reading | What it reads |',
        '|---|---|',
        guardIndexRow('One.php', 'a reading the guards are built from'),
        guardIndexRow('Two.php', 'a second reading, written as a script'),
        '',
        '## Support that is not a guard',
        '',
        '| File | What it is |',
        '|---|---|',
        guardIndexRow('Three.php', 'a fixture the guards are run against'),
        '',
    ]);
}

/**
 * One row as the document writes it, spelled here rather than read from the reading: an expectation
 * built by the code it is checking would agree with it whatever it did.
 */
function guardIndexRow(string $file, string $summary): string
{
    return '| [`../tests/Support/'.$file.'`](../tests/Support/'.$file.') | '.$summary.' |';
}

/** One file in a planted tree, with the directories above it. */
function guardIndexWrite(string $root, string $file, string $contents): void
{
    if (! is_dir(dirname($root.'/'.$file))) {
        mkdir(dirname($root.'/'.$file), 0o777, true);
    }

    file_put_contents($root.'/'.$file, $contents);
}

/**
 * A fixture with the program in it, the reading beside it, and a directory of its own.
 *
 * The reading is written to `lib/` rather than to `tests/Support/`, because the directory this
 * program renders is the one the document names: a copy of it inside that directory would be one
 * more file the rows would have to list. `vendor/autoload.php` is a stub rather than an install,
 * which is what the program asks for and nothing more — whether this package's own autoloader
 * resolves the class is the one question a fixture cannot answer and the test below asks of the
 * real tree.
 */
function indexRepo(): ReleaseRepo
{
    $repo = ReleaseRepo::plain();
    $package = ReleaseRepo::package();

    $repo->write('bin/index.php', (string) file_get_contents($package.'/bin/index.php'));
    $repo->write('lib/Docs.php', (string) file_get_contents($package.'/tests/Support/Docs.php'));
    $repo->write('lib/GuardIndex.php', (string) file_get_contents($package.'/tests/Support/GuardIndex.php'));
    $repo->write('vendor/autoload.php', "<?php\n\nrequire __DIR__.'/../lib/Docs.php';\nrequire __DIR__.'/../lib/GuardIndex.php';\n");

    guardIndexPlant($repo->path);

    return $repo;
}

/**
 * This package's own program, in place, for the question a planted tree cannot be asked.
 */
function indexRun(string ...$arguments): ReleaseRun
{
    $command = [PHP_BINARY, packageRoot().'/bin/index.php', ...$arguments];

    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        packageRoot(),
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Could not run '.implode(' ', $command));
    }

    $output = (string) stream_get_contents($pipes[1]);
    $error = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return new ReleaseRun(proc_close($process), $output, $error, implode(' ', $command));
}

/**
 * The files a cell links, as the target it was written as and the path it resolves to.
 *
 * A link that points somewhere this checkout does not have — a URL, a `mailto:`, an anchor — is
 * left out: the question here is which files the row names, and those name none.
 *
 * @return array<string, string>
 */
function guardIndexPaths(string $root, string $document, string $cell): array
{
    $paths = [];

    foreach (Docs::linksIn($cell) as $link) {
        $path = Docs::resolve($root, $document, $link['target']);

        if ($path !== null) {
            $paths[$link['target']] = $path;
        }
    }

    return $paths;
}
