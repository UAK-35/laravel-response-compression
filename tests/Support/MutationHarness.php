<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use Closure;
use RuntimeException;

/**
 * One break at a time, planted in a copy of this checkout, read back through the guard that is
 * supposed to catch it.
 *
 * WHY THIS EXISTS
 * ---------------
 * Every guard in this package answers "nothing is wrong" when it is working, and answers exactly
 * the same thing when it has stopped working. Each of them is held to its own substance at least
 * once — a scan asserts that the listing it read is the real one before it trusts the empty
 * answer, the changelog guard asserts that both of its halves found something, the log-message
 * guard requires every code and sentence it read to be one the type declares — and that covers the
 * first way a
 * guard goes quiet: a reading that came back empty because its input was empty, through a glob
 * that stopped matching, a directory that moved, or a listing that answered with nothing.
 *
 * What none of it covers is a reading that still has its input and stopped asking the right
 * question of it. Loosen `MachinePaths`' drive pattern to one no path matches, drop a class of
 * file from a walk, compare two arrays with the operator that answers yes — and the suite stays
 * green, because a guard that reports nothing on a tree with nothing wrong is doing what it says
 * on the tin. It would go on doing it forever, and it would be in the index while it did.
 *
 * So the tree is broken on purpose, one way at a time, and the guard is asked again. A mutation
 * plants a single break in a copy of this checkout, and the reading that has to catch it has to
 * come back *naming* the break: an empty answer is a failure here, which is the whole difference
 * between this harness and the guard it is checking on.
 *
 * WHAT IS A MUTATION, AND WHAT IS NOT
 * -----------------------------------
 * The reading is the guard's own — the call its test asserts on, not a second implementation of
 * it. `MachinePaths::scan`, `RepoEscapes::scan`, `ChangelogPreamble::stale`, `ConfigClaims::stale`,
 * `Docs::broken`, `Docs::unlinkedRecords`, `ConfigKeys::unknown`, `ConfigKeys::unknownReaders`,
 * `ToolPaths::copies`, `ToolPaths::unsharedNames`, `GuardIndex::drift`,
 * `IndexNumbers::drift` and `LogMessages::duplicates` are one call each, and
 * `ConfigDoc`'s two halves are one comparison, so this class is a caller rather than a copy for
 * each of them. Where a walk used to be composed in the test — the docs links, the config keys,
 * the config claims, the tool paths — the walk now lives in the support class the guard is about,
 * so there is one definition of it rather than a second one here. `MutationHarnessTest` checks both
 * directions of that list, so a guard cannot be added without either a hold or a reason that is
 * still true — where a hold is one of the three below: a break planted in the tree, a reading's
 * detector rewritten, or a rail taken out of a program.
 *
 * One reading a guard asserts on is deliberately not mutated, and the reason is a fact about the
 * tree rather than about the reading. `ToolPaths::pinDrift()` holds a pin to what `composer.json`
 * requires and to what Composer recorded installing, and that second half lives in
 * `vendor/composer/installed.json` — a file this repository ignores, so the planted tree, which is
 * the files a commit would carry, never holds one. A mutation could plant a fixture record, but a
 * record of its own would be the harness writing down the very pins the guard exists to keep in
 * one place, and it would ask the reading a question about a checkout that is not this one. So the
 * pin half is held by its own test, against this machine's real record, and it is said here rather
 * than left as a gap in the list below.
 *
 * THE THIRD DIRECTION — A GUARD THAT IS A PROGRAM
 * -----------------------------------------------
 * A rail in `bin/release.php` is neither of those: its answer is an exit code, and the thing that
 * has to catch a rail which stopped refusing is the test that asserts on that rail. That program is
 * the one here whose fixture can be built from a script a caller names — `ReleaseRepo::mutated()`
 * and the variable it reads — so a rail is taken out of the program, the rewritten releaser is
 * handed to the rail's own test through `RC_RELEASE_FIXTURE_SCRIPT`, and the run has to go red
 * naming that rail. This is `programs()` below, and the control beside it runs the same child
 * process with the program copied verbatim, for the same reason the overlay has one: the rewrite
 * has to be the only difference between the two answers.
 *
 * The other two program-shaped guards are still declined, for a reason that is now about their
 * fixtures rather than about their shape — a test cannot be handed a program, and neither of those
 * two is planted from one.
 *
 * ONE FILE PER GUARD, AND WHY
 * ---------------------------
 * A hold is driven by a file, and there is one file per guard: each is named after the guard's own
 * test and asks this class for that guard in the argument it passes. `paratest` spreads work by
 * file and by nothing finer, so one file holding every guard is one file the whole suite waits on —
 * these reads are the slowest thing in the suite, and a single file put every worker behind the same
 * one of them. The directory is what makes the split safe rather than a second list of names kept in
 * step by hand: `claimed()` reads the guard each file asks for out of the file itself, and the
 * spine this guard's row is declared in holds that set to `subjects()` — every guard the three
 * directions hold, once each. A guard that arrives with no file is a finding, a file that asks for a
 * guard nobody holds raises rather than running nothing, and the two controls live in `Controls/`
 * beside the holds because they are about the machinery rather than about a guard.
 *
 * WHY THE TREE IS A COPY
 * ----------------------
 * The guards read this checkout's own files, so a mutated tree is the only way to ask them a
 * question with a wrong answer in it — and mutating the checkout in place would make this suite's
 * tree the thing under test, which one failed run would leave broken for the next reader. The copy
 * carries every file a commit would carry from this checkout, which is the set both path readings
 * walk, plus a git index of them, because `MachinePaths::files()` asks git rather than the
 * directory.
 *
 * One tree is planted and every mutation is reverted after it, rather than a tree per mutation: the
 * copy is ninety-odd files, and a second one would cost more than the whole harness. What a
 * mutation wrote is written down as it is written — the bytes a file held before the change, and
 * `null` for a file the mutation is what put there — so putting the tree back is undoing the two
 * things a mutation can do rather than re-copying a tree that is otherwise untouched.
 *
 * @guards-index reading
 */
final class MutationHarness
{
    /** The file a mutation adds when the break has to be in a file of its own. */
    public const string SAMPLE = 'src/MutationSample.php';

    /**
     * The directory the holds are carried in: one file per guard, named after the guard's own test.
     *
     * The top level of it is the list of guards, and the guard a file is for is the argument it asks
     * this class with — so the set of holds that exist is read out of the directory rather than kept
     * beside it. `Controls/` holds the runs that are about the machinery rather than about a guard.
     */
    public const string HOLDS = 'tests/Unit/Support/MutationHarness';

