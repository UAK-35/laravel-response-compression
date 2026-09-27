#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * bin/release.php — cut a release, or a prerelease, from the git tags.
 *
 * THE MODEL
 * ---------
 *   The version of this package is the tag, and nothing else. There is no `version` field in
 *   composer.json and no version file: a number stored in the repository is a second source
 *   of truth that drifts from the tag, and Packagist derives every published version from
 *   tags anyway. So this script never writes a version — it reads the latest tag, works out
 *   the next one, promotes the CHANGELOG heading, commits and tags.
 *
 *   With no tags at all the base is 0.0.0, so the bump applies to it directly: a weighed
 *   minor is 0.1.0 and a weighed patch 0.0.1.
 *
 * THE BUMP IS WEIGHED
 * -------------------
 *   `--weigh` reads the changes that are about to be released, weighs them against the
 *   policy in RELEASING.md and takes the bump. Three signals are read and the loudest one
 *   wins:
 *
 *     CHANGELOG  the `###` headings of the Unreleased section — the Keep a Changelog
 *                categories the policy is written against
 *     commits    the subjects and body footers since the last tag, read as Conventional
 *                Commits (`feat`, `fix`, `!`, `BREAKING CHANGE:`)
 *     surface    the classes, public methods, public properties, public constants and config
 *                keys of src/ and config/ at HEAD against the last tag
 *
 *   An explicit bump (`--minor`, `--major`, `--version=X.Y.Z`) is allowed, but never smaller
 *   than what the policy asks for: declaring one that undersells the changes stops the
 *   release with exit code 1 and names the signal that forbids it. `--patch` does not exist
 *   — a patch is what `--weigh` computes when nothing louder is found.
 *
 *   While the package is 0.x a *weighed* breaking change is a minor, which is what
 *   RELEASING.md's table says: the version does not pretend to be 1.0 yet. A declared
 *   `--major` still opens 1.0.0, because that one is a decision rather than a reading.
 *
 *   The surface signal reads the tree with PHP's own tokenizer rather than with a parser, so
 *   this script runs in a bare checkout with no `vendor/`. It reports what changed in shape,
 *   and one thing it cannot see: a method whose *body* started compressing differently. That
 *   is a change in behaviour, not in shape, and the notes are where it is declared.
 *
 * PRERELEASES
 * -----------
 *   A version may carry a suffix — `-alphaN`, `-betaN`, `-rcN`, or a bare `-dev` — and the
 *   suffixes this script writes are the ones Composer's own parser reads. That is not
 *   tidiness: a tag Composer cannot parse is a tag nobody can install, and it fails quietly,
 *   so `0.0.1-dev.1` and `0.0.1-alpha.1` are refused here instead of being discovered
 *   downstream. `dev` takes no number, which is why a numbered dev lane is spelled `-alpha1`.
 *
 *     php bin/release.php --prerelease=alpha     # v0.0.9 -> v0.0.10-alpha1
 *     php bin/release.php --prerelease=alpha     # v0.0.10-alpha1 -> v0.0.10-alpha2
 *     php bin/release.php --weigh                # v0.0.10-alpha2 -> v0.0.10
 *
 *   The number is not remembered anywhere: asked for a lane, the script counts the tags
 *   already in it and takes the next free number, which is why a lane can be cut again and
 *   again without anyone renaming anything.
 *
 *   A prerelease KEEPS THE LINE it is on, and promotes to its release. `--prerelease=alpha`
 *   after `v0.0.10-alpha2` cuts `v0.0.10-alpha3` rather than `v0.0.11-alpha1`, because 0.0.10
 *   was chosen when the first alpha was cut and the notes written since are that line's notes;
 *   and `--weigh` after `v0.0.10-alpha2` cuts `v0.0.10`, which is what the alphas were for.
 *   Declare `--minor` or `--major` to open a new line instead.
 *
 * ONE LANE AT A TIME
 * ------------------
 *   Everything is cut from `main`, releases and prereleases alike: this repository develops on
 *   one branch, so the suffix decides the *version* rather than the branch. `--branch=NAME`
 *   releases from somewhere else, and a release is refused anywhere else by default.
 *
 *   Because a prerelease precedes the release it is named after, a prerelease cannot be cut
 *   once its release exists: `0.0.10-alpha1` is not newer than `0.0.10`, and the rail that
 *   refuses a version that is not newer is the one that says so. The alphas for a line come
 *   first, then its release.
 *
 * THE RAILS
 * ---------
 *   Every one of these stops the release with exit code 1 rather than being noted:
 *
 *     not a git repository             tags are the version; there is nothing to release into
 *     HEAD is not the expected branch  a release is cut from the branch it is developed on
 *     tracked files are dirty          the tag has to point at exactly what was reviewed
 *     the tag already exists           a published tag is never moved or reused
 *     the version is not newer         a release cannot go backwards
 *     the changelog already has it     two sections for one version is a heading nobody can read
 *     no `## Unreleased` section       there is nothing to promote
 *     the Unreleased section is empty  the notes are what a release publishes
 *     the declared bump undersells     shipping a breaking change as a patch
 *     `composer checks` is red         the tag has to point at a commit the gate passed
 *     HEAD is not the remote's tip     the commit being released is one nothing has built
 *     not interactive, no `--yes`      it is about to commit and tag
 *
 *   `--dry-run` reports the last three instead of enforcing them, because a plan publishes
 *   nothing and should be safe on a dirty tree. `--allow-dirty`, `--ignore-policy`,
 *   `--skip-checks` and `--skip-ci` are the escapes, and each one used is named in the plan so
 *   that a release which took a shortcut says so.
 *
 * USAGE
 * -----
 *   php bin/release.php --weigh --dry-run     print the plan, change nothing
 *   php bin/release.php --weigh               promote, commit, tag
 *   php bin/release.php --minor               declare the bump (never below the policy)
 *   php bin/release.php --prerelease=alpha    cut the next alpha in the current line
 *   php bin/release.php --version=0.2.1       release exactly this version
 *   php bin/release.php --weigh --push        ...and push the branch and the tag
 *
 * EXIT CODES
 * ----------
 *   0 released (or planned), 1 a precondition failed, 2 usage error.
 */
$root = str_replace('\\', '/', dirname(__DIR__));

// ─────────────────────────────────────────────────────────────────────────────
// CLI
// ─────────────────────────────────────────────────────────────────────────────

