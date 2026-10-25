#!/usr/bin/env php
<?php

declare(strict_types=1);

use PhpParser\Error;
use PhpParser\ParserFactory;
use Uak35\ResponseCompression\Tests\Support\Notes;
use Uak35\ResponseCompression\Tests\Support\TestMap;

/**
 * bin/checks.php
 *
 * One entry point for every check this package can run: PHP syntax, an
 * independent AST parse of the same files, the composer.json schema, the
 * platform requirements, the workflow YAML, PHPStan, Pint, Rector and Pest -
 * the toolchain probe, which asks the machine rather than the code - and the
 * agentic layer, which asks this repository whether it still agrees with
 * itself and whether every one of its readings still fires.
 *
 * WHY THIS EXISTS IN ADDITION TO `composer test`
 * ----------------------------------------------
 * `composer test` covers four gates — rector, pint, phpstan and pest — and the
 * other seven are otherwise only looked at by hand, after something has already
 * broken: a per-file `php -l` pass, a parse of every file by nikic/php-parser,
 * `composer validate --strict`, `composer check-platform-reqs`, the workflow
 * YAML, the toolchain probe, and the agentic layer's own account of itself.
 * Running them together gives one command, one summary and one exit code to trust.
 *
 * `composer test:unit` is deliberately NOT invoked from here, even though it is
 * the same Pest binary: it asks for a code coverage driver, and a machine with
 * neither PCOV nor Xdebug would see this gate fail for a reason that is about
 * the machine rather than about the code. CI runs both.
 *
 * The suite itself runs under `--parallel`, which is again what `composer test:unit`
 * already does: most of these tests are process spawns — a planted git repository, a
 * release script started against it — so a worker per file is the difference between
 * minutes and seconds. A narrowed run is not asked to: one file handed to a worker per
 * core is more booting than testing, and `--staged` runs in front of every commit.
 *
 * A parallel run that fails is read before it is believed, because the pool is the one part of
 * this gate that can be wrong about a test: sixteen workers each spawning children of their own
 * is a machine under a load nothing else here creates, and on Windows `proc_open` fails
 * sporadically under it for a command that passes when it is the only thing running. That arrives
 * as an *error* — a test that could not start a process, rather than one that asserted something
 * — so the files a parallel run erred on are asked again, one at a time and without the pool: the
 * check is green only for a run whose every failure was the pool's, and red for a file that fails
 * twice or for anything that asserted. `runPest()` below is where that happens, and it says what it
 * measured: how many of the test files the run started could not be started at all, against how many
 * it ran — because more than one of those at once is a machine under load rather than a finding, and
 * a list of names is a number the reader would have to count for themselves.
 *
 * USAGE
 * -----
 *   composer checks [-- options]     or, the same thing directly:
 *
 *   bin/checks.php                   run everything, print only what failed
 *   bin/checks.php --verbose         stream the output of every check
 *   bin/checks.php --only=pint,tests
 *   bin/checks.php --audit           add `composer audit` (needs the network)
 *   bin/checks.php --require-all     fail instead of skipping when a tool is missing
 *   bin/checks.php --list            list the checks without running anything
 *   bin/checks.php --staged          check only what a commit carries
 *   bin/checks.php --help
 *
 * Neither form names an interpreter, and neither needs to. `composer checks` is run by
 * Composer under the PHP that is running Composer; a direct run is started by whatever PHP
 * the caller used. Which one that *should* be is decided in one place, `.agents/php-bin.sh`
 * — `sh .agents/php-bin.sh --show` says which interpreter this machine names — and this file
 * stays out of that question: nothing here starts a child `php`, so a tool runs under
 * PHP_BINARY and the gate is always measured by the interpreter that started it, and a run
 * under a second one is not the same reading of this repository.
 *
 * Exit code is 0 when every check passed, 1 when any failed (or was skipped
 * under --require-all), 2 for a usage error.
 *
 * STAGED MODE
 * -----------
 * `--staged` is what `.githooks/pre-commit` runs, and it is the same tools pointed at the
 * files a commit carries rather than at the tree: syntax over the staged PHP, Pint over
 * the staged PHP, and the tests those files are covered by - the docs links and the config
 * claims for a document or the config, and the suite that covers a source file for the rest.
 * Those are the ones a commit can break on its own. The rest of the gate is a tree-shaped question — PHPStan
 * and Rector read every file that references the ones you changed, so asking them about a
 * subset answers something other than what `composer checks` answers. A staged PHP file
 * outside the roots the tree-wide pass covers is not checked here for the same reason it
 * is not checked there.
 *
 * One line of that run is not a check: the ignored entries at the root are printed as a note. A
 * file an ignore rule keeps out of a commit is the one kind of uncommitted work `git status` says
 * nothing about - it is absent from the listing rather than marked - and the rules here name a
 * session's leavings (`/oc-session-*.md`, `/permissions-01.md`), so the moment somebody looks is
 * the moment worth telling.
 *
 * WHICH test a file is measured by is `tests/Support/TestMap.php`'s answer, not this file's.
 * The mapping used to be three lines here - markdown to the docs links and the config claims,
 * `config/response-compression.php` to the claims - and three lines are what a mapping looks
 * like right up until the commit that changes a program and runs nothing. The reading now
 * answers for every source file, and `tests/Unit/Support/TestMapTest.php` refuses a source
 * file it has no row for: a commit that breaks a release rail runs the rails, because
 * `bin/release.php` is a row rather than a sentence somebody has to remember to extend.
 *
 * The config-claim test is still conditional on a SECOND file, and the reading is where that
 * lives now. A claim about a key is written in the markdown that repeats it or in the docblock
 * beside it in the config, so a commit carrying either can have made one stale and a commit
 * carrying neither cannot have. Running it on every commit would be asking a question the
 * commit did not raise; running it never, which is what the suite alone did, is how
 * `enable_logging`'s docblock stayed wrong after the wiring made it so.
 *
 * PORTABILITY
 * -----------
 * Tools are invoked as `PHP_BINARY <vendor entry file>` rather than through the
 * `vendor/bin` shims, because a shim is a shell script on Unix and a .bat on
 * Windows and neither is reliably executable from another process. The same
 * invocation works on Windows and Linux, and a missing tool is reported as a
 * skip rather than crashing the run.
 *
 * Which file each tool is comes from `bin/tool-paths.php`, and from nowhere else. This gate used
 * to keep its own copy of those five paths, because it cannot ask the composer scripts for theirs
 * — a script is a shell string — and the copies had drifted by the time anyone compared them. An
 * entry there is a record rather than a path, and `--list` prints the whole of each one: the tool,
 * the version it is pinned at and what it is for. That is the gate describing itself out of the
 * manifest rather than out of a sentence beside this file that nothing compares with it.
 */

