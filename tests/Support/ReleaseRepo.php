<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * A throwaway git repository holding this package's release script.
 *
 * WHY A REPOSITORY AND NOT A MOCK
 * -------------------------------
 * `bin/release.php` is a program about git: it reads tags, counts a lane, diffs a tree against a
 * revision, commits and tags. Every one of those is a question only git can answer, so these
 * tests give it a git repository to answer them in — a real one, in a temporary directory, with a
 * real annotated tag and a real bare remote beside it.
 *
 * The alternative is a fake `git` and a fake working tree, which tests that the script calls the
 * fake the way the fake expects. What is worth testing here is what a release leaves behind: a
 * promoted CHANGELOG, a commit whose subject names the version, an annotated tag, and a refusal
 * for the cases where none of that should happen.
 *
 * `bin/checks.php` is stubbed. The gate the real script runs is this package's own, and running
 * it here would mean installing the package's dev dependencies into every fixture; the stub
 * answers the only thing the rail reads — the exit code — and nothing else.
 */
final readonly class ReleaseRepo
{
    private const string NAME = 'Release Fixture';

    private const string EMAIL = 'fixture@example.test';

    /** The notes a fixture starts with, so a release has something to publish. */
    private const string NOTES = "### Added\n\n- A thing nobody had before.\n";

    private function __construct(public string $path) {}

    /**
     * A repository with one commit, one annotated tag and an Unreleased section, and no remote —
     * a test that needs the remote asks for it, because `withRemote()` pushes from HEAD.
     */
    public static function make(string $firstTag = 'v0.0.1', string $notes = self::NOTES): self
    {
        $repo = self::scaffold();

        $repo->write('CHANGELOG.md', self::changelog($notes));

        $repo->run(['init', '-b', 'main']);
        $repo->run(['config', 'user.name', self::NAME]);
        $repo->run(['config', 'user.email', self::EMAIL]);
        // Neither a global gpg key nor a global hooks path should decide whether a fixture commit
        // or tag can be created, and the line endings are written by this class rather than by git.
        $repo->run(['config', 'commit.gpgsign', 'false']);
        $repo->run(['config', 'tag.gpgsign', 'false']);
        $repo->run(['config', 'core.hooksPath', '.git/no-hooks']);
        $repo->run(['config', 'core.autocrlf', 'false']);
        $repo->run(['add', '-A']);
        $repo->run(['commit', '-m', 'feat: the package']);
        $repo->run(['tag', '-a', $firstTag, '-m', $firstTag]);

        return $repo;
    }

    /**
     * The same files with no repository around them, for the rail that has to notice that.
     */
    public static function plain(): self
    {
        return self::scaffold();
    }

    public static function package(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * A fixture beside a bare repository, with the branch already pushed — which is what the CI
     * rail asks about. Called last in a test that also commits, because it pushes from HEAD.
     */
    public function withRemote(): self
    {
        $remote = $this->path.'-remote.git';
        self::sweepOnShutdown($remote);

        exec(sprintf('git init --bare %s 2>&1', escapeshellarg($remote)), $output, $exit);

        if ($exit !== 0) {
            throw new RuntimeException('Could not create a bare repository: '.implode("\n", $output));
        }

        $this->run(['remote', 'add', 'origin', $remote]);
        $this->run(['push', '-u', 'origin', 'main']);

        return $this;
    }

    public function path(string $relative): string
    {
        return $this->path.'/'.$relative;
    }

    public function read(string $relative): string
    {
        $contents = @file_get_contents($this->path($relative));

        if ($contents === false) {
            throw new RuntimeException('No such file in the fixture: '.$relative);
        }

        return $contents;
    }

    public function exists(string $relative): bool
    {
        return file_exists($this->path($relative));
    }

    public function write(string $relative, string $contents): void
    {
        $path = $this->path($relative);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o777, true);
        }

        file_put_contents($path, $contents);
    }

    /**
     * Replace the entries under `## Unreleased`, keeping the section itself.
     */
    public function notes(string $entries): void
    {
        $this->write('CHANGELOG.md', (string) preg_replace(
            '/^## Unreleased[ \t]*\n.*?(?=^##[ \t])/ms',
            "## Unreleased\n\n".trim($entries, "\n")."\n\n",
            $this->read('CHANGELOG.md'),
        ));
    }

    /**
     * The same, committed: a release refuses a dirty tree, and a fixture that leaves one makes the
     * test that follows about the wrong rail.
     */
    public function withNotes(string $entries): self
    {
        $this->notes($entries);
        $this->commit('docs: the notes for the next release');

        return $this;
    }

    /**
     * Keep a Changelog's compare links, which this package's own changelog does not use and a
     * consumer's might.
     */
    public function withLinkReferences(): self
    {
        $this->write(
            'CHANGELOG.md',
            rtrim($this->read('CHANGELOG.md'), "\n")."\n\n[Unreleased]: https://example.test/repo/compare/v0.0.1...HEAD\n",
        );

        return $this->withNotes("\n### Added\n\n- A thing nobody had before.\n");
    }

    /**
     * Re-tag HEAD, for a fixture whose base version has to be one where a breaking change is a
     * major rather than a minor — the 0.x rule in RELEASING.md turns on the line being released.
     */
    public function retagAs(string $tag): self
    {
        foreach ($this->tags() as $existing) {
            $this->run(['tag', '-d', $existing]);
        }

        $this->tag($tag);

        return $this;
    }

    /**
     * Take a public symbol away, so the surface signal has something to weigh.
     */
    public function withoutPublicSymbol(string $symbol = 'TAG'): self
    {
        $this->write('src/Thing.php', (string) preg_replace(
            '/^\\s*public const '.preg_quote($symbol, '/').' = .*\\n\\n/m',
            '',
            $this->read('src/Thing.php'),
        ));

        $this->commit('refactor: drop a public constant');

        return $this;
    }

    /**
     * Remove the Unreleased section entirely, which is a changelog with nothing to promote.
     */
    public function withoutUnreleased(): self
    {
        $this->write('CHANGELOG.md', (string) preg_replace(
            '/^## Unreleased[ \t]*\n.*?(?=^##[ \t])/ms',
            '',
            $this->read('CHANGELOG.md'),
        ));

        $this->commit('docs: no unreleased section');

        return $this;
    }

    /**
     * A changelog section that is there and says nothing.
     */
    public function withEmptyUnreleased(): self
    {
        $this->notes('');
        $this->commit('docs: empty the unreleased section');

        return $this;
    }

    public function commit(string $message): void
    {
        $this->run(['add', '-A']);
        $this->run(['commit', '-m', $message]);
    }

    public function tag(string $name): void
    {
        $this->run(['tag', '-a', $name, '-m', $name]);
    }

    /** @return list<string> */
    public function tags(): array
    {
        return array_values(array_filter(preg_split('/\R/', trim($this->git('tag', '--list'))) ?: []));
    }

    /**
     * Make the stubbed gate fail, so the rail that runs it can be tested, and commit it — a rail
     * tested on a dirty tree is a test of the dirty-tree rail instead.
     */
    public function failChecks(): self
    {
        $this->write('bin/checks.php', self::checksStub(1));
        $this->commit('chore: break the gate');

        return $this;
    }

    /**
     * Run `bin/release.php` in the fixture. stdin is closed, so every run is non-interactive and a
     * test that wants the apply path has to pass `--yes` — which is the same thing CI has to do.
     */
    public function release(string ...$arguments): ReleaseRun
    {
        return $this->script('bin/release.php', ...$arguments);
    }

    public function script(string $script, string ...$arguments): ReleaseRun
    {
        $command = [PHP_BINARY, $this->path($script), ...$arguments];

        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->path,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not run '.implode(' ', $command));
        }

        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return new ReleaseRun(proc_close($process), $output, $error, implode(' ', $command));
    }

    public function git(string ...$arguments): string
    {
        return $this->run($arguments);
    }

    /**
     * The package skeleton in a directory that is cleaned up at the end of the run.
     *
     * Removal is registered here rather than left to the tests: a fixture that only the test that
     * made it knows how to clean up is one a failing assertion leaks.
     */
    private static function scaffold(): self
    {
        $path = sys_get_temp_dir().'/rc-release-'.bin2hex(random_bytes(6));

        if (! mkdir($path, 0o777, true) && ! is_dir($path)) {
            throw new RuntimeException('Could not create a fixture at '.$path);
        }

        self::sweepOnShutdown($path);

        $repo = new self($path);

        $repo->write('composer.json', self::composer());
        $repo->write('src/Thing.php', self::thing());
        $repo->write('config/response-compression.php', self::config());
        $repo->write('CHANGELOG.md', self::changelog(self::NOTES));
        $repo->write('bin/release.php', (string) file_get_contents(self::package().'/bin/release.php'));
        $repo->write('bin/checks.php', self::checksStub(0));

        return $repo;
    }

    private static function sweepOnShutdown(string $path): void
    {
        register_shutdown_function(static function () use ($path): void {
            self::remove($path);
        });
    }

    private static function remove(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }

        @rmdir($path);
    }

    /**
     * A package skeleton: the keys a release script does not touch, and a `src/` and `config/` for
     * the surface signal to read.
     */
    private static function composer(): string
    {
        return <<<'JSON'
{
    "name": "uak35/laravel-response-compression",
    "description": "Release fixture",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4"
    }
}

