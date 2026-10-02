<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\PhpBinary;
use Uak35\ResponseCompression\Tests\Support\ReleaseRepo;
use Uak35\ResponseCompression\Tests\Support\ReleaseRun;

/*
|--------------------------------------------------------------------------
| The one command a fresh clone is set up with
|--------------------------------------------------------------------------
|
| A clone that has just been made is three things short: it does not say which PHP this machine
| runs, it has no `vendor/`, and nothing has been verified on it. `bin/bootstrap.php` does all
| three, in that order, and the order is the part worth testing: `vendor/` has to exist before the
| gate runs against the tree, and the machine file has to exist before the toolchain is asked what
| interpreter it is under.
|
| The fixture is an *empty checkout* rather than a copy of this one: `ReleaseRepo::plain()` is a
| tree with `composer.json` and nothing installed, and the two heavy neighbours are stubbed so that
| what is measured is this program's orchestration rather than Composer and the whole suite. The
| renderer is *not* stubbed — `python .agents/render_local.py --init`, the real one, writes the
| machine file in the fixture — because "it names this machine's PHP" is the claim, and the way to
| read a claim is to let the thing that owns it answer.
|
| What each stub leaves behind is a line in a journal, so the three steps are read in the order
| they happened rather than inferred from three files existing. Both stubs can also be made to
| fail, which is how the refusals are reached: a setup command that carried on after a step failed
| would report a clone as ready on the strength of its first step.
|
| @guards-index test
*/

/**
 * An empty checkout: this program, the renderer it drives, the templates the renderer writes, and
 * two stand-ins for the parts that would reach the network and take minutes.
 */
function bootstrapCheckout(): ReleaseRepo
{
    $package = ReleaseRepo::package();
    $checkout = ReleaseRepo::plain();

    $checkout->write('bin/bootstrap.php', (string) file_get_contents($package.'/bin/bootstrap.php'));
    $checkout->write('.agents/render_local.py', (string) file_get_contents($package.'/.agents/render_local.py'));
    // The resolver as well, so the machine file this writes can be read back the way this package
    // reads it: an interpreter written down is only useful if the one file that decides which PHP
    // this checkout runs can find it again. Named through `PhpBinary::RESOLVER` rather than
    // spelled out, which is how every reader of that file names it - and this test reads none of
    // it: `bootstrapNamed()` below asks the resolver, and `Tests\Support\PhpBinary` is the one PHP
    // reader of the answer.
    $checkout->write(PhpBinary::RESOLVER, (string) file_get_contents($package.'/'.PhpBinary::RESOLVER));

    // The renderer refuses - exit 1, a finding per template - when a template it declares is not
    // there, so a fixture without them would be a machine file that was never written and a step
    // that failed for a reason that is about the fixture.
    foreach (['.trae/settings.json.example', '.trae/settings.local.json.example', '.kiro/settings/local.json.example'] as $template) {
        $checkout->write($template, (string) file_get_contents($package.'/'.$template));
    }

    $checkout->write('composer.phar', bootstrapComposer());
    $checkout->write('.agents/verify.py', bootstrapVerify());

    return $checkout;
}

/**
 * A stand-in for `composer.phar`: it writes down the arguments it was handed and exits with the
 * code in `composer-exit`, when the test has written one.
 */
function bootstrapComposer(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

bootstrap_journal('composer '.implode(' ', array_slice($argv, 1)));

$code = __DIR__.'/composer-exit';

exit(is_file($code) ? (int) trim((string) file_get_contents($code)) : 0);

function bootstrap_journal(string $line): void
{
    file_put_contents(__DIR__.'/journal', $line."\n", FILE_APPEND);
}

PHP;
}

/**
 * A stand-in for `.agents/verify.py`: the real one runs the whole gate, and it is Python, so this
 * is Python too — which also makes the third step's own claim true, that the verification is handed
 * to an interpreter rather than run by name.
 */
function bootstrapVerify(): string
{
    return <<<'PY'
import pathlib

root = pathlib.Path(__file__).resolve().parent.parent

with (root / "journal").open("a", encoding="utf-8") as handle:
    handle.write("verify\n")

code = root / "verify-exit"

raise SystemExit(int(code.read_text().strip()) if code.is_file() else 0)

PY;
}

/**
 * What the two stand-ins wrote down, in the order they wrote it: the three steps, as they happened.
 *
 * @return list<string>
 */
function bootstrapJournal(ReleaseRepo $checkout): array
{
    if (! $checkout->exists('journal')) {
        return [];
    }

    // Trimmed of the line ending rather than compared with it: the stub that writes the second
    // line is Python, and Python opens a file in text mode — so on Windows that one line ends
    // `\r\n` while the PHP stub's ends `\n`, which is a fact about two languages rather than
    // about the order the steps ran in.
    return array_values(array_filter(array_map(
        static fn (string $line): string => rtrim($line, "\r\n"),
        explode("\n", $checkout->read('journal')),
    )));
}

