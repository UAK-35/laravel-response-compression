---
name: package-commit
description: "Commit a named set of paths in this repository through the one guarded path, bin/commit.php: it writes the changelog bullet, runs the staged checks, and refuses an index that carries a file the commit was not told about. Commands - commit, commit-dry-run, list, staged, verify-setup. Invoke on staging a change, a request to commit this repository, or a request to sort a thread's work into commits with a changelog entry each."
license: "MIT"
---

# Package Commit — a named set, and only that set

Use this skill to commit a **named set of paths** in this repository: the group of files one reason
to have changed is about, committed together, with the changelog bullet written at the same time and
the staged checks run before the commit exists. It is the package's answer to `git commit -- <paths>`,
which cannot do the three things this repository actually needs.

## Why not just `git commit`

Three failures, all of them states this package has been in:

1. **The index is shared.** A colleague, an IDE, or an earlier command can have something staged, and
   `git commit` publishes the whole index. A commit that quietly carries a stranger's work is the
   failure a named set is supposed to prevent, so the first thing the tool does is read the index
   and **refuse** when it holds a path outside the set.
2. **The changelog is written later, from memory.** A bullet written with the change is a bullet that
   describes the change; one written at release time describes a memory of it.
3. **The gate is a hook, and a hook is clone-local.** `bin/checks.php --staged` is what
   `.githooks/pre-commit` runs, and a clone whose `core.hooksPath` was never set runs nothing. The
   tool runs the same command itself, so the verdict has a command behind it.

## Environment

Every machine-local path is a token; the values live in the gitignored `.agents/machine.local.json`
(section 3 of `AGENTS.md`). The commit path itself needs only `git` and a PHP interpreter, and which
PHP this checkout runs is decided in `.agents/php-bin.sh`:

```bash
sh .agents/php-bin.sh --show   # the interpreter this checkout runs, and the rung that answered
python .agents/render_local.py --value __PHP_EXE__   # spelled out, when a line needs it
```

`bin/commit.php` runs under whatever interpreter invoked it, and a direct run renders this machine's
PHP in the line itself rather than naming a `php` for the reader to guess at:

```bash
"$(python .agents/render_local.py --value __PHP_EXE__)" bin/commit.php --help
```

Three environment facts about this host and this repository, each of which cost a session real time:

- **Scratch stays inside this repository**, under a path `.gitignore` already covers — `build/` is
the one this checkout uses. Section 0 of `AGENTS.md` is a hard rule rather than a preference: read
and write inside the root and nowhere else, so a temp directory outside it is out of bounds even
for a file nobody will ever commit. A generator, a snapshot of the authored state and a saved
`git show` all belong beside the work they are about.
- **The two mandated shells are not the same shell.** PowerShell 7 on Windows and `bash` on Linux,
so a command that leans on `sed`, `awk` or another POSIX tool is a command that exists on half the  machines. `rg` is the search utility section 2 names, `git grep` where a shell has no `rg`, and
  statements are separated with `;` or `&&` rather than a literal newline — and a marker search that
  comes back empty is a claim about the quoting before it is a claim about the file, because an
  apostrophe inside the pattern is the usual cause of a bullet that looks absent and is not.
- **A staged check that spawns a shell needs `sh` resolvable.** On a Windows host where `sh` is
Git's and is not on `PATH`, the check fails with an empty answer that looks like a real defect.
Append Git's `usr/bin` to `PATH` **for that run** — append, never prepend, because putting MSYS git
ahead of Windows git breaks every git-backed fixture — and make no file and no git config carry the
workaround.

## The guard

`bin/commit.php` reads `git diff --cached --name-only` **before it stages anything** and refuses when
the index carries a path the named set does not name:

```
refusing: the index carries 1 path(s) this commit was not told about:
  events/scratch.php
stage the named set alone, or name these paths too.
```

A refusal writes nothing: the index keeps the state it was found in. The check is asked again after
staging, because a rename pairs its two halves and the index can gain a path the set did not name —
a guard is only worth having if it is asked of what will actually be committed.

## The changelog bullet

The bullet is written with the change, under `## Unreleased`, in the section the change belongs to —
`--type` is `added`, `changed` (the default) or `fixed`, and it picks the `### Added`, `### Changed`
or `### Fixed` section. A section that is not there yet is created where it belongs; a changelog
with no `## Unreleased` heading is refused rather than edited somewhere else. `--no-changelog` is for
the change that genuinely has nothing to say here.

Two things about bullets that a session learned by being wrong about them:

- **A section may exist only once.** A second `### Fixed` heading is not a second list: `bin/release.php`'s
  `sectionBody()` reads the first body of a section, so the notes under a duplicate heading are notes
  no release will publish, and a release cut later silently drops them. Consolidate the two before
  committing — and count a heading by its own line (`^### Fixed$`) rather than by the string, because
  a bullet that explains this rule names the heading in prose and a substring count sees it there too.
- **The bullet is joined with the changelog's own line endings**, so a file that arrived CRLF gains a
  CRLF bullet rather than a lone `\n` inside it. Nothing here needs normalising by hand; what does
  need the check is the *staged file* — `.gitattributes` pins the text files to LF, so Pint reads a
  CRLF staging as a `line_ending` finding, which is a fix to make before the commit rather than after it.

## Commands

- `commit <paths...>`: write the bullet, run the staged checks, and commit exactly these paths.
- `commit-dry-run <paths...>`: run the guard, print the plan and the bullet's section, and write
  nothing — no changelog edit, no staging, no commit.
- `list <paths...>`: print the resolved paths, one per line, and write nothing.
- `staged`: run `bin/checks.php --staged` against the index as it stands, without committing.
- `verify-setup`: run `python .agents/verify.py`, adding `--fix` to repair what a generator owns.

### commit

```bash
"$(python .agents/render_local.py --value __PHP_EXE__)" bin/commit.php \
    -m "fix: the thing that was wrong" \
    --changelog "**The thing is fixed.** The sentence says what changed, not that something did." \
    --type fixed \
    -- src/Support/Config.php \
       config/response-compression.php
```

- `-m <subject>` is the commit subject; `-F <file>` reads the whole message from a file, for a body
  that needs blank lines and trailers.
- `--paths-from-file <file>` adds one path per line, blank lines and `#` comments skipped.
- The paths come after `--`. A path that is absolute, climbs with `..`, or resolves outside this
  repository is refused rather than normalised.
- `--skip-checks` skips `bin/checks.php --staged`; it is for a run that has just done it by hand, not
  for making a red commit go through.

Exit status: `0` committed, `1` nothing was committed, `2` the command line was wrong.

What the tool guarantees:

- It stages the **working-tree content** of every named path, so a file staged and then written again
  commits what is on disk rather than the older copy that was staged.
- `CHANGELOG.md` is part of the set whenever a bullet is written, so it never has to be remembered.
- The staged checks run **before** the commit, and a failure in them leaves the tree uncommitted.
- A refusal changes nothing: the index keeps the state it was found in.

### commit-dry-run / list

Run `commit-dry-run` before a commit whose set you are unsure of. `list` is for the case where the
interesting part is which paths the tool resolved, including the changelog it would add.

```bash
"$(python .agents/render_local.py --value __PHP_EXE__)" bin/commit.php \
    -m "fix: a thing" --changelog "A thing." --skip-checks --dry-run -- src/Thing.php
"$(python .agents/render_local.py --value __PHP_EXE__)" bin/commit.php --list --no-changelog -- src
```

### staged

The same command the pre-commit hook runs, on the index as it stands — for diagnosing a failure
rather than for the normal path:

```bash
"$(python .agents/render_local.py --value __PHP_EXE__)" bin/checks.php --staged
```

Which tests those are is `tests/Support/TestMap.php`: a document runs the docs-link and config-claim
tests, and a source file runs the suite that covers it. `gate-local` in the `package-dev` skill runs
the whole gate when the question is broader than one commit.

### verify-setup

```bash
python .agents/verify.py --fix     # repair what a generator owns, then re-verify
python .agents/verify.py           # detect only; exits 1 when something is inconsistent
```

## Sorting a thread's work into commits

A request arrives as a sentence — *"sort out the work done in this thread into separate groups and
commit each group with a changelog entry"* — and what it asks for is a judgement: nothing is a group
until it has **one** reason to have changed.

1. **Inventory the work.** `git status --short`, `git diff --stat` and `git log --oneline -15` are
   the raw material, and the recent log is what the subjects and the granularity have to fit into.
2. **Read each change by file**, one `git diff -- <path>` at a time, before grouping any of it: what
   a change *is* decides which group it belongs to, and a file name does not say.
3. **Group by why, not by file.** A fix and the note explaining it are one group where the note
   cannot be written without the fix, and two where the note is about something else. A single file
   carrying two groups' text is not one commit: it is two commits that each carry the file at the
   state belonging to that group. Note every such file as you find it — they are the ones step 6 has
   to materialise more than once.
4. **Read what the changelog already says, and what `bin/commit.php` actually does.** Which bullets
   under `## Unreleased` are already authored, whether a section heading is duplicated, and which
   artefacts carry no bullet at all. Reading the tool's own header is cheap and it is where the
   refusal conditions, the staging behaviour and the exit statuses are written down.
