<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\RepoEscapes;

/*
|--------------------------------------------------------------------------
| Paths that climb out of the repository
|--------------------------------------------------------------------------
|
| The two IDE tool entries named Pint and PHPStan by climbing out of the checkout, and nothing
| read that as wrong. It names no drive letter, no home directory and no share, so the
| machine-path guard had nothing to fire at; it is not a link, so the docs guard had nothing to
| resolve. The gap was that a relative path leaks a layout rather than a machine: it resolves
| wherever the checkout happens to sit, which is one place, and it reads as a path everywhere.
|
| So the paths are read out of the files a commit would carry and *walked*, from the depth of
| the file each one was written in — the climb is a finding only when the walk goes above the
| root, which is what lets the records keep their own links up one level. Three things are
| asserted rather than one: that nothing climbs out, that the detector fires at all, and that
| the two files allowed to hold samples still need to.
|
*/

it('reports no path that climbs out of the repository', function (): void {
    $scan = RepoEscapes::scan(packageRoot());

    $reported = array_map(
        static fn (array $hit): string => sprintf('%s:%d  %s', $hit['file'], $hit['line'], $hit['path']),
        $scan['found'],
    );

    // A scan that read nothing agrees with a repository that leaked nothing, so the listing is
    // checked for substance before the findings are: the files the samples live in have to be
    // in it, which is only true of a listing that came from the real working tree.
    expect(array_diff(array_keys(RepoEscapes::EXEMPT), $scan['files']))->toBe([])
        ->and($reported)->toBe([]);
});

it('still needs every file it exempts', function (): void {
    $scan = RepoEscapes::scan(packageRoot());

    $exempt = array_values(array_unique(array_column($scan['exempt'], 'file')));
    $declared = array_keys(RepoEscapes::EXEMPT);

    sort($exempt);
    sort($declared);

    // Each exemption carries the reason it was granted, and a reason is only worth writing down
    // once it can be checked: a file that no longer holds a sample is reported with the reason
    // it was exempted for, which is the one that gets removed.
    $stale = [];

    foreach (RepoEscapes::EXEMPT as $file => $reason) {
        if (! in_array($file, $exempt, true)) {
            $stale[] = sprintf('%s (%s)', $file, $reason);
        }
    }

    expect($stale)->toBe([])
        ->and($exempt)->toBe($declared);
});

it('reports the paths that climb above the root', function (string $text, int $depth, array $expected): void {
    expect(array_column(RepoEscapes::in($text, $depth), 'path'))->toBe($expected);
})->with([
    'a climb written at the root' => ['../sibling/notes.md', 0, ['../sibling/notes.md']],
    'a record climbing one level too far' => ['[config](../../config.php)', 1, ['../../config.php']],
    'the marker is the root, so it leaves at once' => ['$PROJECT_DIR$/../../application/vendor/bin/pint.bat', 2, ['$PROJECT_DIR$/../../application/vendor/bin/pint.bat']],
    'the same climb, back-slashed' => ['..\\..\\..\\tools', 2, ['..\\..\\..\\tools']],
    'a climb written as a PHP string' => ["__DIR__ . '/../../..'", 2, ['/../../..']],
    'a climb computed from the file directory' => ['dirname(__DIR__, 3)', 2, ['dirname(__DIR__, 3)']],
    'a climb through a name and back out' => ['config/../..', 0, ['config/../..']],
]);

it('leaves the paths that stay inside alone', function (string $text, int $depth): void {
    expect(RepoEscapes::in($text, $depth))->toBe([]);
})->with([
    'a record linking the source it describes' => ['[Config](../src/Support/Config.php)', 1],
    'two levels up from a reading is the root' => ['../../src/Support/Config.php', 2],
    'a climb walked back before it leaves' => ['docs/../tests/x.php', 1],
    'the config path the service provider builds' => ["sprintf('%s/../config/%s.php', __DIR__)", 1],
    'a computed climb that lands on the root' => ['dirname(__DIR__, 2)', 2],
    'a computed climb of one level' => ['dirname(__DIR__, 1)', 2],
    'the directory of the file itself' => ['dirname(__DIR__)', 1],
    'a Windows directory, which is the machine-path question' => ['core.sshCommand = C:/Windows/System32/OpenSSH/ssh.exe', 0],
    'a CI runner' => ['cd /home/runner/work/pkg/pkg', 0],
    'a URL whose path climbs' => ['see https://example.test/a/../..', 0],
    'an ellipsis standing for a directory' => ['the value is `C:/Windows/...`', 0],
    'a path with no climb in it' => ['vendor/pestphp/pest/bin/pest', 2],
    'a namespace in PHP source' => ['namespace Uak35\\ResponseCompression\\Tests;', 2],
    'an escaped namespace in JSON' => ['"Uak35\\\\ResponseCompression\\\\": "src/"', 2],
]);

it('says which line each path is on', function (): void {
    // The report a failure prints is `file:line`, so a climb found in the wrong place would
    // send the reader to the wrong line of a file they are already looking at.
    expect(RepoEscapes::in("cd ../..\n", 1))->toBe([['line' => 1, 'path' => '../..']])
        ->and(RepoEscapes::in("first\nsecond ../../tools\n", 1))
        ->toBe([['line' => 2, 'path' => '../../tools']])
        ->and(RepoEscapes::in("first\r\nsecond \$PROJECT_DIR\$/../..\r\n", 0))
        ->toBe([['line' => 2, 'path' => '$PROJECT_DIR$/../..']]);
});
