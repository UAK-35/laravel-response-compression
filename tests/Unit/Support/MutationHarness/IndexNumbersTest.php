<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MutationHarness;

/*
|--------------------------------------------------------------------------
| The number-claims reading, broken under itself
|--------------------------------------------------------------------------
|
| The break this file plants is the gate gaining a check while the index's sentence keeps the
| number it was written with: true the day it was typed, and with nothing in the document to tell
| it that the array below it grew. It is a row turned into a sentence — the same claim written as
| prose, where no rendering holds it to the program it describes.
|
| The mutation is read twice on the same tree — once as this checkout ships it, where the reading
| has to answer with nothing, and once with the break in it, where it has to come back carrying
| the break's own marker rather than merely something. That is what tells a reading which went
| blind apart from one that answered nothing because nothing was wrong. The inversion is the other
| direction: the gate's check keys are no longer parsed, so the count the document states has
| nothing to be compared with, and the guard's tests have to go red under it — a test that stays
| green while its reading cannot fire is the vacuous test this harness exists to refuse. Both
| directions read `IndexNumbers::drift()` rather than a second implementation of it kept here.
|
| @guards-index test
|
*/

beforeEach(function (): void {
    // Every break is undone before the next one, so no mutation is ever read through the one
    // before it: two breaks in one tree is the thing this harness exists not to do.
    MutationHarness::restore();
});

foreach (MutationHarness::mutations('tests/Unit/Docs/IndexNumbersTest.php') as $mutation) {
    it(sprintf('turns %s red when this checkout is broken under it', $mutation['guard']), function () use ($mutation): void {
        $root = MutationHarness::tree();
        $read = $mutation['read'];

        expect(implode(PHP_EOL, $read($root)))->toBe('');

        $marker = ($mutation['plant'])($root);

        expect($marker)->not->toBe('')
            ->and(implode(PHP_EOL, $read($root)))->toContain($marker);
    });
}

foreach (MutationHarness::inversions('tests/Unit/Docs/IndexNumbersTest.php') as $inversion) {
    it(sprintf('turns %s red when its detector is rewritten under it', $inversion['guard']), function () use ($inversion): void {
        $blinded = MutationHarness::blinded($inversion);

        // Red, and for the right reason: a parse error is not a broken detector, and `FAILED` is
        // the run having reached an assertion rather than a fatal before one.
        expect($blinded['exit'])->not->toBe(0)
            ->and($blinded['output'])->not->toContain('PHP Parse error')
            ->and($blinded['output'])->not->toContain('Error in bootstrap script')
            ->and($blinded['output'])->toContain('FAILED');
    });
}
