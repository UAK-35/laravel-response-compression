<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * the environment as this checkout's own documents state it, held to the one file on a machine that answers it.
 *
 * WHY THIS EXISTS
 * ---------------
 * `AGENTS.md` §2 is where a reader — and an agent at the start of a session — takes this checkout's
 * environment from, and its rows are deliberately written as tokens rather than paths: `__PHP_EXE__`,
 * `__PWSH_EXE__`, `__PACKAGE_DIR__`. The values behind those tokens live in exactly one place,
 * `.agents/machine.local.json`, which is gitignored, and nothing compared the two. A row could name
 * a token nothing supplies, or the machine file could be missing the value for a token the table
 * still points at, and both would read as an environment that is described.
 *
 * The handoff folder is the second telling of the same thing, for the thread that continues this
 * work: `handoff/HANDOFF.md` §2 states the same facts in prose, so it is held to the machine file
 * here as well. It is read as a document, not as a member of this repository — the folder is
 * untracked and its own first paragraph says it can be deleted — so where it is not here, `handoff()`
 * answers `null` and nothing is asked about it.
 *
 * WHERE THE SHAPE COMES FROM
 * --------------------------
 * This does not keep a second copy of how `.agents/machine.local.json` is read. The file that owns
 * that shape is `.agents/render_local.py`: its `TOKENS` mapping is what `--value` answers from, and
 * what renders every settings file from the same values. So the token names and the key path each
 * one comes from are read out of that mapping, and the table, the machine file and the renderer are
 * compared through one definition of the shape rather than three. `Tests\Support\PhpBinary` refuses
 * to read the machine file at all for the same reason — a second reader is a second thing to keep in
 * step — and this reads it only because the comparison the table needs *is* the machine file's
 * contents.
 *
 * TWO ANSWERS, AND WHICH OF THEM IS A FINDING
 * -------------------------------------------
 * A token the documents name and the renderer has never heard of is a finding on any machine: it is
 * a name nothing resolves, and the table is wrong wherever it is read. A token the renderer knows
 * and the machine file has no value for is a finding only where there is a machine file to be short
 * of one — on a CI runner, and in a checkout nobody has run `--init` in, there is no file to hold a
 * value and the question is not asked. `machine()` answers `null` for both of those and the value
 * half is skipped, which is the same distinction `PhpBinary` draws between a disagreement and a run
 * that could not be asked.
 *
 * One value is derived rather than stored — `__PHP_DIR__` is the directory holding `__PHP_EXE__`
 * when the file does not carry it — so an empty key is not a finding on its own. Where a value is
 * missing, the renderer is asked what it would answer for that token, in one child process, and a
 * token it answers for is a token it derives. Where the renderer cannot be asked at all, nothing is
 * reported: \"could not look\" and \"is not there\" are different answers, and only the second is a
 * finding.
 *
 * @guards-index reading
 */
final class Handoff
{
    /** The document whose environment table is the claim this reading is about. */
    public const string RULES = 'AGENTS.md';

    /** The section of it that states this machine's environment, by its own heading. */
    public const string TABLE = '## 2. Environment & shell';

    /** The predecessor thread's telling of the same thing, when that folder is in the checkout. */
    public const string HANDOFF = 'handoff/HANDOFF.md';

    /** The section of it that does the same job as the table above. */
    public const string SECTION = '## 2. Environment';

    /** The one file on a machine that holds the values behind the tokens. */
    public const string MACHINE = '.agents/machine.local.json';

    /** The program that owns that file's shape, and the only source of the token table. */
    public const string RENDERER = '.agents/render_local.py';

    /** The environment file this package states it does not have. */
    public const string ENVIRONMENT = '.env';