/**
 * @guards-index guard `bin/checks.php`
 *
 * eleven checks in one process: syntax, an AST parse, the composer schema, the platform requirements, the toolchain probe, the agentic layer, the workflow YAML, PHPStan, Pint, Rector, Pest — one summary, one exit code
 */

/**
 * @guards-index guard a lock-aware `composer validate`
 *
 * a stale lock: the check runs only when the repository actually ships one
 */

/**
 * @guards-index guard `composer check-platform-reqs`
 *
 * a missing `ext-brotli` or `ext-zstd`, reported as a build problem rather than as a failing test
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
    'staged' => false,
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

    if ($argument === '--staged') {
        $options['staged'] = true;

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
$python = pythonCommand($root);
$yamlFiles = yamlFiles($root);

// Whether this repository contains composer.lock. Asked once, here, because the
// schema check is the only one that can be answered differently for a lock the
// checkout has and the repository does not — see composerValidateCommand().
$lockIsShipped = commitsLockFile($root);

$tools = toolEntries($root);

// ─────────────────────────────────────────────────────────────────────────────
// The checks
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// Staged scope
// ─────────────────────────────────────────────────────────────────────────────

// The files a commit carries, and the narrowing that follows from them. Deleted paths
// are part of the set even though no tool can be handed one: a deletion is what breaks
// a link that nothing else touches.
$stagedMode = $options['staged'];
$stagedFiles = $stagedMode ? stagedFiles($root) : [];

$stagedPhp = array_values(array_filter(
    $stagedFiles,
    static fn (string $path): bool => str_ends_with($path, '.php') && is_file($root.'/'.$path),
));

// The `syntax` check reads absolute paths, so the staged ones are narrowed against the
// absolute list the tree-wide pass built rather than intersected as strings.
$stagedAbsolute = array_map(static fn (string $path): string => $root.'/'.$path, $stagedPhp);
$phpFiles = $stagedMode ? array_values(array_intersect($phpFiles, $stagedAbsolute)) : $phpFiles;

// Pint is handed the paths, and pint.json's `notPath` is honoured when Pint walks the
// tree itself and ignored when it is told what to look at — so the exclusion is applied
// here. A commit must not be blocked by a rule the project turned off.
$pintExcluded = pintExcluded($root);

$pintPaths = $stagedMode
    ? array_values(array_filter(
        $stagedPhp,
        static fn (string $path): bool => ! in_array($path, $pintExcluded, true),
    ))
    : [];

// Which tests a commit runs, from the files it carries. The reading is
// `tests/Support/TestMap.php` rather than a list here, because which test covers which source
// file is a fact about two directories: the reading walks them, and
// `tests/Unit/Support/TestMapTest.php` refuses a source file with no row and a row with no file.
// So a commit that changes a program — `bin/release.php` above all, whose tests are the ones that
// plant a repository and run it — is run against the suite that covers it, which is the thing this
// mode was missing.
//
// The reading is asked only when it is here to be asked: `tests/` is not in an installed package,
// and a checkout whose dependencies were never installed has no Pest to run anyway.
$testMap = $root.'/tests/Support/TestMap.php';

$stagedTests = $stagedMode && is_file($testMap) && class_exists(TestMap::class)
    ? TestMap::covering($stagedFiles)
    : [];

// The staged run is a subset of the suite, and the title says which part of it this one got.
// Which tests those are is the reading's answer above rather than a second decision here: a title
// that named the kinds would be this file reading the same files again, and the two could
// disagree about what a commit runs.
$testsTitle = $stagedMode ? 'Staged tests (pest)' : 'Test suite (pest)';

// The message keeps saying "no staged": the point of the empty case is a pass rather than a
// silence, and `tests/Unit/Gate/ChecksTest.php` reads that sentence off a bare `--staged` run.
$testsSkip = $stagedMode && $stagedTests === []
    ? 'no staged source, document or config for a test to read'
    : null;

// The whole suite runs in parallel, which is how `composer test:unit` already runs it. Most of
// these tests are process spawns — a planted git repository, a release script started against
// it — so a worker per file is what turns minutes into seconds, and it is the same binary and
// the same assertions either way. `--parallel` is paratest, and paratest takes ONE path: handed
// the two or more a staged set usually produces it refuses the run outright rather than ignoring
// the rest, which is what the pre-commit hook found the first time this mode was tried with two
// files in it. So the flag belongs to a command that names exactly one path, and that is every
// command below: the single one a staged set of one reduces to, and each of the one-per-path
// commands a set of several becomes. A set of none stays `[]` and never runs — the empty case is
// a pass, decided a few lines up.
$pestParallel = ! $stagedMode || count($stagedTests) === 1 ? ['--parallel'] : [];

// What the pest check runs, as one command each. `paratest` takes ONE path and refuses several, so
// a staged set of two or more used to be handed to a single sequential run — which made the
// *narrowest* mappings the slowest ones: the harness's hold directory is fifteen files whose tests
// are process spawns, and one after another they are minutes where the whole suite is one parallel
// run. So every path gets its own process and they are all started together, which is the shape the
// files had before anyway: a worker per file.
//
// `--parallel` goes to the paths that can use it. A directory with several files is what paratest
// splits across workers; a single file is one worker whatever it is told, and asking for a pool
// there would start one to run one file. A set naming more than one directory therefore starts a
// pool per directory, which is the one thing this does not bound: those runs take longer than they
// would alone, and they are still the answer to a commit whose mapped set is every one of them.
$pestCommands = count($stagedTests) > 1
    ? array_map(
        static fn (string $path): array => [
            PHP_BINARY,
            $tools['pest']['path'],
            ...(is_dir($root.'/'.$path) ? ['--parallel'] : []),
            $path,
        ],
        $stagedTests,
    )
    : [[PHP_BINARY, $tools['pest']['path'], ...$pestParallel, ...$stagedTests]];

// The two spellings of the note channel, taken from the class that owns them where there is a class
// to load — and there is not always one, because this program is pointed at trees that are not this
// package: the release rails plant one, every fixture in `tests/Unit/Gate/ChecksTest.php` is a tree
// with no `vendor/`, and the autoloader above is required only when the tree has one. What keeps the
// two spellings one is a test rather than a comment: the planted check there writes to the variable
// it is handed and prints the marker the class holds, and the summary has to carry both back.
//
// Both are read before the file that is named further down for a worker to write into, because two
// things read them: the note file, and the retry `runPest()` makes, whose healed files are marked
// lines like any other.
$notesMarker = class_exists(Notes::class) ? Notes::MARKER : '[NOTE]';
$notesVariable = class_exists(Notes::class) ? Notes::SINK : 'RESPONSE_COMPRESSION_NOTES';

$level = phpstanLevel($root);

$checks = [
    'syntax' => [
        'title' => 'PHP syntax (php -l)',
        'skip' => match (true) {
            $phpFiles !== [] => null,
            $stagedMode => 'no staged PHP files',
            default => 'no PHP files found',
        },
        'run' => static fn (): array => checkSyntax($root, $phpFiles),
        'note' => static function (array $result) use ($phpFiles, $stagedMode): string {
            $failed = preg_match_all('/Errors parsing/', $result['output']);

            if ($failed !== 0) {
                return "{$failed} of ".count($phpFiles).' files failed';
            }

            return count($phpFiles).($stagedMode ? ' staged files' : ' files').', no syntax errors';
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

    // The machine's own question, asked of the program written to ask it rather than answered again
    // here. `platform` above is what *Composer* requires of this interpreter; this is everything else
    // the package needs of it - the version against the pin in `.agents/toolchain.json`, the coverage
    // driver a floor needs, composer, Python, PowerShell 7, git, every tool in `bin/tool-paths.php`
    // still starting through `bin/tool.php`, and a `vendor/` at all - and it is asked through
    // `.agents/verify.py` because a second implementation of that reading is the drift every guard in
    // this repository is about. What it buys is where the answer arrives: a runner with the wrong PHP
    // or a missing extension fails here, in one line naming the process that is wrong, instead of
    // somewhere inside the suite as a test that could not run.
    //
    // It is a tree-shaped question rather than a commit-shaped one, so `--staged` does not ask it:
    // a commit cannot have changed which interpreter the machine has, and the probe starts a dozen
    // processes before it answers.
    'toolchain' => [
        'title' => 'Toolchain probe (python .agents/verify.py --only=tools)',
        'skip' => match (true) {
            ! is_file($root.'/.agents/verify.py') => 'there is no agentic probe in this tree (an installed package has none)',
            $python === null => 'no Python 3 found (python, python3 or py on PATH)',
            default => null,
        },
        'run' => static fn (): array => runCommand([...$python ?? [], '.agents/verify.py', '--only=tools'], $root),
        'note' => static function (array $result): string {
            return $result['exit'] === 0
                ? 'every tool and process this package needs answered'
                : probeReason($result['output']);
        },
    ],

    // The agentic layer, asked of the program that owns the reading rather than answered again here.
    // It is this repository's own account of itself - the canonical rules and their sections, the
    // pointer files, the index, the generated skill targets, the rendered command table, the
    // interpreter rule, the tokens and the skill descriptions - and, underneath all of it, the proof
    // that each of those readings still fires: `--selftest` plants one fault per step in a copy of
    // the layer and requires the step to name it. That second half is why this check is in the gate.
    // A reading that has stopped checking reports the same clean answer as a reading that works, and
    // until this check existed nothing in CI could tell the two apart: the tree was green, the step
    // was silent, and the silence read as agreement. It is a layer rather than a tool, so it is asked
    // through `.agents/verify.py` for the same reason the probe above is - a second implementation of
    // the reading is the drift every guard here is about.
    //
    // It is a tree-shaped question rather than a commit-shaped one - a commit cannot have changed
    // what the layer means - so `--staged` does not ask it, and it is not one of the three checks a
    // commit narrows to. On a runner the whole gate runs with `--require-all`, so a machine with no
    // Python fails here rather than losing the reading: that is what makes this a build gate rather
    // than a workstation convenience.
    'agentic' => [
        'title' => 'Agentic layer (python .agents/verify.py --layer, --selftest)',
        'skip' => match (true) {
            ! is_file($root.'/.agents/verify.py') => 'there is no agentic layer in this tree (an installed package has none)',
            $python === null => 'no Python 3 found (python, python3 or py on PATH)',
            default => null,
        },
        'run' => static fn (): array => checkAgentic($root, $python),
        'note' => static function (array $result): string {
            return $result['exit'] === 0
                ? 'the layer agrees with itself, and every reading still names a planted fault'
                : probeReason($result['output']);
        },
    ],
    'yaml' => [
        'title' => 'Workflow YAML (yaml-lint)',
        'skip' => match (true) {
            $yamlFiles === [] => 'no YAML outside vendor/',
            ! is_file($tools['yaml-lint']['path'] ?? '') => 'symfony/yaml is not installed',
            default => null,
        },
        'run' => static fn (): array => runCommand(
            [PHP_BINARY, $tools['yaml-lint']['path'], '--no-ansi', ...$yamlFiles],
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
        'skip' => is_file($tools['phpstan']['path'] ?? '') ? null : 'phpstan is not installed',
        'run' => static fn (): array => runCommand(
            [PHP_BINARY, $tools['phpstan']['path'], 'analyse', '--no-progress', '--no-ansi'],
            $root,
        ),
    ],
    'pint' => [
        'title' => 'Code style (pint --test)',
        'skip' => match (true) {
            ! is_file($tools['pint']['path'] ?? '') => 'laravel/pint is not installed',
            $stagedMode && $pintPaths === [] => 'no staged PHP files for Pint to read',
            default => null,
        },
        'run' => static fn (): array => runCommand(
            [PHP_BINARY, $tools['pint']['path'], '--test', ...$pintPaths],
            $root,
        ),
    ],
    'rector' => [
        'title' => 'Automated refactoring (rector --dry-run)',
        'skip' => is_file($tools['rector']['path'] ?? '') ? null : 'rector is not installed',
        'run' => static fn (): array => runCommand(
            [PHP_BINARY, $tools['rector']['path'], '--dry-run', '--no-progress-bar'],
            $root,
        ),
    ],
    'tests' => [
        'title' => $testsTitle,
        'skip' => match (true) {
            ! is_file($tools['pest']['path'] ?? '') => 'pest is not installed',
            $testsSkip !== null => $testsSkip,
            default => null,
        },
        // The whole reading — the run, and the narrower second run a pool failure can earn — is
        // `runPest()`'s, so the split of the set and the retry of a file in it are one decision.
        'run' => static fn (): array => runPest($pestCommands, $tools['pest']['path'], $root, $notesMarker),
        'note' => static function (array $result): string {
            // Pest ends with its duration, which says nothing about whether it
            // passed; the count it printed a line earlier does. A staged set of
            // more than one path is more than one run, and each ends with its own
            // count: the note is what the set answered as a whole, because a
            // reader shown the first path's numbers would read them as all of it.
            preg_match_all('/^\s*Tests:\s*(.+)$/m', stripAnsi($result['output']), $matches);
            $lines = array_map(trim(...), $matches[1]);

            if ($lines === []) {
                return lastLine($result['output']);
            }

            if (count($lines) === 1) {
                $summary = $lines[0].' (coverage floor is measured by composer test:unit)';
            } else {
                $passed = 0;
                $failed = 0;
                $assertions = 0;

                foreach ($lines as $line) {
                    preg_match('/(\d+) passed/', $line, $count);
                    preg_match('/(\d+) failed/', $line, $broke);
                    preg_match('/(\d+) assertion/', $line, $checked);

                    // A count in a shape this reading does not know is quoted rather
                    // than summed: a total nobody computed is worse than a long line.
                    if (! isset($count[1])) {
                        return implode(' / ', $lines).' ('.count($lines).' runs)';
                    }

                    $passed += (int) $count[1];
                    $failed += (int) ($broke[1] ?? 0);
                    $assertions += (int) ($checked[1] ?? 0);
                }

                $summary = sprintf(
                    '%d passed%s (%d assertions) in %d runs',
                    $passed,
                    $failed === 0 ? '' : ", {$failed} failed",
                    $assertions,
                    count($lines),
                );
            }

            // A run that was asked again says so here as well as in the note below the table: the
            // count above is what the pool's own run printed, so a row reading PASS beside "1
            // failed" would be the one contradiction this whole reading exists to explain away.
            //
            // Which of the two sentences depends on how many answered at once, and the bulk one is a
            // measurement rather than a count of names: "5 of the 39 files it ran" already contains the
            // list's information and adds the thing the list leaves out — that the pool had 39 files to
            // start and could not start five of them — which is what makes it the machine's fault.
            $healed = $result['healed'] ?? [];
            $pool = $result['pool'] ?? [];

            if ($healed === []) {
                return $summary;
            }

            if (($pool['erred'] ?? 0) > 1) {
                return $summary.'; '.sprintf(
                    '%d of the %d files the pool ran could not start under load, and %s passed alone',
                    $pool['healed'],
                    $pool['files'],
                    $pool['healed'] === $pool['erred'] ? 'each' : (string) $pool['healed'],
                );
            }

            return $summary.'; '.count($healed).' file'.(count($healed) === 1 ? '' : 's')
                .' the pool errored on passed alone';
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

if ($stagedMode && $options['only'] === []) {
    // The three a commit can break on its own, and the only three that can be answered
    // from the staged files. An explicit `--only=` is left alone: it is a deliberate
    // question about the tree, not the hook's question about a commit.
    $checks = array_intersect_key($checks, array_flip(['syntax', 'pint', 'tests']));
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

    // The tools behind those checks, read out of the manifest rather than described again here:
    // the file each one is, the version this package pins it at, and what it is for. It is the
    // listing that answers "what would this run?", so a tool that moved belongs in the answer to
    // that question, and it is the only place these three facts are written down.
    if ($tools !== []) {
        echo PHP_EOL.str_repeat('─', 84).PHP_EOL;
        echo 'Tools, pinned by bin/tool-paths.php:'.PHP_EOL;

        foreach ($tools as $name => $tool) {
            printf('  %-10s %-9s %s%s', $name, $tool['pin'], $tool['why'], PHP_EOL);
        }
    }

    exit(0);
}

// ─────────────────────────────────────────────────────────────────────────────
// Run
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// Where a worker puts a note
// ─────────────────────────────────────────────────────────────────────────────

// The suite below runs under `paratest`, and a line a worker prints does not survive it: PHPUnit
// keeps what a test wrote as that test's unexpected output, which it prints for a test that did not
// pass and drops for one that did, so a note about a machine with no file to read would be lost on
// exactly the green run it was made for. What crosses a worker boundary is a file. So one is named
// here and handed to every child through the environment — `proc_open` passes this process's
// environment on, and `pest` passes it on again to each worker — and its contents are read back
// below, under the same heading as the notes the checks printed themselves. A temp directory that
// refuses the file is not a reason to run without notes: nothing is named, no variable is set, and
// every reading is left with the stream a check can print.
$notesFile = tempnam(sys_get_temp_dir(), 'response-compression-notes-');

if ($notesFile !== false) {
    putenv($notesVariable.'='.$notesFile);
}

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

    // A check's output is captured for the summary, so a check that runs for minutes prints nothing
    // while it does - and the `tests` check is the whole suite when the staged set is a file the
    // suite is built on. The hook is a commit waiting on that silence, so the title of the check
    // that is running goes to the error stream as it starts. It is not `-v`, which streams the
    // check's whole output as well; this is one line.
    if (! $options['verbose']) {
        fwrite(STDERR, '▶ '.$check['title'].PHP_EOL);
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
        ? $check['note']([
            'exit' => $result['exit'],
            'output' => stripAnsi($result['output']),
            'healed' => $result['healed'] ?? [],
            'pool' => $result['pool'] ?? [],
        ])
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

// ─────────────────────────────────────────────────────────────────────────────
// Notes
// ─────────────────────────────────────────────────────────────────────────────

// Something a run needs to say that is neither a verdict nor a finding: a reading declining to ask
// a question, or a step of the agentic layer naming the probe it left out. Each of those is printed
// by the check that produced it and captured with that check's output, which is exactly how a skip
// comes to read as a clean answer — the summary shows one line per check, and the note is not in it.
// So the marked lines are repeated under the table, beside the verdicts they qualify: those a check
// printed, out of its output, and those the suite's workers could not print, out of the file named
// above. One copy of each: a reading is asked by tests in a dozen files, so the same sentence
// arrives once per worker, and four identical lines is a reader learning nothing four times.
$notes = [];

foreach ($results as $result) {
    foreach (explode(PHP_EOL, $result['output']) as $line) {
        $line = trim(stripAnsi($line));

        if (str_starts_with($line, $notesMarker.' ')) {
            $notes[$line] = true;
        }
    }
}

// The half a check cannot print at all, and the one CI is run on: a note made inside a paratest
// worker reaches no check's output, because the runner drops what a passing test wrote. The file is
// this program's own — named before the checks ran, handed to every child, and taken away here
// whether or not anything was written to it. A sentence that arrived both ways stays one line: the
// set is keyed by the sentence rather than by where it came from.
if ($notesFile !== false) {
    if (is_file($notesFile)) {
        foreach (file($notesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $written) {
            $written = trim($written);

            if (str_starts_with($written, $notesMarker.' ')) {
                $notes[$written] = true;
            }
        }
    }

    @unlink($notesFile);
}

// What this commit will not carry, which is the one question `git status` cannot answer: an ignored
// file is missing from its listing rather than marked in it. The tree's own rules name a session's
// leavings - `/oc-session-*.md`, `/permissions-01.md` - and a rule doing its job looks exactly like a
// rule hiding the file somebody meant to commit, so the names are repeated at the moment the commit
// is made rather than left to whoever thinks to ask.
//
// Every entry at the root, `vendor/` and `composer.phar` beside a scratch copy: the plainest reading
// of what the commit does not carry is the whole of it, and a curated half would be a second
// .gitignore - a list of which ignored names count - kept in step by hand.
if ($stagedMode) {
    $ignored = ignoredAtRoot($root);

    if ($ignored !== []) {
        $notes[$notesMarker.' checks: ignored at the root, so no commit carries them: '.implode(', ', $ignored)] = true;
    }
}

if ($notes !== []) {
    echo PHP_EOL;

    foreach (array_keys($notes) as $note) {
        echo $note.PHP_EOL;
    }
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
      composer checks [-- options]
      bin/checks.php [options]

    Either form runs under this machine's PHP — `.agents/php-bin.sh` is what decides
    which that is, and `sh .agents/php-bin.sh --show` says so.

    Options:
      -v, --verbose     Stream the output of every check, not just failures.
          --only=KEYS   Run a subset, comma separated (see --list).
          --audit       Also run `composer audit` (needs network access).
          --require-all Treat a missing tool as a failure instead of a skip.
          --list        List the checks and their skip conditions.
          --staged      Check only the staged files (syntax, style, and the tests that cover
                        them), which is what the pre-commit hook runs.
      -h, --help        Show this help.

    Exit code: 0 all green, 1 anything failed, 2 usage error.
    Note: the coverage floor lives in `composer test:unit`, which needs a
    coverage driver and is run by CI separately. Two checks ask Python (python,
    python3 or py on PATH) - the toolchain probe and the agentic layer - and a
    machine without one skips both: --require-all is what turns those skips into
    a failure on a runner.

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

    // Stdin is declared rather than inherited: a child handed this process's own stdin can wait on
    // a prompt nobody sees in a commit's hook, and `NUL` / `/dev/null` is the EOF that ends that
    // wait. Every descriptor is declared for the same reason the suite's own fixtures declare them.
    $process = @proc_open(
        $command,
        [
            0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['redirect', 1],
        ],
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
 * Run several commands at once, and answer with them as one result.
 *
 * The staged gate needs this because `paratest` takes one path: a commit whose mapped set is two or
 * more paths used to be judged by a single sequential run, and the *narrowest* mappings are the ones
 * that costs most — the harness's hold directory is fifteen files of process spawns, and run one
 * after another they are minutes where the whole suite is one parallel run.
 *
 * Every command is started before any is read, because starting them as each one finished would be
 * the sequential run again. Draining the pipes one at a time cannot deadlock: a child that fills its
 * pipe blocks until its own pipe is read, and each pipe is read to EOF in turn, so no child waits on
 * a read that is waiting on it.
 *
 * Each command's output is kept under a heading naming it, the output is every command's in the
 * order given, and the exit code is the first non-zero one — the check that runs this reports one
 * verdict for the set, and the set failed if any of its runs did.
 *
 * @param  list<list<string>>  $commands
 * @return array{exit: int, output: string}
 */
