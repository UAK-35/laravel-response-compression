<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Notes;
use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;
use Uak35\ResponseCompression\Tests\Support\ReleaseRun;
use Uak35\ResponseCompression\Tests\Support\ToolPaths;

/*
|--------------------------------------------------------------------------
| The gate
|--------------------------------------------------------------------------
|
| `bin/checks.php` is the script everything else is measured by, and until now no test had ever
| run it. The release rails plant a three-line `exit(1)` stub where it should be whenever they need
| a gate that fails on cue, which is the right stub to plant and the reason the real gate read 0.0%
| on the coverage floor: it was never executed by the suite at all.
|
| So these run it. A planted tree is the right place for that — the gate is a program about a
| directory: it walks it, parses it, and answers one exit code — and half of what it does on such a
| tree is refuse politely, because a tree without `vendor/` has none of the tools it runs. The
| skips are the point of the gate on a developer's machine, and they are the half nobody could see.
|
| Every assertion here is about a fact the tree decides, so none of them turn on where this runs:
| the tool paths are the planted tree's own — the manifest is copied in beside the gate — and a
| tree that was never installed has none of them on any platform.
|
*/

/**
 * @guards-index guard the gate's own tests
 *
 * the script everything else is measured by, which no test had ever run — its exit codes, its skips and its refusals, in a planted tree
 */

/**
 * A planted tree with the real gate in it, in place of the stub the release rails plant.
 *
 * The manifest comes with it, because a gate and the paths it runs are one arrangement, and this
 * fixture is the arrangement this package ships: a tree carrying the gate but not
 * `bin/tool-paths.php` is a tree of a different shape, and the two are told apart by a test rather
 * than by accident.
 */
function gateRepo(): ReleaseRepo
{
    $repo = ReleaseRepo::make();
    $repo->write('bin/checks.php', (string) file_get_contents(ReleaseRepo::package().'/bin/checks.php'));
    $repo->write('bin/tool-paths.php', (string) file_get_contents(ReleaseRepo::package().'/bin/tool-paths.php'));

    return $repo;
}

function gate(ReleaseRepo $repo, string ...$arguments): ReleaseRun
{
    return $repo->script('bin/checks.php', ...$arguments);
}

/**
 * Plant a `pest` that answers a pooled run from a JUnit log and a lone run from what it was told.
 *
 * The reading this holds is the gate's, and the program it starts is the tree's own tool — so this
 * is a stub in the shape the real Pest answers in rather than the real Pest, which is the same
 * arrangement every other check here is driven through. Handed `--parallel` it is the pooled run: it
 * writes the JUnit log the gate reads back and exits non-zero. Asked a single file it is the retry:
 * it appends what it was asked about to `asked-again.txt` at the tree's root, so a test can see
 * whether the gate asked at all and about what, and exits with the code it was told to.
 *
 * @param  string  $counts  what the file's `<testsuite>` reports — `errors="1" failures="0"` for the
 *                          pool's own noise, `failures="1"` for a run that asserted something
 * @param  int  $alone  the exit code a lone run answers with: 0 heals, 1 does not
 * @param  string  $alsoAsserts  a second file the same run reports as an assertion failure, which is
 *                               the shape a storm of pool errors arrives in: noise beside a finding
 */
function plantFlakyPest(ReleaseRepo $repo, string $file, string $counts, int $alone, string $alsoAsserts = ''): void
{
    $manifest = ToolPaths::manifest(ReleaseRepo::package().'/'.ToolPaths::MANIFEST);

    $second = $alsoAsserts === ''
        ? ''
        : sprintf('<testsuite name="also" file="%s" errors="0" failures="1"></testsuite>', $alsoAsserts);

    // The nowdoc's label is `STUB` rather than `PHP`, and that is not a whim: a label whose name
    // appears in the body — `PHP_EOL` is on the next screenful — is one Rector rewrites into an
    // indented `PHP_WRAP` heredoc, because a label found in the content is the case its rule cannot
    // tell from a closing marker that would end the string early.
    $repo->write($manifest['pest']['path'], sprintf(<<<'STUB'
<?php

$arguments = array_slice($argv, 1);
$pooled = in_array('--parallel', $arguments, true);

if ($pooled) {
    $log = $arguments[array_search('--log-junit', $arguments, true) + 1];

    file_put_contents($log, '<testsuites><testsuite name="pool" file="%s" %s></testsuite>%s</testsuites>');
    echo 'Tests: 1 failed, 1 passed (2 assertions)'.PHP_EOL;

    exit(1);
}

$asked = array_values(array_filter($arguments, static fn (string $argument): bool => ! str_starts_with($argument, '-')));

file_put_contents('asked-again.txt', implode(PHP_EOL, $asked).PHP_EOL, FILE_APPEND);

exit(%d);
STUB, $file, $counts, $second, $alone));
}