$options = [
    'weigh' => false,
    'kind' => null,
    'version' => null,
    'prerelease' => null,
    'dry-run' => false,
    'yes' => false,
    'push' => false,
    'branch' => 'main',
    'remote' => 'origin',
    'allow-dirty' => false,
    'ignore-policy' => false,
    'skip-checks' => false,
    'skip-ci' => false,
];

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        usage();
        exit(0);
    }

    // --patch used to pick the bump by hand. It is gone on purpose: a patch is what --weigh
    // computes when nothing louder is found, and hand-picking one is how a change got
    // undersold in the first place. Saying so beats ignoring it.
    if ($argument === '--patch') {
        usageError('--patch is gone: use --weigh, which computes a patch when the changes are one.');
    }

    if ($argument === '--weigh') {
        $options['weigh'] = true;

        continue;
    }

    if ($argument === '--minor' || $argument === '--major') {
        $options['kind'] = substr($argument, 2);

        continue;
    }

    if (in_array($argument, ['--dry-run', '--yes', '-y', '--push', '--allow-dirty', '--ignore-policy', '--skip-checks', '--skip-ci'], true)) {
        $options[ltrim($argument, '-')] = true;

        continue;
    }

    if (preg_match('/^--(version|prerelease|branch|remote)=(.*)$/', $argument, $match) === 1) {
        $options[$match[1]] = $match[2];

        continue;
    }

    usageError('Unknown option: '.$argument);
}

if ($options['version'] !== null && $options['prerelease'] !== null) {
    usageError('--version and --prerelease are two answers to the same question: pass one of them.');
}

if ($options['kind'] !== null && $options['version'] !== null) {
    usageError('--version names the version outright; drop --'.$options['kind'].'.');
}

if ($options['prerelease'] !== null && ! in_array($options['prerelease'], ['alpha', 'beta', 'rc'], true)) {
    usageError('--prerelease takes alpha, beta or rc — not '.$options['prerelease'].'.');
}

// ─────────────────────────────────────────────────────────────────────────────
// Preconditions — is there anything to release into, and from here?
// ─────────────────────────────────────────────────────────────────────────────

if (git($root, ['rev-parse', '--git-dir'])['exit'] !== 0) {
    fail($root.' is not a git repository. The version of this package is the tag, so a release is a tag, and a tag needs one.');
}

$branch = $options['branch'];
$current = trim(git($root, ['rev-parse', '--abbrev-ref', 'HEAD'])['output']);

if ($current !== $branch) {
    fail(sprintf(
        "A release is cut from %s, and HEAD is on %s.\n\nCheck the branch out first, or pass --branch=%s if that is deliberate.",
        $branch,
        $current === 'HEAD' ? 'a detached HEAD' : $current,
        $current === 'HEAD' ? 'NAME' : $current,
    ));
}

$dirty = trim(git($root, ['status', '--porcelain', '--untracked-files=no'])['output']);

if ($dirty !== '' && ! $options['allow-dirty'] && ! $options['dry-run']) {
    fail("Tracked files have uncommitted changes:\n\n".$dirty."\n\nThe tag has to point at exactly what was reviewed — commit or stash first, or pass --allow-dirty.");
}

$base = latestTag($root);
$baseVersion = $base === null ? '0.0.0' : substr($base, 1);
$baseLine = baseOf($baseVersion);
$baseIsPrerelease = isPrerelease($baseVersion);

// ─────────────────────────────────────────────────────────────────────────────
// The changelog — what would be promoted, and whether there is anything to promote
// ─────────────────────────────────────────────────────────────────────────────

$changelogPath = $root.'/CHANGELOG.md';
$changelogRaw = @file_get_contents($changelogPath);

if ($changelogRaw === false) {
    fail('There is no CHANGELOG.md to promote, and a release publishes its notes.');
}

// CRLF is preserved rather than normalised: whoever writes this file uses the line endings
// their editor uses, and a release commit that rewrites every line of it is a diff nobody
// can read.
$eol = str_contains($changelogRaw, "\r\n") ? "\r\n" : "\n";
$changelog = str_replace("\r\n", "\n", $changelogRaw);

$unreleased = unreleased($changelog);
$unreleasedBody = $unreleased === null ? '' : $unreleased['body'];
$entries = countBullets($unreleasedBody);

// ─────────────────────────────────────────────────────────────────────────────
// Weighing the changes
// ─────────────────────────────────────────────────────────────────────────────

$signals = weigh($root, $base, $unreleasedBody);
$weighed = loudest($signals);
$weighedKind = kindOf($weighed, $baseLine);
$kind = $options['kind'] ?? $weighedKind;

// ─────────────────────────────────────────────────────────────────────────────
// The version
// ─────────────────────────────────────────────────────────────────────────────

$promoting = $baseIsPrerelease && $options['kind'] === null && $options['version'] === null && $options['prerelease'] === null;

if ($options['version'] !== null) {
    $version = canonical($options['version']);
} elseif ($options['prerelease'] !== null) {
    $line = $baseIsPrerelease && $options['kind'] === null ? $baseLine : bump($baseVersion, $kind);
    $version = $line.'-'.$options['prerelease'].nextLaneNumber($root, $line, $options['prerelease']);
} elseif ($promoting) {
    $version = $baseLine;
} else {
    $version = bump($baseVersion, $kind);
}

$tag = 'v'.$version;

// ─────────────────────────────────────────────────────────────────────────────
// Rails on the version
// ─────────────────────────────────────────────────────────────────────────────

$existing = allVersions($root);

if (in_array($version, $existing, true)) {
    $lane = laneOf($version);

    fail(sprintf(
        "Tag %s already exists — a published tag is never moved or reused.\n\n%s",
        $tag,
        $lane === null
            ? 'Cut a newer version, or declare one: --version=X.Y.Z.'
            : sprintf('The next free number in that lane is %s-%s%d.', baseOf($version), $lane, nextLaneNumber($root, baseOf($version), $lane)),
    ));
}

// Asked before the "is not newer" rail, because it is the more specific answer: a prerelease
// whose release already exists is not merely older, it is on the wrong side of the version it is
// named after.
if (isPrerelease($version) && in_array(baseOf($version), $existing, true)) {
    fail(sprintf(
        "%s precedes v%s, which is already released: a prerelease cannot be cut once the release it is named after exists.\n\nCut the alphas for a line before its release, or move on: --version=X.Y.Z.",
        $tag,
        baseOf($version),
    ));
}

$newest = $base === null ? null : maxVersion($existing, $baseVersion);

if ($newest !== null && compareVersions($version, $newest) <= 0) {
    fail(sprintf(
        "%s is not newer than v%s, the most recent tag — a release cannot go backwards.\n\nA prerelease precedes the release it is named after, so the alphas for a line are cut before it rather than after.",
        $tag,
        $newest,
    ));
}

$documented = documentedVersions($changelog);

