<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * the committed machine file's shape, held to the sections and keys `--init` opens a fresh clone with.
 *
 * WHY THIS EXISTS
 * ---------------
 * `.agents/machine.local.json` is the one file in this repository allowed to name a machine, and it
 * is gitignored — so it is in no tree a commit carries, and two other things stand in for it. The
 * first is `.agents/machine.local.json.example`, committed beside the renderer and described as the
 * committed shape: what a reader copies, and the one committed file in the layer that shows a
 * machine path as a placeholder. The second is the file `python .agents/render_local.py --init`
 * writes, which is what a fresh clone is actually opened with, because `bin/bootstrap.php` runs
 * exactly that on a clone nobody has set up yet.
 *
 * Nothing compared them. `verify.py`'s `tokens` step judges the rendered settings files only where
 * there is a machine file to judge them against, and a CI runner has none — so on the machine where
 * the gate runs, that half is not asked either. A section could be added to the renderer's
 * `SECTIONS` and never reach the example a reader copies, or the example could grow a section that
 * `--init` has never opened, and both would read as a shape: the first a machine file nobody can
 * complete from the document beside it, the second a section a fresh clone silently does not get.
 *
 * WHERE THE SHAPE COMES FROM
 * --------------------------
 * The renderer, because it is the program that writes the file. `SECTIONS` is its declaration of
 * what a machine file holds, `OWNED` the keys `--init` writes from the answers it was given, and
 * `TOKENS` the section and key each `__TOKEN__` is answered from — all three read out of
 * `.agents/render_local.py` rather than copied here, which is what makes this a comparison rather
 * than a second definition of the shape. `Tests\Support\Handoff` reads the same mapping for the same
 * reason, and `Tests\Support\PhpBinary` refuses to read the machine file at all: a second reader is
 * a second thing to keep in step.
 *
 * WHAT IT DOES NOT HOLD
 * ---------------------
 * Not this machine's file. `--init` merges the keys it owns into whatever is already there and keeps
 * every other section as it stands — including one nobody declared, on purpose, because a re-run
 * that took the next person's section with it is the defect the renderer's `SECTIONS` was written to
 * fix. So a section on this machine that the declaration does not name is this machine's own record
 * rather than a drift, and what is compared here are the two *declarations*: the example, which says
 * it is the shape, and the program, which is what opens the file.
 *
 * @guards-index reading
 */
final class MachineShape
{
    /** The committed shape of the machine file, and the only committed file that shows a path. */
    public const string EXAMPLE = '.agents/machine.local.json.example';

    /** The program that owns the shape, and the only place its sections are declared. */
    public const string RENDERER = '.agents/render_local.py';

    /** The file `--init` writes, which is what a fresh clone is opened with. */
    public const string MACHINE = '.agents/machine.local.json';

    /** The PowerShell the fixture hands the renderer, which is the default it writes down itself. */
    public const string PWSH = 'pwsh.exe';

    /**
     * The settings templates `--init` renders once it has written the file.
     *
     * They are planted in a fixture for the reason `BootstrapTest` gives about its own: the renderer
     * refuses — exit 1, one finding per template — when a template it declares is not there, so a
     * fixture without them would be a run that failed for a reason about the fixture.
     *
     * @var list<string>
     */
    public const array TEMPLATES = [
        '.trae/settings.json.example',
        '.trae/settings.local.json.example',
        '.kiro/settings/local.json.example',
    ];

    /**
     * The renderer's declaration of the sections a machine file holds, as the one block it is.
     *
     * Read as a block rather than line by line anywhere in the file, because the same names are
     * written in the prose above it — the comment explaining why they are declared — and a mapping
     * read out of its own explanation is a reading of the wrong text.
     */
    private const string SECTIONS_BLOCK = '/^SECTIONS: dict\[str, str\] = \{(.*?)^\}/ms';

    /** One entry of that block: the section, and the sentence saying what it is for. */
    private const string SECTIONS_ENTRY = '/^[ \t]+"([^"]+)": "([^"]*)"/m';

    /** The keys `--init` writes from the answers it was given, as the one block `OWNED` is. */
    private const string OWNED_BLOCK = '/^OWNED: dict\[str, tuple\[str, \.\.\.\]\] = \{(.*?)^\}/ms';

    /** One entry of it: the section, and the keys it writes there in parentheses. */
    private const string OWNED_ENTRY = '/^[ \t]+"([^"]+)": \(([^)]*)\)/m';