/**
 * Plant a `pest` whose pooled run cannot start several files at once, and whose lone runs answer.
 *
 * The storm `runPest()` was written for, and the one a list of names answers badly: the log carries
 * every test file the run held, most of them fine and a handful of them errors, so the gate can count
 * what it could not start against what it ran. A lone run is the retry, and it does what
 * `plantFlakyPest()`'s does — records the file it was asked about, then exits with the code it was
 * told, so the same stub serves a storm that heals and one that does not.
 */
function plantStormPest(ReleaseRepo $repo, int $erred, int $clean, int $alone): void
{
    $manifest = ToolPaths::manifest(ReleaseRepo::package().'/'.ToolPaths::MANIFEST);

    $suites = '';

    for ($index = 1; $index <= $clean; $index++) {
        $suites .= sprintf('<testsuite name="ok" file="tests/Unit/Ok/Ok%02dTest.php" errors="0" failures="0"></testsuite>', $index);
    }

    for ($index = 1; $index <= $erred; $index++) {
        $suites .= sprintf('<testsuite name="erred" file="tests/Unit/Heavy/Heavy%02dTest.php" errors="1" failures="0"></testsuite>', $index);
    }

    // The label is `STUB` for the reason the other stub gives: a label whose name appears in the body
    // — `PHP_EOL` does — is one Rector rewrites into a heredoc.
    $repo->write($manifest['pest']['path'], sprintf(<<<'STUB'
<?php

$arguments = array_slice($argv, 1);
$pooled = in_array('--parallel', $arguments, true);

if ($pooled) {
    $log = $arguments[array_search('--log-junit', $arguments, true) + 1];

    file_put_contents($log, '<testsuites>%s</testsuites>');
    echo 'Tests: %d passed, %d failed (%d assertions)'.PHP_EOL;

    exit(1);
}

$asked = array_values(array_filter($arguments, static fn (string $argument): bool => ! str_starts_with($argument, '-')));

file_put_contents('asked-again.txt', implode(PHP_EOL, $asked).PHP_EOL, FILE_APPEND);

exit(%d);
STUB, $suites, $clean, $erred, ($clean + $erred) * 10, $alone));
}

it('lists what it would run, and the tools it would run them with', function (): void {
    $run = gate(gateRepo(), '--list');

    expect($run->exitCode)->toBe(0)
        ->and($run->said('PHP syntax (php -l)'))->toBeTrue()
        ->and($run->said('Agentic layer'))->toBeTrue()
        ->and($run->said('Test suite (pest)'))->toBeTrue();

    // And the tools behind those checks, out of the manifest rather than out of a sentence beside
    // the gate: the version each is pinned at, and what it is for. The words come from the manifest
    // here rather than being written a second time in this assertion, which is what makes it a
    // check on the reading rather than on the sentence.
    $manifest = ToolPaths::manifest(ReleaseRepo::package().'/'.ToolPaths::MANIFEST);

    expect($run->said($manifest['pest']['why']))->toBeTrue()
        ->and($run->said($manifest['pest']['pin']))->toBeTrue()
        ->and($run->said($manifest['yaml-lint']['why']))->toBeTrue();
});

it('refuses an option it does not have, and says what it does have', function (): void {
    $run = gate(gateRepo(), '--nope');

    // A usage error is not a failed check, so it is a different code: 2, which is what a caller
    // scripting this has to be able to tell apart from "the tree is bad". The complaint is on the
    // error stream because it is a complaint, and the usage is on the output stream because it is
    // the answer to anyone who reads the screen — which is where it has to be to be readable.
    expect($run->exitCode)->toBe(2)
        ->and($run->error)->toContain('Unknown option')
        ->and($run->output)->toContain('Usage:');
});