    /**
     * One entry of the renderer's `TOKENS` mapping: the token, the section and key of
     * `.agents/machine.local.json` it is read from, and the purpose written beside it.
     *
     * Anchored at the mapping's own indentation, which is what keeps a token named in the file's
     * prose — in a help string, or in the sentence this class quotes — from being read as an entry.
     * The entry's own line is the only place the shape is written down.
     */
    private const string TOKENS = '/^[ \t]+"(__[A-Z0-9_]+__)": \(\("([^"]+)", "([^"]+)"\)/m';

    /** A token as a document writes it. */
    private const string NAMED = '/__[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)*__/';

    /**
     * The one name written like a token that is not one: the placeholder this package tells a
     * writer to use where a path would go, which `AGENTS.md` section 2's closing sentence writes.
     *
     * Excused by name rather than left out of the pattern, because the pattern is what says whether
     * a document names something a reader will try to resolve. The same rule is made on the Python
     * side of this layer, in `verify.py`'s `tokens` step, so one name means one thing in both.
     *
     * @var list<string>
     */
    private const array GENERIC = ['__TOKEN__'];

    /**
     * What the documents and the machine file disagree about: empty when the environment a reader is
     * handed is the environment this machine has.
     *
     * The documents are read for the tokens they name, and the renderer for the tokens it has and
     * the key each one lives under; the machine file is then asked for the value of every token the
     * documents name. Each finding names the file and line it is about, so that a table pointing at
     * nothing cannot be read as a table that is in order.
     *
     * @return list<string>
     *
     * @throws RuntimeException when the renderer has no token table to read, or when the document
     *                          this reading is about has no environment section — a reading with
     *                          nothing to read is the one answer that must not come back empty
     */
    public static function stale(string $root): array
    {
        $root = self::root($root);
        $tokens = self::tokens($root);
        $findings = [];

        // The table, and the tokens it names. A token the renderer has never heard of is wrong on
        // every machine, which is why it is reported before anything is asked about values.
        foreach ([self::RULES => self::TABLE, self::HANDOFF => self::SECTION] as $document => $heading) {
            $markdown = self::document($root, $document);

            if ($document === self::HANDOFF && $markdown === null) {
                continue;
            }

            if ($markdown === null) {
                throw new RuntimeException(sprintf(
                    '%s is not here, so the environment table this reading is about cannot be read at all.',
                    $document,
                ));
            }

            $named = self::named($markdown, $heading);

            if ($named === null) {
                $findings[] = sprintf(
                    '%s has no `%s` heading, so the section this reading is about is not there.',
                    $document,
                    $heading,
                );

                continue;
            }

            foreach ($named as $token => $line) {
                if (in_array($token, self::GENERIC, true)) {
                    continue;
                }

                if (! isset($tokens[$token])) {
                    $findings[] = sprintf(
                        '%s:%d: names `%s`, and %s has no such token — a name nothing resolves is a token no command can be written with.',
                        $document,
                        $line,
                        $token,
                        self::RENDERER,
                    );

                    continue;
                }

                $findings = [...$findings, ...self::held($root, $document, $line, $token, $tokens[$token])];
            }
        }

        if (is_file($root.'/'.self::ENVIRONMENT)) {
            $findings[] = sprintf(
                '%s is here, and %s and %s both state that this package has no such file.',
                self::ENVIRONMENT,
                self::RULES,
                self::HANDOFF,
            );
        }

        return array_values(array_unique($findings));
    }

    /**
     * The token table as the renderer declares it: each token against the section and key of
     * `.agents/machine.local.json` it is read from.
     *
     * @return array<string, array{section: string, key: string}>
     *
     * @throws RuntimeException when the mapping reads as fewer than two entries, which is a changed
     *                          shape rather than a table: a reading that found none would hold every
     *                          document to a token list it made up
     */
    public static function tokens(string $root): array
    {
        $renderer = @file_get_contents(self::root($root).'/'.self::RENDERER);

        if ($renderer === false) {
            throw new RuntimeException(sprintf(
                '%s is not here, and it is where the token table and the machine file\'s shape are written down.',
                self::RENDERER,
            ));
        }

        preg_match_all(self::TOKENS, $renderer, $matches, PREG_SET_ORDER);

        $tokens = [];

        foreach ($matches as $match) {
            $tokens[$match[1]] = ['section' => $match[2], 'key' => $match[3]];
        }

        if (count($tokens) < 2) {
            throw new RuntimeException(sprintf(
                '%s: its `TOKENS` mapping reads as %d token(s), so the table this reading compares a document with is not there.',
                self::RENDERER,
                count($tokens),
            ));
        }

        return $tokens;
    }

