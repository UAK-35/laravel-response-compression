<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Every tool this package runs: the file that runs it, the version it is
| pinned at, and what it is for
|--------------------------------------------------------------------------
|
| The one place a tool is written down. `composer.json`'s scripts, `bin/checks.php`,
| `bin/coverage.php` and `bin/tool.php` all run tools and none of them writes a path: a script
| names `bin/tool.php` and a tool, the gate asks for the tool it needs, and this file answers.
|
| The paths used to be written twice — once in `composer.json`, because a script is a shell
| string and cannot read a manifest, and once in `bin/checks.php`, which cannot be asked for its
| list because it runs on include — and the two copies drifted. The scripts named their tools
| through `vendor/bin` while the gate named the file inside `vendor/`, and the difference is
| invisible in the path: `vendor/bin/pest` and `vendor/pestphp/pest/bin/pest` both read as Pest.
| It was not invisible in what it did. On Windows the shim is a `.bat` that runs whichever `php`
| is first on `PATH` — a second interpreter, with a second set of extensions — which is how
| `composer test:unit` came back with no coverage driver available on the machine where the
| gate's own Pest run covered 100.0%.
|
| A path is not the whole of what a caller needs to know about a tool, so an entry is a short
| record rather than a string:
|
|   - `path` — the entry file inside `vendor/`, never a `vendor/bin` shim, because `@php` is
|     already the interpreter the dependencies were installed under. It is on disk, so a wrong
|     path fails in the tool's own words rather than reading as a check that was skipped.
|   - `pin` — the exact version this package asks for, which is the same version
|     `composer.json`'s `require-dev` states. The tool is what the gate measures this repository
|     with, so a tool that moved is a different measurement: a pin that no longer matches what is
|     installed is a finding, not a note, and `composer update` cannot quietly move one.
|   - `why` — what the tool is for, in one line. It is read rather than restated: `bin/checks.php
|     --list` prints this column beside the check each tool runs, so the gate describes itself
|     out of this file.
|
| One rule for an entry with all three, held by `tests/Unit/Support/ToolPathsTest.php`: the pin
| is the version installed, and the version `composer.json` requires — the package that a
| `path` points into is derived from the path itself, because a path inside `vendor/acme/thing/`
| is inside `acme/thing` and saying so a second time is a copy that can drift. That is what
| makes the pin one fact rather than three.
|
| The name on the left is the word everything else says it by: a composer script passes it to
| `bin/tool.php`, the gate asks for it, and a skip in the summary names it.
*/

return [
    'pest' => [
        'path' => 'vendor/pestphp/pest/bin/pest',
        'pin' => '4.7.8',
        'why' => 'the suite — run without a coverage driver, so the gate works on a machine that has none',
    ],
    'phpstan' => [
        'path' => 'vendor/phpstan/phpstan/phpstan.phar',
        'pin' => '2.2.16',
        'why' => 'static analysis, at the level phpstan.neon.dist asks for',
    ],
    'pint' => [
        'path' => 'vendor/laravel/pint/builds/pint',
        'pin' => '1.32.1',
        'why' => 'code style, in test mode',
    ],
    'rector' => [
        'path' => 'vendor/rector/rector/bin/rector',
        'pin' => '2.6.7',
        'why' => 'the refactorings Rector would make, as a dry run',
    ],
    'yaml-lint' => [
        'path' => 'vendor/symfony/yaml/Resources/bin/yaml-lint',
        'pin' => '8.1.6',
        'why' => 'the workflow YAML, which nothing else here reads',
    ],
];
