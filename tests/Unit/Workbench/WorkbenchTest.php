<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Processes;
use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;
use Uak35\ResponseCompression\Tests\Support\ReleaseRun;

/*
|--------------------------------------------------------------------------
| A rail for bin/workbench.sh
|--------------------------------------------------------------------------
|
| `bin/workbench.sh` builds a throwaway copy of this checkout with a vendor of its own, and the only
| thing that makes that copy worth having is what it refuses: the files a commit would carry, a
| clone with no `origin`, and — twice — a tree that would make a reading belong to somebody else.
| Those refusals were exercised by hand when the script arrived, which is evidence and not cover: a
| commit that broke one of them passed the hook, because `TestMap` names the `.php` files under
| `src/` and `bin/` and the syntax and style checks skip a shell script in one line each.
|
| What is measured here is the half of the program that costs nothing to ask: the usage it prints,
| and every way it declines to build a copy. The install is deliberately not reached — it is
| minutes and the network, and a rail a commit waits behind is the wrong place for that. The link
| half is not planted either: a link is a fact about the filesystem a test would have to make, and
| on Windows making one is a privilege the runner may not have, so that refusal stays the one the
| changelog records from a hand-measured junction.
|
| Every refusal is asked of a tree the script was given rather than of this checkout, because the
| script derives its root from its own location: the copy under `bin/` in a throwaway tree makes
| the fixture the subject, and the assertions are then about the program rather than about where
| it happens to be running from.
|
| @guards-index test
|
*/

it('says how it is called, and answers the request for it with 0', function (): void {
    $run = workbench(workbenchTree(), '--help');

    expect($run->exitCode)->toBe(0)
        ->and($run->said('usage: bash bin/workbench.sh'))->toBeTrue()
        ->and($run->said('--into DIR'))->toBeTrue()
        // The one argument that is not the script's: what comes after it belongs to Composer, and
        // the usage is where a reader learns that rather than in the error below.
        ->and($run->said('composer install'))->toBeTrue()
        // A help text is not a finding: the refusal stream stays empty, or an exit code is the only
        // thing that would tell a caller which of the two happened.
        ->and($run->error)->toBe('');
});

it('refuses an argument it does not take, and says where Composer arguments go', function (): void {
    $run = workbench(workbenchTree(), '--into-here');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('unknown argument: --into-here'))->toBeTrue()
        ->and($run->refused('composer arguments go after --'))->toBeTrue()
        // Nothing was built before the refusal, which is what makes it a refusal rather than a
        // failure met half way through a copy.
        ->and($run->said('installing into'))->toBeFalse();
});

it('refuses --into with no directory after it', function (): void {
    $run = workbench(workbenchTree(), '--into');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('--into needs a directory'))->toBeTrue()
        ->and($run->said('installing into'))->toBeFalse();
});

it('refuses a tree that is not a repository, rather than copying nothing', function (): void {
    // The first question the script asks, and the one that would otherwise be answered by a carried
    // set that is empty for the wrong reason: a directory that is not a repository has no files a
    // commit would carry, and a copy of none of them is a copy of nothing.
    $run = workbench(workbenchTree());

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('is not a git repository'))->toBeTrue()
        ->and($run->refused('there is no checkout here to copy'))->toBeTrue()
        ->and($run->said('installing into'))->toBeFalse();
});

it('refuses a directory inside the checkout it is copying, and leaves nothing behind', function (): void {
    $repo = workbenchRepo();

    $run = workbench($repo, '--into', 'inside');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('is inside this checkout'))->toBeTrue()
        ->and($run->refused('a copy belongs outside the tree it copies'))->toBeTrue()
        // The directory it made to answer the question is gone again: a refusal that left an empty
        // directory in the tree is an edit made on the way to declining one.
        ->and($repo->exists('inside'))->toBeFalse()
        ->and($run->said('installing into'))->toBeFalse();
});

it('refuses a directory that already holds something, and leaves what is there', function (): void {
    $repo = workbenchRepo();
    $repo->write('taken/keep.txt', "this file is not the copy's\n");

    $run = workbench($repo, '--into', 'taken');

    expect($run->exitCode)->toBe(1)
        ->and($run->refused('already holds something'))->toBeTrue()
        ->and($run->refused('a copy is built into an empty directory'))->toBeTrue()
        // The file the question was asked about is still there, byte for byte: the check reads the
        // directory before anything is copied into it, and a check that cleared it first would be
        // the destructive answer to a refusal.
        ->and($repo->read('taken/keep.txt'))->toBe("this file is not the copy's\n");
});

/**
 * This package's workbench in a throwaway tree.
 *
 * `plain()` and not `make()`: the tree that is not a repository is the first refusal, and it is the
 * one a repository fixture cannot ask. The two later refusals need history around them, which is
 * what `workbenchRepo()` is for.
 */
function workbenchTree(): ReleaseRepo
{
    $tree = ReleaseRepo::plain();
    $tree->write('bin/workbench.sh', (string) file_get_contents(packageRoot().'/bin/workbench.sh'));

    return $tree;
}

/**
 * The same script in a repository, which is the state every refusal after the first is asked in.
 *
 * The script is written rather than committed: a path that is untracked and not ignored is a path a
 * commit would carry, so the carried set the script reads is the fixture's own either way.
 */
function workbenchRepo(): ReleaseRepo
{
    $repo = ReleaseRepo::make();
    $repo->write('bin/workbench.sh', (string) file_get_contents(packageRoot().'/bin/workbench.sh'));

    return $repo;
}

/**
 * The script, run the way a person runs it — `bash bin/workbench.sh`, from the tree it is in.
 *
 * The script is named **relative** to the fixture and `--into` is relative too, which is not a
 * shortcut but the only spelling that works for every shell this can reach. A native PHP process on
 * Windows resolves `bash` to the one on `PATH`, and here that is WSL's — which answers a script path
 * starting `C:/` with "No such file or directory" rather than opening it, and a `--into` in that
 * spelling the same way. A relative path is resolved against the working directory the child is
 * given, so whichever shell answers, the two halves of the script's own comparison — `$root` from
 * `$0` and `$dest` from `cd … && pwd -P` — are written by the same shell and match. `HandoffState`
 * names `handoff/state.sh` the same way for the same reason.
 *
 * Through `Processes` rather than `proc_open()` directly, because a pooled run can be refused a
 * start on a loaded machine and a loss here would read as a failure of the program. stdin is a pipe
 * that is closed before the read, so the child can never wait on a console: the script asks no
 * question, and a test that left stdin open would hang rather than fail if it ever did.
 */
function workbench(ReleaseRepo $tree, string ...$arguments): ReleaseRun
{
    // `array_values`, because a variadic arrives as an array rather than as a list: a call that
    // named one of these arguments would give it a string key, and `Processes::start()` takes the
    // command as a list of strings.
    $command = array_values(['bash', 'bin/workbench.sh', ...$arguments]);

    [$process, $pipes] = Processes::start(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $tree->path,
    );

    fclose($pipes[0]);
    $output = (string) stream_get_contents($pipes[1]);
    $error = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return new ReleaseRun(proc_close($process), $output, $error, implode(' ', $command));
}
