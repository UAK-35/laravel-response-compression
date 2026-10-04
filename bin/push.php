#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Push a branch, then read the GitHub Actions run it started
|--------------------------------------------------------------------------
|
| WHY THIS EXISTS
| ---------------
| `git push` answers one question — did the bytes leave this machine — and it answers it in a
| second. The question a reader actually has is whether the thing that ran *because* of them
| passed, and that answer arrives somewhere else, minutes later. A push reported as done at the
| moment git returns is therefore a claim about the future dressed as one about the past, which
| is the shape of every "it's pushed" that was followed by a red build.
|
| The release lane already assumes the run exists. `bin/release.php` refuses a release whose
| commit is not the tip of the remote branch, because that commit is one *nothing has built* —
| the check is there precisely because a version shipped behind a red run is a version nobody
| looked at. This program is the other half of that same sentence: the commit is built, and here
| is whether it passed.
|
| So the push and the reading are one command. It pushes the branch, finds the run GitHub
| started for the commit it pushed, and waits for that run to conclude. `success` is the only
| answer that exits 0. Every other answer — a failure, a cancellation, a timeout, or a push that
| started no run at all — exits 1 and says what it read, so the run is read before anyone reports
| the push as done.
|
| HOW THE RUN IS READ
| -------------------
| Through `gh`, the GitHub CLI, which is the tool on this machine that already knows who the
| user is: `gh run list --commit <sha>` resolves the repository from the checkout it is run in
| and authenticates with the session `gh auth login` established, so this program holds no token
| and writes none down. `--gh` names the command to ask instead, which is also how the tests
| drive it: the reader is a command, and a command can be a stand-in.
|
| A push that started no run is a refusal rather than a pass. The workflow here gates `main`,
| `development` and `v*` tags; a push to any other branch builds nothing, and "nothing ran" is
| not the same answer as "it passed" — it is the one answer that cannot be read as a verdict.
|
| USAGE
| -----
|   composer push                            push the current branch and wait for its run
|   bin/push.php [--branch=NAME] [--remote=NAME]
|   bin/push.php --no-watch                  push and stop, saying the run was not read
|   bin/push.php --dry-run                   print what would run, and change nothing
|
| Run it from PowerShell 7 on Windows, like every other push here: a credential prompt is
| answered by the console, and a non-interactive shell has none to answer it on.
|
| Exit code is 0 when the run concluded `success`, 1 when it did not — a failure, a
| cancellation, a timeout, a reader that could not be asked, or a push that started no run —
| and 2 for a usage error.
*/

/**
 * @guards-index guard `bin/push.php`
 *
 * a push reported as done before the run it started was read — the commit that was pushed is asked of GitHub, the run for it is waited for, and only `success` exits 0; a push that started no run is refused rather than read as a pass
 */
$root = str_replace('\\', '/', dirname(__DIR__));

// ─────────────────────────────────────────────────────────────────────────────
// CLI
// ─────────────────────────────────────────────────────────────────────────────

$options = [
    'branch' => '',
    'remote' => 'origin',
    'gh' => 'gh',
    'timeout' => '1800',
    'interval' => '10',
    'grace' => '60',
    'watch' => true,
    'dry-run' => false,
];

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        usage();
        exit(0);
    }

    if ($argument === '--dry-run') {
        $options['dry-run'] = true;

        continue;
    }

    if ($argument === '--no-watch') {
        $options['watch'] = false;

        continue;
    }

    if (preg_match('/^--(branch|remote|gh|timeout|interval|grace)=(.*)$/', $argument, $match) === 1) {
        $options[$match[1]] = $match[2];

        continue;
    }

    fwrite(STDERR, $argument.' is not an option this program has.'.PHP_EOL);
    usage();

    exit(2);
}

foreach (['timeout', 'interval', 'grace'] as $key) {
    if (preg_match('/^\d+$/', (string) $options[$key]) !== 1) {
        fail(sprintf('--%s takes a whole number of seconds, and `%s` is not one.', $key, $options[$key]));
    }

    $options[$key] = (int) $options[$key];
}

if (trim((string) $options['gh']) === '') {
    fail('--gh names the command that reads the run, and it is empty. Omit it to use `gh`.');
}

// ─────────────────────────────────────────────────────────────────────────────
// What is being pushed, and whether it can be
// ─────────────────────────────────────────────────────────────────────────────

// A detached HEAD is not on a branch, and a branch is what this command pushes and then names to
// the reader: a commit without one is not a push a reader could follow.
$branch = $options['branch'] !== ''
    ? $options['branch']
    : trim(git($root, ['rev-parse', '--abbrev-ref', 'HEAD'])['output']);

if ($branch === '' || $branch === 'HEAD') {
    fail('HEAD is not on a branch, so there is no branch to push. Name one with --branch=NAME.');
}