function runCommands(array $commands, string $cwd): array
{
    $running = [];

    foreach ($commands as $command) {
        $process = @proc_open(
            $command,
            [
                0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['redirect', 1],
            ],
            $pipes,
            $cwd,
        );

        $running[] = [
            'process' => is_resource($process) ? $process : null,
            'pipes' => $pipes,
            'command' => $command,
        ];
    }

    $exit = 0;
    $output = '';

    foreach ($running as $entry) {
        $label = implode(' ', $entry['command']);

        if ($entry['process'] === null) {
            $output .= 'could not start: '.$label.PHP_EOL;
            $exit = $exit === 0 ? 127 : $exit;

            continue;
        }

        $output .= PHP_EOL.'▶ '.$label.PHP_EOL;

        $answer = (string) stream_get_contents($entry['pipes'][1]);
        fclose($entry['pipes'][1]);

        $code = proc_close($entry['process']);

        $output .= $answer;

        if ($exit === 0 && $code !== 0) {
            $exit = $code;
        }
    }

    return ['exit' => $exit, 'output' => $output];
}

/**
 * Run the pest commands for the tests check, and ask again — one file at a time, and without the
 * pool — for the files a parallel run reported as errors.
 *
 * WHY A PARALLEL FAILURE IS READ BEFORE IT IS BELIEVED
 * ----------------------------------------------------
 * `--parallel` is paratest, and paratest is the only part of this gate that can be wrong about a
 * test. Sixteen workers, each spawning children of its own, is a machine under a load nothing else
 * here creates, and on Windows `proc_open` fails sporadically under it — `CreateProcess failed:
 * The parameter is incorrect` — for a command that passes when it is the only thing running. What
 * matters is the shape that failure arrives in: an *error*, never an assertion failure. A test that
 * asserted something has answered about the code; a test that could not start a process has
 * answered about the pool, and reporting the second as a finding is how a green tree comes back red
 * for a reason that is not in it.
 *
 * So every command is handed `--log-junit`, and when a parallel run fails its log is read: PHPUnit
 * writes one `<testsuite>` per test file carrying that file's `errors` and `failures`, so the two
 * kinds of red are told apart by the run's own record rather than by a sentence about where they
 * usually come from. The files that erred are asked again — the failing file alone, the same
 * binary, no `--parallel` — which is exactly the condition the flake needs to disappear, and it is
 * the file rather than the whole set because a second full run would spend minutes answering a
 * question about three files.
 *
 * Two things it deliberately does not do. A file that fails alone too is not asked a third time: the
 * check keeps the non-zero exit the first run gave and names the file, because a file that fails
 * twice is a finding rather than noise — and the run's own output already carries that file's trace,
 * so the retry reports what it *found* rather than printing the same failure a second time. (It is
 * also what keeps the check's summary line honest: a lone run of a file prints a `Tests:` line of
 * its own, and a note that summed it would report the retry as a second run of the suite.) And a run
 * that wrote no log at all — a tree whose `pest` is a stub, or a Pest from before the flag — is left
 * exactly as it was found, which is what this check did before any of it existed.
 *
 * The files are asked again even when the same run also reported an assertion failure somewhere else,
 * and that is the case worth arguing: a storm of `proc_open` failures usually arrives beside one real
 * finding, and a check that answered it with twenty-six error traces and no separation would leave
 * the reader to work out which one is about their code. The verdict does not move — an assertion
 * failure keeps the run red whatever the retries say — but the twenty-six are named as the pool's.
 *
 * What it does do is say so, and say what it measured. A check that quietly asked twice would read as
 * one that never failed, so the marker makes the run a note like any other, repeated under the summary
 * beside the verdict it qualifies — and the sentence is the measurement rather than the list: how many
 * of the test files the run started could not be started at all, against how many the run held. Two of
 * those in one run is already the machine, not the code: a file cannot make another file's `proc_open`
 * fail, so one error is a file the pool could not start and its own retry is the answer, while a second
 * in the same run is the condition those workers share. That is the reading a reader cannot do from a
 * list of paths — which is why the count is printed as a count, and why the per-file lines above it are
 * left as the evidence they are.
 *
 * @param  list<list<string>>  $commands  one command per run: the whole suite, or one per mapped path
 * @param  string  $pest  the Pest entry file, as the manifest gives it
 * @param  string  $marker  the note marker, which is `$notesMarker`
 * @return array{exit: int, output: string, healed?: list<string>, pool?: array{erred: int, healed: int, files: int}}
 */
