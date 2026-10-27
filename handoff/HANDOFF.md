# Handoff — the package thread, for a new Freebuff project

This is the whole context of the **predecessor thread**, written on 2026-10-07 so a new Freebuff
project opened on **this package** can continue the work without re-deriving any of it. Nothing
moved: the checkout is where it always was, and only the Freebuff project entry is new. Every path
below is relative to the **package root** — the directory holding `AGENTS.md`, `bin/` and this
`handoff/` folder — so the document reads the same from any machine and any checkout position.

**Open the new Freebuff project on the package root.** That is the directory `AGENTS.md` and
`bin/checks.php` are in. A Laravel checkout and a second package sit beside it in the same workspace
and are different repositories with work of their own in them; nothing in this document is about
those, and this document does not name them, because a path that reaches out of this repository is
refused by this package's own guards (§8).

**You are already reading this**, so discovery worked: `AGENTS.md` §3 names this folder in its load
order, which is the file a Freebuff project opened on this root reads first. (That pointer is one
line, and it can go when this folder does.)

**Then run these two**, before touching anything. The first answers "what is the state right now",
the second rebuilds the one finding that looks like a bug and is not:

```bash
bash handoff/state.sh          # read-only: this repository, with a phantom count
bash handoff/phantom-eol.sh    # rebuilds the stat-cache phantom in a scratch dir, and measures the cures
```

Both write only inside a scratch directory under the machine's temp area (the second deletes it on
exit). Neither writes to the repository, stages anything or changes a config. Then work through
section 7 — and read §3 before you trust the suite's colour.

---

## 0. Where things are

| What | Path (from the package root) |
| --- | --- |
| this document and its two scripts | `handoff/` — untracked; commit it, move it or delete it, nothing depends on it |
| the canonical rules | `AGENTS.md` (sections 0 to 14, and the source of every rule this document does not restate) |
| the gate | `bin/checks.php` — `composer checks`, `composer checks -- --require-all` |
| the only permitted commit path | `bin/commit.php` (`AGENTS.md` §11) |
| the guard index, rendered | `docs/guards.md` (`bin/index.php`, `bin/index.php --check`) |

The three files in `handoff/` are **not** part of `docs/`: the design records under `docs/` are held
to their own guards — every one of them linked from the README, and their count stated in
`tests/Support/Docs.php` — so a note moved into `docs/` is a note that has to be linked and counted.
Leaving it here is deliberate.

---

## 1. The package, as it stands

A standalone Composer package (`src/`, `config/response-compression.php`, a Pest suite in
`tests/Unit/` with the readings in `tests/Support/`, the records in `docs/`), with an agentic layer
of its own and no dependency on anything beside it.

- branch `development`. `HEAD` and how far ahead of `origin/development` it is move with every
  commit, so `bash handoff/state.sh` is where they are read rather than here: measured 2026-10-25,
  this was `ccdb8b5`, **90 ahead and 0 behind**, with nothing pushed;
- gate wired: `core.hooksPath` is `.githooks`, so the hook at `.githooks/pre-commit` runs on every
  commit here;
- its own tooling, all run from the package root: `bin/checks.php` (the gate), `bin/commit.php`
  (the guarded commit path), `bin/index.php` (renders and checks `docs/guards.md`),
  `bin/coverage.php` (the floor), `bin/release.php`, `bin/push.php`, `bin/tool.php` with
  `bin/tool-paths.php` (how a tool is run), and in `.agents/`: `verify.py` (the agentic layer and the
  machine probes), `render_local.py` (renders every settings file from one machine file),
  `php-bin.sh` (which interpreter this checkout runs).

---

## 2. Environment

- **PHP is not on `PATH`** in this checkout's shell. Resolve it rather than hardcoding it:
  `python .agents/render_local.py --value __PHP_EXE__` prints this machine's binary, and
  `sh .agents/php-bin.sh --show` says which rung answered and the version `composer.json` requires.
  A command that runs PHP is written with the rendering in the line itself — the gate's own table in
  `AGENTS.md` §13 shows the form.
- **Machine settings, one file, gitignored:** `.agents/machine.local.json` — **5,642 bytes and 15
  sections on this machine**, where the predecessor measured 293 bytes and four keys. The five the
  renderer reads behind a token are `__PHP_EXE__`, `__PHP_DIR__`, `__PWSH_EXE__`,
  `__PACKAGE_DIR__` and `__SYSTEM_TEMP_FOLDER__`; the sections beside them are this machine's own record — the interpreter's
  version and extensions, what this checkout is at, what an install receives — and
  `python .agents/render_local.py --init` keeps every one of them while it re-names the machine,
  where it used to write its three answers over the file and take the record with them.
  `python .agents/render_local.py --check` reports an unresolved token and a rendered settings file
  that has drifted. There is **no `--ready` flag**, which an earlier version of this bullet claimed:
  a reader who pastes one gets a usage error rather than a verification. Never commit this file and
  never paste it.
- **This package has no `.env`.** That JSON is its only machine-local file; there is no environment
  file to keep in step with anything.
- **The shell and the toolchain are `AGENTS.md` §2 and §13** — the short version: on Windows,
  everything that matters goes through PowerShell 7 by the full path its own rule spells out, never
  `cmd.exe` and never MSYS; the commands and the pins are the tables in §13, rendered from
  `.agents/toolchain.json`, so do not restate them here or anywhere else.
- **A tool is run through `bin/tool.php`**, never by a `vendor/bin` path written down again, so the
  version that runs is the one `bin/tool-paths.php` pins.

---

## 3. State, as measured on 2026-10-25

`bash handoff/state.sh` prints all of the below as it is at the moment it runs, so it outranks this
section the moment the two disagree.

**This half of the document was rewritten by the session that opened the new project on this
package.** It read this file, ran the two scripts, and then did what §7 item 1 asked for — so §3.2
and §7 have both moved since the predecessor wrote them, and §5 is no longer the empty section it
was. Everything the predecessor measured is kept where it is still the useful thing to know: what
has changed is marked as changed, and what has not is left alone.

### 3.1 The package itself

- **nothing staged:** `git diff --cached --name-only` is empty, `git diff --cached --quiet` exits 0,
  and the index is identical to `HEAD`.
- **the modified list is 8 as of 2026-10-25** — measured before this document was saved — 7 with a
  real content diff and 1 untracked, 0 reports: the note channel's scope
  (`tests/Support/Notes.php`, `Handoff.php`, `PhpBinary.php`, `tests/Unit/Support/NotesTest.php`,
  `IgnoredTest.php`, `CHANGELOG.md`), this document, and the untracked
  `docs/investigations/why-the-staged-check-runs-silent.md` the same thread filed. **None of it is
  §4's phantom:** `state.sh` reports 0 files holding no content difference, which is the reading
  that tells a real edit from a timestamp.
- **what the predecessor saw was not a phantom either.** Its snapshot reported **21 listed — 12 with
  a real content diff and 9 untracked**, with **0 reports holding no content difference**. Eleven of
  the twelve are this package's own work (§5) and the twelfth is `AGENTS.md`, whose load order
  somebody's setup edit added a fifth entry to — pointing at this folder, which is why this document
  was read at all. The predecessor's history is kept below, because it is still the shape this list
  takes when nothing at all is wrong:
- **when the predecessor looked, the list was empty.** The thread watched 29 paths reported as modified,
  then 28, all of them holding nothing (§4), and something outside the thread cleared the rest at
  2026-10-07 00:50:58 — the index file's own timestamp. The working copies of the reported files now
  carry CRLF and their sizes match what the index had recorded, which is the signature of a
  `git restore` / `git checkout` of the worktree rather than of `git add --renormalize`. Nothing was
  lost by it: every one of those paths held no content (`git diff --name-only` was empty for all of
  them), the index still equals `HEAD`, and nothing is staged. The one path the thread cleared itself
  was `.editorconfig`, with `git add -f` — 29 → 28 — and that is the only git write the thread made
  outside its commits. What that rewrite *cost* is §3.2.
- no stashes, one worktree.
- `hs_err_pid6024.log` at the root is a JVM crash log nothing here explains. `.gitignore` has
  `/hs_err_pid*.log`, so it is on disk and invisible to git; it is named here only so it is not
  mistaken for your mess.
- **The three `.txt` leavings are decided.** `AGENTS.md.txt` was an `AGENTS.md` from before the
  canonical rules were rewritten (4,884 bytes against the current 37,195) and
  `src/Middleware/CompressResponse.php.txt` an older middleware (11,196 against 13,666); both were
  copies of files the repository already tracks, superseded by them, in no commit and in no object in
  the database, so they were **deleted**. `tests/Unit/Middleware/test.txt` is four bytes, `test`,
  tracked since `87339d8` and read by `CompressResponseTest` as its `BinaryFileResponse` fixture, so
  it was **kept**. None was **adopted**: a copy that is not the file it copies is a second source of
  truth, and neither copy held anything the tracked file does not.
- `CHANGELOG.md` is **LF on disk** (0 CR bytes), and `bin/commit.php` can write a bullet into either
  ending: it joins the bullet with the ending the heading it matched carries, so a file that arrives
  CRLF gains a CRLF bullet (§7 item 3, done 2026-10-11).

### 3.2 The suite, measured on 2026-10-07, and again on 2026-10-25

**Green, measured on 2026-10-25, and the two runs are two different questions.** With one interpreter
and no pool the whole suite read **484 passed (1,564 assertions, 781s)** on the tree before the
mutation harness gained its fourth break, and nothing failed — against the **4 failed, 423 passed**
recorded below. The same tree, now carrying that break, under the gate's own `tests` check — the pool
on 16 processes, with the files it cannot start asked again alone — reads **39 failed, 446 passed
(1,442 assertions, 331.4s)**: the 39 are five files the pool could not start rather than 39
assertions, each passing when asked alone, and the serial-equivalent total is **485 passed**. The
pool's count is one failure said once per worker that started a `git` child:
`proc_open(): CreateProcess failed: The parameter is incorrect`, raised at
`tests/Support/ReleaseRepo.php:793`, and not one of them is an assertion. Those are the files that
pass when they are asked at all, which is what the serial run is — so the pool's tally is a reading
of the machine and the serial run's duration is a reading of one core. Neither is the suite's colour
and neither is the suite's cost; taken together they are both.

**Why the duration moved, in the terms the two figures can bear.** The 201s and 651.7s this section
recorded a day apart were the same tree on the same 16 workers, which is why the sentence below
attributes the difference to the machine's load rather than to the suite, and that attribution is
right: no test arrived between those two runs to pay for a 3×. What has arrived since is real and
smaller — 484 tests against 421, 1,564 assertions against 1,311 — and it is slower per test than
the count suggests, because the additions are the kind that spawn children: a mutation hold plants a
break in a copy of this checkout and runs its reading over it, the inversions control runs a guard's
own file in another Pest process (its child run is the one that came back `1 failed, 116 passed`
under the pool and `1 passed` alone), and the `bin/` programs build release fixtures with real
`git`. A pool splits its work by *file*, so a file that spawns a dozen children is one worker's
whole cost with nothing able to take half of it, and a serial run adds every file's own time
up with no overlap at all. That is the whole of the arithmetic: 201s is the pool on a quiet
machine, 651.7s the pool on a busy one, 304s and 331.4s the two pool runs recorded above, 781s the
serial run — and the figure a reader should compare with 201s is the pool's, not 781s.

**Fixed, then half-cleared.** After the §7 item 1 fix a full run read **6 failed, 421 passed
(1,311 assertions, 201s, 16 parallel workers)** — against the predecessor's **15 failed, 406
passed**, every one of which is gone. After the two Windows scripts were adopted into `bin/`
(§3.3 item 3) a second full run reads **4 failed, 423 passed (1,313 assertions, 651.7s)** on the
same 16 workers, the duration moving with the machine's load rather than with the suite. What
follows is the predecessor's diagnosis, which was right in every particular, and then what the fix
turned out to cost and what is left red.

The predecessor's run, and the throw behind all fifteen of its failures:

```
tests/Support/CoverageEnvironment.php carries no `@guards-index` line, so it says nothing about
which list it is in.
```

That file **does** carry the line — `@guards-index reading`, line 41 of its docblock — and the blob
at `HEAD` reads fine. The guard cannot read it because the file's **working copy is CRLF** and the
declaration pattern is anchored with `$` under `/m` — `GuardIndex.php:138` is
`/^[\s\/*|#<!-]*@guards-index\s+(\S+)(?:[ \t]+(.+?))?[ \t]*$/m` — and `$` does not match in front of
`\r\n`. Measured directly, that pattern against both texts:

| Text | CR bytes | pattern matches |
| --- | --- | --- |
| the worktree file, as it is on disk | 302 | no |
| that path's blob at `HEAD` | 0 | yes → `reading` |

So the guard reads the *checked-out bytes* while `HEAD` holds LF, and this machine's checkout writes
CRLF (`* text=auto` in `.gitattributes` plus `core.autocrlf=true` in the global config — §4).
**29 tracked files carry CR in this worktree**; the readings among them are
`tests/Support/CoverageEnvironment.php`, `tests/Support/IndexNumbers.php`,
`tests/Support/LogMessages.php` and `tests/Support/MachinePaths.php`, and the first of those
alphabetically is enough to stop the reading. The failures are `tests/Unit/Docs/GuardIndexTest` (2),
`tests/Unit/Docs/IndexNumbersTest` (8), `tests/Unit/Support/MutationHarness/GuardIndexTest` (3),
`…/IndexNumbersTest` (1) and `…/Controls/InversionsTest` (1) — all five files fail on that one
throw. `bin/index.php --check` exits 1 for the same reason, with the same message.

This was a defect in the guard rather than in the tree: on a Windows checkout of this repository the
guard index could not read itself, and nothing in `git status` said so — the CRLF files are exactly
the ones the stat cache reports as unmodified (§4). **The fix was the one line the predecessor
named**, in two files: the pattern's line-end class is `[ \t\r]*$` in `tests/Support/GuardIndex.php`
and in `tests/Support/IndexNumbers.php`, which had the same shape and was safe only because
`bin/checks.php` happens to be LF here. `bin/index.php --check` exits **0** where it exited 1, and
`tests/Unit/Docs/GuardIndexTest.php` is **13 passed** where it was 2 failed.

Two further things came out of it, neither of which the predecessor's §3.2 knew about:

- **a second red was hiding behind the first.** The throw aborted the whole index reading before any
  later step could run, and behind it the number-claims reading was red on this document itself: §8
  below tells a writer to keep the gate's size out of prose, and the sentence that said so was
  written in the shape it warns about, so `IndexNumbers` read the warning as the claim. The sentence
  is reworded, and `tests/Unit/Docs/IndexNumbersTest.php` is **9 passed** where it was 8 failed.
- **the page's prose is not rendered.** A file added to `tests/Support/` and a guard beside it moved
  three numbers stated by hand in `docs/guards.md` — the guard count, the reading count and the file
  count — which `bin/index.php` rewrites tables for and nothing rewrites prose for. They were moved
  by hand; `IndexNumbers` is the reading that says so when they are not.

**What is left red is not this package's code.** All four failures are one path reading and the two
things that read through it, and all four are caused by the one outsider left in §3.3 — the sample
machine file — while the escape reading is green for the first time. **That cause is gone
2026-10-27**: the file was deleted (§7 item 2), so the rows below describe the tree this section was
measured on rather than the one a reader has now:

| Failure | Caused by |
| --- | --- |
| `tests/Unit/Support/MachinePathsTest.php` (1 failed) | the drive paths in `.agents/sample.machine.local.json`, and nothing else — the two `bin/` scripts no longer contribute a line to that list |
| `tests/Unit/Support/MutationHarness/MachinePathsTest.php` (2 failed) | the same file, read through the planted tree |
| `tests/Unit/Support/MutationHarness/Controls/InversionsTest.php` (1 failed) | its control asserts that every guard's tests are green on an unmutated tree, and one of them is not |

(The readings that used to be in this table — `RepoEscapesTest.php` and
`MutationHarness/RepoEscapesTest.php` — pass now: **24 passed** on its own, and the control's child
run is `1 failed, 109 passed` where it was `2 failed, 108 passed`.)

**A whole-gate run is a different colour from a suite run, and it is worth knowing before believing
either.** `bin/checks.php --require-all` on this machine also reports seven `ErrorException`
failures across `BootstrapTest`, `PushTest` and `ReleaseApplyTest`, and none of them recurred in the
run above — they are the pool's `proc_open` flakiness (§8), and `PushTest` alone is 18 of 18 under
the same binary. The gate is also red on `pint` (nine files, all `line_ending`, and the same file
pulled out of `HEAD` as a blob is clean), on `rector` (`src/Support/AcceptEncoding.php:67`, untouched
by anything here) and on `agentic` (the outsider's file of §3.3).

### 3.3 Two things are here that neither thread put there, and a third the session adopted

Someone else is working in this folder. None of the following belongs to this package, and none of
it should be deleted, moved or "fixed" from here without asking — except item 3, which was asked
about, answered, and is no longer an outsider:

1. **The three `handoff/` files were staged at 02:42:18** — `git status` read `A  handoff/…` for
   them and nothing else. Nothing in this package does that to the real checkout: every `git add`
   in it runs in a fixture (`tests/Support/ReleaseRepo`, `tests/Support/MutationHarness`) or names a
   path (`bin/commit.php` stages only its named set), and re-running the commands of that moment —
   the two path-guard suites, `verify.py --only=tools` — does not stage anything. It has been
   undone: the index is back to `HEAD` and the folder is untracked again (`git restore --staged --
   handoff/HANDOFF.md handoff/phantom-eol.sh handoff/state.sh` is the whole repair).
2. **`.agents/sample.machine.local.json`** appeared, 3,215 bytes, holding a machine file for a
   *different* package — a sibling package's paths, its `origin`, its branch, its skills. It is
   untracked and not ignored, so it counts as **a file a commit would carry**, and it is full of
   drive paths and of commands that name an interpreter outright. Two readings are red while it is
   there, both measured:
   - `tests/Unit/Support/MachinePathsTest.php`: **1 failed**, 25 passed, naming the file line by line;
   - `python .agents/verify.py --layer`: its `toolchain` step reports **two findings** in it, both
     "restates an interpreter in front of a command on Windows".

   That last one is the one that matters beyond the suite: `verify.py --layer` is what
   `.githooks/pre-commit` runs, so **a commit in this package is refused while that file is there**.
   It is not this package's file and this document does not reproduce its contents.

   **Left in place by decision, 2026-10-07.** The file belongs to the package next door, so it is out
   of this package's scope: do not delete or move it from here, and do not "fix" the guard for it —
   the two readings above stay red while it is there, which is the correct colour for "someone
   else's file is in this tree". The writer's side got the rule instead, so a future spill is
   refused before it happens.

   **Deleted 2026-10-27**, so none of the three items in this list describes the tree any more: item
   1 was undone in the sitting that found it, this one is gone, and item 3 was adopted. §7 item 2
   carries the account of the deletion, `.quarantine/README.md` no longer holds the file, and the
   paragraph above `/.quarantine/` in `.gitignore` says why the directory stays: it is where the next
   file that is not this checkout's to commit or delete goes.

3. **`composer-ca.ps1` and `composer-link.cmd`** appeared after the predecessor wrote this document,
   and they were the same category of spill from the same neighbour: a PowerShell script that fetches a
   Composer CA bundle and a command file that links a checkout, both carrying that package's paths and
   both climbing out of this repository into it. They were untracked and not ignored, so they were
   **files a commit would carry**, and between them they were what put the neighbour's lines into both
   path readings' findings: the machine-path reading named them and `.agents/sample.machine.local.json`,
   and the escape reading named six lines across the two — which is what the six failures of §3.2
   were.

   **Adopted, 2026-10-07, by the user's decision, and no longer a spill.** Both scripts are useful to
   *this* package, so they were moved into `bin/` — where their own headers already said they lived —
   and adapted rather than deleted: the Git CA bundle is discovered by asking Git (`http.sslcainfo`
   where a machine sets it, otherwise the prefix `git --exec-path` answers, which is where every layout
   Git ships keeps the same file), the sibling's package directory is out of the examples, each script
   asks this checkout for the interpreter and the Composer it runs before believing `PATH`, and the
   `--ignore-platform-req` block is gone because this package requires `ext-zlib`, `ext-brotli` and
   `ext-zstd` on every platform. Both are `export-ignore`d in `.gitattributes`, listed in
   `docs/scripts.md` and `docs/tool-registry.md`, and named in the changelog. The batch file's line
   ending is pinned as part of the move — `*.cmd text eol=crlf`, because cmd.exe reads it through
   `goto` and `call` and the general `* text=auto` leaves that ending to each contributor's
   `core.autocrlf`, which is the same reason `*.tsv` is already pinned to LF. What the readings say now
   is in §3.2: **the escape reading is green**, and the machine-path reading names the sample file
   alone.

The practical rule for all three: **run `handoff/state.sh` before you trust the index or the suite**,
and if a path is staged that you did not stage, unstage it rather than committing around it.

---

## 4. The one finding that looks like a bug and is not

**A set of paths in this package was listed as modified again and again with no content in
them — 29 when the thread started looking, 28 after one was cleared on purpose, 0 now (§3.1).** The
byte-identity behind it was proven three ways, and the state was then reproduced from scratch:

- for all of them, `git hash-object --path=<file>` (the blob `git add` would stage, filters applied)
  equals `git rev-parse HEAD:<file>`;
- `git diff --numstat` and `git diff --cached --numstat` are both empty;
- each working copy was byte-identical to the blob `HEAD` holds, with **0 CR bytes** in the file as
  measured then (the worktree has since been rewritten CRLF by whatever cleared the reports, §3.1 —
  and that rewrite is what makes §3.2 red).

**Cause, reproduced rather than guessed.** The index remembers each file's *stat* from when it was
last staged — including its byte size — and at that moment these files were CRLF. Something later
rewrote them as LF (a checkout, a redirect, an editor), so the recorded size stopped matching and
git called the entry worktree-modified, while `git diff` compared content through the clean filter,
got the blob it already had, and stayed empty. The mirror image is what ended it: putting CRLF back
on the worktree makes the recorded size match again, and the reports go away without a byte of
content changing.

The reproduction, in a scratch repository with this package's own settings (`* text=auto` in
`.gitattributes`, `core.autocrlf=true`):