    /** A key inside an entry's parentheses. */
    private const string OWNED_KEY = '/"([^"]+)"/';

    /**
     * The words a placeholder falls back to when nobody has said what belongs under the key.
     *
     * Each one fits every key in the file, which is what makes it useless to the reader it is written
     * for: the example is copied and filled in, so a value left as `value` or `path` records an
     * instruction as a fact. They are listed rather than inferred from the word count, because the
     * failure is a word that is *about* a value rather than about this key, and a count cannot tell
     * the two apart.
     *
     * @var list<string>
     */
    private const array STOCK = [
        'value', 'string', 'number', 'path', 'list', 'object', 'array', 'boolean', 'true', 'false',
        'null', 'none', 'todo', 'tbd', 'xxx', 'n/a', '?', '...',
    ];

    /**
     * The sections a machine file holds, as the renderer declares them: each one against the
     * sentence saying what it is for.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException when that declaration cannot be read, or reads as fewer than two
     *                          sections — a changed shape rather than a list, and a reading that
     *                          found none would hold the example to a shape it made up
     */
    public static function sections(string $root): array
    {
        $renderer = self::source($root, self::RENDERER);

        preg_match(self::SECTIONS_BLOCK, $renderer, $block);

        $sections = [];

        if (isset($block[1])) {
            preg_match_all(self::SECTIONS_ENTRY, $block[1], $entries, PREG_SET_ORDER);

            foreach ($entries as $entry) {
                $sections[$entry[1]] = $entry[2];
            }
        }

        if (count($sections) < 2) {
            throw new RuntimeException(sprintf(
                '%s: its `SECTIONS` declaration reads as %d section(s), so the shape this reading compares the example with is not there.',
                self::RENDERER,
                count($sections),
            ));
        }

        return $sections;
    }

    /**
     * The keys `--init` writes itself, by section: the three answers it was handed, and no others.
     *
     * These are the keys a fresh clone is guaranteed to have a value for, which is why they are the
     * half of the shape the example cannot be missing without a machine file that resolves nothing.
     *
     * @return array<string, list<string>>
     */
    public static function owned(string $root): array
    {
        $renderer = self::source($root, self::RENDERER);

        preg_match(self::OWNED_BLOCK, $renderer, $block);

        $owned = [];

        if (isset($block[1])) {
            preg_match_all(self::OWNED_ENTRY, $block[1], $entries, PREG_SET_ORDER);

            foreach ($entries as $entry) {
                preg_match_all(self::OWNED_KEY, $entry[2], $keys);

                $owned[$entry[1]] = array_values($keys[1]);
            }
        }

        return $owned;
    }

    /**
     * The section and key each token is answered from, out of the one mapping that owns them.
     *
     * `Handoff`'s call rather than a second pattern here: the token table is written down once, in
     * the renderer, and this reading asks the reader that already reads it for the environment table.
     *
     * @return array<string, array{section: string, key: string}>
     */
    public static function tokens(string $root): array
    {
        return Handoff::tokens($root);
    }

    /**
     * The committed example as the shape it declares: each section, and the keys it shows under it.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the example is not there, or is not a JSON object — a shape
     *                          nothing can be read out of is the one answer that must not come back
     *                          as an agreement
     */
    public static function seeded(string $root): array
    {
        return self::decode(self::root($root).'/'.self::EXAMPLE, self::EXAMPLE);
    }

    /**
     * The sections a fresh clone's machine file is opened with, by running the real renderer.
     *
     * Asked of the program rather than inferred from its declaration, because the claim the example
     * makes is about the file a new machine gets: `--init` writes the three answers it was given into
     * the sections it owns, opens every other declared section as it stands, and is the program
     * `bin/bootstrap.php` runs. The fixture is the tree it is asked in, with this repository's
     * renderer copied into it — the two questions a guard asks are one program apart, not one tree.
     *
     * @return array<string, mixed> the file it wrote, decoded
     *
     * @throws RuntimeException when there is no renderer to run, when it cannot be started, or when
     *                          it answers non-zero — a run that did not write the file has nothing
     *                          to be read, which is not the same answer as an empty one
     */
    public static function opened(string $root): array
    {
        $root = self::root($root);
        $answer = self::run($root, '--init', '--yes', '--php='.PHP_BINARY, '--pwsh='.self::PWSH, '--package='.$root);

        if ($answer['exit'] !== 0) {
            throw new RuntimeException(sprintf(
                'python %s --init answered %d in %s, so the machine file it writes there was not read: %s',
                self::RENDERER,
                $answer['exit'],
                $root,
                trim($answer['out'].$answer['err']),
            ));
        }

        return self::decode($root.'/'.self::MACHINE, self::MACHINE);
    }

