<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\IndexNumbers;
use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The numbers the index states
|--------------------------------------------------------------------------
|
| `docs/guards.md` is the page this package keeps its own account of itself on: how many guards it
| keeps, how many checks the gate runs, what floor every file is held to, and how many files the
| two lists below the table name. Every one of those is a fact about the code — a `$checks` array,
| a `FLOORS` constant, a directory — and every one of them was written into a sentence and left
| there. The lists are rendered; the numbers were trusted.
|
| `IndexNumbers` reads each number out of the sentence that states it and compares it with the code
| that produces it. This file asserts the two halves of that: the reading answers with nothing on
| the tree this package ships, and it names the file and the number when the document and the code
| are pulled apart — one mutation at a time, in a copy, with the whole tree otherwise in step.
|
| @guards-index guard
*/

it('reads the gate, the floors and the counts out of the code', function (): void {
    $root = str_replace('\\', '/', packageRoot());

    $checks = IndexNumbers::checks($root);
    $floors = IndexNumbers::floors($root);

    // Substance first: a parse that found nothing agrees with a document that states nothing, and
    // the two names here are ones no reading of the gate's own list could have missed.
    expect($checks)->toContain('syntax')
        ->and($checks)->toContain('tests')
        ->and(IndexNumbers::word(count($checks)))->not->toBe((string) count($checks))
        ->and($floors['source'])->toBe(100.0)
        ->and($floors['scripts'])->toHaveKey('bin/checks.php');

    // The two readings are the same fact said two ways, which is the whole point: the word a
    // sentence states the count with, and the count the program defines.
    expect(IndexNumbers::word(9))->toBe('nine')
        ->and(IndexNumbers::word(11))->toBe('eleven')
        ->and(IndexNumbers::word(21))->toBe('21');
});

it('states in the index only the numbers the code produces', function (): void {
    $root = str_replace('\\', '/', packageRoot());
    $counts = IndexNumbers::counts($root);

    expect(IndexNumbers::drift($root))->toBe([])
        ->and($counts['guards'])->toBeGreaterThan(0)
        ->and($counts['readings'])->toBeGreaterThan(0)
        ->and($counts['support'])->toBeGreaterThan(0)
        ->and($counts['records'])->toBeGreaterThan(0)
        // The three counts the page states are the same three the directory produces: the two
        // lists partition the directory, so a file cannot hide between them.
        ->and($counts['files'])->toBe($counts['readings'] + $counts['support']);
});

it('names the number when the gate gains a check and the page keeps the old one', function (): void {
    $root = indexNumbersRoot();

    indexNumbersReplace($root, 'docs/guards.md', 'nine checks in one process', 'ten checks in one process');

    $findings = implode(PHP_EOL, IndexNumbers::drift($root));

    expect($findings)->toContain('the row says "ten checks in one process"')
        ->and($findings)->toContain('docs/guards.md:')
        ->and($findings)->toContain('defines 9 (nine)');
});

it('names the floor when the code raises one and the page does not', function (): void {
    $root = indexNumbersRoot();

    indexNumbersReplace($root, 'bin/coverage.php', "'bin/tool.php' => 86,", "'bin/tool.php' => 90,");

    $findings = implode(PHP_EOL, IndexNumbers::drift($root));

    expect($findings)->toContain('bin/tool.php')
        ->and($findings)->toContain('records 90');
});

it('names the count when a guard is added to the table', function (): void {
    $root = indexNumbersRoot();

    indexNumbersReplace(
        $root,
        'docs/guards.md',
        '| `bin/index.php` and the two rendered lists |',
        "| a guard added to a copy | its own row now | [`../bin/index.php`](../bin/index.php) |\n| `bin/index.php` and the two rendered lists |",
    );

    // The count the reading has to name is the one the table produces with this fixture's row in
    // it, computed rather than typed: the assertion is about a number that moved, and a typed
    // number would go stale on the next guard the table gains — which is the failure this reading
    // exists to catch, one level up.
    $guards = IndexNumbers::counts(packageRoot())['guards'];

    expect(implode(PHP_EOL, IndexNumbers::drift($root)))
        ->toContain('how many guards the table records')
        ->toContain('produces '.($guards + 1));
});

it('names the counts when a reading arrives and the two lists are not re-counted', function (): void {
    $root = indexNumbersRoot();

    indexNumbersWrite($root, 'tests/Support/Extra.php', "<?php\n\ndeclare(strict_types=1);\n\n/**\n * A reading nobody counted.\n *\n * @guards-index reading\n */\nfinal class Extra\n");

    // Both numbers the reading names are the directory's, read out of the fixture rather than
    // typed: the page this copy was taken from states the count it stated yesterday, and a typed
    // assertion would go stale the next time a reading is added — which is the drift this reading
    // exists to name.
    $counts = IndexNumbers::counts($root);
    $findings = implode(PHP_EOL, IndexNumbers::drift($root));

    expect($findings)->toContain('readings and '.$counts['support'].' files that are not guards')
        ->and($findings)->toContain($counts['readings'].' and '.$counts['support']);
});

