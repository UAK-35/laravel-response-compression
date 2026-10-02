<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;
use Uak35\ResponseCompression\Tests\Support\ReleaseRun;

/*
|--------------------------------------------------------------------------
| The push, and the run it started
|--------------------------------------------------------------------------
|
| `bin/push.php` answers the half of a push that git does not: whether the run the push started
| passed. It is a program about two things — a real remote, and a reading of GitHub — and each is
| stood in for here in the way the thing itself allows.
|
| The remote is a real one. `ReleaseRepo` plants a bare repository beside the fixture and points
| `origin` at it, so the push is a real `git push` to a real remote, and the commit it carries is
| the one the program then asks about. What a fake git would test is that the script calls the fake
| the way the fake expects; what is worth testing is that the branch left and the right commit was
| named.
|
| The reading is a command, because that is what the program treats it as: `--gh` names whatever
| answers `gh run list`, and the test's stand-in is a PHP file behind this interpreter. It answers
| from a list of planted responses, one per call, so a run that is `in_progress` and then
| `completed` is arranged rather than waited for — the polling is exercised without the clock.
|
| @guards-index test
|
*/

it('pushes the branch and reads the run to `success`', function (): void {
    $repo = pushRepo([[pushRun('completed', 'success')]]);

    $run = push($repo);

    expect($run->exitCode)->toBe(0)
        ->and($run->said('Pushed main to origin'))->toBeTrue()
        ->and($run->said('The run concluded `success`'))->toBeTrue()
        ->and($run->said('https://github.com/fixture/actions/runs/1'))->toBeTrue();

    // The reader is aimed at the commit that was pushed rather than at whatever `gh` would guess,
    // which is what makes the verdict a fact about this push.
    $asked = $repo->read('gh-argv.json');

    expect($asked)->toContain('--commit')
        ->and($asked)->toContain(trim($repo->git('rev-parse', 'main')));
});

it('refuses a run that concludes `failure`', function (): void {
    $repo = pushRepo([[pushRun('completed', 'failure')]]);

    $run = push($repo);

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('The run concluded `failure`'))->toBeTrue()
        ->and($run->refused('https://github.com/fixture/actions/runs/1'))->toBeTrue();
});

it('waits for a run that is still going, and reads the end of it', function (): void {
    $repo = pushRepo([
        [pushRun('in_progress')],
        [pushRun('in_progress')],
        [pushRun('completed', 'success')],
    ]);

    $run = push($repo);

    expect($run->exitCode)->toBe(0)
        ->and($run->said('The run concluded `success`'))->toBeTrue()
        // Three readings of one run: a program that took the first answer would have stopped on
        // `in_progress`, and one that never asked again would have stopped there too.
        ->and((int) $repo->read('gh-calls'))->toBe(3);
});

it('refuses a push that started no run at all', function (): void {
    $repo = pushRepo([[]]);

    $run = push($repo, '--grace=1');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('No run was started'))->toBeTrue()
        // And why: the refusal says which refs the workflow gates, so a bare branch push is read
        // as a branch nothing builds rather than as a GitHub that did not answer.
        ->and($run->refused('gates `main`, `development` and `v*` tags'))->toBeTrue();
});

it('pushes without reading when told --no-watch', function (): void {
    $repo = pushRepo([[pushRun('completed', 'failure')]]);

    $run = push($repo, '--no-watch');

    expect($run->exitCode)->toBe(0)
        ->and($run->said('not read (--no-watch)'))->toBeTrue()
        // The reader was never asked: a push that said it would not read the run must not have
        // read it, or the option is a promise about the output rather than about the work.
        ->and($repo->exists('gh-calls'))->toBeFalse();
});

it('plans the push and reads nothing under --dry-run', function (): void {
    $repo = pushRepo([[pushRun('completed', 'success')]]);
    $before = $repo->git('ls-remote', 'origin', 'refs/heads/main');

    $run = push($repo, '--dry-run');

    $after = $repo->git('ls-remote', 'origin', 'refs/heads/main');

    expect($run->exitCode)->toBe(0)
        ->and($run->said('Would push main to origin'))->toBeTrue()
        ->and($run->said('Nothing was pushed and nothing was read'))->toBeTrue()
        ->and($after)->toBe($before)
        ->and($repo->exists('gh-calls'))->toBeFalse();
});