/**
 * The interpreter the fixture's checkout is now named after, read the way this package reads it.
 *
 * Not by opening the machine file here: which PHP runs is answered in one place, and
 * `Tests\Support\PhpBinary` is the PHP side of that answer - it asks `.agents/php-bin.sh`, which
 * asks the renderer, and a test that parsed the JSON itself would be the second reader the
 * `interpreter` step exists to refuse.
 */
function bootstrapNamed(ReleaseRepo $checkout): void
{
    // The resolver has to be here for the question to be put at all: it is the file that decides
    // which PHP this checkout runs, and the machine file is the rung it reads.
    expect($checkout->path(PhpBinary::RESOLVER))->toBeFile();

    $named = PhpBinary::named($checkout->path);

    // A machine with no `sh` cannot be asked, and the reading says so rather than guessing - the
    // same two answers `tests/Unit/Support/PhpBinaryTest.php` allows for the same reason. What is
    // asserted below is the answer, not its shape: here the resolver *should* be decided.
    if ($named->names === '') {
        return;
    }

    expect($named->rung)->toBe(PhpBinary::MACHINE)
        ->and($named->agrees())->toBeTrue();
}

it('names this machine\'s PHP, installs the dependencies and verifies the clone, in that order', function (): void {
    $checkout = bootstrapCheckout();
    $run = $checkout->script('bin/bootstrap.php');

    // The interpreter that ran this is the one the clone is named after — not a `php` found on
    // PATH, which on a machine with more than one of them is the failure this package keeps a
    // resolver for. Read back through the resolver rather than by opening the machine file here:
    // the rung has to be the machine file rather than a fallback, or it is an answer that would
    // have been the same on any other machine.
    bootstrapNamed($checkout);

    expect($run->exitCode)->toBe(0, $run->describe())

        // The order is the claim: nothing can be measured before `vendor/` exists, and the tools
        // cannot be asked which PHP they are under before the machine file says.
        ->and(bootstrapJournal($checkout))->toBe(['composer install --no-interaction', 'verify'])

        // And the renderer ran, rather than being described: the settings file it owns is here.
        ->and($checkout->exists('.trae/settings.json'))->toBeTrue()

        // Each step says which of how many it is, so a run that stopped is read as a run that
        // stopped rather than as one with something missing from it.
        ->and($run->output)->toContain('name this machine\'s PHP')
        ->and($run->output)->toContain('install the dependencies')
        ->and($run->output)->toContain('run the whole verification')
        ->and($run->output)->toContain('1/3')
        ->and($run->output)->toContain('3/3');
});

it('refuses when a program it needs is not here, naming the one that is missing', function (): void {
    $checkout = bootstrapCheckout();

    // A named interpreter that is not a file: the refusal is about the option that named it rather
    // than about PATH, and it comes before any step has run - a first step that wrote the machine
    // file and then failed would leave a clone half-named.
    $python = $checkout->script('bin/bootstrap.php', '--python='.$checkout->path('nowhere/python'));

    expect($python->exitCode)->toBe(1)
        ->and($python->error)->toContain('python')
        ->and($python->error)->toContain('nowhere/python')
        ->and($python->error)->toContain('is not a file')
        ->and($checkout->exists('.agents/machine.local.json'))->toBeFalse()
        ->and(bootstrapJournal($checkout))->toBe([]);

    $composer = $checkout->script('bin/bootstrap.php', '--composer='.$checkout->path('nowhere/composer.phar'));

    expect($composer->exitCode)->toBe(1)
        ->and($composer->error)->toContain('composer')
        ->and($composer->error)->toContain('nowhere/composer.phar')
        ->and($composer->error)->toContain('is not a file')
        ->and(bootstrapJournal($checkout))->toBe([]);
});

it('stops at the step that failed rather than reporting the clone as ready', function (): void {
    $checkout = bootstrapCheckout();

    // The dependencies are the step everything after it stands on, so a failed install is the end:
    // the verification is not run, and nothing says "done".
    $checkout->write('composer-exit', "1\n");
    $install = $checkout->script('bin/bootstrap.php');

    expect($install->exitCode)->toBe(1)
        ->and($install->error)->toContain('composer install failed')
        ->and($install->output)->not->toContain('done.')
        ->and(bootstrapJournal($checkout))->toBe(['composer install --no-interaction']);

    // The same for the last step: the setup succeeded and the clone is still not green, which is
    // the one answer this program exists not to give quietly.
    $checkout->delete('composer-exit');
    $checkout->delete('journal');
    $checkout->write('verify-exit', "1\n");
    $verify = $checkout->script('bin/bootstrap.php');

    expect($verify->exitCode)->toBe(1)
        ->and($verify->error)->toContain('reported a finding')
        ->and(bootstrapJournal($checkout))->toBe(['composer install --no-interaction', 'verify']);
});

