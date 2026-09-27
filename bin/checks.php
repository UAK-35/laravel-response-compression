#!/usr/bin/env php
<?php

declare(strict_types=1);

use PhpParser\Error;
use PhpParser\ParserFactory;

/**
 * bin/checks.php
 *
 * One entry point for every check this package can run: PHP syntax, an
 * independent AST parse of the same files, the composer.json schema, the
 * platform requirements, the workflow YAML, PHPStan, Pint, Rector and Pest.
 *
 * WHY THIS EXISTS IN ADDITION TO `composer test`
 * ----------------------------------------------
 * `composer test` covers four gates — rector, pint, phpstan and pest — and the
 * other five are otherwise only looked at by hand, after something has already
 * broken: a per-file `php -l` pass, a parse of every file by nikic/php-parser,
 * `composer validate --strict`, `composer check-platform-reqs`, and the
 * workflow YAML. Running them together gives one command, one summary and one
 * exit code to trust.
 *
 * `composer test:unit` is deliberately NOT invoked from here, even though it is
 * the same Pest binary: it asks for a code coverage driver, and a machine with
 * neither PCOV nor Xdebug would see this gate fail for a reason that is about
 * the machine rather than about the code. CI runs both.
 *
 * USAGE
 * -----
 *   php bin/checks.php                  run everything, print only what failed
 *   php bin/checks.php --verbose        stream the output of every check
 *   php bin/checks.php --only=pint,tests
 *   php bin/checks.php --audit          add `composer audit` (needs the network)
 *   php bin/checks.php --require-all    fail instead of skipping when a tool is missing
 *   php bin/checks.php --list           list the checks without running anything
 *   php bin/checks.php --help
 *
 * Exit code is 0 when every check passed, 1 when any failed (or was skipped
 * under --require-all), 2 for a usage error.
 *
 * PORTABILITY
 * -----------
 * Tools are invoked as `PHP_BINARY <vendor entry file>` rather than through the
 * `vendor/bin` shims, because a shim is a shell script on Unix and a .bat on
 * Windows and neither is reliably executable from another process. The same
 * invocation works on Windows and Linux, and a missing tool is reported as a
 * skip rather than crashing the run.
 */
$root = str_replace('\\', '/', dirname(__DIR__));

// ─────────────────────────────────────────────────────────────────────────────
// CLI
// ─────────────────────────────────────────────────────────────────────────────

$options = [
    'verbose' => false,
    'audit' => false,
    'require-all' => false,
    'list' => false,
    'only' => [],
];

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        usage();
        exit(0);
    }

    if ($argument === '--verbose' || $argument === '-v') {
        $options['verbose'] = true;

        continue;
    }

    if ($argument === '--audit') {
        $options['audit'] = true;

        continue;
    }

    if ($argument === '--require-all') {
        $options['require-all'] = true;

        continue;
    }

    if ($argument === '--list') {
        $options['list'] = true;

        continue;
    }

    if (str_starts_with($argument, '--only=')) {
        $options['only'] = array_values(array_filter(array_map(
            'trim',
            explode(',', substr($argument, 7)),
        )));

        continue;
    }

    fwrite(STDERR, "Unknown option: {$argument}".PHP_EOL.PHP_EOL);
    usage();
    exit(2);
}

// ─────────────────────────────────────────────────────────────────────────────
// Environment
// ─────────────────────────────────────────────────────────────────────────────

if (is_file($root.'/vendor/autoload.php')) {
    require $root.'/vendor/autoload.php';
}

// bin/ is in the php -l and AST passes on purpose: this script is run far more
// often than it is read, and a syntax error in it is otherwise only found by
// running it.
$phpFiles = phpFiles([$root.'/src', $root.'/tests', $root.'/config', $root.'/bin']);
$composer = composerCommand($root);
$yamlFiles = yamlFiles($root);

// Whether this repository contains composer.lock. Asked once, here, because the
// schema check is the only one that can be answered differently for a lock the
// checkout has and the repository does not — see composerValidateCommand().
$lockIsShipped = commitsLockFile($root);

$tools = [
    'pint' => $root.'/vendor/laravel/pint/builds/pint',
    'phpstan' => $root.'/vendor/phpstan/phpstan/phpstan.phar',
    'rector' => $root.'/vendor/rector/rector/bin/rector',
    'pest' => $root.'/vendor/pestphp/pest/bin/pest',
    'yaml-lint' => $root.'/vendor/symfony/yaml/Resources/bin/yaml-lint',
];