| Scenario | `git status` | `git diff` |
| --- | --- | --- |
| the index recorded while the file was LF (11 bytes) | empty — no phantom | empty |
| the index recorded while the file was CRLF (13 bytes), then the file rewritten as LF again | ` M f.txt` | empty, `numstat` empty, `cmp <(git show :f.txt) f.txt` identical |

`git ls-files --debug <path>` shows the tell: `size: 13` recorded in the index against an 11-byte
file on disk — and that is the pair `state.sh` prints when it reports a non-zero count.

**What clears it, and what does not** (each measured in a fresh scratch repository):

| Operation | Listed before → after | What it actually does |
| --- | --- | --- |
| `git add -f <path>` | 1 → 0 | rewrites the entry with the current stat, same blob |
| `git add --renormalize .` | 1 → 0 | the same for the whole tree; verified to leave **nothing staged** and the index still equal to `HEAD` |
| `git checkout -- <path>` (or `git restore .`) | 1 → 0 | rewrites the **file** to CRLF — changes files on disk, and is what made §3.2 red |
| `git update-index --refresh` | 1 → 1 | prints `needs update` and exits 1 |
| `core.autocrlf false` | 1 → 1 | stops future drift, does not touch the recorded size |

`bash handoff/phantom-eol.sh` rebuilds all of this and prints the same table from this machine.

---

## 5. Uncommitted work: eleven files from the sessions that read this, and one that is not its own

**Nothing is staged and no commit was made when this was written; the work in the table below has
since been committed** — `d89fc95` (the environment held to the machine file), `272a574` (the machine
file keeping its own record), `a043dfe` (the two Windows scripts) and `427a1cb` (the index reading a
CRLF checkout) — and `handoff/state.sh` prints the tree as it stands. The list came back once more
after that, and is empty again as of 2026-10-11: the twelve files it held were sorted into four
commits by reason, each cut through `bin/commit.php`, so each carries its own paths, its own changelog
bullet, and a staged check that ran before it existed — `6346b2f` (the text files pinned to `eol=lf`),
`9eb5205` (the note channel a reading says a skip in, with the two readings that use it), `89d72d7`
(the ignore rows for the files a session leaves at this checkout's root) and `c08312e` (`BEHAVE.mdc`
named in the load order). The predecessor's half of this section was right for
its own moment — the 28/29 reports were stat-cache reports rather than work (§4), and the eight
groups below are **areas of the tree**, not batches of work, each already committed with the
changelog entry its own commit wrote. The table above is history as well now. The three paths that
row re-took at `e73e190` were cut as **one** commit rather than left standing — `6e4a566` (the
`export-ignore` line `BEHAVE.mdc` did not carry, the `CHANGELOG.md` bullet that records it, and this
document itself) — and it is that `HEAD` the table below describes.

**What accumulated after it is the burst this section is now about, and it is six commits**, one
reason each, every one cut through `bin/commit.php` with its own paths, its own bullet and a staged
check that ran before it existed: `469b6fd` (one place a fixture starts a child, and the wait a
refused `CreateProcess` is retried through — `tests/Support/Processes.php`, with the readings that
called `proc_open()` themselves moved onto it), `6dd976e` (the hold that reads a spill around the harness's
exclusion, `tests/Unit/Support/MutationHarness/Controls/SpillTest.php`), `7df2de4` (the gate's pool
sized from this machine — `tests/Support/Workers.php`, `bin/checks.php` and `bin/coverage.php`),
`20e49f6` (the three environment traps as a record of their own, `docs/commit-environment.md`),
`0b548ef` (the changelog and the index put back when a run stops, so an abandoned run leaves no
entry for work nobody committed) and `afdc56c` (a named set whose only moved path is the changelog
refused, with the `package-commit` skill's own text and this document beside it). **Four more
followed in this thread's next sitting, from work of its own** — `f357f20` (the files a pool could
not start asked together rather than one after another), `c35e016` (`bin/workbench.sh`, a throwaway
copy of this checkout with a vendor of its own), `b1247ef` (the bullet staged into the changelog
entry the index already holds rather than the file, so a note another session wrote in the same
document cannot be swept into a commit) and `bb49cb5` (a directory name's parenthesised suffix read
as part of the name). No hunk of the four was lost to the six before them: the shared documents were
materialised once per commit, each state being `HEAD`'s copy with only the bullet that commit
earned, and that is measured rather than assumed — `git show <commit>:CHANGELOG.md` against
`<commit>^` is one added line and no removal for each of the four, and the four documents at `HEAD`
are byte-identical to the state this thread authored.

**The list is not empty as this is written — `handoff/state.sh` reads four paths against a `HEAD` at
`bb49cb5` on 2026-10-27 — and not one of the four is either sitting's**: `pint.json` (an `exclude`
list for `external`, `ext`, `build` and `vendor`, plus a final newline) and
`.agents/skills/package-dev/_meta.json` (a date and a timestamp), both written at 17:57:20 on
2026-10-26, two minutes after the sixth of those commits; this document, whose §5 and §7 are this
re-taking, and which is therefore the one path a commit here would have to name by hand; and
`.idea/vcs.xml`, which the IDE rewrote at 00:34:46 on 2026-10-27 to register two nested repositories
under the ignored `build/rc/` — a mapping nobody wrote deliberately, and the reason a count taken
today is four rather than three. None of the four is recorded under `## Unreleased`, and none of them
is a later session's to commit or discard.

Everything the table below describes is otherwise at that `HEAD`:

| Area | Files | What it is |
| --- | --- | --- |
| the index reading a CRLF checkout | `tests/Support/GuardIndex.php`, `tests/Support/IndexNumbers.php` | §7 item 1, done: both declaration patterns made newline-agnostic |
| the environment table held to the machine file | `tests/Support/Handoff.php`, `tests/Unit/Support/HandoffTest.php`, `tests/Unit/Support/MutationHarness/HandoffTest.php` (all new), `tests/Support/MutationHarness.php`, `docs/guards.md` | a reading that holds `AGENTS.md` §2 and this file's §2 to `.agents/machine.local.json`, its row in the index, its guard test and its hold |
| the machine file keeping its own record | `.agents/render_local.py`, `.agents/machine.local.json.example`, `tests/Unit/Bootstrap/BootstrapTest.php` | `--init` updates the keys it owns and keeps every other line, where it used to write its three sections over the file |
| the records those changes carry | `CHANGELOG.md`, `docs/scripts.md` | the changelog bullets, and the scripts row for `--init`'s new behaviour |
| the two Windows conveniences adopted into `bin/` | `bin/composer-ca.ps1`, `bin/composer-link.cmd` (both new, untracked), `.gitattributes`, `docs/scripts.md`, `docs/tool-registry.md`, `CHANGELOG.md` | §3.3 item 3: the neighbour's scripts moved where their own headers said they lived and adapted to this package — a Git answer instead of a written-down path, this checkout's interpreter and Composer, no ignored platform requirements, an `export-ignore` line each, a row on both tooling pages, and `*.cmd text eol=crlf` pinning the batch file's ending |