if (in_array($version, $documented, true)) {
    // Reachable only for a heading with no tag behind it: a version that is both documented and
    // tagged is refused by the rail above, which is asked first. So this is a heading this
    // repository inherited, not notes an earlier release promoted from here, and the two ways out
    // are decisions about the changelog rather than a repair this script could make.
    //
    // The inherited case is real rather than theoretical: this package's tags are its own while
    // its changelog's oldest sections came from the upstream it was forked from, and the bump the
    // weighing asks for can land exactly on one of those headings.
    $written = maxVersion(array_merge($existing, $documented), $baseVersion);

    // The tags are summarised rather than listed: a message that enumerates two years of tags is
    // one nobody reads to the end of, and the number that matters is the newest of them.
    $tags = $existing === []
        ? 'none'
        : sprintf('newest v%s of %d', $newest, count($existing));

    fail(sprintf(
        "CHANGELOG.md already has a section for %1\$s, and no %1\$s tag exists, so that heading is history this repository never released — not notes an earlier run promoted.\n\n"
        ."Promoting these notes under it would leave one version with two sets of notes: the ones written there and the ones in `## Unreleased`.\n\n"
        ."Two ways out, and both are a decision:\n\n"
        ."  go above it      --version=%2\$s is the next %3\$s above every version this repository has written down (tags: %4\$s; changelog: %5\$s)\n"
        .'  reuse the line   re-label or fold the inherited heading first, then release %1$s from the tag sequence',
        $tag,
        bump($written, $kind),
        $kind,
        $tags,
        'v'.implode(', v', $documented),
    ));
}

// The inherited-history case, reported rather than refused. This package's tags stop at
// v0.0.9 while its changelog's newest section is v0.2.0, inherited from upstream;
// RELEASING.md records both ways out, and which applies is a decision, not a fault.
if ($newest !== null) {
    $ahead = array_values(array_filter($documented, static fn (string $documented): bool => compareVersions($documented, $newest) > 0));

    if ($ahead !== []) {
        note(sprintf('CHANGELOG.md documents v%s, ahead of the newest tag (v%s): inherited history, left as it is', implode(', v', $ahead), $newest));
    }
}

if ($dirty !== '') {
    note('tracked files have uncommitted changes; the tag will point at the commit HEAD names, not at them');
}

if ($promoting) {
    note(sprintf('v%s is a prerelease, so this promotes its line to a release; pass --minor to open a new line instead', $baseVersion));
}

// ─────────────────────────────────────────────────────────────────────────────
// The plan
// ─────────────────────────────────────────────────────────────────────────────

$promoted = promote($changelog, $version, date('Y-m-d'));

echo PHP_EOL.'Release plan'.PHP_EOL.PHP_EOL;
line('branch', $branch);
line('base', $base === null ? 'none — this is the first tag' : $base);
line('bump', $kind.'  ('.($options['kind'] === null ? 'weighed: '.$weighed : 'declared').')');
line('version', $version);
line('tag', $tag);
line('stability', isPrerelease($version) ? $kind.' prerelease, ahead of v'.baseOf($version) : 'stable');
line('changelog', $promoted === null
    ? 'not promoted: there is no `## Unreleased` section'
    : sprintf('%s  (%d entr%s)', $promoted['heading'], $entries, $entries === 1 ? 'y' : 'ies'));
line('checks', $options['skip-checks'] ? 'not checked (--skip-checks)' : ($options['dry-run'] ? 'not run (--dry-run)' : 'composer checks'));
line('ci', $options['skip-ci'] ? 'not checked (--skip-ci)' : 'HEAD must be the tip of '.$options['remote'].'/'.$branch);
line('commit', 'Release '.$tag);
line('tag command', sprintf('git tag -a %s -m %s', $tag, $tag));
line('push', $options['push'] ? sprintf('git push %s %s --follow-tags', $options['remote'], $branch) : 'not requested (--push)');

echo PHP_EOL.'Weighing'.PHP_EOL.PHP_EOL;

foreach ($signals as $name => $signal) {
    line($name, $signal === null ? 'not read' : $signal['severity'].'  '.$signal['evidence']);
}

if ($options['ignore-policy']) {
    note('--ignore-policy: the declared bump is released though it undersells the weighing');
}

echo PHP_EOL;

if ($promoted !== null) {
    echo 'The CHANGELOG head after promotion:'.PHP_EOL.PHP_EOL;
    echo indent(implode("\n", array_slice(explode("\n", $promoted['content']), 0, 18))).PHP_EOL.PHP_EOL;
}

if ($options['dry-run']) {
    note('dry run: nothing was written, committed or tagged');
    exit(0);
}

// ─────────────────────────────────────────────────────────────────────────────
// The rails that cost something — the notes, the policy, the gate, the remote
// ─────────────────────────────────────────────────────────────────────────────

if ($unreleased === null) {
    fail("CHANGELOG.md has no `## Unreleased` section, so there are no notes to promote.\n\nAdd one above the newest released section and write what changed in it.");
}

if ($entries === 0) {
    fail("The `## Unreleased` section is empty, so this release would publish no notes.\n\nA release is what a reader upgrades on: write the entries first, or run --weigh --dry-run to see the bump they weigh.");
}

if ($options['kind'] !== null && ! $options['ignore-policy'] && size($options['kind']) < size($weighedKind)) {
    fail(sprintf(
        "--%s was declared, but the changes call for a %s release.\n\n  %s\n\nDeclare the larger bump, or pass --ignore-policy to release anyway and say so.",
        $options['kind'],
        $weighedKind,
        loudestEvidence($signals),
    ));
}

if (! $options['skip-checks']) {
    $checks = runCommand([PHP_BINARY, $root.'/bin/checks.php'], $root);

    if ($checks['exit'] !== 0) {
        fail(sprintf(
            "`composer checks` did not pass, and a tag is not a good place to find that out:\n\n%s\n\nFix it, or pass --skip-checks to release anyway.",
            indent(rtrim(stripAnsi($checks['output']))),
        ));
    }

    note('the check suite passed');
}

if (! $options['skip-ci']) {
    // The exit code is what decides whether there is an answer: `ls-remote` on a repository with
    // no such remote prints why it failed, and reading that as a sha would report the wrong rail.
    $listed = git($root, ['ls-remote', '--heads', $options['remote'], $branch]);
    $remoteSha = $listed['exit'] === 0 ? (string) strtok(trim($listed['output']), "\t") : '';
    $head = trim(git($root, ['rev-parse', 'HEAD'])['output']);

    if ($remoteSha === '') {
        fail(sprintf(
            "%s/%s has no head to compare against, so nothing has built the commit being released.\n\nPush the branch first, or pass --skip-ci to release on a repository with no remote.",
            $options['remote'],
            $branch,
        ));
    }

    if ($remoteSha !== $head) {
        fail(sprintf(
            "HEAD is not the tip of %s/%s, so the commit being released is one CI has not built.\n\n  local   %s\n  remote  %s\n\nPush it first, or pass --skip-ci.",
            $options['remote'],
            $branch,
            substr($head, 0, 12),
            substr($remoteSha, 0, 12),
        ));
    }

    note('HEAD is the tip of '.$options['remote'].'/'.$branch);
}

// ─────────────────────────────────────────────────────────────────────────────
// Confirm, then do it
// ─────────────────────────────────────────────────────────────────────────────

