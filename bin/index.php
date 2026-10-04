#!/usr/bin/env php
<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\GuardIndex;

/*
|--------------------------------------------------------------------------
| Render the three tables in docs/guards.md
|--------------------------------------------------------------------------
|
| The guard index has three tables. The first is the guards themselves; the other two are lists of
| files — the readings the guards are built from, and the files beside them that are fixtures rather
| than guards. All three are renderings now, and each is sourced from the tree rather than written
| into the page.
|
| A guard is declared where it lives: one comment block in the file its row names carries
| `@guards-index guard <name>`, and the first sentence of that block says what it catches. The file
| is the `Where` cell, so a guard that moves moves its row with it. The block is read in whichever
| comment the file writes in — a PHP banner or docblock, a run of `#` lines, an XML comment —
| because a guard lives where it lives.
|
| The two lists below it work the same way and one step further down: a file in `tests/Support/`
| declares `@guards-index reading` or `@guards-index support`, and its own first sentence is the
| words beside its row.
|
| The test side is the same question the other way. Every test in `tests/Unit/` declares
| `@guards-index guard <name>` (naming its own row) or `@guards-index test`, so a guard cannot
| arrive with a test and a reading and read as though it had always been in the list.
|
|   composer index            rewrite the three tables
|   bin/index.php --check     report the differences, and write nothing
|
| `--check` is the same reading with nothing written, and it is what the suite asserts on — a
| document that has stopped agreeing with the tree fails `tests/Unit/Docs/GuardIndexTest.php`
| rather than being noticed by whoever reads the page next. The reading itself is in
| `tests/Support/GuardIndex.php`, which is where a check on the index belongs; this file is the
| command around it, so a person, a script and the suite all ask one question.
|
| Exit code is 0 when the document and the tree agree, or when the document was written from them, 1
| when they disagree or a file cannot be read as a row, and 2 for a usage error — the same three
| answers the rest of `bin/` gives.
*/

/**
 * @guards-index guard `bin/index.php` and the three rendered tables
 *
 * a table that has stopped being a rendering of the tree: which list a file in `tests/Support/` is in, the words beside its row, and each guard's own row are read from the file itself, this program writes the three tables below from that, and `--check` is what fails when the document and the tree disagree
 */
$root = str_replace('\\', '/', dirname(__DIR__));

$autoload = $root.'/vendor/autoload.php';

// The reading is in `tests/Support/`, which Composer autoloads for development. A checkout that has
// been installed has one; a checkout that has not cannot have the reading either, and that is worth
// one sentence rather than a fatal about a file.
if (! is_file($autoload)) {
    fwrite(STDERR, 'composer install first: this reads tests/Support/, and the autoloader is what finds it.'.PHP_EOL);
    exit(2);
}

require $autoload;

$check = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--check') {
        $check = true;

        continue;
    }

    if ($argument === '--help' || $argument === '-h') {
        usage();
        exit(0);
    }

    fwrite(STDERR, $argument.' is not an option this program has.'.PHP_EOL);
    usage();

    exit(2);
}

$document = $root.'/'.GuardIndex::DOCUMENT;
$markdown = @file_get_contents($document);

if ($markdown === false) {
    fwrite(STDERR, GuardIndex::DOCUMENT.' is not here: the three tables live in it, and this program does not make the file.'.PHP_EOL);
    exit(1);
}

try {
    // Both halves, read before anything is written: the report a run prints is what the reading
    // said was wrong, and after the write there is nothing left to say.
    $drift = GuardIndex::drift($root);

    if ($check) {
        if ($drift === []) {
            fwrite(STDOUT, GuardIndex::DOCUMENT.' renders exactly what the tree declares: every guard, and every file in '.GuardIndex::DIRECTORY.'/.'.PHP_EOL);
            exit(0);
        }

        refuse($drift);

        exit(1);
    }

    $rewritten = GuardIndex::rewritten($root, $markdown);

    if ($rewritten === $markdown) {
        fwrite(STDOUT, GuardIndex::DOCUMENT.' already renders exactly what the tree declares.'.PHP_EOL);
        exit(0);
    }

    if (file_put_contents($document, $rewritten) === false) {
        fwrite(STDERR, 'Could not write '.GuardIndex::DOCUMENT.'.'.PHP_EOL);
        exit(1);
    }

    // The rows that were replaced are the report: what this wrote is what the reading said was
    // wrong, and printing it is how a reader learns that a file moved rather than that a document
    // was touched. Read before the write, because after it there is nothing left to say.
    fwrite(STDOUT, 'Rewrote the three tables in '.GuardIndex::DOCUMENT.':'.PHP_EOL);

    foreach ($drift as $finding) {
        fwrite(STDOUT, '  - '.$finding.PHP_EOL);
    }

    // And the whole reading is asked again, because a program that writes what it just reported
    // should be able to say that the tree and the document agree now. A document that was written
    // and still disagrees is a defect in this program rather than in the tree, and it must not read
    // as a success.
    if (GuardIndex::drift($root) !== []) {
        fwrite(STDERR, PHP_EOL.GuardIndex::DOCUMENT.' was written and still disagrees with '.GuardIndex::DIRECTORY.'/. That is a defect in this program.'.PHP_EOL);
        exit(1);
    }

    exit(0);
} catch (RuntimeException $failure) {
    // A file that declares no list, one that declares a list this package does not have, a document
    // with no table under a heading: each of those is a row that cannot be written, and a program
    // that wrote the other rows and said nothing about this one would be publishing a list with a
    // hole in it.
    fwrite(STDERR, $failure->getMessage().PHP_EOL);
    exit(1);
}

/**
 * The two lists, and the reason there are only two.
 */
function usage(): void
{
    fwrite(STDERR, <<<'TXT'
    bin/index.php — render the three tables in docs/guards.md.

    Usage:
      composer index                 Rewrite the three tables from the tree.
      bin/index.php --check          Report what has drifted, and write nothing.

    A guard is declared where it lives: a comment block in the file its row names carries
    `@guards-index guard <name>`, and the first sentence of that block is what it catches.
    A file in tests/Support/ declares `@guards-index reading` or `@guards-index support`,
    and the first sentence of its own header is the words its row carries. A test in
    tests/Unit/ declares `@guards-index guard <name>` or `@guards-index test`. Run either
    form under this machine's PHP — `.agents/php-bin.sh` decides which that is, and
    `sh .agents/php-bin.sh --show` says so.

    Exit code: 0 in step or rewritten, 1 a difference or a file that cannot be read
    as a row, 2 a usage error.

    TXT);
}

/**
 * A drift report, on the stream a refusal belongs on: the exit code says the document is wrong,
 * and these lines say which file it is wrong about.
 *
 * @param  list<string>  $drift
 */
function refuse(array $drift): void
{
    fwrite(STDERR, 'The tables in '.GuardIndex::DOCUMENT.' have drifted from the tree that declares them:'.PHP_EOL);

    foreach ($drift as $finding) {
        fwrite(STDERR, '  - '.$finding.PHP_EOL);
    }

    fwrite(STDERR, PHP_EOL.'Write them again with: bin/index.php'.PHP_EOL);
}