// ─────────────────────────────────────────────────────────────────────────────
// The checks
// ─────────────────────────────────────────────────────────────────────────────

$level = phpstanLevel($root);

$checks = [
    'syntax' => [
        'title' => 'PHP syntax (php -l)',
        'skip' => $phpFiles === [] ? 'no PHP files found' : null,
        'run' => static fn (): array => checkSyntax($root, $phpFiles),
        'note' => static function (array $result) use ($phpFiles): string {
            $failed = preg_match_all('/Errors parsing/', $result['output']);

            return $failed === 0
                ? count($phpFiles).' files, no syntax errors'
                : "{$failed} of ".count($phpFiles).' files failed';
        },
    ],
    'ast' => [
        'title' => 'AST parse (nikic/php-parser)',
        'skip' => ! class_exists(ParserFactory::class) ? 'nikic/php-parser is not installed' : null,
        'run' => static fn (): array => checkAst($root, $phpFiles),
        'note' => static function (array $result) use ($phpFiles): string {
            return $result['exit'] === 0
                ? count($phpFiles).' files parsed'
                : lastLine($result['output']);
        },
    ],
    'schema' => [
        'title' => 'composer.json schema (validate --strict)',
        'skip' => $composer === null ? 'composer is not available (set COMPOSER_BINARY)' : null,
        'run' => static fn (): array => runCommand(composerValidateCommand($composer, $lockIsShipped), $root),
        'note' => static function (array $result) use ($lockIsShipped): string {
            // composer prints its lock-file section whether or not it was asked to
            // check it, so a run that deliberately skipped the lock would otherwise
            // summarise itself with a warning nothing acted on. The verdict is the
            // exit code's; the note says what the check actually covered.
            if ($lockIsShipped || $result['exit'] !== 0) {
                return composerReason($result['output']);
            }

            return 'composer.json is valid; composer.lock is not part of this repository, so it is not checked';
        },
    ],
    'platform' => [
        'title' => 'Platform requirements (check-platform-reqs)',
        'skip' => $composer === null ? 'composer is not available (set COMPOSER_BINARY)' : null,
        'run' => static fn (): array => runCommand([...$composer ?? [], 'check-platform-reqs'], $root),
        'note' => static function (array $result): string {
            $satisfied = preg_match_all('/\bsuccess\b/', $result['output']);

            return $satisfied === 0
                ? composerReason($result['output'])
                : "{$satisfied} platform requirements satisfied";
        },
    ],
    'yaml' => [
        'title' => 'Workflow YAML (yaml-lint)',
        'skip' => match (true) {
            $yamlFiles === [] => 'no YAML outside vendor/',
            ! is_file($tools['yaml-lint']) => 'symfony/yaml is not installed',
            default => null,
        },
        'run' => static fn (): array => runCommand(
            [PHP_BINARY, $tools['yaml-lint'], '--no-ansi', ...$yamlFiles],
            $root,
        ),
        'note' => static function (array $result) use ($yamlFiles): string {
            // yaml-lint says nothing at all when every file parses, so the count is
            // the only evidence that it read anything.
            return $result['exit'] === 0
                ? count($yamlFiles).' file(s) valid'
                : lastLine($result['output']);
        },
    ],
    'phpstan' => [
        'title' => "Static analysis (phpstan, level {$level})",
        'skip' => is_file($tools['phpstan']) ? null : 'phpstan is not installed',
        'run' => static fn (): array => runCommand(
            [PHP_BINARY, $tools['phpstan'], 'analyse', '--no-progress', '--no-ansi'],
            $root,
        ),
    ],
    'pint' => [
        'title' => 'Code style (pint --test)',
        'skip' => is_file($tools['pint']) ? null : 'laravel/pint is not installed',
        'run' => static fn (): array => runCommand([PHP_BINARY, $tools['pint'], '--test'], $root),
    ],
    'rector' => [
        'title' => 'Automated refactoring (rector --dry-run)',
        'skip' => is_file($tools['rector']) ? null : 'rector is not installed',
        'run' => static fn (): array => runCommand(
            [PHP_BINARY, $tools['rector'], '--dry-run', '--no-progress-bar'],
            $root,
        ),
    ],
    'tests' => [
        'title' => 'Test suite (pest)',
        'skip' => is_file($tools['pest']) ? null : 'pest is not installed',
        'run' => static fn (): array => runCommand([PHP_BINARY, $tools['pest']], $root),
        'note' => static function (array $result): string {
            // Pest ends with its duration, which says nothing about whether it
            // passed; the count it printed a line earlier does.
            if (preg_match('/^\s*Tests:\s*(.+)$/m', stripAnsi($result['output']), $matches) === 1) {
                return trim($matches[1]).' (coverage floor is measured by composer test:unit)';
            }

            return lastLine($result['output']);
        },
    ],
];