if (! $options['yes']) {
    if (! stream_isatty(STDIN)) {
        fail('This is not an interactive shell, and a release commits and tags: pass --yes to confirm it was meant.');
    }

    if (! confirm(sprintf('Release %s? [y/N] ', $tag))) {
        fail('Nothing was released.');
    }
}

if (@file_put_contents($changelogPath, str_replace("\n", $eol, $promoted['content'])) === false) {
    fail('CHANGELOG.md could not be written.');
}

foreach ([
    ['add', 'CHANGELOG.md'],
    ['commit', '-m', 'Release '.$tag],
    ['tag', '-a', $tag, '-m', $tag],
] as $command) {
    note('git '.implode(' ', $command));

    $result = git($root, $command);

    if ($result['exit'] !== 0) {
        fail(sprintf("`git %s` failed:\n\n%s", implode(' ', $command), rtrim($result['output'])));
    }
}

echo PHP_EOL.'Released '.$tag.'.'.PHP_EOL;

if ($options['push']) {
    $pushed = git($root, ['push', $options['remote'], $branch, '--follow-tags']);

    if ($pushed['exit'] !== 0) {
        fail(sprintf("`git push` failed:\n\n%s", rtrim($pushed['output'])));
    }

    echo 'Pushed '.$branch.' and '.$tag.' to '.$options['remote'].'.'.PHP_EOL;
} else {
    echo PHP_EOL.'Push it:'.PHP_EOL.PHP_EOL.sprintf('  git push %s %s --follow-tags', $options['remote'], $branch).PHP_EOL;
}

echo PHP_EOL.'Packagist publishes from the tag rather than from the push: the package page, or a crawl,'.PHP_EOL;
echo 'is what turns '.$tag.' into an installable version.'.PHP_EOL;

if (isPrerelease($version)) {
    echo PHP_EOL.'A prerelease is not matched by `^'.baseOf($version).'` at the default minimum-stability:'.PHP_EOL;
    echo 'consumers need `"minimum-stability": "alpha"`, or a constraint that says so —'.PHP_EOL;
    echo '`^'.baseOf($version).'@'.stabilityOf($version).'`.'.PHP_EOL;
}

exit(0);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers — arguments and output
// ─────────────────────────────────────────────────────────────────────────────

function usage(): void
{
    echo <<<'TXT'
    bin/release.php — cut a release, or a prerelease, from the git tags.

    Usage:
      php bin/release.php --weigh [--dry-run] [--push]

    Options:
          --weigh          Weigh the changes and take the bump they ask for.
          --minor          Declare a minor bump (never below the weighing).
          --major          Declare a major bump.
          --version=V      Release exactly this version, e.g. 1.0.0 or 0.0.1-alpha1.
          --prerelease=L   Cut the next alpha, beta or rc in the current line.
          --dry-run        Print the plan, the promoted changelog head and the commands.
      -y, --yes            Do not ask before committing and tagging.
          --push           Push the branch with --follow-tags when done.
          --branch=NAME    The branch to release from (default: main).
          --remote=NAME    The remote to check and push to (default: origin).
          --allow-dirty    Release though tracked files are uncommitted.
          --ignore-policy  Release though the declared bump undersells the changes.
          --skip-checks    Do not run `composer checks` first.
          --skip-ci        Do not require HEAD to be the tip of the remote branch.
      -h, --help           Show this help.

    Exit code: 0 released or planned, 1 a precondition failed, 2 usage error.

    TXT;
}

/**
 * One `key  value` line of the plan, in the shape the release tests read back.
 */
function line(string $key, string $value): void
{
    printf('  %-13s %s'.PHP_EOL, $key, $value);
}

/**
 * Something the run says about itself without stopping: a skip, an override, a number worth
 * looking at twice.
 */
function note(string $message): void
{
    echo '  note: '.$message.PHP_EOL;
}

/**
 * Indent a block for the plan.
 *
 * Split on any line ending rather than on PHP_EOL: the promoted changelog is normalised to LF
 * while it is worked on and converted back only when it is written, so on Windows — where PHP_EOL
 * is CRLF — exploding on PHP_EOL finds one line in a dozen and indents the first of them.
 */
function indent(string $text): string
{
    $rows = preg_split('/\R/', $text) ?: [$text];

    return implode(PHP_EOL, array_map(static fn (string $row): string => '    '.$row, $rows));
}

/**
 * A precondition failed. The reason goes to stderr while the plan goes to stdout, and the two
 * are kept apart because a test that asserts on the wrong stream passes for the wrong reason.
 */
function fail(string $message): never
{
    fwrite(STDERR, PHP_EOL.'✗ '.$message.PHP_EOL.PHP_EOL);
    exit(1);
}

/**
 * The argument itself is wrong, so nothing was attempted. Exit 2, not 1.
 */
function usageError(string $message): never
{
    fwrite(STDERR, '✗ '.$message.PHP_EOL.PHP_EOL);
    usage();
    exit(2);
}

function confirm(string $question): bool
{
    echo $question;

    $answer = fgets(STDIN);

    return $answer !== false && in_array(strtolower(trim($answer)), ['y', 'yes'], true);
}

function stripAnsi(string $output): string
{
    return (string) preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $output);
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers — git and processes
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Run a git command in the package root.
 *
 * @param  list<string>  $arguments
 * @return array{exit: int, output: string}
 */
function git(string $root, array $arguments): array
{
    return runCommand(['git', ...$arguments], $root);
}

/**
 * Run a command with stdout and stderr merged. No shell is involved: the arguments arrive as
 * a list, so there is nothing to quote and nothing a path with a space in it can break.
 *
 * @param  list<string>  $command
 * @return array{exit: int, output: string}
 */
function runCommand(array $command, string $cwd): array
{
    $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $cwd);

    if (! is_resource($process)) {
        return ['exit' => 127, 'output' => 'could not start: '.implode(' ', $command)];
    }

    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return ['exit' => proc_close($process), 'output' => $output];
}

/**
 * The most recent `v*` tag HEAD contains.
 *
 * `git describe` answers with the nearest tag on the history being built, which is the base a
 * release has to be a change from. A tag cut on a branch HEAD cannot reach says nothing about
 * this tree, so it is not a base — and when `describe` finds nothing, `git tag --merged HEAD`
 * asks the same question of the tags that do exist.
 */
function latestTag(string $root): ?string
{
    $described = trim(git($root, ['describe', '--tags', '--abbrev=0', '--match', 'v*'])['output']);

    if ($described !== '' && git($root, ['merge-base', '--is-ancestor', $described, 'HEAD'])['exit'] === 0) {
        return $described;
    }

    $merged = trim(git($root, ['tag', '--merged', 'HEAD', '--list', 'v*', '--sort=-v:refname'])['output']);

    return $merged === '' ? null : (string) strtok($merged, "\n");
}