    /**
     * The guards the index records that this harness does not mutate, with the reason for each.
     *
     * A list rather than a silence. `MutationHarnessTest` reads it in both directions: a guard in
     * `docs/guards.md` that is missing from it fails, and a name in it that the index no longer
     * records fails too — so every line here is a claim about the suite rather than a note beside
     * it, and the reasons cannot outlive the guards they are about.
     */
    public const array UNAUDITED = [
        'tests/Unit/Gate/ChecksTest.php' => 'the program is the gate, and the tree it is asked about already belongs to its own test: `bin/checks.php` is run as a child process against a repository `ChecksTest` plants, and no variable hands that run a rewrite of it — `ReleaseRepo` and the script it is pointed at are the one fixture here that is built from one, and what they hold is `bin/release.php`',
        'tests/Unit/Gate/CoverageTest.php' => 'the same, one step further out: `bin/coverage.php` is the parent of the run it measures rather than a program a fixture plants, so the run it is about is the test\'s own',
        'tests/Unit/Support/MutationHarnessTest.php' => 'it drives these mutations rather than reading this checkout on its own account: the mutations are the subject, and a mutation of the harness would be a second harness rather than a check on this one',
    ];

    /**
     * The document the guarded set is read from: a guard is recorded here before it can be missed,
     * and `tests/Unit/Docs/GuardIndexTest.php` is what refuses one that is not.
     */
    public const string INDEX = 'docs/guards.md';

    /** The line the sample writes its break on, so a finding's line is assertable. */
    private const int SAMPLE_LINE = 5;

    /** The sample's shape: valid PHP with one comment on line five, and nothing else in it. */
    private const string SAMPLE_SOURCE = "<?php\n\ndeclare(strict_types=1);\n\n%s\n";

    /**
     * The two sentences the log-message mutation makes indistinguishable.
     *
     * The sentence collision's pair: the text a reading has to notice when one reason's sentence is
     * swapped for another's — the sentence an unsuccessful response writes, and the one a body that
     * is not a string writes. Two reasons declared one after the other in the same file, which is how
     * the defect this guard came from shipped.
     */
    private const string KEPT = 'Response is not successful - Response compression skipped - uri: {uri}';

    private const string COLLIDING = 'Response is not a string - Response compression skipped - uri: {uri}';

    /** The one planted tree, made on first use and reused by every mutation. */
    private static ?string $tree = null;

    /**
     * What the mutations have written, keyed by the file they wrote, so the tree can be put back:
     * the bytes that file held before the change, or `null` when the mutation is what put it there.
     *
     * @var array<string, string|null>
     */
    private static array $journal = [];

