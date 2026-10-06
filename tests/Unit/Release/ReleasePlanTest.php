<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;

/*
|--------------------------------------------------------------------------
| The plan, and what a dry run writes
|--------------------------------------------------------------------------
|
| A dry run is the half of the program that publishes nothing: it prints the number, the tag and the
| changelog head it would write, and a reader trusts it only while it writes nothing at all — the
| file, the ref and the tags are all read back here. The head it prints is indented by the plan's own
| helper, which is the part of the output a reader skims.
|
| A file of its own, because a file is what `paratest` spreads work by, and because these three runs
| are all the same fixture state: a repository nothing has been released in yet.
|
| @guards-index test
*/
it('writes nothing on a dry run, even on a dirty tree', function (): void {
    $repo = ReleaseRepo::make();
    $before = $repo->read('CHANGELOG.md');
    $head = trim($repo->git('rev-parse', 'HEAD'));

    // A plan publishes nothing, so the rail that refuses a dirty tree reports instead.
    $repo->write('src/Thing.php', "<?php\n\n// uncommitted\n");

    $run = $repo->release('--weigh', '--dry-run');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->said('dry run: nothing was written'))->toBeTrue()
        ->and($run->said('uncommitted changes'))->toBeTrue()
        ->and($run->plan('checks'))->toBe('not run (--dry-run)')
        ->and($repo->read('CHANGELOG.md'))->toBe($before)
        ->and(trim($repo->git('rev-parse', 'HEAD')))->toBe($head)
        ->and($repo->tags())->toBe(['v0.0.1']);
});

it('prints the promoted changelog head in the plan', function (): void {
    $run = ReleaseRepo::make()->release('--weigh', '--dry-run');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->said('The CHANGELOG head after promotion:'))->toBeTrue()
        ->and($run->said('v0.1.0'))->toBeTrue();
});

it('indents every line of the changelog head it prints', function (): void {
    // The head is printed through the helper that indents the plan, and that helper has to split
    // on the line endings the *content* carries rather than on PHP_EOL: the promoted changelog is
    // worked on as LF while PHP_EOL is CRLF on Windows, and a helper that splits on PHP_EOL finds
    // one line in a dozen — indenting the first and leaving the rest at the margin.
    $run = ReleaseRepo::make()->release('--weigh', '--dry-run');
    $rows = explode("\n", str_replace("\r\n", "\n", $run->output));
    $start = array_search('The CHANGELOG head after promotion:', array_map(rtrim(...), $rows), true);

    expect($start)->toBeInt();

    $printed = [];
    $counter = count($rows);

    for ($index = (int) $start + 1; $index < $counter; $index++) {
        // Blank lines separate the blocks of the plan; the printed head is indented on every row.
        if (trim($rows[$index]) === '' && $printed === []) {
            continue;
        }

        if (! str_starts_with($rows[$index], '    ')) {
            break;
        }

        $printed[] = $rows[$index];
    }

    expect(count($printed))->toBeGreaterThanOrEqual(10)
        ->and(array_map(trim(...), $printed))->toContain('## Unreleased')
        ->and(array_map(trim(...), $printed))->toContain('### Added');
});
