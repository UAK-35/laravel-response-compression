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
 * guard counts the calls it saw against the text of the file — and that covers the first way a
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
 * it. `MachinePaths::scan`, `RepoEscapes::scan`, `ChangelogPreamble::stale`, `ConfigClaims::all`,
 * `GuardIndex::drift` and `LogMessages`'s pair are one call each, and `ConfigDoc`'s two halves are
 * one comparison, so this class is a caller rather than a copy for each of them. The guards whose
 * reading is a *walk* composed in the test — the docs links, the config keys, the tool paths —
 * are declined in `UNAUDITED`, with the reason: a walk written out here a second time would be a
 * guard of its own, which is the one thing this package's readings exist to avoid.
 * `MutationHarnessTest` checks both directions of that list, so a guard cannot be added without
 * either a mutation or a reason that is still true.
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
     * The guards the index records that this harness does not mutate, with the reason for each.
     *
     * A list rather than a silence. `MutationHarnessTest` reads it in both directions: a guard in
     * `docs/guards.md` that is missing from it fails, and a name in it that the index no longer
     * records fails too — so every line here is a claim about the suite rather than a note beside
     * it, and the reasons cannot outlive the guards they are about.
     */
    public const array UNAUDITED = [
        'tests/Unit/Config/ConfigKeysTest.php' => 'the reading is not one call: the keys the config publishes and the keys each file in `src/` reads are two walks, and the comparison between them is written in the test — a second copy of it here would be a guard of its own',
        'tests/Unit/Docs/DocsLinksTest.php' => 'the reading is a walk over every markdown file, resolving each link against the file it was written in. The walk is the test, and a copy of it here would be a second guard wearing this one\'s name',
        'tests/Unit/Support/ToolPathsTest.php' => 'the same: five readings are composed in the test, and no single one of them is the guard',
        'tests/Unit/Gate/ChecksTest.php' => 'the guard is a program rather than a reading. `bin/checks.php` is run as a child process in a planted tree, which is what `ReleaseRepo` is for, and there is nothing in this checkout to break and ask again — the tree it is asked about already belongs to its own test',
        'tests/Unit/Gate/CoverageTest.php' => 'the same: `bin/coverage.php` is a program, and the fixture it is driven in is the test\'s own',
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
     * They are the middleware's own, copied here as the text a reading has to notice — the
     * `enable_logging` output for an unsuccessful response, and for one whose body is not a
     * string. Two branches one after the other in the same file, which is how the defect this
     * guard came from shipped.
     */
    private const string KEPT = 'Response is not successful - Response compression skipped - uri: ';

    private const string COLLIDING = 'Response is not a string - Response compression skipped - uri: ';

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
     * @return list<array{guard: string, test: string, plant: Closure(string): string, read: Closure(string): list<string>}>
     */
    public static function mutations(): array
    {
        return [
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
                    // the defect this guard came from.
                    $key = 'min_length';

                    self::replace($root, 'config/response-compression.php', static fn (string $text): string => str_replace(
                        "'min_length' => env(",
                        "    /**\n     * Nothing reads this one yet.\n     */\n    'min_length' => env(",
                        $text,
                    ));

                    return $key;
                },
                'read' => static function (string $root): array {
                    $published = ConfigKeys::published($root.'/config/response-compression.php');

                    return array_map(
                        static fn (array $claim): string => sprintf('%s: %s', $claim['where'], $claim['claim']),
                        ConfigClaims::all($root, $published),
                    );
                },
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
                'guard' => 'the log-message reading',
                'test' => 'tests/Unit/Middleware/LogMessagesTest.php',
                'plant' => static function (string $root): string {
                    self::replace($root, LogMessages::SOURCE, static fn (string $text): string => str_replace(
                        "'".self::COLLIDING."'",
                        "'".self::KEPT."'",
                        $text,
                    ));

                    return self::KEPT.'{}';
                },
                'read' => static fn (string $root): array => LogMessages::duplicates(
                    LogMessages::in((string) file_get_contents($root.'/'.LogMessages::SOURCE)),
                ),
            ],
        ];
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
