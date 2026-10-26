<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * the state section of the handoff, held to what `handoff/state.sh` measures: its branch, its gate wiring, its changelog endings, and the date every count and colour it states was measured on.
 *
 * WHY THIS EXISTS
 * ---------------
 * `handoff/HANDOFF.md` §3 opens with a promise: "`bash handoff/state.sh` prints all of the below as
 * it is at the moment it runs, so it outranks this section the moment the two disagree." The script
 * is the measurement and the section is a snapshot of it — and nothing held the two together, so the
 * section drifted the way a snapshot does. It carried `CHANGELOG.md` as a file "`bin/commit.php` can
 * only write a bullet into while it is LF" for five days after `ca776f0` made the writer use the
 * file's own endings, and the same paragraph is what `state.sh` contradicted on every run: the
 * script prints "bin/commit.php can write a bullet into it" beside the count it measured.
 *
 * A reading cannot hold a snapshot to the tree it was taken from, and pretending otherwise would be
 * the worse guard: a section that states the live `HEAD` and the live ahead count is red the moment
 * anything is committed, which is the ordinary state of this checkout. So the split is made where
 * the facts actually divide:
 *
 *   - the ones that do not move with work in progress are held to the script, now: the branch, the
 *     hook wiring, and `CHANGELOG.md`'s line endings, which is a fact about the tree rather than about
 *     the moment and the one `bin/commit.php` reads;
 *   - the ones that do move — `HEAD`, how far ahead of the upstream it is, how many paths are listed
 *     — are the script's to print, and the section may state them only in a bullet that says when it
 *     measured them. A count with no date is a claim about now, and now is `state.sh`'s;
 *   - the colour the section carries is a tally from a run of the suite, which no script measures, so
 *     the section has to be a snapshot rather than a competing measurement: its heading carries the
 *     date it was measured on, and it says the script outranks it.
 *
 * WHAT IT READS
 * -------------
 * `bash handoff/state.sh` is run — the program, in the tree it is asked about, so the parse is a
 * reading of what the script prints rather than a second implementation of the queries it runs. The
 * document is then read for the four claims above, each found by the words it is written with: the
 * `branch` bullet of §1, the `core.hooksPath` sentence beside it, the `CHANGELOG.md` bullet of §3.1,
 * and every bullet that states a `HEAD` or how far ahead it is. What comes back is one finding per
 * disagreement, naming the file and the line, which is the form the rest of this package's readings
 * answer in.
 *
 * The folder is removable — `AGENTS.md` §3 says the pointer to it goes with it — so a checkout
 * without the handoff answers with an empty list rather than a refusal: there is no snapshot to hold
 * to anything. A handoff with no script beside it is the other case, and it throws: the section's
 * precedence sentence names a program that is not there, which is a claim nothing can be measured
 * against.
 *
 * @guards-index reading
 */
final class HandoffState
{
    /** The snapshot: a section of a document, which is what a reader is handed. */
    public const string DOCUMENT = 'handoff/HANDOFF.md';

    /** The measurement: the program the snapshot defers to, and the only thing this reading trusts. */
    public const string SCRIPT = 'handoff/state.sh';

    /** The section's own heading, up to the date it carries. */
    public const string STATE = '## 3. State';

    /** §1, which is where the branch and the gate the handoff was written against are stated. */
    public const string PACKAGE = '## 1. The package';

    /** The file whose endings a commit path reads, and which §3.1 states one way or the other. */
    public const string CHANGELOG = 'CHANGELOG.md';

    /** A date, as the document writes one: the only thing that makes a count a measurement. */
    private const string DATE = '/20\d\d-\d\d-\d\d/';

    /**
     * A claim that moves with the tree: a `HEAD`, how far ahead of the upstream it is, a tally of the
     * suite, or its colour said in a word.
     *
     * The tally and the colour are here for the same reason the `HEAD` is: a bullet reading "466
     * passed" or "the suite is green" is a measurement of a moment, and a reader takes it as the
     * tree's now. What makes it a measurement rather than a claim about today is the date it carries
     * — in the bullet, or in the heading it sits under.
     */
    private const string COUNTED = '/HEAD `[0-9a-f]{7,40}`|\b\d+ commits? ahead\b|\b\d+ behind\b|\b\d+ (?:failed|passed)\b|\b(?:red|green)\b/';

