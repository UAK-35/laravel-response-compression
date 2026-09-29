#!/usr/bin/env php
<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\GuardIndex;

/*
|--------------------------------------------------------------------------
| Render the two lists of files in docs/guards.md
|--------------------------------------------------------------------------
|
| The guard index has three tables. The first is the guards themselves, written by hand because
| what a guard refuses is an argument. The other two are lists of files — the readings the guards
| are built from, and the files beside them that are fixtures rather than guards — and a list of
| files is not an argument, it is a fact about `tests/Support/`, which is why it went stale the
| moment a file was renamed and why nobody could see it happen.
|
| So both are a rendering now. Each file declares which list it is in, in its own header
| (`@guards-index reading` or `@guards-index support`), and the words beside its row are the first
| sentence of that header. The directory is the list of files, the file is the decision about
| itself, and this program writes the document from the two.
|
|   composer index            rewrite the two lists
|   bin/index.php --check     report the differences, and write nothing
|
| `--check` is the same reading with nothing written, and it is what the suite asserts on — a
| document that has stopped agreeing with the directory fails `tests/Unit/Docs/GuardIndexTest.php`
| rather than being noticed by whoever reads the page next. The reading itself is in
| `tests/Support/GuardIndex.php`, which is where a check on the index belongs; this file is the
| command around it, so a person, a script and the suite all ask one question.
|
| Exit code is 0 when the document and the directory agree, or when the document was written from
| them, 1 when they disagree or a file cannot be read as a row, and 2 for a usage error — the same
| three answers the rest of `bin/` gives.
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
    fwrite(STDERR, GuardIndex::DOCUMENT.' is not here: the two lists live in it, and this program does not make the file.'.PHP_EOL);
    exit(1);
}

try {
    $drift = GuardIndex::drift($root);

    if ($check) {
        if ($drift === []) {
            fwrite(STDOUT, GuardIndex::DOCUMENT.' lists exactly what '.GuardIndex::DIRECTORY.'/ declares.'.PHP_EOL);
            exit(0);
        }

        refuse($drift);

        exit(1);
    }

    $rewritten = GuardIndex::rewritten($root, $markdown);

    if ($rewritten === $markdown) {
        fwrite(STDOUT, GuardIndex::DOCUMENT.' already lists exactly what '.GuardIndex::DIRECTORY.'/ declares.'.PHP_EOL);
        exit(0);
    }

    if (file_put_contents($document, $rewritten) === false) {
        fwrite(STDERR, 'Could not write '.GuardIndex::DOCUMENT.'.'.PHP_EOL);
        exit(1);
    }

    // The rows that were replaced are the report: what this wrote is what the reading said was
    // wrong, and printing it is how a reader learns that a file moved rather than that a document
    // was touched. Read before the write, because after it there is nothing left to say.
    fwrite(STDOUT, 'Rewrote the two lists in '.GuardIndex::DOCUMENT.':'.PHP_EOL);

    foreach ($drift as $finding) {
        fwrite(STDOUT, '  - '.$finding.PHP_EOL);
    }

    // And the reading is asked again, because a program that writes what it just reported should be
    // able to say that the two agree now. A document that was written and still disagrees is a
    // defect in this program rather than in the tree, and it must not read as a success.
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
    bin/index.php — render the two lists of files in docs/guards.md.

    Usage:
      composer index                 Rewrite the two lists from tests/Support/.
      bin/index.php --check          Report what has drifted, and write nothing.

    A file in tests/Support/ says which list it is in with `@guards-index reading` or
    `@guards-index support` in its header, and the first sentence of that header is
    the words its row carries. Run either form under this machine's PHP — the one
    `.agents/machine.local.json` names, printed by
    `python .agents/render_local.py --value __PHP_EXE__`.

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
    fwrite(STDERR, 'The two lists in '.GuardIndex::DOCUMENT.' have drifted from '.GuardIndex::DIRECTORY.'/:'.PHP_EOL);

    foreach ($drift as $finding) {
        fwrite(STDERR, '  - '.$finding.PHP_EOL);
    }

    fwrite(STDERR, PHP_EOL.'Write them again with: bin/index.php'.PHP_EOL);
}