if ($options['audit']) {
    $checks['audit'] = [
        'title' => 'Security advisories (composer audit)',
        'skip' => $composer === null ? 'composer is not available' : null,
        'run' => static fn (): array => runCommand(
            [...$composer ?? [], 'audit', '--no-interaction'],
            $root,
        ),
        'note' => static fn (array $result): string => composerReason($result['output']),
    ];
}

if ($options['only'] !== []) {
    $unknown = array_diff($options['only'], array_keys($checks));

    if ($unknown !== []) {
        fwrite(STDERR, 'Unknown check(s): '.implode(', ', $unknown).PHP_EOL);
        fwrite(STDERR, 'Known: '.implode(', ', array_keys($checks)).PHP_EOL);
        exit(2);
    }

    $checks = array_intersect_key($checks, array_flip($options['only']));
}

if ($options['list']) {
    foreach ($checks as $key => $check) {
        $skip = $check['skip'] === null ? '' : "  (skipped when: {$check['skip']})";

        printf('%-10s %s%s%s', $key, $check['title'], $skip, PHP_EOL);
    }

    exit(0);
}

// ─────────────────────────────────────────────────────────────────────────────
// Run
// ─────────────────────────────────────────────────────────────────────────────

$results = [];
$startedAt = microtime(true);

foreach ($checks as $key => $check) {
    if ($options['verbose']) {
        echo PHP_EOL."▶ {$check['title']}".PHP_EOL;
    }

    if ($check['skip'] !== null) {
        $results[$key] = [
            'status' => $options['require-all'] ? 'FAIL' : 'SKIP',
            'note' => $options['require-all']
                ? "could not run: {$check['skip']}"
                : $check['skip'],
            'seconds' => 0.0,
            'output' => '',
        ];

        continue;
    }

    $checkStartedAt = microtime(true);
    $result = $check['run']();
    $seconds = microtime(true) - $checkStartedAt;

    if ($options['verbose']) {
        echo $result['output'];
    }

    // A check may lend a `note` of its own, for a summary line clearer than the last
    // line of its output. It is handed the whole result — the verdict as well as the
    // output — so a note can say what was and was not checked rather than guess it
    // from the prose. The output arrives stripped of ANSI: a note that counts words
    // would otherwise miss them, because a colour reset ends in `m` and `m` is a
    // word character.
    $note = isset($check['note'])
        ? $check['note'](['exit' => $result['exit'], 'output' => stripAnsi($result['output'])])
        : null;

    $results[$key] = [
        'status' => $result['exit'] === 0 ? 'PASS' : 'FAIL',
        'note' => $note ?? lastLine($result['output']),
        'seconds' => $seconds,
        'output' => $result['output'],
    ];
}

$elapsed = microtime(true) - $startedAt;

// ─────────────────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────────────────

$passed = count(array_filter($results, static fn (array $r): bool => $r['status'] === 'PASS'));
$failed = array_keys(array_filter($results, static fn (array $r): bool => $r['status'] === 'FAIL'));
$skipped = count(array_filter($results, static fn (array $r): bool => $r['status'] === 'SKIP'));
$total = count($results);

echo PHP_EOL.str_repeat('─', 84).PHP_EOL;
printf(
    '%d check%s: %d passed, %d failed, %d skipped — %.1fs'.PHP_EOL,
    $total,
    $total === 1 ? '' : 's',
    $passed,
    count($failed),
    $skipped,
    $elapsed,
);
echo str_repeat('─', 84).PHP_EOL;

foreach ($results as $key => $result) {
    printf(
        '%-10s %-5s %6.1fs  %s'.PHP_EOL,
        $key,
        $result['status'],
        $result['seconds'],
        $result['note'],
    );
}

