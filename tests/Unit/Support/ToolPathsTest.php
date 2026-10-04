<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\ToolPaths;

/*
|--------------------------------------------------------------------------
| The one manifest of tool paths
|--------------------------------------------------------------------------
|
| The same four tools used to be named twice — once in `composer.json`, because a script is a
| shell string, and once in `bin/checks.php`, which cannot be asked for its list because it runs on
| include. The two copies disagreed, and the difference was invisible in the path itself:
| `vendor/bin/pest` and `vendor/pestphp/pest/bin/pest` both read as Pest.
|
| It was not invisible in what it did. On Windows the shim is a `.bat` that runs whichever `php`
| is first on `PATH` — a second interpreter, with a second set of extensions — and
| `composer test:unit` reported that no coverage driver was available on the machine where the
| gate's own Pest run covered 100.0%.
|
| So every path lives in `bin/tool-paths.php`, and what is asserted here is what can go wrong with
| one copy rather than with two: a path written down again in a script or a program, a path that
| resolves nowhere or to a shim, and a name that nothing has or nothing uses.
|
| An entry carries two more facts than a path — the version the package pins the tool at, and what
| the tool is for — and they are held the same way. The pin is a statement about the tool this
| repository pins itself to, so it is read against `composer.json`'s `require-dev` and against
| what `vendor/composer/installed.json` says is here: a pin that disagrees with either is the gate
| measuring a different tool than the one it names, which is the shim failure one level up and just
| as invisible in a path. `why` is why the gate can print what each tool is for without saying it
| twice.
|
| WHY THE RUNNER IS READ TOO
| --------------------------
| A tool can be invoked in three places: a composer script, one of the three programs this package
| keeps in `bin/`, and `.github/workflows/main.yml`. The third had no reader, and it is the one that
| decides what a published version was gated by: CI that dropped `composer checks`, or ran it
| without `--require-all`, is a build weaker than the gate a tag is cut behind — and weaker in a
| file nobody compares with a local run. So the workflow is parsed rather than searched: what the
| runner runs is held to `ToolPaths::CI`, and no tool's path may be written in it either.
|
*/

/**
 * @guards-index guard the tool-path test
 *
 * a tool's path written down in a second place — in a composer script, or in one of the three programs that runs a tool — a `vendor/bin` shim, which is a second interpreter, a path that is not on disk, a name nothing asks for, an entry nothing runs, and the two facts an entry carries beside the path: a pin that is not the version `composer.json` requires or not the one installed, and an entry with no line saying what its tool is for; and the runner — `.github/workflows/main.yml`, the third place a tool can be invoked and the one that had no reader — held to the gate it has to run (`composer checks -- --require-all` and `composer test:unit`, with a shell comment in a step no substitute for the command) and to the manifest for a tool path written into it
 */

/** The manifest this package ships. */
function toolManifest(): array
{
    return ToolPaths::manifest(packageRoot().'/'.ToolPaths::MANIFEST);
}

/**
 * An entry as the manifest writes one, for a fixture that is about something else.
 *
 * @return array{path: string, pin: string, why: string}
 */
function toolEntry(string $path, string $pin = '1.0.0', string $why = 'a fixture'): array
{
    return ['path' => $path, 'pin' => $pin, 'why' => $why];
}

/** Where Composer records what it installed, which is the other half. */
function toolInstalledFile(): string
{
    return packageRoot().'/vendor/composer/installed.json';
}

/** Every command the package's scripts run. */
function toolScripts(): array
{
    return ToolPaths::scriptsIn((string) file_get_contents(packageRoot().'/composer.json'));
}

/** The workflow that runs this gate on a runner, which the reader parses rather than searches. */
function toolWorkflow(): string
{
    return (string) file_get_contents(packageRoot().'/'.ToolPaths::WORKFLOW);
}

it('keeps every tool path in the manifest, and in no script or program', function (): void {
    // Both readers have to have found something before their clean answer means anything: a reader
    // that lost the scripts block, or a manifest that came back empty, would pass this guard while
    // broken — and its green tick would look exactly the same either way.
    expect(toolManifest())->not->toBeEmpty()
        ->and(toolScripts())->not->toBeEmpty()

        // The reading itself, as one call: a script that runs a tool by path or through the
        // `vendor/bin` shim instead of naming one, a program in `bin/` that writes the path down
        // again, and the runner — the third place a tool can be invoked, which had no reader at
        // all. It is a method on `ToolPaths` rather than a walk written out here, so the mutation
        // harness asks the same call with a copy planted in a tree.
        ->and(ToolPaths::copies(packageRoot()))->toBe([]);
});