    /**
     * What `--check` answers in that tree: its exit code, and the lines it printed on its output.
     *
     * Asked of the program for the same reason `opened()` is — the report is the renderer's, and a
     * list of sections kept here would be a second copy of the question rather than the answer to
     * it. Both answers are returned because both are the claim: the lines say which sections this
     * machine has recorded nothing in, and the exit code says that a machine in that state is being
     * told rather than failed, which is what makes the report usable on the clone
     * `bin/bootstrap.php` has just set up.
     *
     * @return array{exit: int, lines: list<string>}
     *
     * @throws RuntimeException when there is no renderer to run, or when it cannot be started
     */
    public static function checked(string $root): array
    {
        $answer = self::run(self::root($root), '--check');
        $lines = array_values(array_filter(array_map(
            trim(...),
            explode("\n", $answer['out']),
        ), static fn (string $line): bool => $line !== ''));

        return ['exit' => $answer['exit'], 'lines' => $lines];
    }

    /**
     * What the example and the renderer disagree about: empty when the shape a reader is handed is
     * the shape a fresh clone is opened with.
     *
     * Five questions, and each is a way the two declarations can come apart rather than a spelling of
     * one: a section the declaration has and the example does not show, a section the example shows
     * and `--init` never opens, a section shown with nothing under it, a key `--init` writes that the
     * example does not show, and a token whose key `--init` does not write at all.
     *
     * That last one is a fact about the renderer alone, and it is reported here rather than left to
     * the environment-table reading, which asks a different question — whether this machine's file
     * holds a value — and can only answer it where there is a file to be short of one. A token whose
     * key `--init` does not write is a value every machine has to fill in by hand, which is what the
     * token exists to avoid.
     *
     * @return list<string>
     */
    public static function stale(string $root): array
    {
        $root = self::root($root);
        $sections = self::sections($root);
        $owned = self::owned($root);
        $tokens = self::tokens($root);
        $example = self::seeded($root);
        $source = self::source($root, self::EXAMPLE);
        $findings = [];

        foreach ($sections as $section => $purpose) {
            if (! array_key_exists($section, $example)) {
                $findings[] = sprintf(
                    '%s: the example shows no `%s` section, and %s opens a fresh clone\'s file with one — %s.',
                    self::EXAMPLE,
                    $section,
                    self::RENDERER,
                    $purpose,
                );

                continue;
            }

            $held = $example[$section];

            if (! is_array($held)) {
                $findings[] = sprintf(
                    '%s:%d: the `%s` section is not an object, and the file this example stands in for holds one object per section.',
                    self::EXAMPLE,
                    self::lineOf($source, $section),
                    $section,
                );

                continue;
            }

            if ($held === []) {
                $findings[] = sprintf(
                    '%s:%d: the `%s` section is shown with no key under it, and the example is the shape a reader copies — a section with nothing in it is a heading rather than a shape.',
                    self::EXAMPLE,
                    self::lineOf($source, $section),
                    $section,
                );
            }
        }

        foreach (array_keys($example) as $section) {
            if (! array_key_exists($section, $sections)) {
                $findings[] = sprintf(
                    '%s:%d: the `%s` section is shown here, and %s does not declare it — no fresh clone is opened with it, so the shape this example states is not the shape a new machine gets.',
                    self::EXAMPLE,
                    self::lineOf($source, (string) $section),
                    $section,
                    self::RENDERER,
                );
            }
        }

        $findings = [...$findings, ...self::missingKeys($example, $source, $owned, $tokens)];

        foreach ($tokens as $token => $key) {
            if (! in_array($key['key'], $owned[$key['section']] ?? [], true)) {
                $findings[] = sprintf(
                    '%s: `%s` is answered from `%s.%s`, and `--init` writes no key there — a fresh clone is opened with that key absent, so the token resolves to nothing until somebody fills it in by hand.',
                    self::RENDERER,
                    $token,
                    $key['section'],
                    $key['key'],
                );
            }
        }

        return array_values(array_unique($findings));
    }

