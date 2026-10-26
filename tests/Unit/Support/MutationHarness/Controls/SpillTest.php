<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MachinePaths;
use Uak35\ResponseCompression\Tests\Support\MutationHarness;
use Uak35\ResponseCompression\Tests\Support\RepoEscapes;

/*
|--------------------------------------------------------------------------
| The control for the spill: a copy that carries one, and the tree planted from it
|--------------------------------------------------------------------------
|
| The holds are only worth anything if the tree they read is clean, and the tree is planted from a
| listing of this package's own work rather than from every file in the folder. That exclusion is
| the one thing in the harness nobody could see from a green run: a checkout spilled into by the
| package beside it plants a clean tree, every hold answers nothing as it should, and the two path
| readings go on naming the spill on the checkout itself — which is the colour that tree is
| supposed to have.
|
| Both halves of that are asserted here, on copies rather than on this checkout, because a test that
| wrote a spill here to prove the guard fires would be the spill. A copy of the checkout is taken,
| a spill is planted in it, and the two answers are read from the two trees: the copy is a checkout
| the path readings name, and the tree planted from that same copy is one every hold answers
| nothing on. The second half is the whole list of mutations rather than the one about paths,
| because the listing is one listing — a spill that reached it would redden the holds of every
| guard at once, and this is where that is measured rather than assumed.
|
| @guards-index test
|
*/

it('plants a tree from a copy that carries a spill, with the copy named and every hold green', function (): void {
    // The drive path and the climb are assembled rather than written out, because this file is
    // itself a file a commit would carry: a literal one here would be a finding of the very reading
    // the control is about.
    $drive = 'C:'.'/tools/php/php.exe';
    $climb = '..'.'/'.'../sibling/notes.md';

    // A copy of this checkout, made the way a hold's tree is made and left uncached: it is a
    // checkout to spill into, not the tree the mutations go into.
    $copy = MutationHarness::tree(MutationHarness::package());

    file_put_contents($copy.'/spill.machine.json', json_encode(['php' => $drive])."\n");
    file_put_contents($copy.'/spill-script.md', 'See [the package next door]('.$climb.').'."\n");

    $named = MachinePaths::scan($copy);

    // The listing is checked for substance before the finding is believed: a scan that read nothing
    // agrees with a copy that leaked nothing, so the spill has to be a file the listing saw.
    expect($named['files'])->toContain('spill.machine.json')
        ->and(array_column($named['found'], 'file'))->toContain('spill.machine.json')
        ->and(array_column(RepoEscapes::scan($copy)['found'], 'file'))->toContain('spill-script.md');

    $planted = MutationHarness::tree($copy);
    $files = MachinePaths::files($planted);

    // The tree the holds read is checked for substance on the same terms: the work has to be in it,
    // and the spill has to be out of it — a copy that carried nothing would satisfy the exclusion
    // without carrying the package the holds are about.
    expect($files)->toContain('composer.json')
        ->toContain('tests/Support/MachinePaths.php')
        ->not->toContain('spill.machine.json')
        ->not->toContain('spill-script.md');

    // Every hold's own reading, on that tree: the pristine half of the mutation it carries, which
    // has to answer with nothing. The answer is collected with the guard's name so a failure says
    // which hold went red and what it named, rather than that some list was not empty.
    $answered = [];

    foreach (MutationHarness::mutations() as $mutation) {
        $read = $mutation['read'];
        $found = $read($planted);

        if ($found !== []) {
            $answered[] = $mutation['guard'].' — '.implode('; ', $found);
        }
    }

    expect($answered)->toBe([]);
});
