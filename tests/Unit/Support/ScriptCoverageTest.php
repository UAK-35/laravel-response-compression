<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ScriptCoverage;

/*
|--------------------------------------------------------------------------
| What the spawned scripts covered
|--------------------------------------------------------------------------
|
| The two scripts in `bin/` are only ever reached as child processes, so the floor cannot see
| them: the release rails plant a repository, copy the script into it and run it. Three things
| about reading that back are worth pinning, and every one of them is a way to report progress
| that is not there.
|
| The first is the path. A child reports the file it executed — in a temporary directory, named
| after the fixture — so a copy has to be mapped onto the repository's file. The rails plant a
| three-line `exit(1)` stub at `bin/checks.php` whenever they need a gate that fails on cue; read
| by name, that stub is the 1,003-line gate, and the gate's own lines look covered by it.
|
| The second is the shape. `pcov\collect()` answers with a negative count for a line that never
| ran, so a merge that keeps every entry reports a file as read because it was parsed.
|
*/

/**
 * A planted tree: a copy of the release script, and the stub the rails plant where a gate that
 * fails on cue is needed. The copy is byte for byte the repository's file; the stub is not.
 */
function scriptCoverageFixture(): string
{
    $root = sys_get_temp_dir().'/rc-script-coverage-'.bin2hex(random_bytes(6));

    mkdir($root.'/bin', 0o777, true);
    file_put_contents($root.'/bin/release.php', (string) file_get_contents(packageRoot().'/bin/release.php'));
    file_put_contents($root.'/bin/checks.php', "<?php\n\nexit(1);\n");

    return $root;
}

/**
 * Take the planted tree away again, so a failing test does not leave temporary directories behind.
 */
function scriptCoverageCleanup(string $root): void
{
    foreach (glob($root.'/*/*') ?: [] as $file) {
        unlink($file);
    }

    foreach (glob($root.'/*') ?: [] as $directory) {
        rmdir($directory);
    }

    rmdir($root);
}

it('maps a copy of a script onto the file it was copied from, and a stub onto nothing', function (): void {
    $fixture = scriptCoverageFixture();

    // The copy is the repository's file: same name, same bytes.
    $release = $fixture.'/bin/release.php';

    expect(ScriptCoverage::map($release, hash_file('sha256', $release), packageRoot()))
        ->toBe(str_replace('\\', '/', packageRoot().'/bin/release.php'));

    // The stub is not, and this is the assertion the guard exists for: a file with the gate's name
    // and none of its lines. Read by name it would hand the gate 100.0% coverage for a script that
    // exits on line three.
    $stub = $fixture.'/bin/checks.php';

    expect(ScriptCoverage::map($stub, hash_file('sha256', $stub), packageRoot()))->toBeNull()

        // A file that is no longer there is evidence of nothing, which is the shape a missing hash
        // and a name nothing matches both have to read as.
        ->and(ScriptCoverage::map($release, null, packageRoot()))->toBeNull()
        ->and(ScriptCoverage::map($fixture.'/bin/something-else.php', 'a hash', packageRoot()))->toBeNull();

    scriptCoverageCleanup($fixture);
});

it('merges what each process wrote, and sets aside a file that is not the repository’s', function (): void {
    $fixture = scriptCoverageFixture();
    $collected = $fixture.'/collected';
    mkdir($collected);

    // Two processes over the same copy, one of them reaching a line the other did not, plus the
    // signals pcov sends for a line that could not run — and one file that is not the repository's
    // file at all.
    $release = $fixture.'/bin/release.php';
    $stub = $fixture.'/bin/checks.php';

    file_put_contents($collected.'/one.cov', serialize([
        'hashes' => [
            $release => hash_file('sha256', $release),
            $stub => hash_file('sha256', $stub),
        ],
        'lines' => [
            $release => [125 => 1, 131 => 1, 150 => -1, 151 => -2],
            $stub => [3 => 1],
        ],
    ]));
    file_put_contents($collected.'/two.cov', serialize([
        'hashes' => [$release => hash_file('sha256', $release)],
        'lines' => [$release => [125 => 1, 132 => 1]],
    ]));
    file_put_contents($collected.'/torn.cov', 'a half-written file');

    $read = ScriptCoverage::read($collected, packageRoot());
    $release = str_replace('\\', '/', packageRoot().'/bin/release.php');

    // A count is not what is being added up here, only whether a line ran: the same line covered by
    // two processes is one covered line, and neither of them is a fraction of one.
    expect($read['lines'][$release])->toBe([125 => 2, 131 => 1, 132 => 1])
        ->and($read['processes'])->toBe(2)
        ->and($read['ignored'])->toBe([str_replace('\\', '/', $fixture.'/bin/checks.php')]);

    // Nothing collected is not the same answer as nothing found: a caller that checked only the
    // lines would take an empty directory for a merge that worked.
    expect(ScriptCoverage::read($fixture.'/nowhere', packageRoot()))
        ->toBe(['lines' => [], 'processes' => 0, 'ignored' => []]);

    scriptCoverageCleanup($fixture);
});
