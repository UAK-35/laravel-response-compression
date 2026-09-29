<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * A throwaway git repository with a real tag and a real remote, holding this package's release
 * script.
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
 *
 * ONE REPOSITORY IS PLANTED, AND EVERY TEST COPIES IT
 * --------------------------------------------------
 * `make()` used to build the tree it hands back: `git init`, seven `git config`, `git add`,
 * `git commit` and an annotated tag. Every one of those is a process spawn, and it came to about
 * 550ms on Windows — of which the seven `config` calls alone were 265ms. The suite asks for a
 * fixture 52 times, so 28 of the release suite's seconds went on planting the same repository
 * over and over.
 *
 * So a repository is planted **once per tag and changelog body** and copied for each test that
 * asks for one. Those two are the only things `make()` varies, and both have to be *committed*
 * rather than merely written (the release script refuses a tree it cannot vouch for), which is why
 * the templates are keyed by them instead of one template being patched afterwards. A copy is a
 * recursive file copy — `cp -r` in PHP, because there is no portable command for it — and it
 * carries `.git` with it: git stores no absolute path in a freshly initialised repository, and a
 * copied index reads as modified-by-content rather than by stat, so the copy is clean and
 * `git status` says so. It costs about 60ms, and the planting it replaces is paid once.
 *
 * The copy is also a fraction of what a checkout would be, because the repository is initialised
 * from an empty template: 26 files in a stock `.git` against 10 in this one, and the fourteen hook
 * samples that make up the difference are inert — git runs a hook under its real name, and nothing
 * here renames one.
 *
 * Isolation is unchanged, and that is the point of copying rather than resetting: the template is
 * written once and never touched again, every test gets a tree of its own to commit, tag and
 * overwrite in, and nothing one test does can reach another. The fixture would be worse than slow
 * if the shortcut were a shared working tree.
 *
 * The fixture's git settings are not per-repository either. They are written once into a file
 * beside the template and handed to every child as `GIT_CONFIG_GLOBAL`, with `GIT_CONFIG_NOSYSTEM`
 * and an identity in the environment, so no process this fixture starts can read the settings of
 * the machine it happens to run on — a signing key, an `init.defaultBranch`, or a `core.hooksPath`
 * pointing at this package's own hooks. That file is shared rather than copied because a copy of a
 * constant is a constant.
 *
 * @guards-index support
 */
final class ReleaseRepo
{
    private const string NAME = 'Release Fixture';

    private const string EMAIL = 'fixture@example.test';

    /** The notes a fixture starts with, so a release has something to publish. */
    private const string NOTES = "### Added\n\n- A thing nobody had before.\n";

    /** How a template is keyed: the tag it stops at, then the notes it starts with. */
    private const string PLAIN = '#plain';

    /** One planted repository per tag and body, copied for every test that asks. */
    private static array $templates = [];

    /** Everything the sweep still has to remove. */
    private static array $trash = [];

    /** Whether the one shutdown function that does the removing is registered yet. */
    private static bool $sweeper = false;

    /**
     * @param  string  $path  the fixture's own tree
     * @param  string  $config  the git config every child is handed; a sibling of the root rather
     *                          than a file in it, so it is never copied and never committed
     */
    private function __construct(
        public string $path,
        private readonly string $config,
    ) {}

    /**
     * A repository with one commit, one annotated tag and an Unreleased section, and no remote —
     * a test that needs the remote asks for it, because `withRemote()` pushes from HEAD.
     */
    public static function make(string $firstTag = 'v0.0.1', string $notes = self::NOTES): self
    {
        return self::copy(self::template($firstTag, $notes));
    }

    /**
     * The same files with no repository around them, for the rail that has to notice that.
     */
    public static function plain(): self
    {
        return self::copy(self::template(self::PLAIN, ''));
    }

    public static function package(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Remove a tree, chmod-ing as it goes: git writes its object files read-only, and Windows will
     * not unlink a read-only file.
     *
     * The directory itself is retried rather than removed once. A handle can outlive the process
     * that opened it for a moment on Windows, and a single `rmdir` then leaves an empty shell
     * behind in the temp directory on every machine that happens to be busy — a leak nobody notices
     * and nobody cleans up.
     *
     * Public because a second fixture removes a tree of its own: `MutationHarness` plants a copy of
     * this checkout and takes it away again, and the two things that make this hard are the same
     * two whatever the tree holds. A second copy of the loop is a second place for it to be wrong
     * about a read-only object file, which is a failure nobody would connect to the harness.
     */
    public static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @chmod($path, 0o777);
            @unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            self::remove($path.'/'.$entry);
        }

