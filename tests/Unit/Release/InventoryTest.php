<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
 * `files.tsv` and `methods.tsv` are the fourth weighing signal, and the only one the release
 * writes: both go into the release commit, stamped with the tag being created, so the next release
 * can weigh against exactly what was shipped.
 *
 * These tests are about the stamp rather than about the diff. A file describing the release being
 * cut is evidence; a file regenerated at some other moment cannot tell "nothing changed" from "not
 * refreshed", and believing the first when the second is true is how a breaking change ships as a
 * patch — the one direction a versioning signal must never be wrong in.
 */

it('writes both inventory files into the release commit, stamped with the tag it creates', function (): void {
    $repo = ReleaseRepo::make();

    $run = $repo->release('--weigh', '--skip-ci', '--yes');

    expect($run->exitCode)->toBe(0, $run->describe())
        // Nothing was written down before the first release from here, so the plan says where the
        // two files come from instead of reporting a signal it does not have.
        ->and($run->plan('inventory'))->toBe('2 file(s), 1 method(s)  (nothing written down yet — this release writes it)');

    expect($repo->read('files.tsv'))->toContain('describes the tree at v0.1.0')
        ->and($repo->read('files.tsv'))->toContain("Thing.php\tsrc/Thing.php\tUak35\\ResponseCompression\\Thing")
        ->and($repo->read('methods.tsv'))->toContain("handle\tsrc/Thing.php\tUak35\\ResponseCompression\\Thing\t1");

    // Bookkeeping about the version, so it is committed with the version.
    $committed = $repo->git('show', '--stat', '--format=', 'HEAD');

    expect($committed)->toContain('files.tsv')
        ->and($committed)->toContain('methods.tsv');

    // And the next plan reads it back: fresh, and with nothing to report, which is the state a
    // signal that can only ever raise the bump has to be in to be worth the reading.
    $again = $repo->release('--weigh', '--skip-ci', '--dry-run');

    expect($again->plan('inventory'))->toBe('2 file(s), 1 method(s)  (fresh, weighed against v0.1.0)')
        ->and($again->said('nothing removed, renamed or added since v0.1.0'))->toBeTrue();
});

it('witnesses a method that changed shape since the tag the inventory describes', function (): void {
    $repo = ReleaseRepo::make();
    $repo->release('--weigh', '--skip-ci', '--yes');

    // A required argument that was not there before: the breaking kind a method name alone cannot
    // see, and the one the notes would have to remember to mention.
    $repo->write('src/Thing.php', str_replace(
        'public function handle(string $body, bool $flag = false): string',
        'public function handle(array $body, string $format, bool $flag = false): string',
        $repo->read('src/Thing.php'),
    ));
    $repo->commit('refactor: move the body into an argument');

    $run = $repo->release('--weigh', '--skip-ci', '--dry-run');

    expect($run->plan('inventory'))->toBe('2 file(s), 1 method(s)  (fresh, weighed against v0.1.0)')
        ->and($run->said('::handle() needs 2 required argument(s), was 1'))->toBeTrue();
});

it('writes the inventory for the tag the tree is built on, so the next release weighs it', function (): void {
    $repo = ReleaseRepo::make();

    // The rows describe the tree at v0.0.1 rather than whatever is on disk at the moment it is run,
    // because the stamp is a claim about a release: a record written at some other moment and
    // stamped as though it belonged to one is the state this signal exists to refuse.
    $run = $repo->release('--inventory');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->said('describing v0.0.1'))->toBeTrue()
        ->and($repo->read('files.tsv'))->toContain('describes the tree at v0.0.1')
        // Nothing was committed and no tag moved: it writes two files and stops, which is why it is
        // asked before the branch, dirty-tree and tag rails.
        ->and($repo->tags())->toBe(['v0.0.1'])
        ->and(trim($repo->git('log', '-1', '--format=%s')))->toBe('feat: the package');

    // Asked again, it says so rather than pretending to have written something.
    expect($repo->release('--inventory')->said('already described this tree'))->toBeTrue();

    // And the release that follows weighs a real record instead of starting blind, which is the
    // whole reason for writing it a release early.
    $weighed = $repo->release('--weigh', '--skip-ci', '--dry-run');

    expect($weighed->plan('inventory'))->toBe('2 file(s), 1 method(s)  (fresh, weighed against v0.0.1)');
});

it('names a move as a move, the way the surface signal does', function (): void {
    $repo = ReleaseRepo::make();
    $repo->release('--weigh', '--skip-ci', '--yes');

    // The two signals read one change from two directions — a path list and a name — and they have
    // to agree about it, or a reader is left to decide which of them to believe.
    $repo->moveClass('src/Thing.php', 'src/Moved.php');

    $run = $repo->release('--weigh', '--skip-ci', '--dry-run');

    expect($run->said('moved Uak35\ResponseCompression\Thing: src/Thing.php -> src/Moved.php'))->toBeTrue()
        ->and($run->plan('surface'))->toContain('moved between files')
        ->and($run->plan('inventory'))->toBe('2 file(s), 1 method(s)  (fresh, weighed against v0.1.0)');
});

it('does not weigh an inventory whose stamp names another release', function (): void {
    $repo = ReleaseRepo::make();
    $repo->release('--weigh', '--skip-ci', '--yes');

    // The pair was written together, so one stamp saying something else is a file edited on its
    // own — and a half-refreshed inventory cannot be told apart from one that describes a tree
    // that did not change, which is the one thing this signal must never guess at.
    $repo->write('methods.tsv', str_replace(
        'describes the tree at v0.1.0',
        'describes the tree at v0.0.1',
        $repo->read('methods.tsv'),
    ));
    $repo->commit('chore: restamp one of the two');

    $disagreed = $repo->release('--weigh', '--skip-ci', '--dry-run');

    expect($disagreed->plan('inventory'))->toBe('2 file(s), 1 method(s)  (stale: it describes v0.1.0 / v0.0.1 and this release is built on v0.1.0, so it is not weighed)')
        ->and($disagreed->said('not read'))->toBeTrue();

    // And both re-stamped: an inventory regenerated by hand, or in a commit of its own, which is
    // the case the stamp exists for. Reported and skipped rather than believed.
    $repo->write('files.tsv', str_replace(
        'describes the tree at v0.1.0',
        'describes the tree at v0.0.1',
        $repo->read('files.tsv'),
    ));
    $repo->commit('chore: restamp both');

    $stale = $repo->release('--weigh', '--skip-ci', '--dry-run');

    expect($stale->plan('inventory'))->toBe('2 file(s), 1 method(s)  (stale: it describes v0.0.1 and this release is built on v0.1.0, so it is not weighed)')
        // Not weighed at all: a stale inventory is the absence of a signal rather than a weak one,
        // because it cannot tell a tree that did not change from a file nobody refreshed.
        ->and($stale->said('not read'))->toBeTrue();
});