    /**
     * The example's values that say nothing about the key they sit under: empty when every value
     * tells a reader what belongs there.
     *
     * The example is the shape a reader copies, so the text of a value is the only instruction its
     * key gets. Three ways it stops being one, and each is a different failure rather than a
     * spelling of the same one: a value written as an angle-bracket token, which is a string that
     * copies and stays — the brackets are the value, and a machine file built from it records an
     * instruction where a path belongs; a stock word that would fit under any key, which is the
     * token with its brackets taken off; and one text written under two keys, which cannot be about
     * either, because a sentence that answers both is a sentence about neither. An empty value is
     * the fourth: a key shown with nothing to put in it.
     *
     * What this does not hold is whether a sentence is *true*: a value that names the wrong fact in
     * good prose reads the same here as the right one. That is a claim about this machine, and this
     * file is a shape rather than a record.
     *
     * Each finding names the dotted path as well as the line, because two keys in the example are
     * called `exe` and a line alone cannot tell one from the other; the text it quotes is the one a
     * reader has to replace, which is what makes it findable.
     *
     * @return list<string>
     */
    public static function generic(string $root): array
    {
        $source = self::source(self::root($root), self::EXAMPLE);
        $written = [];
        $findings = [];

        self::vague(self::seeded($root), '', $source, $written, $findings);

        return $findings;
    }

    /**
     * One node of the example, judged by the text a reader would copy out of it.
     *
     * Arrays are walked rather than judged, because a list's shape is how many entries it has and
     * what one of them says — the element's own text is the placeholder. A key is judged only for
     * the token it is written as: a key that names a tool or a nested section is a name, and two
     * sections legitimately share one such as `exe`.
     *
     * @param  array<string, string>  $written  the text already used, against the path that used it
     * @param  list<string>  $findings
     */
    private static function vague(mixed $node, string $path, string $source, array &$written, array &$findings): void
    {
        if (is_array($node)) {
            foreach ($node as $key => $child) {
                // A list's elements are numbered rather than named, and the index is not part of
                // what a reader is told: one entry of `skills.dirs` is one skill's target.
                if (is_int($key)) {
                    self::vague($child, $path.'[]', $source, $written, $findings);

                    continue;
                }

                $here = $path === '' ? $key : $path.'.'.$key;

                if (str_contains($key, '<') || str_contains($key, '>')) {
                    $findings[] = sprintf(
                        '%s:%d: the key `%s` is written as an angle-bracket token, and a reader copying the file gets a key no tool looks for — name the thing it stands for.',
                        self::EXAMPLE,
                        self::lineOfText($source, '"'.$key.'"'),
                        $here,
                    );
                }

                self::vague($child, $here, $source, $written, $findings);
            }

            return;
        }

        if (! is_string($node)) {
            return;
        }

        $text = trim($node);

        if ($text === '') {
            $findings[] = sprintf(
                '%s: `%s` holds nothing, and a key with nothing under it is a heading rather than a value — say what belongs here.',
                self::EXAMPLE,
                $path,
            );

            return;
        }

        if (str_contains($text, '<') || str_contains($text, '>')) {
            $findings[] = sprintf(
                '%s:%d: `%s` is written as `%s`, an angle-bracket token that copies as it stands — the brackets become the value, so say what belongs there instead.',
                self::EXAMPLE,
                self::lineOfText($source, $text),
                $path,
                $text,
            );
        }

        if (in_array(strtolower($text), self::STOCK, true)) {
            $findings[] = sprintf(
                '%s:%d: `%s` is `%s`, a word that fits any key in this file — it says what kind of value goes there rather than what this one records.',
                self::EXAMPLE,
                self::lineOfText($source, '"'.$text.'"'),
                $path,
                $text,
            );
        }

        if (isset($written[$text])) {
            $findings[] = sprintf(
                '%s:%d: `%s` repeats the text written for `%s`, and a sentence that answers both keys is a sentence about neither — each one says what its own value is.',
                self::EXAMPLE,
                self::lineOfText($source, $text),
                $path,
                $written[$text],
            );

            return;
        }

        $written[$text] = $path;
    }

    /**
     * The line a text is written on, for a finding that has to point at one.
     *
     * The first line holding it, which is enough here because a placeholder that is not unique is a
     * finding of its own: the duplicate is named with both paths, and the line is the first of them.
     * A text that is nowhere reads as line 1 rather than as a refusal — the finding is about a value
     * the file does not hold, and pointing at where it would have been written is the useful answer.
     */
    private static function lineOfText(string $source, string $text): int
    {
        foreach (explode("\n", $source) as $index => $line) {
            if (str_contains($line, $text)) {
                return $index + 1;
            }
        }

        return 1;
    }