5. **Ask the two judgements that cannot be inferred**: how many commits (one per concern, or a
   coarser grouping), and what to do with anything the grouping cannot decide — a duplicated section,
   or an artefact with no bullet, which is either a commit of its own or a bullet to be written.
6. **Snapshot the authored state, then build each group's tree.** A file serving two concerns is
   committed twice, so the authored copy and the `HEAD` copy are both saved first (`Copy-Item`, and
   `git show HEAD:<path>` for the other end) and each commit materialises the intermediate state that
   belongs to it. The changelog is the same shape: one state per commit, each a superset of the one
   before it, and each carrying only the bullets earned by the commits up to it. Build each state
   from the file rather than from a template — the blank line between `## Unreleased` and the first
   section is part of the state, and a generator that starts at the first heading silently drops it,
   which is a diff in every one of the intermediate commits.
7. **Unstage anything already in the index** — `git restore --staged -- <paths>` is an index write,
   never a revert, and it is not optional: the tool reads the index before it stages anything and
   refuses a set the index is not already inside.
8. **Commit one group at a time**, oldest concern first, each through `commit` with a subject that
   says what changed and why, and a body that carries the evidence: what was measured, which command
   produced it, and what was deliberately left alone. When the bullet was already authored while the
   work was done, pass `--no-changelog`, **name `CHANGELOG.md` in the set by hand**, and let `-F`
   carry the message — otherwise the tool writes a second bullet for the same change. The set's own
   paths still have to be the change: a changelog entry records one rather than being one, so a
   group whose paths already hold what `HEAD` holds is refused (`carries no change of its own`)
   instead of being committed for its entry. That refusal is the answer to the eight-group shape —
   the work is already in the history, and there is no commit here to cut.
9. **Read the set back**, because the work is not finished when the last commit exists:
   * each commit carries exactly its own paths — `git show --stat <commit>`;
   * each changelog entry sits under the commit whose change it describes — `git show <commit>:CHANGELOG.md`;
   * the last commit's changelog equals the state the thread intended, and every earlier one is a
     subset of it, so no commit publishes a note for work it does not contain;
   * what the thread did not mean to commit is still uncommitted, and the tree is otherwise clean.
10. **Finish with the layer, not with the last commit**: `python .agents/verify.py --layer` and
    `bin/index.php --check`, and remove any throwaway worktree (`git worktree remove --force <path>`,
    then `git worktree prune`).

### When the staged checks fail

A failed `bin/checks.php --staged` is the tool working, and it leaves the tree uncommitted — so the
finding is a thing to understand, not an obstacle. Sort it in this order:

1. **Decide whether it is yours.** Reproduce it at the commit the thread started from
   (`git worktree add <dir> <commit>`, then run the same check there). A pre-existing failure is
   named as pre-existing in the commit message and the handoff rather than repaired inside this
   commit's set.
2. **Fix what is yours with the prescribed tool**, never by hand: `bin/tool.php pint <paths>`,
   `bin/tool.php rector`, `bin/tool.php phpstan`. A style finding is a real defect in the change.
3. **Never widen the set to make a finding go away, and never reach for `--skip-checks`.** The tool
   exists because a commit that skips its checks is the failure this skill is for.

Leave the removal of the throwaway worktree to step 10; a worktree left behind is a second checkout
of this repository that nothing else knows about.

## Guardrails

- **Never `git add -A` or another broad stage.** Stage the named set only, through the tool.
- **A commit goes through `bin/commit.php`.** It is the only path that writes the bullet, runs the
  staged checks and refuses a file it was not told about.
- **Do not commit unless asked.** Committing is a user decision, not a step of finishing a task.
- **Never discard, overwrite, stash or commit a change you did not make.** Another agent, an IDE, or
  the user may be editing the same tree.
- **Never push from here.** Pushing is `package-push`'s job.
- **Keep a red commit out of the history.** A failed staged check is a reason to fix the change, not
  to reach for `--skip-checks`, and not a reason to add a path to the set until the finding is out of
  the way.
- **Leave a concurrent writer's files alone.** The working tree is shared with other agents, the user
  and an IDE: a file that changed while the thread was reading it is not part of any group, and a
  commit that carries it is the failure the named set exists to prevent, arriving by a different door.
- `python .agents/verify.py` green before presenting work as finished.

## Usage

- `@package-commit commit src/Support/Config.php`
- `@package-commit commit-dry-run src/Middleware/CompressResponse.php`
- `@package-commit list src`
- `@package-commit staged`
- `@package-commit verify-setup`
- *sort the work done in this thread into commits, one changelog entry each*
