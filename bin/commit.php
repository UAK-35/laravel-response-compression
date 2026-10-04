#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * bin/commit.php — a commit of exactly the paths it was told about.
 *
 * WHY THIS EXISTS
 * ---------------
 * A commit of a named set has three jobs that a plain `git commit -- <paths>` does not do, and the
 * repository has paid for each of them at least once:
 *
 *   - It carries the file it was told about and **nothing else**. The index is shared state: a
 *     colleague, an IDE, or an earlier command can have something staged, and `git commit` publishes
 *     the whole index. So the first thing this does is read the index and refuse when it holds a
 *     path outside the named set — because a commit that quietly carries a stranger's work is the
 *     failure a named set is supposed to prevent.
 *   - It writes the **changelog bullet** with the change, under `## Unreleased`, in the section the
 *     change belongs to. A changelog entry written later is a changelog entry written from memory.
 *   - It runs the **staged checks** — `bin/checks.php --staged`, the same suite the pre-commit hook
 *     runs — and does not commit when they fail. So the verdict is attributed to a command rather
 *     than to a hook the committer may not have enabled.
 *
 * TAKING THE WORKING-TREE CONTENT
 * -------------------------------
 * A path is staged from the filesystem, not from the index, so a file staged and then written again
 * commits what is on disk rather than the older copy that was staged. `tests/Unit/Gate/CommitTest.php`
 * holds that, because it is the failure `git add` followed by an edit and then `git commit` produces.
 *
 * EXIT STATUS
 * -----------
 *   0   the commit was made, or a `--dry-run`/`--list` reported a plan and wrote nothing
 *   1   nothing was committed: a named path was refused, the index carried a path outside the set,
 *       the changelog could not be written, or the staged checks failed
 *   2   the command line itself was wrong
 */

/**
 * @guards-index guard `bin/commit.php`
 *
 * a commit that carries a file it was not told about: the index is read before anything is staged and the commit refused when it holds a path outside the named set — and the changelog bullet is written and the staged checks run before the commit exists
 */
$root = dirname(__DIR__);

$options = [
    'message' => '',
    'message-file' => '',
    'paths-from-file' => '',
    'changelog' => '',
    'section' => 'Changed',
    'no-changelog' => false,
    'skip-checks' => false,
    'dry-run' => false,
    'list' => false,
    'help' => false,
    'names' => [],
];

/** The three sections a bullet may be written into, spelled as the changelog spells them. */
const SECTIONS = ['added' => 'Added', 'changed' => 'Changed', 'fixed' => 'Fixed'];

const CHANGELOG = 'CHANGELOG.md';

/**
 * Run a command in the repository root and answer its exit code and both streams.
 *
 * stdin is closed rather than inherited: a child of this program reads none, and leaving descriptor
 * 0 open hands it whatever handle a parallel test runner gave this process.
 *
 * @param  list<string>  $command
 * @return array{exit: int, output: string}
 */
function runCommand(array $command, string $root): array
{
    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root,
    );

    if (! is_resource($process)) {
        return ['exit' => 127, 'output' => 'could not start '.implode(' ', $command)];
    }

    fclose($pipes[0]);
    $output = (string) stream_get_contents($pipes[1]);
    $error = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['exit' => proc_close($process), 'output' => $output.$error];
}

/**
 * @return array{exit: int, output: string}
 */
function git(string $root, string ...$arguments): array
{
    $command = ['git', '-C', $root];

    foreach ($arguments as $argument) {
        $command[] = $argument;
    }

    return runCommand($command, $root);
}

/**
 * The paths the index carries, relative to the root and forward-slashed, as git reports them.
 *
 * @return list<string>
 */
function staged(string $root): array
{
    $result = git($root, 'diff', '--cached', '--name-only', '--diff-filter=ACMRD', '-z');

    if ($result['exit'] !== 0) {
        return [];
    }

    return array_values(array_filter(
        explode("\0", $result['output']),
        static fn (string $path): bool => $path !== '',
    ));
}

/**
 * A named path as git will read it, or null when it is not one this tool may commit.
 *
 * Absolute paths, `..` climbs and names that resolve outside the repository are refused rather than
 * normalised: a named set is a decision about this repository, and a path that needs resolving is
 * one the caller has not made that decision about.
 */
function named(string $root, string $path): ?string
{
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^\./#', '', $path) ?? $path;

    if ($path === '' || str_starts_with($path, '/') || preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
        return null;
    }

    if (str_contains($path, "\0")) {
        return null;
    }

    return $path;
}

/**
 * The changelog with one bullet added to a section of `## Unreleased`, or null when there is no
 * Unreleased section to write into.
 *
 * The bullet is inserted as the first entry of the section, so the newest change reads first and a
 * section that already has entries keeps them. A section that is not there yet is created directly
 * under the heading; a blank line is kept between the heading and the first entry either way.
 */
