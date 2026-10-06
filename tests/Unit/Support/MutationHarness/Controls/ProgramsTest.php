<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MutationHarness;

/*
|--------------------------------------------------------------------------
| The control for the programs: the program, copied verbatim
|--------------------------------------------------------------------------
|
| The rail's hold is `tests/Unit/Support/MutationHarness/RailsTest.php`, and this is the run that
| makes the rail taken out of the program the only difference between the two answers: the same
| child process and the same `RC_RELEASE_FIXTURE_SCRIPT` variable, with the program left as this
| checkout ships it. Without it a red hold could be the variable, the bootstrap or the child
| process rather than the rewrite. One run per program, because the variable names one script per
| process.
|
| A file of its own for the same reason the overlay's control has one: it is one of the slowest
| runs in the suite, and running it beside the hold rather than in front of it is what the split
| is for.
|
| @guards-index test
|
*/

foreach (MutationHarness::programs() as $program) {
    it(sprintf('runs the test that holds %s green under the program copied verbatim', $program['guard']), function () use ($program): void {
        $control = MutationHarness::verbatim($program);

        expect($control['exit'])->toBe(0, $control['output']);
    });
}