if (git($root, ['rev-parse', '--verify', '--quiet', 'refs/heads/'.$branch])['exit'] !== 0) {
    fail(sprintf('%s is not a branch in this repository.', $branch));
}

$url = trim(git($root, ['remote', 'get-url', $options['remote']])['output']);

if ($url === '') {
    fail(sprintf('%s is not a remote here, so there is nothing to push to.', $options['remote']));
}

// The slug is only needed to aim `gh` when it cannot resolve the repository itself, so a remote
// that is not GitHub is not a refusal — it is a reading that has to be told where to look, and
// the reader says so if it cannot find it.
$slug = slug($url);

if ($options['dry-run']) {
    echo 'Would push '.$branch.' to '.$options['remote'].' ('.rtrim($url).') with --follow-tags,'.PHP_EOL;
    echo 'then read its run through: '.trim((string) $options['gh']).PHP_EOL;

    if ($slug !== '') {
        echo 'Repository: '.$slug.PHP_EOL;
    }

    echo PHP_EOL.'Nothing was pushed and nothing was read (--dry-run).'.PHP_EOL;

    exit(0);
}

// ─────────────────────────────────────────────────────────────────────────────
// The push
// ─────────────────────────────────────────────────────────────────────────────

$pushed = git($root, ['push', $options['remote'], $branch, '--follow-tags']);

if (trim($pushed['output']) !== '') {
    echo rtrim($pushed['output']).PHP_EOL;
}

if ($pushed['exit'] !== 0) {
    fail(sprintf(
        '`git push %s %s --follow-tags` failed, so there is no run to read.',
        $options['remote'],
        $branch,
    ));
}

// The commit that was pushed is the branch's tip: `--follow-tags` may have carried tags beside
// it, and a tag is a name for the same commit rather than a different one.
$sha = trim(git($root, ['rev-parse', $branch])['output']);

note(sprintf('Pushed %s to %s (%s).', $branch, $options['remote'], substr($sha, 0, 12)));

if (! $options['watch']) {
    echo PHP_EOL.'The run was not read (--no-watch): this push is reported as sent, not as verified.'.PHP_EOL;

    exit(0);
}

// ─────────────────────────────────────────────────────────────────────────────
// The run, to a verdict
// ─────────────────────────────────────────────────────────────────────────────

$deadline = microtime(true) + $options['timeout'];
$grace = microtime(true) + $options['grace'];
$announced = false;
$run = [];

while (true) {
    $runs = reader((string) $options['gh'], $slug, $sha);

    if ($runs === []) {
        // A run is registered a moment after the push, so "no run yet" is worth a second look
        // rather than an immediate refusal — but only for as long as the grace allows, because a
        // branch the workflow does not gate builds nothing at all.
        if (microtime(true) >= $grace) {
            fail(sprintf(
                "No run was started for %s.\n\nThe workflow here gates `main`, `development` and `v*` tags, so a push to another branch builds nothing, and a push that started no run cannot be read as a verdict.",
                substr($sha, 0, 12),
            ));
        }

        sleep($options['interval']);

        continue;
    }

    $run = $runs[0];

    if (! $announced) {
        note(sprintf(
            'Run %s  %s',
            (string) ($run['workflowName'] ?? 'a workflow'),
            (string) ($run['url'] ?? '(no url reported)'),
        ));

        $announced = true;
    }

    if (($run['status'] ?? '') === 'completed') {
        break;
    }

    if (microtime(true) >= $deadline) {
        fail(sprintf(
            'The run had not finished after %d seconds: %s',
            $options['timeout'],
            (string) ($run['url'] ?? '(no url reported)'),
        ));
    }

    sleep($options['interval']);
}

$conclusion = (string) ($run['conclusion'] ?? '');
$runUrl = (string) ($run['url'] ?? '');

if ($conclusion === 'success') {
    note(sprintf('The run concluded `success`: %s', $runUrl));

    exit(0);
}

fail(sprintf(
    'The run concluded `%s`: %s',
    $conclusion === '' ? 'nothing — it was completed without a conclusion' : $conclusion,
    $runUrl === '' ? '(no url reported)' : $runUrl,
));

// ─────────────────────────────────────────────────────────────────────────────
// The two processes this program runs
// ─────────────────────────────────────────────────────────────────────────────

/**
 * What GitHub has for this commit, as the reader answers it.
 *
 * The reader is `gh run list`, or a command that answers the same shape. Its JSON is the whole
 * answer: which run, what state it is in, how it ended, and where to read it. An answer that is
 * not a list of runs is treated as no runs rather than as a crash, because the question here is
 * whether a run exists — and a reader that printed something else has not said that it does.
 *
 * @return list<array<string, mixed>>
 */