/**
 * Every version this repository has tagged, without the `v`, so a tag that already exists is
 * found locally and a lane number can be counted.
 *
 * @return list<string>
 */
function allVersions(string $root): array
{
    $listed = trim(git($root, ['tag', '--list', 'v*'])['output']);

    if ($listed === '') {
        return [];
    }

    return array_map(static fn (string $tag): string => ltrim(trim($tag), 'v'), explode("\n", $listed));
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers — versions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * `X.Y.Z[-laneN]` as its parts, so the arithmetic has one place to read a version.
 *
 * A version that does not parse comes back with `legal` false rather than being guessed at:
 * the refusal is a message about what is wrong with it, and a guess is not.
 *
 * @return array{base: string, lane: string|null, number: int, legal: bool}
 */
function parseVersion(string $version): array
{
    if (preg_match('/^(\d+\.\d+\.\d+)(?:-([a-z]+)(\d*))?$/', $version, $match) !== 1) {
        return ['base' => $version, 'lane' => null, 'number' => 0, 'legal' => false];
    }

    return [
        'base' => $match[1],
        'lane' => ($match[2] ?? '') === '' ? null : $match[2],
        'number' => ($match[3] ?? '') === '' ? 0 : (int) $match[3],
        'legal' => true,
    ];
}

function baseOf(string $version): string
{
    return parseVersion($version)['base'];
}

function laneOf(string $version): ?string
{
    return parseVersion($version)['lane'];
}

function isPrerelease(string $version): bool
{
    return laneOf($version) !== null;
}

function stabilityOf(string $version): string
{
    return laneOf($version) ?? 'stable';
}

/**
 * Why a suffix is not one Composer reads, or null when it is.
 *
 * Composer's vocabulary is fixed — `dev`, `alpha`, `beta`, `RC`, `stable` — and this script
 * writes the lowercase numbered lanes and a bare `dev`, because `dev` takes no number.
 * `-alpha.1` is refused rather than accepted as a second spelling of `-alpha1`: Composer reads
 * both as one version, so two tags carrying them would publish one version twice, and once the
 * dotted one exists it blocks the undotted one.
 */
function legalReason(string $version): ?string
{
    if (preg_match('/^\d+\.\d+\.\d+$/', $version) === 1) {
        return null;
    }

    if (preg_match('/^\d+\.\d+\.\d+-(alpha|beta|rc)\d+$/', $version) === 1) {
        return null;
    }

    if (preg_match('/^\d+\.\d+\.\d+-dev$/', $version) === 1) {
        return null;
    }

    if (preg_match('/^\d+\.\d+\.\d+-(alpha|beta|rc)\.\d+$/i', $version) === 1) {
        return 'the dot is a second spelling of the same version, and Composer reads both as one';
    }

    // Asked before the lowercase rule below, because `-rc` is first of all a lane with no number:
    // the more specific answer is the one a reader can act on.
    if (preg_match('/^\d+\.\d+\.\d+-(alpha|beta|rc)$/i', $version) === 1) {
        return 'a lane takes a number: '.strtolower($version).'1';
    }

    if (preg_match('/^\d+\.\d+\.\d+-(alpha|beta|rc)\d*$/i', $version) === 1) {
        return 'the lane is written in lowercase: '.strtolower($version);
    }

    if (preg_match('/^\d+\.\d+\.\d+-(nightly|pre|snapshot|p)\d*$/i', $version) === 1) {
        return 'Composer reads a fixed vocabulary — dev, alpha, beta, rc — rather than arbitrary identifiers';
    }

    return 'a version is X.Y.Z, optionally with -alphaN, -betaN, -rcN or -dev';
}

/**
 * The version as it will be written, refusing a spelling Composer cannot parse.
 *
 * The refusal is exit 2 rather than 1 because the argument itself is wrong, and it happens
 * here rather than downstream because a tag nobody can install fails quietly: it never becomes
 * a version at all.
 */
function canonical(string $version): string
{
    $version = ltrim(trim($version), 'v');
    $reason = legalReason($version);

    if ($reason !== null) {
        usageError($version.' is not a version Composer will read: '.$reason.'.');
    }

    return $version;
}

/**
 * The base with one kind of bump applied, dropping any prerelease suffix.
 */
function bump(string $base, string $kind): string
{
    [$major, $minor, $patch] = array_map(intval(...), explode('.', baseOf($base)));

    return match ($kind) {
        'major' => ($major + 1).'.0.0',
        'minor' => $major.'.'.($minor + 1).'.0',
        default => $major.'.'.$minor.'.'.($patch + 1),
    };
}

/**
 * The kind of release a weighing severity asks for, given the line being released.
 *
 * A breaking change is a major once the package is past 1.0, and a minor while it is 0.x —
 * which is the table in RELEASING.md rather than a shortcut: the version does not pretend to
 * be 1.0, and the notes are what call the change out.
 */
function kindOf(string $severity, string $line): string
{
    if ($severity !== 'breaking') {
        return $severity;
    }

    return (int) explode('.', $line)[0] === 0 ? 'minor' : 'major';
}

/**
 * A version's place in the numbering, for the "is not newer" rail.
 *
 * A release is newer than any prerelease of the same numbers, and the lanes are ordered
 * `dev` < `alpha` < `beta` < `rc` — Composer's own order, which is what decides whether a
 * prerelease can be cut and what a consumer's constraint matches.
 */
function compareVersions(string $left, string $right): int
{
    $leftParts = parseVersion($left);
    $rightParts = parseVersion($right);

    $comparison = version_compare($leftParts['base'], $rightParts['base']);

    if ($comparison !== 0) {
        return $comparison;
    }

    if ($leftParts['lane'] === $rightParts['lane']) {
        return $leftParts['number'] <=> $rightParts['number'];
    }

    $rank = ['dev' => 0, 'alpha' => 1, 'beta' => 2, 'rc' => 3, null => 4];

    return ($rank[$leftParts['lane']] ?? 4) <=> ($rank[$rightParts['lane']] ?? 4) ?: $leftParts['number'] <=> $rightParts['number'];
}

/**
 * The newest of these versions, with `$fallback` as the floor.
 */
function maxVersion(array $versions, string $fallback): string
{
    $newest = $fallback;

    foreach ($versions as $version) {
        if (compareVersions($version, $newest) > 0) {
            $newest = $version;
        }
    }

    return $newest;
}

/**
 * The next free number in a lane, counted from the tags rather than remembered.
 */
function nextLaneNumber(string $root, string $line, string $lane): int
{
    $highest = 0;

    foreach (allVersions($root) as $version) {
        if (baseOf($version) !== $line || laneOf($version) !== $lane) {
            continue;
        }

        $highest = max($highest, parseVersion($version)['number']);
    }

    return $highest + 1;
}

/**
 * How loud a bump is, so two of them can be compared.
 */
function size(string $kind): int
{
    return match ($kind) {
        'major' => 3,
        'minor' => 2,
        default => 1,
    };
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers — the changelog
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The topmost `## Unreleased` section: its heading line, its body, and where the heading sits in
 * the file.
 *
 * The heading and the body are captured apart so that promoting something replaces the heading
 * and leaves the notes where they are. The body keeps its leading blank line, which is why the
 * promoted heading writes its own spacing rather than inheriting it.
 *
 * `start` is where the heading begins and `end` is where the whole section stops — the body
 * included — so a caller can rebuild the file around it without the notes arriving twice.
 *
 * @return array{heading: string, body: string, start: int, end: int}|null
 */
function unreleased(string $content): ?array
{
    if (preg_match('/^(##[ \t]+\[?Unreleased\]?[ \t]*\n?)(.*?)(?=^##[ \t]|\z)/ms', $content, $match, PREG_OFFSET_CAPTURE) !== 1) {
        return null;
    }

    return [
        'heading' => rtrim($match[1][0]),
        'body' => $match[2][0],
        'start' => $match[1][1],
        'end' => $match[0][1] + strlen($match[0][0]),
    ];
}

/**
 * How many bulleted entries a section holds — the number the plan prints, and the one an empty
 * Unreleased section is refused for.
 */
function countBullets(string $body): int
{
    return preg_match_all('/^\s*[-*]\s+\S/m', $body);
}

/**
 * The `###` headings of a section, which are the Keep a Changelog categories the versioning
 * policy is written against.
 *
 * @return list<string>
 */
function headings(string $body): array
{
    preg_match_all('/^###\s+(.+?)\s*$/m', $body, $matches);

    return array_map(trim(...), $matches[1]);
}

/**
 * The body of one `###` section, up to the next heading.
 */
function sectionBody(string $body, string $heading): string
{
    if (preg_match('/^###\s+'.preg_quote($heading, '/').'\s*$(.*?)(?=^###\s|\z)/ms', $body, $match) !== 1) {
        return '';
    }

    return $match[1];
}

/**
 * Every version the changelog documents, without the `v`.
 *
 * @return list<string>
 */
function documentedVersions(string $content): array
{
    preg_match_all('/^##\s+\[?v?(\d+\.\d+\.\d+(?:-[\w.]+)?)\]?/m', $content, $matches);

    return array_values(array_unique($matches[1]));
}

/**
 * The changelog with its Unreleased notes promoted to `$version`, and a fresh empty Unreleased
 * section left above them.
 *
 * The notes are moved, not copied. The heading style follows the file: this changelog writes
 * `## [v0.2.0] - 2025-09-09`, so a promoted heading is bracketed and carries the `v` the tags
 * carry, and a file that writes neither keeps its own style.
 *
 * @return array{content: string, heading: string}|null
 */
function promote(string $content, string $version, string $date): ?array
{
    $section = unreleased($content);

    if ($section === null) {
        return null;
    }

    // The style comes from the file's own *released* headings, not from the Unreleased one: the
    // released headings are the ones a reader already knows, and the Unreleased heading is the
    // one this script writes. A file with no released heading yet is followed only as far as
    // its Unreleased heading can say, which is whether it is bracketed.
    $bracketed = str_contains($section['heading'], '[');
    $prefixed = false;

    if (preg_match('/^##[ \t]+(\[)?(v)?\d+\.\d+\.\d+/m', $content, $released) === 1) {
        $bracketed = ($released[1] ?? '') === '[';
        $prefixed = ($released[2] ?? '') === 'v';
    }

    $heading = match (true) {
        $bracketed && $prefixed => '## [v'.$version.'] - '.$date,
        $bracketed => '## ['.$version.'] - '.$date,
        $prefixed => '## v'.$version.' - '.$date,
        default => '## '.$version.' - '.$date,
    };

    $before = substr($content, 0, $section['start']);
    $tail = ltrim(substr($content, $section['end']), "\n");

    // Trimmed at both ends, not just the right: the body arrives with the blank line that follows
    // the Unreleased heading on its front, and the two headings this assembles around it already
    // write their own spacing, so keeping it would leave two blank lines under the promoted one.
    $body = trim($section['body'], "\n");

    // "\n" rather than PHP_EOL throughout: the content is normalised to LF on the way in and
    // converted back on the way out, so a PHP_EOL here would arrive as a doubled return.
    $promoted = $before.'## Unreleased'."\n\n".$heading."\n\n".$body
        .($tail === '' ? "\n" : "\n\n".$tail);

    return ['content' => linkReferences($promoted, $version), 'heading' => $heading];
}

/**
 * Keep a Changelog's compare links, when the file keeps them.
 *
 * A file with no `[Unreleased]:` reference gets nothing appended: this changelog has none, and
 * inventing a link style for it would put two conventions in one file. A reference whose shape
 * this does not recognise is left exactly as it is, because a URL that is nearly right is
 * worse than one nobody touched.
 */
function linkReferences(string $content, string $version): string
{
    if (preg_match('/^\[Unreleased\]:\s*(\S+)\s*$/m', $content, $match) !== 1) {
        return $content;
    }

    $url = $match[1];

    if (preg_match('/^(.*\/compare\/)(v[\w.]+)\.\.\.HEAD$/', $url, $parts) !== 1) {
        return $content;
    }

    $content = str_replace($url, $parts[1].'v'.$version.'...HEAD', $content);

    return rtrim($content, "\n")."\n".'[v'.$version.']: '.$parts[1].$parts[2].'...v'.$version."\n";
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers — the weighing
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The three signals, each read on its own. The loudest wins, and a signal that cannot be read
 * costs a second opinion and nothing else — never a lower bump.
 *
 * @return array<string, array{severity: string, evidence: string}|null>
 */
function weigh(string $root, ?string $base, string $unreleased): array
{
    return [
        'CHANGELOG' => changelogSignal($unreleased),
        'commits' => commitSignal($root, $base),
        'surface' => surfaceSignal($root, $base),
    ];
}

/**
 * The `###` headings of the Unreleased notes.
 *
 * `### Removed` is the notes declaring a breaking change, and no bump flag talks the script out
 * of it: an entry filed under it is what the release is.
 *
 * @return array{severity: string, evidence: string}|null
 */
function changelogSignal(string $unreleased): ?array
{
    if (countBullets($unreleased) === 0) {
        return null;
    }

    $severity = 'patch';
    $evidence = [];

    foreach (headings($unreleased) as $heading) {
        $evidence[] = '### '.$heading.' ('.countBullets(sectionBody($unreleased, $heading)).')';
        $severity = louder($severity, severityOfHeading($heading));
    }

    if (str_contains($unreleased, 'BREAKING')) {
        $severity = louder($severity, 'breaking');
    }

    return [
        'severity' => $severity,
        'evidence' => $evidence === [] ? countBullets($unreleased).' entries, uncategorised' : implode(', ', $evidence),
    ];
}

function severityOfHeading(string $heading): string
{
    $normalised = strtolower(trim($heading));

    return match (true) {
        str_starts_with($normalised, 'removed') => 'breaking',
        str_contains($normalised, 'breaking') => 'breaking',
        str_starts_with($normalised, 'added') => 'minor',
        str_starts_with($normalised, 'changed') => 'minor',
        str_starts_with($normalised, 'deprecated') => 'minor',
        default => 'patch',
    };
}

/**
 * The commits since the last tag, read as Conventional Commits.
 *
 * A subject with no `type:` prefix reads as a patch: it cannot raise the bump by accident, and
 * it cannot lower one either — the notes and the surface still say what they say. Release
 * commits and merges are skipped, because they describe the release rather than the change.
 *
 * @return array{severity: string, evidence: string}|null
 */
function commitSignal(string $root, ?string $base): ?array
{
    $log = git($root, ['log', '--no-merges', '--format=%s%x1f%b%x1e', $base === null ? 'HEAD' : $base.'..HEAD']);

    if ($log['exit'] !== 0) {
        return null;
    }

    $severity = 'patch';
    $count = 0;

    foreach (explode("\x1e", $log['output']) as $entry) {
        if (trim($entry) === '') {
            continue;
        }

        [$subject, $body] = array_pad(explode("\x1f", $entry, 2), 2, '');
        $subject = trim($subject);

        if (preg_match('/^(Release v|Merge )/', $subject) === 1) {
            continue;
        }

        $count++;

        if (str_contains($body, 'BREAKING CHANGE:') || preg_match('/^[a-z]+(\(.+\))?!:/', $subject) === 1) {
            $severity = louder($severity, 'breaking');

            continue;
        }

        if (preg_match('/^feat(\(.+\))?:/', $subject) === 1) {
            $severity = louder($severity, 'minor');
        }
    }

    return $count === 0
        ? null
        : ['severity' => $severity, 'evidence' => $count.' commit(s)'.($base === null ? '' : ' since '.$base)];
}

/**
 * The public shape of `src/` and `config/` at HEAD against the last tag.
 *
 * Read with PHP's tokenizer rather than a parser, so this script runs in a bare checkout: what
 * the signal needs is names and shapes, not a tree. A symbol that disappeared is breaking —
 * that is the change a consumer cannot survive, and the one a patch bump would ship. A symbol
 * that appeared is a minor. A public method whose required arguments grew is breaking, which is
 * the one shape change the tokens are read for; a method whose *behaviour* changed is not
 * visible here at all, and the notes are where that is declared.
 *
 * @return array{severity: string, evidence: string}|null
 */
function surfaceSignal(string $root, ?string $base): ?array
{
    $now = publicSurface($root, null);

    if ($now === []) {
        return null;
    }

    if ($base === null) {
        return ['severity' => 'patch', 'evidence' => count($now).' public symbol(s), with no tag to compare against'];
    }

    $then = publicSurface($root, $base);

    $gone = array_values(array_diff(array_keys($then), array_keys($now)));
    $narrowed = [];

    foreach ($now as $name => $required) {
        if (isset($then[$name]) && $required > $then[$name]) {
            $narrowed[] = $name;
        }
    }

    if ($gone !== [] || $narrowed !== []) {
        return [
            'severity' => 'breaking',
            'evidence' => count($gone).' removed, '.count($narrowed).' narrowed: '.implode(', ', array_slice([...$gone, ...$narrowed], 0, 3)),
        ];
    }

    $appeared = array_values(array_diff(array_keys($now), array_keys($then)));

    if ($appeared !== []) {
        return ['severity' => 'minor', 'evidence' => count($appeared).' symbol(s) appeared: '.implode(', ', array_slice($appeared, 0, 3))];
    }

    return ['severity' => 'patch', 'evidence' => 'no public symbol changed'];
}

/**
 * Every public symbol, keyed by `file::name`, with the number of required arguments it takes as
 * its shape. A higher number than the same name at the last tag is a parameter that became
 * required.
 *
 * @return array<string, int>
 */
function publicSurface(string $root, ?string $revision): array
{
    $symbols = [];

    foreach (surfaceFiles($root, $revision) as $file) {
        if ($revision === null) {
            $contents = @file_get_contents($root.'/'.$file);
            $contents = $contents === false ? null : $contents;
        } else {
            $blob = git($root, ['show', $revision.':'.$file]);
            $contents = $blob['exit'] === 0 ? $blob['output'] : null;
        }

        if ($contents === null || $contents === '') {
            continue;
        }

        foreach (symbolsIn($contents) as $name => $shape) {
            $symbols[$file.'::'.$name] = $shape;
        }
    }

    ksort($symbols);

    return $symbols;
}

/**
 * The PHP files the surface is read from: the package's own source and its config.
 *
 * Tracked files only, so the signal is about the tree being released rather than about a scratch
 * file somebody left in it.
 *
 * @return list<string>
 */
function surfaceFiles(string $root, ?string $revision): array
{
    $listed = $revision === null
        ? git($root, ['ls-files', '--', 'src', 'config'])
        : git($root, ['ls-tree', '-r', '--name-only', $revision, '--', 'src', 'config']);

    $files = [];

    foreach (explode("\n", trim($listed['output'])) as $file) {
        $file = trim($file);

        if ($file !== '' && str_ends_with($file, '.php')) {
            $files[] = $file;
        }
    }

    return $files;
}

/**
 * The public names one file declares: its classes, public methods, public properties, public
 * constants, enum cases, and — in a file that declares no class at all, which is what a config
 * file is — the string keys of its arrays.
 *
 * The config keys are read only in a class-free file, because `match ($x) { 'gzip' => … }` is a
 * string key followed by an arrow too, and a `match` arm is not configuration.
 *
 * @return array<string, int>
 */
function symbolsIn(string $contents): array
{
    $tokens = token_get_all($contents);
    $count = count($tokens);

    $symbols = [];
    $namespace = '';
    $class = null;
    $declaredClass = false;
    $depth = 0;
    $classDepth = null;
    $methodDepth = null;
    $pendingClass = false;
    $pendingMethod = false;

    for ($index = 0; $index < $count; $index++) {
        $token = $tokens[$index];
        $id = is_array($token) ? $token[0] : null;
        $text = is_array($token) ? $token[1] : $token;

        if ($id === T_NAMESPACE) {
            $namespace = (string) nameAfter($tokens, $index, [T_STRING, T_NAME_QUALIFIED]);

            continue;
        }

        if ($id === T_CLASS || $id === T_INTERFACE || $id === T_TRAIT || $id === T_ENUM) {
            $previous = previousMeaningful($tokens, $index);

            // `Foo::class` and `new class` are not declarations.
            if ($previous === '::' || $previous === T_NEW) {
                continue;
            }

            $name = nameAfter($tokens, $index, [T_STRING]);

            if ($name !== null && $classDepth === null) {
                $class = $namespace === '' ? $name : $namespace.'\\'.$name;
                $symbols['class '.$class] = 0;
                $declaredClass = true;
                $pendingClass = true;
            }

            continue;
        }

        if ($text === '{') {
            $depth++;

            if ($pendingClass) {
                $classDepth = $depth;
                $pendingClass = false;
            } elseif ($methodDepth === null && $pendingMethod) {
                $methodDepth = $depth;
                $pendingMethod = false;
            }

            continue;
        }

        if ($text === '}') {
            if ($methodDepth !== null && $depth === $methodDepth) {
                $methodDepth = null;
            } elseif ($classDepth !== null && $depth === $classDepth) {
                $class = null;
                $classDepth = null;
            }

            $depth--;

            continue;
        }

        if ($id === T_FUNCTION) {
            $name = nameAfter($tokens, $index, [T_STRING]);
            $visibility = visibilityOf($tokens, $index);

            if ($name !== null && $visibility === 'public' && $class !== null && $methodDepth === null) {
                $symbols['function '.$class.'::'.$name] = requiredArguments($tokens, $index);
            }

            // Every method body is skipped, private ones included: a `public` looking thing
            // inside a private method is a local variable, not a member.
            $pendingMethod = $name !== null && $class !== null;

            continue;
        }

        if ($class === null) {
            if (! $declaredClass && $id === T_CONSTANT_ENCAPSED_STRING && $depth >= 1 && isKey($tokens, $index)) {
                $symbols['config '.trim($token[1], "'\"")] = 0;
            }

            continue;
        }

        if ($methodDepth !== null) {
            continue;
        }

        if ($id === T_CONST && isPublicMember($tokens, $index)) {
            $name = nameAfter($tokens, $index, [T_STRING]);

            if ($name !== null) {
                $symbols['const '.$class.'::'.$name] = 0;
            }

            continue;
        }

        if ($id === T_CASE) {
            $name = nameAfter($tokens, $index, [T_STRING]);

            if ($name !== null) {
                $symbols['case '.$class.'::'.$name] = 0;
            }

            continue;
        }

        if ($id === T_VARIABLE && isPublicMember($tokens, $index)) {
            $symbols['property '.$class.'::'.$text] = 0;
        }
    }

    return $symbols;
}

/**
 * A public method's required argument count, read from its parameter list: the arguments up to
 * the first one with a default.
 */
function requiredArguments(array $tokens, int $index): int
{
    $depth = 0;
    $required = 0;
    $optional = false;
    $seen = false;

    for ($index++; $index < count($tokens); $index++) {
        $token = $tokens[$index];
        $text = is_array($token) ? $token[1] : $token;

        if ($text === '(') {
            $depth++;

            continue;
        }

        if ($text === ')') {
            $depth--;

            if ($depth === 0) {
                return $seen && ! $optional ? $required + 1 : $required;
            }

            continue;
        }

        if ($depth !== 1) {
            continue;
        }

        if ($text === '=') {
            $optional = true;

            continue;
        }

        if ($text === ',') {
            $required += $seen && ! $optional ? 1 : 0;
            $seen = false;
            $optional = false;

            continue;
        }

        if (is_array($token) && $token[0] === T_VARIABLE) {
            $seen = true;
        }
    }

    return $required;
}

/**
 * Whether the member starting at `$index` is public — declared so, or carrying no visibility at
 * all, which is public.
 */
function visibilityOf(array $tokens, int $index): string
{
    for ($index--; $index >= 0; $index--) {
        $token = $tokens[$index];

        if (! is_array($token)) {
            break;
        }

        if ($token[0] === T_PUBLIC) {
            return 'public';
        }

        if ($token[0] === T_PRIVATE || $token[0] === T_PROTECTED) {
            return 'other';
        }

        if (! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_STATIC, T_FINAL, T_ABSTRACT], true)) {
            break;
        }
    }

    return 'public';
}

/**
 * Whether a `const` or a property at `$index` is public. Neither carries a modifier in every
 * file that writes one, and the ones that do not are public.
 */
function isPublicMember(array $tokens, int $index): bool
{
    for ($index--; $index >= 0; $index--) {
        $token = $tokens[$index];

        if (! is_array($token)) {
            return $token === '{' || $token === ';';
        }

        if ($token[0] === T_PRIVATE || $token[0] === T_PROTECTED) {
            return false;
        }

        if ($token[0] === T_PUBLIC || $token[0] === T_FINAL || $token[0] === T_CONST) {
            return true;
        }

        if (! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_STATIC, T_ABSTRACT, T_READONLY], true)) {
            return false;
        }
    }

    return true;
}

