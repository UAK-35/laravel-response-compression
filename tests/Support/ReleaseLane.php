<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * The branch releases are cut from, read out of the one file that states it, and held in every other place this repository states it again.
 *
 * WHY THIS EXISTS
 * ---------------
 * The lane was one word written down in four places at once: as a command — the `release` entry in
 * `.agents/toolchain.json` — and three times as prose about it: the comment on the workflow's `push`
 * trigger, and every release or push a reader copies out of `RELEASING.md` and `PUSHING.md`. Three of
 * those had drifted to `main`, and the drift was invisible because `main` is the branch the release
 * program *defaults* to: the short version, the by-hand push and the prerelease examples all still
 * read as sensible commands, and every one of them stops at the branch rail when it is run. They were
 * found by reading the runbooks and fixed by hand, which is the kind of fix that comes back — the next
 * runbook is written the same obvious way, with the default sitting there to be copied.
 *
 * So the lane is not read out of a command any more, where it was one word inside a longer line: it is
 * read out of `.release-lane`, the file that states it and the file `bin/release.php` reads as the
 * branch it releases from. That is what made the copies go away rather than need watching — nothing
 * has to pass the branch any more, so the flags that used to drift are not there to. What is left to
 * hold is the three statements that remain: a `--branch=` a runbook mentions, a `git push` to this
 * repository's own remote, and the workflow's own literal trigger whatever cannot read the file.
 *
 * A trigger is the one statement that cannot read it: GitHub evaluates `on.push.branches` before any
 * step runs, so the ref list there has to be literal, and the same workflow reads `.release-lane` in a
 * step to require that list to gate it. Here the list is read and held to the lane directly, so a lane
 * moved to a branch no run ever sees fails in the suite as well as in CI.
 *
 * Everything else is left alone, and it has to be — the runbooks explain what the default is, and a
 * reading that could not tell that sentence from a command would refuse the explanation the fix
 * depends on. What it deliberately does not read is a `git push` to some other remote: the shape being
 * read is a command, and the sentences `git push` appears in are not commands — `a bare \`git push\`
 * had nothing to do` has two words after it and neither is a branch. A runbook that grows a second
 * remote is a decision to make here rather than a silence.
 *
 * A file it cannot read, a `.release-lane` that names no branch, and a workflow with no `push` trigger
 * all throw rather than reporting agreement with something this reading never saw: a guard that found
 * nothing and a guard that read nothing report the same clean answer, and only one of them is working.
 *
 * @guards-index reading
 */
final class ReleaseLane
{
    /**
     * Where the lane is stated: the one file that holds it, which `bin/release.php` reads as the branch
     * a release is cut from and which the workflow reads to check that branch is one it gates.
     */
    public const string LANE = '.release-lane';

    /**
     * Where a command names a branch and it has to be the lane: the two runbooks a release and a push
     * are written out in, which is where the drift back to `main` actually happened.
     */
    public const array SOURCES = [
        'RELEASING.md',
        'PUSHING.md',
    ];

    /**
     * The trigger that has to gate the lane, and the one statement of it that cannot read the file:
     * `on.push.branches` is evaluated before any step runs, so that ref list is literal, and this is
     * the file it is held against.
     */
    public const string WORKFLOW = '.github/workflows/main.yml';

    /**
     * The remote a push in these runbooks goes to — the one this repository publishes to, which
     * `PUSHING.md` states and `bin/release.php` assumes.
     *
     * Named rather than matched loosely, because the alternative is reading English: a bare
     * `git push` in a sentence has two words after it, and a rule that took the second one for a
     * branch would report `nothing` as a lane.
     */
    public const string REMOTE = 'origin';

    /**
     * The branch releases are cut from: the one line `.release-lane` holds.
     *
     * @throws RuntimeException when that file cannot be read, or names no branch at all
     */
    public static function lane(string $root): string
    {
        $lane = trim(self::readable($root, self::LANE));

        if (preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*$#', $lane) !== 1) {
            throw new RuntimeException(
                self::LANE.' names no branch, so there is no lane for the runbooks to be held to: a release names the branch it is cut from, and this file is where that branch is written.',
            );
        }

        return $lane;
    }

