<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Workers;

/*
|--------------------------------------------------------------------------
| The pool's size
|--------------------------------------------------------------------------
|
| `--parallel` is paratest, and a pool of one worker per core is right on a machine that has nothing
| else to do. This one does: a sixteen-worker pool was reported as files it could not start, each of
| those files passing when it was asked alone — a pool bigger than the machine, not a pile of
| findings in the code. A worker is also not one process: a test that starts a child, and most of
| this suite's do, makes every worker two or three.
|
| So the number is read rather than defaulted: half the cores, which is a pool of roughly the cores
| in processes with the other half of the machine left to the gate, to those children and to
| whatever else is running. The arithmetic is held here; the core count itself is read against the
| host this is running on, because that is the half a fixture cannot answer.
|
| @guards-index test
|
*/

it('sizes the pool at half the machine', function (int $cores, int $workers): void {
    expect(Workers::bound($cores))->toBe($workers);
})->with([
    // The machine this was measured on, and the number the full suite was timed against: 214 seconds
    // on a pool of fifteen, 242 on a pool of eight.
    'sixteen cores hold eight workers' => [16, 8],
    'thirty-two cores hold sixteen' => [32, 16],
    // Odd cores round down rather than up, because the half being kept free is the point.
    'an odd count rounds the pool down' => [7, 3],
    // The floor: a one-core machine — and a platform that named no core count, which answers with
    // one for the same reason — runs a suite rather than a pool of zero.
    'one core is one worker' => [1, 1],
    'two cores still hold a worker' => [2, 1],
]);

it('puts the size on the option, which is the form pest hands to paratest intact', function (): void {
    // Both programs that start a pool — the gate and the coverage floor — hand pest this, so the
    // shape is asserted once: `--parallel` and a single option carrying the number. A value written
    // after the option is the form that stops the run ("The `--processes` option requires a
    // value"), which is why the same fact is asserted from the gate's own argv beside this file.
    $arguments = Workers::arguments();

    expect($arguments)->toHaveCount(2)
        ->and($arguments[0])->toBe('--parallel')
        ->and($arguments[1])->toBe('--processes='.Workers::count());
});

it('reads the cores of the machine it is running on', function (): void {
    // Against this host rather than a fixture: the reads are three platform spellings and a fallback,
    // and which one answers is a fact about the machine. What it proves is that one of them does, and
    // that the pool the answer produces is inside the two bounds rather than a third number.
    $cores = Workers::cores();
    $workers = Workers::count();

    expect($cores)->toBeGreaterThan(0)
        ->and($workers)->toBeGreaterThan(0)
        ->and($workers)->toBeLessThanOrEqual(intdiv($cores, 2) === 0 ? 1 : intdiv($cores, 2));
});