function reader(string $gh, string $slug, string $sha): array
{
    $arguments = [
        'run', 'list',
        '--commit', $sha,
        '--limit', '1',
        '--json', 'databaseId,status,conclusion,url,workflowName',
    ];

    // Given a GitHub remote, aim the reader at it explicitly: `gh` would otherwise resolve the
    // repository from the checkout, which is right when it can and silent when it cannot.
    if ($slug !== '') {
        $arguments = [...$arguments, '--repo', $slug];
    }

    $result = run([...words($gh), ...$arguments]);

    if ($result['exit'] !== 0) {
        fail(sprintf(
            "The run could not be read.\n\n`%s` exited %d:\n\n%s",
            trim($gh),
            $result['exit'],
            rtrim($result['output']),
        ));
    }

    $decoded = json_decode(trim($result['output']), true);

    if (! is_array($decoded)) {
        return [];
    }

    $runs = [];

    foreach ($decoded as $run) {
        if (is_array($run)) {
            $runs[] = $run;
        }
    }

    return $runs;
}

/**
 * One git command, in this checkout, answering with its output and its exit code.
 *
 * The exit code is returned rather than thrown on: a remote that is not there and a push that
 * was refused are both answers this program has something to say about, and the caller is where
 * that is known.
 *
 * @param  list<string>  $arguments
 * @return array{exit: int, output: string}
 */
function git(string $root, array $arguments): array
{
    return run(['git', ...$arguments], $root);
}

/**
 * A command, run to completion, with its two streams folded into one answer.
 *
 * One stream rather than two, because both are a report here: git writes its progress to stderr
 * and its refusals there too, and a reader that separated them would have to decide which half a
 * sentence belongs to. stdin is declared and closed, so a command that would ask a question gets
 * an end of file instead of a hang — a push that stopped for a password would never finish.
 *
 * @param  list<string>  $command
 * @return array{exit: int, output: string}
 */
function run(array $command, ?string $cwd = null): array
{
    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes,
        $cwd,
    );

    if (! is_resource($process)) {
        fail('Could not start `'.implode(' ', $command).'`.');
    }

    fclose($pipes[0]);
    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return ['exit' => proc_close($process), 'output' => $output];
}

/**
 * A command line split into words, so `--gh` may name an interpreter and a script as well as a
 * binary.
 *
 * `gh` is a program on `PATH`; the tests' stand-in is a PHP file, which is a program only with an
 * interpreter in front of it. Splitting on whitespace is what lets one option name either, and
 * the paths it is given hold no spaces — an assumption worth stating rather than discovering.
 *
 * @return list<string>
 */
function words(string $command): array
{
    $words = preg_split('/\s+/', trim($command), -1, PREG_SPLIT_NO_EMPTY);

    return $words === false || $words === [] ? [$command] : array_values($words);
}

/**
 * The `owner/name` a GitHub remote names, or an empty string for a remote that is something else.
 *
 * Both shapes a GitHub remote is written in are read — the HTTPS URL and the `git@` one — because
 * which of the two a checkout uses is a fact about how it was cloned rather than about where the
 * run is.
 */
function slug(string $url): string
{
    if (preg_match('#github\.com[:/]([^/]+)/(.+?)(?:\.git)?/?$#', trim($url), $match) !== 1) {
        return '';
    }

    return $match[1].'/'.$match[2];
}

/** Something the run said, on stdout. */
function note(string $line): void
{
    echo $line.PHP_EOL;
}

/** A refusal, on the stream a refusal belongs on. */
function fail(string $message): never
{
    fwrite(STDERR, PHP_EOL.$message.PHP_EOL);

    exit(1);
}

/**
 * The options, and what each one is for.
 */
function usage(): void
{
    fwrite(STDERR, <<<'TXT'
    bin/push.php — push a branch, then read the Actions run it started.

    Usage:
      composer push                 Push the current branch and wait for its run.
      bin/push.php [options]

    Options:
      --branch=NAME     The branch to push (default: the one HEAD is on).
      --remote=NAME     The remote to push to (default: origin).
      --gh=COMMAND      The command that reads the run (default: gh). Words are
                        split on spaces, so an interpreter and a script can be
                        named — which is how the tests drive it.
      --timeout=SECONDS How long to wait for the run to end (default: 1800).
      --interval=SECONDS How long between readings (default: 10).
      --grace=SECONDS   How long to wait for a run to appear at all (default: 60).
      --no-watch        Push and stop, saying the run was not read.
      --dry-run         Print what would run, and change nothing.
      -h, --help        This.

    Exit code: 0 when the run concluded `success`, 1 when it did not — a failure,
    a cancellation, a timeout, a reader that could not be asked, or a push that
    started no run — and 2 for a usage error.

    TXT);
}