it('prints the plan and changes nothing under --dry-run', function (): void {
    $checkout = bootstrapCheckout();
    $run = $checkout->script('bin/bootstrap.php', '--dry-run');

    expect($run->exitCode)->toBe(0)
        ->and($run->output)->toContain('render_local.py --init')
        ->and($run->output)->toContain('composer install')
        ->and($run->output)->toContain('python .agents/verify.py')
        ->and($run->output)->toContain('nothing was changed')
        ->and($checkout->exists('.agents/machine.local.json'))->toBeFalse()
        ->and(bootstrapJournal($checkout))->toBe([]);
});

it('leaves alone a clone that has already named this machine, unless --force', function (): void {
    $checkout = bootstrapCheckout();

    // First run: the machine file is written, as above.
    expect($checkout->script('bin/bootstrap.php', '--skip-install', '--skip-verify')->exitCode)->toBe(0)
        ->and($checkout->exists('.agents/machine.local.json'))->toBeTrue();

    // A second run is not a refusal - the rest of it is still worth doing - but the naming step is
    // skipped, because replacing a decision somebody made about this machine is not something a
    // setup command should do on its own.
    $checkout->write('.agents/machine.local.json', '{"php":{"exe":"somewhere else"}}'."\n");

    $again = $checkout->script('bin/bootstrap.php', '--skip-install', '--skip-verify');

    expect($again->exitCode)->toBe(0)
        ->and($again->output)->toContain('already here')
        ->and($checkout->read('.agents/machine.local.json'))->toContain('somewhere else');

    // `--force` is that decision, and it is the only way the file is written again.
    $forced = $checkout->script('bin/bootstrap.php', '--skip-install', '--skip-verify', '--force');

    expect($forced->exitCode)->toBe(0);

    bootstrapNamed($checkout);
});

it('sets up a clone whose dependencies are already in place, and one that leaves the verifying out', function (): void {
    $checkout = bootstrapCheckout();

    // `--skip-install` is the second run of the day and `--skip-verify` is "set it up now, verify
    // it later". Both are the same program with one step fewer, and neither step is faked: the
    // count the run reports is the count of what it was asked to do.
    $run = $checkout->script('bin/bootstrap.php', '--skip-install', '--skip-verify');

    expect($run->exitCode)->toBe(0)
        ->and($run->output)->toContain('1/1')
        ->and($run->output)->not->toContain('install the dependencies')
        ->and(bootstrapJournal($checkout))->toBe([])
        ->and($checkout->exists('.agents/machine.local.json'))->toBeTrue();

    $narrowed = $checkout->script('bin/bootstrap.php', '--skip-verify');

    expect($narrowed->exitCode)->toBe(0)
        ->and($narrowed->output)->toContain('2/2')
        ->and($narrowed->output)->not->toContain('run the whole verification')
        ->and(bootstrapJournal($checkout))->toBe(['composer install --no-interaction']);
});

it('says how it is used, and refuses an option it does not have', function (): void {
    $checkout = bootstrapCheckout();

    $help = $checkout->script('bin/bootstrap.php', '--help');

    expect($help->exitCode)->toBe(0)
        ->and($help->output)->toContain('Usage: php bin/bootstrap.php')
        ->and($help->output)->toContain('--skip-install')
        ->and($help->output)->toContain('--dry-run');

    // A usage error is its own code, so a script that runs this can tell "you typed it wrong" from
    // "this machine is not set up" - the same two codes every other program in `bin/` answers with.
    $unknown = $checkout->script('bin/bootstrap.php', '--nope');

    expect($unknown->exitCode)->toBe(2)
        ->and($unknown->error)->toContain('--nope is not an option this has')
        ->and($unknown->output)->toBe('');
});

it('runs this package\'s own program as a program, which is the half a fixture cannot see', function (): void {
    // The fixture above is a tree of its own; the file it copies is this one. It is asked here for
    // the only question a planted tree cannot answer - that the program in this checkout parses and
    // answers for itself - and `--dry-run` is what to ask, because a real run would install into the
    // checkout that is running the suite.
    $command = [PHP_BINARY, ReleaseRepo::package().'/bin/bootstrap.php', '--dry-run'];

    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        ReleaseRepo::package(),
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Could not run '.implode(' ', $command));
    }

    fclose($pipes[0]);
    $output = (string) stream_get_contents($pipes[1]);
    $error = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $run = new ReleaseRun(proc_close($process), $output, $error, implode(' ', $command));

    expect($run->exitCode)->toBe(0, $run->describe())
        ->and($run->output)->toContain('nothing was changed');
});