it('passes a tree that parses, and names the file that does not', function (): void {
    $repo = gateRepo();

    $clean = gate($repo, '--only=syntax');

    expect($clean->exitCode)->toBe(0)
        ->and($clean->output)->toContain('no syntax errors');

    // The half that matters: a file that cannot be parsed is a failure rather than a note, and the
    // summary names it, because the whole point of the gate is being able to believe the tick.
    $repo->write('src/Broken.php', "<?php\n\nfunction nope(\n");

    $broken = gate($repo, '--only=syntax');

    expect($broken->exitCode)->toBe(1)
        ->and($broken->output)->toContain('src/Broken.php');
});

it('says which check is running on the error stream, so a commit is not waiting on silence', function (): void {
    $repo = gateRepo();

    // The summary captures each check's output, so a check that runs for minutes prints nothing
    // while it does — and the staged set runs the whole suite when a file the suite is built on is
    // part of the commit, which is a hook sitting in front of a prompt with no sign of life.
    $run = gate($repo, '--only=syntax');

    // The title, on stderr, before the check starts. `syntax` is the check a planted tree always
    // answers, so the line is the gate's own and not something the check's output carried in: the
    // summary prints the title too, on stdout, and that is the half a commit never sees.
    expect($run->error)->toContain('▶ PHP syntax (php -l)')
        ->and($run->output)->not->toContain('▶ PHP syntax (php -l)')
        ->and($run->exitCode)->toBe(0);
});

it('skips a tool the tree does not have, and refuses to when it is told to', function (): void {
    $repo = gateRepo();

    // A tree that was never installed has no `vendor/`, so every tool the gate runs is absent —
    // which is a skip on a workstation and a hole in the build on a runner.
    $skipped = gate($repo, '--only=pint');

    expect($skipped->exitCode)->toBe(0)
        ->and($skipped->output)->toContain('is not installed');

    $required = gate($repo, '--only=pint', '--require-all');

    expect($required->exitCode)->toBe(1);
});

it('reads a tree with no manifest as a tree with no tools', function (): void {
    // The gate is not only pointed at this package. `--only=pint` on a tree that has neither the
    // manifest nor a `vendor/` is the shape a checkout whose dependencies were never installed has,
    // and it is the shape every planted fixture here has: no manifest is no tool, which skips —
    // where an include of a file that is not there would end the run before the first check.
    $repo = gateRepo();
    $repo->delete('bin/tool-paths.php');

    $run = gate($repo, '--only=pint');

    expect($run->exitCode)->toBe(0)
        ->and($run->output)->toContain('laravel/pint is not installed')

        // And the list is still the answer to "what would this run?", which is the half a tree with
        // nothing in it can still give.
        ->and(gate($repo, '--list')->output)->toContain('Code style (pint --test)');
});

it('skips the agentic layer on a tree that has none, and refuses to when it is told to', function (): void {
    // The gate is pointed at trees that are not this package as well as at this one - the release
    // rails plant a fixture, and every fixture here is a tree with a gate and no `.agents/` - and a
    // layer that is not there is a skip rather than a crash. It is the same half the toolchain probe
    // has, and the same reason: a machine that loses the reading should say so rather than report a
    // green gate that quietly stopped asking.
    $run = gate(gateRepo(), '--only=agentic');

    expect($run->exitCode)->toBe(0)
        ->and($run->output)->toContain('there is no agentic layer');

    $required = gate(gateRepo(), '--only=agentic', '--require-all');

    expect($required->exitCode)->toBe(1);
});

it('repeats a note a check printed, so a skip is not read as a clean answer', function (): void {
    $repo = gateRepo();

    // A tool with nothing to report says so on a line of its own, and the note cell beside a check is
    // the last line of its output — so a marker printed above that line is a line the summary would
    // otherwise drop, and the run would read as one that had nothing to say. The marker is built from
    // `Notes::MARKER` rather than typed out, and this fixture is a tree with no `vendor/` and so no
    // autoloader: the assertion is therefore also the one thing holding the class's spelling of the
    // marker to the spelling `bin/checks.php` carries for a tree it cannot load the class in.
    $manifest = ToolPaths::manifest(ReleaseRepo::package().'/'.ToolPaths::MANIFEST);

    $repo->write(
        $manifest['pint']['path'],
        "<?php\n\necho 'fixed 0 files'.PHP_EOL;\n".
        "echo '".Notes::MARKER." probe: nothing to style here'.PHP_EOL;\n".
        "echo 'done'.PHP_EOL;\n",
    );

    $run = gate($repo, '--only=pint');

    expect($run->exitCode)->toBe(0)
        ->and($run->output)->toContain('done')
        ->and($run->output)->toContain(Notes::MARKER.' probe: nothing to style here');
});

