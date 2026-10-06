<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\TestMap;

/*
|--------------------------------------------------------------------------
| The tests a commit runs
|--------------------------------------------------------------------------
|
| The pre-commit hook narrows the gate to the files a commit carries, and it used to narrow it to
| three things: syntax, style, and the two tests a document can stale. A commit that changed a
| program was therefore never run against the suite that covers it — `bin/release.php` is the
| longest file in the package and the one whose tests plant a real repository and run it, so a
| broken rail was committed green and found later.
|
| `tests/Support/TestMap.php` is the answer now, and what can go wrong with one is a different
| question from what can go wrong with a list beside the gate. A list goes stale — a source file
| that was renamed keeps its old row, and a source file that arrived has none — so this reads the
| map as a claim about the tree rather than trusting it: every `.php` file under `src/` and
| `bin/` has to have a row, every row has to name a file that is there, and every row has to name
| something for the tests to be pointed at.
|
| The narrowing is read the same way rather than restated here: a mapping that agreed with a copy
| of itself would be two rules that can disagree about which tests a commit runs, which is the
| failure the reading exists to prevent, one layer out. So the cases below are chosen for the two
| branches a rule has to get right — the file with its own row, and the change that is not a source
| file at all.
|
| @guards-index test
*/

it('names every file under src/ and bin/, and every row a file that is here', function (): void {
    $root = packageRoot();

    // The directory is the claim. A file that is not in the map is a file a commit can change
    // without anything covering it, and the guard's whole job is to refuse that rather than to
    // notice it after the fact — which is why the missing case is named before the other one.
    $missing = array_values(array_diff(TestMap::files($root), array_keys(TestMap::covers())));

    expect($missing)->toBe([], 'Every .php file under `src/` and `bin/` needs a row in TestMap::COVERS; these have none: '.implode(', ', $missing));

    // The other direction is a row naming a path that is not here, which is what a source file that
    // was renamed leaves behind. It is asked of every row rather than of the two directories,
    // because one row names a file outside them: the harness the holds are made of is not under
    // `src/` or `bin/`, and a row for it is a row like any other.
    $gone = [];

    foreach (array_keys(TestMap::covers()) as $source) {
        if (! file_exists($root.'/'.$source)) {
            $gone[] = $source;
        }
    }

    expect($gone)->toBe([], 'These rows name a file the package does not have: '.implode(', ', $gone));
});

it('names only tests that are on disk', function (): void {
    $root = packageRoot();
    $gone = [];

    foreach (TestMap::covers() as $source => $tests) {
        expect($tests)->not->toBe([], "The row for {$source} names no test, so a commit carrying it would run nothing.");

        foreach ($tests as $test) {
            if (! file_exists($root.'/'.$test)) {
                $gone[] = "{$source} -> {$test}";
            }
        }
    }

    expect($gone)->toBe([], 'These rows name a test the repository does not have: '.implode(', ', $gone));
});

it('reads the files it is a claim about, so an empty answer is not agreement', function (): void {
    $files = TestMap::files(packageRoot());

    // A walk that stopped matching would agree with a map whose rows were all wrong, so the
    // listing is shown to have substance before any count of it is believed.
    expect($files)->toContain('src/Support/Config.php')
        ->and($files)->toContain('bin/release.php')
        ->and($files)->not->toContain('src/Middleware/CompressResponse.php.txt');
});

it('reads the files a commit carries into the tests that cover them', function (): void {
    // A source file is its own row, and a row may name a directory: the release script's tests are
    // the whole of one, because that is the only truthful answer to which of them covers it.
    expect(TestMap::covering(['bin/push.php']))->toBe(['tests/Unit/Release/PushTest.php'])
        ->and(TestMap::covering(['bin/release.php']))->toBe(['tests/Unit/Release'])

        // A second path that the first one already covers is not a second run.
        ->and(TestMap::covering(['bin/release.php', 'tests/Unit/Release/RailsTest.php']))->toBe(['tests/Unit/Release'])

        // A changed test is run as itself; a fixture beside it that has a row is run by that row —
        // the harness is the class the holds are made of, so the holds it builds and the spine that
        // keeps them to the directory are the whole of what a change to it can have broken — and a
        // fixture with no row of its own is the suite, which is the only narrower true answer left.
        ->and(TestMap::covering(['tests/Unit/Gate/ToolTest.php']))->toBe(['tests/Unit/Gate/ToolTest.php'])
        ->and(TestMap::covering(['tests/Support/MutationHarness.php']))->toBe([
            'tests/Unit/Support/MutationHarness',
            'tests/Unit/Support/MutationHarnessTest.php',
        ])
        ->and(TestMap::covering(['tests/Support/ReleaseRepo.php']))->toBe(['tests/Unit'])

        // The documents are the gate's two old cases — kept in the same reading rather than beside
        // it, so the three cannot come to disagree about what a commit runs — and the lane a runbook
        // names, which is the one claim in them that no other test reads.
        ->and(TestMap::covering(['docs/guards.md']))->toBe([
            'tests/Unit/Config/ConfigClaimsTest.php',
            'tests/Unit/Docs/DocsLinksTest.php',
            'tests/Unit/Docs/ReleaseLaneTest.php',
        ])
        ->and(TestMap::covering(['config/response-compression.php']))->toBe(['tests/Unit/Config/ConfigClaimsTest.php'])

        // And a file that is none of those — a lock file, a workflow — is mapped to nothing rather
        // than to a test invented for it.
        ->and(TestMap::covering(['composer.json', 'phpunit.xml.dist']))->toBe([]);
});