it('holds the runner to the gate it has to be, rather than to a list of the gate\'s tools', function (): void {
    $steps = ToolPaths::stepsIn(toolWorkflow());

    // Both halves have to have found something: a workflow that came back as no steps, or a gate
    // with nothing in it to look for, would pass this guard while reading nothing at all.
    expect($steps)->not->toBeEmpty()
        ->and(ToolPaths::gateGaps($steps))->toBe([]);

    // Each way the runner can stop being the gate, on a workflow of its own — the real file is the
    // gate, so a reading that looked at none of them would be green for the same reason.
    expect(ToolPaths::gateGaps([['step' => 'the floor', 'command' => 'composer test:unit']]))
        ->toBe(['the runner never runs `composer checks`, so what it gates is weaker than the gate a tag is cut behind'])

        // Two gaps, in the order the requirement is stated: the argument is missing, and so is the
        // other half of the gate.
        ->and(ToolPaths::gateGaps([['step' => 'the gate', 'command' => 'composer checks']]))
        ->toBe([
            'the runner runs `composer checks` without `--require-all`, so a check that skipped reads as a green build',
            'the runner never runs `composer test:unit`, so what it gates is weaker than the gate a tag is cut behind',
        ])

        ->and(ToolPaths::gateGaps([['step' => 'the gate', 'command' => 'composer checks -- --require-all']]))
        ->toBe(['the runner never runs `composer test:unit`, so what it gates is weaker than the gate a tag is cut behind']);

    // A note is not a command. The workflow explains itself beside its commands, so a step that only
    // names the gate in a shell comment has not run it — which is the one way a text search would
    // have answered yes here.
    $commented = ToolPaths::stepsIn(<<<'YAML'
        jobs:
          build:
            steps:
            - name: Explain the gate
              run: |
                # composer checks -- --require-all
                # composer test:unit
                echo "nothing is run here"
        YAML);

    expect($commented[0]['command'])
        ->not->toContain('composer checks')
        ->and(ToolPaths::gateGaps($commented))->toHaveCount(2);
});

it('refuses a workflow it could not read, rather than agreeing with one', function (): void {
    // Each of these is a way this reading could answer "nothing is wrong" for a file it never
    // understood — on the runner that answer is a green build.
    expect(fn (): array => ToolPaths::stepsIn("on: [push\n"))
        ->toThrow(RuntimeException::class, 'is not YAML')

        ->and(fn (): array => ToolPaths::stepsIn("name: PHP Composer\non: [push]\n"))
        ->toThrow(RuntimeException::class, 'no `jobs`')

        ->and(fn (): array => ToolPaths::stepsIn("jobs:\n  build:\n    steps:\n    - uses: actions/checkout@v4\n"))
        ->toThrow(RuntimeException::class, 'no step that runs a command');
});

it('names a file inside vendor/ that is on disk, and never a vendor/bin shim', function (): void {
    expect(ToolPaths::problems(toolManifest(), packageRoot()))->toBe([]);

    // Each rule on a fixture of its own, because the real manifest passes all of them and a reader
    // that found nothing would pass it too. Two of them are about the path and two about the facts
    // beside it, and the two beside it are what an entry gained: a pin is the version itself rather
    // than a range (a range is what `composer.json` already says), and a tool with no line saying
    // what it is for is a tool the gate would list with nothing to say about it.
    $problems = implode("\n", ToolPaths::problems([
        'pest' => toolEntry('vendor/bin/pest'),
        'pint' => toolEntry('node_modules/pint'),
        'rector' => toolEntry('vendor/rector/rector/bin/nope.php'),
        'phpstan' => toolEntry('vendor/phpstan/phpstan/phpstan.phar', '^2.2'),
        'yaml-lint' => toolEntry('vendor/symfony/yaml/Resources/bin/yaml-lint', '8.1.6', '   '),
    ], packageRoot()));

    expect($problems)
        ->toContain('pest names the vendor/bin/pest shim, which runs whichever php is first on PATH')
        ->toContain('pint names node_modules/pint, which is not a file inside vendor/')
        ->toContain('rector names vendor/rector/rector/bin/nope.php, which is not there')
        ->toContain('phpstan pins phpstan/phpstan at ^2.2, which is not the version itself')
        ->toContain('bin/tool-paths.php does not say what yaml-lint is for');
});