function runPest(array $commands, string $pest, string $root, string $marker): array
{
    $logs = [];
    $logged = [];

    // One log per command rather than one for the set: the fan-out starts every command before
    // reading any, so a single file would be written by whichever run finished last.
    foreach ($commands as $command) {
        $log = tempnam(sys_get_temp_dir(), 'response-compression-junit-');

        $logs[] = is_string($log) ? $log : null;
        $logged[] = is_string($log) ? [...$command, '--log-junit', $log] : $command;
    }

    $result = count($logged) === 1 ? runCommand($logged[0], $root) : runCommands($logged, $root);

    $erred = [];
    $asserted = [];
    $ran = 0;

    foreach ($commands as $index => $command) {
        $log = $logs[$index];

        if (! is_string($log)) {
            continue;
        }

        // A run that passed, or one that was not the pool's, has nothing to ask again: a single
        // file handed to one worker is the condition the retry creates, so an error there is that
        // file's answer rather than the pool's noise.
        if ($result['exit'] !== 0 && in_array('--parallel', $command, true) && is_file($log)) {
            $outcome = junitOutcome((string) file_get_contents($log));

            $erred = [...$erred, ...$outcome['erred']];
            $asserted = [...$asserted, ...$outcome['asserted']];
            $ran += $outcome['files'];
        }

        @unlink($log);
    }

    $erred = array_values(array_unique($erred));

    if ($erred === []) {
        return $result;
    }

    $healed = [];
    $again = [];
    $output = $result['output'].PHP_EOL.'▶ the pool errored on '.count($erred).' file(s), and each was asked again, alone'.PHP_EOL;

    foreach ($erred as $file) {
        $retry = runCommand([PHP_BINARY, $pest, $file], $root);

        if ($retry['exit'] === 0) {
            $healed[] = $file;
            $output .= '  '.$file.' — passed alone'.PHP_EOL;

            continue;
        }

        $again[] = $file;
        $output .= '  '.$file.' — failed alone too'.PHP_EOL;
    }

    // Said when every file that erred answered the second time, whether or not something else in the
    // run asserted: the sentence is about those files, and a red run that also contains the pool's
    // noise is the run that needs it most. What it says is the measurement rather than the names — the
    // names are the lines just above it — and more than one is called the machine, because a file
    // cannot make another file's process fail and each of these has already passed on its own.
    if ($again === []) {
        $output .= $marker.' '.(count($erred) > 1
            ? sprintf(
                'the parallel run could not start %d of the %d test files it ran — a machine under load rather than %d findings in the code — and each passed when it was asked again alone',
                count($healed),
                $ran,
                count($healed),
            )
            : sprintf(
                'the parallel run could not start %d of the %d test files it ran, and it passed when it was asked again alone',
                count($healed),
                $ran,
            )).PHP_EOL;
    }

    // Whatever the retries found, a run that asserted something is red: that verdict belongs to the
    // code and nothing here may soften it, so only a run whose every failure was the pool's can come
    // back green — and only because each of those files answered a second time.
    //
    // `healed` goes to the check's own summary line rather than being parsed back out of the note
    // above: a row that reads PASS beside the "1 failed" the pool's run printed — or FAIL beside two
    // dozen errors, most of them the pool's — is the ambiguity this whole reading exists to remove.
    return [
        'exit' => $again === [] && $asserted === [] ? 0 : $result['exit'],
        'output' => $output,
        'healed' => $healed,
        'pool' => ['erred' => count($erred), 'healed' => count($healed), 'files' => $ran],
    ];
}

