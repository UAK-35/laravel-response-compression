<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\HandoffState;
use Uak35\ResponseCompression\Tests\Support\MutationHarness;

/*
|--------------------------------------------------------------------------
| The handoff's state section, broken under itself
|--------------------------------------------------------------------------
|
| Two breaks, one for each half of what the section claims. The first puts back the sentence the
| reading was written for — `bin/commit.php` "can only write a bullet into an LF file", true the day
| it was typed and false from `ca776f0` — so a reading that stopped comparing the claim with the
| script's own line answers with nothing and is caught here. The second writes `HEAD` and how far
| ahead the branch is with no date on the bullet, which is the count that moves with every commit and
| the half a snapshot may state only when it says which moment it measured.
|
| The reading is taken twice on the same tree — once as this checkout ships it, where it has to
| answer with nothing, and once with the break in it, where it has to come back carrying that
| break's own marker rather than merely something. Read through the guard's own call —
| `HandoffState::stale()` — rather than a second implementation of it kept here, and carried in a
| file of its own so `paratest`, which spreads work by file, hands this read to a worker of its own.
|
| @guards-index test
|
*/

beforeEach(function (): void {
    // Every break is undone before the next one, so no mutation is ever read through the one
    // before it: two breaks in one tree is the thing this harness exists not to do.
    MutationHarness::restore();
});

foreach (MutationHarness::mutations('tests/Unit/Support/HandoffStateTest.php') as $mutation) {
    it(sprintf('turns %s red when this checkout is broken under it', $mutation['guard']), function () use ($mutation): void {
        $root = MutationHarness::tree();
        $read = $mutation['read'];

        expect(implode(PHP_EOL, $read($root)))->toBe('');

        $marker = ($mutation['plant'])($root);

        expect($marker)->not->toBe('')
            ->and(implode(PHP_EOL, $read($root)))->toContain($marker);
    });
}
