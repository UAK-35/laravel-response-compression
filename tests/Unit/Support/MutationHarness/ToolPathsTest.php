<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MutationHarness;

/*
|--------------------------------------------------------------------------
| The tool-path reading, broken under itself
|--------------------------------------------------------------------------
|
| Two breaks. A tool's path written down in a program that already has a name for it is the
| second copy the manifest exists to stop — and the path is read out of the manifest rather than
| typed, so the marker is a path this tree really carries, because a fixture with a made-up path
| would prove the reading looks at the file without proving it compares the right list. A program
| asking the manifest for a name it does not have is the typo that reads as "not installed" at
| runtime, a skip in the summary that looks like a machine without the tool.
|
| The mutation is read twice on the same tree — once as this checkout ships it, where the reading
| has to answer with nothing, and once with the break in it, where it has to come back carrying
| the break's own marker rather than merely something. That is what tells a reading which went
| blind apart from one that answered nothing because nothing was wrong. The inversion is the other
| direction: a tool name cannot be read out of a program, so nothing is asked for and the
| manifest's names all read as names nothing runs, and the guard's tests have to go red under it.
| Both directions read `ToolPaths::copies()` and `ToolPaths::unsharedNames()` rather than a second
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

foreach (MutationHarness::mutations('tests/Unit/Support/ToolPathsTest.php') as $mutation) {
    it(sprintf('turns %s red when this checkout is broken under it', $mutation['guard']), function () use ($mutation): void {
        $root = MutationHarness::tree();
        $read = $mutation['read'];

        expect(implode(PHP_EOL, $read($root)))->toBe('');

        $marker = ($mutation['plant'])($root);

        expect($marker)->not->toBe('')
            ->and(implode(PHP_EOL, $read($root)))->toContain($marker);
    });
}

foreach (MutationHarness::inversions('tests/Unit/Support/ToolPathsTest.php') as $inversion) {
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