    /**
     * The machine file as this checkout has it, or `null` where there is none to read.
     *
     * Null is the answer on a CI runner and in a checkout nobody has set up: there is no file, so no
     * value is missing from it, and a reading that treated the two alike would report every runner
     * as a machine with an unfinished environment.
     *
     * @return array<string, mixed>|null
     */
    public static function machine(string $root): ?array
    {
        $path = self::root($root).'/'.self::MACHINE;

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * The handoff document, or `null` where that folder is not in the checkout.
     *
     * Null rather than an empty string, because the two are different statements about the tree: a
     * document that is not here has nothing to agree or disagree with, and a document that is here
     * and reads as nothing is a document this reading would be quiet about for no reason.
     */
    public static function handoff(string $root): ?string
    {
        return self::document(self::root($root), self::HANDOFF);
    }

    /**
     * The tokens one section of a document names, with the line each is written on.
     *
     * The section runs from its heading to the next second-level heading, and the whole of it is
     * read rather than its table alone: the prose under the table names the same tokens, and one of
     * those sentences is the example this package gives of a token standing where a command should
     * be. Every one of them is a name a reader will try to resolve, which is the whole question
     * here.
     *
     * @return array<string, int>|null null when the document has no such heading
     */
    public static function named(string $markdown, string $heading): ?array
    {
        $named = [];
        $inside = false;
        $line = 0;

        foreach (explode("\n", str_replace("\r\n", "\n", $markdown)) as $index => $text) {
            $line = $index + 1;

            if (str_starts_with($text, '## ')) {
                if ($inside) {
                    break;
                }

                $inside = rtrim($text) === $heading;

                continue;
            }

            if (! $inside) {
                continue;
            }

            preg_match_all(self::NAMED, $text, $matches);

            foreach ($matches[0] as $token) {
                $named[$token] ??= $line;
            }
        }

        return $inside || $named !== [] ? $named : null;
    }

    /**
     * What the machine file is asked for one token a document names: the value it holds, or the
     * renderer's own answer where the file holds none.
     *
     * @param  array{section: string, key: string}  $key
     * @return list<string>
     */
    private static function held(string $root, string $document, int $line, string $token, array $key): array
    {
        $machine = self::machine($root);

        if ($machine === null) {
            // No machine file: the value half of this reading has nothing to be short of a value,
            // and the renderer is not asked on behalf of a machine that never named one. Said out
            // loud rather than answered quietly, because "there is no file to be short of a value"
            // and "the file holds everything the table names" are otherwise the same answer, and
            // only one of them is this reading having read something.
            Notes::add(self::class, sprintf(
                'there is no %s in this checkout, so the value half of the environment table was not asked - a runner and a checkout nobody has run `--init` in both look like this',
                self::MACHINE,
            ), $root);

            return [];
        }

        $section = $machine[$key['section']] ?? null;
        $value = is_array($section) ? ($section[$key['key']] ?? null) : null;

        if (! is_string($value) || trim($value) === '') {
            $derived = self::resolved($root, $token);

            if ($derived !== null) {
                $value = $derived;
            } else {
                return [sprintf(
                    '%s:%d: names `%s`, and %s holds no value for it at `%s.%s`.',
                    $document,
                    $line,
                    $token,
                    self::MACHINE,
                    $key['section'],
                    $key['key'],
                )];
            }
        }

        $value = trim($value);

        // Two token names carry their own claim about what the value is — a binary and a directory
        // — and both are checked, because a recorded path that is not there is the machine file
        // being wrong in the one way its readers meet as a failure halfway through a command.
        if (str_ends_with($token, '_EXE__') && ! is_file($value)) {
            return [sprintf(
                '%s:%d: names `%s`, and %s records %s for it, which is not a file.',
                $document,
                $line,
                $token,
                self::MACHINE,
                $value,
            )];
        }

        if (str_ends_with($token, '_DIR__') && ! is_dir($value)) {
            return [sprintf(
                '%s:%d: names `%s`, and %s records %s for it, which is not a directory.',
                $document,
                $line,
                $token,
                self::MACHINE,
                $value,
            )];
        }

        // The package directory is the one value with a second claim to check: the machine file says
        // which checkout it is about, and a file naming another one resolves every path in every
        // command to the wrong tree while reading as a complete answer.
        if ($token === '__PACKAGE_DIR__' && ! self::same($value, $root)) {
            return [sprintf(
                '%s:%d: names `%s`, and %s names %s as this checkout, which is %s.',
                $document,
                $line,
                $token,
                self::MACHINE,
                $value,
                $root,
            )];
        }

        return [];
    }

    /**
     * What the renderer answers for one token, or `null` when it cannot be asked.
     *
     * Asked rather than derived, because the shape belongs to the renderer: `__PHP_DIR__` is the
     * directory holding `__PHP_EXE__` when the machine file does not carry it, and a second
     * derivation here would be a second thing to keep in step with that one. One `--value` call is
     * the price, and it is paid only for a token the machine file holds no value for.
     *
     * `null` covers every way the question can go unasked — no Python to run it with, no renderer in
     * the tree, a non-zero exit — and the caller reports nothing for it, because an answer nobody
     * could get is not an answer that is wrong.
     */
    private static function resolved(string $root, string $token): ?string
    {
        $renderer = $root.'/'.self::RENDERER;

        if (! is_file($renderer)) {
            return null;
        }

        // A refused start answers `null` here rather than being waited out into an exception: this
        // reading treats "the renderer could not be asked" as an answer of its own. The waiting is
        // `Processes`', because a refused start is a fact about this machine and not about tokens.
        $started = Processes::attempt(
            ['python', $renderer, '--value', $token],
            [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w']],
            $root,
        );

        if ($started === null) {
            return null;
        }

        [$process, $pipes] = $started;

        $answer = trim((string) stream_get_contents($pipes[1]));
        fclose($pipes[1]);

        return proc_close($process) === 0 && $answer !== '' ? $answer : null;
    }

    /**
     * A document's bytes, or `null` where it is not in the tree.
     */
    private static function document(string $root, string $document): ?string
    {
        $path = $root.'/'.$document;

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    /**
     * The package root in the form every path here is compared in.
     */
    private static function root(string $root): string
    {
        return rtrim(str_replace('\\', '/', $root), '/');
    }

    /**
     * Whether two spellings of a path are the same place.
     *
     * Compared through `realpath()` when both sides resolve, because a checkout reached through a
     * symlink and the path it resolves to are one directory and two strings — and with case folded
     * on Windows, where they are also one directory and two spellings of it.
     */
    private static function same(string $one, string $other): bool
    {
        $one = rtrim(str_replace('\\', '/', $one), '/');
        $other = rtrim(str_replace('\\', '/', $other), '/');

        if ($one === $other) {
            return true;
        }

        $realOne = realpath($one);
        $realOther = realpath($other);

        if ($realOne !== false && $realOther !== false) {
            $one = rtrim(str_replace('\\', '/', $realOne), '/');
            $other = rtrim(str_replace('\\', '/', $realOther), '/');
        }

        return PHP_OS_FAMILY === 'Windows' ? strcasecmp($one, $other) === 0 : $one === $other;
    }
}
