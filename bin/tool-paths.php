<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Every tool this package runs, and the file inside `vendor/` that runs it
|--------------------------------------------------------------------------
|
| The one place a tool's path is written down. `composer.json`'s scripts, `bin/checks.php`,
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
| Two rules for an entry, both held by `tests/Unit/Support/ToolPathsTest.php`:
|
|   - the path is the entry file inside `vendor/`, never a `vendor/bin` shim, because `@php` is
|     already the interpreter the dependencies were installed under;
|   - the file is on disk, so a wrong path fails in the tool's own words rather than reading as a
|     check that was skipped.
|
| The name on the left is the word everything else says it by: a composer script passes it to
| `bin/tool.php`, the gate asks for it, and a skip in the summary names it.
*/

return [
    'pest' => 'vendor/pestphp/pest/bin/pest',
    'phpstan' => 'vendor/phpstan/phpstan/phpstan.phar',
    'pint' => 'vendor/laravel/pint/builds/pint',
    'rector' => 'vendor/rector/rector/bin/rector',
    'yaml-lint' => 'vendor/symfony/yaml/Resources/bin/yaml-lint',
];
