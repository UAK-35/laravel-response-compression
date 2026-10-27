<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

use RuntimeException;

/**
 * a copy of a tracked file among the files git does not have — the scratch a session leaves when it restores a file by copying, which the ignore rules keep out of `git status` and neither path reading is looking at
 *
 * WHY THIS EXISTS
 * ---------------
 * A guard is proven to fire by breaking the tree under it, and the file that was broken has to go
 * back — which is done by copying, never by `git checkout` or `git restore`, because the file on
 * disk is where a session's own uncommitted work lives and a checkout would take it away. So the
 * copy comes first: `build/head/<path>` for what `HEAD` holds, `build/authored/<path>` for what the
 * session had written, and those two directories are the workaround's whole footprint.
 *
 * Both are invisible. `build` is in `.gitignore`, so no entry of `git status` mentions either one,
 * and the two readings that own this ground are reading the complement of the set they are in:
 * `MachinePaths` and `RepoEscapes` read the files a commit *would* carry, and `Ignored` reads what
 * an ignore rule holds that records a machine somewhere else. A copy of this package's own file is
 * in neither answer, so it sits there quietly — and it is the shape that matters, because a copy is
 * a second version of a file: the one that gets edited by mistake, and the one a later `git add` of
 * the wrong path makes into a commit.
 *
 * WHAT COUNTS AS A COPY
 * ---------------------
 * The name, because the name is the half of a copy that says what it is. Two shapes, both read as
 * "this path is that tracked path, written somewhere else or with something on the end":
 *
 *   build/head/tests/Support/MachinePaths.php   a tracked path under a directory in front of it —
 *                                               the scratch directory mirrors the tree the copy
 *                                               was taken from, which is what a copy of a whole
 *                                               checkout looks like
 *   AGENTS.md.txt                               a tracked path and a mark — the copy left beside
 *   src/Config.php.orig                         the file it was taken from
 *
 * `.txt` is in the list of marks because it is the one that happened here: `AGENTS.md.txt` was a
 * copy of a file this package owns, and the `*.txt` rule that was holding it was narrowed for
 * exactly that reason. The rest are the words a copy is written with on the platforms this package
 * runs on, and none of them is matched on a file that is not a tracked path plus that mark.
 *
 * WHAT IT DOES NOT READ
 * ---------------------
 * Bytes. A copy whose name says nothing about the file it copies — the same bytes under some other
 * name — is not read here, because nothing in the tree says it is one: `build/FINAL.md` being byte
 * for byte the changelog is a fact about two files that no listing decides. And a file with no
 * directory of its own is not an original: `README.md` is a name most dependencies ship, so reading
 * it would report a vendor package's readme as a copy of this one. What is left is a path, and a
 * path is what a copy mirrors — so a dependency that happens to ship a path this package also has
 * is reported as one, which is the stated cost of a rule written around names: the sentence names
 * both paths, and the reader can see which tree the copy is in.
 *
 * WHY IT NAMES RATHER THAN FAILS
 * ------------------------------
 * A green run on a clone with no copy has to stay green, and the copies that are here were made by
 * this repository's own way of working rather than by a mistake in it. So the answer is names and
 * not findings, exactly as `Ignored` answers for the files an ignore rule holds: the caller decides
 * what the names mean, and `ScratchCopiesTest` writes them into the note channel a run prints, so a
 * session sees what is sitting in the tree without asking. The two readings are complements, and
 * they are the last pair on this ground: `Ignored` names what an ignore rule is holding that is
 * somebody else's, and this names what it is holding that is a copy of this package's own files.
 *
 * @guards-index reading
 */
final class ScratchCopies
{
    /**
     * The marks a copy is written with, longest first where one ends in another — a name is read
     * against each in turn and the first that leaves a tracked path is the answer.
     *
     * @var list<string>
     */
    private const array MARKS = ['.backup', '.copy', '.orig', '.save', '.tmp', '.bak', '.old', '.txt', '~'];