it('has a name for every tool something asks for, and no name nothing runs', function (): void {
    // Every asker has to have been read: the scripts, the gate's `$tools['…']` lookups, and the one
    // `bin/coverage.php` makes. A name that is missing reads as "not installed" at runtime, which is
    // a skip in the summary that looks like a machine without the tool rather than a typo here.
    //
    // The walk lives in `ToolPaths::asked()` rather than here, so the test and the mutation harness
    // ask the same call: a name something asks for that the manifest does not have is the break it
    // has to name, and both read the scripts and the programs through one definition.
    expect(ToolPaths::asked(packageRoot()))->not->toBeEmpty()
        ->and(ToolPaths::unsharedNames(packageRoot()))->toBe([]);
});

it('reports a name nobody has, and an entry nothing runs, in the words of what asked', function (): void {
    expect(ToolPaths::unshared([
        'pint' => toolEntry('vendor/laravel/pint/builds/pint'),
        'yaml-lint' => toolEntry('vendor/symfony/yaml/Resources/bin/yaml-lint'),
    ], [
        ['from' => 'the lint script', 'tool' => 'pint'],
        ['from' => 'bin/checks.php', 'tool' => 'phpstan'],
    ]))->toBe([
        'bin/checks.php asks for phpstan, which bin/tool-paths.php does not name',
        'bin/tool-paths.php names yaml-lint, which nothing runs',
    ]);
});

it('reads a call and a name, and refuses to report agreement with a file it could not read', function (): void {
    // The list form, a script that runs another script, and the programs this repository keeps in
    // `bin/` — every one of them read, because the rule is now about which of them may name a path.
    $composer = <<<'JSON'
    {
      "scripts": {
        "checks": "@php bin/checks.php",
        "lint": "@php bin/tool.php pint",
        "test:types": "@php bin/tool.php phpstan analyse --ansi",
        "tidy": ["@lint", "@refactor", "@lint"],
        "release": "@php bin/release.php"
      }
    }
    JSON;

    expect(ToolPaths::scriptsIn($composer))->toBe([
        ['script' => 'checks', 'path' => 'bin/checks.php', 'arguments' => ''],
        ['script' => 'lint', 'path' => 'bin/tool.php', 'arguments' => 'pint'],
        ['script' => 'test:types', 'path' => 'bin/tool.php', 'arguments' => 'phpstan analyse --ansi'],
        ['script' => 'release', 'path' => 'bin/release.php', 'arguments' => ''],
    ])

        // A name is read where it is used and nowhere else: `$tools['pest']` is a name this package
        // asks the manifest for, and the same index on another array is not.
        ->and(ToolPaths::namesIn("<?php\n\n\$tools['pest'] ?? '';\n\$paths['pest'];\n"))->toBe(['pest'])

        // A reader that returns nothing agrees with everything, so each of these is a failure rather
        // than a clean answer — and each names what it could not find rather than what it wanted.
        ->and(fn (): array => ToolPaths::manifest(packageRoot().'/'.ToolPaths::MANIFEST.'.gone'))
        ->toThrow(RuntimeException::class, 'is not there')
        ->and(fn (): array => ToolPaths::scriptsIn('{"name": "uak35/laravel-response-compression"}'))
        ->toThrow(RuntimeException::class, 'no `scripts` block');
});