**Eleven of the twelve modified files are that work; the twelfth is `AGENTS.md`**, which is somebody else's
setup edit — the fifth load-order entry of §3.1 — and is not this package's to commit or discard. It
is also the one file a commit here would have to *name* deliberately: it is the canonical rules file,
and a commit carrying it should say why the entry is being added.

The predecessor's rule holds, and matters more now: **commit named paths, never `git add -A`**,
because three of the seven untracked entries are another package's (§3.3).

What the predecessor found when it looked, kept because the shape recurs:

| Group | Last commit that produced it |
| --- | --- |
| the gate running the repository's own account of itself, plus the pointer files it reaches, and `tests/Support/IndexNumbers.php` | `fe0ef8b` |
| the guarded commit path and its skill | `fd8d07b` |
| this package's own rules, and the machine-name record | `446008b`, `a4a97d1` |
| the diagnostic records | `bb2edd1` |
| the PHP floor a run is under | `c3acd33` |
| the settings file that drifted | `649c8ee` |
| the machine-path and doc-link readings | `061ddba`, `a4aa123` |
| the release inventory and the releaser | `77d4be7`, `e5cf79e` |
| four files carried over from upstream, untouched by this project's work | the 2024 import, `6f4fcce`, `18140da` |

(The two import commits carry no `CHANGELOG.md` entry, which is what "untouched by this project's
work" means: their bullet belongs to whoever wrote them.)

A commit naming any of those paths stages nothing and adds a bullet describing work an earlier
commit already did and already recorded. That is why no such commit was made, and why the one commit
that was made instead (`ac96830`) is a record of exactly this and says so in its own body.

---

## 6. The predecessor thread's commits, all in this package

| Commit | Subject |
| --- | --- |
| `ac96830` | Record that the list a Windows checkout calls modified is bookkeeping, so no commit is cut from it |
| `39363d9` | Run a staged set one process per mapped path, so the narrowest mapping is not the slowest |
| `b8df4ec` | Map the mutation harness to the holds it builds, so a change to it runs the holds rather than the suite |
| `9a18697` | Ask the release rails in files of their own, so a worker is handed a rail rather than the whole list |
| `3b642e7` | Drive the harness's holds one file per guard, so paratest spreads the slowest thing in the suite |

All five are local; none is pushed. Each was verified signed (`G`) when it was made, and
`.githooks/` refuses an unsigned commit at the ref — so a push is refused too if one ever lost its
signature. The range is what a push would send, so check it before pushing:

```bash
git log --format='%h %G?' -5          # no `N` in the second column
```

---

## 7. Open work, in the order I would take it

1. ~~**The guard index cannot read a CRLF checkout**~~ — **done, 2026-10-07**, by the session that
   read this document. It was the defect behind §3.2, and the reason the
   suite was red that day. `GuardIndex::DECLARATION_PATTERN` (`tests/Support/GuardIndex.php:146`) ended
   `[ \t]*$/m`, which does not match in front of `\r\n`, while every file it reads is read as raw
   bytes off the disk. Fix it newline-agnostically (`[ \t\r]*$`, or split on `\R` first and match a
   line) and cover it with a CRLF copy of a declaring file. **Both halves are done, and committed
   together as `427a1cb`**: the line-end class is `[ \t\r]*$` in `tests/Support/GuardIndex.php` and
   in `tests/Support/IndexNumbers.php`, and each is covered by a test that plants CRLF rather than
   inheriting it — the measurement is in §7 item 8.
   `tests/Support/IndexNumbers.php:118` had
   the same shape (`"/^ {4}'([a-z][a-z0-9-]*)' => \[$/m"`, reading `bin/checks.php`), and was only
   safe because that file happens to be LF on this machine.
   **What it cost and what it uncovered is §3.2**, and the short version is there: the fifteen
   failures are gone, two reds came out from behind the throw, and three numbers stated by hand in
   `docs/guards.md` had to be moved by hand, because prose is the one part of that page no program
   rewrites.
2. ~~**`.agents/sample.machine.local.json`** if it is still there (§3.3) — **already decided: leave
   it.**~~ — **closed 2026-10-27: the file is deleted**, and the account at the end of this item says
   what replaced the decision. What it carried, in the order it was taken: it belongs to a sibling
   package and is out of this package's scope, so it is not this thread's file to delete, move or
   ignore by rule. Nothing to do here beyond knowing why the readings of §3.3 are red; the package it
   belongs to now carries a scope rule that refuses the write, so the next spill does not reach this
   tree. **Moved 2026-10-25**: the decision to leave it
   in place stands — it is still on disk — but it is in the gitignored `.quarantine/` now, with the
   two session configs a workspace agent wrote, because a file in the carried set is what kept every
   path reading red and `bin/commit.php` refusing this checkout's own commits. `.quarantine/README.md`
   says what each one is and how to put it back, `.gitignore` says why a move and not a rule, and
   `tests/Support/Ignored.php` still names it in the note a run prints.
   **Deleted 2026-10-27**, with the two records that described it: the paragraph above `/.quarantine/`
   in `.gitignore`, and `.quarantine/README.md`. What decided it was not ownership in the end but what
   keeping a copy costs: the file names a branch, a head, a tag and a GPG signing key that only
   `laravel-weighted-dbmanager` can keep true, so nothing in this tree could say whether it was still
   right, and it was the one entry every run printed a `[NOTE] Ignored:` line about. That line is
   gone — no run names a machine record now, and what the staged note still lists at the root is the
   working files this checkout ignores (`composer.lock`, the session notes) rather than a record of
   anybody's machine. **The two session configs went the other way**: this checkout decided its
   editor setup is its own and committed them as `63a2465`, so `.quarantine/` holds nothing but its
   own README — and `MachinePathsTest` names those two for as long as they are tracked, because each
   carries a machine's own temp directory in an `external_directory` permission.
   **Its two siblings were adopted instead** —
   `composer-ca.ps1` and `composer-link.cmd`, §3.3 item 3 — moved into `bin/` and adapted to this
   package, which is why the escape reading is green. The machine-path reading named this file alone
   while the two configs were in `.quarantine/`; it names those two now, which is the colour the
   paragraph above records.
3. ~~**`bin/commit.php` cannot write into a CRLF `CHANGELOG.md`**~~ — **done, 2026-10-11**, by the
   session that read this document. The pin in item 5 took away the way a checkout produced one —
   `CHANGELOG.md` is written LF now — but the refusal was real for a file that arrived with CRLF, a
   tree written before the pin or one an editor saves that way. `withBullet()`'s pattern is
   `/^(##[ \t]+Unreleased[ \t]*\r?\n)(.*?)(?=^##[ \t]|\z)/ms` now, and the bullet is joined with the
   ending the matched heading carries — `str_ends_with($match[1][0], "\r\n") ? "\r\n" : "\n"` — so a
   CRLF changelog gains `### Fixed\r\n\r\n- …\r\n` rather than an LF line inside it, which would be
   the whole file in the next diff. Two tests in `tests/Unit/Commit/CommitTest.php` plant CRLF rather
   than inherit it, one for a section that is not there yet and one for a section that is already
   there, and each asserts there is no lone LF in the result.
   **The recovery claim this line used to carry was wrong**, and the reproduction in a throwaway
   repository says so: the refusal is an `exit(1)` at the `withBullet()` answer, *before*
   `file_put_contents` is reached, so a CRLF changelog is left exactly as it was found and nothing is
   staged. Four bullets were written into this checkout's LF changelog on 2026-10-11, one per commit
   of §5, and every one of them was written before its checks ran.
4. ~~**`bin/commit.php` will cut a commit whose named set stages nothing.**~~ — **done, 2026-10-25**,
   by the session that took this item. It was the eight groups that would have been eight such
   commits: paths named, zero lines, bullets describing work already recorded. `carrying()` reads the
   working tree against `HEAD` — `git diff --numstat HEAD` for a path the history has, `git ls-files
   --others` for one it does not — **before** the bullet is written, so a refusal leaves the
   changelog exactly as it found it, and a set that carries nothing exits 1 naming its paths. The
   tree rather than the index, which is the one thing this item guessed differently: the program
   stages from the filesystem, so a path staged and then restored to `HEAD`'s bytes stages nothing
   while `git status` still calls it a change. `CommitTest` plants the state on a fixture whose work
   is committed, and holds the other direction — an untouched path named beside a moved one still
   commits — and a changelog edit named without a bullet, where the tool writes nothing to it, still
   does. Measured: `CommitTest` 20 passed (67 assertions), `bin/commit.php` at 78.2% (197 of 252
   lines) against its 70% floor under PCOV 1.0.12, and `bin/index.php --check` at exit 0 after the
   guard's row was re-rendered.

   **That guard had a hole, and the eight groups would have found it again — closed 2026-10-26.**
   `--no-changelog` is how a session hands the changelog over while it cuts a set in parts, so the
   evidence was the whole named set: a group that named its own paths *and* `CHANGELOG.md` — the
   file holding the bullet the group before it wrote — had that bullet counted as the change,
   `carrying()` answered yes to a set with nothing of its own in it, and the commit it cut
   published the earlier group's entry under a subject about work already in the history. The
   evidence is now the named set with the changelog taken out, because an entry records a change
   rather than being one, and the one shape where it *is* the commit is the entry named alone —
   `--no-changelog` with `CHANGELOG.md` and nothing else. `CommitTest`'s `it refuses a set whose only
   change is the changelog an earlier group wrote` plants exactly that fixture and holds both
   halves: the refusal exits 1 naming `carries no change of its own` and the untouched paths, the
   entry is left where it was found, nothing is staged and `HEAD` has not moved — and the same set
   with `CHANGELOG.md` alone still commits. The test is proven to fire rather than trusted: with the
   previous rule put back (`$evidence = $options['no-changelog'] ? $given : …`, restored by copy,
   never by `git checkout`) it fails on its first assertion — `Failed asserting that 0 is identical
   to 1` at line 175, a commit cut where a refusal was required — and is green with this one.
   Measured: `CommitTest` 23 passed (89 assertions), the whole suite under `bin/coverage.php` at 502
   passed (1,632 assertions), `bin/commit.php` at 79.6% (226 of 284 lines) against its 70% floor
   under PCOV 1.0.12, PHPStan at max and Pint in test mode, and `bin/index.php --check` at exit 0.
5. ~~**The root-cause decision, narrowed by item 1 and not settled by it.**~~ — **settled
   2026-10-10, on the third option: `eol=lf` pinned in `.gitattributes`.** The line is
   `* text=auto eol=lf` now, the guard row moved with it (`docs/guards.md` is rendered from the file
   the guard lives in, so the guard's name is the line), and the suite's colour no longer moves with
   the platform: Pint's nine `line_ending` findings are gone from a worktree that is LF, which is the
   half item 1 could not reach by making the readings CRLF-tolerant. Measured in a throwaway clone
   rather than argued: with `core.autocrlf=true`, 196 of the 199 tracked files came out CRLF before
   the pin and one does now — `bin/composer-link.cmd`, whose own `*.cmd text eol=crlf` overrides the
   pin and is written last so that it does. **A tree already written CRLF keeps its CRLF**, and the
   two repairs are not the same one: `git add --renormalize` refreshes the index's record of a file
   and leaves the bytes alone, while `git checkout -- <path>` writes the file again — LF under the
   pin, CRLF without it. Which one is needed is decided by what a checkout reads, and that is the
   index rather than the working copy: measured by pinning only the working copy, where both
   `git checkout -- <path>` and `git checkout-index -f` went on writing CRLF, so the pin binds a
   checkout once it is committed. This worktree was converted by rewriting the 27 CRLF files with a
   script and then `git add --renormalize` on those paths — which staged nothing, because no blob's
   content moved — and `git status` reports two files now, `.gitattributes` and this one. §8's
   `git checkout`-writes-CRLF trap is the other place this changes what a successor should do.
6. **Only `docs/guards.md` describes the new fan-out** ("one process per mapped path"). The rows in
   `docs/scripts.md`, `docs/tool-registry.md` and `README.md` still describe the same commands
   without it, so the inventories a reader trusts and the behaviour a reader gets have drifted. The
   two behaviours §5's burst added are in the same position: the pool's width, read from this machine
   through `tests/Support/Workers.php`, is named on the guard page and on no row of the three, and the
   retry that asks the files a pool could not start *together* rather than one after another
   (`f357f20`) is named on none of the four pages at all.
7. **Push, when asked.** 109 commits ahead of `origin/development` and 0 behind as measured on
   2026-10-27 — and the commit that carries this document is one more. `handoff/state.sh` prints that
   range and is the reading to trust, because a commit under this document moves it. A plain push suffices; never a force. `bin/push.php` / `composer
   push` is this package's own path, and a Windows push goes through PowerShell 7 over HTTPS, never
   from MSYS. **The work below this line is committed and unpushed**: §5's six of the burst and the
   four that followed are the last ten of that range, and one path is still uncommitted — this
   document — so a push sends the local range and no more, leaving the working copy as §5 describes
   it. What a push *is* is decided by §3.4's signature check, not by this list.
8. ~~**The CRLF fix has no test of its own**~~ — **written and committed as `427a1cb`.** Two tests
   plant the condition rather than inherit it, so the cover fires on an LF checkout as well as on this
   one: `tests/Unit/Docs/GuardIndexTest.php` writes every declaring file of its fixture with CRLF
   endings and reads them back through `GuardIndex::declared()`, `guards()` and `drift()` — both
   header styles among them — and `tests/Unit/Docs/IndexNumbersTest.php` rewrites its copied
   `bin/checks.php` with CRLF endings and requires the same `$checks` keys back. **Re-measured
   2026-10-10** by a later session: with `[ \t]*$` put back into one pattern at a time, the matching
   test goes red — naming a file that carries no declaration, and a gate whose array reads as no
   checks at all — and green again with the pattern restored byte-identical. Restored by copy, never
   by `git checkout` or `git restore`, which is the trap in §8.
9. **The fresh machine file's shape is asserted by nothing** — new, from the `--init` work. A new file
   is opened with the declared sections, and the bootstrap fixture exercises that branch without
   holding the result to anything, so a section dropped from `render_local.py`'s `SECTIONS` would be
   noticed by a reader rather than by a run.
10. **The environment reading's value half is not mutated** — new, from the same work. Its guard is
    held by one planted break, which is a token the renderer has never heard of; the other half — a
    machine file holding no value, or a recorded path that is not there — cannot be planted the usual
    way, because `.agents/machine.local.json` is gitignored and so is in no tree the harness copies.
11. **The two adopted scripts have no test of their own** — new, from §3.3 item 3. Nothing in the
    suite runs a `.ps1` or a `.cmd`: `TestMap` names every `.php` under `src/` and `bin/` and no other
    suffix, `ScriptCoverage` reads `bin/*.php`, and the coverage floor measures `.php` only. What holds
    the adaptation today is the two path readings — which is real, because the machine-path reading is
    what caught the written-down Git path — plus the interpreter rule in `verify.py`. Nothing asserts
    the behaviour the scripts promise: that the CA bundle is rebuilt, that a Composer failing under an
    intercepting scanner succeeds with the bundle, or that the link guards fire in order. Both were
    exercised by hand on this machine (see §9), which is not the same thing as being covered. The
    staged gate is no help either: `TestMap` answers for `.php` files under `src/` and `bin/` and for
    markdown, so a commit carrying these two is measured by syntax and style — which skip them — and
    by nothing else, and the readings that would have caught the paths they used to carry are only
    run because the whole suite runs them.
12. **A row on the two tooling pages is prose, not a guard** — new, from the same work.
    `docs/scripts.md` says in its own *Adding one* section that a tool under `bin/` earns a row, and
    `docs/tool-registry.md` says the same tools appear there with what each one is and who calls it;
    neither has a reading behind it, so a script added to `bin/` and forgotten on both pages is a
    silence rather than a finding. The nearest thing to a guard is `TestMapTest`, and it is about
    `.php` files only.
13. ~~**`bin/workbench.sh` has no test of its own**~~ — **done 2026-10-27**, by the session that
    read this item. It sat where item 11's two scripts sit and for the same reason: `TestMap` names
    markdown and the `.php` files under `src/` and `bin/`, and `ScriptCoverage` reads `bin/*.php`, so
    a commit carrying a `.sh` was measured by syntax and style — which skip it — and by nothing else.
    What holds it now is `tests/Unit/Workbench/WorkbenchTest.php`: the usage `--help` prints and
    answers 0 for, and every refusal that stops a copy being built — an argument it does not take,
    `--into` with no directory after it, a tree that is not a repository, a destination inside the
    checkout it is copying (and the empty directory that answer made, gone again), and a destination
    that already holds something, with the file in it read back byte for byte. `TestMap::COVERS`
    gained the row and `TestMap::files()` reads `.sh` beside `.php`, so the next shell script in
    `bin/` with no row is a finding in `TestMapTest` rather than a silence — which is what the map's
    own claim about programs already said. The install and the link refusal stay evidence rather
    than cover, deliberately: minutes and the network, and a privilege a runner may not have. What
    the rail cost to write will be paid by anything that starts a `.sh` from PHP — the script is
    named relative to the fixture, with `--into` too, because a native PHP process here resolves
    `bash` to WSL's shell, which answers a `C:/` script path with "No such file or directory"
    instead of opening it. Measured: `WorkbenchTest` and `TestMapTest` 10 passed (66 assertions) in
    9.81s, `bin/index.php --check` exit 0, `pint --test` clean on the three files, and
    `bin/checks.php --only=phpstan` `[OK] No errors` with Rector's dry run `[OK]`.
14. **§5's two leftovers are uncommitted and unrecorded** — new, from the burst. `pint.json`'s
    `exclude` list and `.agents/skills/package-dev/_meta.json`'s date hold what `HEAD` does not,
    carry no bullet under `## Unreleased`, and have been left exactly as they were found. The first
    is the one that changes behaviour, since it decides which directories the style check skips.
    Deciding them is one commit with a bullet, or a deliberate leave with the reason written beside
    it; until then `handoff/state.sh` reports them on every run. The fourth path §5 names is neither
    of those and no work at all — `.idea/vcs.xml` is the IDE's own mapping, added for the nested
    repositories under the ignored `build/rc/` scratch, and stands where §4's phantom reports stand:
    a reading, not an edit, and nothing to stage, commit or revert.
15. **A path whose directory carries a space after a mount root is still read to the space** — new,
    named by §5's `bb49cb5` rather than half-fixed by it. `MachinePaths` reads a parenthesised mark as
    part of a name now, but the mount pattern still ends a path at a space, a rule that spelling
    never had; it is a silence rather than a truncation, which is why the reading that made the
    parenthesis work wrote the edge down instead of guessing at it.

---

## 8. Traps the predecessor thread paid for

- **paratest takes exactly one path** (`--parallel` refuses two), so a mapped set has to be split by
  file — which is exactly what the fan-out commit did.
- **`proc_open` fails sporadically on Windows under the pool** (`CreateProcess failed: The
  parameter is incorrect`), at a child a worker starts, never as an assertion failure. **Measured
  2026-10-26:** at 16 workers, 42 failed tests on the run that raised it, then 29, then 26 — every
  one of them a file whose first test was refused while building its fixture tree, so one refused
  start reddens every test after it in that file; at 8 workers, which is what the gate asks for,
  none. Redirecting the streams to files, which this trap used to prescribe, is **not** the answer:
  the run with every stream on a file failed 26 tests too, because it is not the pipe at all. What
  is: the start was refused, so no child ever existed and a second attempt cannot repeat an effect.
  `tests/Support/Processes.php` is now the one place any fixture starts a child, and nothing else
  under `tests/` calls `proc_open()` at all — the calls left in `bin/` are a different case, a
  program starting its children for a single run, which no pool widens. The ladder is
  50 ms, then three times that each pause up to 4 s,
  for as long as 30 seconds, paid only by a refusal. Two shorter ladders were beaten by measurement —
  three attempts with 60 ms of pauses lost a run of 29 failed tests, and five attempts with just
  over 2 s lost a run of 41, every failure reporting `no reason was given`, which is what `error_get_last()` holds when
  Windows refuses a start without a message. The fault is bursty: the next run at the same width recorded
  948 starts and not one refusal, and passed 502 tests in 236 s, so read the ceiling off a failing
  run and not off a green one. Proven to fire with every first attempt refused on purpose
  (`BranchAliasTest` + `ReleaseApplyTest` 7 passed, file restored by copy). `pest --parallel` with no
  `--processes` is one worker per core, so this is the bare command and not the gate's 8-worker pool.
- **A commit is slow:** `bin/commit.php` runs the staged gate itself, and then the `pre-commit` hook
  runs it again. Run it in the background and poll; its stdout stays empty until it finishes because
  both gates capture their children.
- **A `bin/commit.php` run that is *killed* still leaves its changelog bullet on disk and its set
  staged.** The tool now puts both back itself on every refusal it can answer (`restoreTree()`), so
  this is the case where the process never reached that code — a SIGKILL, a closed terminal, a
  machine that stopped. Recover with `git restore --staged -- CHANGELOG.md`, then restore the blob with
  `git show HEAD:CHANGELOG.md > CHANGELOG.md`. The reason that is `git show` rather than
  `git checkout --` is that a checkout writes the working tree through the attributes, so what the
  file comes back as depends on them: before the `eol=lf` pin (§7 item 5) it came back CRLF, which is
  what tripped old item 3, and now it comes back LF and is merely the longer way round. The rule that
  survives both settings is the one that made it a trap: restore content from the blob, not through
  the working tree, when what you want is the bytes `HEAD` holds.
- **`--changelog` text must not begin with `- `** — the tool prepends its own `- `, and the first
  attempt produced a double bullet.
- **Never `git add -A` / `git commit -a`** here: it stages whatever the stat cache is reporting and
  everything else untracked, and §3.3 shows that list can be somebody else's file.
- **`bin/commit.php` refuses absolute paths and climbs** in its path arguments; the changelog and the
  repository's own rules do the rest.
- **The tests step's own summary is summed across runs** (`… in N runs`) because the fan-out starts
  one process per path — a single `Tests:` line is a single process, not a smaller set.
- **A guard that reads a file reads the bytes on disk, not the blob.** That is §3.2 in one sentence:
  five test files go red on a checkout whose line endings moved, while `git status` says the tree is
  clean. When a reading disagrees with `HEAD`, compare `git show HEAD:<path>` against the file before
  believing the reading.
- **If you edit anything in `handoff/`, keep it clear of machine paths.** This folder is untracked
  but not ignored, which puts it in "the files a commit would carry" — the set the package's
  machine-path and repository-escape readings scan (`MachinePaths`, `RepoEscapes`, both asserted in
  `tests/Unit/Support/`). So: no drive letters, no home directories, no network shares, and no
  relative path that climbs above the package root. Also keep a sentence about the gate's size out of
  this prose: `IndexNumbers` reads one as a claim, holds it to `bin/checks.php`, and reads this
  folder — a note in this file is in "the files a commit would carry" like any other, and this
  document's own first attempt at saying that out loud was itself read as the claim it warned of.
- **The escape reading reads prose, and a climb is read from the depth of the file.** A changelog
  bullet that mentions a parent-directory token in passing is a finding, because `CHANGELOG.md` is at
  the root and one step up is already outside it — while the same token in this folder, one directory
  down, is not a climb at all. The session that adopted the two `bin/` scripts paid for this twice:
  the scripts were reported for it where they sat at the root and stopped being reported the moment
  they moved into `bin/`, and a *sentence in the changelog* describing that put the reading back to
  red until the word was spelled out instead of written as a path.

---

## 9. What was verified, and what was not

**Verified by the predecessor thread:** the byte-identity of every reported path, three ways; the
stat-cache mechanism and each cure, by reproduction; the state of this repository at the time of each
reading; that every anchor commit of the eight groups carries its own `CHANGELOG.md` entry; that
`ac96830` is signed, touches only `CHANGELOG.md` (+12 −0) and leaves the file LF; and that both gates
(the commit path's own and the hook's) ran green for it — the tests step reported
`11 passed (32 assertions) in 3 runs`. **`bin/index.php --check` was green at that moment, went red
when the worktree was rewritten CRLF, and is green again as of §3.2** — the item 1 fix is what put it
back, and the readings that were red in between are listed there.

**Verified by the session that read this handoff and did §7 item 1 (2026-10-07):** the full suite at
**6 failed / 421 passed / 1,311 assertions**, all six failures being the outsider files of §3.3;
`bin/index.php --check` at exit 0 and the five files that were red under it green —
`tests/Unit/Docs/GuardIndexTest.php` 13 passed, `Docs/IndexNumbersTest.php` 9,
`MutationHarness/IndexNumbersTest.php` 2, `HandoffTest.php` 5, `MutationHarness/HandoffTest.php` 1,
`MutationHarnessTest.php` 4; the new reading's assertions against this cheque's own machine file, and
its refusal of a tree it cannot read; that `--init --force` keeps all fourteen sections of
`.agents/machine.local.json` and no key of any of them, with the renderer at `HEAD` writing three
sections back from the same file, measured side by side; and `render_local.py --check`,
`verify.py --only=tokens`, `--only=index`, `--only=interpreter` and `--only=canonical` all at exit 0
or `[ OK ]`. **A whole-gate run was also taken** and is red on `pint`, `rector`, `agentic` and the
tests step; every one of those findings was traced to something already red before this session, and
the seven `ErrorException` failures in it did not recur in the suite run above.

**Verified by the session that adopted the two Windows scripts into `bin/` (2026-10-07):**
`tests/Unit/Support/MachinePathsTest.php` and `tests/Unit/Support/RepoEscapesTest.php` run together
read **1 failed, 49 passed** — the failure naming `.agents/sample.machine.local.json` *alone*, with
the three absolute Git bundle paths, the example package directory and the climb at the root gone with
the move — and `RepoEscapesTest.php` on its own is **24 passed**, so **the escape reading is green for
the first time in this document's life**. `python .agents/verify.py --only=toolchain` reports **two
findings, both in that same sample file** — each one an interpreter written in front of a program of
this package's own gate — and none anywhere in either script; `--only=interpreter`, `--only=interpreter-token`
and `--only=tools` are at 0 findings; `bin/index.php --check` is at exit 0; `DocsLinksTest`,
`ChangelogPreambleTest` and `IndexNumbersTest` are green over the four documents this session edited;
and the suite tally of §3.2 was re-measured after the adoption. Both scripts were run on this machine
as well, through the interfaces a person uses: the CA script found Git's own bundle, wrote the rebuilt
one to `%TEMP%`, resolved the interpreter `.agents/machine.local.json` names and the `composer.phar`
beside `composer.json`, and printed **Composer 2.10.3 under PHP 8.4.26** at exit 0; the link script
answered `--help` at exit 0 and its usage and directory guards at exit 1, re-read a package's `name`
with no `php` on this machine's `PATH`, and — run from a fixture application against a fixture package
with `--dry-run` — registered the path repository with a forward-slash URL and required
`uak35/laravel-response-compression:@dev` at `dev-main cf9c922`, leaving the fixture's `composer.json`
holding only the repository entry it was told to write.

**Verified in this handoff session (2026-10-07, after the move into this folder):** both scripts pass
`bash -n` and run green from the package root and from another directory; the three files carry no
drive path, no home directory, no share and no climb — read with the package's own detector, 0
findings in all three; `.agents/verify.py --layer` was green with them present (11 steps, 0 findings)
when it was measured, and is red now only for the outsider's file of §3.3, against which it reports
two findings in its `toolchain` step and nothing anywhere else. Also verified: the `Docs`/`GuardIndex`
throw of §3.2 and its cause, by running
the guard's own pattern against both texts; the full-suite tally of §3.2; that the staged entries of
§3.3 were restored and the index is back to `HEAD`; and that `.agents/verify.py --only=tools` is
green.

**Not verified, and worth knowing:** the exact git-internals code path behind the phantom — the
reproduction is empirical and the explanation is the one that fits every measurement; who or what
cleared the reports at 2026-10-07 00:50:58, and who staged the `handoff/` files and wrote
`.agents/sample.machine.local.json`; and whether any GUI shows those reports under a *staged*
heading — git's index holds nothing, which is what `handoff/state.sh` prints.