    /**
     * The files git does not have: every untracked file, and every one the rules hold.
     *
     * Returned rather than counted because a caller has to be able to see what the reading read: a
     * reading whose subject is "the files this tree does not carry" answers with nothing both on a
     * tree that has no copy in it and on one where the question was never put, and this is the half
     * that tells them apart.
     *
     * A union of the two listings beside this reading rather than a third question asked of git:
     * `MachinePaths::untracked()` and `Ignored::files()` are the two halves of "everything git does
     * not have", and asking for the whole of it afresh would be one more place for this repository
     * to decide what it carries. A copy hides in either half — an untracked file is in `git status`
     * and still unread by any guard, and an ignored one is in neither listing at all.
     *
     * @return list<string>
     *
     * @throws RuntimeException when git cannot be asked — a tree with no repository, or no git
     */
    public static function files(string $root): array
    {
        $files = [...MachinePaths::untracked($root), ...Ignored::files($root)];
        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /**
     * The copies among them, as copy => the tracked file it is named after.
     *
     * Keyed by the copy so a caller can check that every name came from the listing rather than from
     * somewhere else, and valued with the file it copies, which is the half a reader needs: a path
     * on its own does not say whether anything is missing because of it.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException when git cannot be asked, or answers with no tracked path at all
     */
    public static function copies(string $root): array
    {
        $originals = self::originals($root);
        $copies = [];

        foreach (self::files($root) as $file) {
            $original = self::origin($file, $originals);

            if ($original !== null) {
                $copies[$file] = $original;
            }
        }

        ksort($copies);

        return $copies;
    }

    /**
     * The tracked files a copy can be a copy of, as a set to look a path up in.
     *
     * Read as the tracked half of the commit scope: `MachinePaths::files()` is every tracked file
     * plus every untracked one the rules do not hold, and `untracked()` is that second half, so the
     * difference is the set a copy is named after. The file with no directory of its own is left
     * out, which is the boundary the header states: a name at the root of the tree — `README.md`,
     * `composer.json` — is a name dependencies have too, and a rule that read it would report every
     * package that ships one as a copy of this package's.
     *
     * @return array<string, true>
     *
     * @throws RuntimeException when git cannot be asked, or answers with nothing
     */
    private static function originals(string $root): array
    {
        $root = self::root($root);
        $originals = [];

        foreach (array_diff(MachinePaths::files($root), MachinePaths::untracked($root)) as $file) {
            if (str_contains($file, '/')) {
                $originals[$file] = true;
            }
        }

        // The answer a reading must not give twice: an empty set of originals reports no copies on
        // a tree that has none and on a tree whose tracked files were never read at all. There is
        // always at least one file a commit would carry, so this is a shape that cannot happen
        // rather than a case that can.
        if ($originals === []) {
            throw new RuntimeException(sprintf(
                'git answered with no tracked path holding a directory in %s, so a copy could not be told from any other file: nothing was read there.',
                $root,
            ));
        }

        return $originals;
    }

    /**
     * The tracked file a path is a copy of, or null where its name says nothing about one.
     *
     * The mark is asked first because it is the cheaper question: a copy beside its original leaves
     * one candidate, where the mirror below walks the path a segment at a time. The walk starts at
     * the first separator, so the last segment is never asked about on its own — that is the case
     * `originals()` leaves out, and asking it here would put the vendor readme back in.
     *
     * @param  array<string, true>  $originals
     */
    private static function origin(string $file, array $originals): ?string
    {
        foreach (self::MARKS as $mark) {
            if (! str_ends_with($file, $mark)) {
                continue;
            }

            $beside = substr($file, 0, strlen($file) - strlen($mark));

            if (isset($originals[$beside])) {
                return $beside;
            }
        }

        $segments = explode('/', $file);
        $counter = count($segments);

        for ($at = 1; $at < $counter; $at++) {
            $tail = implode('/', array_slice($segments, $at));

            if (isset($originals[$tail])) {
                return $tail;
            }
        }

        return null;
    }

    /** One path in the form two of them are compared in, whichever platform wrote them down. */
    private static function root(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