    /**
     * Every place one of the sources names a branch that is not the lane, as a line to read.
     *
     * @return list<string>
     *
     * @throws RuntimeException when a source cannot be read, or the lane cannot be decided
     */
    public static function drift(string $root): array
    {
        $lane = self::lane($root);
        $findings = [];

        foreach (self::SOURCES as $source) {
            foreach (self::named(self::readable($root, $source)) as $named) {
                if ($named['branch'] !== $lane) {
                    $findings[] = sprintf(
                        '%s:%d  %s names `%s`, and releases are cut from `%s`',
                        $source,
                        $named['line'],
                        $named['where'],
                        $named['branch'],
                        $lane,
                    );
                }
            }
        }

        $gated = self::gated(self::readable($root, self::WORKFLOW));

        if (! in_array($lane, $gated['branches'], true)) {
            $findings[] = sprintf(
                '%s:%d  the push trigger gates %s, and releases are cut from `%s`',
                self::WORKFLOW,
                $gated['line'],
                $gated['branches'] === [] ? 'no branch' : '`'.implode('`, `', $gated['branches']).'`',
                $lane,
            );
        }

        return $findings;
    }

    /**
     * The branches the workflow's `push` trigger gates, and the line that lists them.
     *
     * Read from the `push` block rather than from the first `branches:` in the file, because the
     * `pull_request` trigger below it lists `main` alone: a reading that took the first one it found
     * would be answering about the wrong trigger the moment the two are written in the other order, and
     * would call a lane it never looked at gated.
     *
     * @return array{line: int, branches: list<string>}
     *
     * @throws RuntimeException when the trigger, or its branch list, cannot be read
     */
    public static function gated(string $workflow): array
    {
        $inside = false;

        foreach (explode("\n", str_replace("\r\n", "\n", $workflow)) as $index => $line) {
            if ($line === '  push:') {
                $inside = true;

                continue;
            }

            // The next sibling of `push` ends its block. A list found after it is the `pull_request`
            // trigger's, and taking it would be answering about a trigger this was not asked about.
            if ($inside && preg_match('#^  [a-z_]+:#', $line) === 1) {
                break;
            }

            if ($inside && preg_match('#^\s*branches:\s*\[(.*)\]#', $line, $match) === 1) {
                preg_match_all('#"([^"]*)"|\'([^\']*)\'#', $match[1], $names, PREG_SET_ORDER);

                return [
                    'line' => $index + 1,
                    'branches' => array_map(
                        static fn (array $one): string => $one[1] !== '' ? $one[1] : $one[2],
                        $names,
                    ),
                ];
            }
        }

        throw new RuntimeException(
            self::WORKFLOW.' has no `push` trigger with a branch list, so which branches a release is built on cannot be read.',
        );
    }

    /**
     * The branches one document names in a command, with the line and the shape that named them.
     *
     * A reading of the text rather than of a file, the way `Docs::linksIn()` is: the guard holds
     * whole documents, and a test that wants to know how a sentence is told from a command should
     * not have to write one to disk to ask.
     *
     * @return list<array{line: int, where: string, branch: string}>
     */
    public static function named(string $markdown): array
    {
        $named = [];

        foreach (explode("\n", str_replace("\r\n", "\n", $markdown)) as $index => $line) {
            if (preg_match_all('#--branch=([A-Za-z0-9][A-Za-z0-9._/-]*)#', $line, $matches) > 0) {
                foreach ($matches[1] as $branch) {
                    $named[] = ['line' => $index + 1, 'where' => '--branch=', 'branch' => $branch];
                }
            }

            // A push command rather than a sentence that mentions one: the remote has to be the one
            // this repository pushes to, which is what tells `git push origin main` from the words
            // `git push` sitting between two backticks.
            if (preg_match('#\bgit push +'.preg_quote(self::REMOTE, '#').' +([A-Za-z0-9][A-Za-z0-9._/-]*)#', $line, $match) === 1) {
                $named[] = ['line' => $index + 1, 'where' => 'git push '.self::REMOTE, 'branch' => $match[1]];
            }
        }

        return $named;
    }

    /**
     * One of the files this reading is about, read whole.
     *
     * @throws RuntimeException when it is not there, because a walk over a document that was never
     *                          read agrees with every claim made about it
     */
    private static function readable(string $root, string $source): string
    {
        $path = rtrim(str_replace('\\', '/', $root), '/').'/'.$source;
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException(
                $source.' could not be read, so the branches in it cannot be held to the lane this repository releases from.',
            );
        }

        return $contents;
    }
}
