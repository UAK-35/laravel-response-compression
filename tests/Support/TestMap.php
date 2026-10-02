<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The tests a commit runs, chosen from the files it carries: which test covers each source file, what a document change can stale, and which test a staged test file is measured by.
 *
 * WHY THIS EXISTS
 * ---------------
 * `--staged` used to narrow the gate to three things a commit can break on its own: syntax, style,
 * and the two tests a document can stale — the docs links and the config claims. So a commit that
 * changed a program was judged on whether it still parsed and still matched the style, and nothing
 * ran the suite that would have caught it behaving differently. The release tooling was the worst
 * of it: `bin/release.php` is the longest file here, and the tests that cover it are the ones that
 * plant a git repository and run the script against it — so a commit that broke a rail passed the
 * hook and was found by hand, later.
 *
 * The mapping is therefore a claim about two directories, and it is kept in one place rather than
 * in a list beside the gate. A list goes stale the moment a source file is renamed or added, so a
 * file the map does not name is a finding rather than something nobody notices: every `.php` file
 * under `src/` and `bin/` has to have a row here, held by `tests/Unit/Support/TestMapTest.php`.
 *
 * WHAT A COMMIT'S FILES ARE MEASURED BY
 * -------------------------------------
 *   - a source file with a row: the tests that row names. A file with no row of its own and no rule
 *     below is a file nothing covers, which is a fact this reading declines to guess at.
 *   - `config/response-compression.php`: the config-claim test, because a claim about a key is
 *     written in the markdown that repeats it or in the docblock beside it in the config.
 *   - a markdown record: the docs-link test, and the config-claim test — the second because the
 *     README carries a copy of the config the claims are checked against.
 *   - a file under `tests/`: itself when it is a test, so a changed test is run rather than trusted,
 *     and the whole suite otherwise, because a shared fixture is a thing every test is built on and
 *     there is no narrower true answer to which of them it can have broken.
 *
 * A path that matches none of those — a composer script, a workflow, a lock file — is not mapped
 * here. It is a finding for the gate's own checks or for nothing, and inventing a test to run for
 * it would be this reading claiming a coverage it does not have.
 *
 * @guards-index reading
 */
final class TestMap
{
    /** The directory a change to the test tree falls back to: the suite itself. */
    public const string SUITE = 'tests/Unit';

    /** What a markdown record is measured by: the links in it and the claims it repeats. */
    public const string LINKS = 'tests/Unit/Docs/DocsLinksTest.php';

    /** What the config file a claim is about is measured by. */
    public const string CLAIMS = 'tests/Unit/Config/ConfigClaimsTest.php';

    /** The config file the claim above is a claim about. */
    public const string CONFIG = 'config/response-compression.php';

