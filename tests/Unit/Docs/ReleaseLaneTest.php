<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseLane;

/*
|--------------------------------------------------------------------------
| One lane, and the places it is written down
|--------------------------------------------------------------------------
|
| The branch this repository releases from is stated once, in `.release-lane`, and `bin/release.php`
| reads that file as the branch it releases from. Everything else states it again: the two runbooks,
| and the workflow's own literal trigger — which cannot read the file, because GitHub evaluates
| `on.push.branches` before any step runs, and which reads it in a step of its own to require that the
| list it gates is the lane.
|
| Three of those copies had drifted to `main`, which is the branch the release program *defaults* to
| when a repository declares no lane, so every one of them still read as a sensible command and every
| one of them stopped at the branch rail when it was run. Nothing noticed, because a runbook is not run
| by anything. So the lane is read out of the file and every other statement of it has to name the same
| branch: a `--branch=` a runbook mentions, a `git push` to this repository's own remote, and the ref
| list the workflow gates. A sentence that mentions `main` is left alone, which it has to be — the
| runbooks explain what the default is, and that explanation is the reason the lane is read at all.
|
| `ReleaseLane` holds the reading; this file holds the two questions a guard has to answer — is the
| tree clean, and is the reading still looking. The mutation harness plants the drift in a copy of this
| checkout and requires the reading to name it.
|
*/

/**
 * @guards-index guard the release-lane test
 *
 * a branch written where a release or a push runs that is not the one releases are cut from: the lane is read out of the release command in `.agents/toolchain.json`, and the runbooks and the workflow's own trigger comment have to name it — so all four drift to `main` together or none of them does, and a sentence that mentions the default is left alone because the runbooks have to explain it
 */
it('names the lane in every place this repository states it', function (): void {
    $root = str_replace('\\', '/', packageRoot());
    $lane = ReleaseLane::lane($root);
    $named = ReleaseLane::named((string) file_get_contents($root.'/RELEASING.md'));
    $gated = ReleaseLane::gated((string) file_get_contents($root.'/'.ReleaseLane::WORKFLOW));

    // The commands the reading did find are checked for substance before its clean answer is
    // believed: a walk that matched nothing agrees with every tree, and this one is asked of
    // documents that are mostly prose about a branch.
    expect($lane)->toBe('development')
        ->and($named)->not->toBeEmpty()
        ->and(array_column($named, 'branch'))->toContain($lane)
        ->and($gated['branches'])->toContain($lane)
        ->and(ReleaseLane::drift($root))->toBe([]);
});

it('tells a branch named in a command from one mentioned in a sentence', function (): void {
    $named = ReleaseLane::named(implode("\n", [
        'composer release -- --weigh --push --branch=main',
        'git push origin main --follow-tags',
        'a bare `git push` had nothing to do',
        'the releaser defaults to `main`, which is why the flag is passed',
        'git push --force-with-lease',
    ]));

    expect(array_column($named, 'branch'))->toBe(['main', 'main'])
        ->and(array_column($named, 'where'))->toBe(['--branch=', 'git push origin'])
        ->and(array_column($named, 'line'))->toBe([1, 2]);
});

it('reads the push trigger rather than the pull-request list under it', function (): void {
    $workflow = implode("\n", [
        'on:',
        '  push:',
        '    branches: [ "main", "development" ]',
        '    tags: [ "v*" ]',
        '  pull_request:',
        '    branches: [ "main" ]',
    ]);

    $gated = ReleaseLane::gated($workflow);

    expect($gated['branches'])->toBe(['main', 'development'])
        ->and($gated['line'])->toBe(3);
});

it('refuses a lane file that names no branch rather than agreeing with it', function (): void {
    $root = sys_get_temp_dir().'/rc-lane-'.bin2hex(random_bytes(6));
    mkdir($root, 0o777, true);
    file_put_contents($root.'/'.ReleaseLane::LANE, "  \n");

    expect(fn (): string => ReleaseLane::lane($root))
        ->toThrow(RuntimeException::class, 'names no branch');

    unlink($root.'/'.ReleaseLane::LANE);
    rmdir($root);
});

it('refuses a statement it cannot read rather than reporting agreement with it', function (): void {
    expect(fn (): string => ReleaseLane::lane('/rc-lane-that-is-not-here'))
        ->toThrow(RuntimeException::class, 'could not be read');
});