    /** This package's own root, which is what the planted tree is a copy of. */
    public static function package(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * The copy of this checkout every mutation is planted in, made once per process.
     */
    public static function tree(): string
    {
        if (self::$tree !== null) {
            return self::$tree;
        }

        $root = sys_get_temp_dir().'/rc-mutation-'.bin2hex(random_bytes(6));

        self::makeDirectory($root);

        foreach (self::carried() as $file) {
            self::makeDirectory(dirname($root.'/'.$file));

            if (! copy(self::package().'/'.$file, $root.'/'.$file)) {
                throw new RuntimeException('Could not plant '.$file);
            }
        }

        self::git($root, ['init', '--quiet']);

        // `--force`, because this tree's `.gitignore` excludes `*.txt` and the checkout being
        // copied carries one: the planted tree is the files a commit would carry from here, which
        // is a set the ignore rules have already been applied to. Asking git for the files that
        // merely survive them would plant a tree one file smaller than the one these guards are
        // about.
        self::git($root, ['add', '--all', '--force']);

        register_shutdown_function(static function () use ($root): void {
            ReleaseRepo::remove($root);
        });

        return self::$tree = $root;
    }

    /**
     * The tree as it was planted, by undoing what the mutations wrote rather than by copying this
     * checkout over them again.
     *
     * Called before each mutation as well as after one, so a mutation is never read through the one
     * before it — two breaks in the tree at once is the one thing this harness exists not to do —
     * and a test that failed halfway still leaves the next one a whole tree. A tree nobody has
     * written to costs nothing here, which is what makes calling it unconditionally in a fixture
     * cheaper than remembering when to.
     */
    public static function restore(): void
    {
        // Read once rather than cast per file: a journal with anything in it is a tree that was
        // planted, and the cast is here for the reader that cannot know that.
        $root = (string) self::$tree;

        foreach (self::$journal as $file => $was) {
            $path = $root.'/'.$file;

            if ($was !== null) {
                self::write($path, $was);

                continue;
            }

            @chmod($path, 0o777);

            if (! @unlink($path)) {
                throw new RuntimeException('Could not take the planted '.$file.' back out of the tree');
            }
        }

        self::$journal = [];
    }

    /**
     * The breaks this harness plants, one per guard it covers.
     *
     * `plant` edits the planted tree and answers with the marker the guard's reading has to carry;
     * `read` answers with that reading, written the way the guard's own failure prints it. The
     * marker is what makes this a mutation test rather than a smoke test: a reading that went blind
     * answers with nothing for a broken tree exactly as it does for a whole one, and only the
     * marker says which of the two happened.
     *
     * @param  string|null  $test  one guard's own test, which is what a hold file asks for: the
     *                             mutations about that guard and nothing else. Nothing when the whole
     *                             list is wanted, which is what the accounting in the harness's own
     *                             test asks for.
     * @return list<array{guard: string, test: string, plant: Closure(string): string, read: Closure(string): list<string>}>
     */
    public static function mutations(?string $test = null): array
    {
        $mutations = [
            [
                'guard' => 'the machine-path reading',
                'test' => 'tests/Unit/Support/MachinePathsTest.php',
                'plant' => static function (string $root): string {
                    $leak = self::leak();

                    self::add($root, self::SAMPLE, sprintf(self::SAMPLE_SOURCE, '// '.$leak));

                    return self::sample($leak);
                },
                'read' => static fn (string $root): array => array_map(
                    static fn (array $hit): string => sprintf('%s:%d  %s', $hit['file'], $hit['line'], $hit['path']),
                    MachinePaths::scan($root)['found'],
                ),
            ],
            [
                'guard' => 'the machine-path reading, on a mount spelling',
                'test' => 'tests/Unit/Support/MachinePathsTest.php',
                'plant' => static function (string $root): string {
                    // The same leak as the mutation above, written the other way: a drive letter
                    // becomes the single-letter segment a POSIX shell mounts it as, which is the
                    // spelling the drive pattern cannot see. A reading that lost this half would
                    // still be red on the mutation above it, which is why the two are separate.
                    $leak = self::mountLeak();

                    self::add($root, self::SAMPLE, sprintf(self::SAMPLE_SOURCE, '// '.$leak));

                    return self::sample($leak);
                },
                'read' => static fn (string $root): array => array_map(
                    static fn (array $hit): string => sprintf('%s:%d  %s', $hit['file'], $hit['line'], $hit['path']),
                    MachinePaths::scan($root)['found'],
                ),
            ],
            [
                'guard' => 'the repository-escape reading',
                'test' => 'tests/Unit/Support/RepoEscapesTest.php',
                'plant' => static function (string $root): string {
                    // The shape the IDE entries were written in: a relative path that resolves
                    // wherever this checkout happens to sit, which is one position on one disk.
                    // Written out in full, because two levels up from this file is the root and
                    // the climb only becomes a finding below it — which is the whole rule.
                    $escaped = '../../vendor/bin/pint';

                    self::add($root, self::SAMPLE, sprintf(self::SAMPLE_SOURCE, '// '.$escaped));

                    return self::sample($escaped);
                },
                'read' => static fn (string $root): array => array_map(
                    static fn (array $hit): string => sprintf('%s:%d  %s', $hit['file'], $hit['line'], $hit['path']),
                    RepoEscapes::scan($root)['found'],
                ),
            ],
            [
                'guard' => 'the changelog-preamble reading',
                'test' => 'tests/Unit/Docs/ChangelogPreambleTest.php',
                'plant' => static function (string $root): string {
                    // A version the file has a section for, named above the first heading. The
                    // preamble's claim is that the numbers it names are someone else's history, and
                    // this is the number that claim stops being true for.
                    $named = '0.0.10';

                    self::replace($root, 'CHANGELOG.md', static fn (string $text): string => str_replace(
                        '## Unreleased',
                        'It counts past `'.$named.'`.'
                            ."\n\n"
                            .'## Unreleased',
                        $text,
                    ));

                    return $named;
                },
                'read' => static fn (string $root): array => ChangelogPreamble::stale(
                    (string) file_get_contents($root.'/CHANGELOG.md'),
                ),
            ],
            [
                'guard' => 'the README-versus-config reading',
                'test' => 'tests/Unit/Config/ConfigDocTest.php',
                'plant' => static function (string $root): string {
                    // A default moved in the config file and left alone in the README's copy of
                    // it: the disagreement an operator meets as a number that does not do what
                    // the README says it does.
                    $key = 'min_length';

                    self::replace($root, 'config/response-compression.php', static fn (string $text): string => str_replace(
                        "'RESPONSE_COMPRESSION_MIN_LENGTH', 2048",
                        "'RESPONSE_COMPRESSION_MIN_LENGTH', 4096",
                        $text,
                    ));

                    return $key;
                },
                'read' => static function (string $root): array {
                    $shipped = ConfigDoc::shipped($root.'/config/response-compression.php');
                    $documented = ConfigDoc::documented($root.'/README.md', '## Config');

                    $findings = [];

                    foreach (array_diff_assoc($shipped['leaves'], $documented['leaves']) as $key => $value) {
                        $findings[] = sprintf(
                            '%s: the config file ships %s, and the README documents %s',
                            $key,
                            $value,
                            $documented['leaves'][$key] ?? '(nothing)',
                        );
                    }

                    return $findings;
                },
            ],
            [
                'guard' => 'the config-claim reading',
                'test' => 'tests/Unit/Config/ConfigClaimsTest.php',
                'plant' => static function (string $root): string {
                    // A docblock beside a key the source reads, saying that nothing reads it: the
                    // claim was true when it was written and the wiring made it false, which is
                    // the defect this guard came from. The key is the marker, because it is what
                    // the reading has to pair with the reader that contradicts it.
                    $key = 'min_length';

                    self::replace($root, 'config/response-compression.php', static fn (string $text): string => str_replace(
                        "'min_length' => env(",
                        "    /**\n     * Nothing reads this one yet.\n     */\n    'min_length' => env(",
                        $text,
                    ));

                    return $key;
                },
                // The pairing of a claim with the reader that contradicts it is one call, which is
                // what the guard's test asserts on: a claim planted where the source reads the key
                // is the break it names.
                'read' => ConfigClaims::stale(...),
            ],
            [
                'guard' => 'the guard-index reading',
                'test' => 'tests/Unit/Docs/GuardIndexTest.php',
                'plant' => static function (string $root): string {
                    // A file added to the directory the index describes: which is how a guard
                    // arrives, and the one way the document can stop being a rendering of it. The
                    // mutation is the whole point of the reading — a file that is in neither list
                    // is a decision nobody made, and a rendering that answered "nothing to write"
                    // for this tree would answer it for every tree.
                    $file = 'tests/Support/MutationReading.php';

                    self::add($root, $file, "<?php\n\ndeclare(strict_types=1);\n\n/**\n * A reading the index has no row for.\n *\n * @guards-index reading\n */\nfinal class MutationReading\n");

                    return $file;
                },
                'read' => GuardIndex::drift(...),
            ],
            [
                'guard' => 'the guard index, read from the test side',
                'test' => 'tests/Unit/Docs/GuardIndexTest.php',
                'plant' => static function (string $root): string {
                    // A test that declares itself a guard and has no row in the table: the guard
                    // that arrived unrecorded, which is the half the two rendered lists cannot
                    // see. The file is the marker, so a reading that answered "nothing" for every
                    // tree is told apart from one that answered "nothing" because it never looked
                    // at the tests at all.
                    $file = 'tests/Unit/Support/MutationGuardTest.php';

                    self::add($root, $file, "<?php\n\ndeclare(strict_types=1);\n\n/*\n * A guard the table has no row for.\n *\n * @guards-index guard the mutation guard\n */\n\nit('a planted test', function (): void {});\n");

                    return $file;
                },
                'read' => GuardIndex::drift(...),
            ],
            [
                'guard' => 'the guard index, on a guard no row records',
                'test' => 'tests/Unit/Docs/GuardIndexTest.php',
                'plant' => static function (string $root): string {
                    // A guard declared in a file of its own, which the table has no row for: the
                    // half the rendering adds — the table is written from the files now, so a
                    // guard arriving in one and going unrecorded is the way this reading can go
                    // quiet. The name is the marker, because it is what the failure prints.
                    self::add($root, 'bin/mutation-guard.php', "<?php\n\ndeclare(strict_types=1);\n\n/**\n * @guards-index guard the mutation guard row\n *\n * a break no row records\n */\n");

                    return 'no row declares the guard `the mutation guard row`';
                },
                'read' => GuardIndex::drift(...),
            ],
            [
                'guard' => 'the docs-link reading',
                'test' => 'tests/Unit/Docs/DocsLinksTest.php',
                'plant' => static function (string $root): string {
                    // A record added to `docs/` whose link names a file beside it that is not there:
                    // the renamed test, the moved doc, the path written from the wrong directory.
                    // It is planted as a new record rather than as a link in an existing one
                    // because the reading is a walk over the markdown this package ships — a file
                    // the walk never reaches is not a finding, and the marker has to be one the
                    // walk is supposed to reach.
                    $file = 'docs/mutation-broken-link.md';
                    $target = 'a-record-that-was-renamed.md';

                    self::add($root, $file, "# A record the walk has to reach\n\nSee [gone](".$target.").\n");

                    return $file.':3 -> '.$target;
                },
                'read' => Docs::broken(...),
            ],
            [
                'guard' => 'the docs-link reading, on a record nothing links to',
                'test' => 'tests/Unit/Docs/DocsLinksTest.php',
                'plant' => static function (string $root): string {
                    // A record added to `docs/` that the README does not link: the doc that was
                    // never explained to anyone, which is the other half of this guard and the one
                    // a walk over the links alone cannot see. The filename is the marker, because
                    // a reading that answered "nothing" for every tree would answer it here too.
                    $file = 'docs/mutation-unlinked-record.md';

                    self::add($root, $file, "# A record nobody is sent to\n\nIt explains itself to no one.\n");

                    return basename($file);
                },
                'read' => Docs::unlinkedRecords(...),
            ],
            [
                'guard' => 'the config-key reading',
                'test' => 'tests/Unit/Config/ConfigKeysTest.php',
                'plant' => static function (string $root): string {
                    // A reader asking for a key the config file does not publish: the brotli defect,
                    // written as a file rather than found in one. The marker is the whole finding
                    // up to the line number, so a reading that named the key but not the file — or
                    // the file but not the key — is told apart from one that read both.
                    $file = 'src/MutationConfigRead.php';

                    self::add($root, $file, "<?php\n\ndeclare(strict_types=1);\n\nnamespace Uak35\\ResponseCompression\\Mutation;\n\nuse Uak35\\ResponseCompression\\Support\\Config;\n\nfinal class MutationConfigRead\n{\n    public static function level(): int\n    {\n        return Config::int('response-compression.mutation_level');\n    }\n}\n");

                    // The path the reading prints is relative to the scanned directory (`src/`),
                    // which is the shape every finding of this guard has, so the marker stops at
                    // the file and leaves the line number to the reading.
                    return 'response-compression.mutation_level is read by int() in MutationConfigRead.php';
                },
                'read' => ConfigKeys::unknown(...),
            ],
            [
                'guard' => 'the config-reader-list reading',
                'test' => 'tests/Unit/Config/ConfigKeysTest.php',
                'plant' => static function (string $root): string {
                    // A reader renamed in the class the list describes: the method and the list then
                    // disagree, and the scan — which reads a reader's *name* — would read a key
                    // invisibly in both directions at once. The new name is the marker, so a
                    // reading that looked at the list alone is told apart from one that read the
                    // class.
                    $renamed = 'intOrElse';

                    self::replace($root, ConfigKeys::SOURCE, static fn (string $text): string => str_replace(
                        'public static function intOr(',
                        'public static function '.$renamed.'(',
                        $text,
                    ));

                    return $renamed;
                },
                'read' => ConfigKeys::unknownReaders(...),
            ],
            [
                'guard' => 'the tool-path reading',
                'test' => 'tests/Unit/Support/ToolPathsTest.php',
                'plant' => static function (string $root): string {
                    // A tool's path written down in a program that already has a name for it: the
                    // second copy the manifest exists to stop. The path is read out of the
                    // manifest rather than typed, so the marker is a path this tree really carries
                    // — a fixture with a made-up path would prove the reading looks at the file
                    // without proving it compares the right list.
                    $path = ToolPaths::manifest($root.'/'.ToolPaths::MANIFEST)['pest']['path'];

                    self::replace($root, 'bin/checks.php', static fn (string $text): string => str_replace(
                        'declare(strict_types=1);',
                        "declare(strict_types=1);\n\n// \$tools['pest'] is ".$path,
                        $text,
                    ));

                    return 'bin/checks.php writes '.$path.' down';
                },
                'read' => ToolPaths::copies(...),
            ],
            [
                'guard' => 'the tool-name reading',
                'test' => 'tests/Unit/Support/ToolPathsTest.php',
                'plant' => static function (string $root): string {
                    // A program asking the manifest for a name it does not have: the typo that reads
                    // as "not installed" at runtime, which is a skip in the summary that looks
                    // like a machine without the tool rather than a name nothing defines. The name
                    // is planted as a lookup the reader already knows how to read, so the marker is
                    // the name and not the file that carries it.
                    $name = 'mutation-tool';

                    self::replace($root, 'bin/checks.php', static fn (string $text): string => str_replace(
                        'declare(strict_types=1);',
                        "declare(strict_types=1);\n\n// \$tools['".$name."'], which nothing names.",
                        $text,
                    ));

                    return $name;
                },
                'read' => ToolPaths::unsharedNames(...),
            ],
            // `ToolPaths::pinDrift()` has no mutation here, and the reason is in this class's
            // docblock: half of what it reads is `vendor/composer/installed.json`, which this
            // repository ignores and a planted tree therefore never carries.
            [
                'guard' => 'the number-claims reading',
                'test' => 'tests/Unit/Docs/IndexNumbersTest.php',
                'plant' => static function (string $root): string {
                    // The gate gains a check and the index's sentence keeps the number it was
                    // written with: true the day it was typed, and nothing in the document to tell
                    // it that the array below it grew. This is a row turned into a sentence — the
                    // same claim written as prose, where nothing else held it to the program it
                    // describes.
                    $said = 'twelve checks in one process';

                    self::replace($root, IndexNumbers::DOCUMENT, static fn (string $text): string => str_replace(
                        'eleven checks in one process',
                        $said,
                        $text,
                    ));

                    return $said;
                },
                'read' => IndexNumbers::drift(...),
            ],
            [
                'guard' => 'the log-message reading',
                'test' => 'tests/Unit/Middleware/LogMessagesTest.php',
                'plant' => static function (string $root): string {
                    // One arm of the type's `match` rewritten to another reason's sentence: the
                    // collision the type cannot rule out, because two cases are free to say the same
                    // words — which is the defect this guard came from, one file along.
                    self::replace($root, LogMessages::SOURCE, static fn (string $text): string => str_replace(
                        "self::ResponseNotString => '".self::COLLIDING."',",
                        "self::ResponseNotString => '".self::KEPT."',",
                        $text,
                    ));

                    return 'ResponseNotSuccessful and ResponseNotString write the same line: '.self::KEPT;
                },
                'read' => static fn (string $root): array => LogMessages::duplicates(
                    LogMessages::declaredIn((string) file_get_contents($root.'/'.LogMessages::SOURCE)),
                ),
            ],
            [
                'guard' => 'the log-message reading, on the code',
                'test' => 'tests/Unit/Middleware/LogMessagesTest.php',
                'plant' => static function (string $root): string {
                    // A second case handed the code of one declared above it: the collision the type
                    // carries silently, because PHP's duplicate-value error arrives only when the
                    // enum is asked for a case by value, which this package never does. The line a
                    // log holds is then the same for two reasons, which is exactly what a reader
                    // greps for and what the sentence collision cannot see.
                    self::replace($root, LogMessages::SOURCE, static fn (string $text): string => str_replace(
                        "case ResponseNotString = 'response.not_string';",
                        "case ResponseNotString = 'response.not_successful';",
                        $text,
                    ));

                    return 'ResponseNotSuccessful and ResponseNotString both carry the code response.not_successful';
                },
                'read' => static fn (string $root): array => LogMessages::duplicates(
                    LogMessages::declaredIn((string) file_get_contents($root.'/'.LogMessages::SOURCE)),
                ),
            ],
            [
                'guard' => 'the release-lane reading',
                'test' => 'tests/Unit/Docs/ReleaseLaneTest.php',
                'plant' => static function (string $root): string {
                    // The drift this guard was written for, planted back: the by-hand push naming
                    // the branch the release program *defaults* to. It is the shape that reads as
                    // correct — every word of it is a command somebody would run — and the one that
                    // stops at the branch rail the moment it is run, which is why nothing noticed
                    // it for a release.
                    self::replace($root, 'RELEASING.md', static fn (string $text): string => str_replace(
                        'git push origin development --follow-tags',
                        'git push origin main --follow-tags',
                        $text,
                    ));

                    return 'git push origin names `main`, and releases are cut from `development`';
                },
                'read' => ReleaseLane::drift(...),
            ],
            [
                'guard' => 'the environment table',
                'test' => 'tests/Unit/Support/HandoffTest.php',
                'plant' => static function (string $root): string {
                    // A row added to the environment table naming a token the renderer has never
                    // heard of: what a table looks like when it is written ahead of the file that was
                    // to supply it. It is the half of the reading that has to fire on every machine —
                    // a name nothing resolves rather than a value a machine happens to be missing —
                    // and the half a reading which had stopped resolving names could not see.
                    $token = '__PYTHON_EXE__';

                    self::replace($root, Handoff::RULES, static fn (string $text): string => str_replace(
                        '| Linux shell (mandatory) | `bash` |',
                        '| Linux shell (mandatory) | `bash` |'.PHP_EOL.'| Python | `'.$token.'` |',
                        $text,
                    ));

                    return $token;
                },
                'read' => Handoff::stale(...),
            ],
        ];

        return self::held($mutations, $test, 'mutation');
    }

    /**
     * The inversions: each reading's own detector, rewritten so it cannot fire, with that guard's
     * tests required to go red.
     *
     * WHY THIS IS THE OTHER DIRECTION
     * -------------------------------
     * A mutation above breaks the *tree* and requires the reading to name the break. It catches a
     * reading that stopped asking the right question of its input, because the input it is handed
     * now has something wrong in it. What it cannot catch is a reading whose *test* stopped
     * asserting: a test that asks a call whose answer is empty both before and after, or one whose
     * expectations a walk that reached nothing satisfies. The reading would be broken and the suite
     * green, in both directions at once.
     *
     * So the detector itself is rewritten — a pattern that cannot match, a walk that returns before
     * it reads — and the guard's own tests are run against it. They have to go red. A test that
     * stays green while the reading it is supposed to be checking cannot fire is the vacuous test
     * this exists to refuse, and it is a failure the mutation above cannot see: the planted tree is
     * broken, the reading names the break, the test passes — and the reading is one character away
     * from answering "nothing" for every tree.
     *
     * HOW A REWRITTEN READING IS LOADED
     * ---------------------------------
     * It cannot be planted in this checkout: the class is already loaded in the process running
     * these tests, so a second copy on disk would be read as the one it was loaded from. Instead a
     * copy is written into an overlay directory, and a bootstrap prepends that directory to
     * Composer's PSR-4 mapping for `Tests\\Support` before Pest starts. The guard's own tests are
     * then run in a child process under that bootstrap, with every other class resolved from this
     * checkout — so the rewrite in the overlay is the only difference between the green run and the
     * red one, which is what `unblinded()` exists to show.
     *
     * @param  string|null  $test  one guard's own test, which is what a hold file asks for: the
     *                             inversions about that guard and nothing else.
     * @return list<array{guard: string, test: string, reading: string, blind: Closure(string): string}>
     */
    public static function inversions(?string $test = null): array
    {
        $inversions = [
            [
                'guard' => 'the machine-path reading',
                'test' => 'tests/Unit/Support/MachinePathsTest.php',
                'reading' => 'MachinePaths',
                // The drive pattern's own character class, made one no drive can match: the detector
                // is still a pattern, still run on every line, and can no longer fire.
                'blind' => static fn (string $source): string => str_replace('([A-Za-z]:', '(NEVER:', $source),
            ],
            [
                'guard' => 'the repository-escape reading',
                'test' => 'tests/Unit/Support/RepoEscapesTest.php',
                'reading' => 'RepoEscapes',
                // The path token, replaced whole: the walk still runs and the samples the two
                // exempt files carry stop being found, which is the assertion this has to redden.
                'blind' => static fn (string $source): string => preg_replace(
                    '~private const string TOKEN = .*;~',
                    "private const string TOKEN = '~NEVER-MATCHES~';",
                    $source,
                ) ?? $source,
            ],
            [
                'guard' => 'the changelog-preamble reading',
                'test' => 'tests/Unit/Docs/ChangelogPreambleTest.php',
                'reading' => 'ChangelogPreamble',
                // No second-level heading is read as a released version, so a preamble naming one
                // the file has released reads as clean.
                'blind' => static fn (string $source): string => str_replace(
                    "preg_match_all('/^##[ \\t]+\\[?v?(\\d+\\.\\d+\\.\\d+)/m', \$content, \$matches)",
                    "preg_match_all('/NEVER-MATCHES/m', \$content, \$matches)",
                    $source,
                ),
            ],
            [
                'guard' => 'the README-versus-config reading',
                'test' => 'tests/Unit/Config/ConfigDocTest.php',
                'reading' => 'ConfigDoc',
                // The parse returns before it reads a line: the reader that found nothing, which
                // is the shape a test asserting on its own substance is there to refuse.
                'blind' => static fn (string $source): string => str_replace(
                    '        ksort($leaves);',
                    "        return ['leaves' => [], 'lists' => [], 'docblocks' => []];\n\n        ksort(\$leaves);",
                    $source,
                ),
            ],
            [
                'guard' => 'the config-key reading',
                'test' => 'tests/Unit/Config/ConfigKeysTest.php',
                'reading' => 'ConfigKeys',
                // The walk over `src/` returns before it descends, so the source reads as a tree
                // that asks for no key at all.
                'blind' => static fn (string $source): string => str_replace(
                    "    private static function phpFiles(string \$directory): array\n    {\n        \$files = [];",
                    "    private static function phpFiles(string \$directory): array\n    {\n        return [];\n        \$files = [];",
                    $source,
                ),
            ],
            [
                'guard' => 'the config-claim reading',
                'test' => 'tests/Unit/Config/ConfigClaimsTest.php',
                'reading' => 'ConfigClaims',
                // No marker is recognised, so a docblock saying a key is unread stops being a claim
                // and the pairing with the reader that contradicts it has nothing to pair.
                'blind' => static fn (string $source): string => str_replace(
                    "        foreach (self::MARKERS as \$marker) {\n            if (str_contains(\$text, \$marker)) {",
                    "        foreach (self::MARKERS as \$marker) {\n            if (false) {",
                    $source,
                ),
            ],
            [
                'guard' => 'the docs-link reading',
                'test' => 'tests/Unit/Docs/DocsLinksTest.php',
                'reading' => 'Docs',
                // The link pattern cannot match, so every document reads as holding no links — the
                // half both of this guard's questions are asked through.
                'blind' => static fn (string $source): string => str_replace(
                    "preg_match_all('/\\]\\(([^)\\s]+)\\)/', \$line, \$matches)",
                    "preg_match_all('/NEVER-MATCHES/', \$line, \$matches)",
                    $source,
                ),
            ],
            [
                'guard' => 'the guard-index reading',
                'test' => 'tests/Unit/Docs/GuardIndexTest.php',
                'reading' => 'GuardIndex',
                // The table parser answers with no header and no rows, which is the shape a
                // document with no table has — so the row walk has nothing to resolve.
                'blind' => static fn (string $source): string => str_replace(
                    "        return ['header' => \$header, 'rows' => \$rows];",
                    "        return ['header' => [], 'rows' => []];",
                    $source,
                ),
            ],
            [
                'guard' => 'the number-claims reading',
                'test' => 'tests/Unit/Docs/IndexNumbersTest.php',
                'reading' => 'IndexNumbers',
                // The gate's `$checks` keys are no longer parsed, so the count the document states
                // has nothing to be compared with and the reading refuses rather than agreeing.
                'blind' => static fn (string $source): string => str_replace(
                    'preg_match_all("/^ {4}',
                    'preg_match_all("/NEVER-MATCHES {4}',
                    $source,
                ),
            ],
            [
                'guard' => 'the log-message reading',
                'test' => 'tests/Unit/Middleware/LogMessagesTest.php',
                'reading' => 'LogMessages',
                // No `case` line is read, so the file declares nothing and the collision it exists
                // to find has nothing to be found in.
                'blind' => static fn (string $source): string => str_replace(
                    "    private const string CASE = '~^\\s*case ([A-Za-z_][A-Za-z0-9_]*) = \\'((?:[^\\'\\\\\\\\]|\\\\\\\\.)*)\\';\\s*\$~';",
                    "    private const string CASE = '~NEVER-MATCHES~';",
                    $source,
                ),
            ],
            [
                'guard' => 'the tool-path reading',
                'test' => 'tests/Unit/Support/ToolPathsTest.php',
                'reading' => 'ToolPaths',
                // A tool name cannot be read out of a program, so nothing is asked for and the
                // manifest's names all read as names nothing runs.
                'blind' => static fn (string $source): string => str_replace(
                    '(?<name>[a-z0-9-]+)',
                    '(?<name>NEVER_MATCHES)',
                    $source,
                ),
            ],
            [
                'guard' => 'the release-lane reading',
                'test' => 'tests/Unit/Docs/ReleaseLaneTest.php',
                'reading' => 'ReleaseLane',
                // The flag pattern, made one no `--branch=` can match: the shape is still read on
                // every line, and a command stops being told from a sentence that mentions one —
                // which is the assertion the guard's own test has to redden on.
                'blind' => static fn (string $source): string => str_replace(
                    "preg_match_all('#--branch=([A-Za-z0-9][A-Za-z0-9._/-]*)#', \$line, \$matches) > 0",
                    "preg_match_all('#NEVER-MATCHES#', \$line, \$matches) > 0",
                    $source,
                ),
            ],
        ];

        return self::held($inversions, $test, 'inversion');
    }

    /**
     * The programs that are guards in their own right: one rail taken out of one of them, and that
     * program's own test required to go red.
     *
     * WHY A PROGRAM IS A THIRD DIRECTION
     * ---------------------------------
     * A mutation above breaks the *tree*, and an inversion rewrites a *reading*; both are about a
     * guard whose answer is a call. A rail in `bin/release.php` answers with an exit code instead,
     * and the thing that catches a rail which stopped refusing is the test that asserts on that
     * rail. `bin/release.php` is the program here whose fixture can be built from a script a caller
     * names — `ReleaseRepo::mutated()`, and `RC_RELEASE_FIXTURE_SCRIPT` for the process that builds
     * its own — which is what makes it the one that can be held this way at all: a test cannot be
     * handed a program as an argument, so the rewrite arrives through the environment of the child
     * process the test runs in.
     *
     * `rail` is the rail's own sentence, the string the test that holds it asserts on, and the marker
     * this direction is read for — the same rule the mutations above are held to, because a run that
     * went red for any other reason is not evidence that the rail was being held.
     *
     * @param  string|null  $test  one program's own test, which is what its hold file asks for: the
     *                             control and the red run then sit in two files rather than one that
     *                             waits on itself.
     * @return list<array{guard: string, test: string, program: string, rail: string, rewrite: Closure(string): string}>
     */
    public static function programs(?string $test = null): array
    {
        $programs = [
            [
                'guard' => 'the branch rail',
                'test' => 'tests/Unit/Release/RailsTest.php',
                'program' => ReleaseRepo::SCRIPT,
                'rail' => 'A release is cut from release, and HEAD is on main.',
                'rewrite' => static fn (string $source): string => self::without($source, '$current !== $branch'),
            ],
        ];

        return self::held($programs, $test, 'program');
    }

    /**
     * Every guard the harness holds, by the test that holds it — the three directions read as one
     * set, once each and in one order.
     *
     * The set is derived from the holds rather than listed: the question it answers is whether the
     * directory carries what the lists hold, and a list of its own could agree with one of them and
     * not the other.
     *
     * @return list<string>
     */
    public static function subjects(): array
    {
        $tests = [
            ...array_column(self::mutations(), 'test'),
            ...array_column(self::inversions(), 'test'),
            ...array_column(self::programs(), 'test'),
        ];

        $tests = array_values(array_unique($tests));
        sort($tests);

        return $tests;
    }

    /**
     * The guard each file in the hold directory asks this class for, read out of the file itself.
     *
     * The argument a hold file passes is the file's own claim about which guard it carries, and the
     * reading takes it at its word: the directory is the list, so a guard the lists hold and no file
     * asks for is a finding rather than a silence, and a file that asked for no guard at all — or two
     * files that asked for the same one — is refused rather than run. The two are held to each other
     * in the harness's own test, which is what makes adding a guard a decision: it arrives with a
     * file, or the suite says so.
     *
     * @return list<string>
     *
     * @throws RuntimeException when a file in the directory asks for no guard, or when two files
     *                          ask for the same one
     */
    public static function claimed(): array
    {
        $claimed = [];
        // The root in the form a path is printed relative to: `Docs::relative()` normalises the
        // path it is handed and not the root, and this machine's `dirname()` writes backslashes.
        $root = str_replace('\\', '/', self::package());
        $files = glob(self::package().'/'.self::HOLDS.'/*.php') ?: [];

        sort($files);

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            preg_match_all(
                '/MutationHarness::(?:mutations|inversions|programs)\(\s*\'([^\']+)\'/',
                $source,
                $matches,
            );

            $guards = array_values(array_unique($matches[1]));

            if ($guards === []) {
                throw new RuntimeException(sprintf(
                    '%s asks the harness for no guard, so there is nothing holding the guard it was written for.',
                    Docs::relative($root, $file),
                ));
            }

            foreach ($guards as $guard) {
                if (isset($claimed[$guard])) {
                    throw new RuntimeException(sprintf(
                        '%s and %s both ask for %s: a guard is held in one file, so the holds can be spread over workers and a reader can find them.',
                        Docs::relative($root, $claimed[$guard]),
                        Docs::relative($root, $file),
                        $guard,
                    ));
                }

                $claimed[$guard] = $file;
            }
        }

        $guards = array_keys($claimed);
        sort($guards);

        return $guards;
    }