    /**
     * Source file => the test paths that cover it.
     *
     * A row per file rather than per directory on purpose: the directory is the thing that grows,
     * and a rule that answered for everything under `src/Encoders/` would go on answering for a
     * fourth encoder that its own three tests do not drive. A path here is a file or a directory —
     * a directory is handed to Pest as it is, and `bin/release.php` is the case for that, because
     * its tests are the whole of `tests/Unit/Release/` rather than any one file in it.
     *
     * @var array<string, list<string>>
     */
    private const array COVERS = [
        'bin/bootstrap.php' => ['tests/Unit/Bootstrap/BootstrapTest.php'],
        'bin/checks.php' => ['tests/Unit/Gate/ChecksTest.php'],
        'bin/coverage.php' => ['tests/Unit/Gate/CoverageTest.php'],
        'bin/index.php' => ['tests/Unit/Docs/GuardIndexTest.php'],
        'bin/push.php' => ['tests/Unit/Release/PushTest.php'],
        'bin/release.php' => ['tests/Unit/Release'],
        'bin/tool-paths.php' => [
            'tests/Unit/Support/ToolPathsTest.php',
            'tests/Unit/Gate/ToolTest.php',
            'tests/Unit/Gate/ChecksTest.php',
        ],
        'bin/tool.php' => ['tests/Unit/Gate/ToolTest.php'],
        'src/Contracts/Encoder.php' => ['tests/Unit/Encoders'],
        'src/Encoders/BrotliEncoder.php' => ['tests/Unit/Encoders/BrotliEncoderTest.php'],
        'src/Encoders/GzipEncoder.php' => ['tests/Unit/Encoders/GzipEncoderTest.php'],
        'src/Encoders/ZstdEncoder.php' => ['tests/Unit/Encoders/ZstdEncoderTest.php'],
        'src/Exceptions/InvalidConfigurationException.php' => ['tests/Unit/Exceptions/InvalidConfigurationExceptionTest.php'],
        'src/Middleware/CompressResponse.php' => [
            'tests/Unit/Middleware/CompressResponseTest.php',
            'tests/Unit/Middleware/LogMessagesTest.php',
        ],
        'src/ResponseCompressionServiceProvider.php' => ['tests/Unit'],
        'src/Support/AcceptEncoding.php' => ['tests/Unit/Support/AcceptEncodingTest.php'],
        'src/Support/Config.php' => [
            'tests/Unit/Support/ConfigTest.php',
            'tests/Unit/Config/ConfigKeysTest.php',
            'tests/Unit/Config/ConfigClaimsTest.php',
            'tests/Unit/Support/RepoEscapesTest.php',
        ],
        'src/Support/Diagnostic.php' => [
            'tests/Unit/Middleware/LogMessagesTest.php',
            'tests/Unit/Middleware/CompressResponseTest.php',
        ],
        'src/Support/Enc.php' => [
            'tests/Unit/Support/EncTest.php',
            'tests/Unit/Encoders',
        ],
    ];

    /**
     * The map itself, for the guard that holds it to the two directories it is a claim about.
     *
     * @return array<string, list<string>>
     */
    public static function covers(): array
    {
        return self::COVERS;
    }

    /**
     * Every `.php` file under `src/` and `bin/`, as a path from the package root.
     *
     * The directory is read rather than listed here, because a list of the source files is the
     * second copy this reading exists to avoid: a file that arrived and has no row is the failure
     * the guard has to be able to see, and it cannot see it in a list that was never updated.
     *
     * @return list<string>
     */
    public static function files(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $files = [];

        foreach (['src', 'bin'] as $directory) {
            if (! is_dir($root.'/'.$directory)) {
                continue;
            }

            $entries = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($entries as $entry) {
                if (! $entry instanceof SplFileInfo || ! $entry->isFile() || $entry->getExtension() !== 'php') {
                    continue;
                }

                $files[] = ltrim(substr(str_replace('\\', '/', $entry->getPathname()), strlen($root)), '/');
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    /**
     * The test paths a commit carrying these files should run, sorted and without a repeat.
     *
     * @param  list<string>  $staged  the files a commit carries, as paths from the package root
     * @return list<string>
     */
    public static function covering(array $staged): array
    {
        $tests = [];

        foreach ($staged as $path) {
            $path = ltrim(str_replace('\\', '/', $path), '/');

            if (isset(self::COVERS[$path])) {
                foreach (self::COVERS[$path] as $test) {
                    $tests[$test] = true;
                }

                continue;
            }

            if ($path === self::CONFIG) {
                $tests[self::CLAIMS] = true;

                continue;
            }

            if (str_ends_with($path, '.md')) {
                $tests[self::LINKS] = true;
                $tests[self::CLAIMS] = true;

                continue;
            }

            // Anything else under the test tree is either a test, which is run as itself, or a
            // fixture the whole suite is built on, which is the only narrower true answer there is.
            if (str_starts_with($path, 'tests/')) {
                $itself = str_starts_with($path, self::SUITE.'/') && str_ends_with($path, 'Test.php');
                $tests[$itself ? $path : self::SUITE] = true;
            }
        }

        $paths = array_keys($tests);
        sort($paths);

        // A directory already contains the files under it, so a path a broader one already
        // reaches is dropped: a commit that changes `bin/release.php` and one of its tests would
        // otherwise hand that test to Pest twice, which is the whole slow file run a second time.
        $reduced = [];

        foreach ($paths as $path) {
            foreach ($paths as $other) {
                if ($other !== $path && str_starts_with($path, rtrim($other, '/').'/')) {
                    continue 2;
                }
            }

            $reduced[] = $path;
        }

        return $reduced;
    }
}