function withBullet(string $changelog, string $section, string $bulletin): ?string
{
    $pattern = '/^(##[ \t]+Unreleased[ \t]*\n)(.*?)(?=^##[ \t]|\z)/ms';

    if (preg_match($pattern, $changelog, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return null;
    }

    $body = $match[2][0];
    $offset = $match[2][1];
    $heading = '### '.$section;
    $entry = '- '.$bulletin."\n";

    if (preg_match('/^'.preg_quote($heading, '/').'[ \t]*\n/m', $body, $found, PREG_OFFSET_CAPTURE) === 1) {
        $at = $found[0][1] + strlen($found[0][0]);

        // A blank line right after the heading is the list's own spacing: step over it so the new
        // bullet joins the entries instead of standing above a gap of its own.
        if (substr($body, $at, 1) === "\n") {
            $at++;
        } else {
            $entry = "\n".$entry;
        }

        $body = substr_replace($body, $entry, $at, 0);
    } else {
        // A section that is not there yet goes in directly under the Unreleased heading, with the
        // blank line that keeps it apart from the heading above it and from the section below.
        $body = "\n".$heading."\n\n".$entry."\n".ltrim($body, "\n");
    }

    return substr_replace($changelog, $body, $offset, strlen($match[2][0]));
}

function usage(): string
{
    return <<<'TEXT'
bin/commit.php — commit exactly the paths it was told about, and only those.

Usage:
  bin/commit.php -m "subject" --changelog "what changed" [options] -- <path> ...

Options:
  -m, --message <subject>     the commit subject (required unless -F)
  -F, --message-file <file>   read the whole message from a file instead
      --paths-from-file <f>   read more paths, one per line, from a file
      --changelog <text>      the changelog bullet to write with the change
      --type <name>           added, changed or fixed (default: changed)
      --no-changelog          write no bullet, and expect none in the index
      --skip-checks           do not run bin/checks.php --staged before committing
      --dry-run               print the plan and write nothing
      --list                  print the resolved paths and write nothing
  -h, --help                  this text

The index must carry no path outside the named set, and the changelog bullet is written under
`## Unreleased` before anything is committed.
TEXT;
}

// ---------------------------------------------------------------------------
// the command line
// ---------------------------------------------------------------------------

$arguments = array_slice($argv, 1);
$afterSeparator = false;

for ($index = 0; $index < count($arguments); $index++) {
    $argument = $arguments[$index];

    if ($afterSeparator) {
        $options['names'][] = $argument;

        continue;
    }

    $take = static function () use (&$index, $arguments): string {
        return $arguments[++$index] ?? '';
    };

    switch (true) {
        case $argument === '--':
            $afterSeparator = true;
            break;
        case $argument === '-m' || $argument === '--message':
            $options['message'] = $take();
            break;
        case $argument === '-F' || $argument === '--message-file':
            $options['message-file'] = $take();
            break;
        case $argument === '--paths-from-file':
            $options['paths-from-file'] = $take();
            break;
        case $argument === '--changelog':
            $options['changelog'] = $take();
            break;
        case $argument === '--type':
            $options['section'] = $take();
            break;
        case str_starts_with($argument, '--type='):
            $options['section'] = substr($argument, 7);
            break;
        case $argument === '--no-changelog':
            $options['no-changelog'] = true;
            break;
        case $argument === '--skip-checks':
            $options['skip-checks'] = true;
            break;
        case $argument === '--dry-run':
            $options['dry-run'] = true;
            break;
        case $argument === '--list':
            $options['list'] = true;
            break;
        case $argument === '-h' || $argument === '--help':
            $options['help'] = true;
            break;
        default:
            fwrite(STDERR, 'unknown option: '.$argument.PHP_EOL);
            fwrite(STDERR, usage().PHP_EOL);
            exit(2);
    }
}

if ($options['help']) {
    fwrite(STDOUT, usage().PHP_EOL);
    exit(0);
}

$section = SECTIONS[strtolower((string) $options['section'])] ?? null;

if ($section === null) {
    fwrite(STDERR, 'the type must be one of added, changed or fixed'.PHP_EOL);
    exit(2);
}

// ---------------------------------------------------------------------------
// the named set
// ---------------------------------------------------------------------------

$names = [];
$refused = [];

foreach ($options['names'] as $name) {
    $resolved = named($root, $name);

    if ($resolved === null) {
        $refused[] = $name;

        continue;
    }

    $names[] = $resolved;
}

if ($options['paths-from-file'] !== '') {
    $contents = @file_get_contents($options['paths-from-file']);

    if ($contents === false) {
        fwrite(STDERR, 'could not read the paths file: '.$options['paths-from-file'].PHP_EOL);
        exit(2);
    }

    foreach (preg_split('/\R/', $contents) ?: [] as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $resolved = named($root, $line);

        if ($resolved === null) {
            $refused[] = $line;

            continue;
        }

        $names[] = $resolved;
    }
}

if ($refused !== []) {
    fwrite(STDERR, 'refusing a path that is not inside this repository: '.implode(', ', $refused).PHP_EOL);
    exit(1);
}

if ($names === []) {
    fwrite(STDERR, 'no named paths: a commit of a named set needs the set it is a commit of'.PHP_EOL);
    exit(1);
}

// The changelog goes with the change, so it is part of the set rather than a path the caller has to
// remember to add on every call - and the guard below knows it is expected.
if (! $options['no-changelog']) {
    if ($options['changelog'] === '') {
        fwrite(STDERR, 'no changelog bullet: pass --changelog "..." or --no-changelog'.PHP_EOL);
        exit(1);
    }

    $names[] = CHANGELOG;
}

/** @var list<string> $names */
$names = array_values(array_unique($names));
sort($names);

// ---------------------------------------------------------------------------
// the guard — the index may not carry a file this commit was not told about
// ---------------------------------------------------------------------------

/** @var list<string> $allowed */
$allowed = $names;
$carried = staged($root);
$unexpected = array_values(array_diff($carried, $allowed));

if ($unexpected !== []) {
    fwrite(STDERR, 'refusing: the index carries '.count($unexpected).' path(s) this commit was not told about:'.PHP_EOL);

    foreach ($unexpected as $path) {
        fwrite(STDERR, '  '.$path.PHP_EOL);
    }

    fwrite(STDERR, 'stage the named set alone, or name these paths too.'.PHP_EOL);
    exit(1);
}

if ($options['list']) {
    fwrite(STDOUT, implode(PHP_EOL, $names).PHP_EOL);
    exit(0);
}

// ---------------------------------------------------------------------------
// the changelog
// ---------------------------------------------------------------------------

$changelogPath = $root.'/'.CHANGELOG;
$changelogAfter = null;

if (! $options['no-changelog']) {
    $before = @file_get_contents($changelogPath);

    if (! is_string($before)) {
        fwrite(STDERR, 'refusing: '.CHANGELOG.' is not readable'.PHP_EOL);
        exit(1);
    }

    $after = withBullet($before, $section, $options['changelog']);

    if ($after === null) {
        fwrite(STDERR, 'refusing: '.CHANGELOG.' has no `## Unreleased` section to write into'.PHP_EOL);
        exit(1);
    }

    $changelogAfter = $after;
}

// ---------------------------------------------------------------------------
// the message
// ---------------------------------------------------------------------------

$messageArguments = [];

if ($options['message-file'] !== '') {
    if (! is_file($options['message-file'])) {
        fwrite(STDERR, 'refusing: no message file at '.$options['message-file'].PHP_EOL);
        exit(1);
    }

    $messageArguments = ['-F', $options['message-file']];
} elseif ($options['message'] !== '') {
    $messageArguments = ['-m', $options['message']];
} else {
    fwrite(STDERR, 'refusing: no message - pass -m "subject" or -F <file>'.PHP_EOL);
    exit(1);
}

// ---------------------------------------------------------------------------
// the plan
// ---------------------------------------------------------------------------

if ($options['dry-run']) {
    fwrite(STDOUT, 'plan: '.count($names).' path(s)'.PHP_EOL);

    foreach ($names as $path) {
        fwrite(STDOUT, '  '.$path.PHP_EOL);
    }

    if (! $options['no-changelog']) {
        fwrite(STDOUT, 'changelog: a `'.$section.'` bullet under `## Unreleased`'.PHP_EOL);
    }

    fwrite(STDOUT, 'checks: '.($options['skip-checks'] ? 'skipped' : 'bin/checks.php --staged').PHP_EOL);
    exit(0);
}

// ---------------------------------------------------------------------------
// stage, check, commit
// ---------------------------------------------------------------------------

if ($changelogAfter !== null) {
    if (file_put_contents($changelogPath, $changelogAfter) === false) {
        fwrite(STDERR, 'refusing: could not write '.CHANGELOG.PHP_EOL);
        exit(1);
    }
}

$add = runCommand(['git', '-C', $root, 'add', '-f', '--', ...$names], $root);

if ($add['exit'] !== 0) {
    fwrite(STDERR, 'could not stage the named set:'.$add['output'].PHP_EOL);
    exit(1);
}

// A rename pairs its two halves, so the index can gain a path the set did not name. Read it back
// rather than assume: the guard is only worth having if it is asked of what will be committed.
$unexpected = array_values(array_diff(staged($root), $allowed));

if ($unexpected !== []) {
    fwrite(STDERR, 'refusing: staging the set pulled in '.count($unexpected).' path(s) it was not told about:'.PHP_EOL);

    foreach ($unexpected as $path) {
        fwrite(STDERR, '  '.$path.PHP_EOL);
    }

    exit(1);
}

if (! $options['skip-checks']) {
    $checks = runCommand([PHP_BINARY, $root.'/bin/checks.php', '--staged'], $root);

    if ($checks['exit'] !== 0) {
        fwrite(STDERR, 'the staged checks failed, so nothing was committed:'.PHP_EOL.$checks['output']);
        exit(1);
    }
}

$committed = runCommand(['git', '-C', $root, 'commit', ...$messageArguments], $root);

fwrite(STDOUT, $committed['output']);

if ($committed['exit'] !== 0) {
    exit(1);
}

exit(0);