if ($failed !== []) {
    foreach ($failed as $key) {
        $output = rtrim(stripAnsi($results[$key]['output']));

        if ($output === '') {
            continue;
        }

        echo PHP_EOL.str_repeat('─', 84).PHP_EOL;
        echo "✗ {$checks[$key]['title']}".PHP_EOL;
        echo str_repeat('─', 84).PHP_EOL;
        echo $output.PHP_EOL;
    }

    echo PHP_EOL.'FAILED: '.implode(', ', $failed).PHP_EOL;

    exit(1);
}

echo PHP_EOL.'All checks passed.'.PHP_EOL;

exit(0);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers — argument handling
// ─────────────────────────────────────────────────────────────────────────────

function usage(): void
{
    echo <<<'TXT'
    bin/checks.php — run every check this package can run, with one summary.

    Usage:
      php bin/checks.php [options]

    Options:
      -v, --verbose     Stream the output of every check, not just failures.
          --only=KEYS   Run a subset, comma separated (see --list).
          --audit       Also run `composer audit` (needs network access).
          --require-all Treat a missing tool as a failure instead of a skip.
          --list        List the checks and their skip conditions.
      -h, --help        Show this help.

    Exit code: 0 all green, 1 anything failed, 2 usage error.
    Note: the coverage floor lives in `composer test:unit`, which needs a
    coverage driver and is run by CI separately.

    TXT;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers — process management
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Run a command with stdout and stderr merged.
 *
 * The command is passed as an array so no shell is involved: no quoting rules
 * to get wrong on Windows, and no shell profile to depend on.
 *
 * @param  list<string>  $command
 * @return array{exit: int, output: string}
 */
function runCommand(array $command, string $cwd): array
{
    if ($command === []) {
        return ['exit' => 127, 'output' => 'nothing to run'];
    }

    $process = @proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes,
        $cwd,
    );

    if (! is_resource($process)) {
        return ['exit' => 127, 'output' => 'could not start: '.implode(' ', $command)];
    }

    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return ['exit' => proc_close($process), 'output' => $output];
}

/**
 * The composer executable to use: an explicit COMPOSER_BINARY, the local
 * composer.phar (gitignored, present in a dev checkout) or composer on PATH.
 *
 * @return list<string>|null
 */
function composerCommand(string $root): ?array
{
    $binary = getenv('COMPOSER_BINARY');

    if (is_string($binary) && $binary !== '' && is_file($binary)) {
        return [PHP_BINARY, $binary];
    }

    if (is_file($root.'/composer.phar')) {
        return [PHP_BINARY, $root.'/composer.phar'];
    }

    // Composer installed globally: `composer --version` decides whether it is
    // really reachable, so an absent binary becomes a skip rather than a fail.
    foreach (composerOnPath($root) as $candidate) {
        if (runCommand([...$candidate, '--version'], $root)['exit'] === 0) {
            return $candidate;
        }
    }

    return null;
}

/**
 * How a global `composer` may be reached, when neither COMPOSER_BINARY nor a
 * composer.phar in this checkout has named it.
 *
 * On Unix a global install is an executable with a shebang, and one attempt is
 * all that is needed. On Windows it is a `.bat` shim, and a shim is unreliable
 * from a child process twice over:
 *
 *  - `proc_open` goes through CreateProcess, which cannot start a batch file at
 *    all — the process is never created, so the check would be skipped on a
 *    machine that has composer.
 *  - `cmd.exe` started with the bare name `composer` hands the batch file a `%0`
 *    of just `composer`, and the `%~dp0` that every such shim uses to find its
 *    own composer.phar then resolves to the *caller's* directory. The shim works
 *    from a checkout that has a phar and fails from one that does not — which is
 *    precisely the case this fallback exists for. Measured, not assumed:
 *    `cmd.exe /C composer --version` reports `Could not open input file:
 *    <cwd>\composer.phar` from a directory without one.
 *
 * `where` gives the shim's real path, which fixes both: the phar beside the shim
 * is named directly, and if there is none, cmd.exe is given the shim in full so
 * its `%~dp0` is its own directory.
 *
 * @return list<list<string>>
 */
function composerOnPath(string $root): array
{
    if (PHP_OS_FAMILY !== 'Windows') {
        return [['composer']];
    }

    $candidates = [];

    foreach (preg_split('/\R/', trim(runCommand(['where', 'composer'], $root)['output'])) ?: [] as $shim) {
        if ($shim === '') {
            continue;
        }

        $phar = dirname($shim).'/composer.phar';

        $candidates[] = is_file($phar) ? [PHP_BINARY, $phar] : ['cmd.exe', '/C', $shim];
    }

    return [...$candidates, ['composer']];
}

