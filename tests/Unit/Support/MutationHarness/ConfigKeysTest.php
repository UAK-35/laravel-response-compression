<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MutationHarness;

/*
|--------------------------------------------------------------------------
| The config-key reading, broken under itself
|--------------------------------------------------------------------------
|
| Two breaks, one for each half of the contract this guard holds. A reader asking for a key the
| config file does not publish is the `brotli.level` defect written out as a file rather than
| found in one; a reader renamed in the class the list describes is the other half, where the
| scan and the list disagree and a key reads invisibly in both directions at once.
|
| The mutation is read twice on the same tree — once as this checkout ships it, where the reading
| has to answer with nothing, and once with the break in it, where it has to come back carrying
| the break's own marker rather than merely something. That is what tells a reading which went
| blind apart from one that answered nothing because nothing was wrong. The inversion is the other
| direction: the walk over `src/` returns before it descends, so the source reads as a tree that
| asks for no key at all, and the guard's tests have to go red under it — a test that stays green
| while its reading cannot fire is the vacuous test this harness exists to refuse. Both directions
| read `ConfigKeys::unknown()` and `ConfigKeys::unknownReaders()` rather than a second
| implementation of them kept here.
|
| @guards-index test
|
*/

beforeEach(function (): void {
    // Every break is undone before the next one, so no mutation is ever read through the one
    // before it: two breaks in one tree is the thing this harness exists not to do.
    MutationHarness::restore();
});

foreach (MutationHarness::mutations('tests/Unit/Config/ConfigKeysTest.php') as $mutation) {
    it(sprintf('turns %s red when this checkout is broken under it', $mutation['guard']), function () use ($mutation): void {
        $root = MutationHarness::tree();
        $read = $mutation['read'];

        expect(implode(PHP_EOL, $read($root)))->toBe('');

        $marker = ($mutation['plant'])($root);

        expect($marker)->not->toBe('')
            ->and(implode(PHP_EOL, $read($root)))->toContain($marker);
    });
}

foreach (MutationHarness::inversions('tests/Unit/Config/ConfigKeysTest.php') as $inversion) {
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
