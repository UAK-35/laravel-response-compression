<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MutationHarness;

/*
|--------------------------------------------------------------------------
| The repository-escape reading, broken under itself
|--------------------------------------------------------------------------
|
| The break this file plants is the shape the guard was written for and the machine-path reading
| could not see: a relative path that climbs above the repository root, which leaks a layout
| rather than a machine because it resolves wherever the checkout happens to sit.
|
| The mutation is read twice on the same tree — once as this checkout ships it, where the reading
| has to answer with nothing, and once with the break in it, where it has to come back carrying
| the break's own marker rather than merely something. That is what tells a reading which went
| blind apart from one that answered nothing because nothing was wrong. The inversion is the other
| direction: the reading's own token is replaced with one no path can match, and the guard's tests
| have to go red under it — a test that stays green while its reading cannot fire is the vacuous
| test this harness exists to refuse. Both directions read `RepoEscapes::scan()` rather than a
| second implementation of it kept here.
|
| @guards-index test
|
*/

beforeEach(function (): void {
    // Every break is undone before the next one, so no mutation is ever read through the one
    // before it: two breaks in one tree is the thing this harness exists not to do.
    MutationHarness::restore();
});

foreach (MutationHarness::mutations('tests/Unit/Support/RepoEscapesTest.php') as $mutation) {
    it(sprintf('turns %s red when this checkout is broken under it', $mutation['guard']), function () use ($mutation): void {
        $root = MutationHarness::tree();
        $read = $mutation['read'];

        expect(implode(PHP_EOL, $read($root)))->toBe('');

        $marker = ($mutation['plant'])($root);

        expect($marker)->not->toBe('')
            ->and(implode(PHP_EOL, $read($root)))->toContain($marker);
    });
}

foreach (MutationHarness::inversions('tests/Unit/Support/RepoEscapesTest.php') as $inversion) {
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