    /**
     * The renderer, run in a tree with the arguments given, and what it answered.
     *
     * The one place a child process is started here, so the two questions above are one rig apart
     * rather than two: this repository's renderer, asked in a fixture, with the output and the error
     * stream kept apart — the notes are written to the output, and a Python warning is not a line of
     * the report.
     *
     * @param  string  ...$arguments  the renderer's own arguments, one word each
     * @return array{exit: int, out: string, err: string}
     *
     * @throws RuntimeException when the renderer is not in the tree, or Python cannot be started —
     *                          a question this reading cannot put is not an answer of "nothing"
     */
    private static function run(string $root, string ...$arguments): array
    {
        $renderer = $root.'/'.self::RENDERER;

        if (! is_file($renderer)) {
            throw new RuntimeException(sprintf(
                '%s is not here, so nothing can be asked about what a fresh clone is opened with or what it has recorded.',
                self::RENDERER,
            ));
        }

        $process = @proc_open(
            ['python', $renderer, ...$arguments],
            [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start python in '.$root);
        }

        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($process), 'out' => $output, 'err' => $error];
    }

    /**
     * The keys `--init` writes that the example does not show, each named with what its absence costs.
     *
     * A key is one of the two things the shape is made of, and this is the half whose absence has a
     * reader-visible price: the token answered from it resolves to nothing in a machine file built
     * from the example, and the sentence names that token where one is.
     *
     * @param  array<string, mixed>  $example
     * @param  string  $source  the example's own bytes, for the line each section is written on
     * @param  array<string, list<string>>  $owned
     * @param  array<string, array{section: string, key: string}>  $tokens
     * @return list<string>
     */
    private static function missingKeys(array $example, string $source, array $owned, array $tokens): array
    {
        $findings = [];
        $answered = [];

        foreach ($tokens as $token => $key) {
            $answered[$key['section'].'.'.$key['key']] = $token;
        }

        foreach ($owned as $section => $keys) {
            $held = $example[$section] ?? [];
            $held = is_array($held) ? $held : [];

            foreach ($keys as $key) {
                if (array_key_exists($key, $held)) {
                    continue;
                }

                $token = $answered[$section.'.'.$key] ?? null;

                $findings[] = sprintf(
                    '%s:%d: the `%s` section shows no `%s`, and it is a key `--init` writes from an answer it was handed%s.',
                    self::EXAMPLE,
                    self::lineOf($source, $section),
                    $section,
                    $key,
                    $token === null ? '' : sprintf(' — the value `%s` is answered from', $token),
                );
            }
        }

        return $findings;
    }

    /**
     * A file in the tree as its bytes, or a refusal naming it.
     *
     * @throws RuntimeException when the file is not there: a reading of a file that is missing is a
     *                          reading of nothing, and the empty answer it would otherwise give is
     *                          the same one a tree in order gives
     */
    private static function source(string $root, string $file): string
    {
        $path = self::root($root).'/'.$file;
        $bytes = is_file($path) ? @file_get_contents($path) : false;

        if ($bytes === false) {
            throw new RuntimeException(sprintf(
                '%s is not here, so the shape this reading is about cannot be read at all.',
                $file,
            ));
        }

        return $bytes;
    }

    /**
     * A JSON file as the object it is, or a refusal naming it.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException when the file is not there, or is not an object — both are a shape
     *                          nothing can be read out of rather than a file that agrees
     */
    private static function decode(string $path, string $file): array
    {
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf(
                '%s is not a JSON object, so the shape it is supposed to hold cannot be read out of it.',
                $file,
            ));
        }

        return $decoded;
    }

    /**
     * The line a section is written on, for a finding that has to point at one.
     *
     * The first line whose own text opens with the section's name, which is how both files write it:
     * the example as a JSON key, the renderer as an entry of its `SECTIONS` block. A name that is
     * nowhere reads as line 1 rather than as a refusal — the finding is about a section that is not
     * in the file, and pointing at where it would have been written is the useful answer.
     */
    private static function lineOf(string $source, string $section): int
    {
        foreach (explode("\n", $source) as $index => $line) {
            if (str_starts_with(trim($line), '"'.$section.'"')) {
                return $index + 1;
            }
        }

        return 1;
    }

    /**
     * The tree's root in the form every path here is compared in.
     */
    private static function root(string $root): string
    {
        return rtrim(str_replace('\\', '/', $root), '/');
    }
}