/**
 * Whether a string token sits where a config key does: `'key' =>`.
 */
function isKey(array $tokens, int $index): bool
{
    for ($index++; $index < count($tokens); $index++) {
        $token = $tokens[$index];

        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT], true)) {
            continue;
        }

        return is_array($token) && $token[0] === T_DOUBLE_ARROW;
    }

    return false;
}

/**
 * The identifier after a keyword, skipping whitespace and comments.
 *
 * @param  list<int>  $kinds
 */
function nameAfter(array $tokens, int $index, array $kinds): ?string
{
    for ($index++; $index < count($tokens); $index++) {
        $token = $tokens[$index];

        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return is_array($token) && in_array($token[0], $kinds, true) ? $token[1] : null;
    }

    return null;
}

/**
 * The token id (or the character) before `$index`, skipping whitespace and comments.
 */
function previousMeaningful(array $tokens, int $index): int|string|null
{
    for ($index--; $index >= 0; $index--) {
        $token = $tokens[$index];

        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return is_array($token) ? $token[0] : $token;
    }

    return null;
}

/**
 * The loudest severity of the three.
 */
function loudest(array $signals): string
{
    $severity = 'patch';

    foreach ($signals as $signal) {
        if ($signal !== null) {
            $severity = louder($severity, $signal['severity']);
        }
    }

    return $severity;
}

function louder(string $left, string $right): string
{
    $order = ['patch' => 1, 'minor' => 2, 'breaking' => 3];

    return ($order[$right] ?? 1) > ($order[$left] ?? 1) ? $right : $left;
}

/**
 * The signal that forbade a declared bump, for the refusal message.
 */
function loudestEvidence(array $signals): string
{
    $worst = null;

    foreach ($signals as $name => $signal) {
        if ($signal === null) {
            continue;
        }

        if ($worst === null || louder($worst['severity'], $signal['severity']) === $signal['severity']) {
            $worst = ['name' => $name, 'severity' => $signal['severity'], 'evidence' => $signal['evidence']];
        }
    }

    return $worst === null
        ? 'no signal could be read, so the bump could not be weighed'
        : sprintf('%s (%s): %s', $worst['name'], $worst['severity'], $worst['evidence']);
}