it('takes the package a tool lives in out of the path, and reads no version of its own', function (): void {
    // The package is derived rather than carried, because an entry that named it a second time
    // would be one more copy of what the path already says — and it is the name the pin is looked
    // up by on both sides.
    expect(ToolPaths::package('vendor/pestphp/pest/bin/pest'))->toBe('pestphp/pest')
        ->and(ToolPaths::package('vendor/symfony/yaml/Resources/bin/yaml-lint'))->toBe('symfony/yaml')
        ->and(ToolPaths::package('/vendor/rector/rector/bin/rector'))->toBe('rector/rector')

        // Composer writes some versions with the `v` their tag carries and some without, and
        // `composer.json` is written without it. One spelling is the pin's, and it is this one —
        // so the real record is read rather than a fixture of it, which is the half a fixture
        // could not have told us was being normalised.
        ->and(ToolPaths::installed(toolInstalledFile())['pestphp/pest'])->toBe('4.7.8')
        ->and(ToolPaths::installed(toolInstalledFile())['laravel/pint'])->toBe('1.32.1')

        // A record with no packages in it is not a tree with nothing installed: it is a reader
        // that found nothing, and it says so rather than agreeing with everything.
        ->and(fn (): array => ToolPaths::installed(packageRoot().'/'.ToolPaths::MANIFEST.'.gone'))
        ->toThrow(RuntimeException::class, 'is not there');
});

it('holds every pin to what composer.json requires and to what is installed here', function (): void {
    // Both halves have to have found something before their agreement means anything: a
    // `require-dev` that came back empty, or a record with no packages in it, would let the
    // comparison below pass for a tree where nothing was compared.
    //
    // The walk lives in `ToolPaths::pinDrift()` rather than here, so the reading has one definition
    // and the test asks for it rather than composing it: the manifest, what `composer.json` requires
    // and what Composer recorded installing are gathered in one place. The harness does not mutate
    // it — half of what it reads is `vendor/composer/installed.json`, which a planted tree never
    // carries — so this test, against the real record, is what holds the pin half.
    expect(ToolPaths::required((string) file_get_contents(packageRoot().'/composer.json')))->not->toBeEmpty()
        ->and(ToolPaths::installed(toolInstalledFile()))->not->toBeEmpty()
        ->and(ToolPaths::pinDrift(packageRoot()))->toBe([]);

    // And each way it can disagree, on a fixture of its own, because a tree where all three agree
    // passes whether or not the reading looks at any of them.
    $fixture = ['pest' => toolEntry('vendor/pestphp/pest/bin/pest', '4.7.8', 'the suite')];

    expect(ToolPaths::drift($fixture, ['pestphp/pest' => '4.7.7'], ['pestphp/pest' => '4.7.8']))
        ->toBe(['composer.json requires pestphp/pest at 4.7.7, and bin/tool-paths.php pins pest at 4.7.8'])
        ->and(ToolPaths::drift($fixture, ['pestphp/pest' => '4.7.8'], ['pestphp/pest' => '4.7.9']))
        ->toBe(['bin/tool-paths.php pins pest at 4.7.8, and 4.7.9 is installed — a tool that moved measures something else'])
        ->and(ToolPaths::drift($fixture, ['pestphp/pest' => '4.7.8'], ['phpstan/phpstan' => '2.2.16']))
        ->toBe(['bin/tool-paths.php pins pest at 4.7.8, and no version of pestphp/pest is installed here'])
        ->and(ToolPaths::drift($fixture, ['phpstan/phpstan' => '2.2.16'], ['pestphp/pest' => '4.7.8']))
        ->toBe(['bin/tool-paths.php runs pestphp/pest, which composer.json does not require at a version']);
});

it('refuses an entry that is not a record with all three facts in it', function (): void {
    // The shape is the half of this a caller cannot invent: `bin/checks.php` and `bin/tool.php`
    // read the path out of an entry, and a manifest that answered with a bare string would leave
    // them reading a character of a path. Each fixture below is a file on disk because the manifest
    // is required rather than parsed, which is the reason it is a file of its own.
    $file = sys_get_temp_dir().'/rc-manifest-'.bin2hex(random_bytes(6)).'.php';

    foreach ([
        "<?php\n\nreturn ['pest' => 'vendor/pestphp/pest/bin/pest'];\n" => 'missing: path, pin, why',
        "<?php\n\nreturn ['pest' => ['path' => 'vendor/pestphp/pest/bin/pest', 'pin' => '4.7.8']];\n" => 'missing: why',
        "<?php\n\nreturn ['pest' => ['path' => 'vendor/pestphp/pest/bin/pest', 'pin' => '', 'why' => 'the suite']];\n" => 'missing: pin',
    ] as $source => $expected) {
        file_put_contents($file, $source);

        expect(fn (): array => ToolPaths::manifest($file))
            ->toThrow(RuntimeException::class, $expected);
    }

    @unlink($file);
});