it('reads the repository out of a GitHub remote and aims the reader at it', function (): void {
    $repo = pushRepo([[pushRun('completed', 'success')]]);

    $repo->git('remote', 'set-url', 'origin', 'https://github.com/UAK-35/laravel-response-compression.git');

    $run = push($repo, '--dry-run');

    expect($run->exitCode)->toBe(0)
        ->and($run->said('Repository: UAK-35/laravel-response-compression'))->toBeTrue();
});

it('refuses a HEAD that is not on a branch, and a branch that is not here', function (): void {
    $repo = pushRepo([[pushRun('completed', 'success')]]);

    $repo->git('checkout', '--detach');

    expect(push($repo)->refused('HEAD is not on a branch'))->toBeTrue();

    $repo->git('checkout', 'main');

    // And the same command told which branch to push: a name that is not a branch is a refusal
    // rather than a push of whatever the name resolves to.
    expect(push($repo, '--branch=nope')->exitCode)->toBe(1)
        ->and(push($repo, '--branch=nope')->refused('nope is not a branch'))->toBeTrue();
});

it('refuses when the reader itself cannot be asked', function (): void {
    $repo = pushRepo([[pushRun('completed', 'success')]]);

    // A stand-in that is not there: the run cannot be read, and an unread run is not a pass.
    $run = $repo->script(
        'bin/push.php',
        '--remote=origin',
        '--gh='.PHP_BINARY.' '.$repo->path('no-such-reader.php'),
        '--interval=0',
        '--timeout=10',
        '--grace=5',
    );

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('The run could not be read'))->toBeTrue();
});

it('refuses when the run does not finish in time', function (): void {
    $repo = pushRepo([
        [pushRun('in_progress')],
        [pushRun('in_progress')],
        [pushRun('in_progress')],
    ]);

    $run = push($repo, '--timeout=1', '--interval=1');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('had not finished after 1 seconds'))->toBeTrue()
        ->and($run->said('Run PHP Composer'))->toBeTrue();
});

it('refuses an option it does not have', function (): void {
    $repo = pushRepo([[pushRun('completed', 'success')]]);

    $run = push($repo, '--nonsense');

    expect($run->exitCode)->toBe(2)
        ->and($run->refused('is not an option'))->toBeTrue();
});

it('prints its usage and stops clean when asked for help', function (): void {
    $repo = pushRepo([[pushRun('completed', 'success')]]);

    // `--help` is the one option that never pushes: it prints the options and exits 0, which is what
    // makes it safe to run wherever the program is, with nothing to push to.
    $run = $repo->script('bin/push.php', '--help');

    expect($run->exitCode)->toBe(0)
        ->and($run->error)->toContain('bin/push.php — push a branch')
        ->and($run->error)->toContain('--no-watch');
});

it('refuses a value that is not a whole number of seconds', function (): void {
    $repo = pushRepo([[pushRun('completed', 'success')]]);

    // A mistyped duration is a refusal rather than a silent cast to zero, which would make the
    // timeout happen at once and read as a build that never waited.
    $run = push($repo, '--timeout=soon');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('--timeout takes a whole number of seconds'))->toBeTrue();
});

it('refuses an empty reader command rather than guessing one', function (): void {
    $repo = pushRepo([[pushRun('completed', 'success')]]);

    $run = push($repo, '--gh=');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('--gh names the command that reads the run'))->toBeTrue();
});

it('refuses a remote that names no URL', function (): void {
    $repo = pushRepo([[pushRun('completed', 'success')]]);

    // A remote whose URL is blank: `git remote get-url` answers with whitespace, so once trimmed
    // there is no URL to push to. It is a refusal made before any attempt to push, rather than a
    // push of whatever the blank name happens to resolve to. (Blank rather than removed because
    // `bin/push.php` folds a command's stderr into its output: a removed remote prints an error
    // line, which is not the empty answer this branch is about.)
    $repo->git('config', 'remote.origin.url', ' ');

    $run = push($repo);

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('origin is not a remote here'))->toBeTrue();
});

it('refuses when the push itself fails, before reading anything', function (): void {
    $repo = pushRepo([[pushRun('completed', 'success')]]);

    // A URL that is not a repository: the remote is named and resolves to something, so the program
    // goes on to push, and the push is what fails. The run must not be read, because a commit that
    // never left has nothing on the remote to ask about.
    $repo->git('remote', 'set-url', 'origin', $repo->path('no-such-repository'));

    $run = push($repo);

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('failed, so there is no run to read'))->toBeTrue()
        ->and($repo->exists('gh-calls'))->toBeFalse();
});