    /**
     * One program with one rail taken out of it, and its own test run against what is left.
     *
     * @param  array{guard: string, test: string, program: string, rail: string, rewrite: Closure(string): string}  $program
     * @return array{exit: int, output: string}
     */
    public static function disarmed(array $program): array
    {
        $source = self::program($program['program']);
        $rewritten = ($program['rewrite'])($source);

        // A rewrite that changed nothing would leave the rail standing and the run green, which is
        // exactly the passing test this exists to make impossible. The rewrites raise on a needle
        // that stopped matching as well, so this is the second half of one rule rather than it.
        if ($rewritten === $source) {
            throw new RuntimeException(sprintf(
                'The rewrite of %s left it exactly as it was, so there is no rail for its test to catch.',
                $program['program'],
            ));
        }

        return self::handed($program['test'], $rewritten);
    }

    /**
     * The control: the same child process and the same variable, with the program copied verbatim.
     *
     * Without it a red run could be the variable, the bootstrap or the child process rather than the
     * rail taken out of the program. The claim of a hold is that the rewrite is the only difference
     * between the two answers, and this is the run that says so — one per program, because the
     * variable names one script per process.
     *
     * @param  array{guard: string, test: string, program: string, rail: string, rewrite: Closure(string): string}  $program
     * @return array{exit: int, output: string}
     */
    public static function verbatim(array $program): array
    {
        return self::handed($program['test'], self::program($program['program']));
    }

