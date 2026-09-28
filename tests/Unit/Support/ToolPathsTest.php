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
*/

/** The package's own programs that run a tool, as path => its text. */
function toolReaders(): array
{
    $sources = [];

    foreach (ToolPaths::READERS as $reader) {
        $sources[$reader] = (string) file_get_contents(packageRoot().'/'.$reader);
    }

    return $sources;
}

/** The manifest this package ships. */
function toolManifest(): array
{
    return ToolPaths::manifest(packageRoot().'/'.ToolPaths::MANIFEST);
}

/** Every command the package's scripts run. */
function toolScripts(): array
{
    return ToolPaths::scriptsIn((string) file_get_contents(packageRoot().'/composer.json'));
}

/**
 * Every tool something in this package asks the manifest for, and what asked: a script, which hands
 * the name to `bin/tool.php`, or one of the programs, which looks the name up itself.
 */
function toolsAsked(): array
{
    $asked = [];

    foreach (toolScripts() as $script) {
        if ($script['path'] !== 'bin/tool.php') {
            continue;
        }

        // The first word after the program is the tool; a script that names none is asked for with
        // an empty name, which is a name the manifest does not have and so a finding.
        $asked[] = ['from' => 'the '.$script['script'].' script', 'tool' => (string) strtok($script['arguments'], ' ')];
    }

    foreach (toolReaders() as $reader => $source) {
        foreach (ToolPaths::namesIn($source) as $name) {
            $asked[] = ['from' => $reader, 'tool' => $name];
        }
    }

    return $asked;
}

it('keeps every tool path in the manifest, and in no script or program', function (): void {
    $manifest = toolManifest();
    $scripts = toolScripts();

    // Both readers have to have found something before their clean answers mean anything: a reader
    // that lost the scripts block, or a manifest that came back empty, would pass this guard while
    // broken — and its green tick would look exactly the same either way.
    expect($manifest)->not->toBeEmpty()
        ->and($scripts)->not->toBeEmpty()

        // The defect one layer up: a script that runs a tool by path instead of naming one. There is
        // one form a script may use — `@php bin/tool.php <tool>` — and everything else is a path.
        ->and(ToolPaths::foreignScripts($scripts))->toBe([])

        // And the copy nothing can see any more: a program that writes the path down again, or
        // reaches for the `vendor/bin` shim of a name the manifest already holds.
        ->and(ToolPaths::secondCopies($manifest, toolReaders()))->toBe([]);
});

it('names a file inside vendor/ that is on disk, and never a vendor/bin shim', function (): void {
    expect(ToolPaths::problems(toolManifest(), packageRoot()))->toBe([]);

    // Each rule on a fixture of its own, because the real manifest passes all of them and a reader
    // that found nothing would pass it too.
    $problems = implode("\n", ToolPaths::problems([
        'pest' => 'vendor/bin/pest',
        'pint' => 'node_modules/pint',
        'rector' => 'vendor/rector/rector/bin/nope.php',
    ], packageRoot()));

    expect($problems)
        ->toContain('pest names the vendor/bin/pest shim, which runs whichever php is first on PATH')
        ->toContain('pint names node_modules/pint, which is not a file inside vendor/')
        ->toContain('rector names vendor/rector/rector/bin/nope.php, which is not there');
});

it('has a name for every tool something asks for, and no name nothing runs', function (): void {
    $asked = toolsAsked();

    // Every asker has to have been read: the scripts, the gate's `$tools['…']` lookups, and the one
    // `bin/coverage.php` makes. A name that is missing reads as "not installed" at runtime, which is
    // a skip in the summary that looks like a machine without the tool rather than a typo here.
    expect($asked)->not->toBeEmpty()
        ->and(ToolPaths::unshared(toolManifest(), $asked))->toBe([]);
});

it('reports a name nobody has, and an entry nothing runs, in the words of what asked', function (): void {
    expect(ToolPaths::unshared([
        'pint' => 'vendor/laravel/pint/builds/pint',
        'yaml-lint' => 'vendor/symfony/yaml/Resources/bin/yaml-lint',
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