it('prints what the suite could not, out of the file it named for it', function (): void {
    $repo = gateRepo();

    // The other half of the note channel, and the one a runner sees: the suite runs under
    // `paratest`, a worker's own two streams are dropped by the runner when its test passed, so a
    // note about a runner with no machine file survives only as a file — which means the gate has to
    // name one, hand it to the suite it starts, and print what came back. The planted check is a
    // runner that can only do the second thing: it writes the sentence to the file it was handed and
    // exits green, which is exactly what a reading does and what the runner then drops.
    //
    // It writes it twice, because two workers each holding the same reading is what an undisturbed
    // run of a suite would be, and the gate's answer is one line for one sentence. Both the variable
    // name and the marker are the class's rather than this test's spelling: a gate that invented
    // either would find nothing to print here, and that is the assertion.
    $manifest = ToolPaths::manifest(ReleaseRepo::package().'/'.ToolPaths::MANIFEST);
    $note = Notes::MARKER.' pest: nothing to test here';

    $repo->write(
        $manifest['pest']['path'],
        "<?php\n\n".
        '$sink = getenv('.var_export(Notes::SINK, true).");\n".
        "if (is_string(\$sink)) {\n".
        "    foreach (range(1, 2) as \$worker) {\n".
        '        file_put_contents($sink, '.var_export($note, true).'.PHP_EOL, FILE_APPEND);'."\n".
        "    }\n".
        "}\n".
        "echo 'Tests: 0 passed (0 assertions)'.PHP_EOL;\n",
    );

    $run = gate($repo, '--only=tests');

    expect($run->exitCode)->toBe(0)
        ->and($run->output)->toContain($note)
        ->and(substr_count($run->output, $note))->toBe(1);
});

it('asks a file the pool erred on again, alone, and counts the run as the pool being wrong', function (): void {
    $repo = gateRepo();
    $file = 'tests/Unit/Heavy/HeavyTest.php';

    // The pool's own failure, in the shape it arrives in: an error — a test that could not start a
    // process — for a file that passes when it is the only thing running.
    plantFlakyPest($repo, $file, 'errors="1" failures="0"', 0);

    $run = gate($repo, '--only=tests');

    // Green, because the file answered a second time — and said so in both places a reader looks:
    // the marked note under the table, and the check's own summary line, which would otherwise read
    // PASS beside the "1 failed" the pool's run printed. The note counts the file against the run
    // rather than naming it again: it sits under a line that already names it, and "1 of the 1 test
    // files it ran" is the sentence a machine's answer is made of. One file is not called load: the
    // machine is only the reading when a second file fails to start in the same run, because a file
    // cannot make another file's process fail.
    expect($run->exitCode)->toBe(0)
        ->and($run->output)->toContain(
            Notes::MARKER.' the parallel run could not start 1 of the 1 test files it ran, and it passed when it was asked again alone'
        )
        ->and($run->output)->toContain('1 file the pool errored on passed alone')
        ->and($repo->read('asked-again.txt'))->toBe($file.PHP_EOL);
});

it('keeps the failure when the file errs alone as well', function (): void {
    $repo = gateRepo();
    $file = 'tests/Unit/Heavy/HeavyTest.php';

    // The same first answer, from a file that is genuinely broken: asked on its own, it fails again.
    plantFlakyPest($repo, $file, 'errors="1" failures="0"', 1);

    $run = gate($repo, '--only=tests');

    // A file that fails twice is a finding rather than noise, so the run keeps the verdict the first
    // run gave — and the second run is in the output, because it is evidence rather than a secret.
    expect($run->exitCode)->toBe(1)
        ->and($run->output)->toContain($file.' — failed alone too')
        ->and(str_contains($run->output, Notes::MARKER.' the parallel run errored on'))->toBeFalse();
});

