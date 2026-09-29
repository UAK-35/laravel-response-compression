<?php

declare(strict_types=1);

use Uak35\ResponseCompression\Tests\Support\MachinePaths;

/*
|--------------------------------------------------------------------------
| Paths that only resolve on one machine
|--------------------------------------------------------------------------
|
| PUSHING.md went into this repository — which is public — carrying two of them: a
| `C:/Users/<name>/.gitconfig` in a table of evidence, and a `cd` line in its push recipe that
| named a whole workspace on the author's disk. Neither is a credential and neither breaks a
| build, so neither would have been caught by reading a diff; a path that works where it was
| written reads like a path. What they cost is the reader, and the fix was a hand edit that
| could come back at any time.
|
| So the paths are read out of the files a commit would carry, with the ones that name
| nobody — `C:/Windows/...`, `/home/runner/...` — named in the detector rather than left to
| the eye. Three things are asserted rather than one: that nothing leaks, that the detector
| fires at all, and that the two files allowed to hold samples still need to.
|
*/

it('reports no machine-local path in the files a commit would carry', function (): void {
    $scan = MachinePaths::scan(packageRoot());

    $reported = array_map(
        static fn (array $hit): string => sprintf('%s:%d  %s', $hit['file'], $hit['line'], $hit['path']),
        $scan['found'],
    );

    // A scan that read nothing agrees with a repository that leaked nothing, so the listing is
    // checked for substance before the findings are: the files the samples live in have to be
    // in it, which is only true of a listing that came from the real working tree.
    expect(array_diff(array_keys(MachinePaths::EXEMPT), $scan['files']))->toBe([])
        ->and($reported)->toBe([]);
});

it('still needs every file it exempts', function (): void {
    $scan = MachinePaths::scan(packageRoot());

    $exempt = array_values(array_unique(array_column($scan['exempt'], 'file')));
    $declared = array_keys(MachinePaths::EXEMPT);

    sort($exempt);
    sort($declared);

    // Each exemption carries the reason it was granted, and a reason is only worth writing down
    // once it can be checked: a file that no longer holds a sample is reported with the reason
    // it was exempted for, which is the one that gets removed.
    $stale = [];

    foreach (MachinePaths::EXEMPT as $file => $reason) {
        if (! in_array($file, $exempt, true)) {
            $stale[] = sprintf('%s (%s)', $file, $reason);
        }
    }

    // An exemption is a file the guard is not allowed to report, which is a hole in it unless
    // the file still holds something the guard would otherwise fire at. Move the samples and
    // this fails, so the exemption cannot outlive the reason written beside it — and a third
    // file cannot hide behind an exemption it never asked for, which is the second assertion.
    expect($stale)->toBe([])
        ->and($exempt)->toBe($declared);
});

it('reports the paths that name a machine', function (string $text, array $expected): void {
    expect(array_column(MachinePaths::in($text), 'path'))->toBe($expected);
})->with([
    'a drive letter' => ['cd C:/Users/umar/project', ['C:/Users/umar/project']],
    'a back-slashed drive letter' => ['cd C:\Users\umar\project', ['C:\Users\umar\project']],
    'a Linux home directory' => ['cp /home/umar/.gitconfig .', ['/home/umar/.gitconfig']],
    'a macOS home directory' => ['cp /Users/umar/.gitconfig .', ['/Users/umar/.gitconfig']],
    'a network share' => ['cat \\\\fileserver\share\notes', ['\\\\fileserver\share\notes']],
]);

it('leaves the paths that name nobody alone', function (string $text): void {
    expect(MachinePaths::in($text))->toBe([]);
})->with([
    // A scheme is not a drive letter: the `s` of `https` is preceded by a letter, which is the
    // whole reason the drive pattern has a lookbehind.
    'an https URL' => ['see https://example.test/docs'],
    'a URL whose path starts with a home directory' => ['see https://example.test/home/umar'],
    'the Windows directory' => ['core.sshCommand = C:/Windows/System32/OpenSSH/ssh.exe'],
    'a back-slashed Windows directory' => ['core.sshCommand = C:\Windows\System32\OpenSSH\ssh.exe'],
    'a CI runner' => ['cd /home/runner/work/pkg/pkg'],
    'a tilde, which is how a home directory is written portably' => ['cp ~/.gitconfig .'],
    'a path that is absolute and belongs to nobody' => ['/usr/local/bin/php'],
    'a namespace in PHP source' => ['namespace Uak35\ResponseCompression\Tests;'],
    'an escaped namespace in JSON' => ['"Uak35\\\\ResponseCompression\\\\": "src/"'],
]);

it('says which line each path is on', function (): void {
    // The report a failure prints is `file:line`, so a path found in the wrong place would send
    // the reader to the wrong line of a file they are already looking at.
    expect(MachinePaths::in("cd /home/umar/pkg\n"))->toBe([['line' => 1, 'path' => '/home/umar/pkg']])
        ->and(MachinePaths::in("first\nsecond /home/umar/pkg\n"))->toBe([['line' => 2, 'path' => '/home/umar/pkg']])
        ->and(MachinePaths::in("first\r\nsecond C:\\Users\\umar\r\n"))->toBe([['line' => 2, 'path' => 'C:\Users\umar']]);
});