/**
 * Which of a run's files erred, and which asserted something, out of the JUnit log it wrote.
 *
 * Read off the `<testsuite>` elements rather than the `<testcase>` ones: PHPUnit writes one
 * `<testsuite>` per test file — carrying that file, its `errors` and its `failures` — and the
 * counts are summed exactly where this reading wants them. Parallel mode merges the workers' logs
 * under an element of its own, which carries no `file` and is skipped rather than read as a file.
 *
 * The log is matched rather than parsed: this program is pointed at trees that have no `vendor/` —
 * every fixture it is tested in — so `ext-simplexml` or `ext-dom` is a dependency the gate would be
 * wrong to have. A log in a shape the three attributes do not appear in names nothing, and naming
 * nothing is the answer that changes nothing.
 *
 * `files` is how many test files the log accounts for, which is the denominator the pool's own count
 * needs: a run prints the tests it ran, not the files it held, and "five of them could not start" is
 * a sentence about a machine only beside the number that says five of how many.
 *
 * @return array{erred: list<string>, asserted: list<string>, files: int} paths as the run named them, slashed
 */
function junitOutcome(string $log): array
{
    $erred = [];
    $asserted = [];
    $ran = [];

    preg_match_all('/<testsuite\b[^>]*>/', $log, $elements);

    foreach ($elements[0] as $element) {
        if (preg_match('/\bfile="([^"]+)"/', $element, $file) !== 1) {
            continue;
        }

        // Windows spells a path with backslashes, and every other path this program prints is
        // git's spelling — the two are compared, deduplicated and listed together.
        $path = PHP_OS_FAMILY === 'Windows' ? str_replace('\\', '/', $file[1]) : $file[1];
        $ran[] = $path;

        if (junitCount($element, 'errors') > 0) {
            $erred[] = $path;
        }

        if (junitCount($element, 'failures') > 0) {
            $asserted[] = $path;
        }
    }

    return [
        'erred' => array_values(array_unique($erred)),
        'asserted' => array_values(array_unique($asserted)),
        'files' => count(array_unique($ran)),
    ];
}