JSON;
    }

    /**
     * A class with something public to lose: a constant, a property, a method with a required
     * argument, and a private method whose internals must not be mistaken for members.
     */
    private static function thing(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression;

final class Thing
{
    public const TAG = 'response-compression';

    public string $label = 'a thing';

    public function handle(string $body, bool $flag = false): string
    {
        return $body;
    }

    private function hidden(): void
    {
        $local = 1;
    }
}

PHP;
    }

    private static function config(): string
    {
        return <<<'PHP'
<?php

return [
    'enabled' => true,
    'br' => [
        'level' => 5,
    ],
];

PHP;
    }

    /**
     * This package's own changelog style: bracketed headings carrying the `v` the tags carry, and
     * no compare links.
     */
    private static function changelog(string $notes): string
    {
        return "# Changelog\n\n## Unreleased\n\n".trim($notes, "\n")."\n\n## [v0.0.1] - 2025-01-01\n\n### Added\n\n- The first release.\n";
    }

    /**
     * A stub gate: the rail reads nothing but the exit code.
     */
    private static function checksStub(int $exit): string
    {
        return "#!/usr/bin/env php\n<?php\n\nexit(".$exit.");\n";
    }

    /**
     * @param  list<string>  $arguments
     */
    private function run(array $arguments): string
    {
        $process = proc_open(
            ['git', ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes,
            $this->path,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not run git '.implode(' ', $arguments));
        }

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit = proc_close($process);

        if ($exit !== 0) {
            throw new RuntimeException(sprintf(
                "git %s failed in %s:\n%s",
                implode(' ', $arguments),
                $this->path,
                $output,
            ));
        }

        return $output;
    }
}