it('refuses a document that has stopped stating the number at all', function (): void {
    $root = indexNumbersRoot();

    // The sentence replaced is the page's own, built from the count it states rather than typed:
    // this fixture is about a number that stopped being stated at all, not about how many guards
    // there are today.
    $guards = IndexNumbers::counts(packageRoot())['guards'];

    indexNumbersReplace($root, 'docs/guards.md', 'The table records '.$guards.' guards.', 'The table records many guards.');

    expect(implode(PHP_EOL, IndexNumbers::drift($root)))
        ->toContain('no longer states how many guards the table records');
});

it('names the docs-record count when the reading beside it goes stale', function (): void {
    $root = indexNumbersRoot();

    indexNumbersReplace($root, 'tests/Support/Docs.php', 'fourteen of them', 'nine of them');

    $findings = implode(PHP_EOL, IndexNumbers::drift($root));

    expect($findings)->toContain('tests/Support/Docs.php')
        ->and($findings)->toContain('nine of them')
        ->and($findings)->toContain('holds 14');
});

it('catches the gate\'s size said outside the index', function (): void {
    $root = indexNumbersRoot();

    // The same claim in another file, which is what makes the phrase rather than the row the
    // reading: the README, the rules and the workflow write it too, and each should go red.
    indexNumbersWrite($root, 'README.md', "# A package\n\nIt is ten checks in one process: one summary, one exit code.\n");

    $findings = implode(PHP_EOL, IndexNumbers::drift($root));

    expect($findings)->toContain('README.md:')
        ->and($findings)->toContain('ten checks in one process');
});

/**
 * A copy of the four things this reading reads: the two programs, the index, and the directory the
 * two lists name.
 *
 * Copied rather than planted from a fixture, because the numbers are facts about this package's own
 * files: a hand-written `bin/checks.php` would be a second gate, and a mutation has to be the only
 * difference between the answer the reading gives and the one it is expected to give. It is a git
 * repository so that the phrase scan can ask for the files a commit would carry, which is the same
 * question the mutation harness asks of its tree.
 */
function indexNumbersRoot(): string
{
    $root = sys_get_temp_dir().'/rc-index-numbers-'.bin2hex(random_bytes(6));

    foreach (['bin', 'docs', 'tests/Support'] as $directory) {
        indexNumbersCopy(packageRoot().'/'.$directory, $root.'/'.$directory);
    }

    indexNumbersGit($root, ['init', '--quiet']);
    indexNumbersGit($root, ['add', '--all', '--force']);

    register_shutdown_function(static function () use ($root): void {
        ReleaseRepo::remove($root);
    });

    return str_replace('\\', '/', $root);
}

/** One directory of this package, copied into a tree. */
function indexNumbersCopy(string $from, string $to): void
{
    if (! is_dir($to)) {
        mkdir($to, 0o777, true);
    }

    foreach (glob($from.'/*') ?: [] as $path) {
        if (is_dir($path)) {
            indexNumbersCopy($path, $to.'/'.basename($path));

            continue;
        }

        copy($path, $to.'/'.basename($path));
    }
}

/** A file in the planted tree, with the directories above it. */
function indexNumbersWrite(string $root, string $file, string $contents): void
{
    if (! is_dir(dirname($root.'/'.$file))) {
        mkdir(dirname($root.'/'.$file), 0o777, true);
    }

    file_put_contents($root.'/'.$file, $contents);
}

/**
 * A break planted in the planted tree.
 *
 * It raises when the needle has stopped matching, for the same reason the mutation harness does: a
 * mutation whose `str_replace` found nothing leaves the tree whole, and the reading would then be
 * asserted against a tree that has nothing wrong with it.
 */
function indexNumbersReplace(string $root, string $file, string $needle, string $replacement): void
{
    $was = (string) file_get_contents($root.'/'.$file);
    $now = str_replace($needle, $replacement, $was);

    if ($now === $was) {
        throw new RuntimeException('The needle "'.$needle.'" no longer matches '.$file.', so there is nothing to catch.');
    }

    file_put_contents($root.'/'.$file, $now);
}

/**
 * A git command in the planted tree, so the phrase scan can be asked which files a commit would
 * carry.
 *
 * @param  list<string>  $arguments
 */
function indexNumbersGit(string $root, array $arguments): void
{
    $process = proc_open(
        ['git', '-C', $root, ...$arguments],
        [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes,
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Could not run git in '.$root);
    }

    fclose($pipes[1]);

    if (proc_close($process) !== 0) {
        throw new RuntimeException('`git '.implode(' ', $arguments).'` failed in '.$root);
    }
}