/**
 * One attribute of a JUnit element as a count, and 0 when the element does not carry it.
 *
 * The missing case is the one that matters: a log from a runner that reports some other way has
 * attributed nothing to any file, so nothing is asked again rather than everything.
 */
function junitCount(string $element, string $name): int
{
    return preg_match('/\b'.$name.'="(\d+)"/', $element, $count) === 1 ? (int) $count[1] : 0;
}

/**
 * Every tool this package runs, as name => its entry from the manifest, with the path resolved.
 *
 * From `bin/tool-paths.php`, which is the only place a tool is written down: the composer scripts
 * reach their tools through `bin/tool.php`, which reads the same manifest, so the two sides cannot
 * disagree about which file a tool is. The other two facts an entry carries come along because
 * `--list` prints them — the version the tool is pinned at, and what it is for — and a reader that
 * dropped them would be this gate keeping its own copy of something the manifest already says.
 *
 * An entry without a path in it is dropped, which reads here as a tool this tree does not have:
 * every check that would have run it skips, which is what the skip column is for, and the reading
 * that refuses an entry in that shape is `tests/Unit/Support/ToolPathsTest.php`.
 *
 * An empty answer is not an error here. A tree with no manifest is a tree with no tools — a
 * checkout whose `vendor/` was never installed, and the planted fixtures this gate is tested in —
 * and a check whose tool is missing skips. A name the manifest does not have reads the same way
 * rather than as an undefined index.
 *
 * @return array<string, array{path: string, pin: string, why: string}>
 */