    /**
     * One guard's tests, run in a child process under an overlay that holds a rewritten reading.
     *
     * The overlay is a directory of `Tests\\Support` files that Composer's PSR-4 mapping is pointed
     * at first, and the bootstrap that does that is written beside it. Every file in the overlay is
     * a full copy of the one it stands for — rewritten for an inversion, verbatim for the control —
     * so a class that is not in it resolves from this checkout exactly as it does in the suite.
     *
     * @param  array<string, string>  $readings  class name => the file's contents, as it should load
     * @param  list<string>  $tests  the guard's test files, one or more
     * @param  array<string, string>  $env  variables the child is started with beside the ones it
     *                                      would inherit, which is how a rewritten *program* reaches
     *                                      a test that cannot be passed one as an argument
     * @return array{exit: int, output: string}
     */
    public static function under(array $readings, array $tests, array $env = []): array
    {
        $root = sys_get_temp_dir().'/rc-inversion-'.bin2hex(random_bytes(6));

        self::makeDirectory($root.'/Support');

        foreach ($readings as $reading => $contents) {
            self::write($root.'/Support/'.$reading.'.php', $contents);
        }

        $bootstrap = $root.'/bootstrap.php';

        self::write($bootstrap, sprintf(
            "<?php\n\n\$loader = require %s;\n\n\$loader->setPsr4('Uak35\\\\ResponseCompression\\\\Tests\\\\Support\\\\', [%s, %s]);\n",
            var_export(self::package().'/vendor/autoload.php', true),
            var_export($root.'/Support', true),
            var_export(self::package().'/tests/Support', true),
        ));

        register_shutdown_function(static function () use ($root): void {
            ReleaseRepo::remove($root);
        });

        // The manifest's own Pest rather than a path written here: which file runs the suite is one
        // fact about this package, and `ToolPaths` is where it is stated.
        $pest = self::package().'/'.ToolPaths::manifest(self::package().'/'.ToolPaths::MANIFEST)['pest']['path'];

        $files = array_map(static fn (string $file): string => self::package().'/'.$file, $tests);

        $command = [PHP_BINARY, $pest, '--no-coverage', '--colors=never', '--bootstrap', $bootstrap, ...$files];

        // The variable beside the environment the child would have had, rather than instead of it:
        // what `proc_open()` is given is the whole environment on Windows, and a run that lost `PATH`
        // and `SystemRoot` would be red for a reason about the machine rather than about the program.
        $environment = null;

        if ($env !== []) {
            $inherited = getenv();
            $environment = array_replace(is_array($inherited) ? $inherited : [], $env);
        }

        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes,
            self::package(),
            $environment,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not run '.implode(' ', $tests).' under the rewriting harness');
        }

        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return ['exit' => proc_close($process), 'output' => $output];
    }

    /**
     * One inversion: the reading's detector rewritten, and that guard's tests run against it.
     *
     * @param  array{guard: string, test: string, reading: string, blind: Closure(string): string}  $inversion
     * @return array{exit: int, output: string}
     */
    public static function blinded(array $inversion): array
    {
        $source = self::reading($inversion['reading']);
        $rewritten = ($inversion['blind'])($source);

        // A rewrite whose needle stopped matching would leave the detector whole and the guard
        // green, which is exactly the passing test this exists to make impossible.
        if ($rewritten === $source) {
            throw new RuntimeException(sprintf(
                'The rewrite of %s left it exactly as it was, so there is no broken detector for the guard to catch.',
                $inversion['reading'],
            ));
        }

        return self::under([$inversion['reading'] => $rewritten], [$inversion['test']]);
    }

    /**
     * The control: every reading copied in verbatim, and every guard's tests run under them.
     *
     * Without this, a red run could be the overlay, the bootstrap or the child process rather than
     * the rewrite — and the whole point of an inversion is that the rewrite is the only difference
     * between the two answers. One run rather than one per inversion, because the claim is about the
     * overlay mechanism rather than about a reading: if a verbatim overlay is transparent to every
     * guard at once, it is transparent to each of them.
     *
     * @param  list<array{guard: string, test: string, reading: string, blind: Closure(string): string}>  $inversions
     * @return array{exit: int, output: string}
     */
    public static function unblinded(array $inversions): array
    {
        $readings = [];
        $tests = [];

        foreach ($inversions as $inversion) {
            $readings[$inversion['reading']] = self::reading($inversion['reading']);
            $tests[] = $inversion['test'];
        }

        return self::under($readings, array_values(array_unique($tests)));
    }

    /**
     * A program's own test, in a child process in which every fixture is built from that program.
     *
     * The program is written to a directory of its own rather than into this checkout: the fixture
     * reads the file the variable names, and the suite's own copy is the one every other test here
     * is measuring. No overlay is planted — the rewrite is a program rather than a class, so it
     * arrives the other way — and the empty overlay the run is started with is the same mechanism
     * the control above is green under.
     *
     * @return array{exit: int, output: string}
     */
    private static function handed(string $test, string $program): array
    {
        $directory = sys_get_temp_dir().'/rc-program-'.bin2hex(random_bytes(6));
        $script = $directory.'/release.php';

        self::makeDirectory($directory);
        self::write($script, $program);

        register_shutdown_function(static function () use ($directory): void {
            ReleaseRepo::remove($directory);
        });

        return self::under([], [$test], [ReleaseRepo::SCRIPT_ENV => $script]);
    }

    /** One program, as this checkout ships it. */
    private static function program(string $program): string
    {
        return (string) file_get_contents(self::package().'/'.$program);
    }

    /**
     * One program with one condition taken out of it.
     *
     * The condition is replaced by the one that cannot hold rather than deleted, so the file is
     * still a program: what stopped is the rail, not the parsing. A needle that matched no line or
     * more than one raises rather than planting nothing, because a rewrite that changed nothing
     * would leave the rail standing and be read as a rail that held.
     */
    private static function without(string $source, string $condition): string
    {
        $needle = 'if ('.$condition.') {';
        $found = substr_count($source, $needle);

        if ($found !== 1) {
            throw new RuntimeException(sprintf(
                'The condition `%s` appears %d time(s) in the program, so there is no one rail to take out of it.',
                $needle,
                $found,
            ));
        }

        return str_replace($needle, 'if (false) {', $source);
    }

    /** One reading, as this checkout ships it. */
    private static function reading(string $reading): string
    {
        return (string) file_get_contents(self::package().'/tests/Support/'.$reading.'.php');
    }

    /**
     * The path a mutation leaks, assembled rather than written out.
     *
     * A file that spells a machine path is a file this package's own machine-path guard has to be
     * exempted from reporting, and an exemption excuses every other path in that file for as long
     * as the file exists. Written as four parts joined at run time, the text in this file holds no
     * drive letter followed by a separator and no home directory, so this file is read by that
     * guard the way every other file is — while the copy a mutation plants carries the whole path
     * on one line, which is where it has to be for the guard to have something to catch.
     */
    private static function leak(): string
    {
        return implode('', ['C:', '/Users', '/a-reader/', '.gitconfig']);
    }

    /**
     * The mount-spelled leak, assembled for the same reason and one more.
     *
     * Split so that no line of this file holds what the mount pattern asks for — a
     * single-letter segment with two segments after it — because this file is not one of the two
     * the guard is allowed to report, and the exemption it would otherwise need would excuse
     * every other line of it for as long as it exists. The copy the mutation plants carries the
     * whole path on one line, which is where it has to be to have something to catch.
     */
    private static function mountLeak(): string
    {
        return implode('', ['/e', '/a-reader', '/workspace']);
    }

    /** A finding as the two path readings print it: the file, its line, and the path. */
    private static function sample(string $path): string
    {
        return self::SAMPLE.':'.self::SAMPLE_LINE.'  '.$path;
    }

    /** A file a mutation puts into the tree, which it was not carrying before. */
    private static function add(string $root, string $file, string $contents): void
    {
        if (file_exists($root.'/'.$file)) {
            throw new RuntimeException('The planted tree already carries '.$file);
        }

        self::$journal[$file] = null;

        self::write($root.'/'.$file, $contents);
    }

    /**
     * One tracked file, rewritten by a mutation.
     *
     * A change that changed nothing raises rather than planting nothing. A mutation is a claim that
     * this file is what the guard is reading, and a `str_replace` whose needle stopped matching
     * would leave the tree whole and the reading green — which is exactly the passing test this
     * harness exists to make impossible.
     */
    private static function replace(string $root, string $file, Closure $change): void
    {
        $path = $root.'/'.$file;
        $was = (string) file_get_contents($path);
        $now = $change($was);

        if ($now === $was) {
            throw new RuntimeException('A mutation of '.$file.' left it exactly as it was, so there is nothing in the tree to catch.');
        }

        // The first bytes the file held are the ones to put back, so a file two mutations write to
        // is journalled once.
        if (! array_key_exists($file, self::$journal)) {
            self::$journal[$file] = $was;
        }

        self::write($path, $now);
    }

    private static function write(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Could not write '.$path);
        }
    }

    private static function makeDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0o777, true) && ! is_dir($path)) {
            throw new RuntimeException('Could not create '.$path);
        }
    }

    /**
     * One direction's holds, as the one file that asked for this guard needs them.
     *
     * A slice that came back empty raises rather than answering with a shorter list: a file whose
     * guard had been renamed would run no test at all, and a run of no tests reports the same green
     * as a run of every one of them — which is the silence this harness exists to refuse, one layer
     * up from the ones it plants.
     *
     * @template THold of array{test: string}
     *
     * @param  list<THold>  $holds
     * @return list<THold>
     */
    private static function held(array $holds, ?string $test, string $direction): array
    {
        if ($test === null) {
            return $holds;
        }

        $held = array_values(array_filter($holds, static fn (array $hold): bool => $hold['test'] === $test));

        if ($held === []) {
            throw new RuntimeException(sprintf(
                'No %s holds %s, so a file asking for that guard would run nothing and report it green.',
                $direction,
                $test,
            ));
        }

        return $held;
    }

    /**
     * The files a commit would carry from this checkout — the tracked set and the files that have
     * just been written, which is what both path readings walk and therefore what the planted tree
     * has to be.
     *
     * The listing comes from `MachinePaths::files()` rather than from a second `git ls-files`
     * written here. The planted tree, the tree the guards walk and the tree a commit would publish
     * are one question, and two answers to it are two answers that can come to disagree.
     *
     * @return list<string>
     */
    private static function carried(): array
    {
        return MachinePaths::files(self::package());
    }

    /**
     * A git command in a directory, answering with its output.
     *
     * A planting step that quietly did nothing would leave a tree that agrees with every guard, so
     * a git that failed is a complaint rather than a silence.
     *
     * @param  list<string>  $arguments
     */
    private static function git(string $root, array $arguments): string
    {
        $command = ['git', '-C', $root, ...$arguments];

        // The same descriptors `MachinePaths::files()` opens: a pipe for the output, the error
        // stream folded into it, and no stdin for a command that never reads one.
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Could not run git in '.$root);
        }

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $exit = proc_close($process);

        if ($exit !== 0) {
            throw new RuntimeException(sprintf(
                '`git %s` failed in %s:%s%s',
                implode(' ', $arguments),
                $root,
                PHP_EOL,
                $output,
            ));
        }

        return $output;
    }
}
