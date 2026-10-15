<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MutationHarness;

/*
|--------------------------------------------------------------------------
| The machine-file shape, broken under itself
|--------------------------------------------------------------------------
|
| The break this file plants is a section taken out of `.agents/machine.local.json.example`: the
| committed shape a reader copies, with one of the sections `python .agents/render_local.py --init`
| opens a fresh clone's file with missing from it. It is the drift nobody sees — the machine file
| itself is gitignored, so the example is the only telling of the shape a commit carries, and a
| reader who copies it gets a machine file the program that writes it would not have produced.
|
| The reading is taken twice on the same tree — once as this checkout ships it, where it has to
| answer with nothing, and once with the section gone, where it has to come back carrying that
| section's name rather than merely something. A reading that answered with nothing both times is
| the failure this exists to tell apart from a guard that answered nothing because nothing was wrong.
|
| Read through the guard's own call — `MachineShape::stale()` — rather than a second implementation
| of it kept here, and carried in a file of its own so `paratest`, which spreads work by file, hands
| this read to a worker of its own rather than to whichever one picked up the file the rest are in.
|
| @guards-index test
|
*/

beforeEach(function (): void {
    // Every break is undone before the next one, so no mutation is ever read through the one
    // before it: two breaks in one tree is the thing this harness exists not to do.
    MutationHarness::restore();
});

foreach (MutationHarness::mutations('tests/Unit/Support/MachineShapeTest.php') as $mutation) {
    it(sprintf('turns %s red when this checkout is broken under it', $mutation['guard']), function () use ($mutation): void {
        $root = MutationHarness::tree();
        $read = $mutation['read'];

        expect(implode(PHP_EOL, $read($root)))->toBe('');

        $marker = ($mutation['plant'])($root);

        expect($marker)->not->toBe('')
            ->and(implode(PHP_EOL, $read($root)))->toContain($marker);
    });
}