/**
 * `composer validate --strict`, asking the same question CI asks.
 *
 * `--strict` fails on warnings as well as errors, and the one warning this
 * package meets locally and never in CI is the stale lock file. `.gitignore`
 * excludes `composer.lock`, so a checkout has one only because it was installed
 * into, while CI has one only because `composer install` wrote it moments
 * earlier — which is why CI's lock cannot be out of date and a developer's can
 * be, on the same commit, over a file the repository does not contain. Editing
 * `composer.json` then turns this check red on the machine that edited it,
 * without anyone touching a manifest by hand.
 *
 * So the lock is checked only when the repository contains one. A branch that
 * ships a lock has something to keep in step with the manifest; a branch that
 * ignores it has nothing for a local one to disagree with, and what is
 * validated is the manifest — the same question, and the same answer, as CI's.
 *
 * @param  list<string>|null  $composer  the composed `validate` invocation's prefix, or null
 * @param  bool  $lockIsShipped  whether the repository contains a composer.lock
 * @return list<string>
 */
function composerValidateCommand(?array $composer, bool $lockIsShipped): array
{
    $command = [...$composer ?? [], 'validate', '--strict'];

    if (! $lockIsShipped) {
        $command[] = '--no-check-lock';
    }

    return $command;
}

/**
 * Whether `composer.lock` is part of the repository rather than a local install
 * artefact.
 *
 * git is asked about the file instead of `.gitignore` being read, because the
 * question is whether the repository contains it: a tracked lock is in the
 * repository even where a pattern would ignore it, and an untracked one is the
 * checkout's own however it got there.
 *
 * A machine with no git — or with `composer.lock` in a checkout that is not a
 * repository — answers `false`. There is then no repository to have shipped a
 * lock, so the only lock a run could be comparing is this checkout's own, which
 * is the case the manifest-only question exists for.
 */
function commitsLockFile(string $root): bool
{
    return runCommand(['git', 'ls-files', '--error-unmatch', '--', 'composer.lock'], $root)['exit'] === 0;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers — file discovery
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Every .php file under the given roots, sorted, dot-directories skipped.
 *
 * @param  list<string>  $roots
 * @return list<string>
 */
function phpFiles(array $roots): array
{
    $files = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);

        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static fn (SplFileInfo $entry): bool => ! $entry->isDir()
                || ! str_starts_with($entry->getFilename(), '.'),
        );

        foreach (new RecursiveIteratorIterator($filter) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
    }

    sort($files);

    return $files;
}

/**
 * YAML that ships with this repository — `.github/` only, so vendor and any
 * fixture YAML are left alone.
 *
 * @return list<string>
 */
function yamlFiles(string $root): array
{
    $files = [];
    $directory = $root.'/.github';

    if (! is_dir($directory)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['yml', 'yaml'], true)) {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }

    sort($files);

    return $files;
}

/**
 * The level phpstan.neon.dist asks for, so the summary names what it enforced.
 */
