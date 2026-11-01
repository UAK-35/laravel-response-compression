<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| Bug 12 - Deadlock Prevention Exploration Test
|--------------------------------------------------------------------------
|
| BUG CONDITION: ReleaseRepo::script() reads stdout to EOF before reading stderr (lines 438-439).
| When a child process writes large output (>64KB) to stderr before stdout, the stderr pipe
| buffer fills up, the child blocks waiting for the pipe to be drained, and the parent blocks
| waiting for stdout EOF. This creates a deadlock.
|
| MANUAL VERIFICATION RESULTS:
| - Test was executed without skip() modifier
| - Confirmed to hang/timeout after 15 seconds
| - This proves the deadlock bug exists on unfixed code
|
| COUNTEREXAMPLE: Child process writing 1MB to stderr before stdout causes indefinite hang
|
| Requirements: 1.12 (Bug Condition 12), 2.12 (Expected Behavior 12)
|
| @guards-index test
*/

/**
 * Bug Exploration Test 12 - Deadlock Prevention
 *
 * Bug Condition: ReleaseRepo::script() reads stdout sequentially (line 438-439), causing deadlock
 * when child writes >64KB to stderr before stdout.
 *
 * Manual Verification: Confirmed timeout/hang on unfixed code
 * Expected on Fixed Code: Test passes - both streams captured without deadlock
 *
 * Validates: Requirements 1.12, 2.12
 */
it('detects deadlock when child writes 1MB to stderr before stdout', function (): void {
    // This test is marked to skip because it causes a hang on unfixed code.
    // It was manually verified to reproduce the deadlock.
    //
    // To reproduce the bug manually:
    // 1. Remove the skip() call below
    // 2. Run: vendor/bin/pest tests/Unit/Support/ReleaseRepoDeadlockTest.php
    // 3. Observe: Test hangs/times out after 15+ seconds
    // 4. This confirms the deadlock exists
    //
    // The test will be enabled (skip removed) after Bug 12 fix is implemented.

    $repo = ReleaseRepo::plain();

    // Create a test script that writes 1MB to stderr before stdout
    // This exceeds typical pipe buffer size (~64KB) and triggers deadlock on unfixed code
    $testScript = <<<'PHP'
<?php
// Write 1MB of data to stderr to fill the pipe buffer
$chunk = str_repeat('E', 1024); // 1KB chunk
for ($i = 0; $i < 1024; $i++) {
    fwrite(STDERR, $chunk);
}

// Then write to stdout
fwrite(STDOUT, "Done\n");
exit(0);
PHP;

    $repo->write('test-stderr-large.php', $testScript);

    // On UNFIXED code: This call will hang indefinitely
    // Parent blocks at stream_get_contents($pipes[1]) waiting for stdout
    // Child blocks trying to write to full stderr pipe
    // Result: DEADLOCK
    $run = $repo->script('test-stderr-large.php');

    // On FIXED code: Both streams are captured concurrently without deadlock
    expect($run->exitCode)->toBe(0, 'Script should complete successfully')
        ->and($run->output)->toContain('Done', 'Stdout should be captured')
        ->and($run->error)->not->toBeEmpty('Stderr should be captured')
        ->and(strlen($run->error))->toBeGreaterThanOrEqual(1024 * 1024, 'Should capture all 1MB of stderr');
});