function toolEntries(string $root): array
{
    $manifest = $root.'/bin/tool-paths.php';

    if (! is_file($manifest)) {
        return [];
    }

    $paths = require $manifest;

    if (! is_array($paths)) {
        return [];
    }

    $tools = [];

    foreach ($paths as $name => $entry) {
        if (! is_string($name) || ! is_array($entry) || ! is_string($entry['path'] ?? null) || $entry['path'] === '') {
            continue;
        }

        $tools[$name] = [
            'path' => $root.'/'.ltrim($entry['path'], '/'),
            'pin' => is_string($entry['pin'] ?? null) ? $entry['pin'] : '',
            'why' => is_string($entry['why'] ?? null) ? $entry['why'] : '',
        ];
    }

    return $tools;
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
 * Python, as this checkout can reach it: the first of the three names on `PATH` that answers with a
 * 3, or null when none of them does.
 *
 * The three names, and their order, are `.agents/php-bin.sh`'s and `.githooks/pre-commit`'s rather
 * than a third opinion: a machine whose `python` is a 2 and whose `python3` is a 3 has to answer the
 * same way wherever it is asked, or the probe would be asked in one place and skipped in another.
 *
 * What is asked of the answer is only that it is a Python 3. Whether it answers the pin in
 * `.agents/toolchain.json` is one of the questions the probe itself asks, and answering it here as
 * well would be exactly the second reading this check exists to avoid - so a Python that is too old
 * is reported by the probe, in the probe's own words, rather than twice.
 *
 * A name that is not there at all is not an error: `runCommand()` answers 127 and this moves on,
 * which is why the loop can simply walk the three.
 *
 * @return list<string>|null
 */
function pythonCommand(string $root): ?array
{
    foreach (['python', 'python3', 'py'] as $candidate) {
        $result = runCommand([$candidate, '--version'], $root);

        if ($result['exit'] === 0 && preg_match('/\bPython 3\./', $result['output']) === 1) {
            return [$candidate];
        }
    }

    return null;
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
 * The paths a commit would carry, relative to the package root and forward-slashed,
 * as git reports them.
 *
 * Deletions are in the set as well as additions, copies, modifications and renames,
 * because a deletion is a change to what a doc link resolves to: the file that breaks
 * is the one nobody touched. A path git no longer has on disk is filtered out by the
 * caller before a tool is handed it.
 *
 * A repository git cannot be asked about answers an empty list. A staged run over an
 * empty list skips its checks rather than passing them, which is what the summary then
 * says — the alternative, reporting nothing and exiting 0, is the failure mode a check
 * like this exists to avoid.
 *
 * @return list<string>
 */
function stagedFiles(string $root): array
{
    $result = runCommand(
        ['git', 'diff', '--cached', '--name-only', '--diff-filter=ACMRD', '-z'],
        $root,
    );

    if ($result['exit'] !== 0) {
        return [];
    }

    $paths = array_values(array_filter(
        explode("\0", $result['output']),
        static fn (string $path): bool => $path !== '',
    ));

    sort($paths);

    return $paths;
}

/**
 * The ignored entries at the tree's root: what no commit will carry, however it is staged.
 *
 * `--directory` is what keeps this a reading of names rather than a walk: an ignored subtree arrives
 * as one entry (`vendor/`), where a tree with its dependencies installed would otherwise print
 * thousands of lines about a directory nobody is going to commit either way.
 *
 * Restricted to the root because that is where a session's own files land - a scratch copy beside
 * `composer.json`, a log from the editor's agent - and because a name at the root is one a reader can
 * go and look at. A tree with no repository around it answers with nothing: there is no commit for
 * this to be about.
 *
 * @return list<string>
 */
function ignoredAtRoot(string $root): array
{
    $result = runCommand(
        ['git', 'ls-files', '--others', '--ignored', '--exclude-standard', '--directory', '-z'],
        $root,
    );

    if ($result['exit'] !== 0) {
        return [];
    }

    $names = [];

    foreach (explode("\0", $result['output']) as $path) {
        // A separator means somewhere below the root: this answers what is sitting beside
        // `composer.json`, not what is inside a directory beside it.
        if ($path === '' || str_contains(rtrim($path, '/'), '/')) {
            continue;
        }

        $names[] = $path;
    }

    sort($names);

    return $names;
}

/**
 * The paths `pint.json` tells Pint to leave alone.
 *
 * Pint honours `notPath` when it walks the tree itself and ignores it when it is told
 * what to look at, and the staged run hands it the staged paths — so the exclusion has
 * to be applied by the caller. Without this, committing a change to the one file the
 * project deliberately exempts would be blocked by a rule the project turned off.
 *
 * @return list<string>
 */
function pintExcluded(string $root): array
{
    $config = @file_get_contents($root.'/pint.json');

    if (! is_string($config)) {
        return [];
    }

    $decoded = json_decode($config, true);

    if (! is_array($decoded) || ! is_array($decoded['notPath'] ?? null)) {
        return [];
    }

    return array_values(array_filter($decoded['notPath'], is_string(...)));
}

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
 * The agentic layer, and the proof that each of its readings still fires.
 *
 * Two runs rather than one, because they answer the two halves of one question and neither implies
 * the other. `--layer` reads this tree and answers whether the layer agrees with itself: the
 * canonical rules and their sections, the pointer files, the index, the generated skill targets, the
 * rendered command table, the interpreter rule, the tokens and the descriptions. `--selftest` plants
 * one fault per step in a copy of that layer and answers whether each step still names it - and that
 * is the half this check exists for, because a layer that agrees with itself is exactly what a step
 * which has stopped checking also reports. A reading whose glob stopped matching, a comparison
 * rewritten to the operator that answers yes: the tree is still clean, the step still says so, and
 * nothing above it would ever have noticed.
 *
 * Both are read as a child of the interpreter that started this one, under `.agents/verify.py`'s own
 * scope flags, rather than through a composer script: a script is a shell string, and this is the
 * same program the probe above drives. The selftest is the slow half - a planted tree per step - and
 * that is the price of the answer rather than a reason to skip it: a gate that cannot fail for a
 * reading gone quiet is the state this check was added to end.
 *
 * @param  list<string>|null  $python  the interpreter, resolved once by pythonCommand()
 * @return array{exit: int, output: string}
 */
function checkAgentic(string $root, ?array $python): array
{
    $exit = 0;
    $output = '';

    foreach ([['--layer'], ['--selftest']] as $arguments) {
        $result = runCommand([...$python ?? [], '.agents/verify.py', ...$arguments], $root);

        $exit = max($exit, $result['exit']);
        $output .= ($output === '' ? '' : PHP_EOL).rtrim($result['output']);
    }

    return ['exit' => $exit, 'output' => $output];
}

/**
 * The one line of the probe's output worth putting in this gate's summary.
 *
 * The probe answers per step and then counts what it ran, so the last line of its output is a tally
 * rather than a finding: a summary built from it would report a failure without saying what failed,
 * which is the shape of report this check was added to avoid. The finding is the first line the
 * probe marked `[FAIL]` - its own line for the step, with the file, the tool and the reason in it -
 * and the tally is the fallback, for a run that stopped before it reported anything.
 */
function probeReason(string $output): string
{
    foreach (preg_split('/\R/', $output) ?: [] as $line) {
        if (str_starts_with(ltrim($line), '[FAIL]')) {
            return clip(trim($line));
        }
    }

    return lastLine($output);
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