it('names the pool\'s files even when the same run asserted something', function (): void {
    $repo = gateRepo();
    $erred = 'tests/Unit/Heavy/HeavyTest.php';
    $asserted = 'tests/Unit/Real/RealTest.php';

    // The shape a storm arrives in: a pile of `proc_open` failures under the pool, beside one real
    // finding. The verdict is the finding's and stays red — but the run has to say which of the two
    // dozen red lines were the pool being wrong, or a reader is left to work it out.
    plantFlakyPest($repo, $erred, 'errors="1" failures="0"', 0, $asserted);

    $run = gate($repo, '--only=tests');

    // Two files are in the log — the one that erred and the one that asserted — so the note counts
    // the one against the two, and the summary repeats it: a red run whose numbers were the pool's is
    // the case that most needs both halves said out loud.
    expect($run->exitCode)->toBe(1)
        ->and($run->output)->toContain(
            Notes::MARKER.' the parallel run could not start 1 of the 2 test files it ran, and it passed when it was asked again alone'
        )
        ->and($run->output)->toContain('1 file the pool errored on passed alone')
        ->and($repo->read('asked-again.txt'))->toBe($erred.PHP_EOL);
});

it('calls a storm a machine under load, and says how many of the files it ran could not start', function (): void {
    $repo = gateRepo();

    // The shape a reader has to work out for themselves today: thirty-nine test files in one pooled
    // run, five of which the pool could not start, and every one of those five green when asked on
    // its own. What a list of five names does not say is the denominator — five of thirty-nine is a
    // machine with sixteen workers spawning children, five of five is a codebase in trouble — and the
    // count printed beside the run is the only place that distinction can be made.
    plantStormPest($repo, 5, 34, 0);

    $run = gate($repo, '--only=tests');

    expect($run->exitCode)->toBe(0)
        // The note is the measurement and the reading of it: how many files the run held, how many it
        // could not start, and that five at once is the machine rather than five findings.
        ->and($run->output)->toContain(
            Notes::MARKER.' the parallel run could not start 5 of the 39 test files it ran — a machine under load rather than 5 findings in the code — and each passed when it was asked again alone'
        )
        // And the row says the same two numbers, because the note under the table is not where a
        // reader of a gate run looks first: the summary is what they came for.
        ->and($run->output)->toContain('; 5 of the 39 files the pool ran could not start under load, and each passed alone')
        ->and($run->output)->not->toContain(Notes::MARKER.' the parallel run errored on')
        // Every one of the five was asked, once, and alone: the count above is only worth printing if
        // the retry behind it actually happened.
        ->and(explode(PHP_EOL, trim($repo->read('asked-again.txt'))))->toHaveCount(5);
});

it('does not ask again for a file that failed an assertion', function (): void {
    $repo = gateRepo();
    $file = 'tests/Unit/Heavy/HeavyTest.php';

    // The half the retry must never touch: a file that asserted something has told the truth about
    // the code, and asking it again would be the gate giving an assertion a second chance. The stub
    // is planted so that a lone run *would* pass — so the absence of the record below is the whole
    // assertion: nothing was ever asked.
    plantFlakyPest($repo, $file, 'errors="0" failures="1"', 0);

    $run = gate($repo, '--only=tests');

    expect($run->exitCode)->toBe(1)
        ->and($repo->exists('asked-again.txt'))->toBeFalse();
});

it('checks only what a commit carries, and says so when that is nothing', function (): void {
    // `--staged` is what the pre-commit hook runs, and on a tree with nothing staged the three
    // checks it narrows to have nothing to read. Reporting that as a pass is the whole reason the
    // hook can run in front of every commit without blocking one.
    $run = gate(gateRepo(), '--staged');

    expect($run->exitCode)->toBe(0)
        ->and($run->output)->toContain('no staged');
});

it('names what the commit does not carry, from the ignored entries at the root', function (): void {
    // An ignored file is the one kind of uncommitted work `git status` says nothing about — it is
    // absent from the listing rather than marked in it — and the rules in this repository are
    // written for a session's leavings: a scratch copy, an editor's export, a log. So the staged run,
    // which is the moment a commit is made, repeats the names sitting at the root.
    $repo = gateRepo();
    $repo->write('.gitignore', "*.scratch\n");
    $repo->write('left-behind.scratch', "a session's scratch copy\n");
    $repo->write('second.scratch', "another one\n");

    $run = gate($repo, '--staged');

    expect($run->exitCode)->toBe(0)
        ->and($run->output)->toContain(
            Notes::MARKER.' checks: ignored at the root, so no commit carries them: left-behind.scratch, second.scratch'
        );

    // And a tree where nothing is ignored gets no line at all: the note says something because
    // something is sitting outside the commit, not because a run happened.
    $clean = gateRepo();
    $clean->write('.gitignore', "*.scratch\n");

    expect(gate($clean, '--staged')->output)->not->toContain('ignored at the root');
});