function phpstanLevel(string $root): string
{
    $config = @file_get_contents($root.'/phpstan.neon.dist');

    if (is_string($config) && preg_match('/^\s*level:\s*(\S+)/m', $config, $matches) === 1) {
        return $matches[1];
    }

    return 'unknown';
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers — output
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The most informative line of a check's output: its last non-empty one, which
 * in every tool used here is the verdict ("Tests: 35 passed", "[OK] No errors",
 * "PASS 17 files", "./composer.json is valid").
 *
 * Progress dots and box-drawing rules are collapsed first, because a summary
 * line reading 'PASS  ............' tells you nothing.
 */
function lastLine(string $output): string
{
    $lines = [];

    foreach (explode("\n", stripAnsi($output)) as $line) {
        $line = trim((string) (preg_replace('/\s+/', ' ', $line) ?? ''));

        // A separator or progress line carries no verdict.
        if ($line === '' || preg_match('/^[.\-═─= ]+$/', $line) === 1) {
            continue;
        }

        $lines[] = (string) (preg_replace('/\.{4,}/', '…', $line) ?? $line);
    }

    if ($lines === []) {
        return '(no output)';
    }

    return clip((string) end($lines));
}

/**
 * Keep a summary line on one line of the terminal, whatever it is summarising.
 */
function clip(string $line): string
{
    return strlen($line) > 78 ? substr($line, 0, 77).'…' : $line;
}

/**
 * Why a failed composer command failed.
 *
 * Composer ends a failed run with its own synopsis — `validate [--no-check-all]
 * [--check-lock] …` — so the last line names the command's options and not the
 * reason. The reason is in the exception block it printed above it, after the
 * `In <file> line <n>:` marker, as the exception class and then its message; the
 * message is joined back into one sentence because composer wraps it to the
 * console width, and half a wrapped sentence is no more use than a synopsis.
 *
 * A failure composer reports without an exception, such as a lock file that is out
 * of date, has no such block: there the last line is the reason, as it usually is.
 */
function composerReason(string $output): string
{
    $message = [];
    $inException = false;

    foreach (explode("\n", stripAnsi($output)) as $line) {
        $line = trim((string) (preg_replace('/\s+/', ' ', $line) ?? ''));

        // Both of these end the part of the output that says anything about the
        // failure: a trace below it is phar paths, and a synopsis is the command's
        // own usage.
        if ($line === 'Exception trace:' || preg_match('/^[a-z][a-z0-9:_-]* \[/', $line) === 1) {
            break;
        }

        if (preg_match('/^In \S+ line \d+:$/', $line) === 1) {
            $inException = true;

            continue;
        }

        // A blank line, and the bare `[TypeError]` class marker that precedes the
        // message, are not part of it.
        if (! $inException || $line === '' || preg_match('/^\[[A-Za-z\\\\]+\]$/', $line) === 1) {
            continue;
        }

        $message[] = $line;
    }

    return $message === [] ? lastLine($output) : clip(implode(' ', $message));
}

/**
 * Strip ANSI escape sequences — PHPStan colours its table even when stdout is
 * not a terminal, and a note made entirely of a reset sequence looks like an
 * empty line.
 */
function stripAnsi(string $output): string
{
    return (string) preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $output);
}

/**
 * Path relative to the package root, for output that does not depend on where
 * the repository is checked out.
 */
function relative(string $root, string $path): string
{
    $path = str_replace('\\', '/', $path);

    return str_starts_with($path, $root.'/') ? substr($path, strlen($root) + 1) : $path;
}

// ─────────────────────────────────────────────────────────────────────────────
// Checks
// ─────────────────────────────────────────────────────────────────────────────

/**
 * `php -l` each file: the interpreter's own linter, which is the only thing
 * that catches problems the parser alone would accept.
 *
 * @param  list<string>  $files
 * @return array{exit: int, output: string}
 */
function checkSyntax(string $root, array $files): array
{
    $failures = [];

    foreach ($files as $file) {
        $result = runCommand([PHP_BINARY, '-l', $file], $root);

        if ($result['exit'] !== 0) {
            $failures[] = relative($root, $file).': '.trim($result['output']);
        }
    }

    return $failures === []
        ? ['exit' => 0, 'output' => count($files).' files, no syntax errors']
        : ['exit' => 1, 'output' => implode(PHP_EOL, $failures)];
}

/**
 * Parse every file with nikic/php-parser — the same parser Rector and PHPStan
 * read the tree with, run here in-process so a syntax problem is reported as a
 * file:line rather than as a wall of AST dump.
 *
 * @param  list<string>  $files
 * @return array{exit: int, output: string}
 */
function checkAst(string $root, array $files): array
{
    if (! class_exists(ParserFactory::class)) {
        return ['exit' => 1, 'output' => 'nikic/php-parser is not installed'];
    }

    $factory = new ParserFactory;
    $parser = $factory->createForNewestSupportedVersion();

    $failures = [];

    foreach ($files as $file) {
        $source = @file_get_contents($file);

        if ($source === false) {
            $failures[] = relative($root, $file).': unreadable';

            continue;
        }

        try {
            $parser->parse($source);
        } catch (Error $error) {
            $failures[] = sprintf(
                '%s:%d: %s',
                relative($root, $file),
                $error->getStartLine(),
                $error->getRawMessage(),
            );
        }
    }

    return $failures === []
        ? ['exit' => 0, 'output' => count($files).' files parsed']
        : ['exit' => 1, 'output' => implode(PHP_EOL, $failures)];
}
