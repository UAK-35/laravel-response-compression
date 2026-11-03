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
    /** The one file a mutation harness replaces: the program whose rails are the subject. */
    public const string SCRIPT = 'bin/release.php';

    /**
     * The environment variable that points every fixture this process plants at another release
     * script.
     *
     * The harness's other direction — a mutated program run against the test that holds the rail —
     * needs the *suite's own* test to build its fixture, and a test cannot be handed a script. So
     * the rewrite arrives through the environment instead: a child process is started with this
     * set, and every fixture it plants is built from that file. Without it the only way to put a
     * mutated releaser in front of a test would be to write the test twice.
     */
    public const string SCRIPT_ENV = 'RC_RELEASE_FIXTURE_SCRIPT';

    private const string NAME = 'Release Fixture';

    private const string EMAIL = 'fixture@example.test';

    /** The notes a fixture starts with, so a release has something to publish. */
    private const string NOTES = "### Added\n\n- A thing nobody had before.\n";

    /** How a template is keyed: the tag it stops at, then the notes it starts with. */
    private const string PLAIN = '#plain';

    /**
     * How long one spawned script may run before it is stopped, in seconds.
     *
     * Generous on purpose: the bound is not a budget a green run spends, it is the line past which
     * a run is not slow but stuck, and the scripts here are `bin/release.php`, `bin/commit.php` and
     * `bin/bootstrap.php` over fixtures that answer in a second or two. A test that holds the bound
     * itself asks for a shorter one with `within()` rather than by making this smaller for
     * everybody.
     */
    private const float DEADLINE = 300.0;

    /** How long a stopped child is given to be gone before the caller carries on, in seconds. */
    private const float GRACE = 5.0;

    /** How long the wait is between two looks at a running child, in microseconds. */
    private const int POLL = 10_000;

    /** The exit status a stopped run reports: the one `timeout(1)` uses for the same thing. */
    private const int TIMED_OUT = 124;

    /**
     * The reader program: copy stdin to the capture file, and end when the pipe closes.
     *
     * One line of PHP rather than a class, because it must not be this class: it has to run in a
     * process of its own, with the pipe as its stdin, for the read to be a wait this process is
     * not in.
     */
    private const string READER = 'stream_copy_to_stream(STDIN, fopen($argv[1], "w"));';

    /** One planted repository per tag and body, copied for every test that asks. */
    private static array $templates = [];

    /** Everything the sweep still has to remove. */
    private static array $trash = [];

    /** The bound `script()` holds one child to, in seconds; `within()` lowers it for one test. */
    private float $deadline = self::DEADLINE;

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
    public static function make(string $firstTag = 'v0.0.1', string $notes = self::NOTES, ?string $script = null): self
    {
        return self::copy(self::template($firstTag, $notes, $script));
    }

    /**
     * A fixture whose `bin/release.php` is not this package's.
     *
     * One rail of the program has been rewritten so that it no longer refuses, and everything else
     * about the fixture — the tag, the notes, the remote, the stub gate — is the same tree the
     * rail's own test is written against. That is the whole point: the mutated program and the real
     * one are handed the same conditions, so a difference in what they answer is attributable to
     * the rail rather than to the fixture.
     *
     * A source rather than a path, because a mutation is a rewrite: the caller has already turned
     * this package's script into a broken one, and a file on disk would be a second thing to clean
     * up.
     */
    public static function mutated(string $script): self
    {
        return self::make(script: $script);
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

    /**
     * Hold this fixture's scripts to a shorter bound, for the test that holds the bound itself.
     *
     * The default is `DEADLINE`, which no passing run reaches; a test about the bound cannot wait
     * five minutes to see it enforced, so it asks for the one it can outlast.
     */
    public function within(float $seconds): self
    {
        $this->deadline = $seconds;

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

        // stdout is drained by a child of this process, and stderr goes to a file. A pipe could not
        // be read without blocking on Windows — `stream_set_blocking()` answers `false` for a
        // `proc_open` pipe, `stream_get_meta_data()` still says `blocked`, and a first `fread()` on
        // an empty one waited 2.1 seconds for the byte it was waiting for — which is why a deadline
        // could not be enforced from here before: the read *was* the wait.
        //
        // So the wait moves one process out. The reader copies the pipe to the capture file and
        // blocks, which is its whole job; this process only polls `proc_get_status()`, and a poll is
        // the one wait that can look at a clock. Two measurements made a reader necessary rather
        // than an obvious file descriptor. stdout cannot be a file: `bin/bootstrap.php` starts
        // composer and the verifier with `1 => STDOUT`, and the write that lands last in that run
        // was measured starting at the capture file's first byte — `bin/bootstrap.php: done.` in
        // front of the lines its children had written — so a second writer does not append whatever
        // mode the file was opened in. And the pipe's order has to survive, because the tests read
        // the program's own lines beside its children's. A reader that writes the file as the bytes
        // arrive keeps both: the pipe is exactly what it was, and the file has one writer.
        //
        // The capture files are siblings of the tree rather than files in it: the fixture is
        // committed and diffed by git, so a capture written inside it would be a dirty tree the
        // releaser refuses. They are removed as soon as the run has been read.
        $stdout = $this->path.'.stdout';
        $stderr = $this->path.'.stderr';

        self::remove($stdout);
        self::remove($stderr);

        [$process, $pipes] = $this->start(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $stderr, 'w']],
        );

        fclose($pipes[0]);

        // The reader is handed the pipe itself, not a copy of a file: a duplicate is what makes EOF
        // arrive when the last writer closes it — the child, or a grandchild that inherited `STDOUT`
        // — and it is why this process closes its own handle before it waits.
        [$reader] = $this->start(
            [PHP_BINARY, '-r', self::READER, $stdout],
            [0 => $pipes[1], 1 => $this->discard(), 2 => $this->discard()],
        );

        fclose($pipes[1]);

        $timedOut = false;

        try {
            $exitCode = $this->await($process, $reader);
            $timedOut = $exitCode === null;

            $output = $this->capture($stdout);
            $error = $this->capture($stderr);
        } finally {
            proc_close($reader);
            proc_close($process);
            self::remove($stdout);
            self::remove($stderr);
        }

        return new ReleaseRun(
            $timedOut ? self::TIMED_OUT : (int) $exitCode,
            $output,
            $error,
            implode(' ', $command),
            $timedOut,
            $this->deadline,
        );
    }

    public function git(string ...$arguments): string
    {
        return $this->run($arguments);
    }

    /**
     * The planted tree for one tag and body, built on first use and copied from then on. Returned
     * rather than exposed: a test gets a copy, never this.
     */
    private static function template(string $firstTag, string $notes, ?string $script = null): self
    {
        $script ??= self::fixtureScript();

        // The script is part of the key rather than a detail of the build, because it is the one
        // thing a template cannot be patched for afterwards: the fixture is committed and tagged
        // the moment it is planted, so a rewritten program has to be in the tree before that.
        $key = $firstTag."\0".$notes."\0".($script === null ? '' : md5($script));

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

        $repo->scaffold($script);
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
     * The release script a fixture should be built from when the environment names one.
     *
     * Read as a file rather than as text: this is an override a child process is started with, and
     * a variable that names something that is not there is a mistake rather than an empty answer —
     * falling back to this package's script would run the *unmutated* program and report the rail
     * as still held.
     */
    private static function fixtureScript(): ?string
    {
        $named = getenv(self::SCRIPT_ENV);

        if (! is_string($named) || $named === '') {
            return null;
        }

        if (! is_file($named)) {
            throw new RuntimeException(self::SCRIPT_ENV.' names '.$named.', which is not a file.');
        }

        return (string) file_get_contents($named);
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
     * Wait for one child, and answer its exit status — or `null` when the deadline stopped it.
     *
     * The clock is the point of this method. `proc_get_status()` is the one call that asks about a
     * child without waiting on it, so a loop that asks it is a loop that can decide a run has taken
     * too long; the blocking read lives in the reader, which is why this process never enters one.
     *
     * @param  resource  $process
     * @param  resource  $reader
     */
    private function await($process, $reader): ?int
    {
        $deadline = microtime(true) + $this->deadline;

        while (true) {
            $status = proc_get_status($process);

            if (! $status['running']) {
                $this->settle($reader);

                return (int) $status['exitcode'];
            }

            if (microtime(true) >= $deadline) {
                $this->stop($process);
                $this->settle($reader);

                return null;
            }

            usleep(self::POLL);
        }
    }

    /**
     * Give the reader the bounded time it needs to finish the capture, then end it.
     *
     * A child that has exited can leave a grandchild holding the pipe, so the reader is waited on
     * rather than assumed finished; past the grace it is ended and whatever it has written is read
     * as the output, which is a partial capture rather than a hang.
     *
     * @param  resource  $reader
     */
    private function settle($reader): void
    {
        $until = microtime(true) + self::GRACE;

        while (proc_get_status($reader)['running'] && microtime(true) < $until) {
            usleep(self::POLL);
        }

        proc_terminate($reader);
    }

    /**
     * Stop a child that has run past its deadline, and everything it started.
     *
     * The tree is the half `proc_terminate()` cannot answer: a release script's children are `git`,
     * and the bootstrap fixture's are composer and the verifier, and a bound that left those
     * running would leave them writing into a tree the next test copies — the cleanup the report's
     * finding asked for, rather than only the clock. Windows has `taskkill /T` for it, and there it
     * goes first: `/T` finds a child's descendants through the parent that is still there, so
     * stopping the child first would leave nothing to enumerate the tree by. Elsewhere the process
     * table is walked downwards, because `proc_open()` starts no process group for `kill -pid` to
     * name.
     *
     * @param  resource  $process
     */
    private function stop($process): void
    {
        $pid = (int) proc_get_status($process)['pid'];

        if ($pid > 0 && PHP_OS_FAMILY === 'Windows') {
            exec(sprintf('taskkill /F /T /PID %d 2>NUL', $pid));
        } elseif ($pid > 0) {
            foreach (array_reverse($this->descendants($pid)) as $descendant) {
                $this->signal($descendant);
            }

            $this->signal($pid);
        }

        proc_terminate($process);

        // A kill is a request; this waits, bounded, for it to have been carried out. Without it the
        // wait is `proc_close()` in the caller, and that one has no clock — the same shape as the
        // read the deadlock was made of.
        $until = microtime(true) + self::GRACE;

        while (proc_get_status($process)['running'] && microtime(true) < $until) {
            usleep(self::POLL);
        }
    }

    /**
     * A descriptor that throws away what is written to it, for a process whose output is not the
     * subject.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function discard(): array
    {
        return ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'];
    }

    /**
     * Kill one process, through `posix` where the extension answers and through `kill` where it is
     * not installed — Windows never reaches this, because `taskkill` is the whole tree there.
     */
    private function signal(int $pid): void
    {
        if (function_exists('posix_kill')) {
            @posix_kill($pid, 9);

            return;
        }

        exec(sprintf('kill -9 %d 2>/dev/null', $pid));
    }

    /**
     * Every process below one, breadth first, read from the machine's own process table.
     *
     * `ps -e -o pid=,ppid=` is the form both Linux and macOS answer, and a machine that cannot be
     * asked answers with an empty list rather than failing: the direct child is stopped by
     * `proc_terminate()` either way, so what an unreadable table costs is the tree, not the stop.
     *
     * @return list<int>
     */
    private function descendants(int $pid): array
    {
        $listing = @shell_exec('ps -e -o pid=,ppid= 2>/dev/null');

        if (! is_string($listing)) {
            return [];
        }

        /** @var array<int, int> $parents pid => ppid */
        $parents = [];

        foreach (preg_split('/\R/', $listing) ?: [] as $line) {
            if (preg_match('/^\s*(\d+)\s+(\d+)\s*$/', $line, $match) !== 1) {
                continue;
            }

            $parents[(int) $match[1]] = (int) $match[2];
        }

        $found = [];
        $queue = [$pid];

        while ($queue !== []) {
            $parent = array_shift($queue);

            foreach ($parents as $child => $ppid) {
                if ($ppid === $parent) {
                    $found[] = $child;
                    $queue[] = $child;
                }
            }
        }

        return $found;
    }

    /**
     * What the child wrote to one of its two capture files, read once it has exited.
     *
     * A file that is not there reads as an empty stream rather than as a failure: both are created
     * with the child, so its absence is a start that never happened, which `start()` has already
     * thrown on.
     */
    private function capture(string $path): string
    {
        return is_file($path) ? (string) file_get_contents($path) : '';
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
    private function scaffold(?string $script = null): void
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
        $this->write(self::SCRIPT, $script ?? (string) file_get_contents(self::package().'/'.self::SCRIPT));
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
     * Start one child in this fixture's tree and with its environment, waiting out a refused start.
     *
     * The waiting itself is `Processes`', because it is a fact about this machine rather than about
     * the release rails: the reading that could not start `git` and the one that could not start
     * `bash` fail the same way, and both are answered in one place.
     *
     * @param  list<string>  $command
     * @param  array<int, array<int, string>|resource>  $descriptors
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function start(array $command, array $descriptors): array
    {
        return Processes::start($command, $descriptors, $this->path, $this->environment());
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
        [$process, $pipes] = $this->start(
            ['git', ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        );

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
