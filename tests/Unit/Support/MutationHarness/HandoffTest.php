<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MutationHarness;

/*
|--------------------------------------------------------------------------
| The environment table, broken under itself
|--------------------------------------------------------------------------
|
| The break this file plants is a row the environment table does not have: a token written into
| `AGENTS.md` §2 that `.agents/render_local.py` has never heard of, which is what a table looks like
| when it is written ahead of the file meant to supply it. The value behind a row is one thing; the
| row naming something no file answers for is another, and it is the one that reads as a described
| environment on a machine where every value happens to be in place.
|
| The reading is taken twice on the same tree — once as this checkout ships it, where it has to
| answer with nothing, and once with the row in it, where it has to come back carrying that token
| rather than merely something. A reading that answered with nothing both times is the failure this
| exists to tell apart from a guard that answered nothing because nothing was wrong.
|
| Read through the guard's own call — `Handoff::stale()` — rather than a second implementation of it
| kept here, and carried in a file of its own so `paratest`, which spreads work by file, hands this
| read to a worker of its own rather than to whichever one picked up the file the rest are in.
|
| @guards-index test
|
*/

beforeEach(function (): void {
    // Every break is undone before the next one, so no mutation is ever read through the one
    // before it: two breaks in one tree is the thing this harness exists not to do.
    MutationHarness::restore();
});

foreach (MutationHarness::mutations('tests/Unit/Support/HandoffTest.php') as $mutation) {
    it(sprintf('turns %s red when this checkout is broken under it', $mutation['guard']), function () use ($mutation): void {
        $root = MutationHarness::tree();
        $read = $mutation['read'];

        expect(implode(PHP_EOL, $read($root)))->toBe('');

        $marker = ($mutation['plant'])($root);

        expect($marker)->not->toBe('')
            ->and(implode(PHP_EOL, $read($root)))->toContain($marker);
    });
}
