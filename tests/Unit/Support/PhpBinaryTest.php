<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\PhpBinary;
use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| Which PHP this repository runs, as the PHP side reads it
|--------------------------------------------------------------------------
|
| `.agents/php-bin.sh` answers "which interpreter" once, for the hook, for the workflow and for the
| two programs in `bin/` that need it. This reading is how the PHP side asks, and what is asserted
| here is the half of the question a shell script cannot answer for a program that is already
| running: whether the interpreter this run is under is the one this machine names.
|
| Three of these cases cannot be arranged on a machine: a resolver that answers from a fallback, one
| that answers a sentence instead of a path, and one that is not there at all. They are handed in,
| the way `CoverageEnvironment` takes its extensions, because a test that had to be run on the
| machine it is about is a test that would pass by being skipped.
|
| The one case that is asked of this checkout is the one a fixture cannot answer: that the resolver
| this package ships is a file and that the answer it gives about this tree resolves to something.
| A fixture proves the reading reads; only the real tree proves there is something to read.
|
| @guards-index test
*/

/**
 * A tree that looks like a checkout with a resolver in it, whose answer is planted - and, unless
 * `$report` is null, that resolver.
 *
 * The machine file is there because it is what makes the question worth putting: where this machine
 * names no interpreter, the resolution can only be a fallback, and the reading does not even start
 * the shell to be told so. A fixture without it is a checkout that has never run `--init`, which is
 * the last case below.
 */
function phpBinaryRepo(?string $report = null): ReleaseRepo
{
    $repo = ReleaseRepo::plain();
    $repo->write('.agents/machine.local.json', "{}\n");

    if ($report !== null) {
        // Not executable, and not marked so: the reading runs it through `sh`, which is what the
        // hook does and what a checkout on a filesystem with no exec bit can still do.
        $repo->write('.agents/php-bin.sh', "#!/bin/sh\nprintf '%s' '".$report."'\n");
    }

    return $repo;
}

it('reads the interpreter and the rung out of the resolver, and compares them with this run', function (): void {
    $repo = phpBinaryRepo('.agents/machine.local.json'."\t".'/other/install/php');
    $binary = PhpBinary::named($repo->path);

    expect($binary->names)->toBe('/other/install/php')
        ->and($binary->rung)->toBe(PhpBinary::MACHINE)

        // The file this machine names is a decision about this machine, so this is the case the
        // reading exists for: the two interpreters are different, and the sentence says which is
        // which rather than only that they differ.
        ->and($binary->decided())->toBeTrue()
        ->and($binary->agrees())->toBeFalse()
        ->and($binary->source())->toBe('.agents/machine.local.json')
        ->and($binary->disagreement())
        ->toContain(PhpBinary::running())
        ->toContain('/other/install/php')
        ->toContain('.agents/machine.local.json');
});

it('compares the two spellings of one interpreter rather than the two strings', function (): void {
    // The same interpreter, written the way the machine file writes it. A comparison of strings
    // would call this a disagreement on every machine whose file spells its path differently from
    // PHP_BINARY, which is the case this reading would then get wrong for everyone.
    $forward = str_replace('\\', '/', PHP_BINARY);

    expect(PhpBinary::named('/nowhere', PhpBinary::ENVIRONMENT."\t".$forward)->agrees())->toBeTrue()
        ->and(PhpBinary::named('/nowhere', PhpBinary::ENVIRONMENT."\t".$forward)->disagreement())->toBe('');
});

it('treats a fallback as an answer rather than as a second opinion', function (): void {
    // On a runner there is no machine file, the resolution comes from PATH, and the PHP that
    // installed the dependencies is the first `php` on PATH as often as not. Printing a note about
    // that would be a note on every build about the arrangement rather than about a mistake.
    $fallback = PhpBinary::named('/nowhere', 'PATH'."\t".'/other/install/php');

    expect($fallback->decided())->toBeFalse()
        ->and($fallback->agrees())->toBeFalse()
        ->and($fallback->disagreement())->toBe('');
});

it('does not ask at all about a tree that cannot have an answer', function (): void {
    // A checkout nobody has run `--init` in, which is every runner and every fixture: no machine
    // file, so the resolver could only answer from a fallback, and a fallback is not a second
    // opinion. The shell is not started for it, which matters because `bin/tool.php` asks once per
    // tool it runs and a resolution costs a shell and a renderer through it.
    $without = phpBinaryRepo();
    $without->write('.agents/php-bin.sh', "#!/bin/sh\nprintf '%s' 'the environment\t/other/install/php'\n");
    $without->delete('.agents/machine.local.json');

    expect(PhpBinary::named($without->path)->names)->toBe('')
        ->and(PhpBinary::named($without->path)->agrees())->toBeNull()
        ->and(PhpBinary::named($without->path)->disagreement())->toBe('');

    // And with the machine file there but no resolver: a checkout with an answer and nothing to ask,
    // which is "not asked" too rather than a disagreement with itself.
    $noResolver = phpBinaryRepo();

    expect(PhpBinary::named($noResolver->path)->names)->toBe('')
        ->and(PhpBinary::named($noResolver->path)->agrees())->toBeNull();
});

it('answers null rather than false when there is nothing to compare', function (): void {
    // No resolver, no shell, or an answer that is not a report: three ways to have no answer, and
    // all three are "not asked". A reading that called them `false` would put a note at the head of
    // every run on a machine where the question could not be put.
    $missing = PhpBinary::named('/nowhere');

    expect($missing->names)->toBe('')
        ->and($missing->rung)->toBe('')
        ->and($missing->agrees())->toBeNull()
        ->and($missing->disagreement())->toBe('')

        // A sentence where a report was expected: the resolver explains what is missing when it
        // cannot resolve the token, and an explanation is not an interpreter.
        ->and(PhpBinary::named('/nowhere', 'no value for __PHP_EXE__')->agrees())->toBeNull()
        ->and(PhpBinary::named('/nowhere', '')->agrees())->toBeNull();
});

it('reads this checkout, and the interpreter it names is a program', function (): void {
    // The two facts a fixture cannot answer: that the resolver this package ships is here, and that
    // whatever it answers about this tree is a file rather than a sentence. Which rung answers is a
    // fact about the machine — a checkout with no `--init` yet answers from PATH, and one with no
    // PHP answers nothing — so the assertions are the shape of the answer, not its value.
    $binary = PhpBinary::named(packageRoot());

    expect(packageRoot().'/'.PhpBinary::RESOLVER)->toBeFile()

        ->and($binary->rung === '' || in_array($binary->rung, [PhpBinary::MACHINE, PhpBinary::ENVIRONMENT, ...PhpBinary::FALLBACKS], true))
        ->toBeTrue();

    if ($binary->names !== '') {
        expect(is_file($binary->names))->toBeTrue();
    }
});
