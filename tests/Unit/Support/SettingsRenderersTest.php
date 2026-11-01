<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\Processes;
use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;
use Uak35\ResponseCompression\Tests\Support\ReleaseRun;

/*
|--------------------------------------------------------------------------
| Two renderers, and the placeholders they write
|--------------------------------------------------------------------------
|
| `.agents/render_local.py` writes the settings files a tool reads from
| `.agents/machine.local.json`, and `manage-config.py` writes `opencode.jsonc` from
| `opencode.jsonc.template` and the same file. Both are the place a typo becomes a path a tool
| reads, so both are asked here rather than only by the layer's own check — which judges the tree
| this checkout happens to hold, and a template is committed to every checkout.
|
| Three questions, none of which a run on a well-formed tree can put. A template naming a
| placeholder no token resolves used to render as itself: the file looked finished and held a name
| nothing resolves. `--init --force` used to keep a section only when the section was a mapping, so
| a machine that recorded a string, a list or a number under a declared name lost it to the next
| re-pointing — and a record it could not parse was read as an empty one and written over. And a
| substituted value went in as raw text, so a path holding a quote, a backslash or a line ending
| took the document with it: `\n` became a line ending inside a JSON string, which is a machine path
| silently changed rather than a crash.
|
| The fixture is a tree of its own with this repository's renderers copied into it, so each question
| is asked of the program that owns it rather than of a copy of its logic.
|
| @guards-index test
*/

/**
 * A tree holding the two renderers, the settings templates, and a machine file that answers every
 * token: the state a clone is in once `--init` has run and will be asked to run again.
 */
function settingsTree(): ReleaseRepo
{
    $package = ReleaseRepo::package();
    $tree = ReleaseRepo::plain();

    $tree->write('.agents/render_local.py', (string) file_get_contents($package.'/.agents/render_local.py'));
    $tree->write('manage-config.py', (string) file_get_contents($package.'/manage-config.py'));

    foreach (['.trae/settings.json.example', '.trae/settings.local.json.example', '.kiro/settings/local.json.example'] as $template) {
        $tree->write($template, (string) file_get_contents($package.'/'.$template));
    }

    $tree->write('.agents/machine.local.json', settingsRecord([
        'php' => ['exe' => '/first/answer/php.exe', 'dir' => '/first/answer'],
        'pwsh' => ['exe' => 'pwsh.exe'],
        'package' => ['dir' => $tree->path],
        'systemTempFolder' => ['systemTempFolderPath' => $tree->path.'/temp'],
    ]));

    return $tree;
}

/**
 * A machine file written from the sections given, in the order they are given.
 *
 * @param  array<string, mixed>  $sections
 */