it('aims the reader at the repository a GitHub remote names', function (): void {
    $repo = pushRepo([[pushRun('completed', 'success')]]);

    // A bare repository planted at a path that reads as a GitHub slug and reached through a
    // `file://` URL: the push is real, and the URL the program reads names a repository rather than
    // a directory. That is the branch that adds `--repo` to the reader, which a remote that is a
    // plain path never reaches.
    $slug = 'github.com/UAK-35/laravel-response-compression.git';
    $repo->git('init', '--bare', '--quiet', $slug);
    $repo->git('remote', 'set-url', 'origin', 'file:///'.str_replace('\\', '/', $repo->path($slug)));

    $run = push($repo);

    expect($run->exitCode)->toBe(0)
        ->and($run->said('The run concluded `success`'))->toBeTrue();

    // Decoded rather than matched against the raw file: the stub writes the arguments as JSON, and
    // JSON escapes the slash in the slug, which would make a substring match on the raw bytes fail
    // for a reason that has nothing to do with the branch this test is for.
    $asked = json_decode($repo->read('gh-argv.json'), true);

    expect($asked)->toContain('--repo')
        ->and($asked)->toContain('UAK-35/laravel-response-compression');
});

it('treats a reader that answered something other than runs as no runs', function (): void {
    $repo = pushRepo(['hello']);

    // The reader printed a string where a list of runs belongs. An answer that is not runs has not
    // said that a run exists, so it is read as no run rather than as a crash — the same verdict as
    // a reader that answered an empty list.
    $run = push($repo, '--grace=1');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('No run was started'))->toBeTrue();
});

/**
 * A fixture with the push program in it, a stand-in for `gh`, and a real remote.
 *
 * The stand-in and its answers are written and committed before `withRemote()` pushes, because
 * that is the one difference between this fixture and every other release test: the remote has to
 * be holding the commit the program will name.
 *
 * @param  list<list<array<string, mixed>>>  $responses
 */
function pushRepo(array $responses): ReleaseRepo
{
    $repo = ReleaseRepo::make();

    $repo->write('bin/push.php', (string) file_get_contents(ReleaseRepo::package().'/bin/push.php'));
    $repo->write('gh-stub.php', pushStub());
    $repo->write('gh-responses.json', json_encode($responses));
    $repo->commit('test: the push program and a stand-in for gh');

    return $repo->withRemote();
}

/**
 * The push program, in the fixture, aimed at the fixture's remote and its stand-in reader.
 *
 * The intervals are zero and the timeout is short: the polling is arranged by the planted answers
 * rather than by the clock, and a test that waited out a real interval would be slower and no
 * more true. Anything after these is an option the test is asking about.
 */
function push(ReleaseRepo $repo, string ...$arguments): ReleaseRun
{
    return $repo->script(
        'bin/push.php',
        '--remote=origin',
        '--gh='.PHP_BINARY.' '.$repo->path('gh-stub.php'),
        '--interval=0',
        '--timeout=10',
        '--grace=5',
        ...$arguments,
    );
}

/** One run as `gh run list --json` answers it. */
function pushRun(string $status, string $conclusion = '', int $id = 1): array
{
    return [
        'databaseId' => $id,
        'status' => $status,
        'conclusion' => $conclusion,
        'url' => 'https://github.com/fixture/actions/runs/'.$id,
        'workflowName' => 'PHP Composer',
    ];
}

/**
 * Stands in for `gh run list`: the next planted answer, and a note of what it was asked.
 *
 * The answer is advanced per call rather than by time, so the sequence a real run goes through —
 * `in_progress` and then a conclusion — is a list the test writes. What it was asked is written
 * beside it, because "which commit" is half of what the reader is for.
 */
function pushStub(): string
{
    return <<<'PHP'
    <?php

    $root = __DIR__;
    $calls = is_file($root.'/gh-calls') ? (int) file_get_contents($root.'/gh-calls') : 0;
    $calls++;

    file_put_contents($root.'/gh-calls', (string) $calls);
    file_put_contents($root.'/gh-argv.json', json_encode(array_slice($argv, 1)));

    $answers = json_decode((string) file_get_contents($root.'/gh-responses.json'), true);
    $index = min($calls - 1, count($answers) - 1);

    echo json_encode($answers[$index]);
    PHP;
}
