<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Processes;

/*
|--------------------------------------------------------------------------
| A refused start, and who gets told about it
|--------------------------------------------------------------------------
|
| Under a pool this machine sporadically declines `CreateProcess` for a child a worker asks for —
| `proc_open(): CreateProcess failed: The parameter is incorrect` — and the child is there on the
| next attempt. A retry is the honest answer to that, because a refused start created nothing and so
| repeated no effect. What was not answered is who hears about it: `@` stops PHP from printing the
| warning and nothing more, so the message still reached the runner. Pest records it against the
| test that made the start, `phpunit.xml.dist` carries `failOnWarning="true"`, and the suite went red
| on a machine that had simply refused a process — 42 tests carrying that warning, every one of them
| with the child it asked for having started on the second attempt and the work having proceeded.
|
| So the refusal is taken by a handler `Processes` installs for the one call and puts back on the way
| out, and the message it keeps is what `start()` throws with when the patience is gone. Both halves
| are held here. That a refusal is not recorded against the test is asserted by the run itself: the
| warning is what fails this file when it escapes, so the assertion is the second `expect` never
| being reached. And that the handler goes back is asserted directly, because one left installed
| would swallow every warning after it in a worker — which is the failure `failOnWarning` exists to
| catch, arriving by a different door.
|
| The first test costs the suite the patience: a start that is refused every time is waited out
| before it is called a refusal, and that waiting is the thing being asked about. The second costs
| one `git --version`.
|
| @guards-index test
*/

it('waits a refused start out, reports it, and leaves no warning against the test', function (): void {
    $command = ['definitely-not-a-program-this-machine-has'];

    // The whole path in one call: `start()` retries through `attempt()` until the patience is gone
    // and then throws, with the reason beside the command it could not run. Both assertions are
    // about a refusal that was taken rather than recorded — and the run's own verdict is the third:
    // with the message left to the runner this file would come back as a warning against this test
    // instead of reaching either expectation below.
    expect(fn (): array => Processes::start($command, [1 => ['pipe', 'w']]))
        ->toThrow(RuntimeException::class, 'Could not start definitely-not-a-program-this-machine-has');

    // The reason is the warning's own sentence, which is what makes the throw a replacement for it
    // rather than a summary of nothing: a start refused without a message of its own would read
    // `no reason was given`, and that is a different fact about the machine.
    expect(Processes::reason())->toContain('proc_open()');
});

it('puts the handler it installed back, so nothing after it is swallowed', function (): void {
    // A sentinel stands where the runner's handler was, so what this asks is about the stack rather
    // than about the suite's config: `set_error_handler()` answers whatever was installed, and after
    // a start that answer has to be the sentinel again rather than the one `attempt()` put there.
    $sentinel = static fn (int $severity, string $message, string $file, int $line): bool => true;

    set_error_handler($sentinel);

    $installed = $sentinel;

    try {
        // A start that succeeds, which is the ordinary path: the handler is installed around the
        // call whether the child was refused or started.
        $started = Processes::attempt(['git', '--version'], [1 => ['pipe', 'w']]);

        expect($started)->not->toBeNull();

        [$process, $pipes] = $started;

        fclose($pipes[1]);
        proc_close($process);

        // Pushed and taken straight back off, so the answer is read without the stack moving: what
        // comes back is either the sentinel — this class cleaned up after itself — or a handler it
        // left behind, which is the failure.
        $installed = set_error_handler($sentinel);
        restore_error_handler();
    } finally {
        restore_error_handler();
    }

    expect($installed)->toBe($sentinel);
});