function settingsRecord(array $sections): string
{
    return json_encode($sections, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
}

/**
 * A rendered document as the structure the editor would read, refused rather than returned when it
 * is not one.
 *
 * @return array<mixed>
 */
function settingsDocument(string $text): array
{
    $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

    return is_array($decoded) ? $decoded : [];
}

/**
 * One of the two renderers, run in the tree with the arguments given.
 *
 * `Processes` rather than `proc_open()`: under a pool this machine refuses a start outright, and a
 * fixture that never got its child is a test that reports the machine rather than the renderer.
 */
function settingsRun(ReleaseRepo $tree, string $program, string ...$arguments): ReleaseRun
{
    // Built in two steps so the type is a list rather than an array a spread might have re-keyed:
    // `Processes::start()` takes the command it will hand to `proc_open()` in order.
    $command = ['python', $tree->path($program)];
    array_push($command, ...$arguments);

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

it('refuses a template naming a placeholder nothing resolves, and writes nothing for it', function (): void {
    $tree = settingsTree();

    // A token renamed in the template and not in the table: the placeholder is well formed, the JSON
    // is well formed, and every value the machine file holds still resolves — so the only thing wrong
    // is a name, which is the one mistake a committed template makes on its own.
    $tree->write(
        '.trae/settings.local.json.example',
        str_replace('__PHP_EXE__', '__PHP_PATH__', $tree->read('.trae/settings.local.json.example')),
    );

    $run = settingsRun($tree, '.agents/render_local.py');

    expect($run->exitCode)->toBe(1, $run->describe())
        ->and($run->output)->toContain('.trae/settings.local.json.example: names __PHP_PATH__')
        ->and($run->output)->toContain('nothing resolves')

        // Nothing was written for the template that names it — and the template beside it, which
        // names only tokens the table has, still was: a refusal is about one file rather than about
        // the run, which is what makes it useful.
        ->and($tree->exists('.trae/settings.local.json'))->toBeFalse()
        ->and($tree->exists('.trae/settings.json'))->toBeTrue()
        ->and($tree->read('.trae/settings.json'))->toContain('/first/answer/php.exe')
        ->and($tree->read('.trae/settings.json'))->not->toContain('__PHP_EXE__');

    // And the same question in the mode that writes nothing, so a checkout is told about it before a
    // commit rather than after a render.
    $checked = settingsRun($tree, '.agents/render_local.py', '--check');

    expect($checked->exitCode)->toBe(1)
        ->and($checked->output)->toContain('names __PHP_PATH__');
});

it('keeps every section it did not write, whatever shape the section holds', function (): void {
    $tree = settingsTree();

    // A record of this machine as somebody wrote it by hand: the shapes the example shows (mappings)
    // beside the shapes a record takes when the fact is a single value. `--force` promises to keep
    // the rest, and the rest is whatever is there — a section kept only when it is a mapping is a
    // record kept only when it looks like the program's own.
    $tree->write('.agents/machine.local.json', settingsRecord([
        'php' => ['exe' => '/first/answer/php.exe', 'version' => '8.4.27'],
        'pwsh' => ['exe' => 'pwsh.exe'],
        'package' => ['dir' => $tree->path],
        'systemTempFolder' => ['systemTempFolderPath' => $tree->path.'/temp'],
        'search' => 'the ripgrep this machine records',
        'skills' => ['.agents/skills/rc-dev', '.agents/skills/rc-push'],
        'checks' => 11,
        'tarball' => false,
        'undeclaredSection' => ['kept', 'because', 'it is not ours'],
    ]));

    $run = settingsRun(
        $tree,
        '.agents/render_local.py',
        '--init',
        '--force',
        '--yes',
        '--php=/second/answer/php.exe',
        '--pwsh=pwsh.exe',
        '--package='.$tree->path,
    );

    $record = $tree->read('.agents/machine.local.json');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->output)->not->toContain('[FAIL]')

        // The answer it was given, written: this is the half `--force` exists for.
        ->and($record)->toContain('/second/answer/php.exe')

        // And everything beside it, whichever shape it holds, verbatim.
        ->and($record)->toContain('8.4.27')
        ->and($record)->toContain('the ripgrep this machine records')
        ->and($record)->toContain('.agents/skills/rc-push')
        ->and($record)->toContain('"checks": 11')
        ->and($record)->toContain('"tarball": false')
        ->and($record)->toContain('it is not ours');
});

it('refuses to name a machine again over a record it cannot read, and leaves it as it was', function (): void {
    $tree = settingsTree();

    // A record somebody was halfway through editing. `--init` reads the file so it can keep what is
    // there, so a parse failure is not "nothing recorded" — it is the one state in which the program
    // cannot tell what it would be keeping, and writing its answers over it is how a machine's record
    // is lost to a command that meant to update three keys.
    $broken = "{\n    \"php\": {\n        \"exe\": \"/half/written\"\n";
    $tree->write('.agents/machine.local.json', $broken);

    $run = settingsRun(
        $tree,
        '.agents/render_local.py',
        '--init',
        '--force',
        '--yes',
        '--php=/second/answer/php.exe',
        '--pwsh=pwsh.exe',
        '--package='.$tree->path,
    );

    expect($run->exitCode)->not->toBe(0, $run->describe())
        ->and($run->error)->toContain('.agents/machine.local.json')
        ->and($run->error)->toContain('could not be read as JSON')
        ->and($tree->read('.agents/machine.local.json'))->toBe($broken);
});

it('escapes a value into the string it is written in, and refuses a document that stops parsing', function (): void {
    $tree = settingsTree();

    // A path-shaped value that is hostile to text: the two backslashes and the quote the JSON string
    // has to carry, and a line ending that used to end the string it sat in. Relative on purpose — a
    // drive path here is a machine path in a file a commit carries, which `MachinePathsTest` refuses.
    $hostile = 'build\\temp\\a "quoted" dir'."\n".'and a second line';

    $tree->write('opencode.jsonc.template', settingsRecord([
        'permissions' => [['action' => 'shell', 'resource' => '<__SYSTEM_TEMP_FOLDER__>/opencode/*']],
    ]));
    $tree->write('.agents/machine.local.json', settingsRecord([
        'systemTempFolder' => ['systemTempFolderPath' => $hostile],
    ]));

    $run = settingsRun($tree, 'manage-config.py');

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->output)->toContain('wrote opencode.jsonc');

    // Read back as a document rather than as text: the claim is that the config the editor parses
    // holds the path this machine has, and a value pasted in raw fails that by being unreadable or by
    // reading as something shorter.
    $written = settingsDocument($tree->read('opencode.jsonc'));

    expect(data_get($written, 'permissions.0.resource'))->toBe($hostile.'/opencode/*');

    // The template is JSONC — it carries the prose that explains each rule beside the rule — so a
    // comment and a trailing comma are still a document this program reads.
    $tree->write('opencode.jsonc.template', "{\n    // the prose beside the rule\n    \"skills\": [\n        \".agents/skills\",\n    ],\n}\n");

    $jsonc = settingsRun($tree, 'manage-config.py');

    expect($jsonc->exitCode)->toBe(0, $jsonc->describe());

    // And a document that stops making sense is refused before it is written, with the file it came
    // from named and the previous config left where it was: the editor reads a broken file happily,
    // and the mistake only surfaces later as a path that does not exist.
    $tree->write('opencode.jsonc.template', "{\n    \"permissions\": [\n");
    $before = $tree->read('opencode.jsonc');

    $refused = settingsRun($tree, 'manage-config.py');

    expect($refused->exitCode)->not->toBe(0, $refused->describe())
        ->and($refused->error)->toContain('opencode.jsonc.template')
        ->and($refused->error)->toContain('does not read as JSONC')
        ->and($tree->read('opencode.jsonc'))->toBe($before);
});