    /** The branch as §1 spells it: in backticks, after the word. */
    private const string BRANCH = '/branch `([^`]+)`/';

    /**
     * What `handoff/state.sh` measured, run in the tree it is asked about.
     *
     * @return array{
     *     branch: string,
     *     staged: int,
     *     index: string,
     *     listed: int,
     *     content: int,
     *     untracked: int,
     *     reports: int,
     *     stashes: int,
     *     hooks: string|null,
     *     changelog: array{cr: int, writable: bool}|null,
     *     files: array<string, string>
     * }
     *
     * @throws RuntimeException when the script is not there, cannot be started, or answers non-zero —
     *                          a measurement that did not run is not an empty one
     */
    public static function measured(string $root): array
    {
        $root = self::root($root);
        $script = $root.'/'.self::SCRIPT;

        if (! is_file($script)) {
            throw new RuntimeException(sprintf(
                '%s is not here, so the section of %s that says it outranks this one cannot be held to anything.',
                self::SCRIPT,
                self::DOCUMENT,
            ));
        }

        // The path is relative to the tree it is run in rather than the one on disk: a POSIX shell on
        // Windows cannot open a drive-letter path, and the script derives everything it prints from
        // its own location, so the relative name is the whole of what it needs.
        //
        // A refused start is waited out, because under a pool Windows answers `CreateProcess failed`
        // before the child exists — this reading is one of the places it was measured. The waiting is
        // `Processes`', so this one and every other spawning fixture are answered in the same place.
        $started = Processes::attempt(
            ['bash', self::SCRIPT],
            [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $root,
        );

        if ($started === null) {
            throw new RuntimeException(sprintf(
                'Could not start bash for %s in %s: %s',
                self::SCRIPT,
                $root,
                Processes::reason(),
            ));
        }

        [$process, $pipes] = $started;

        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        if ($exit !== 0) {
            throw new RuntimeException(sprintf(
                '%s answered %d in %s, so the state this reading is about was not measured: %s',
                self::SCRIPT,
                $exit,
                $root,
                trim($output.$error),
            ));
        }

        return self::parsed($output);
    }

    /**
     * The claims the document makes about the state, or `null` where the folder is not in the tree.
     *
     * @return array{
     *     heading: string,
     *     heading_line: int,
     *     dated: bool,
     *     deference: bool,
     *     branch: array{value: string, line: int}|null,
     *     hooks: array{value: string, line: int}|null,
     *     changelog: array{cr: int, writable: bool|null, line: int}|null,
     *     undated: list<array{line: int, claim: string}>
     * }|null
     *
     * @throws RuntimeException when the document is here and §3 is not — the snapshot this reading is
     *                          about has no other place to be
     */
    public static function stated(string $root): ?array
    {
        $root = self::root($root);
        $markdown = Handoff::handoff($root);

        if ($markdown === null) {
            return null;
        }

        $lines = self::lines($markdown);
        $state = self::section($lines, self::STATE);

        if ($state === null) {
            throw new RuntimeException(sprintf(
                '%s has no `%s` section, so the snapshot this reading holds to %s is not there.',
                self::DOCUMENT,
                self::STATE,
                self::SCRIPT,
            ));
        }

        $package = self::section($lines, self::PACKAGE);
        $text = implode("\n", $state['lines']);
        $heading = $state['heading'];

        return [
            'heading' => $heading,
            'heading_line' => $state['at'] + 1,
            'dated' => preg_match(self::DATE, $heading) === 1,
            'deference' => str_contains($text, self::SCRIPT) && str_contains($text, 'outranks'),
            'branch' => self::claim($package, self::BRANCH),
            'hooks' => self::hooks($package),
            'changelog' => self::changelog($state),
            'undated' => self::undated($state, $package),
        ];
    }

    /**
     * What the snapshot and the measurement disagree about: empty when the section is what the script
     * would print.
     *
     * @param  array<string, mixed>|null  $measured  the measurement, when the caller has already taken
     *                                               one — a planted tree a test builds has no history
     *                                               to measure, and the comparison is the same one
     * @return list<string>
     *
     * @throws RuntimeException when the handoff is here and cannot be read, which is the answer this
     *                          reading must not give as an empty list
     */
    public static function stale(string $root, ?array $measured = null): array
    {
        $root = self::root($root);
        $stated = self::stated($root);

        // A checkout without the folder has no snapshot to hold to anything: the document says it can
        // go, and `AGENTS.md` §3 has the line to delete with it.
        if ($stated === null) {
            return [];
        }

        $measured ??= self::measured($root);
        $findings = [];

        if (! $stated['dated']) {
            $findings[] = sprintf(
                '%s:%d: the section states the suite\'s colour and does not say when it was measured — a colour with no date reads as the tree\'s now, and %s is what the tree answers now.',
                self::DOCUMENT,
                $stated['heading_line'],
                self::SCRIPT,
            );
        }

        if (! $stated['deference']) {
            $findings[] = sprintf(
                '%s:%d: the section does not say that `bash %s` outranks it, which is the sentence that makes it a snapshot rather than a second measurement.',
                self::DOCUMENT,
                $stated['heading_line'],
                self::SCRIPT,
            );
        }

        $branch = $stated['branch'];

        if ($branch !== null && $branch['value'] !== $measured['branch']) {
            $findings[] = sprintf(
                '%s:%d: names the branch `%s`, and %s answers `%s` in this checkout.',
                self::DOCUMENT,
                $branch['line'],
                $branch['value'],
                self::SCRIPT,
                $measured['branch'],
            );
        }

        $hooks = $stated['hooks'];
        $wired = $measured['hooks'];

        if ($hooks !== null && $wired !== null && $hooks['value'] !== $wired) {
            $findings[] = sprintf(
                '%s:%d: says the gate is wired through `%s`, and %s answers `%s` — a commit in a clone where that is unset runs no hook at all.',
                self::DOCUMENT,
                $hooks['line'],
                $hooks['value'],
                self::SCRIPT,
                $wired,
            );
        }

        $changelog = $stated['changelog'];
        $endings = $measured['changelog'];

        if ($changelog !== null && $endings !== null) {
            if ($changelog['cr'] !== $endings['cr']) {
                $findings[] = sprintf(
                    '%s:%d: states `%s` as %d CR bytes, and %s counts %d.',
                    self::DOCUMENT,
                    $changelog['line'],
                    self::CHANGELOG,
                    $changelog['cr'],
                    self::SCRIPT,
                    $endings['cr'],
                );
            }

            if ($changelog['writable'] !== null && $changelog['writable'] !== $endings['writable']) {
                $findings[] = sprintf(
                    '%s:%d: says `bin/commit.php` %s write a bullet into `%s`, and %s says it %s — the writer takes the ending from the heading it matched, so this is a claim about the tool rather than about the file it is run in.',
                    self::DOCUMENT,
                    $changelog['line'],
                    $changelog['writable'] ? 'can' : 'cannot',
                    self::CHANGELOG,
                    self::SCRIPT,
                    $endings['writable'] ? 'can' : 'cannot',
                );
            }
        }

        foreach ($stated['undated'] as $claim) {
            $findings[] = sprintf(
                '%s:%d: states %s with no date on the bullet it is in — a count that moves with every commit is the script\'s to print, and one written down is a measurement of a moment that has to say which.',
                self::DOCUMENT,
                $claim['line'],
                $claim['claim'],
            );
        }

        return array_values(array_unique($findings));
    }

    /**
     * The script's own report, field by field.
     *
     * Every field is matched by the word the script prints beside it, so the parse is a reading of
     * that program's answers rather than a second program asking git the same questions. A field the
     * script stopped printing answers as `null` or `-1` rather than as a zero, which is what keeps a
     * report that changed shape from reading as a tree with nothing wrong in it.
     *
     * @return array{
     *     branch: string,
     *     staged: int,
     *     index: string,
     *     listed: int,
     *     content: int,
     *     untracked: int,
     *     reports: int,
     *     stashes: int,
     *     hooks: string|null,
     *     changelog: array{cr: int, writable: bool}|null,
     *     files: array<string, string>
     * }
     */
    private static function parsed(string $output): array
    {
        $find = static function (string $pattern) use ($output): ?array {
            preg_match($pattern, $output, $match);

            return $match === [] ? null : $match;
        };

        $branch = $find('/^\s+branch:\s+(\S+)$/m');
        $index = $find('/^\s+index:\s+(\d+) staged; index (identical to HEAD|DIFFERS from HEAD)$/m');
        $worktree = $find('/^\s+worktree:\s+(\d+) listed\s+—\s+(\d+) with a real content diff, (\d+) untracked$/mu');
        $reports = $find('/^\s+reports with no content difference:\s+(\d+)/m');
        $stashes = $find('/^\s+stashes:\s+(\d+)$/m');
        $hooks = $find('/^\s+core\.hooksPath:\s+(\S+)/m');
        $changelog = $find('/^\s+CHANGELOG\.md:\s+(.*)$/m');

        $files = [];

        preg_match_all('/^\s+(\S+)\s+(present|MISSING)$/m', $output, $rows, PREG_SET_ORDER);

        foreach ($rows as $row) {
            $files[$row[1]] = $row[2];
        }

        return [
            'branch' => $branch[1] ?? '',
            'staged' => (int) ($index[1] ?? -1),
            'index' => $index[2] ?? '',
            'listed' => (int) ($worktree[1] ?? -1),
            'content' => (int) ($worktree[2] ?? -1),
            'untracked' => (int) ($worktree[3] ?? -1),
            'reports' => (int) ($reports[1] ?? -1),
            'stashes' => (int) ($stashes[1] ?? -1),
            'hooks' => $hooks === null ? null : $hooks[1],
            'changelog' => $changelog === null ? null : self::endings($changelog[1]),
            'files' => $files,
        ];
    }

    /**
     * The one line the script prints about `CHANGELOG.md`: the count it measured and what it says
     * `bin/commit.php` can do with it.
     *
     * @return array{cr: int, writable: bool}
     */
    private static function endings(string $line): array
    {
        preg_match('/(\d+) CR bytes?/', $line, $match);

        return [
            'cr' => (int) ($match[1] ?? -1),
            'writable' => str_contains($line, 'can write a bullet'),
        ];
    }

    /**
     * A section of the document by the heading it opens with, and the lines under it.
     *
     * The heading is matched by its prefix rather than whole, because §3 carries the date it was
     * measured on in the heading itself — which is the claim this reading is about, so a heading
     * matched exactly would be a heading the reading had already required to be right.
     *
     * @param  list<string>  $lines
     * @return array{at: int, heading: string, lines: list<string>}|null
     */
    private static function section(array $lines, string $heading): ?array
    {
        $at = null;
        $body = [];

        foreach ($lines as $index => $line) {
            if (str_starts_with($line, '## ')) {
                if ($at !== null) {
                    break;
                }

                if (str_starts_with($line, $heading)) {
                    $at = $index;
                }

                continue;
            }

            if ($at !== null) {
                $body[] = $line;
            }
        }

        return $at === null ? null : ['at' => $at, 'heading' => trim((string) $lines[$at]), 'lines' => $body];
    }

    /**
     * The first line of a section that writes a value the way the pattern reads it.
     *
     * @param  array{at: int, heading: string, lines: list<string>}|null  $section
     * @return array{value: string, line: int}|null
     */
    private static function claim(?array $section, string $pattern): ?array
    {
        if ($section === null) {
            return null;
        }

        foreach ($section['lines'] as $offset => $line) {
            if (preg_match($pattern, $line, $match) === 1) {
                return ['value' => $match[1], 'line' => $section['at'] + $offset + 2];
            }
        }

        return null;
    }

    /**
     * The gate the handoff says is wired, as the sentence beside the branch states it.
     *
     * @param  array{at: int, heading: string, lines: list<string>}|null  $section
     * @return array{value: string, line: int}|null
     */
    private static function hooks(?array $section): ?array
    {
        if ($section === null) {
            return null;
        }

        foreach ($section['lines'] as $offset => $line) {
            if (! str_contains($line, 'core.hooksPath')) {
                continue;
            }

            // The value is the one the sentence assigns to the setting — `core.hooksPath` is
            // `.githooks` — and not the hook file it goes on to name on the same line: that is a
            // different path, and reading it would report a disagreement with the script's own
            // answer where the document has made none.
            if (preg_match('/core\.hooksPath`?\s+is\s+`([^`]+)`/', $line, $match) !== 1) {
                continue;
            }

            return ['value' => $match[1], 'line' => $section['at'] + $offset + 2];
        }

        return null;
    }

    /**
     * The `CHANGELOG.md` bullet of §3.1: the ending count it states and what it says the writer can do
     * with the file.
     *
     * @param  array{at: int, heading: string, lines: list<string>}  $section
     * @return array{cr: int, writable: bool, line: int}|null
     */
    private static function changelog(array $section): ?array
    {
        foreach ($section['lines'] as $offset => $line) {
            if (! str_contains($line, self::CHANGELOG) || ! str_contains($line, 'CR bytes')) {
                continue;
            }

            preg_match('/(\d+) CR bytes?/', $line, $match);

            // The capability is a claim only where the bullet makes one: a bullet that states the
            // count and says nothing about the writer is held to the count alone, and one that says
            // the writer cannot use the file is held to that as well.
            $writable = null;

            if (str_contains($line, 'cannot') || str_contains($line, 'can only write')) {
                $writable = false;
            } elseif (str_contains($line, 'can write')) {
                $writable = true;
            }

            return [
                'cr' => (int) ($match[1] ?? -1),
                'writable' => $writable,
                'line' => $section['at'] + $offset + 2,
            ];
        }

        return null;
    }

    /**
     * The bullets that state a `HEAD` or how far ahead it is without saying when they measured it.
     *
     * A bullet is the unit rather than a line, because the sentence that carries the number is
     * wrapped: what has to say when it looked is the claim, and the claim is the item.
     *
     * @param  array{at: int, heading: string, lines: list<string>}  $state
     * @param  array{at: int, heading: string, lines: list<string>}|null  $package
     * @return list<array{line: int, claim: string}>
     */
    private static function undated(array $state, ?array $package): array
    {
        $found = [];

        foreach (self::bullets($state, $package) as $item) {
            if (preg_match(self::COUNTED, $item['text'], $match) !== 1) {
                continue;
            }

            if ($item['dated'] || preg_match(self::DATE, $item['text']) === 1) {
                continue;
            }

            $found[] = ['line' => $item['line'], 'claim' => str_replace('`', '', $match[0])];
        }

        return $found;
    }

    /**
     * The bullets of the two sections, each read whole with the line it opens on.
     *
     * An item is assembled before it is judged, because the number and the date that belongs with it
     * are rarely on the same line: the claim is the bullet, and the bullet is the sentence a reader
     * takes as one statement. A `###` subheading closes the bullet above it the way the next `##`
     * would, which is why the two are not one condition — a section can end a claim without ending.
     *
     * Each bullet carries the date of the heading it sits under, because that is the other place a
     * measurement may be written down: `### 3.2 The suite, measured on 2026-10-07, and again on
     * 2026-10-25` dates every
     * tally beneath it, and a tally under a heading that says nothing is the one this reading refuses.
     *
     * Written as a loop rather than a closure so that nothing here rewrites itself into an empty
     * function: Rector reads a variable captured by reference as one nothing writes, and the "dead"
     * block it then removes is this reading's whole body.
     *
     * @param  array{at: int, heading: string, lines: list<string>}  $state
     * @param  array{at: int, heading: string, lines: list<string>}|null  $package
     * @return list<array{line: int, text: string, dated: bool}>
     */
    private static function bullets(array $state, ?array $package): array
    {
        $bullets = [];

        foreach ([$package, $state] as $section) {
            if ($section === null) {
                continue;
            }

            $dated = preg_match(self::DATE, $section['heading']) === 1;
            $open = null;

            foreach ($section['lines'] as $offset => $line) {
                if (preg_match('/^#{3,4} /', $line) === 1) {
                    if ($open !== null) {
                        $bullets[] = $open;
                    }

                    $dated = preg_match(self::DATE, $line) === 1;
                    $open = null;

                    continue;
                }

                if (str_starts_with($line, '- ')) {
                    if ($open !== null) {
                        $bullets[] = $open;
                    }

                    $open = ['line' => $section['at'] + $offset + 2, 'text' => $line, 'dated' => $dated];

                    continue;
                }

                if ($open !== null) {
                    $open['text'] .= "\n".$line;
                }
            }

            if ($open !== null) {
                $bullets[] = $open;
            }
        }

        return $bullets;
    }

    /**
     * The document as lines, with the endings a checkout may have written taken out of the way.
     *
     * @return list<string>
     */
    private static function lines(string $markdown): array
    {
        return array_values(explode("\n", str_replace("\r\n", "\n", $markdown)));
    }

    /**
     * The tree, spelled the way every other reading here spells it.
     */
    private static function root(string $root): string
    {
        return rtrim(str_replace('\\', '/', $root), '/');
    }
}
