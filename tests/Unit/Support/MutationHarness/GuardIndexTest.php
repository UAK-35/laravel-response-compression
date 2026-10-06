<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MutationHarness;

/*
|--------------------------------------------------------------------------
| The guard-index reading, broken under itself
|--------------------------------------------------------------------------
|
| Three breaks, one for each way the table can stop being a rendering of the tree. A reading added
| to `tests/Support/` that the two lists do not name is a file nobody decided about; a test that
| declares itself a guard with no row in the table is the guard that arrived unrecorded from the
| test side; and a guard declared in a program that no row records is the rendering's own half.
| Each marker is what the reading prints, so a reading that answered "nothing" for every tree is
| told apart from one that answered "nothing" because it never looked at the tests at all.
|
| The mutation is read twice on the same tree — once as this checkout ships it, where the reading
| has to answer with nothing, and once with the break in it, where it has to come back carrying
| the break's own marker rather than merely something. The inversion is the other direction: the
| table parser answers with no header and no rows — the shape a document with no table has — so
| there is nothing to resolve, and the guard's tests have to go red under it. Both directions read
| `GuardIndex::drift()` rather than a second implementation of it kept here.
|
| @guards-index test
|
*/

beforeEach(function (): void {
    // Every break is undone before the next one, so no mutation is ever read through the one
    // before it: two breaks in one tree is the thing this harness exists not to do.
    MutationHarness::restore();
});

foreach (MutationHarness::mutations('tests/Unit/Docs/GuardIndexTest.php') as $mutation) {
    it(sprintf('turns %s red when this checkout is broken under it', $mutation['guard']), function () use ($mutation): void {
        $root = MutationHarness::tree();
        $read = $mutation['read'];

        expect(implode(PHP_EOL, $read($root)))->toBe('');

        $marker = ($mutation['plant'])($root);

        expect($marker)->not->toBe('')
            ->and(implode(PHP_EOL, $read($root)))->toContain($marker);
    });
}

foreach (MutationHarness::inversions('tests/Unit/Docs/GuardIndexTest.php') as $inversion) {
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
