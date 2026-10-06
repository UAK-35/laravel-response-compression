<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MutationHarness;

/*
|--------------------------------------------------------------------------
| The branch rail, taken out of the program that holds it
|--------------------------------------------------------------------------
|
| A rail in `bin/release.php` is neither a tree to break nor a reading to rewrite: its answer is an
| exit code, and the thing that catches a rail which stopped refusing is the test that asserts on
| that rail. So the condition comes out of the program — `if ($current !== $branch)` becomes the
| one that cannot hold — the rewritten releaser is handed to `tests/Unit/Release/RailsTest.php`
| through `RC_RELEASE_FIXTURE_SCRIPT`, and the run has to go red carrying the rail's own sentence.
| The rail itself is the marker, exactly as a mutation's is: a run that went red for any other
| reason is not evidence that the rail was being held.
|
| The control for this direction is a file of its own, in `Controls/`, so the two runs can be on
| two workers rather than one waiting on the other: the control is the same child process and the
| same variable with the program copied verbatim, and without it a red run could be the variable or
| the child process rather than the rail taken out of the program.
|
| @guards-index test
|
*/

foreach (MutationHarness::programs('tests/Unit/Release/RailsTest.php') as $program) {
    it(sprintf('turns %s red when the program stops refusing', $program['guard']), function () use ($program): void {
        $disarmed = MutationHarness::disarmed($program);

        // Red, and for the right reason: the rail's own sentence is what the run has to reach, so
        // a run that could not load the program at all is told apart from a rail that stopped
        // refusing — a parse error is not an unarmed program, and `FAILED` is the run having
        // reached an assertion rather than a fatal before one.
        expect($disarmed['exit'])->not->toBe(0)
            ->and($disarmed['output'])->not->toContain('PHP Parse error')
            ->and($disarmed['output'])->not->toContain('Error in bootstrap script')
            ->and($disarmed['output'])->toContain('FAILED')
            ->and($disarmed['output'])->toContain($program['rail']);
    });
}
