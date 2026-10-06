<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MutationHarness;

/*
|--------------------------------------------------------------------------
| The control for the inversions: the overlay, copied verbatim
|--------------------------------------------------------------------------
|
| The holds live one file per guard at the top level of this directory, and this is not one of
| them: it is the run that makes each of their rewrites the only difference between two answers.
| A detector rewritten in an overlay has to turn the guard's own tests red — and the same overlay,
| the same bootstrap and the same child process with every reading copied in verbatim have to run
| them green. If a red run could be any of those rather than the rewrite, it would be red here
| too, which is what makes the rewrite the thing that was being measured.
|
| One run rather than one per inversion, because the claim is about the overlay mechanism rather
| than about a reading: if a verbatim overlay is transparent to every guard at once, it is
| transparent to each of them. It is a file of its own — not a test in the spine or beside a hold
| — because it is one of the slowest runs in the suite, and a file is what `paratest` spreads work
| by: in the spine it would hold the whole suite behind it while the guards waited.
|
| @guards-index test
|
*/

it('runs every guard\'s tests green under an overlay that copies every reading verbatim', function (): void {
    $control = MutationHarness::unblinded(MutationHarness::inversions());

    expect($control['exit'])->toBe(0, $control['output']);
});