        for ($attempt = 0; $attempt < 4; $attempt++) {
            if (@rmdir($path)) {
                return;
            }

            usleep(25_000);
        }
    }

    /**
     * A fixture beside a bare repository, with the branch already pushed — which is what the CI
     * rail asks about. Called last in a test that also commits, because it pushes from HEAD.
     */
    public function withRemote(): self
    {
        $remote = $this->path.'-remote.git';
        self::sweep([$remote]);

        $this->run(['init', '--bare', '--quiet', $remote]);
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
     * Take a file out of the fixture, for a change that is a move rather than an edit.
     *
     * Named `delete` rather than `remove` because the sweep below already owns that name.
     */
    public function delete(string $relative): void
    {
        unlink($this->path($relative));
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
     * Move a class to another file, keeping its name — the one change a path list can read as a
     * move and a name-only diff cannot.
     */
    public function moveClass(string $from, string $to): self
    {
        $this->write($to, $this->read($from));
        $this->delete($from);
        $this->commit('refactor: move the class to another file');

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
     * Take a public method away, so both signals — the surface, which reads symbols, and the
     * inventory, which keeps a row per method — have a removal to agree on.
     *
     * A public constant goes the other way: `withoutPublicSymbol()` above removes one the inventory
     * has no column for, which is the one-reading case the notes rail deliberately leaves alone.
     */
    public function withoutPublicMethod(string $method = 'handle'): self
    {
        $this->write('src/Thing.php', (string) preg_replace(
            '/\n    public function '.preg_quote($method, '/').'\(.*?\n    }\n/s',
            '',
            $this->read('src/Thing.php'),
        ));

        $this->commit('refactor: drop a public method');

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
        $this->write('bin/checks.php', $this->checksStub(1));
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
        $command = [PHP_BINARY, ...$this->coverage(), $this->path($script), ...$arguments];

        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->path,
            $this->environment(),
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
     * The planted tree for one tag and body, built on first use and copied from then on. Returned
     * rather than exposed: a test gets a copy, never this.
     */
    private static function template(string $firstTag, string $notes): self
    {
        $key = $firstTag."\0".$notes;

        if (isset(self::$templates[$key])) {
            return self::$templates[$key];
        }

        $root = sys_get_temp_dir().'/rc-release-template-'.bin2hex(random_bytes(6));
        $config = $root.'.gitconfig';

        // An empty `git init --template`: a stock repository ships fourteen inert hook samples, a
        // `description` and an `info/exclude`, and every one of them would be copied for the whole
        // suite without git ever reading it. The template is passed as a sibling of the root for
        // the same reason the config is — it must not be part of the tree that gets copied, or it
        // would be committed into the fixture as well.
        $initTemplate = $root.'.git-template';

        if (! mkdir($initTemplate, 0o777, true) && ! is_dir($initTemplate)) {
            throw new RuntimeException('Could not create '.$initTemplate);
        }

        $repo = new self($root, $config);
        self::sweep([$root, $config, $initTemplate]);

        if (file_put_contents($config, self::gitconfig()) === false) {
            throw new RuntimeException('Could not write '.$config);
        }

        $repo->scaffold();
        $repo->write('CHANGELOG.md', self::changelog($notes));

        if ($firstTag !== self::PLAIN) {
            $repo->run(['init', '-b', 'main', '--template='.$initTemplate]);
            $repo->run(['add', '-A']);
            $repo->run(['commit', '-m', 'feat: the package']);
            $repo->run(['tag', '-a', $firstTag, '-m', $firstTag]);
        }

        return self::$templates[$key] = $repo;
    }

    /** A copy of a planted tree, swept when the process ends. */
    private static function copy(self $template): self
    {
        $path = sys_get_temp_dir().'/rc-release-'.bin2hex(random_bytes(6));

        self::copyTree($template->path, $path);
        self::sweep([$path]);

        return new self($path, $template->config);
    }

    /**
     * A tree copied file by file, subdirectories and dot-files included.
     *
     * Written out rather than shelled out to: `cp -r` is not a command Windows has, and the one it
     * does have is not the one Linux has either. Modes are carried over because git tracks the
     * executable bit, so a copy that dropped it would show up as a modification to nobody's edit.
     */
    private static function copyTree(string $from, string $to): void
    {
        if (! mkdir($to, 0o777, true) && ! is_dir($to)) {
            throw new RuntimeException('Could not create '.$to);
        }

        foreach (array_diff(scandir($from) ?: [], ['.', '..']) as $entry) {
            $source = $from.'/'.$entry;
            $target = $to.'/'.$entry;

            if (is_dir($source)) {
                self::copyTree($source, $target);

                continue;
            }

            if (! copy($source, $target)) {
                throw new RuntimeException('Could not copy '.$source);
            }

            @chmod($target, fileperms($source) & 0o777);
        }
    }

    /**
     * What the seven `git config` calls used to write into each fixture, written once instead.
     *
     * Neither a global gpg key nor a global hooks path should decide whether a fixture commit or
     * tag can be created, and the line endings are written by this class rather than by git.
     */
    private static function gitconfig(): string
    {
        return implode("\n", [
            '[core]',
            "\tautocrlf = false",
            "\tsafecrlf = false",
            "\thooksPath = .git/no-hooks",
            '[commit]',
            "\tgpgsign = false",
            '[tag]',
            "\tgpgsign = false",
            '[init]',
            "\tdefaultBranch = main",
            '[user]',
            "\tname = ".self::NAME,
            "\temail = ".self::EMAIL,
            '',
        ]);
    }

    /**
     * Register paths for removal at the end of the process, and arrange for it to happen once.
     *
     * Removal is registered here rather than left to the tests: a fixture that only the test that
     * made it knows how to clean up is one a failing assertion leaks. The list is held rather than
     * each path registering its own shutdown function, so a run with 52 fixtures has one callback
     * to invoke and not 52.
     *
     * @param  list<string>  $paths
     */
    private static function sweep(array $paths): void
    {
        self::$trash = [...self::$trash, ...$paths];

        if (self::$sweeper) {
            return;
        }

        self::$sweeper = true;

        register_shutdown_function(static function (): void {
            foreach (array_reverse(self::$trash) as $path) {
                self::remove($path);
            }
        });
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
     * The flags a spawned script is run with when the suite is being measured, and nothing at all
     * when it is not.
     *
     * A script in a fixture is a child process, so the floor cannot see it: the coverage a child
     * collects is its own. `bin/coverage.php` sets `RC_SCRIPT_COVERAGE_DIR` and runs Pest with it
     * exported, which is what turns this on — a plain `pest`, the gate's own test step, and every
     * developer's run are left exactly as they were.
     *
     * The scope is the fixture rather than the package, because the file the child executes is the
     * copy in the fixture: `pcov.directory` is already pinned to this package's `src/` on some
     * machines, and a collection that nothing falls inside is silent. What the child covered is
     * then mapped back to the file it was copied from by `ScriptCoverage`, which compares contents
     * before it believes a copy is the package's file.
     *
     * @return list<string>
     */
    private function coverage(): array
    {
        $directory = getenv('RC_SCRIPT_COVERAGE_DIR');

        if (! is_string($directory) || $directory === '') {
            return [];
        }

        return [
            '-d', 'pcov.enabled=1',
            '-d', 'pcov.directory='.str_replace('\\', '/', $this->path),
            '-d', 'auto_prepend_file='.self::package().'/tests/Support/collect-coverage.php',
        ];
    }

    /**
     * A package skeleton: the keys a release script does not touch, and a `src/` and `config/` for
     * the surface signal to read.
     */
    private function composer(): string
    {
        return <<<'JSON'
{
    "name": "uak35/laravel-response-compression",
    "description": "Release fixture",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4"
    },
    "extra": {
        "branch-alias": {
            "dev-main": "0.0.x-dev",
            "dev-development": "0.0.x-dev"
        }
    }
}

JSON;
    }

    /**
     * A class with something public to lose: a constant, a property, a method with a required
     * argument, and a private method whose internals must not be mistaken for members.
     */
    private function thing(): string
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

    private function config(): string
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
     * A stub gate: the rail reads nothing but the exit code.
     */
    private function checksStub(int $exit): string
    {
        return "#!/usr/bin/env php\n<?php\n\nexit(".$exit.");\n";
    }

    /**
     * The package skeleton in the planted tree: the keys a release script does not touch, a `src/`
     * and a `config/` for the surface signal to read, and the real release script.
     */
    private function scaffold(): void
    {
        foreach (['', '/src', '/config', '/bin'] as $directory) {
            if (! mkdir($this->path.$directory, 0o777, true) && ! is_dir($this->path.$directory)) {
                throw new RuntimeException('Could not create '.$this->path.$directory);
            }
        }

        $this->write('composer.json', $this->composer());
        $this->write('src/Thing.php', $this->thing());
        $this->write('config/response-compression.php', $this->config());
        $this->write('CHANGELOG.md', self::changelog(self::NOTES));
        $this->write('bin/release.php', (string) file_get_contents(self::package().'/bin/release.php'));
        $this->write('bin/checks.php', $this->checksStub(0));
    }

    /**
     * The environment every child is given: the machine's own, plus the four settings that make git
     * answerable to the fixture instead of to whoever installed it. `PATH` and `SystemRoot` have to
     * survive, or git would not resolve on Windows.
     *
     * @return array<string, string>
     */
    private function environment(): array
    {
        $inherited = getenv();

        if (! is_array($inherited)) {
            $inherited = [];
        }

        return array_merge($inherited, [
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_CONFIG_GLOBAL' => $this->config,
            'GIT_AUTHOR_NAME' => self::NAME,
            'GIT_AUTHOR_EMAIL' => self::EMAIL,
            'GIT_COMMITTER_NAME' => self::NAME,
            'GIT_COMMITTER_EMAIL' => self::EMAIL,
            'GIT_TERMINAL_PROMPT' => '0',
        ]);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function run(array $arguments): string
    {
        // All three streams are declared, stdin included, rather than left to be inherited: with no
        // descriptor 0 the child is handed whatever handle the runner gave this process, and a
        // parallel runner spawns its workers with pipes of its own. No child here reads stdin, so
        // the write end of its pipe is closed as soon as the process exists.
        $process = proc_open(
            ['git', ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes,
            $this->path,
            $this->environment(),
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not run git '.implode(' ', $arguments));
        }

        fclose($pipes[0]);
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
