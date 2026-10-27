# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

**Versions count on from the newest tag**, and the history this package inherited is not part of
that sequence: the `0.2.0` and `0.1.0` releases were
[`chr15k/laravel-response-compression`](https://github.com/chr15k/laravel-response-compression)'s
own, the upstream this package was taken from, and no tag here carries either number. They are
kept — the notes, the credits and the dates as that project wrote them — under
[Inherited from upstream](#inherited-from-upstream) at the end of this file, in headings that are
not versions of this one. The tags are what Packagist publishes, so they are what the numbering
follows. How a number is worked out is recorded in `RELEASING.md`, a runbook for whoever cuts the
release — which is why it sits in the repository rather than in the package.

## Unreleased

### Added

- **A scratch copy of a tracked file is named rather than left sitting in the tree, so the workaround of putting a file back by copying is visible where it was silent.** A guard is proven to fire by breaking the tree under it, and the file goes back by copying rather than by `git checkout` — the working copy is where a session's own uncommitted work lives, and a checkout would take it away. What that leaves is `build/head/<path>` and `build/authored/<path>`, and `build` is ignored: no entry of `git status` mentions either one, `MachinePaths` and `RepoEscapes` read the files a commit *would* carry, and `Ignored` reads what a rule holds that records a machine somewhere else — so a copy of this package's own file was in none of the three answers. `tests/Support/ScratchCopies.php` is the reading over the other set, and it answers with names rather than findings, because a clone with no copy has to stay green and the copies here are this repository's own way of working: `files()` is every file git does not have — the union of `MachinePaths::untracked()` and `Ignored::files()` rather than a third question asked of git — and `copies()` reads each of those names for the two shapes a copy is written in: a tracked path under a directory in front of it, which is a scratch directory mirroring the tree the copy was taken from, and a tracked path with a mark on the end, `.txt` first among the marks because `AGENTS.md.txt` is the copy that happened here. The originals are the tracked half of `MachinePaths::files()` with its untracked half taken out, and a file with no directory of its own is left out of them, because `README.md` is a name dependencies ship too. Two boundaries are stated rather than discovered: bytes are not read, so a copy kept under a name of its own — the test's `build/kept-under-another-name`, byte for byte the file it was taken from — is a case nothing in the tree decides; and a dependency that happens to ship a path this package also has is reported as a copy, which is the cost of a rule written around names, paid where a reader can see it, in a sentence that names both paths. `ScratchCopiesTest` holds it in five tests (18 assertions): the two shapes on a fixture, a copy that is itself in the commit — an index entry is listed by `git ls-files --cached` whatever the ignore rules say, so it is a file a commit carries rather than a copy sitting in the tree — four files git does not have that are named after nothing, a tree with no repository around it, which throws rather than answering that nothing is there, and this checkout's own listing reaching the note channel a run prints. The test declares `@guards-index test` rather than a guard row, because a note has no break for the mutation harness to plant, and that reason is in the test's own header. Its row is rendered from the file like every other reading's, which moved the counts in `docs/guards.md` to 22 readings and 29 files in `tests/Support/` in all, and `docs/commit-environment.md` — whose §1 is the rule this reading makes visible — now names it beside `IgnoredTest` where that record says what reads the files a commit would not carry, correcting the name it had spelled `IgnoreTest`. Measured: `ScratchCopiesTest` 5 passed (18 assertions) in 21.5s; the reading named 57 copies in this checkout — 31 under `build/authored/` and 26 under `build/head/`, the commit-sorting thread's snapshots, 62 files in all, the five its rule leaves unnamed being the root-level names it excludes (`CHANGELOG.md`, `README.md`, `pint.json`) — and both directories have since been removed, so a run of this checkout reports none. The kit that wrote them is gone with them, which is the half that keeps the answer: `build/state.py` — the scratch tool whose `AUTHORED` and `HEAD` roots they were — its driver `build/run-steps.sh`, the six commit messages under `build/messages/`, the per-step states, `build/authored.md5` and the logs of the six commits have been removed as one set, because a tool that is still in the tree is a tool that writes the directories again, and the reading would have the same 57 names to report the next time somebody cut those groups; `bin/index.php --check` reported the reading as in neither list before the render and exits 0 after it; `GuardIndexTest`, `IndexNumbersTest` and `DocsLinksTest` 28 passed (157 assertions); and the gate green with `--require-all` in two runs — the suite as `--only=tests`, which ended in `All checks passed` with 516 passed (1,679 assertions) in 324.3s and no copy named in its note block once those directories were gone, and the other ten checks together, whose one finding was Rector's own (`ForRepeatedCountToOwnVariableRector`, a repeated `count()` hoisted out of a loop in the new reading), applied by `bin/tool.php rector` and clean on the dry run after it, with Pint in test mode over 122 files and PHPStan at max reporting no errors.
- **A throwaway copy of this checkout can be built with a vendor of its own, so an experiment's reading belongs to the tree it was taken on rather than to whichever tree its vendor resolved into.** `bin/workbench.sh` builds it: the files a commit would carry (`git ls-files --cached --others --exclude-standard`, the same set `bin/checks.php --staged` reads) and never `vendor/`, which the copy is given by running this checkout's Composer against its own lock, plus the three ignored files that decide how the copy behaves — the machine record, `composer.lock` and `composer.phar`. The two ways a copy stops being one are refused rather than inherited. A path the copy would carry that is a link ends the run before anything is created, naming the link and what it resolves to; a Windows junction counts, and the reading is the shell's rather than PHP's, because `[ -L ]` and `readlink` see a junction while `is_link()` does not. The vendor the install produced is then read back, so a copy whose `vendor/` resolves outside the copy is refused instead of handed over as an experiment's tree. `git clone --no-hardlinks` gives it a history of its own and the clone's `origin` is removed, so nothing in the copy reaches back into the tree it came from, and a `vendor/` that is or holds a link is printed as a note: the copy installs what the lock names rather than reading another tree's packages. The interpreter is not decided in the script — `sh .agents/php-bin.sh --report` answers it, so the copy is installed under the PHP this checkout runs — and its row is on `docs/scripts.md` and `docs/tool-registry.md` beside the two Windows conveniences, `export-ignore`d with them. Measured: a copy built here reported a vendor of 13,626 files resolving inside the copy, no remote, and no object hardlinked to this checkout's store, and `pest tests/Unit/Support/WorkersTest.php` inside it is 7 passed (12 assertions) in 5.64s under Composer 2.10.3 and PHP 8.4.27; a junction planted among the carried paths was refused by name with nothing created, and a destination inside this checkout was refused as well.
- **The three environment traps the commit skill carries have a record behind them, so the bullet a session applies is argued rather than only stated.** `.skills/package-commit/instructions.md` lists them under **Environment** — scratch kept inside the root, Git's `sh` appended to `PATH` for one run, and the two mandated shells not being the same shell — and a skill has room for an instruction rather than for the case that makes it one. `docs/commit-environment.md` is the record: what each trap looks like from the outside, why nothing in this repository reports it, and why the workaround is the narrow one. The first is section 0 of `AGENTS.md` read from the commit's side — the staged run prints the ignored entries at the root by name, which is how a machine record for another checkout gets named by a commit at all, while a scratch directory outside the root is a path no listing holds and neither `MachinePathsTest` nor `RepoEscapesTest` can read; the shape is not "no temporary files" but "the scratch belongs beside the work it is about". The second is measured in the code rather than assumed: `tests/Support/PhpBinary::shell()` is the literal `sh`, its docblock says a machine with no `sh` on `PATH` is a machine where the question goes unasked, and `.agents/verify.py`'s `tools` step turns exactly that into a note (`tools: no sh on PATH, so .agents/php-bin.sh was not asked to prove its ladder`) rather than a finding — a green run that proves less than a green run, with `php-bin.sh --selftest` the thing left unrun. The record says why the line appends and never prepends (Git for Windows carries an MSYS `git` beside its `sh`, and putting that ahead of the Windows one changes which git every fixture in `tests/Support/ReleaseRepo` runs) and why no file and no git config carries it (a `PATH` is a fact about a host; `.agents/machine.local.json` is gitignored and is the only file allowed to name this machine). The third is the table a bullet cannot hold — `sed`, `awk`, `head` and a literal newline as a statement separator exist on one of the two shells and not the other, `rg` is mandated with `git grep` as the fallback, and an empty marker search is a claim about the quoting before it is a claim about the file, because an apostrophe inside a single-quoted pattern ends the string early. The sixteenth record also made one fixture stale, which is a change worth naming: `IndexNumbersTest` typed the word a stale count is planted against (`fifteen of them`) and the count it should have been caught against (`holds 15`), so it failed on its own quotation the day this record arrived rather than on the reading — both ends are now read from `IndexNumbers::counts()`, which is what the test beside it already did. It is linked from the README's Design records list (before the guards record, which the paragraph under that list calls the last one and the index a guard is added to), `tests/Support/Docs.php`'s header now states the count it is read for — sixteen — and the record cross-links `docs/guards.md`, `docs/scripts.md` and the files it argues about. It also names what it does not claim: that a guard could catch any of the three, that they are traps only for a commit, or that the `PATH` line is a fix for a machine with no Git. Measured: `DocsLinksTest` 3 passed (10 assertions), `IndexNumbersTest` green with the count it now reads, and `bin/index.php --check` at exit 0.
- **The spill the harness reads around has a control of its own, so the exclusion that keeps one out of the holds is measured rather than believed.** A checkout spilled into by the package beside it used to redden every hold, because the planted tree was built from the files a commit would carry - the right set for the real readings and the wrong set for a hold, whose first act is to require an empty answer on an unmutated tree. `MutationHarness::carried()` fixed that by keeping only this package's own work, and nothing ran the two answers end to end: a green hold cannot show which listing it read, and a listing that had stopped excluding spills would be green on the checkout it was planted from. `tests/Unit/Support/MutationHarness/Controls/SpillTest.php` is the third control beside the overlay and the program ones: it takes a copy of this checkout, plants a machine record and a climbing link in it, and reads both trees. `MachinePaths::scan()` and `RepoEscapes::scan()` have to name the spill on the copy - with the listing checked for substance first, so a scan that read nothing cannot satisfy the claim - and `MutationHarness::tree($copy)` has to plant a tree the holds read as clean, every one of the mutations' pristine readings answering with nothing, collected with the guard's name so a failure says which hold went red. It is the whole list rather than the asks about paths, because the listing is one listing: a spill that reached it would redden every guard's hold at once. The control is proven to fire rather than trusted: with `carried()`'s exclusion planted out (`|| self::belongs(…)` replaced by `|| true`) it fails on its first assertion, naming the spilled file as one the planted tree carries, and with the file restored by copy - byte-identical to `HEAD`'s blob - it is green again. `docs/guards.md` says there are three controls now that a spill is one of them. Measured: `SpillTest` 1 passed (8 assertions) at 37.3s in 42.1s, the planted break failing it at line 63, and `bin/index.php --check` at exit 0.
- **The silent staged run has a record beside its fix, so the reading behind `5a48ebf` is kept rather than only the sentence a commit message had room for.** `docs/investigations/why-the-staged-check-runs-silent.md` says what was asked, what was read (`TestMap::covering()`, the gate's main loop, `runCommand()`, `runCommands()`, `ChecksTest`), the three findings - the staged mapping is the whole suite when a `tests/Support/` file is staged, every check's output is buffered until the summary, and a child inherited stdin - what the fix changed, and what it did not establish: a full nine-minute `--staged` run was not reproduced here, so the duration is attributed to the suite from the mapping and the measured runs, and the mapping is still the whole suite by design. `docs/investigations/README.md` is the folder's index and says so, which is why nothing links to the record and `DocsLinksTest` does not read it.
- **The mutation harness plants the opposite collapse of the machine-file record's three states, so a
  report that keeps the fresh clone's sentence for a record that has grown is caught as well.**
  `9c6973f` planted the branch's removal — the fresh clone falling through to the grown sentence —
  and `MachineShape::state()` names that direction. The other was unheld: drop
  `len(missing) == total - len(OWNED)` from the `elif` and a record with one section past the
  `--init` answers is printed with the fresh clone's sentence, which sends its reader back to the
  answers they already gave and hides the sections still empty. `MachineShape::recorded()` is the
  reading beside `state()`: it opens the tree the way `bin/bootstrap.php` opens it, records one
  section beyond the three, and requires the report to have stopped saying the file is a fresh clone;
  it puts the tree back as it found it. `MachineShapeTest` asserts it empty on an unbroken tree, and
  the new mutation — driven by `tests/Unit/Support/MutationHarness/MachineShapeTest.php` — takes the
  clause out of `render_local.py`'s branch and requires the reading to name the sentence that comes
  back. The guard's row in `docs/guards.md` was re-rendered from the test header.
  Measured: `MachineShapeTest` 9 passed (69 assertions), the hold 4 passed (12 assertions), and
  `bin/index.php --check` at exit 0.
- **The files this checkout ignores are read as a set of their own, so a machine record another package left in `.agents/` is named rather than hidden by the rule that keeps it out of a commit.** Both path guards read the files a commit would carry, which is the right set for a path that could reach a commit and the wrong set for a file this repository has decided not to carry. `.agents/sample.machine.local.json.txt` is the case in hand: a machine record for `laravel-weighted-dbmanager`, the package beside this one, full of drive paths. `*.txt` keeps it out of `git status` and out of both readings, so a rule written to hold a session's scratch copy also kept somebody else's machine file from being mentioned at all — in the directory this repository's own machine file is kept in. `tests/Support/Ignored.php` asks the other question, `git ls-files --others --ignored --exclude-standard`: among those files, which are machine records (a JSON document carrying `php.exe` and `package.dir`, the shape `--init` writes), and which of them describe a checkout that is not this one — the comparison is on `package.dir`, so this checkout's own record is excused by the checkout it names rather than by its path. It answers with names instead of findings, because a clone with no spill must stay green and the file that is here was left in place by decision, and `IgnoredTest` writes the names into the note channel a run prints: a bare run of this checkout now says that `.agents/sample.machine.local.json.txt` is a machine record for the package next door — a checkout that is not this one — and that no commit carries it. Five tests hold it: a record that is somebody else's, a copy that is this checkout's, three ignored files that record no machine, a tree with no repository (which throws rather than answering that nothing is there), and this checkout's own listing reaching the note. `.gitignore`'s paragraph about what the rule is holding now names the reading rather than asking the reader to trust it. Measured: `IgnoredTest` 5 passed (14 assertions), `bin/index.php --check` at exit 0 with the new reading in the two lists, and docs/guards.md's counts at 21 readings and 26 files.
- **The staged run names the ignored entries at the root, so what a commit does not carry is read at the moment the commit is made.** A file an ignore rule keeps out of a commit is the one kind of uncommitted work `git status` says nothing about — it is absent from the listing rather than marked in it — and this repository's rules are written for exactly that kind of file: `*.txt`, `/oc-session-*.md`, `/opencode*.jsonc`, `/permissions-01.md`. A rule doing its job therefore looks the same as a rule hiding the file somebody meant to commit, and the only place the two can be told apart is the commit. `bin/checks.php --staged`, which is what `.githooks/pre-commit` runs, now prints them as a note under the summary: `git ls-files --others --ignored --exclude-standard --directory`, kept to the names at the root and sorted, so an ignored subtree arrives as one entry (`vendor/`) rather than as every file beneath it. On this checkout that is fourteen names, `AGENTS.md.txt` and the four `oc-session-*.md` logs among them; machinery is named beside litter on purpose, because the plainest reading of "what this commit does not carry" is the whole of it, and a curated half would be a second `.gitignore` — a list of which ignored names count — kept in step by hand. `tests/Unit/Gate/ChecksTest.php` plants a rule and the two files it ignores and requires the note to name both in order, then a tree carrying the rule and nothing under it and requires no line at all; with `ignoredAtRoot()` answering `[]`, that test fails. Measured: `ChecksTest` 14 passed (47 assertions), and `bin/checks.php --staged` on this tree printing the fourteen names.
- **A session can now investigate a question about this package and keep the answer, so the next one
  reads what was found instead of repeating the reading.** `rc-investigation` is a skill authored in
  `.skills/rc-investigation/` and read from `.agents/skills/rc-investigation/SKILL.md`: it activates
  on a prompt that begins with the `/rc-investigation` marker, and for a prompt that does not carry it
  but asks to investigate or to find out why it asks *"Is this an RC package investigation?"* first
  and activates only on a yes — the marker is the confirmation, so it is never asked of a prompt that
  already carries one. When the response is finished the skill writes the investigation as a markdown
  document under `docs/investigations/`: the question as it was put, what was read, what stands behind
  each finding and what the reading did not establish. That folder is deliberately not read by the
  docs-link guard, whose glob is `docs/*.md` and non-recursive, so a record links nothing and nothing
  links to it; `docs/investigations/README.md` says so and carries the two rules a record still obeys
  as a file a commit would carry — no machine path and no climb above the root. The description names
  two commands, `rc-investigation` and `record`, both defined in `instructions.md`, which is what the
  `descriptions` step requires of a hyphenated word; `python .skills/_sync.py` wrote the target, its
  `_meta.json`, the `GEMINI.md` import and the Windsurf pointer, and `.agents/README.md` carries the
  skill's row.
- **The handoff's state section is held to `handoff/state.sh`, so the counts and the colour a reader
  is handed cannot go on describing a tree this checkout has stopped being.** `handoff/HANDOFF.md`
  §3 opens by promising that `bash handoff/state.sh` outranks it, and nothing held the two together:
  the section carried `CHANGELOG.md` as a file "`bin/commit.php` can only write a bullet into while
  it is LF" for five days after `ca776f0` made the writer take the heading's own ending, and the
  script contradicted the sentence on every run — it prints the ending it measured beside what the
  writer can do with it. A snapshot cannot be held to the tree it was taken from, because the live
  `HEAD` moves with every commit, so `tests/Support/HandoffState.php` splits the claims where they
  divide: the branch, the `core.hooksPath` the gate is wired through and `CHANGELOG.md`'s endings are
  held to the script now; a bullet that states `HEAD`, how far ahead the branch is, a tally such as
  `466 passed` or the colour in a word has to say when it looked — in the bullet, or in the heading
  it sits under, which is how §3.2's dated heading covers the counts beneath it; and the section has
  to be dated and say the script outranks it, because the colour it carries is a tally no script
  measures. Two lines of the handoff moved with it — §1's bullet now names the date it was measured
  on, and §3.1's says what the writer can do rather than what it could not.
  `tests/Unit/Support/HandoffStateTest.php` holds the reading over this checkout and over a document
  with each break planted back, including the same tally under a heading that dates it and one that
  does not; the mutation harness plants both breaks — the ending claim and the undated count — in a
  copy of the tree. Measured: `HandoffStateTest` 8 passed (28 assertions), the two holds 2 passed (6 assertions),
  `docs/guards.md` at 38 guards and 20 readings, and `bin/index.php --check` at exit 0.
- **The committed machine file's shape is held to the program that writes it, so the sections a
  fresh clone is opened with cannot drift from the example a reader copies.**
  `.agents/machine.local.json` names this machine and is gitignored, so it is in no commit — and two
  things stand in for it: `.agents/machine.local.json.example`, the committed shape, and the file
  `python .agents/render_local.py --init` writes, which is what `bin/bootstrap.php` gives a clone
  nobody has set up yet. Nothing compared them, and the half that could have was the half that is
  not asked on a runner: `verify.py`'s `tokens` step judges the rendered settings files only where
  there is a machine file to judge them against, and CI has none. So a section could be declared in
  the renderer and never reach the example, or the example could grow a section `--init` has never
  opened, and both read as a shape. `tests/Support/MachineShape.php` is the reading: it takes the
  renderer's own declarations — `SECTIONS` for what a machine file holds and what each section is
  for, `OWNED` for the keys `--init` writes from the answers it is handed, and `TOKENS` through
  `Handoff`, which already reads that mapping — and reports a section declared in one and missing
  from the other, a section the example shows that no fresh clone is opened with, a key `--init`
  writes that the example shows nowhere, and a token whose key `--init` never writes. Its other half
  asks the program rather than its declaration: the real renderer runs in a fixture, and the file it
  writes there has to be exactly the declared sections, filled from the answers, because the
  example's claim is about the file a new machine gets. `tests/Unit/Support/MachineShapeTest.php` is
  the guard, `docs/guards.md` carries its row and `bin/index.php` wrote the two lists — the page's
  count of guards is 37 rather than 36 — and
  `tests/Unit/Support/MutationHarness/MachineShapeTest.php` is its hold, which takes a section out of
  the example and requires the reading to name it. The record sections' keys are deliberately not
  held: `--init` keeps those as they stand and the renderer declares no keys for them, so a reading
  that pinned them would be a second copy of the example rather than a comparison with the program.
- **The four files a session leaves at the root of this checkout are ignored, so `git status` stops
  opening with somebody's transcript.** `/permissions-01.md` and the three `/oc-session-01*.md` files —
  a session's log, its summary, the issues it faced and the workflow it followed — land at the root of
  this checkout while work is being done in it, in the same shape as the `/opencode.jsonc` row directly
  beside them: one tool's record of one machine's run, belonging to no version of this package.
  Untracked and *not* ignored is the combination that matters, because that is what shows in `git
  status` and what a broad `git add` publishes: a commit then has to explain a file whose content is
  somebody's transcript of a session, and a reviewer checking that a commit carries only its own paths
  has four more paths to read past on every call. The rule is written down beside the rows above it
  rather than remembered, because the next session writes its own files and it is not this one.
- **A reading that declines to ask a question says so in a note, and the note is printed where its
  answer would otherwise be indistinguishable from a clean one.** Half of `tests/Support/` asks a
  question some machines cannot answer — `Handoff` holds the environment table to
  `.agents/machine.local.json`, and `PhpBinary` walks its resolver ladder only where there is a machine
  file or an override to ask about — and both answer with nothing, which is also their answer when
  there is nothing wrong: a CI runner and a checkout nobody has run `--init` in read exactly like a
  reading that asked and agreed. `tests/Support/Notes.php` is the channel they say it in now. A
  reading hands it its class and a sentence, the suite prints what a test made — on the error stream,
  because PHPUnit keeps what a test writes to output as that test's unexpected output and shows it
  only for one that did not pass — and `bin/checks.php` repeats every marked line under its summary,
  where a skip used to be read as a green run. The channel has a second half because the gate runs the
  suite under `paratest`: a worker's *two* streams are dropped by the runner when its test passed,
  which is measured rather than assumed — a probe printing to each of them from a passing test came
  back in neither — so the gate names a file in `RESPONSE_COMPRESSION_NOTES`, every worker writes its
  notes into it, and the gate reads it back with the notes its own checks printed, one line per
  sentence however many workers wrote that sentence down. A note is written to the file *as well* as
  held for the terminal, never instead of it: a sink that cannot be written therefore costs nothing,
  and no reader can be shown a line twice because each collects sentences into a set. The channel's
  own tests change nothing they do not put back — a worker runs more than one file, and a test that
  unset the variable for its own convenience would silence the file half of every reading after it in
  that process — each skip is asserted where it happens, and `tests/Unit/Gate/ChecksTest.php` plants a
  runner that can only write a note to the file it was handed and requires the gate to print it.
- **Two Windows conveniences moved into `bin/`, where their own headers said they lived, and were
  adapted to this package rather than to the checkout beside it.** `bin/composer-ca.ps1` runs Composer
  through a CA bundle rebuilt from Git's own plus every intercepting root it finds in the Windows trust
  store — the workaround for a local TLS scanner that makes PHP's curl fail where Git's Schannel
  succeeds — and `bin/composer-link.cmd` links a package on this machine into a Laravel application and
  requires it at its dev version, the CMD port of a published `composer-link` shell function with the
  same six guards. Both arrived at the repository root carrying a sibling package's assumptions, and
  this repository's own guards reported them for it: an absolute Git bundle path three times, an
  example package directory, and a parent-directory climb written at the root — which is a climb
  when it is written there and is not one a directory down. The bundle is now found by asking Git — `http.sslcainfo` where a
  machine sets it, and otherwise the prefix `git --exec-path` answers, which is where every layout Git
  ships keeps the same file — so no install directory is written down, the examples name this package,
  and each script asks this checkout for the interpreter and the Composer it needs before falling back
  to `PATH`: `.agents/render_local.py --value __PHP_EXE__`, then the `composer.phar` beside
  `composer.json`, which is how Composer runs on a machine that has neither on `PATH`. The
  `--ignore-platform-req` block is gone as well, because this package requires `ext-zlib`,
  `ext-brotli` and `ext-zstd` on every platform and the gate's `platform` check is what reports a
  missing one. `.gitattributes` `export-ignore`s both — they are started by hand, on Windows, by
  whoever works on this repository — and `docs/scripts.md` and `docs/tool-registry.md` carry a row for
  each. The batch file's line ending is pinned as it arrives: it jumps between labels from its first
  line to its last, and cmd.exe wants the ending a Windows filesystem hands it, so `.gitattributes`
  sets `*.cmd text eol=crlf` — the one line that overrides the `eol=lf` the general rule pins, and it
  is written after that rule so that it does — and the blob is stored LF like every other file here.
- **The file a workspace tool writes at this checkout's root is ignored, so `git status` is not where it announces itself.** `/opencode.jsonc` appears the first time a checkout is opened as an OpenCode workspace, and what it holds is a setting for one machine, so it belongs to no version of this package. Untracked and *not* ignored is the one combination that matters: it shows in `git status`, so a broad `git add` would publish a file whose whole content is one editor's preference. The rule is named beside the artefacts a run already leaves behind, which is why `/.freebuff/` sits there for the same reason, and it is written down rather than remembered because the next machine writes its own.
- **The behavioural guidelines this repository works under are a committed file rather than a session's private prompt.** `BEHAVE.mdc` collects four rules a session is expected to hold to — think before coding and surface the trade-offs, prefer the simplest change that solves the problem, keep each edit surgical, and turn a request into a goal that can be verified — and every one of them is written against a mistake a change here has already made rather than against the package. It sits at the root beside the rules file, names no command, no file of `src/` and no machine, and is versioned with the checkout so the behaviour one session brings is the one the next finds.
- **The environment table is held to `.agents/machine.local.json`, so a row naming a token nothing
  supplies is a finding rather than a described environment.** `AGENTS.md` §2 writes its rows as
  tokens — `__PHP_EXE__`, `__PWSH_EXE__`, `__PACKAGE_DIR__` — on purpose, because no committed file
  may name a machine, and the values live in the one gitignored file a checkout has; nothing compared
  the two, and the way that fails is quiet in both directions: a session reads the table, resolves a
  token nothing answers for, and finds out halfway through a command. `tests/Support/Handoff.php`
  reads the names the table writes, the token list and the key behind each one *out of*
  `.agents/render_local.py` — the file that owns that shape, so there is no second copy of it to keep
  in step — and the values out of the machine file, and it reports a name the renderer has never heard
  of, a token the machine file holds no value for which the renderer does not derive itself, a
  recorded path that is not there, and a machine file naming another checkout while reading as a
  complete answer. `verify.py` excuses `__TOKEN__` on the Python side of this layer and this reading
  excuses it for the same reason: the placeholder is written like a token and is not one. The handoff
  folder is read as the document it says it is — its own §2 is held the same way where that folder is
  here, and answered as `null` where it is not, so deleting it takes nothing with it.
  `tests/Unit/Support/HandoffTest.php` declares the row, the mutation harness plants an added row
  naming a token the renderer does not have and requires the reading to carry it back, and both lists
  in `docs/guards.md` were rendered from the files.
- **The modified list a Windows checkout shows is recorded here as line-ending bookkeeping rather
  than as work waiting to be committed.** A checkout that wrote CRLF over a blob's LF — what `*
  text=auto` alone did here, and what the pin in `### Changed` ends — left `git status` listing the
  files it touched: the blob git would stage is the one `HEAD` already holds and the diff is empty,
  so a commit naming such a path would carry no lines at all. The list came back once more when this
  worktree was rewritten to LF, and `git add --renormalize` cleared it without staging a line. The
  paths fall into eight groups - the gate's own account of itself and the pointer files it 
  reaches, the guarded commit path and its skill, this package's own rules and the machine-name 
  record, the diagnostic records, the PHP floor and the drifted settings file, the machine-path and 
  doc-link readings, the release inventory and the releaser, and four files carried over from 
  upstream - and every one of those groups' work, with the changelog entry its own commit wrote, is 
  already in the commits this branch carries. No behaviour moves, and this entry is the whole of what 
  its commit contains.
- **A guard shaped like a program is held too: one rail is taken out of `bin/release.php`, and the
  rails' own test has to go red on the run that asserts it.** The harness held readings two ways — a
  break planted in the tree, and a detector rewritten under its own tests — and declined the guards
  whose shape is a program, because there was nothing to break and ask again. That was true only
  while no fixture could be handed a rewritten program: `ReleaseRepo::mutated()` and
  `RC_RELEASE_FIXTURE_SCRIPT` are that hand, so the branch rail's condition comes out of the program,
  the rewritten releaser reaches `tests/Unit/Release/RailsTest.php` through that variable, and the
  run has to go red carrying the rail's own sentence — with the same run under the program copied
  verbatim as the control. A test cannot be handed a program as an argument, which is what the
  variable is for. `tests/Unit/Release/RailsTest.php` declares a row of its own in `docs/guards.md`
  for the same reason, so the guard the index records is one this harness accounts for, and the two
  declines that remain now say why their fixtures cannot be handed one rather than that a program
  cannot be held.
- **The release fixture can be built from a script a test names, so a rewritten releaser can be put in
  front of the rail's own test.** `ReleaseRepo::make()` takes an optional script, `mutated()` is the
  named way to ask, and `RC_RELEASE_FIXTURE_SCRIPT` is how a child process hands one to a test that
  builds its own fixture. The script arrives as a source rather than a path because a mutation is a
  rewrite, it is part of the template key because the fixture is committed and tagged the moment it
  is planted, and a variable naming a file that is not there throws rather than falling back — a
  silent fallback would run the unmutated program and report the rail as still held. Nothing in this
  tree calls it yet: it is the fixture half of the harness's other direction, the one that holds a
  guard shaped like a program rather than a reading.
- **A layer step refuses the token left standing where an interpreter goes.** `verify.py` already
  refused an interpreter's *name* written in front of a command, which runs whatever that name is
  first on `PATH`. The token that stands for this machine's PHP is the same failure one
  word further out, and it was only a rule in prose: nothing stopped `__PHP_EXE__ bin/checks.php`
  being written into the layer, where a reader copies a line the shell hands to a program called
  `__PHP_EXE__`. The new `interpreter-token` step reads every file of the layer for a token that is
  neither quoted nor resolved and is followed by a word naming a file; a token in backticks is a
  reader being told what it is, and `__PHP_EXE__ is this machine's PHP` has no program after it, so
  both are left alone. The rule is platform-free, because a token is spelled the same on every system
  and so is the rendering that replaces it, and `--selftest` plants the bare line in `.agents/README.md`
  and requires it to be named.

- **The branch releases are cut from has one source of truth, so a runbook cannot drift back to
  `main`.** The lane was written down four times and three of those had drifted
  to `main` at once — the short version, the by-hand push and the prerelease examples in
  `RELEASING.md` and `PUSHING.md` — and nothing noticed, because a runbook is not run by anything
  and because `main` is what `bin/release.php` *defaults* to: every drifted command still read as
  one somebody would run, and each stops at the branch rail the moment it is. The lane is now read
  out of `.release-lane`, one line holding the branch, which `bin/release.php` reads as the branch it
  releases from and the workflow reads in a step to require that the branch its trigger gates is the
  lane. That is what made the copies go away rather than need watching: nothing has to pass the flag
  any more, so `--branch=development` is gone from both runbooks, `AGENTS.md`, the release command and
  the skill, and the lane is instructed in exactly one place. What is left to hold is what still names
  a branch — a `--branch=` a runbook mentions, a `git push` to this repository's own remote, and the
  workflow's literal trigger, which cannot read the file because GitHub evaluates `on.push.branches`
  before any step runs. A sentence that mentions `main` is left alone, which it has to be, because the
  runbooks explain what the default is — and the default is no longer written into the program at all:
  it is the file, or `main` where a repository declares none. The reading throws rather than agreeing
  with a file that names no branch, or with one it could not read; `tests/Unit/Docs/ReleaseLaneTest.php`
  asserts the clean tree and then the shapes it must still tell apart; a mutation plants the by-hand
  push back on `main` and requires the drift to be named; its detector is inverted with the rest of
  the readings; and a markdown change now carries it in the hook, out of `tests/Support/TestMap.php`,
  because the commit that edits a runbook is the only moment the drift exists to be caught.

- **The mutation harness is inverted, so a guard whose test has stopped asserting fails as well as
  a guard whose reading has stopped looking.** A mutation breaks the tree and requires the reading
  to name the break; what that cannot see is a test whose expectations a reading that reached
  nothing satisfies — a call that answers empty both before and after, a walk that found no file.
  So each reading's own detector is now rewritten in place — a pattern that cannot match, a walk
  that returns before it descends, a marker no longer recognised — and that guard's tests are run
  against it and have to go red. The rewrite is planted in an overlay directory that a bootstrap
  prepends to Composer's PSR-4 mapping for `Tests\Support`, so the guard's tests run in a child
  process with every other class resolved from this checkout; a control run, every reading copied
  in verbatim, is asserted green so the rewrite is the only difference between the two answers.
  All eleven readings with a detector of their own are inverted, and a rewrite whose needle stopped
  matching raises rather than planting nothing.

- **The mutation harness now covers the three readings it used to decline, so the guard that keeps
  the other guards honest is itself quiet-proof.** `Docs::broken()`, `ConfigKeys::unknown()` and
  `ToolPaths::copies()` are new public readings: each is the walk the docs-link, config-key and
  tool-path guards assert on, moved out of the test and into the support class the guard is about,
  so one definition of it serves the guard and the harness. Three mutations were added — a broken
  link planted in a new record, a reader of an unpublished key planted in `src/`, and a tool's path
  written down in `bin/checks.php` — and each requires the guard's own reading to name the break on
  a copy of this checkout. `UNAUDITED` now names only the two guards that are programs rather than
  readings, and the harness's own test.

- **The gate now runs the agentic layer, so a reading that has stopped checking fails the build
  rather than passing quietly.** `bin/checks.php` gained an eleventh check, `agentic`, which runs
  `.agents/verify.py` twice: `--layer`, which reads this repository's account of itself - the
  canonical rules and their sections, the pointer files, the index, the generated skill targets, the
  rendered command table, the interpreter rule, the tokens and the skill descriptions - and
  `--selftest`, which plants one fault per reading in a copy of the layer and requires the reading to
  name it. The second half is why the check is in the gate at all: a reading that has stopped
  checking reports the same clean answer as one that works, and until now nothing CI ran could tell
  the two apart. It is a tree-shaped question, so `--staged` does not ask it, and on a runner
  `--require-all` turns a machine with no Python into a failure rather than a lost reading. The
  suite's own count of the gate's checks moved with it, and `tests/Unit/Gate/ChecksTest.php` drives
  the skip a tree without a layer takes.

- **A commit of a named set now goes through one guarded path, and carries only what it was told
  about.** `bin/commit.php` reads the index before staging anything and refuses when it holds a path
  the named set does not name, so a colleague's staged work cannot ride along on a commit that does
  not mention it. It then writes the changelog bullet under `## Unreleased`, in the `### Added`,
  `### Changed` or `### Fixed` section the change belongs to, runs `bin/checks.php --staged` - the
  same suite the pre-commit hook runs - and commits exactly the named set. A refusal changes
  nothing: the index keeps the state it was found in. The workflow is the `package-commit` skill
  (`.skills/package-commit/`, generated into `.agents/skills/`), whose commands are `commit`,
  `commit-dry-run`, `list`, `staged` and `verify-setup`, and the program is held at a 70% coverage
  floor by `tests/Unit/Commit/CommitTest.php`.

- **A verification run ends with one health table, so a red run says which part of the machine
  moved.** The `tools` step asks PHP, Composer, Python, PowerShell 7, git and every tool in
  `bin/tool-paths.php` for a version and holds each answer to the pin the manifest records, and it
  used to discard those answers once it had decided them - so a reader with a red run had to match a
  finding's wording back to the tool it was about to learn which one had moved. It now records a row
  per tool as it goes and prints them together when the run ends: the tool, the version found, the
  pin it was held to, and whether it ran at all - so a fresh clone with no `vendor/` says `no`
  against every manifest tool rather than leaving that to a note. The table is one block after the
  notes and above the closing count, and it is silent when the `tools` step was not run.

- **An interpreter written in front of a command is refused, the way a `vendor/bin` shim already
  is.** The shim is a second interpreter because it runs whichever `php` is first on `PATH`, and a
  command written with one in front of it says the same thing with the shim left out - which is why
  the four commands that need an interpreter render it out of `machine.local.json` in the command
  itself, and why every program here runs under `PHP_BINARY`. `python .agents/verify.py`'s
  `toolchain` step reads the layer line by line for that shape and names the file, the line and the
  command, with `@php` left alone because it is Composer's own word for the interpreter it is already
  under. The rule is **decided per platform and declared that way**, because an interpreter is
  spelled differently on each: Windows carries an extension on the name and a shell of its own
  (`php.exe`, `pwsh.exe`, `powershell`, `cmd`) and calls a program `.php`, `.ps1`, `.bat` or `.cmd`,
  while Linux and macOS spell PHP as `php` and a program as `.php`. Two of those answers coincide
  today and are kept apart anyway, since the platform is the axis of the rule rather than a
  convenience - a Linux rule that started refusing `bash -c '<line>'` would be refusing the way every
  command is run there by mandate. Each finding names the platform it was found on, because a line
  this refuses on Windows is a line a Linux reader would not write that way at all. One program is
  excused, and it is the one that cannot be written otherwise: `bin/bootstrap.php` runs before there
  is a machine file for the answer to come from, so the command that writes one names an interpreter
  by necessity. `--selftest` gains a round for it - the restatement planted in the index a session
  copies a command from, which every platform's rule reads - so the step with two answers now has two
  breaks rather than one, and a round that proves the step fires proves *which* reading fired.

- **A fresh clone is set up by one command, and a test proves it from an empty checkout.** Three
  things a checkout needs before it can answer anything had a command each and an order between
  them that a reader had to get right: `.agents/render_local.py --init` names this machine's PHP,
  `composer install` puts the dependencies in place, and `python .agents/verify.py` runs the tools,
  the two path guards, the gate and the layer over the result. `php bin/bootstrap.php` is those
  three in that order, numbered as they run, and it stops at the step that failed rather than
  reporting the clone as ready - a machine file that could not be written, an install that did not
  finish, and a verification that reported a finding each end the run with a status a caller can
  read. The interpreter it names is the one running it, never a `php` found on `PATH`, which is the
  failure `.agents/php-bin.sh` exists for; Python, Composer and PowerShell 7 are looked up rather
  than written down - the last through the directory its installer uses, because PowerShell 7 does
  not always put itself on `PATH` - and any of them can be named instead with `--python`,
  `--composer` and `--pwsh`. `--dry-run` prints the three steps and changes nothing, `--skip-install`
  and `--skip-verify` run fewer of them, and `--force` is the only way a machine file somebody has
  already written is replaced. `tests/Unit/Bootstrap/BootstrapTest.php` drives it in an empty
  checkout: the renderer is the real one, because writing `.agents/machine.local.json` is its job
  and a test of the naming step that stubbed it would be testing the stub, and Composer and the
  verification are stand-ins that journal what they were handed - so the *order* the three ran in is
  read rather than inferred from three files existing, and each can be made to fail so that the
  refusals are reached too.

- **The pre-commit hook runs the suite that covers a changed source file, rather than only the two
  tests a document can stale.** `bin/checks.php --staged` narrowed the gate to syntax, style, the
  docs-link test and the config-claim test, so a commit that changed a program was judged on
  whether it parsed and whether it was formatted, and nothing ran the tests that cover it - which is
  exactly the case `bin/release.php` is: the longest file in the package, covered by the tests that
  plant a git repository and run it against, and found later by hand when a rail broke. The mapping
  now lives in `tests/Support/TestMap.php`, a reading that names the tests for every file under
  `src/` and `bin/` and the two document rules the gate already had, and
  `tests/Unit/Support/TestMapTest.php` refuses a source file with no row and a row that names no
  file - so it cannot go stale the way a list beside the gate would, and a commit carrying
  `bin/release.php` runs the release rails. Nothing was added to `$checks`: the staged subset is
  still the same three, and what the tests check points at is what changed.

- **The push program is held to a coverage floor, and the statements that could close were closed.**
  `bin/push.php` was measured - `tests/Unit/Release/PushTest.php` drives it end to end - but it was
  held to no floor, so the report read `floor 0%` and a regression in it could ship without the floor
  saying so. It now carries a floor of 81%, and the scenarios that were missing are written: `--help`,
  a duration that is not a whole number, an empty `--gh`, a remote that names no URL, a push that
  fails before anything is read, a GitHub remote that aims the reader with `--repo`, and a reader
  that answered something other than a list of runs. What is left is the script's ceiling under the
  counting rather than a gap: 26 of the 29 lines it does not reach carry no runtime opcode (the
  entries of a constant array and the literal arguments of a call written across several lines are
  statements PHPUnit counts per line while PCOV reports the statement once), and the other three are
  the two header lines every script carries and the body of a `proc_open` failure.

- **The tool runner's interpreter note is covered, so `bin/tool.php`'s floor moves up to meet it.**
  `bin/tool.php` printed a line when a run was under an interpreter other than the one this checkout
  names, and nothing had ever driven that branch: a machine whose resolver agrees cannot be arranged
  into disagreeing, so the reading is planted instead - a fixture autoloader that answers with a
  disagreement reaches it - and the note is asserted beside the exit code the tool still returns.
  With that measured the ratchet moved from 86% to 89%, which is the rule every floor here follows:
  a floor is the measurement, and a floor may only rise.

- **A claim a document makes about a config key is judged by the commit that could have staled
  it, rather than by the next full run of the suite.** The config-claim guard already put the prose
  and the source side by side - a docblock or a record that says a key is unread while something
  reads it, which is the drift this package shipped once with `enable_logging`, whose docblock and
  whose README copy both stayed wrong after the wiring made them so. What it did not have was a
  place in the commit: it ran when the whole suite ran, which is after the commit that broke it.
  `bin/checks.php --staged` now runs it when either of the two files a claim is written in is
  staged - the markdown that repeats a key, and `config/response-compression.php`, whose docblocks
  sit beside the keys themselves. A commit carrying neither cannot have changed what any document
  claims about a key, so it is not asked; a commit carrying either is judged before it lands.

  It rides in the staged subset `bin/checks.php` already had, beside the docs-link test, rather
  than as a check of its own or a second program: a tenth check would have made the gate's own
  size - "nine checks in one process", which `docs/guards.md`, `docs/tool-registry.md`, the README
  and `AGENTS.md` all state and `IndexNumbersTest` holds to the `$checks` array - a number to move
  in five places for one answer that is a subset of a check already there. `.githooks/pre-commit`
  is unchanged: it already runs the staged gate, and the narrowing is the gate's to decide.

- **A response the middleware left alone still says it varies by `Accept-Encoding`.** The encoders
  set the header on the bodies they compressed and nothing set it on the bodies they did not, so the
  two representations of one URL reached a shared cache as one resource or as two - either the
  identity body reused for a client that asked for Brotli, or a compressed body handed to a client
  that asked for `identity` and cannot decode it. `CompressResponse::handle()` now returns through a
  `vary()` call on every outcome it can reach once compression is enabled, including the ones that
  are uncompressed for every client rather than only for this one: a small body is small whoever
  asks, and a 404 is a 404 whoever asks. That is deliberately the safe direction rather than the
  precise one, because a test on the exact request-dependent paths would have to be re-derived
  whenever a reason is added, and getting it wrong is a missing header rather than a red build. The
  header is appended to `Vary` rather than replacing it, so an application's own `Vary: Origin`
  survives beside it and a response an encoder already marked is not marked twice.

- **A client can ask for the body as it is, and the header that asks for it is honoured rather
  than matched by accident.** `Accept-Encoding: identity` is the HTTP name for "no content
  coding", so it is read as a request this package can grant: the response goes out with no
  `Content-Encoding` and nothing applied to its body. What made it worth writing down is what the
  old reading could not see — `Request::getEncodings()` answers with the coding names alone,
  sorted by quality with the quality discarded and the case kept — so `identity;q=1.0, gzip;q=0.5`
  arrived indistinguishable from `gzip` first and `identity` second, and the body was compressed
  for both of them. `src/Support/AcceptEncoding.php` reads the header itself as a quality per
  coding, and `identity` is a veto rather than a candidate: named with a quality above zero and no
  lower than the encoding on offer, it decides that nothing is compressed, and a tie goes to
  `identity` because a client that is indifferent between the two should not have to write a
  quality to say so. An `identity` the header does not name is still not a preference, so the
  ordinary `br, zstd, gzip` is untouched, and `identity;q=0` — the client refusing an unencoded
  body — is the opposite of a request and keeps compressing. The qualities on the other codings
  are deliberately still not read: that order is the package's own preference list, and a header
  most clients write as an unordered list of what they support is not a ranking. See
  docs/accept-encoding-identity.md.
- **`which PHP` is answered in one place, and the four things that used to answer it ask instead.**
  `.agents/php-bin.sh` — `composer which-php`, or `sh .agents/php-bin.sh --show` — resolves the
  interpreter in the order the hook used to keep privately: `$RESPONSE_COMPRESSION_PHP`, then
  `.agents/machine.local.json` through `render_local.py`, then `git config`, then `PATH`. It reports
  which rung answered, so a fallback cannot pass for a decision, and `--selftest` plants a fault per
  rung and requires each one to be answered — the same thing the `tools` step of
  `python .agents/verify.py` asks it on every run. PHP asks through `Tests\Support\PhpBinary`, the
  same answer read from this side: it says whether the run is under the interpreter this machine
  names, and stays quiet when the rung was a fallback, because a runner's `PATH` is the arrangement
  rather than a second opinion. `--pin` and `--matrix` read the version `composer.json` requires, so
  CI installs the requirement rather than a copy of it, and the new `interpreter` step refuses a
  second copy of the ladder in the hook, of the version in a workflow, or of the resolution in any
  PHP file but the reading.

- **The repository can be asked whether the tools it needs still work.** `python .agents/verify.py`
  runs thirteen readings in two halves, and only one of them is about the agentic layer. The `tools`
  step asks programs rather than reading files: which PHP is being used here and does its version
  answer the pin in `.agents/toolchain.json`, are the three extensions `composer.json` requires
  loaded, can a coverage driver count a line for the floor, what composer reports, is Python new
  enough, is PowerShell 7 where the Windows snippets expect it, is git runnable, does every tool in
  `bin/tool-paths.php` still start through `bin/tool.php` — which is the process rather than the tool
  — is the hook this repository documents actually enabled in this clone, and is there a `vendor/` at
  all to run any of it from. The layer's ten readings follow, and the package's own two path guards
  and its gate are steps of the same run rather than something it re-implements. `--layer` is the
  fast subset the pre-commit hook now runs, `--package` is the tools and the two package readings, and
  `--only=STEP` runs one. The first run of it found the check's own bug: Composer colourises
  `--version` even when it is writing into a pipe, so the version arrived as an escape sequence and
  was read as no version at all — the same reading `bin/checks.php` already makes with its own
  `stripAnsi()`. A missing toolchain is a finding here rather than a skip, because "the processes this
  package needs do not run on this machine" is the answer to the question this step was asked, and a
  missing `vendor/` is a note naming the one command that fixes it rather than a silent pass.

- **The repository carries its own rules, and the rules are checked rather than trusted.**
  `AGENTS.md` is the canonical file: what this package is, the gate and what each of its nine checks
  proves, the two path guards and the rule that a guard may not go quiet, the code and documentation
  rules, the release lane and the two runbooks, and how a commit is made. Ten pointer files name it
  and restate nothing — `CLAUDE.md`, `GEMINI.md`, `.trae/TRAE.md` and `.trae/rules/project_rules.md`,
  `.cursor/rules/agents.mdc`, `.continue/rules/00-agents.md`, `.junie/guidelines.md`,
  `.kiro/steering/package-rules.md`, `.github/copilot-instructions.md`,
  `.windsurf/rules/000-agents.windsurf.md` — so a rule is edited once and cannot come to mean two
  things. `.agents/` holds what a tool loads: two skills generated from `.skills/` (`package-dev`
  for the daily loop, `package-release` for weighing, cutting and pushing a version), a
  `code-reviewer` agent that reviews against this package's semver promise rather than generic
  Laravel advice, a command each for the gate and a release, and `toolchain.json` as the single
  statement of every command and every pin — section 13 of `AGENTS.md` is rendered from it, and a
  command written out a second way is refused. `verify.py` is what makes the layer checkable: the
  canonical sections and the pointers to them, the index of everything loadable, each skill target
  against the source it was generated from, the three pointer mechanisms, the pins against
  `composer.json`, and every `__TOKEN__` against the four that exist. `--selftest` plants one fault
  per step in a copy of the layer and requires the step to name it, because a check that stopped
  checking reports exactly what a working one reports on a tree with nothing wrong: 9 steps, 9
  mutations, all reported. What was deliberately not copied from the application this package is
  developed beside is recorded in `.agents/toolchain.json` — its deploys, its push infrastructure,
  its MCP servers and its application layout rules describe a Laravel application, and none of them
  acts on a Composer package.

- **The index of the guards is read, so the next guard cannot be added without a place in it.**
  `docs/guards.md` is where a guard is put in front of whoever adds the next one, which makes it
  the one file in this repository whose drift is every guard's drift. One direction of that was
  watched already — a row that links a test which has since been renamed fails, because a row's
  link is a link like any other — and the other could not be watched at all, and no test could have
  watched it, because none of them knew what a guard is: a guard could arrive with no row and read
  as though it had always been in the list. `tests/Support/GuardIndex.php` reads the table as a
  claim about a directory: every row's `Where` cell is resolved, and every file in
  `tests/Support/` — 17 of them, read from the directory rather than listed beside it — is named on
  the page, as a reading in the section that lists them or as support in the section that says it
  is not a guard. A new file there therefore costs one line of the index, and that line is the
  decision the index exists to record. The two rows whose `Where` cell said "the same workflow" in
  prose now link the workflow they meant, because a row that resolves nothing sends nobody; and
  the page's two claims that this package sat beside another are stated as what they are — a
  companion package rather than a sibling, a sibling being a layout claim like any other. A link is
  read out of a cell by `Docs::linksIn()`, which the docs guard now reads a document through as
  well, so the one thing a link means cannot come to differ between a cell and a file. 4 tests.

- **A guard that stopped checking anything now fails here instead of reporting nothing forever.**
  Every reading in this package answers "nothing is wrong" while it is working, and the same thing
  once it is not: a pattern loosened until no path matches it, a class of file dropped from a walk,
  a comparison changed to the operator that answers yes — all of them leave the suite green, because
  a guard that reports nothing on a tree with nothing wrong is doing what it says on the tin. What
  each reading is held to already is its own substance, which catches the half of that where the
  input went empty: a glob that stopped matching, a listing that answered with nothing. What nothing
  could catch is a reading that still has its input and stopped asking the right question of it, so
  `tests/Support/MutationHarness.php` breaks this checkout on purpose, one way at a time, and
  requires the guard's own reading to *name* the break — an empty answer from a broken tree is a
  failure here rather than a pass. Six mutations, each on a copy of this checkout with a git index
  of it: a machine path planted in a file of its own, a climb out of the repository beside it, a
  version the changelog has released named above its first heading, a default moved in the config
  file and left alone in the README's copy, a docblock that says nothing reads a key the source
  reads, and two branches made to write the same log line. The reading is the call each guard's test
  asserts on rather than a second implementation of it, which is why four guards are declined by
  name and reason instead: the docs links, the guard index, the config keys and the tool paths each
  compose their reading as a walk inside the test, and a second walk here would be a guard of its
  own. The set is read from `docs/guards.md`, and both lists are read back against it — every guard
  the index records is mutated or declined, nothing is accounted for that the index does not record,
  and every file named is still there — so a guard added to the index fails this harness rather than
  being asked nothing. A mutation that stopped changing anything raises rather than planting nothing,
  so a needle that no longer matches is a failure instead of a green reading. 9 tests.

- **A climb out of the repository is a finding, which is the half of the question the machine-path
  guard could not see.** `.idea/php.xml` named Pint and PHPStan by climbing out of the checkout to
  reach them, and it read as a path to everything that looked at it: it names no drive letter, no
  home directory and no share, so the machine-path reading had nothing to fire at, and it is not a
  link, so the docs guard had nothing to resolve either. It was found by moving the checkout, and
  fixed by hand. What a relative path leaks is a layout rather than a machine — the one position a
  checkout has to occupy for the path to resolve — so the reading is a *walk* rather than a pattern:
  each path token is walked from the depth of the file that wrote it, and only a walk that ends above
  the root is a finding. That is what lets a record in `docs/` keep its own link up a single level
  while the identical climb written in a file at the root can only be a climb. The IDE's project
  marker starts a walk at the root wherever it is written, and `dirname(__DIR__, n)` is walked too,
  because a guard that can be walked around by writing the same location in the other language is a
  guard that will be. The listing and the binary test come from `MachinePaths` rather than a second
  copy of either, so the two readings cannot come to disagree about which files a commit would carry.

- **The same drive is read in the spelling the other half of this repository writes it in.** The
  machine-path guard was written around a drive letter, so `/e/workspace` - the same directory, as
  MSYS and Git Bash mount it - was not a finding at all. That is the half a
  sweep walks past: this repository's own runbooks and batch files are written in the shells that
  spell a drive that way, so a rewrite out of a drive letter produces exactly the shape the rule
  could not see. `tests/Support/MachinePaths.php` now reads three mount roots - the bare letter,
  WSL's `/mnt/<letter>/`, and Cygwin's `/cygdrive/<letter>/` - and tolerates the mount spelling of
  the one directory that names nobody (`/c/Windows/...` is `C:/Windows/...`) through the same rule
  rather than a second one that could drift from it. Two boundaries are deliberate: the segment
  after the root has to be a single letter, which is what keeps a container path
  (`/mnt/laravel/html`) out of it, and there has to be a segment after that, because no rule can
  tell a one-segment `/a/b` in a sentence from prose. The mutation harness plants both spellings
  now, so the half that was missing is a reading that has to stay awake rather than a sentence in
  this file.

- **A floor run says which PHP it is under, and whether anything can count a line.** Both are
  reasons a floor comes back red on a machine with no regression in it: the recorded floors were
  taken under one interpreter while `composer test:unit` runs the script under Composer's own, and
  with no PCOV or Xdebug loaded every file measures 0.0% and every floor reports the whole package
  below itself. Neither is visible in the number, so `bin/coverage.php` opens by naming the
  interpreter — its version, its path, and whether Composer is what started it — and the coverage
  driver, or the sentence saying there is none and the one line that would give this machine one.
  The same facts are repeated at the foot of the two failures that are about a floor, because a red
  build is read at the bottom of that output rather than at the top, and a run that collected
  nothing now takes its temporary directory with it, where it used to `exit(1)` straight past the
  cleanup. The decision lives in `tests/Support/CoverageEnvironment.php` and is asserted branch by
  branch, including the two a runner cannot be made to have: a PCOV that is loaded and switched
  off, and an Xdebug left in `develop` — each a driver that is present and cannot count.
  And the facts are asked of the interpreter that will measure rather than of the one the command
  happened to run under: the suite is started as a child with none of this invocation's flags, so
  `bin/coverage.php` starts a second copy of itself in a probe mode, hands the four raw facts it
  prints to the same reading, and names the environment that will count the lines — a `-n` run now
  names the driver its child has, where it used to name its own absence. When that answer is that
  nothing can count, the suite is not started at all: a floor of 0.0% for every file is not a floor
  that was missed, and two minutes of tests to reach it is a red build pointing at the code instead
  of at the machine.
  The foot line of a failure carries every fact the header states rather than the two about the
  interpreter alone: who started the run is what makes the PHP named beside it the PHP Composer
  runs under, and a red CI step is read at its foot rather than at its top.

- **A guard for the claims the documents make about the keys.** `ConfigKeys` compares the keys
  the config publishes with the keys the source reads, and that comparison cannot see the third
  place a key is described: the prose. `enable_logging` was published, read by nothing, and its
  docblock in the config file and in the README both said so — the wiring made the comment
  wrong and nothing noticed, because a claim and a reader are each correct about themselves.
  `tests/Unit/Config/ConfigClaimsTest.php` reads the claims out of the docblock beside each key
  (which `ConfigDoc` now reads in the same walk as the key) and out of the README and the design
  records, and fails when one of them says a key is unread while the source reads it — naming
  the reader, the file and the line. A sentence that accounts for the key having been wired
  ("read by nobody, until the middleware was wired to it") is read as the history it is, and the
  reader is exercised on that distinction, because a guard whose green tick is a scan that found
  no claims at all is a guard that would look the same if it were broken.

- **A removal the notes leave out is refused, rather than only weighed.** The surface signal and
  the inventory each read a symbol that is gone and each weigh it as breaking, so the release takes
  the right number — while the notes, which are what a consumer upgrades on, can still file the
  change as a fix. `bin/release.php` asks both readings before it commits: when they agree that a
  public symbol was removed and `## Unreleased` declares no removal, the release stops, naming the
  symbol and the reading that saw it, and `--ignore-policy` is what publishes notes that thin. Both
  have to agree, because a public constant or property is a symbol the inventory has no column for:
  a removal only one reading saw is left to the notes rather than turned into a refusal the next
  person works around.

- **The changelog's opening paragraph is read against the file it opens.** It explains the
  numbering by naming the versions that are not part of it — the two inherited from the upstream
  this package was taken from — and names in prose go stale quietly. `0.1.0` is the next release
  here, so the file is about to carry a released `0.1.0` while that paragraph still calls the
  number someone else's, and nothing in the suite reads an opening paragraph.
  `tests/Unit/Docs/ChangelogPreambleTest.php` reads the versions named above the first heading and
  the versions the file has a second-level heading for, and fails when one is both. The level is
  the whole distinction: the inherited `0.1.0` is a third-level heading under `## Inherited from
  upstream`, which keeps the number readable and searchable without making it a release of this
  package.

- **Every skip the middleware reports is one diagnostic, with a stable code in front of it.** Two
  of the middleware's checks sat next to each other and both wrote the same line, so an
  unsuccessful response was recorded as a binary file — in the one output `enable_logging` exists
  to produce, which is where anyone looks to find out why a response was left alone. It was found
  by reading the file and fixed by hand, which is the kind of fix that comes back, because a test
  that pins a message pins it one branch at a time: a third branch copied from one of them passes
  every one of them. The middleware no longer holds the text of a line at all. Each branch names a
  `Uak35\ResponseCompression\Support\Diagnostic`, that reason's sentence is written once in the
  type, and every line the log holds carries the reason's code — `request.identity`,
  `response.too_small` — in front of the sentence, so two lines can never be read as one another
  and a branch cannot invent a wording. That closes the defect by construction; what is left is the
  two ways the one file can still be written wrong, and `tests/Unit/Middleware/LogMessagesTest.php`
  reads them out of the file itself: two cases carrying the same code, which nothing in PHP refuses
  because the duplicate-value error only arrives when the enum is asked for a case by value, and
  two cases writing the same sentence, which is the original defect one file along. Both are
  reported with the cases and the code or the sentence they share. The reading is held to more than
  its own collision rules: it has to find every case and every sentence rather than the ones it
  understood, it throws rather than reporting agreement with a file it never read, and the codes
  and sentences it read are required to be the ones the type declares, so the reading and the type
  cannot come to disagree.

- **A tool's path is written down once, in a manifest the scripts and the gate both read.** It was
  written down twice — once in `composer.json`, because a script is a shell string and cannot read
  a manifest, and once in `bin/checks.php`, which cannot be asked for its list because it runs on
  include — and the two copies disagreed without either path looking wrong: `vendor/bin/pest` and
  `vendor/pestphp/pest/bin/pest` both read as Pest. `bin/tool-paths.php` is now the only file
  holding a path. A composer script names `bin/tool.php` and the tool — `@php bin/tool.php pint` —
  and `bin/tool.php` looks the path up, runs the tool from the package root under `PHP_BINARY`, and
  returns the tool's own exit code, so a script that runs one tool means what that tool means. The
  gate asks the same manifest, and a tree whose manifest is missing has no tools rather than a
  fatal out of an include. `tests/Unit/Support/ToolPathsTest.php` reads what a single file cannot
  check about itself: a path written down again in a script or in one of the three programs that
  runs a tool, a `vendor/bin` shim, a path that is not on disk, a name nothing asks for, and an
  entry nothing runs. The runner's own refusals — no manifest, a manifest that names nothing, a
  name it does not have, a tool that is not installed — are driven in
  `tests/Unit/Gate/ToolTest.php`, and the file is held to a floor of 86% with the rest of `bin/`.

- **The runner is held to the gate it is meant to run, rather than to a list of the gate's tools.**
A tool can be invoked in three places — a composer script, one of the three programs in `bin/`, and
`.github/workflows/main.yml` — and the third had no reader, in the file that decides what a published
version was gated by. `tests/Support/ToolPaths.php` now parses that workflow rather than searching
it, because the file discusses the gate in its own comments and a note is not a command: the two
commands that make CI the gate a tag is cut behind — `composer checks -- --require-all` and
`composer test:unit` — are stated once in `ToolPaths::CI`, with the argument `--require-all` beside
the script that has to carry it, and the steps the workflow really runs are held to them. A runner
that dropped the gate, ran it without `--require-all`, or stopped enforcing the floor is a finding
rather than a comment, and a tool's path or its `vendor/bin` shim written into the workflow is the
same second copy the rest of this guard is about. The reading refuses a workflow it could not parse,
one with no `jobs`, and one with no step that runs anything, because each of those would otherwise
read as a runner that still runs the gate.

- **The two scripts in `bin/` nothing could reach are measured, and every script there is held to
  a floor.** The two were the only code in the repository nothing read: both run on include, both
  call `exit()`, and the release one commits and tags, so the suite reaches them the only way it
  can — by planting a repository, copying the script into it and running it as a child process —
  and the coverage a child collects is its own. The
  floor therefore reported 100.0% of `src/` while reading 0.0% on both scripts, with sixty tests
  driving one of them end to end. `tests/Support/collect-coverage.php` is handed to every child as
  `auto_prepend_file` when `RC_SCRIPT_COVERAGE_DIR` is set, and writes what that process covered at
  shutdown; `bin/coverage.php` — which `composer test:unit` now runs in place of Pest — maps each
  copy back onto the file it came from by comparing contents rather than names, adds those lines to
  the report, and then reads the floor: 100.0% for every file in `src/`, judged on its own rather
  than as one average, and for each script a recorded floor that may be raised and not lowered.
  Those floors are 54% for `bin/checks.php`, 78% for `bin/release.php` and 86% for `bin/tool.php`,
  a point or two under what they measure today and written down so the next change cannot quietly
  spend them. Two files in `bin/` are outside the report and outside the floor: the runner, which
  is the parent of the run it reads, and the manifest, which is a list rather than a script — a
  `return [...]` whose lines PHPUnit counts as statements while PCOV reports the statement once, so
  it can never read above a seventh however many processes read it. What can be wrong with the
  manifest is which entries it holds, which the tool-path test asserts. The margin is what the
  environment costs: the gate decides whether to run `composer validate` by looking for a Composer
  it can reach, so a run through `composer test:unit` covers the check and a run of the runner
  directly covers the skip — the same tests, ten lines apart.

- **The gate is tested, which is where most of that came from.** `bin/checks.php` is the script
  everything else is measured by, and no test had ever run it: the release rails plant a three-line
  `exit(1)` stub wherever they need a gate that fails on cue, which is the right stub to plant and
  the reason the real gate read 0.0%. `tests/Unit/Gate/ChecksTest.php` runs it in a planted tree —
  `--list`, an option it does not have, a file that does not parse, a tool that is not installed
  with and without `--require-all`, `--staged` on a tree with nothing staged, and a tree with no
  manifest at all — taking it from 0.0% to 55.4% of its lines and pinning the two codes a caller
  has to be able to tell apart: 2 for an option it does not have, 1 for a tree that fails.

- **The numbers the index states are read out of the code that produces them rather than written
  down and trusted.** The two lists under the table are rendered now, so a file that moves cannot
  leave a stale row; the numbers in the prose were left out of that, and a number in a sentence is
  the same defect one layer up. `IndexNumbers` reads "nine checks in one process" out of the
  `$checks` array in `bin/checks.php`, the floor every file is held to out of `bin/coverage.php`,
  and the counts the page is about — the guards the table records, the two lists' 15 readings and
  4 files, the 19 files in `tests/Support/` and the 11 `docs/` records — out of the directory and
  the table itself, then compares each with the sentence that states it. The phrase that names the
  gate's size is read wherever a commit would carry it, so the README, the rules, the gate's
  command and the workflow cannot go on saying nine while the gate runs ten. A number that is no
  longer stated at all is a finding rather than a silence, which is the rule `GuardIndex` already
  makes about a row. It reports rather than rewrites, because a number inside a sentence is prose
  rather than a rendering — and `tests/Unit/Docs/IndexNumbersTest.php` pulls the document and the
  code apart one number at a time to prove each of them is read.

- **The guard index checks its other side, so a guard test cannot land without a row either.** The
  two lists in `docs/guards.md` are a rendering of `tests/Support/`, so a reading cannot be added
  without a place in the index — but the guards' *tests* had no such rule, and a guard could arrive
  with a test, a reading and a real defect behind it and read as though it had always been part of
  the list. Every test under `tests/Unit/` now declares `@guards-index guard` for a test the table
  records or `@guards-index test` for a test of the code, and `GuardIndex` refuses one that declares
  nothing, because a guard that forgot and an ordinary test that stayed silent are the same file from
  the outside. A declared guard whose test the table's `Where` cells link nowhere is a finding from
  both `bin/index.php --check` and `GuardIndexTest`. The table stays hand-written — what a guard
  refuses is an argument — so the row is a person's to write and the declaration is the file's own;
  the writing form of `bin/index.php` still repairs only the two lists it renders.

- **A push is not done until its run is read.** `composer push` pushes the current branch and
  then waits on the GitHub Actions run that push started: it finds the run for the commit it
  pushed through `gh` — already authenticated on the machine, so no token is held and none is
  written down — prints the run's URL, and exits 0 only when that run concluded `success`. Every
  other ending — a failure, a cancellation, a timeout, a reader that could not be asked, or a
  push that started no run at all — exits 1 and says what it read, because a push reported as
  done at the moment `git` returns is a claim about the future. A push that built nothing is a
  refusal rather than a pass, since "nothing ran" is not the same answer as "it passed".
  `bin/release.php` already assumes the run exists — its CI rail refuses a commit that is one
  *nothing has built* — and this is the other half of that sentence: the commit is built, and
  here is whether it passed. The command is `bin/push.php`, declared as the `push` composer
  script: `--branch`, `--remote`, `--gh`, `--timeout`, `--interval` and `--grace` shape the
  wait, `--dry-run` prints the plan instead, and `--no-watch` pushes and says plainly that the
  run was not read. The reader is a command rather than a hard-wired client, which is what lets
  the tests stand in for `gh` and read a run that never touches the network.

- **The tools an agent runs have a registry of their own.** `docs/tool-registry.md` is a
  four-column account of the agentic layer — where each tool lives, what it does, and which
  file calls it: the rules in `AGENTS.md` and the pointers to them, the `.agents/` layer (the
  resolver that names this machine's PHP, the renderer, the check, the skills, the agents, the
  commands), the gate and the five other programs in `bin/`, the pre-commit hook, the workflow,
  and the two runbooks. It deliberately does not restate the two lists that already have an
  owner — the guards in `docs/guards.md` and the tool paths and pins in `bin/tool-paths.php` —
  because a second copy of either is the drift those two files exist to refuse. The column that
  earns the page is the last one: a tool is only a tool if something calls it.

- **The tools an agent runs have a list as well as a registry.** `docs/scripts.md` is the flat
  inventory of that same layer: the checks in `.agents/`, the generator in `.skills/`, the nine
  programs in `bin/`, the hook and the workflow, each with the command that starts it. The registry
  answers *what a tool is in the chain and who calls it*, and a reader who only wanted to know
  whether a tool exists — and what to type to run it — had to read a four-column argument to find
  out. Three lists it deliberately does not repeat, because each already has an owner: the guards
  and their readings in `docs/guards.md`, the tool paths and pins in `bin/tool-paths.php`, and what
  is loadable in `.agents/README.md`. The README links it beside the registry.

- **This package can analyze its own PHP, which it could gate but never read.** Three skills knew how
  to *gate* the tree — `package-dev` runs the eleven checks, `package-commit` commits a named set,
  `package-release` weighs a version — and none answered the narrower question a reader has with a
  file in front of them: what is wrong with *this path*. `.skills/php-code-analyzer/` is that skill,
  and it is built out of the tools already pinned rather than a new analyzer: `syntax-sweep` lints
  every file with PHP's own linter under the interpreter this checkout names, `analyze-types` runs
  PHPStan at the `max` level `phpstan.neon.dist` sets, `analyze-modernize` runs Rector's dry run over
  the sets `rector.php` configures, and `analyze` runs all six readings and numbers the findings, so
  `analyze-apply` can take one of them. The two readings the package has no tool for — cyclomatic
  complexity and duplicate blocks — are named as readings, in the report and in `recommend-tools`,
  rather than dressed up as a check; a second analyzer would be the copy this package writes guards
  about. The skill is generated like the rest (its target, the `GEMINI.md` import and the Windsurf
  pointer), indexed in `.agents/README.md`, and listed in `docs/scripts.md` and
  `docs/tool-registry.md`. Adding it also closed a break the scripts list was already carrying: two
  of its command cells wrote `php` in front of a program, which the interpreter rule refuses wherever
  it is written, so they render the interpreter from `.agents/machine.local.json` now — a failure the
  layer's `toolchain` step names and nothing had asked it about, because a docs-only commit runs
  neither that step nor the hook's layer subset.

### Changed

- **`bin/tool.php` hands a tool that asked for a pool the width this package reads, so a run through the wrapper stops asking for workers this machine will refuse.** The wrapper's job is to pass a tool's own arguments through, and `--parallel` is the one argument that has to be added to rather than passed: pest reads it as paratest's pool and defaults to one worker per core, while a worker of this suite is several processes — most of this suite is a spawn of its own — so the request arrives as more starts than the machine will make, and what comes back is not a failure but an error, `proc_open(): CreateProcess failed`, from files that pass when they are asked one at a time (§8). The size is therefore read from the one place this package reads it, `Tests\Support\Workers`, which is the same answer `bin/checks.php` and `bin/coverage.php` hand pest when they start a pool of their own — one definition of the width rather than three. Nothing is added when the tool is not pest, when `--parallel` was not asked for, when the caller named a pool of its own (`-p` or `--processes`), or when there is no autoloader to read the width from, which is every fixture behind `ChecksTest`. `docs/tool-registry.md` says so in the tool's row, and `tests/Unit/Gate/ToolTest.php` holds both halves by reading the arguments the fixture's tool wrote down — the pool planted rather than taken from this machine's core count, and a caller's own pool left alone. Measured: `ToolTest` 6 passed (47 assertions).
- **The sentence a refused start gave is taken by a handler `Processes` installs for the one call, so the warning is neither printed nor left for a runner to fail a run on.** `@proc_open()` stops PHP from printing the warning and nothing else: the message is still raised, a runner's handler still sees it, Pest records it against the test that made the start, and `phpunit.xml.dist` carries `failOnWarning="true"` — so a machine that had simply declined a `CreateProcess` turned a run red, measured here as 42 tests carrying `proc_open(): CreateProcess failed`, every one of them with the child started on the next attempt and the work done. The refusal is now taken the way the suppression could not take it: a handler of the class's own around the one `proc_open()` call, `restore_error_handler()` on the way out whichever way the call went, and what the handler keeps is the sentence `start()` throws beside the command when the patience is gone — so the throw replaces the warning rather than summarising nothing, and a start refused with no message of its own still reads `no reason was given`. `tests/Unit/Support/ProcessesTest.php` holds both halves: that a refusal is not recorded against the test, where the run's own verdict is the assertion because the warning is what would fail that file, and that the handler is put back, with a sentinel standing where the runner's was, because one left installed would swallow every warning after it in a worker. Measured: that file 2 passed (5 assertions), 26.4s of it the patience a start refused every time is waited out for; with `ToolTest` in the same run, 8 passed (52 assertions) in 37.6s.
- **§5's list of uncommitted work is re-taken against the tree it is read in, so the paths it states are the ones `handoff/state.sh` reports rather than four taken at `bb49cb5`.** What the section listed was a reading of 2026-10-27 at `bb49cb5` — `pint.json`, `.agents/skills/package-dev/_meta.json`, this document, and `.idea/vcs.xml` — and four commits have landed since, so a reader comparing the section with the script had no way to tell which of its counts were still about the checkout they were standing in. What it says now is the measurement as it stands: 17 paths against a `HEAD` at `21424aa` on 2026-10-27, 13 of them with a real content diff and 4 untracked, nothing staged; the two paths that are nobody's in this thread are named as such — `pint.json`'s `exclude` list for `external`, `ext`, `build` and `vendor`, and `_meta.json`'s date and timestamp, both written at 17:57:20 on 2026-10-26, two minutes after the sixth of the burst's commits, and both still exactly as they were found; and the other fifteen are given as five groups, one reason each, with what each group is and what its commit will carry — the scratch-copies reading (`tests/Support/ScratchCopies.php` and its test, `docs/guards.md`, `docs/commit-environment.md`), the workbench rail §7 item 13 asks for (`tests/Unit/Workbench/WorkbenchTest.php`, `tests/Support/TestMap.php`, `tests/Unit/Support/TestMapTest.php`), the pool `bin/tool.php` hands pest (`tests/Unit/Gate/ToolTest.php`, `docs/tool-registry.md`), the reason a refused start gave (`tests/Support/Processes.php` and its new test), and the two sentences `21424aa` left out of its set on purpose (`tests/Support/Ignored.php`, `tests/Unit/Support/IgnoredTest.php`). `CHANGELOG.md` is named as the one path with no reason of its own to be modified — the two bullets belonging to the first two groups, the only additions `git show HEAD:CHANGELOG.md` does not have, plus a final newline `4d4c00d` left deliberately — with what that means for a commit that names it, which is why `21424aa` had the tool write its own bullet and leave those three lines alone (`CHANGELOG.md holds 3 line(s) this run did not write, and they are not staged`). The paragraph carrying the predecessor's rule now says what it protects: §3.3 being resolved, none of the four untracked entries is another package's any more, so a blanket stage would put five reasons under one subject and one bullet. §7 item 14's cross-reference to "the fourth path §5 names" is re-taken with it, since that path is neither in today's list nor work at all. Measured: `handoff/state.sh` reads 2 ahead / 0 behind, 0 staged, 17 listed — 13 with a real content diff and 4 untracked — against `21424aa`, which is what the section now states; `HandoffTest` and `HandoffStateTest` 15 passed (52 assertions), so the section's counts stay inside the reading that holds them; and this document is itself one of the paths until that commit lands: the reading above is what the script answers at `21424aa`, where `handoff/HANDOFF.md` holds what `HEAD` holds, so a count taken mid-edit is 18 where the commit that lands the re-take leaves the 17.
- **The records that kept the quarantined machine record present say what became of it now, so nothing here is read as a tree that still holds the package next door's file.** The record for `laravel-weighted-dbmanager` was deleted on 2026-10-27 — `handoff/HANDOFF.md` §7 item 2 carries that decision — and the account of it had been written in three places while it was still here. `.gitignore`'s `*.txt` paragraph said the machine-path reading "reports it ... the colour this repository decided on" for a file that is somebody else's, which was true while the file was a file a commit would carry and false the moment it was not, and the paragraph above `/.quarantine/` read as a list of three files the directory holds; both now say the three are resolved — the record deleted, the two session configs committed as `63a2465` and written from their templates since `4d4c00d` — and that the directory stays as the place the next file that is not this checkout's to commit or delete goes. `.quarantine/README.md` is the third, and it is gitignored: its half of this change cannot be committed and stands in the tree it describes. It still told a reader how to put the two configs back, an instruction `4d4c00d` had already made wrong, since `python manage-config.py` writes the configs the ignore rules hold from the tracked templates; what it says now is that there is nothing here to put back. `handoff/HANDOFF.md` §3.2 led with the outsider as the cause of its four red readings and §3.3 item 2 still stated the file was left in place; §3.2 carries the closure and §3.3 item 2 the deletion, and none of that list's three items describes this tree any more. Two sentences in the reading that asserted the file "is here" — `tests/Support/Ignored.php`'s docblock and `tests/Unit/Support/IgnoredTest.php`'s header — were corrected in the same sitting and are deliberately not in this commit, whose set is the two documents; nothing behavioural moved, and the docblock's first sentence, which `docs/guards.md` renders as the reading's row, is untouched. Left alone deliberately: the changelog bullets that describe the record as present, which are the history of the commits that wrote them. Measured: `IgnoredTest` 5 passed (14 assertions) and adds no `[NOTE] Ignored:` line, so the note a run prints for a foreign record is empty; `MachinePathsTest` 36 passed (40 assertions); `HandoffTest`, `HandoffStateTest` and `GuardIndexTest` 30 passed (166 assertions), so the edits stay inside their readings; `bin/index.php --check` exit 0; `pint --test` on the two touched PHP files, 2 files PASS; and `python .agents/verify.py --layer` 11 steps with 0 findings. `bin/checks.php --staged` prints one note line — the ignored entries at the root — and names no machine record.
- **The two opencode workspace configs are generated from tracked templates rather than carried as files, so this checkout's editor setup reaches the history without a machine path in it.** `opencode.jsonc.template` and `opencode.v1-backup.jsonc.template` are what a commit carries - each the file it was, with the scratch-directory entry replaced by `<__SYSTEM_TEMP_FOLDER__>` - and both rendered configs are ignored beside `/.freebuff/`, because the directory they name is the editor's own and belongs to this machine rather than to a version of this package. `manage-config.py` is the program that fills one in: `SUBSTITUTIONS` maps the token to `systemTempFolder.systemTempFolderPath` and is the whole of the mapping, the angle brackets are consumed with the token so the rendered line names a directory rather than a bracketed one, and nothing is written unless every token resolves - a template naming an unknown token, a machine file that records nothing for the key, and a machine file that is not there are each a refusal rather than a half-substituted config the editor would read happily. Measured: `python manage-config.py` wrote a file byte-identical to the one it replaced and `git diff` was empty; each refusal exercised in a throwaway copy under the ignored `build/`; `MachinePaths` and `RepoEscapes` find nothing in either template or in the script; `python .agents/verify.py --layer` 11 steps with 0 findings. The two findings `MachinePathsTest` still reports are a `.txt` copy of each rendered config, left at the root by another session, and named rather than deleted here.
- **The machine file carries this machine's temporary directory, and `__SYSTEM_TEMP_FOLDER__` answers from it, so a settings file can name scratch space without naming a machine.** `.agents/machine.local.json` gains a `systemTempFolder` section with `systemTempFolderPath`, `render_local.py`'s `TOKENS` maps `__SYSTEM_TEMP_FOLDER__` to that key, `SECTIONS` declares the section and `OWNED` adds it to what `--init` writes - defaulted from `tempfile.gettempdir()`, because a temp folder is a fact about the machine rather than an answer worth asking for. `--init` therefore opens it on a fresh clone instead of leaving a token that resolves to nothing, which is the rule `MachineShape` holds every token to; the committed example shows the key, `AGENTS.md`'s token table names the placeholder, and the handoff's environment section counts five tokens and the file's new size. It is written in the `__TOKEN__` shape the layer's token step and `Handoff`'s pattern read, so it is held like the other four rather than only named in a table. Measured: `render_local.py --value __SYSTEM_TEMP_FOLDER__` prints this machine's temp directory; `--check` exits 0; `MachineShapeTest` 9 passed (70 assertions), `HandoffTest` 6 passed (20), `HandoffStateTest` 9 passed (32), and `python .agents/verify.py --layer` 11 steps with 0 findings.
- **The handoff's open-work list is re-taken against the tree it now sits in, so the thread that reads it next is not sent to work that is already done.** §7 item 2 — the machine record for the package next door — is closed by deleting the file, with the paragraph above `/.quarantine/` in `.gitignore` and `.quarantine/README.md` rewritten to say what was held and what became of each of the three, and the `[NOTE] Ignored:` line every run printed about it gone rather than transferred. §7 item 13 — `bin/workbench.sh` has no test of its own — is closed by `tests/Unit/Workbench/WorkbenchTest.php` and the `TestMap` row a commit carrying the script is pointed at; §7 records what that rail asks, what it deliberately does not, and the relative-path cost of starting a `.sh` from PHP. The set is this document and the bullet that records it, and nothing else: the rail's own files, the two records above and the bullets already under `## Unreleased` are changes with reasons of their own, and each belongs to its own commit.
- **The handoff is re-taken against the tree it now sits in, so the thread that continues this work starts from the ten commits the last two sittings made rather than from the state the predecessor left it in.** `handoff/HANDOFF.md` §5 carries that burst as one narrative — the six of the first sitting (`469b6fd`, `6dd976e`, `7df2de4`, `20e49f6`, `0b548ef`, `afdc56c`) and the four that followed from the work of its next sitting (`f357f20`, `c35e016`, `b1247ef`, `bb49cb5`) — with the shared documents measured rather than assumed: `git show <commit>:CHANGELOG.md` against `<commit>^` is one added line and no removal for each of the four, and the four documents at `HEAD` are byte-identical to the state that sitting authored. The list §5 says is not empty is the four paths no sitting of this work wrote: `pint.json` and the `exclude` list it carries for `external`, `ext`, `build` and `vendor`; `.agents/skills/package-dev/_meta.json` and its date; this document itself, which is why a commit here has to name it by hand; and `.idea/vcs.xml`, the mapping the IDE wrote for the two nested repositories under the ignored `build/rc/` scratch — none of the four recorded under `## Unreleased`, and none of them this thread was to commit or discard. §7 gains the three items the burst left open: `bin/workbench.sh` sits where the other two scripts sit and has no reading behind it, so its refusals are evidence rather than cover; the two leftovers §5 names are set down as uncommitted and unrecorded rather than quietly dropped; and a path whose directory carries a space after a mount root is still read to the space, written down by `bb49cb5` instead of half-fixed, because the parenthesis it taught the mount pattern is the spelling the space has never had. Its push item is re-measured at 109 commits ahead of `origin/development` and 0 behind on 2026-10-27. Measured: `HandoffTest` and `HandoffStateTest` 15 passed (52 assertions) in 62.97s — the two readings that hold this document to `handoff/state.sh` and to `.agents/machine.local.json` — and this commit check green, being the docs-link, config-claim and release-lane tests `TestMap` maps a markdown file to.
- **The gate's retry of the files a pool could not start asks them together rather than one after another, so a storm of heavy files can no longer add a second suite's wall time to a run whose only real finding may be one file.** `runPest()` asked each erred file with a `runCommand()` of its own — the same binary, no `--parallel` — and a boot of pest is the expensive half of that: the framework, the manifest, the suite. The files were never a sequence, though; each is a run of its own, and the shape the original failure had was one file per worker. They now go to `runCommands()`, which already starts every command before reading any (the staged fan-out's own shape), in waves as wide as the pool this tree was given: the width is this package's own answer about this machine, `Workers::count()`, read from the tree rather than chosen here, because asking more of them at once than the pool had would be the load that refused the first start arriving a second time — and a retry that could not start is read as a file that failed alone, so the second failure would be worse than the first: a heal reported as a finding. A tree whose autoloader does not load `Workers` has no width to read and asks every file at once, which is every fixture behind `ChecksTest` — one tree, and no machine to size it. `runCommands()` answers with `results` beside the set's single verdict for the same reason the retry needs it: the caller has to act on one member of a set, because a lone run that came back green is what heals a file, and an aggregate can only say that one of the runs failed — while a member with no answer at all is read as the failure it is rather than as a file that passed. The retry's line says what it did now (`▶ the pool errored on 5 file(s), and each was asked again, alone, 5 at a time` on a tree with no width to read; this machine reads eight for a pool of thirty-nine files), and nothing else about the reading moved: the note, the summary, the threshold that calls more than one a machine, and the rule that a file failing alone keeps the run red are as they were. Held by the shape of the retry rather than by a clock: each lone run of a planted `pest` appends `start <file>`, waits until a given number of starts are in the record, then appends `end <file>`, so asked together every `start` precedes every `end`, and asked one after another the first `end` arrives while the others have not started at all — `ChecksTest` requires that order for three files on a tree with no width to read, and the wave boundary for `Workers::count()`-plus-two files on a tree handed this checkout's autoloader, where no more than the pool's width is in flight and the rest start only after those have stopped. The stub's appends are locked, and one run of ten files measured the need for it: an unlocked `FILE_APPEND` finds the end of the file before it writes, and nineteen lines were recorded where twenty were written. Measured: a fixture of five heavy files whose lone runs take one and a half seconds each reported its `tests` check at 2.1s asked together where the loop it replaced took 8.5s — the gate's own wall time 6.3s against 12.1s — `ChecksTest` 19 passed (75 assertions), the suite 504 passed (1,642 assertions) with `bin/checks.php` at 62.5% of its 688 lines against a floor of 54%, and Pint in test mode, PHPStan at max and Rector's dry run clean.
- **The gate sizes its test pool at half the machine's cores, so a commit's parallel run stops asking for workers the machine cannot feed.** `--parallel` is paratest, and its default is one worker per core — but a worker here is two or three processes, because most of the suite is a process spawn of its own (a planted git repository, a release script started against it, a nested `pest` under an overlay), so one worker per core is a machine asked for several processes per core. What that returns is not a failure: the files whose worker never started come back as errors, `runPest()` asks each of them again alone, and the run ends green with a note blaming the machine — eight files named that way on this machine, every one of them passing alone. `tests/Support/Workers.php` answers before the pool starts: half the cores, never fewer than one, and a platform that reports no core count gets one worker rather than a guess. `Workers::arguments()` is the one place the flags are built — `--parallel` and the size — and both programs that start a pool take them from there: `bin/checks.php`, at both places a pooled run begins, and `bin/coverage.php`, where the same default is a wrong run rather than a slow one. The coverage floor has no retry for a file the pool could not start, so a test that never reached the script it measures leaves that script's lines uncovered: on this machine the uncovered run reported `bin/commit.php` at 0.0% of its 252 lines and `bin/release.php` at 72.5% against a floor of 78%, both of them red on a machine with nothing wrong with it. A tree with no autoloader — every fixture in `ChecksTest`, and the fixtures that run a copy of `bin/coverage.php` — keeps paratest's default, which is the command those tests were written against. The number goes on the option (`--processes=8`) rather than after it: pest rebuilds the arguments before paratest sees them, so `--processes 8` arrives as an option with nothing after it and the run stops on "The `--processes` option requires a value", while `-p 8` is refused as a second path — both measured, and the first of them is how the gate went red on the way in. Measured on sixteen cores, which is the machine this was written on: a pool of fifteen held thirty-three `php` processes and a pool of eight held eighteen; the suite passed all 499 tests on both, in 214 and 242 seconds, so the bound costs thirteen per cent and a pool cannot oversubscribe the machine. Memory was asked first and is not the reading: the suite passed all 499 tests on a pool of fifteen while the machine reported 286 MB available, and that figure swings between 286 MB and 6 GB across runs on an otherwise idle host, so a memory bound would have cut this pool to a couple of workers on a reading that predicted a failure that did not happen. `WorkersTest` holds the arithmetic on a core count handed to it, `ChecksTest` holds the wiring from both sides — a fixture without the autoloader names no `--processes`, and one handed it names a positive whole number no larger than half the cores — and `bin/index.php` rendered the new file's row into `docs/guards.md`. Measured: `WorkersTest` 6 passed (13 assertions) and `ChecksTest` 18 (69) — with `WorkersTest`, `ChecksTest` and `CoverageTest` green together at 28 passed (109 assertions) — each reading proven to fire by planting the regression it guards against, and on this machine `cores=16 workers=8`. The suite: 498 passed (1,607 assertions) in 188.9s inside a full green gate run, and the same 498 under `bin/coverage.php` at `Parallel: 8 processes`, 182.7s, every floor met — `bin/commit.php` 78.2%, `bin/release.php` 80.3%, every `src/` file at 100.0% — with no `CreateProcess` failure in the log.
- **The handoff's snapshot counts the untracked record beside its edits, so §3.1's eight is seven files that moved and one nobody had committed yet.** The line read `8 with a real content diff and none untracked` while `state.sh` answered `8 listed — 7 with a real content diff, 1 untracked`: both halves were eight, and the split was not. It now names the untracked file (`docs/investigations/why-the-staged-check-runs-silent.md`) and keeps the reading that tells a real edit from a timestamp - 0 files holding no content difference. `HandoffStateTest` holds the section's form rather than its count, which is why this arrives as a correction and not as a red test.
- **The gate names the check it is running on the error stream, and gives each check's child an empty stdin, so a commit is never waiting on a silence.** Every check's output is captured for the summary, which is why the `tests` check - the whole suite, when the staged set carries a file the suite is built on - printed nothing for the minutes it ran, and `--verbose` streams everything, which is not what a hook wants either. The `▶ <title>` line now goes to stderr as the check starts, one line, and the summary keeps its own copy for whoever reads the run afterwards. `runCommand()` and `runCommands()` declare descriptor 0 as `NUL` on Windows and `/dev/null` elsewhere: a child that inherited this process's own stdin can wait on a prompt nobody can see, which is the same silence one layer down, and every descriptor is declared for the reason the suite's own fixtures declare all three. `ChecksTest` plants a gate, requires `▶ PHP syntax (php -l)` on the error stream and nothing of the kind on the output stream, and goes red when the line is taken out. Measured: `ChecksTest` 16 passed (55 assertions), Pint in test mode, PHPStan at max with no errors and Rector's dry run clean.
- **A note written by a test fixture stays off the gate's summary, so the notes under it are readings that ran against this checkout.** The sink `bin/checks.php` names for the suite carried every note a worker wrote, and the fixtures write notes too - a tree with no machine file, a resolver with nothing to ask, and the channel's own literals - so the summary said `there is no .agents/machine.local.json in this checkout` beside a checkout that has one. `Notes::add()` now takes the tree the reading was asked about and writes the sink only when it is this package's root; the in-process and terminal halves are unchanged, so the test that arranged a fixture still sees its note. `Handoff` and `PhpBinary` pass their `$root`, `IgnoredTest`'s checkout note passes `packageRoot()`, and the tests that assert the file name the package root themselves. `NotesTest` holds the other half by planting it: a note about a tree that is not this one - and a note whose caller named no tree at all, which has said nothing about this checkout either - leaves the file unwritten and the terminal half whole, and the test goes red with the root check taken out. Measured: `NotesTest` 6 passed (15 assertions), `HandoffTest`, `PhpBinaryTest` and `IgnoredTest` together 17 passed (67 assertions), and `bin/checks.php --only=tests` printed only `[NOTE] Ignored: .quarantine/...` under its table when it was run before these two tests existed (485 passed, 1,569 assertions); `toolchain` and `agentic` stayed red on this host, because Git's MSYS `sh` leaves one of `php-bin.sh --selftest`'s 17 fixtures unanswered (the PATH rung) - the host's shell rather than this tree, and the same fixture that keeps `PhpBinaryTest` from being exercised without `sh` on `PATH`.
- **Composer's process timeout is 1800 seconds rather than `0`, so a run that wedges has a deadline again while the gate and the floor still fit inside it.** `f957fac` removed Composer's 300-second default with `0`, which means no deadline at all; 1800 clears the gate's 607.9 seconds and the longer coverage floor with room, and still ends a command that wedges. The number is a bound on a stuck run rather than a second statement of how long the gate takes.
- **The handoff's state snapshot is refreshed to the tree it describes, so its dated HEAD and colour claims match this checkout.** §1's measured line names `ccdb8b5` at 90 ahead and 0 behind rather than `f745bfd` at 89; §3 is dated 2026-10-25, and §3.1 states the worktree as it is now — 9 listed, 7 with a real content diff and 2 untracked, naming the mutation-harness work, `composer.json`'s timeout and two `*.txt` scratch copies. §3.2 records today's colour: the gate's `tests` check at 39 failed, 446 passed (1,442 assertions, 331.4s), the 39 being five files the pool could not start and each passing alone, for a serial-equivalent 485 passed, with the earlier serial run (484 passed, 1,564 assertions, 781s) kept as the tree before the fourth mutation hold. `HandoffStateTest` holds it at 8 passed (29 assertions), and `bin/index.php --check` exits 0.
- **The three files in this checkout that are not its work are moved into a gitignored `.quarantine/`, so the guards read this package again while the files stay on disk for whoever they belong to.** The `.agents/sample.machine.local.json.txt` that `handoff/HANDOFF.md` §3.3 decided to leave in place, and the two session configs a workspace agent wrote beside it (`opencode.jsonc`, `opencode.v1-backup.jsonc`), are all foreign machine records: they carry drive paths, so `tests/Unit/Support/MachinePathsTest.php` named them, its three planting holds failed because the planted tree carries them, the inversion control that requires every guard green on an unmutated tree failed with them, and `bin/commit.php` refused every commit this checkout made because the index carried paths it had not been told about. Deletion was never available — they are not this checkout's to delete — and a rule over their names was what the paragraph above `*.txt` records as the wrong answer: an ignore rule that answers a guard is the quiet half of the failure `AGENTS.md` section 0 describes. So they are moved, not matched: `.quarantine/` is a gitignored directory at the root, the files keep their own paths inside it (`.quarantine/opencode.jsonc`, `.quarantine/.agents/sample.machine.local.json.txt`), and a local `README.md` inside the directory says what each one is and how to put it back, because the record in `.gitignore` is read by whoever edits that file and a person standing in the directory reads the directory. Nothing inside is tracked, which is the half a rule alone cannot do: `git ls-files --cached` lists an index entry whatever the ignore rules say, so the two opencode configs were unstaged when they were moved — they were staged additions rather than history, so nothing committed is removed. The file is still named where it should be: `tests/Support/Ignored.php` reads the ignored set, so the `.agents/sample.machine.local.json.txt` record for the package next door still reaches the note a run prints — the guard stops reading it and the note keeps saying it exists. Measured: `MachinePathsTest` and its holds 34 passed (47 assertions), `InversionsTest` 1 passed, the whole suite 484 passed (1564 assertions) with nothing failed — the first green run of it in this thread, where the five failures were these three files — `python .agents/verify.py --layer` 11 steps with 0 findings where it had carried 1, and `python .agents/verify.py --only=guards` 0 findings.
- **Every drift finding `bin/index.php --check` prints names the line its declaration is on, so a report says where to look rather than which file to search.** `GuardIndex::declaration()` has answered the line since the CRLF work — the count walks to the `@guards-index` word itself, so a docblock's blank ` *` above the tag no longer reports the declaration one line early — and no finding printed it: a report named `tests/Support/Ignored.php` and left a reader to open the file and find the header, which is a search in a reading of several hundred rows. Every finding about a file the tree has now says `path:line`: a row whose words have drifted (`the row for the guard-index test says "…", and tests/Unit/Docs/GuardIndexTest.php:39 declares "…"`), a file in neither list, a file listed under the other heading, a row that links a file the guard no longer lives in, a guard no row declares, and both order findings, which say which declaration comes first and where it is. The findings that name a file the tree does not have keep the bare path — there is no declaration left to point at. Reading the tree once rather than twice came with it: `rendered()` and `guardRendered()` split into `renderedDeclared()` and `renderedRows()`, so `lists()` and `guardDrift()` build the comparison and the line map from one walk, because `guardRows()` reads every file a commit carries and asking it twice for one report would read them twice. `GuardIndexTest` extends its plants to `path:line` — line 8 of the header `guardIndexHeader()` writes, line 6 of `guardIndexGuard()`'s — adds `it names the line a guard row has drifted from, and not only its file` for the words and the link, and requires the command's own error to carry `tests/Support/Four.php:8`. Measured: `GuardIndexTest` 15 passed (114 assertions), the guard-index holds 4 passed (13 assertions), Pint in test mode and PHPStan at max with no errors, and `bin/index.php --check` at exit 0 with the page unchanged.
- **The `*.txt` ignore rule is narrowed to the names a session actually leaves, so it stops hiding copies of files this package owns and another package's machine record — which the machine-path reading reports again, the colour the handoff decided on.** The rule's reach was the problem rather than its purpose: `*.txt` held `AGENTS.md.txt` and `src/Middleware/CompressResponse.php.txt`, copies of files this package owns, and `.agents/sample.machine.local.json.txt`, the machine record for the package next door, out of `git status` and out of both path readings — because ignoring a file is also how a file is kept out of a commit without being kept out of the tree, and both guards read the set a commit would carry. An ignore rule answering a guard is the quiet half of the failure section 0 of `AGENTS.md` describes, and `handoff/HANDOFF.md` §3.3 had already decided the other way: that file was **left in place by decision on 2026-10-07**, with "the two readings above stay red while it is there, which is the correct colour for 'someone else's file is in this tree'". The rule now names the leavings it was written for (`/oc-session-*.md`, `/permissions-01.md`), the comment above it says why the extension form was removed, `tests/Support/MutationHarness.php`'s `--force` no longer cites it, and the readings are back to what the handoff measured: `MachinePathsTest` **1 failed, 29 passed (34 assertions)** naming six lines of the sample file, its three planting holds fail because the planted tree now carries the spill, and the inversion control that requires every guard green on an unmutated tree fails with them. `.agents/verify.py --layer`'s `toolchain` step reports the two interpreter sentences inside the file, which is why a commit naming `AGENTS.md` is refused while it is here. Nothing in this change deletes, moves or adopts the file — it is not this repository's — and a green tree would mean the rule had answered the guard again. `tests/Support/Ignored.php` still reads whatever the rules do hold, so a rule that starts hiding a record is named rather than trusted; it answers with nothing here now, which is the reading's other half saying the same thing. Measured: `IgnoredTest` 5 passed (14 assertions), `verify.py --layer` 11 steps with 1 finding (both lines in the sample file), and the four failures above on the tree this commit leaves.
- **A pooled run that cannot start many files at once is called a machine under load, and says how many of the files it ran it could not start, instead of leaving a reader to count the names.** The retry in `runPest()` reported the files it healed but not the measurement behind them: five names beside "34 passed, 5 failed" say neither how many test files the pool held nor whether five is a lot, and the difference between five of nine and five of thirty-nine is the whole question — a codebase with five broken files, or sixteen workers spawning children on a busy machine. `junitOutcome()` now also answers how many test files the run's JUnit log accounts for, and the note under the table reads `the parallel run could not start 5 of the 39 test files it ran — a machine under load rather than 5 findings in the code — and each passed when it was asked again alone`; the check's summary row carries the same two numbers, because that is the line a reader of a gate run looks at first. From two errors at once the sentence names the condition, and that is earned rather than assumed: a file cannot make another file's `proc_open` fail, and each of those files has already passed on its own, so the pool's errors were the pool's. A single error keeps the old sentence and makes no claim of load — one is a file the pool could not start, and its retry is the answer. `ChecksTest` plants a storm of five errored files among thirty-four clean ones, requires both sentences, requires all five to have been asked alone, and fails on the note when the threshold is moved out of reach. Measured: `ChecksTest` 15 passed (52 assertions), Pint in test mode and Rector's dry run clean, PHPStan at max with no errors.
- **`--check` tells a machine file nobody has lived in yet from one that has recorded almost all of itself, so the two stop getting the same sentence.** The report about the sections holding nothing opened with a count, and the count is the same for both: `--init` leaves three answered sections and eleven untouched, and a machine filled in over weeks with one left reads `13 of the 14 sections … are empty` against `11 of the 14 …` — true of each, and no help to either reader. `unrecorded()` in `render_local.py` now says which state the record is in before it lists anything: none of the sections recorded, only the three `--init` was handed answers for (the file a fresh clone gets, and the one `bin/bootstrap.php` leaves behind), or a record that has grown, where the sentence names the sections still empty. The per-section lines are unchanged. `verify.py`'s `tokens` step reads that output once and sorts it by prefix — `[FAIL]` to findings, `[NOTE]` to the run's own notes — so the state sentence and every section still empty reach a session that never ran `--check`; the old code collected only `[FAIL]` lines, and the notes were read by nothing but their own test. `tests/Unit/Support/MachineShapeTest.php` asks all three states on trees of their own, each required to give the sentence that is not the other two, and a copied record that is complete still answers with nothing. The half is a reading of its own as well — `MachineShape::state()` asks a tree the naming step has just opened whether the report says so, and puts the file back as it found it, because the holds share one copy of this checkout — and the mutation harness takes that branch out of the renderer and requires the reading to name the sentence that comes back instead: a fall-through is not silence, since the sections are still listed, so a test asserting on the sections alone would pass. Proven twice: with the branch removed, the state test fails on the sentence it expects, and with `state()` answering `[]` whatever it reads, the hold fails while the test itself stays green. The two descriptions of `--check` — the command block in `AGENTS.md` §3 and the row in `.agents/README.md` — now say what it reports rather than the half of it. Measured: `MachineShapeTest` 9 passed (68 assertions), its three holds 3 passed (9 assertions), and `bin/index.php --check` at exit 0 after the guard's row was re-rendered.
- **`bin/bootstrap.php`'s naming step is held to the renderer that reads the file it writes, so a fresh clone is not called set up on the strength of a settings file its own run rendered.** `python .agents/render_local.py --init` writes `.agents/machine.local.json` and then renders the three settings files from `values_from(data)` — the mapping it just built from the answers it was handed — so the settings files existing says nothing about the file on disk: a writer that dropped a key, or wrote one under a name nothing reads, would render for that run and be found out by the first command that starts from the file. Nothing asked the two halves of that step about each other. `tests/Unit/Bootstrap/BootstrapTest.php` now takes the three settings files the naming step wrote away, runs the renderer in the fixture with no arguments — the reader that answers each `__TOKEN__` from `.agents/machine.local.json` or reports `no value for` and writes nothing — and requires exit 0, no `no value for`, no `[FAIL]`, all three files written again, and this interpreter's path in `.trae/settings.json`; it then asks `--value` for the table and requires no `<unset>` in it. Proven by planting the hole: with `--init` rendering from its answers while writing a file without the `pwsh` section, the setup still exits 0 and still renders the settings, and the read-back is the assertion that fails. Measured: `BootstrapTest` 9 passed (80 assertions), and `bin/index.php --check` at exit 0 after the guard's row was re-rendered.
- **The machine file's example says what belongs under each key, so a reader who copies it is told what value to write rather than handed a token that fits any of them.** `.agents/machine.local.json.example` was written in one convention throughout — `<the absolute path to php.exe>`, `<the version it reports>`, `<each tool bin/tool-paths.php pins>` as a key — and the convention is the defect: a string like that copies as it stands, so the brackets become the value and a machine file built from the example records an instruction where a path belongs. Two keys also shared one sentence, and a sentence that answers both answers neither. Each placeholder now names the value its own key records, in terms of that key: `php.exe` says which binary `--init` writes there, `git.coreEol` says the two endings it can hold, `checks.count` says it is an integer and how many, `tools` shows real tool names rather than a placeholder key, and every list shows one real entry so a reader knows what an element is. `MachineShape::generic()` is the reading that holds it: a value written as an angle-bracket token, a stock word from a declared list (`value`, `path`, `string`, …), the same text written under two keys, an empty value, and a key that is itself an angle-bracket token. `MachineShapeTest` plants each of the first three on its own tree and requires the finding that names it, and the mutation harness carries a second hold for this guard — the angle-bracket token put back into `php.exe` — so the half that stopped asking fails rather than reporting nothing. Measured: `MachineShapeTest` 9 passed (56 assertions), its hold 2 passed (6 assertions), and `bin/index.php --check` at exit 0 after the guard's row was re-rendered.
- **The planting rule is read on a copied checkout, not only on its listing.** `MutationHarness::tree()` takes the checkout it plants from — this package by default, still made once per process — so a control can plant from a fixture that carries a spill without writing one into this checkout; a copy planted from anywhere else is returned and not kept, because `restore()` and every hold mean the tree this package's listing produced. `tests/Unit/Support/MutationHarnessTest.php` builds a checkout carrying a sample machine file and a script that climbs to the package next door, requires both readings to name them there, then plants from it and requires the copy's readings to answer with nothing while still holding a note this package left uncommitted — so an exclusion that dropped everything untracked could not pass by planting an empty tree. A harness that went back to copying `MachinePaths::files()` fails here instead of reddening every hold.
- **The workspace config's backups are ignored by the rule that ignores the config, so a session file stops reading as one a commit would carry.** `/opencode.jsonc` is ignored because what it holds is one machine's preference, and writing the config for a second version left `opencode.v1-backup.jsonc` beside it — untracked and not ignored, which is the one combination that matters, since a file a commit would carry is what the two path guards read; that backup holds a drive path and this machine's temporary directory, so `MachinePathsTest` named both. The row is `/opencode*.jsonc` now, which covers the config, the backup this upgrade wrote, and the next one's the moment it appears.
- **The `*.txt` ignore rule says what it is holding, so the one file it keeps a guard from reading
  is written down beside the rule instead of inferred from a green run.** `*.txt` was added so that
  untracked text files stop appearing in `git status`, and it does that by deciding what `git
  ls-files --cached --others --exclude-standard` answers — the listing both path guards read. The
  rule is not wrong; what it was missing is the sentence saying what it answers.
  `.agents/sample.machine.local.json.txt` is a machine file for the package next door, full of drive
  paths, and the decision recorded about it is to leave it in place and let the machine-path reading
  stay red while it is there — so the rule is what keeps this tree green instead, and a reader of
  `.gitignore` had no way to know that. The paragraph above the rule now names the file, the
  decision, and the direction to narrow the rule in once the file goes home. Nothing about the tree
  changed: the same paths are ignored before and after it, and no guard's answer moves.
- **The session-file rows are a glob rather than four names, so the next run's log is ignored the moment it appears.** The rule named the four files one session had written — `/oc-session-01.md` and the three beside it — and a list of filenames is not a shape: a second session writes `/oc-session-02.md`, and `git status` opens with a path no row covers, which is the state `/opencode.jsonc` and `/.freebuff/` are ignored to keep out of a commit. `/oc-session-*.md` says what the files are rather than which run wrote them, and nothing else in this checkout is named that.
- **`render_local.py --check` names the declared sections this machine has recorded nothing in, so a
  machine file that is the shape and nothing else stops reading as a complete one.** `--init` opens
  every section the shape declares, and a section it cannot fill from its three answers is opened
  empty on purpose — the file a fresh clone gets is the shape it documents rather than the three
  answers alone. What that left was a file nothing complained about: fourteen sections, eleven of
  them holding nothing, and a `--check` with as little to say about it as about a machine that has
  recorded everything. It now prints the count and then one line per empty section, each naming what
  the section is for. They are notes rather than findings, and the state `--init` creates is why:
  `bin/bootstrap.php` verifies the clone whose machine file the step before it wrote, so a `[FAIL]`
  here would fail that run on its own output, and an empty section is a fact about this machine
  rather than a defect in the tree. `verify.py`'s `tokens` step reads the `[FAIL]` lines out of that
  program and leaves every other line alone, which is what makes the distinction hold in CI as well
  as in front of a reader — said where it is decided, in that step. `Tests\Support\MachineShape`
  gained `checked()` — the renderer run in a fixture, answering with its exit code and its lines —
  and `tests/Unit/Support/MachineShapeTest.php` asserts both halves: the notes and exit 0 on the
  file `--init` has just written, no line at all once every declared section holds one fact, and no
  notes on a tree that has never named itself, where the unresolved tokens already say so twice.
  `AGENTS.md` section 3, `.agents/README.md` and `docs/scripts.md` name the new report where they
  describe the command, and `docs/guards.md`'s row for the machine-file shape says the guard catches
  it.
- **The gate's tests step asks a file the pool erred on again, alone and without the pool, so the
  pool's own flakiness stops being reported as a test failure.** `paratest` is the one part of
  `bin/checks.php` that can be wrong about a test: sixteen workers each spawning children of their own
  is a machine under a load nothing else here creates, and `proc_open` fails sporadically under it —
  `CreateProcess failed: The parameter is incorrect`, at `tests/Support/ReleaseRepo.php`'s `git` and
  PHP spawns alike, and never as an assertion — for a command that passes when it is the only thing
  running. Every pest command now carries `--log-junit`, and a failed parallel run is read before it
  is believed: PHPUnit writes one `<testsuite>` per test file carrying that file's `errors` and
  `failures`, which is how a test that could not start a process is told apart from one that asserted
  something and answered about the code. The files that erred are asked again one at a time, with the
  same binary and no `--parallel` — the failing file alone is exactly the condition the flake needs to
  disappear — and the check is green only when each of them passes and nothing in the run asserted. A
  file that fails alone too keeps the verdict the first run gave, named in the retry's own section
  rather than reprinted, because the run's output above it already carries that file's trace and a
  second `Tests:` line would be summed into the check's summary as a second run of the suite; a storm
  of pool errors beside one real finding stays red, and the files the pool was wrong about are named
  rather than left for the reader to separate. Both facts reach the summary —
  the healed count in the check's own line, and one marked note under the table naming the files —
  because a check that quietly asked twice would read as one that had never failed. Measured on this
  machine with a probe file that errors only where `paratest`'s `TEST_TOKEN` is set: the pooled run
  read `32 failed, 417 passed` — the probe, and seven of the release rails' own spawn-heavy files,
  which is the shape this exists for — and the check came back `PASS`, exit 0, with `8 files the pool
  errored on passed alone`. The same probe made to error wherever it runs keeps the run red, names
  the file under `failed alone too`, and is not asked a third time. Nothing here re-runs a test that
  asserted something, and a run that wrote no JUnit log at all is left exactly as it was found.
- **The behavioural guidelines stop travelling alone: an installed package no longer receives
  `BEHAVE.mdc`, because the rules file that names it has never travelled either.** `.gitattributes`
  marks the agentic layer `export-ignore` — the rules an agent reads, the skills it loads, the checks
  that keep the two honest, and a pointer file for each tool that expects one — and `BEHAVE.mdc` was
  the one file of that layer carrying no line: `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `.agents/`,
  `.skills/` and each tool's dot-directory were already out, so what a consumer installed was the four
  rules a session holds to, without the list whose item 2 they are and without anything that reads
  them. The file is upstream-derived — the four Karpathy guidelines, with the front-matter a Cursor
  rule needs — and the only other `.mdc` in this tree, `.cursor/rules/agents.mdc`, is excluded for the
  same reason by living inside `/.cursor`; it names no command, no file of `src/` and no machine, which
  is the case `RELEASING.md` and `PUSHING.md` are excluded on too. Measured on a scratch tree built
  from `HEAD` plus this line alone (a temporary `GIT_INDEX_FILE`, so the real index is untouched):
  `git archive` holds 63 entries against 64 without it, the one that left is `BEHAVE.mdc`, and `src/`
  is 17 either way.
- **The commit skill keeps a rebuilt changelog spacing, and does not trust an empty marker search.** A generator that rebuilds the `## Unreleased` body starting at the first section heading drops the blank line between the heading and that section, so every intermediate commit carries a diff that is not the change it is about; the body is built from the file rather than from a template, spacing included. And an empty marker search is a claim about the quoting before it is a claim about the file — an apostrophe inside the pattern, `repository's` being the one that did it, does not survive one shell quoting and a bullet that is present reads as absent, so the marker is cross-checked against the source it came from before anything is acted on. Both are named in the sorting steps, and both are rules the session's own record paid for.
- **The `package-commit` skill carries the traps a commit-sorting session paid for, and the order it worked out for them.** The section that sorts a thread work into commits is ten steps rather than four, and the ones that were missing are the ones that cost calls: reading each change by file before grouping it; reading what the changelog already says and what `bin/commit.php` actually does; asking the two judgements that cannot be inferred; snapshotting the authored state so a file serving two concerns is committed at the intermediate state belonging to each; unstaging what was already in the index, because the tool reads the index before it stages anything; passing `--no-changelog` with `CHANGELOG.md` named by hand when the bullet was authored alongside the work; reading each commit changelog back as a superset of the one before it; and finishing with the layer and a worktree cleanup rather than with the last commit. A new "When the staged checks fail" order says to decide first whether a finding is yours — reproduce it at the commit the thread started from, in a throwaway worktree — then fix what is yours with `bin/tool.php` pint, rector or phpstan, and neither widen the set nor reach for `--skip-checks`. The Environment section names three facts about this host: scratch stays inside the root under an ignored path, because section 0 of `AGENTS.md` is a hard rule; the two mandated shells are not the same shell, so a command leaning on `sed` or `awk` exists on half the machines; and a staged check that spawns `sh` on a Windows host needs Git `usr/bin` **appended** to `PATH` for that run, never prepended, which puts MSYS git ahead of Windows git and breaks every git-backed fixture. The changelog-bullet section records that a section heading may exist only once — `bin/release.php` sectionBody reads the first body, so a second `### Fixed` is notes no release publishes — and that a heading is counted by its own line rather than by the string. Version 1.1.0.
- **The list a session loads on the way in names the behavioural guidelines, so they are read rather than
  assumed to be found.** `BEHAVE.mdc` was committed with the four rules a session is expected to hold
  to, and the list did not name it: it went from `AGENTS.md` itself to the indexes and the anchors, so a
  file at the repository root was found only by somebody who already knew it was there — which is the
  failure `.agents/README.md` exists to prevent for the layer's own skills, agents and commands, and its
  reach stops at that directory. It is item 2 now, directly after the rules and before the indexes, and
  the four entries below it are renumbered together, because the list is read as an order rather than
  as a set. No other file names it: the rest of the agentic layer points at `AGENTS.md` and restates
  nothing, which is what makes the rules file's own list the only place this can be said at all.
- **The text files are pinned to LF, so what a checkout writes stops depending on the machine that
  checked it out.** `* text=auto` decided what was *committed* and left what a checkout *writes* to
  each contributor's `core.autocrlf` — `true` on the machine this package is written on, unset
  elsewhere — which made every reading that opens a file as the bytes on disk answer for the machine
  rather than for the package: `GuardIndex` and `IndexNumbers` refused files that carry exactly the
  declaration they were read for, and Pint reported nine `line_ending` findings in a worktree `git
  status` calls clean, which is where the line-end classes and the CRLF cover in `### Fixed` come
  from. `.gitattributes` says `* text=auto eol=lf` now, and the case for it is a measurement rather
  than an argument — in a throwaway clone with `core.autocrlf` set the way this machine sets it, 196
  of the 199 tracked files came out CRLF before the pin and one does now: the batch file, whose own
  `*.cmd text eol=crlf` line overrides the pin and is written after it so that it does. Nothing an
  installer receives changes, because `eol=` is a checkout fact and every blob was already LF, and
  the guard row in `docs/guards.md` moved with the line, because that table is rendered from the file
  the guard lives in. A tree already written CRLF keeps its CRLF until something rewrites it: `git
  add --renormalize` refreshes the index's record of a file and leaves the bytes alone, `git checkout
  -- <path>` writes the file again, and a checkout reads these attributes from the index rather than
  from the working copy — so the pin binds a checkout once it is committed.
- **The rules are `AGENTS.md`'s and nothing else's, and the two files that still read as though they
  held one no longer do.** `.agents/README.md` is the index: its pointer sat under a `## Hard rule`
  heading, which reads as though the index carried a rule of the repository's, and it is `## Where
  the rules are` now — the rules are `AGENTS.md`'s alone, and this file carries only the rules about
  *this directory*, which is what a tool loads, that every loadable thing has a row in the index, and
  that a pointer never restates what it points at. `.trae/TRAE.md` was the fourth pointer the note
  below does not name, still carrying the old, narrower rule under `## Hard rule — no parent-folder
  config` in a file whose own first paragraph promised it restates no project rule; its section is
  `## Where the hard rule is` now and it names §0 rather than repeating one clause of it. The
  `canonical` step had not caught that one, and cannot: it compares a pointer's *headings* against
  `AGENTS.md`'s sections, so a heading that repeats an older wording matches none of them, and a
  restatement that has drifted is invisible to a check looking for an exact one.
- **The machine-path guard's own specimens no longer named the machine they were written on.**
  `tests/Unit/Support/MachinePathsTest.php` proved the detector fires with a path into this
  repository's own workspace — both the MSYS spelling and the WSL one — and with the account name
  this checkout runs under, in a drive path, a back-slashed one and both home-directory spellings; so
  the one file whose whole job is to refuse a path that resolves for exactly one person was itself
  carrying two. The specimens are synthetic now — `alex` for the account, and a workspace directory
  that is nobody's — which is the same reading against the same shapes (26 passed, 30 assertions)
  with nothing left in a file a commit would carry that resolves only where it was written. `composer.json` keeps the author's name, because that is authorship rather than a path.
- **The hard rule is this repository's scope, not only its parent's configuration.** `AGENTS.md` §0
  read as a rule about loading agentic configuration from a parent folder, which is one way a checkout
  leaves its own root rather than the rule itself: reading a sibling checkout, searching another drive
  and writing into the package next door were all outside it and all silent. §0 now states the scope —
  read, search, write and commit inside this repository's root, and nowhere else — with the
  parent-configuration clause as one of four, and it argues the failure from this repository's own
  record rather than from principle: a machine file for the package next door spilled into `.agents/`
  and made the machine-path reading red, along with its two holds and the control that requires every
  guard green on an unmutated tree, while the package the file belonged to showed nothing at all; the
  repair was the same crossing in the other direction. `CLAUDE.md` and `GEMINI.md` were restating the
  old, narrower rule in headings of their own — `## Hard rule — no parent-folder config` — and
  `.agents/README.md` was restating it in a `## Hard rule` section of its own while its first
  paragraph claimed every other agentic file here is a pointer to `AGENTS.md`; all three now point at
  §0 instead, which is what a pointer file is for and what the reading that compares a pointer with
  the canonical file can see.
- **A staged set runs as one process per mapped path instead of one sequential run, so the
  narrowest mapping is no longer the slowest thing the gate does.** `--parallel` is paratest,
  which takes exactly one path and refuses several outright, so a commit whose mapped set named
  two or more was handed to a single sequential Pest process — which made the tests a commit
  runs *because* they are narrow the expensive ones: the harness's hold directory is fifteen
  process-spawning files and one after another they are roughly eight minutes, against about two
  minutes for the whole suite in parallel. `bin/checks.php` now builds one command per path and
  starts every one of them before reading any, so the pool is filled the way a file-per-worker
  run fills it, and `--parallel` goes to the paths that are directories, a single file being one
  worker whatever it is told. Each run ends with its own `Tests:` line, so the summary sums
  passed, failed and assertions across them and says "in N runs" rather than letting one path's
  numbers stand for the set; a line in a shape the reading does not know is quoted instead of
  summed. What is not bounded is a set naming more than one directory, which starts a pool per
  directory — stated in the code and in the hook paragraph of `docs/guards.md`, which now
  describes the shape of a mapped set.
- **A change to the mutation harness runs the holds it builds rather than the whole suite.**
  `tests/Support/TestMap.php` decides which tests a commit runs, and every row it had was a file
  under `src/` or `bin/`: a file under `tests/` was mapped by a rule instead, itself when it is a
  `*Test.php` under `tests/Unit/` and the whole of `tests/Unit` otherwise, because a fixture the
  suite is built on is shared by every test. The harness is the case where that is false — it
  is the class the holds are made *of*, so the whole of what a change to it can break is the
  fifteen files under `tests/Unit/Support/MutationHarness/` and the spine above them, where the
  fallback ran all 421 tests, minutes of the suite that never load the harness at all. It is the
  one row outside `src/` and `bin/`, and the map's own guard now allows it: the reading that
  compared the rows against those two directories in both directions asks the missing direction
  of the directories and asks every row for a file that is there, which is the failure a rename
  leaves behind on either side of that line, and it asserts the row through `covering()` beside
  the fallback it keeps for `tests/Support/ReleaseRepo.php`.
- The release suite's rails are asked in files of their own — one family each, from the branch and the lane to the notes, the number they call for and the surface they change — so `paratest` hands a worker a rail rather than a suite, and the branch rail the mutation harness takes out of `bin/release.php` is held by a file of five tests rather than one of twenty-four.
- **The mutation harness's holds are one file per guard, so `paratest` spreads the slowest thing in
  the suite instead of queueing sixteen workers behind one file.** The holds were one file, and that
  file was 540s of the suite's 1801s of test time on one worker — while the other fifteen ran out of
  work in under a tenth of that — because `paratest` divides work a file at a time and nothing finer.
  Each guard now has a file of its own under `tests/Unit/Support/MutationHarness/`, named after the
  guard's own test, and the harness takes the guard as an argument: a slice that comes back empty
  raises rather than running no tests and reporting them green, which a renamed test file would
  otherwise do silently. What is about the set rather than about a guard stays in
  `MutationHarnessTest.php` — the planted tree, the accounting against `docs/guards.md`, the declines,
  and `claimed()` held to `subjects()`: the guard each hold file asks for, read out of the file
  itself, against every guard the three directions hold, once each — so a guard that arrives with no
  file is a finding and a file asking for a guard nobody holds is refused. The two controls, the
  slowest runs of the whole thing, sit in `Controls/` so each gets a worker of its own. `pest
  tests/Unit --parallel` is 421 tests in 218s where the same run was 604s, and the longest file is now
  the overlay's control rather than a file carrying a whole direction.
- **The guards table is a rendering now, from the files the guards live in, and the guard-index
  check is harder to satisfy.** `docs/guards.md` had three tables and only two were written: the
  guards themselves were kept by hand, on the reasoning that what a guard refuses is an argument.
  An argument still is, but it has a home — one comment block in the file the guard's row names
  carries `@guards-index guard <name>`, and the block's own first sentence is what it catches. The
  file is the `Where` cell, read in whichever comment the file writes in — a PHP banner or docblock,
  a run of `#` lines, an XML comment — so a guard that moves moves its row with it, and a row left
  linking a path that moved is no longer a thing that can be written. The test side is the same
  question: a test under `tests/Unit/` declares `@guards-index guard <name>`, which names its own
  row, or `@guards-index test`. A declaration is then held to the kind of file it is in and to what
  the file actually holds: a `guard` line in `tests/Support/` is refused, because that directory is
  readings and fixtures rather than guards, and a file that says it is a test while carrying none —
  or a reading that carries one — is refused rather than recorded. `bin/index.php` writes all three
  tables, `GuardIndex::drift` is what the suite asserts on, and the mutation harness plants a guard
  no row records and requires the rendering to name it.

- **The interpreter rule now reads the whole repository, so a later change cannot put a restated
  interpreter back by hand.** The refusal was written over the layer — the rules, the agentic files,
  the programs in `bin/`, the hook and the workflow — which is the set a command is handed over in,
  and it left the prose records and everything outside them unread. That is the wrong set for this
  question: the failure is a reader copying an answer to "which PHP" out of whatever they are
  reading, and a runbook, the README or a test states it exactly as `AGENTS.md` does. The reading is
  therefore taken over the files a commit would carry — one `git ls-files`, the same listing the PHP
  guards read, falling back to a walk past `.git`, `vendor` and the caches where there is no checkout
  to ask — so `.agents/machine.local.json` is the one file an interpreter may still be written in, as
  it is the file that names this machine's PHP. Two changelog sentences that quoted the refused
  command as prose were reworded to describe it instead. The pattern also gained a dot in front of the
  interpreter: `Foo.php` followed by a tab and another path is a filename rather than the word `php`,
  which is what `files.tsv` and the tables in `docs/` had been reading as one file running another.
  The selftest's own round still plants the restatement in `.agents/README.md` and requires it named.

- **The command table's release row names the lane too, so the fourth copy of the command is the one
  that runs.** The runbook, the rules and the release skill were each corrected to pass
  `--branch=development`, and the row in the table rendered into `AGENTS.md` from
  `.agents/toolchain.json` was left as `composer release -- --weigh` — a command that stops at the
  branch rail on this repository's own checkout, which is exactly the failure the other three were
  fixed for. The manifest is the source of that row, so the lane is written there and rendered from
  it rather than corrected in the table a generator owns; the purpose cell says why the lane is
  named. It is the one release command in the repository that is not quoted from a runbook, which is
  how it escaped the sweep: a row in a rendered table reads as a rendering rather than as a command
  somebody copies.

- **Every walk the mutation harness used to compose in a test now lives in the reading it asserts
  on, so the harness can point the last four guards at a broken tree.** The docs-link, config-key,
  config-claim and tool-path guards each built their walk inside the test — a link resolved, a key
  compared, a path counted — which left the harness with nothing to call. Each walk moved into the
  support class the guard is about: `Docs::unlinkedRecords()` for the record nothing links to,
  `ConfigKeys::unknownReaders()` for the reader the class no longer declares, `ConfigClaims::stale()`
  for the claim a reader contradicts, and `ToolPaths::asked()`, `unsharedNames()` and `required()`
  for the names, the paths and the pins. Three mutations were added — an unlinked record planted in
  `docs/`, a reader renamed in `src/Support/Config.php`, and a tool name nothing defines planted in
  `bin/checks.php` — and the config-claim mutation was rewired to the new reading, so each guard is
  now broken on purpose and asked to name the break. One reading is deliberately left unmutated:
  `ToolPaths::pinDrift()` reads `vendor/composer/installed.json`, which this repository ignores and
  a planted tree never carries, so its pin half stays held by its own test against the real record.
  The harness docblock and `docs/guards.md` say why rather than leaving a gap in the list.
- **The release runbook's tag note names the one exception.** `RELEASING.md` said "every existing tag
  is annotated", but `v0.0.1` is a lightweight tag and has no peeled `^{}` ref — the one tag
  `--follow-tags` would not carry. The paragraph now says which tag is the exception and why the
  caveat is there.
- **The by-hand release recipe stages what the release commit actually carries.** `RELEASING.md` said
  the script writes "three things" and then staged only `CHANGELOG.md`; the release commit has also
  carried files.tsv and methods.tsv, restamped with the tag being created, since the inventory
  arrived, and `composer.json` when the release opens a line. The section now stages all of them and
  says what the stamp in each inventory file is for.
- **The release runbook describes the gate as it is now.** `RELEASING.md` said the gate was
  `composer checks` "exactly as CI runs it", while the workflow runs `composer checks --
  --require-all`, and it said the gate "calls every tool as `PHP_BINARY <entry file>`" after the gate
  gained two checks that ask Python. The runbook now names the CI form and says which half runs as a
  PHP tool and which two skip without a Python 3.
- **The release runbook now cuts from the lane this repository develops on.** Every command in
  `RELEASING.md` passes `--branch=development`: the release program defaults to `main`, and its
  branch rail refuses a cut anywhere else, so the short version, the prerelease examples, the
  numbering example, the `v0.0.10` transcript and the by-hand push each stopped before changing
  anything. The refusal table's wrong-branch row names the lane too.
- **A tool is a record now rather than a path, and the two facts beside the path are checked.** An
  entry in `bin/tool-paths.php` carries the version the package pins the tool at and one line saying
  what it is for, beside the file that runs it, and the readers follow: `bin/tool.php` runs an
  entry's `path` and refuses a name it holds without one — its own answer, rather than "not
  installed", because `composer install` is not what would fix it — `bin/checks.php` and
  `bin/coverage.php` read the same field, and `python .agents/verify.py`'s `tools` step asks PHP for
  the manifest as data rather than reading records out of the source with a pattern. The two new
  facts are what the rest is for: `bin/checks.php --list` prints every tool with its pin and its
  purpose out of the manifest, so what the gate says about a tool is the manifest's own sentence
  rather than one beside the gate that nothing compares with it, and `ToolPaths::drift` holds each
  pin in the two directions it can move — against `composer.json`'s `require-dev`, which is what the
  package asks Composer for, and against `vendor/composer/installed.json`, which is what it got —
  because a tool that moved measures something other than this tree, which is the `vendor/bin` shim
  failure one level up and just as invisible in a path. The package a tool lives in is derived from
  the path (`vendor/acme/thing/bin/thing` is inside `acme/thing`) rather than written beside it, so
  the three facts cannot disagree about which tool they describe, and `symfony/yaml` is a
  `require-dev` entry now: the workflow YAML the gate lints is a tool this package runs, so its
  version is asked for rather than inherited from whichever dependency happens to bring it.

- **The hook, CI and the two PHP programs that need an interpreter no longer decide one.** The
  pre-commit hook asks `.agents/php-bin.sh` and keeps only the part that is about committing: it
  skips rather than blocking when no PHP is found, and says so when a fallback is what answered. The
  workflow gained a job that runs `sh .agents/php-bin.sh --matrix` and a matrix built from its answer
  with `fromJSON`, so the PHP it installs is read out of `composer.json` instead of written into the
  workflow as a second copy. `bin/tool.php` prints one line before it runs a tool whose interpreter
  is not the one this machine names — the `vendor/bin` failure one layer up — and `bin/coverage.php`'s
  header names both interpreters when they differ, since a floor recorded under one and measured
  under another is the first thing to rule out when it moves.

- **A command a skill or an agent hands over renders this machine's PHP in the line itself, rather
  than leaving the token in front of a program for a reader to substitute.** The four commands that
  need an interpreter — `staged`, `checks-list`, `index` and `index-check` — now read
  `"$(python .agents/render_local.py --value __PHP_EXE__)" bin/checks.php --staged`: in the
  `.agents/toolchain.json` they are declared in, in section 13 of `AGENTS.md` that is rendered from
  it, in both skill targets, and in the `.skills/` sources those are generated from.
  `__PHP_EXE__ bin/checks.php --staged` is not a command, it is a placeholder, and a skill that
  states one hands over a line somebody has to edit before anything runs — which is the difference
  between a skill and a note about a skill. Nothing committed carries a drive path either way, and
  that was never in question; what the token needed was the renderer named inside it.
  `step_toolchain` in `.agents/verify.py` was taught to read through the rendered prefix, so the
  program behind it is still checked to be here rather than becoming the one line nothing looks at.

- **This machine's paths live in one gitignored file, and nothing committed names them.**
  `.agents/render_local.py` owns `.agents/machine.local.json`: `--init` asks for the PHP binary,
  PowerShell 7 and the package root, `--value` answers a token for a reader who needs one spelled
  out, and the three settings files a tool actually reads — `.trae/settings.json`,
  `.trae/settings.local.json`, `.kiro/settings/local.json` — are rendered from the committed
  `.example` template beside each one. All four are ignored, because a rendered settings file holds a
  drive path by construction, which is the thing the two path guards exist to refuse. The
  pre-commit hook gained the matching step: when a commit touches this layer it runs
  `python .agents/verify.py` before the package's own staged checks, and a machine with no Python
  loses the check rather than the commit, exactly as a machine with no PHP already did.
  `.gitattributes` keeps the layer out of the dist — a consumer installs the middleware and the
  config, and none of this is anything they run.

- **Nothing in this repository names anything outside it any more, including the two records that
  explain what such a name looks like.** `tests/Support/MachinePaths.php` and the test beside it
  taught the shape of a finding with one: a workspace on a real disk, in an example whose whole
  purpose was to show what a drive letter and the path after it look like. Both docblocks quoted
  the `cd` line the push runbook used to open with, so the guard carried the very facts that had
  just been removed from the runbook it was written about. The example is now a workspace that
  belongs to nobody, and the two places that leaned on the old line say what happened without
  naming it. `docs/env-types.md`
  demonstrated the two variable types under names borrowed from another project — `LPR_INT` and
  `LPR_TRUE` — which put a place this package has never heard of inside the evidence for a claim
  about `env()`; the run was repeated under `DEMO_INT` and `DEMO_TRUE` on 2026-09-30 and came back
  byte-identical, which is what makes that a rename rather than a new claim. And `.idea/php.xml`,
  which this repository commits like the rest of the project's files, was re-read by the IDE once
  the checkout had moved: what it wrote back is the two tool registrations the IDE re-added at
  `$PROJECT_DIR$/vendor/bin/pint.bat` and `$PROJECT_DIR$/vendor/bin/phpstan.bat`, the two
  `symfony/polyfill` include paths its interpreter now reports, and `phpstan.neon.dist` as the
  config PHPStan is run under — every path in it relative to this checkout, which is the one form
  an IDE's own file can be committed in.

- **The desktop app's own directory is ignored, so its state cannot reach a commit.** `.freebuff/`
  appeared the first time this checkout was opened as a workspace by the editor's agent: one file
  holding a project id, naming nothing and belonging to no version of this package. Untracked and
  *not* ignored is the one combination that matters — it shows in `git status`, so `git add -A`
  takes it, and the next commit would publish a file whose entire content is a fact about one
  machine. `.gitignore` now covers it, beside the other artefacts a run leaves behind, and the
  reason to write the rule down rather than remember it is the reason that file exists at all: the
  next machine writes its own.

- **One file names this machine's PHP, and every command that needs an interpreter asks it.** The
  machine file above covered the paths; the interpreter was still restated wherever a command was
  written down, and a command written with the interpreter in front of the program it runs is the
  same restatement as a drive path — it names whichever interpreter happens to be first on `PATH`,
  which on this box is not the PHP that installed the dependencies. `AGENTS.md` now carries the question as a row of its environment table, together
  with the half of the answer that is *nothing*: most commands name no interpreter at all, because
  Composer runs a script under the PHP that is running Composer. The pre-commit hook asks
  `.agents/php-bin.sh`, which is that ladder in one place now — the environment, then the machine
  file through the renderer, then `git config`, then `PATH`; the last three survive so that a clone
  which has never run `render_local.py --init` still commits, and the rung that answered is printed,
  because a check run under a second PHP is the failure that leaves no trace. The same reading is applied to the two staged commands in
  `.agents/toolchain.json`, to the usage text of all three programs in `bin/` — `bin/tool.php`'s
  usage line is asserted by `ToolTest`, which moved with it — to the two runbooks, to the hook
  paragraph in `docs/guards.md`, to both skills' instructions, and to the command in
  `docs/env-types.md` that produced that record's evidence: it runs under the token now, and was
  re-run to confirm the output did not move (`string(1) "9"`, `bool(true)`).

- **A rendered settings file that drifted is reported by the value that moved rather than by the
  two lines that differ.** `python .agents/render_local.py --check` already refused a settings file
  that is not what its template would produce; what it said about one was `_first_difference` of
  the expected text and the file, which is true and is a diff the reader then does by hand. The
  difference is nearly never a hand edit: the file was rendered when `machine.local.json` named one
  install, it names another, and the two disagree by a path. So every token is asked where it sits
  in the text the template would produce with that token left as itself - the same literal on both
  sides of it - and the text the file holds between those two pieces of literal is the value it was
  written with. A value that has moved is reported as the line, the token, the value the file holds
  and the value the machine holds; a difference no token accounts for is still the two lines,
  because a hand edit has no token to name. One line per token, because a path that appears six
  times in a file is one value that moved rather than six findings.

- **The guard index's two lists of files are rendered from the directory they describe.** The page
  below the table of guards is where `tests/Support/` is written down — the readings the guards are
  built from, and the files beside them that are fixtures rather than guards — and both lists were
  prose. That is a copy of a directory: a file that moved had to be moved by hand here as well, a
  file that arrived was in no list until somebody remembered it, and a row whose link still resolved
  to a file still named was a row nothing could contradict. Each file now says which list it is in,
  in its own header — `@guards-index reading` or `@guards-index support` — and the first sentence of
  that header is the words beside its row, so which list a file is in, and what a reader is told
  about it, are the file's own facts rather than the page's recollection of them. `bin/index.php` —
  `composer index` — writes both tables from that, and `--check` is the same reading with nothing
  written, which is what the suite asserts: the document and the directory cannot come to disagree,
  because the directory is where the document comes from. A file that declares nothing, declares a
  list the page does not have, or has no sentence to quote is refused by name instead of being
  written around, because "in neither list" and "in it and correct" are the two answers a rendering
  has to be able to tell apart — and a program that wrote the rows it could and said nothing about
  the one it could not would publish a list with a hole in it.

  The row for the guard-index guard itself had been sitting below a blank line of its own, which made
  it a table of one that the reader of the guard table never read: the guard that checks this page
  was the one row on it the page's own reading could not see. It moved back into the table it belongs
  to. `GuardIndex::drift` is one call, so the mutation harness breaks the directory under it the way
  it breaks the tree under every other reading — a file added to `tests/Support/` and left in neither
  list — which retires the reason the harness used to decline the index, and the guard index's row in
  `docs/guards.md` now names the program beside the test. The program carries a floor of its own in
  `bin/coverage.php` at 83.3%, driven end to end in a planted tree, with the same two header lines
  unreachable that every measured file in this repository carries.

- **The release skill is two skills now, so a push and a cut cannot be mistaken for one another.**
  `package-release` answered both questions — "what is the next version" and "how does its tag reach
  the remote" — and the two have different failure modes and different permissions: one writes a
  commit and a tag inside the checkout, the other touches the remote and can be refused by it. It is
  split along that line. `package-deploy` keeps the whole release lane with the push taken out —
  `unreleased`, `weigh`, `weigh-dry-run`, `release-cut`, `prerelease` and `verify-releasing` — and it
  never pushes: even `prerelease` now cuts the tag without `--push`. `package-push` owns the remote —
  `push`, and `verify-pushing` for the `PUSHING.md` evidence table. The one runbook check that used to
  cover both files is two checks, each beside the half it is about, and the `--follow-tags` caveat (a
  lightweight tag is not carried, and `v0.0.1` is this repository's one lightweight tag) now lives
  where a push is read rather than in a release guide. `package-release` is deleted: its target, its
  Windsurf pointer and its source directory are gone, its `GEMINI.md` import is replaced by the two
  new ones, and `package-commit`'s "pushing is `package-release`'s job" now names `package-push`.
  Nothing about `bin/release.php`, `composer release`, `.release-lane` or the release commit changed —
  the program does the same thing; what was split is which skill hands a maintainer the command.

### Fixed

- **`bin/commit.php` stages the bullet it wrote and not the changelog file it wrote it into, so a note another session left in the same document cannot be swept into this commit.** `git add` stages a *file*, and `CHANGELOG.md` is the one file two sessions write into at once in a shared checkout: the entry this run puts a bullet in is also the entry the thread beside it has been putting its own notes in, the named set carries the changelog — the program adds it itself — and `git add -f -- … CHANGELOG.md` therefore published that thread's uncommitted note under this message, ahead of the commit whose subject says what it is about. What replaced it is not staging the file at all: the entry the index already holds is read (`indexed()` — `git cat-file blob :CHANGELOG.md` — or the file itself when the index holds none, which is a changelog this tree has never carried), this run's bullet is added to *that* copy rather than to the file on disk, and the result becomes the index's entry for the path through `stageBytes()`: `git hash-object -w --no-filters` on a temporary file, then `git update-index --add --cacheinfo 100644 <sha> CHANGELOG.md`. Those are the calls that can put bytes into the index where `git add` cannot, because `git add` has no way to be told "this file, but only this part of it". `--no-filters` because those bytes are the index's own with a bullet in them, so a clean filter would move lines this run did not write; `update-index` rather than writing the file, because the working copy belongs to whoever has been writing in it and is left exactly as it was found. Everything else in the named set is still staged from disk, which is what a named set means. The two documents then differ whenever the file held more than this run wrote, and the run says so rather than leaving it to be discovered: one `[NOTE]` line naming how many lines the changelog on disk holds that this run did not write and that are not staged — which is also what a `git status` still showing `CHANGELOG.md` modified after a successful commit means. The abandon path needed no new state for it: `indexSnapshot()` reads the index file itself, so a run that stops after this point is still put back byte for byte. Held by two specimens in `CommitTest` — `it leaves the notes another session wrote in the changelog out of the commit` plants the shared-checkout state, a note another session wrote beside the bullet this run writes, and reads the commit back with `git show HEAD:CHANGELOG.md`, requiring this run's bullet in it and the other note out of it while the note is still on disk, still unstaged and still the difference `git diff` reports; `it adds its bullet to the changelog entry the index already holds` stages a note the caller staged before the run and then leaves a later one in the file, and requires the commit to carry the caller's arrangement plus the bullet and not the later note — and both are proven to fire rather than trusted, because with the old shape planted back (the file staged whole, and no bytes written into the index) they fail 2 of 7 assertions, each on the assertion about the note, and are green with this one, the file restored by copy. `docs/guards.md`'s row for the program says it in the sentence the file declares, and the two inventories, [the scripts page](docs/scripts.md) and [the tool registry](docs/tool-registry.md), say it in the row that describes the commit. Measured: `CommitTest` 25 passed (101 assertions), with the two new specimens at 2 failed (7 assertions) under the planted break; the whole suite under the coverage driver, 511 passed (1,661 assertions) in 327.15s, put `bin/commit.php` at 79.4% (255 of 321 lines) against its 70% floor under PCOV 1.0.12; and the whole gate, `bin/checks.php --require-all`, is 11 checks: 11 passed, 0 failed, 0 skipped in 423.9s, with Pint, PHPStan at max and Rector's dry run clean.
- **A directory name's parenthesised suffix is read as part of the name, so `C:/Program Files (x86)/…` is one path rather than the prefix of one.** The install-root bullet below names this as the sibling root it did not reach: a segment ended at the `(` the way it used to end at the space, so the drive rule answered the root's first word — a path nobody wrote reported as a path somebody did, which is the failure the space was read through to avoid one mark earlier. `MachinePaths` carries `MARKED` now, a name that may hold a parenthesis, built on the same `SEGMENT` class the name is: `(x86)` is read and `(see above)` is not, because an aside a sentence writes beside a path carries the words a name does not — which is also why a mark may end a path where a space may not, since a name in parentheses is one word and the run of words after a path is what the space rule is held back by. `SPACED` is written on `MARKED` rather than beside it, so the two marks compose and `Program Files (x86)` is one segment. Three readings move with it. `tolerated()` reads the 32-bit sibling of the install root it already excused, because the same argument holds for the same reason and the tolerance only ever sees a whole root: the sibling arrives as a name, or as the root's first word, which matches no row of that list. The mount pattern reads the mark too, and there the failure was not a truncation but a silence — a mount is a path because a segment follows the root, so a path whose directory carries a parenthesis was answered with nothing at all in that spelling, and the two spellings of one directory answering differently about one name is what this file keeps one rule to prevent. What did not move is that pattern's own edge: a name carrying a *space* after the mount root is still read to the space, a space rule it never had, named here rather than half-fixed by this. Held by the readings rather than by the argument: four specimens in `tests/Unit/Support/MachinePathsTest.php` — the mark in a path that names a machine, in each spelling, the aside beside it as the boundary, and the tolerated sibling whose failure is the truncation itself — and a fourth machine-path mutation in `tests/Support/MutationHarness.php`, the leak with a parenthesis in one of its directories, whose marker is the whole path so a reading that stops at the mark answers with a prefix of it rather than with it. `docs/guards.md` names the sibling beside the root it shares an argument with, what the mark has to hold to be part of a name, and the silence in the mount spelling that this closed. Measured: `MachinePathsTest` 36 passed (40 assertions), the machine-path hold 5 passed (16 assertions), `GuardIndexTest` 15 (114) and `IndexNumbersTest` 10 (33).
- **Every fixture starts its children through one place that waits out a refused start, so a pooled run stops losing files to a machine that briefly declines `CreateProcess`.** `proc_open()` under `pest --parallel` on this machine answers `CreateProcess failed: The parameter is incorrect` sporadically at a child a worker starts, and never as an assertion about the work: 42 failed tests on the run that raised it, then 29, then 26, every one of them a file whose first test was refused while building its fixture tree — one refused start, and every test after it in that file fails at the same line, because they all use the tree it was building. The same commands pass alone, and the gate's 8-worker pool never showed it. A start that was refused is the one failure a retry is honest about, because no process was created and so no effect is repeated; a child that started and then misbehaved is never retried. That answer used to live in `tests/Support/ReleaseRepo.php` only, where it healed the release rails and left the rest of the suite — `Bootstrap`, `Tool`, `Coverage`, `GuardIndex`, `IndexNumbers`, `MutationHarness`, and the readings in `Handoff`, `HandoffState`, `Ignored`, `MachinePaths`, `MachineShape`, `PhpBinary`, `Workers` — starting children with a bare `proc_open()`. `tests/Support/Processes.php` is now the one place: `attempt()` retries a refused start and answers `null` when the patience ran out, `start()` throws with the last reason beside the command, and no `proc_open()` call is left outside it anywhere under `tests/`. The calls that remain in `bin/` are a different case and are not claimed here: a program there starts its children for a single run, and a test that needs one starts the program itself through `Processes`. The ladder is 50 milliseconds, then three times that each pause up to four seconds, for as long as thirty seconds — nothing on a green run, because the ordinary path is the first attempt and only a refusal pays. Two shorter ladders were beaten by measurement rather than by argument: three attempts with 60 milliseconds of pauses lost a run of 29 failed tests, and five attempts with just over two seconds on the table lost a run of 41, every one of them reported `no reason was given`, which is what `error_get_last()` holds when Windows refuses a start without a message. The fault is bursty rather than uniform, which is why the ceiling came from the failing run and not from a green one: the next run at the same width recorded 948 starts and not one refusal — with a refusal log written for that run only — and the code as it now stands passed 502 tests (1,632 assertions) in 206.8 seconds with no `CreateProcess` line. The retry is proven to fire rather than trusted: with every first attempt refused on purpose, `BranchAliasTest` and `ReleaseApplyTest` still pass (7 passed, 37 assertions) and the file restored by copy. `docs/guards.md` renders the new support file's row, and its two counts move with it — 21 readings and 7 files that are not guards, 28 files in `tests/Support/` in all. Measured: `bin/checks.php --require-all` 11 checks passed in 476.8 seconds with the suite at 502 passed, `IndexNumbersTest` and its hold 12 passed (40 assertions), `HandoffStateTest` and `DocsLinksTest` 12 passed (42 assertions), and `bin/index.php --check` at exit 0.
- **A named set whose only moved path is the changelog is refused, so a commit cannot publish an earlier group's bullet under a subject about work that is already in the history.** `carrying()` was asked of the whole named set whenever `--no-changelog` handed the changelog over, which is the way a session cutting a set in parts hands it over — so a group that named its own paths *and* `CHANGELOG.md`, the file holding the bullet the group before it wrote, had that bullet counted as the evidence that the group had a change in it. The commit it cut carried one file and described work an earlier commit had already done and already recorded, which is the shape §5's eight groups had, read from the other side. The evidence is now the named set with `CHANGELOG.md` taken out: an entry records a change rather than being one, so the change has to be somewhere else in the set, and the one shape where the entry *is* the commit is the entry named alone — `--no-changelog` with `CHANGELOG.md` and nothing else, which is a changelog correction rather than a description of one. `CommitTest`'s `it refuses a set whose only change is the changelog an earlier group wrote` plants that fixture and holds both answers: the refusal exits 1 naming the paths and saying the set carries no change of its own, the entry is left where it was found, nothing is staged and `HEAD` is unmoved; the same set with `CHANGELOG.md` alone still commits. The test is proven to fire rather than trusted — with the previous rule put back, and the file restored by copy rather than by `git checkout`, it fails on its first assertion (`Failed asserting that 0 is identical to 1`, a commit cut where a refusal was required) and is green with this one. `docs/guards.md`'s row for the program says it in the sentence the file declares, [the scripts inventory](docs/scripts.md) names the third refusal beside the other two, and the commit skill says it at the step that tells a session to hand the changelog over with `--no-changelog` — the sentence a session reads before it cuts a group whose paths are already committed. Measured: `CommitTest` 23 passed (89 assertions); the whole suite under the coverage driver, 502 passed (1,632 assertions), put `bin/commit.php` at 79.6% (226 of 284 lines) against its 70% floor under PCOV 1.0.12; `bin/index.php --check` at exit 0; PHPStan at max with no errors; Pint in test mode.
- **A `bin/commit.php` run that stops after writing the changelog bullet puts the file and the index back, so an abandoned run leaves no entry for work nobody committed and nothing staged that the caller did not stage.** The bullet used to be written before the set was staged and the staged checks were run, which meant every refusal after that point left its own output behind: a failing gate or a rename that pulled a path into the index ended the run with a `CHANGELOG.md` entry describing a commit that does not exist — in the file everybody reads to find out what was — and with a named set staged for the next commit to carry without anybody deciding it. The state is now taken before anything is written: `indexSnapshot()` reads the bytes of the index file (`git rev-parse --git-path index`, so a worktree and a moved git directory are read rather than assumed) and the changelog's own contents are held beside it, and `restoreTree()` writes both back on every refusal after the write — a `git add` that fails, a staging that pulled in a path the set did not name, red staged checks, or a `git commit` that exits nonzero. The index is restored rather than emptied, because emptying it is the same defect from the other side: the guard allows the index to have carried paths of the set already, and what a refused run has to leave is the index it found. `CommitTest` holds both halves — `it leaves no bullet behind when the staged checks abandon the run` asserts the changelog byte-identical to what it found, nothing staged, `HEAD` unmoved and the caller's edit still on disk, and `it puts the index back with exactly what it found staged` stages one path of a two-path set first and asserts that path is still the only one staged afterwards, with the other still an untracked file of the caller's — and both go red when the restore is taken out of the abandon path (measured: 2 failed, 7 assertions, with the planted break). `docs/guards.md`'s row for the program says it now, in the same sentence the file declares. Measured: `CommitTest` 22 passed (81 assertions).
- **A program's install root is tolerated like the Windows directory it sits beside, and the space in its name is why the two halves of that rule are one reading rather than two.** `C:/Program Files/OpenSSH/ssh.exe` is the same path on every Windows install and names nobody — the argument `C:/Windows/…` has been excused on since the guard was written — and it was being reported, because no root but the Windows directory was known to `MachinePaths::tolerated()`. The space is what makes it more than one more row in a list: the drive rule ends a segment at whitespace unless the space is inside one, so a reading that stopped at it answered the first word of the root rather than the root — the prefix of a path, not a path — and would have gone on reporting it even with the root tolerated, because a prefix matches no root. The row is `~^[A-Za-z]:/(?:Windows|Program Files)(?:/|$)~i`, one rule for both install roots, and the mount spelling of it needed no pattern of its own: `/c/Program Files/…` is normalised into `c:/Program Files/…` by the rule already there and read by the same one, which is what keeps the two spellings from coming to disagree about who they name. Two specimens hold it in `tests/Unit/Support/MachinePathsTest.php`, and the one that proves the space half is the drive spelling: the toleration only ever sees a whole root, so a reading that ended at the space comes back as a finding instead of an excusal, and that specimen passing is the proof. The mount specimen that used to report the mount spelling of that same root reports a path that names somebody now — a drive with a directory whose name carries a space — because the path it named has become a tolerated one; `docs/guards.md` names the third shape beside the other two. What this does not reach is the sibling root whose directory name carries a parenthesis as well as a space: that path still reads as the root's first word, because a segment ends at the `(` the way it used to end at the space — the segment rule's boundary, named here rather than half-fixed by it. Measured: `MachinePathsTest` 32 passed (36 assertions), the machine-path hold 4 passed (13 assertions), and `bin/index.php --check` at exit 0.
- **`handoff/state.sh` measures a linked worktree instead of declaring it unusable, so a throwaway checkout of a commit can be read rather than refused.** A worktree keeps `.git` as a file pointing at the repository's git dir, and git for Windows writes a drive-letter path there; the POSIX git the script runs under reads that as relative and refuses the tree, which is the state `state.sh` reported as "not a git repository". The script now resolves the gitdir from the `.git` file and translates a drive path with the shell's own translator (`wslpath -u` on WSL, `cygpath -u` on MSYS) before handing it back through `GIT_DIR`/`GIT_WORK_TREE`, and an ordinary checkout keeps the `git -C` path it had. `HandoffStateTest` adds `measures a linked worktree rather than declaring it unusable`: a fixture repository carrying the script, a real `git worktree add`, and `HandoffState::measured()` required to answer with the worktree's branch and an unchanged index. Measured: `HandoffStateTest` 9 passed (32 assertions), and a manual `git worktree add` of `HEAD` printed its own path, branch and HEAD instead of the refusal.
- **The handoff's planted breaks are patterns over the section rather than quotations of it, so a re-measured state section cannot leave a guard editing nothing and passing.** `HandoffStateTest` and the harness's hold behind it both planted their break by quoting the document word for word — the §1 bullet that wrote the date, the hash and how far ahead the branch stood, and the §3.2 heading that named the day the suite was measured — and §3 is the one part of this repository that is re-dated on purpose. The first re-measurement therefore left two plants with nothing to edit: the test asserted on a finding that could not arrive, and the harness refused a mutation that left the tree as it found it. Both now match the bullet and the heading they break, with the `HEAD` the marker carries read back out of the file, so the date and the hash are the document's rather than a copy of them kept beside it. Measured: `HandoffStateTest` 8 passed (29 assertions), its hold 2 passed (6 assertions), PHPStan at max, and Pint in test mode.
- **`bin/commit.php` refuses a named set whose paths all already hold what `HEAD` holds, so a second run of a commit that already happened cannot publish a changelog bullet for work that is already in the history.** The state is invisible from the tree — the files a finished commit left behind are the files a pending one names, which is why it cost this repository real time when eight groups were named after the work had been committed — and git cannot refuse it either: the one thing such a commit stages is the bullet this program writes itself, so without the guard the result is a commit whose entire diff is a changelog entry describing a change that is in the history already. `carrying()` compares the working tree with `HEAD` for every named path **before** the bullet is written, and a set that carries nothing exits 1 naming its paths: refusing after the write would leave a bullet for a commit nobody made, which is the failure the bullet exists to prevent. The working tree rather than the index, because this program stages from the filesystem — a path staged and then put back to `HEAD`'s bytes stages nothing — and two calls for one answer, because neither knows both: `git diff --numstat HEAD` reports a tracked path that moved and says nothing about a file that is not in the history yet, and `git ls-files --others` is exactly those, `--exclude-standard` left off on purpose because a named path the ignore rules hold is staged with `-f` a few lines below. A set that names only `CHANGELOG.md` is the same state said the other way and gets its own sentence — unless `--no-changelog` is passed, where an edit to that file is the caller's and counts as evidence like any other path, so a changelog fix is still a commit. `README.md`, `docs/scripts.md` and `docs/tool-registry.md` carry the second refusal in their rows, and `docs/guards.md` was re-rendered so the guard's own row does too. `CommitTest` plants the state on a fixture whose work is committed — requiring exit 1, the refusal naming the path, `HEAD` unchanged, the changelog byte-identical and the index empty — and holds the other direction as well: an untouched file named beside a moved one still commits, which is what keeps the guard from refusing an ordinary set. Measured: `CommitTest` 20 passed (67 assertions), `bin/commit.php` at 78.2% (197 of 252 lines) against its 70% floor under PCOV 1.0.12, Pint in test mode, PHPStan at max and Rector's dry run clean, and `bin/index.php --check` at exit 0 after the row was re-rendered.
- **The planted tree is this package's own work, so a spill from the checkout beside it cannot turn
  every hold red.** `MutationHarness::tree()` copied `MachinePaths::files()` — every file a commit
  would carry, which is the tracked set plus the untracked files the ignore rules leave alone. That
  is the right set for the path readings on the real checkout, and it is exactly why a spill is
  named there: an untracked file from the package next door is a file a commit here would carry.
  It is the wrong set for a hold, because a hold begins by requiring its reading to come back with
  nothing on the unmutated tree — so one spill reddened every hold for a reason no mutation planted.
  The handoff measured it on 2026-10-07: `MutationHarness/MachinePathsTest.php` was 2 failed and
  `MachinePathsTest.php` 1 failed, all of it `.agents/sample.machine.local.json`, and not one line
  of this package's code.
  `MachinePaths::untracked()` now answers the half of the listing git does not have yet, and
  `MutationHarness::carried()` keeps an untracked file only when `MachinePaths::in()` and
  `RepoEscapes::in()` — the two readings that define a file this repository will not have — name
  nothing in it; a tracked or ignored file never takes part, and a file that cannot be read as text
  is carried rather than guessed about. `tests/Unit/Support/MutationHarnessTest.php` asks the rule
  of a fixture with a tracked file, an untracked note this package wrote, a sample machine file and
  a script that climbs out of the root, and requires the last two to be left behind while the first
  two are carried. What does not change is the colour of the real tree: both readings still run
  against the checkout, so a spill is still named there, which is the finding the checkout is
  supposed to carry. Measured: `MutationHarnessTest` 5 passed (16 assertions), `MachinePathsTest`
  with `RepoEscapesTest` 59 passed (78 assertions), the machine-path and guard-index holds 8 passed
  (26 assertions), and `bin/index.php --check` at exit 0 after the harness row was re-rendered.
- **The line `GuardIndex::declared()` reports is the line the declaration is on, and the CRLF test
  asks its own plant before it reads it.** `declaration()` counted newlines to the start of the
  match, and the decoration class that match begins with carries `\s` — so on a header written the
  way every file in `tests/Support/` writes one, with a blank ` *` line above the tag, the match
  starts on that line and the reading names the line before the declaration:
  `tests/Support/MachineShape.php` said 47 for a tag on 48, and every declared file was one line
  early. Nothing consumed the field, which is why it stood; the test that reads a CRLF checkout asks
  it now. That test also stopped trusting its own fixture — it asserts the planted file holds CRLF
  and no bare LF, and that the source it was planted from holds none, so a fixture written with this
  checkout's own endings cannot leave the `\r` in the pattern untested — and asserts the reported
  line is the one computed from the source rather than typed. The fix is one offset, counted to the
  word instead of to the decoration in front of it.
- **The documented gate command can finish again, because `composer.json` raises the process timeout that was killing it at 300 seconds.** Composer gives every command a script starts a 300-second cap by default, and nothing in this package had ever said otherwise — while the gate it documents as the one a tag is cut behind measures 607.9 seconds here, and the coverage floor behind `composer test:unit` is longer still. So `composer checks` died mid-run with `The process "… bin\checks.php --require-all" exceeded the timeout of 300 seconds` and exit 1, having printed nothing about the eleven checks it was part-way through: a command that reads as a failing package and is a failing wrapper. It was never the gate — `bin/checks.php --require-all` under the same interpreter reads `11 checks: 11 passed, 0 failed, 0 skipped` — and it was not only a workstation: `python .agents/verify.py`'s `gate` step runs `composer checks -- --require-all`, so the same cap made the package's own reading red for a run that had passed. `config.process-timeout` is `0` in `composer.json`, which is Composer's own word for "this is not the deadline a run of this package is measured by": a number would be a second copy of ten minutes, kept beside  the program that knows how long the gate takes and raised again the next time the suite grows. Nothing about the gate's work changed, and the next thing that runs long — Pest under coverage, a `composer update` on a cold cache — inherits the same answer. Measured after the change: `python .agents/verify.py --only=gate` reads `[ OK ] gate` and exits 0, where the same step before it reported the timeout as its finding.
- **The selftest's planted layer is a copy of the files a commit would carry, so a file this tree
  ignores can no longer be read as the layer's own content.** `python .agents/verify.py --selftest`
  copies this checkout's layer into a temp tree, plants one break in it, and requires the step that
  owns that break to name it — and it refuses to read a break on a copy it has not first established
  to be clean. The copy was made with `shutil.copytree` over the entries in `LAYER`, so it took
  everything on the disk. The planted tree is not a repository, and a step that asks git which files
  a commit carries in a checkout walks the directory where there is none: `.agents/
  sample.machine.local.json.txt` — a machine file for the package next door, ignored here and in no
  commit — travelled into the copy and was read there, where the checkout's own ignore rule could not
  reach it. Two rounds reported it (`toolchain` and `interpreter-restated`, both of which read the
  interpreter rule), the cleanliness precondition failed on the copy rather than on the mutation, and
  both printed `the planted layer is not clean`. Both readings were green on this checkout the whole
  time: the difference was never in either reading, it was in the tree they were handed. `plant()`
  now asks the question the steps ask — one `git ls-files --cached --others --exclude-standard` over
  the checkout, the same listing `repository_files()` reads — and carries the files it names, plus
  `.agents/machine.local.json` explicitly, because that file is ignored on purpose and the renderer
  the plant runs afterwards needs it to write the settings `tokens` judges rather than inherit
  whatever this machine happened to leave in the checkout. Where there is no repository to ask, the
  copy is the directory as before, which is the one case the walk exists for. Measured: `--selftest`
  now reads `14 mutation(s) planted, 0 not reported`, exit 0, where it read two `[FAIL]` lines before.
- **The redundant cast in `AcceptEncoding::preferences()` is gone, so the gate's `rector` row is
  clean again and `composer checks -- --require-all` can be run at all.**
  `$coding = strtolower(trim((string) array_shift($parameters)))` carried a cast that
  `RecastingRemovalRector` reads as unnecessary, and the row has been red on it — which nothing in
  front of a commit asks about, because the pre-commit hook runs syntax, style, the tests that cover
  the staged files and the doc links, and Rector is not one of them, so the finding arrived and stayed
  while every other reading in the gate was green. Removing the cast outright, rather than replacing
  it with a coalesce, is what the two readings agree on: PHPStan at max answers `Expression on left
  side of ?? is not nullable` for `?? ''`, because `explode()` answers with a non-empty list and its
  first item is a `string` — which is also the reason the cast was redundant. The line is
  `trim(array_shift($parameters))` with that reason written beside it, `tests/Unit/Support/
  AcceptEncodingTest.php` reads 30 passed, and both `rector --dry-run` and `phpstan analyse` exit 0.
- **`bin/commit.php` writes its changelog bullet into a file that arrived with CRLF endings, and keeps those endings.** `withBullet()` matched `## Unreleased` with a literal `\n`, so a `CHANGELOG.md` saved with Windows endings — a tree staged before `.gitattributes` pinned the text files to LF, or one an editor wrote that way — was refused with "no `## Unreleased` section to write into", which named the changelog for a fault that was not in it and left its endings to be rewritten by hand. The heading is matched with `\r?\n` now and the bullet is joined with the ending the matched heading carries, so a CRLF file gains `### Fixed\r\n\r\n- …\r\n` rather than an LF line inside it; `tests/Unit/Commit/CommitTest.php` has two tests that plant CRLF rather than inherit it, one for a section that is not there yet and one for a section that is already there, each asserting there is no lone LF in the result. The program header also named a test file that does not exist (`tests/Unit/Gate/CommitTest.php`); the file is `tests/Unit/Commit/CommitTest.php`.
- **The machine-path guard reads a drive path with a space in it as the path that was written, instead of stopping at the space.** The drive rule ended a finding at the first whitespace, so a path under a directory whose name carries a space came back truncated at the space — an address that names nobody, out of the guard whose whole job is to say which machine a path names, and a finding a reader could not search for in the file it was reported from. The rule is now written as a segment and a run of separators (`SEGMENT`, `SPACED`) rather than as a run of characters: a directory whose name carries one space — a program's install root is the shape that made this visible — stays one segment, while a run of two or more spaces still ends the path, because a run is the prose a path is written in and not part of one. That second boundary is deliberate and named in the constant, because a rule that read the whole run as a segment would join the two paths of a sentence into one finding — a report about a path nobody wrote, where the shape being reported is two. Four specimens were added to `tests/Unit/Support/MachinePathsTest.php`: a spaced directory, the same path back-slashed, two paths in one sentence staying two, and the tolerated Windows directory with a sentence after it. The mutation harness holds the fix rather than trusting it: a third machine-path break plants the same leak with a space in one of its directories, and the reading has to name that path whole — a reading that stopped at the space answers with a prefix of the marker, which the hold reads as the guard having gone quiet.

- **The guard index can read a Windows checkout, so `docs/guards.md` is no longer unreadable on one.**
  `GuardIndex::DECLARATION_PATTERN` ended `[ \t]*$`, while every file it reads is read as the bytes on
  disk — and `* text=auto` in `.gitattributes` meeting a `core.autocrlf` of `true` puts CRLF in a
  working tree, where `$` does not match in front of the `\r`. So on this machine the index refused
  `tests/Support/CoverageEnvironment.php` for carrying no declaration, which it carries on line 41 of
  its own header, and five test files went red with `bin/index.php --check` exiting 1 — on a tree
  `git status` calls clean, because the blob at `HEAD` is LF and the working copy is not. The line's
  end is `[ \t\r]*$` now, `tests/Support/IndexNumbers.php` reads its own pattern the same way for the
  same reason, and both say why beside the pattern: this is the one place where what a reading has to
  read is a line ending rather than a fact about the package.

- **The CRLF fix has a cover of its own, and the cover does not need a CRLF checkout to fire.** The
  two line-end classes were right by construction and were exercised by nothing: what made them
  necessary was *this* worktree, so on an LF checkout — CI, or any clone a `core.autocrlf` never
  touched — setting either pattern back to `[ \t]*$` would have left the whole suite green. Two
  tests plant the condition rather than inherit it. `tests/Unit/Docs/GuardIndexTest.php` writes a
  tree whose declaring files carry CRLF endings and reads them back through `GuardIndex::declared()`,
  `guards()` and `drift()`, both header styles among them; `tests/Unit/Docs/IndexNumbersTest.php`
  rewrites its copied gate with CRLF endings and requires its `$checks` keys to read the same as
  before the rewrite. Measured rather than assumed: with the `\r` put back out of both patterns both
  tests fail — one naming a file that carries no declaration, one a gate whose `$checks` array reads
  as no checks at all — and with the patterns as they ship they pass, with the two files byte-identical
  afterwards. The document in the guard-index fixture stays LF while the files around it do not,
  because that is the shape of this machine's own checkout and the reading has to hold it.

- **`--init` keeps the machine file it did not write in full.** The file carries this machine's own
  record beside the three sections `--init` is asked for — the interpreter's version and extensions,
  what this checkout is at, what an install receives — and `--init` built its three answers into a
  fresh mapping and wrote that over the file, so re-naming a machine took the record with it: handed
  a fourteen-section file, the old behaviour wrote three sections back and truncated `php` to the two
  keys it owns. `.agents/render_local.py` now declares the sections the file may carry and what each
  one is for, updates only the keys it owns, and keeps every other line — including a section it does
  not declare, because a rule with an exception for the names a program happens to know is a rule
  that deletes the next person's work. A new file is opened with the declared shape, the run says
  which sections it kept, `.agents/machine.local.json.example` is the whole shape rather than the
  three answers, and `--force` re-names the machine instead of replacing the file.
  `tests/Unit/Bootstrap/BootstrapTest.php` now writes a section beside the answers and requires it to
  survive a `--force` run — an assertion the old behaviour fails, which is what was measured rather
  than assumed.

- **The notes signal weighs a `BREAKING` claim where a note makes one, and not where a note names
  the marker.** `changelogSignal()` read the `## Unreleased` section with
  `str_contains($unreleased, 'BREAKING')`, so a section qualified as breaking if the word appeared
  anywhere in it — including in a note about this very signal, because documenting the marker means
  writing it down. Past `1.0.0` that made every such section declare its own release a major: the
  weighing that exists to refuse a bump nobody asked for was itself asking for it. The reading is now
  `breakingClaim()`, which finds the word where a changelog makes one — at the start of a line's
  prose, behind the bullet, quote or emphasis the note opens with — and drops a fenced block whole,
  because a fence is an example being exhibited, with the code spans wherever they sit on their line.
  It deliberately cannot read the sentence: a claim written mid-sentence weighs nothing, and the
  remedy is the marker's own — give it a line of its own. Both directions are pinned by
  `it('weighs a BREAKING claim, and not a note that only names the marker')` — three notes that
  mention the marker weigh `1.0.1`, three that claim one refuse the declared `--minor` as a major —
  and putting `str_contains()` back fails that fixture by name. `RELEASING.md`'s signal row now says
  what a claim is, because that table is where a reader learns what the script reads.
- **The push runbook's evidence table was re-read against the tree, and every number in it is the
  one the repository answers now.** `PUSHING.md` recorded the lane at `9e00b11` and said the two refs
  named the same commit, and a push has since moved it nine commits: the reflog's newest entry
  carries `9e00b11` → `4e1f561`, so the table says the tracking ref names `4e1f561`, `HEAD` is
  `84eefbd` thirty commits past it, `git status -sb` reads `[ahead 30]`, the public probe answers
  `4e1f561 refs/heads/development`, the reflog holds six `update by push` entries rather than five,
  and `git rev-list --left-right --count main...development` answers `0 51` rather than `0 12`. The
  tag rows were re-read and stand as written: all eleven tags are on the remote with the hashes this
  clone holds — a `diff` of the two listings is empty — and every one but `v0.0.1` is annotated. The
  closing section is the part that could not simply be confirmed, because the lane is no longer in
  sync: a bare `git push` now has thirty commits to send, and the commits past `v0.0.10` divide into
  the fifteen that are published and the thirty that are local only. The credential and ssh rows were
  re-run and left as written.

- **`bin/release.php` no longer says every version is cut from `main` when this repository's lane is
  `development`.** The header's `ONE LANE AT A TIME` paragraph opened with "Everything is cut from
  `main`, releases and prereleases alike", which reads as a statement of fact about this repository
  and is false about it: the workflow gates `main` and `development` and says every cut here passes
  `--branch=development`, `git branch --contains v0.0.10` names `development` alone, and `PUSHING.md`
  has a row for exactly this distinction. The default is `main` and is meant to be - it is a
  repository's own lane that decides where a cut is legal, and the rail refuses any branch the
  release was not told to expect - so the paragraph now says that instead, and the usage line for
  `--branch=NAME` names `development` as the lane this repository cuts from rather than leaving a
  reader to find out from a refusal. One comment repeating the same claim, on the inventory path
  that runs before the branch rail, was corrected with it. No behaviour changed: the default, the
  refusal and its message are the ones that were there.

- **The agentic check reports a rendered settings file that drifted even outside a git repository.**
  `verify.py`'s `tokens` step asks `render_local.py --check` for the settings files a tool reads and
  collects its findings - but it did so only inside a git repository, so every other tree reported the
  step green while comparing nothing: a copy of the package beside a site, an archive a consumer
  unpacked, and the program's own planted fixture all skipped the question entirely. The comparison
  reads a template and the file rendered from it and asks git nothing, so the repository gate is gone
  and the report is the same in every checkout. What does still need a repository is the rule that
  `.agents/machine.local.json` stays ignored, and that one keeps its gate. The half now also has its
  own planted-fault round under `--selftest`: a tree rendered from a machine file of the round's own,
  a hand-edited `.trae/settings.json`, and the report required to name the file - which the generic
  round cannot do, because the files it compares are per machine and are rendered rather than
  committed.

- **A shipped record no longer points at a file an install does not carry.** `docs/min-length.md`
  linked `tests/Unit/Config/ConfigDocTest.php`, which resolves in this checkout and in nothing a
  consumer is handed: `.gitattributes` leaves `/tests` out of the archive, so that link was dead in
  every copy but this one. The docs-link guard cannot see it, because it resolves a link against
  the checkout, where the file is - so the fix belongs in the record, and it is the shape the other
  design records already use: `tests/Unit/Support/ConfigTest.php` and
  `tests/Unit/Middleware/CompressResponseTest.php` are named in backticks rather than linked. The
  path is still there to be read and still says which test to run; what is gone is a pointer that
  only worked for whoever had the repository.
- **A response left uncompressed now says it varies by `Accept-Encoding`, so a shared cache
  cannot mix it with a compressed body.** The encoders set `Vary: Accept-Encoding` on the bodies
  they compress and nothing set it on the ones they did not, so the two representations of one
  URL read to a cache as one resource: a response left alone for a client that asked for
  `identity`, named no encoding this package offers, or came from a user agent it refuses
  carried no `Content-Encoding` and no `Vary`, while the same URL is compressed for the next
  client. `CompressResponse` now marks every response it considered and left alone - the
  request-refused paths, the response-refused paths (unsuccessful, not a string, a stream or a
  binary file, below `min_length`), the encoding it cannot produce, and the multiple-encoding
  walk that finds nothing - so the header belongs to the URL rather than to the one response an
  encoder happened to write it on. The mark is idempotent: `Vary` is appended to rather than
  rewritten, an application's own `Vary: Origin` survives beside it, and a body an encoder has
  already marked is not marked twice. The reasoning, and the alternatives that were refused, are
  in docs/vary-on-uncompressed.md.

- **The push runbook describes this repository's lane, and its evidence table was re-read claim by
  claim.** `PUSHING.md` was written from reads and said so — "no push was exercised" — and four of
  its claims did not survive being checked against the tree as it is. The branch was recorded as
  `main`, which made the recipe's `git push origin main --follow-tags` a command that could not
  have pushed a release that exists: the lane is `development`, and every tag since `v0.0.9` was
  cut there. The tag range stopped at `v0.0.9` where it reaches `v0.0.10` and `v0.0.10-alpha1`,
  every hash of which matches this clone's, so nothing published was rewritten to say otherwise.
  "The tags are annotated" was one tag too broad — `v0.0.1` is lightweight, which is what makes it
  the single tag `--follow-tags` would not carry — and the closing section answered a question
  about a tree that had moved on. What stands behind the recipe now is the push itself rather than
  an assembly of verified parts: `git reflog show origin/development` is five `update by push`
  entries, the newest carrying `f25d5c4` → `9e00b11`. Rows that were already true were left as
  written, and the two a reader would otherwise assume backwards are said twice — the lane is
  `development` and the release is cut from it, and `main` holds `v0.0.9` twelve commits back
  rather than leading. The section answering what a push would send no longer answers "nothing" as
  though that were a property of the repository: it says what was read, and hands over the three
  commands that answer the question now, because the commit carrying the sentence is already past
  the reading it reports.

- **A pointer an installed package cannot follow is gone.** `RELEASING.md` and `PUSHING.md` are
  runbooks for whoever cuts a release, and `.gitattributes` leaves both out of the dist — while the
  README linked to each of them, and this file's own preamble named one as the place the numbering
  is recorded. A reader with the package met a dead link, or went looking for a file that was never
  installed. The two links are gone and the preamble says where the record lives and why it does not
  travel with the code; the mentions inside released sections are left as they were written, because
  a release's notes are a record of what that release said.

- **The tool scripts run the tools under the PHP that installed them, not through the `vendor/bin`
  shims beside them.** A shim is generated by Composer for the platform that installed the
  dependencies, and on Windows the `.bat` one runs whichever `php` is first on `PATH`. On a machine
  with more than one PHP that is a different interpreter with a different set of extensions, and it
  is not hypothetical: `composer test:unit` reported that no coverage driver was available, while
  the same `pest`, run by the PHP that had installed the dependencies, reported 100.0%. Every script
  that runs a tool now reaches it through `bin/tool.php`, which runs the file the manifest names
  under `PHP_BINARY` — the interpreter Composer itself is running under, so it needs no shim, and
  it resolves the same way on Windows and on Linux. A checkout whose Composer is the
  `composer.phar` beside it rather than a `composer` on `PATH` runs the same scripts as
  `php composer.phar <script>`.

## [v0.0.10] - 2026-09-28

### Changed

- **The development tools are pinned, and a monthly run deliberately unpins them.** Every
  tool `require-dev` named was a range — `2.*`, `1.*`, `^10.6.0|^11.0` — so a runner resolved
  whatever was newest that day while a workstation kept whatever it last installed, and this
  release went out on a green workstation and a red runner. The tools the gate runs are named
  with exact versions now, `pestphp/pest` among them: the binary `bin/checks.php` runs was
  arriving transitively, so the one tool the tests are actually run by was the one nobody had
  named. `.github/workflows/main.yml` gains a monthly `schedule`, and a `workflow_dispatch` for
  asking sooner — that run loosens the pins, updates, and runs the same gate, so the newest
  tools are exercised on a date nobody is releasing on. What sits below the pinned tools still
  resolves fresh; only a lockfile would close that, and this repository has none by choice.

- **The coverage floor the unit gate sets is met again, on the coverage it was failing on.**
  `bin/checks.php` runs Pest without coverage on purpose — the same gate has to work on a machine
  with no coverage driver — so `pest --coverage --min=100` is asserted by a CI step of its own, and
  that was the red step: 95.6% against 100%. What was missing was tests, not middleware. The
  statements were the paths a reader takes when a key is simply not set (`boolOr`, `stringOr`), the
  `string()` and `stringListOr()` refusals, the guard for a response whose content is not a string,
  and two `describe()` arms that no reader reaches. 144 tests before, 166 after, `src/` back at 100%.

### Fixed

- **`rector.php` asked Rector for a prepared set that no longer exists, and that alone is
  what the tag published as v0.0.10-alpha1 went out behind.** `Unknown named parameter
  $strictBooleans` is a fatal error, so the run is red. Rector had already deprecated that set
  as risky and not practical — 2.2.4 printed the warning and ignored the key, which is why
  this machine's gate said `rector PASS` — and 2.6.7 removed the parameter, so the same
  configuration is an error wherever the newest Rector is installed. The line is gone, which
  is what the deprecation asked for. Nothing else in the gate failed on the newer tools:
  syntax, AST, the composer schema, the platform requirements, the workflow YAML, PHPStan,
  Pint and all 144 tests passed.

- **A response that was not successful logged itself as a binary file, and now says so.** The
  response-side checks run in order — binary file or stream, then a status that is not successful,
  then a body that is not a string — and the second and third were writing the same sentence, so a
  500 was recorded in the diagnostic output as a stream skip. `enable_logging` exists to answer "why
  was this response left alone", and for every failed request that answer was wrong. It reads
  `Response is not successful - Response compression skipped` now; which responses are compressed is
  unchanged.

## [v0.0.10-alpha1] - 2026-09-28

### Added

- `bin/checks.php` through `composer checks`: PHP syntax, an AST parse of the same files,
  `composer validate --strict`, the platform requirements, the workflow YAML, PHPStan, Pint,
  Rector and Pest in one run, with one summary and one exit code. CI runs it on `main`, on
  `development`, and on every tag that would publish a version.
- `bin/release.php` through `composer release`: works the next version out from the last tag and
  the changes since it, promotes the changelog, commits and tags — and cuts prereleases with
  `--prerelease=alpha|beta|rc`, counted from the tags themselves rather than remembered. It
  refuses a release over a red gate, over a commit the remote has not seen, over a tag that
  already exists, and over a changelog heading with no tag behind it. See RELEASING.md.
- `Uak35\ResponseCompression\Support\Config` and `InvalidConfigurationException`: the readers
  every configured value now goes through.
- A test that fails when a file a commit would carry holds a path that only resolves on the
  machine it was written on — a drive letter, a home directory, a network share. `C:/Windows/…`
  and `/home/runner/…` are named in the detector as the shapes that name nobody.
- `.githooks/pre-commit`, enabled once per clone with `git config core.hooksPath .githooks`:
  syntax, style and the docs links over the files a commit carries, through
  `bin/checks.php --staged`, so those three cannot land broken. It bypasses with
  `git commit --no-verify` and skips, saying so, when it cannot find a PHP interpreter.
- **`enable_logging` writes a line for every compression decision.** The key was published in
  v0.0.9 with both of its docblocks saying that nothing read it; it now reaches the middleware's
  debug logging — not enabled, not allowed for this request, skipped and why (binary or streamed,
  not successful, not a string, under the floor), which encoding was used, the user agent it
  refused — most of the lines naming the request. Off by default, and deliberately not part of
  `Config::validate()`: a line in a log is not worth refusing to start an application over. Every
  key the config file publishes now has a reader. Two tests turn the switch on and off around one
  request and assert the exact lines a compressed response writes — and none at all when it is
  off, which is the half that would catch the key going inert a second time. See
  docs/unwired-config.md.
- **The inventory — `files.tsv` and `methods.tsv` at the package root — written by the release and
  weighed by it.** A release writes both into its own commit, stamped with the tag it creates, and
  the next release reads them as a fourth signal — but only while that stamp names the tag being
  released from. A pair written at some other moment cannot tell "nothing changed" from "not
  refreshed", so it is reported as stale and skipped rather than believed, and the tag diff stays
  the authority; both stamps have to match, because a disagreement between them is one file edited
  on its own. What it catches is what a tag diff and a commit log can miss: a file that was renamed
  or removed, a public method that went, a required argument that appeared. `composer release --
  --inventory` writes both files once for the tag a tree is built on and stops, so a package that
  adopts the signal mid-life weighs a real record on its next release instead of the one after —
  which is what this package's own two files are, written for v0.0.9. See RELEASING.md. Both
  files are pinned to LF and marked `export-ignore` in `.gitattributes`, because a row is read
  back with an `explode` on the tab — a carriage return left on the last cell would make two
  identical rows compare unequal — and an installed package has no tree a tag describes.
- **`extra.branch-alias` for the two dev lanes, kept in step by the release.** `dev-main` and
  `dev-development` now resolve as the line being developed, so a consumer requiring `^0.0` can
  install a branch as readily as a tag. A patch on that line leaves them alone; a release that
  opens one moves them. Only those two values are ever touched, and only when they are wrong — the
  rest of `composer.json` is left byte for byte, in the file and in the release commit.

### Changed

- **The package's PHP floor moves from `^8.3.0` to `^8.4`.** v0.0.9 required 8.3 and this
  release requires 8.4, which is a change a consumer on 8.3 meets at `composer update` rather
  than in a test: the requirement is the announcement, with nothing that fails later to repeat
  it. It is the version the gate runs under, the version PHPStan analyses as, and the single
  leg the CI matrix builds, so the number the package promises and the number anything is
  exercised on are one number rather than two that agree by habit.

- **A configuration value that cannot be read is refused instead of being replaced with a
  default.** A value that *is* what the key requires is read — including the string a value from
  `.env` always arrives as — and anything else stops with the key, the value and the file to fix
  it in. The three silent fallbacks this replaces were all in the wrong direction: `min_length`
  became a floor of **0**, so everything was compressed, and a level became the default, so
  setting it did nothing. See docs/config-reading.md.
- **The brotli level is read from `response-compression.br.level`.** It was read from
  `response-compression.brotli.level`, which is not a key, so the lookup returned `null` and the
  level was always the default however the config or `.env` was set. The `br` section is the one
  the encoder's settings live under, and the name the middleware uses for this algorithm.
- Every configured value is read once at boot, so a value that cannot be read fails at start-up
  rather than on whichever request first needs it.
- PHPStan is pinned to the PHP floor the package promises (`phpVersion: 80400`) rather than
  analysing against whatever PHP the analysis happens to run on, and PHPUnit now fails a run for
  a warning, a notice, a deprecation or a risky test rather than reporting it and exiting 0.
- **A version named with `--version=X.Y.Z` is held to the same floor `--minor` is.** The step its
  digits imply is what is weighed, so `0.0.9` to `0.0.10` is a patch whatever the notes say;
  releasing below the weighing needs `--ignore-policy`, and the plan prints which number was
  declared — `bump  patch  (declared: --version=0.0.10)` — and the override it needed, rather than
  letting a version choose quietly. A dry run reports the refusal it is avoiding instead of
  printing the plan as though it were releasable.
- The refusal for a changelog heading with no tag behind it names a third way out: the next
  number above the newest tag that no heading claims, which is the tag sequence continuing past
  an inherited section rather than jumping over it. That is the number this release took.
- **The release suite builds one repository and copies it, and the gate runs the whole suite in
  parallel.** Every fixture used to build the tree it handed back — `git init`, seven
  `git config`, a commit and a tag — which is about 550ms of process spawns, and the release
  suite asks for one 52 times: 28 of its seconds went on planting the same repository over and
  over. A tree is copied now from a template written once per tag and changelog body, and the
  fixture's git settings are handed to every child as `GIT_CONFIG_GLOBAL`, so no process it
  starts can read the settings of the machine it runs on. `bin/checks.php` then runs Pest the way
  `composer test:unit` already did — in parallel — while `--staged` stays sequential, because one
  file handed to a worker per core is more booting than testing. Neither the tests nor what they
  assert changed — 144 tests, 360 assertions, the same names — and a fixture is about 60ms to
  copy where it was about 550ms to plant. The gate's whole-suite check runs in 29s rather than
  the 69s it takes serially.

### Removed

- **`CompressResponse::validateRequestForAlgo()` is no longer part of the middleware's public
  surface.** It was public in v0.0.9 — `validateRequestForAlgo(mixed $compressionAlgorithm,
  SymfonyRequest $request): bool` — and the two methods that called it now go through the private
  `validateRequestPerAlgoAndUserAgent()`, which reads the request's encodings rather than the
  request itself. Nothing documented pointed at it, but a public method on a class a consumer can
  replace is part of what a version number promises, so the release's own surface reading and its
  inventory both weigh this as a breaking change — which is why `v0.0.10` is a step the policy has
  to be told to accept rather than one it takes on its own.

### Fixed

- A `min_length` set in `.env` no longer becomes a floor of zero: `RESPONSE_COMPRESSION_MIN_LENGTH=4096`
  arrives as the string `'4096'`, which is now read as 4096 rather than rejected by `is_int()`.
- A `gzip` or `zstd` level set in `.env` is honoured for the same reason, rather than being
  silently replaced by the documented default.
- Broken links between the docs are caught by a test, and so is a design record nothing links to.
- A key read that the config file does not publish, and a key published that nothing reads, are
  caught by a test that reads both sides — the pair of defects that made the brotli level and
  `enable_logging` invisible. See docs/guards.md for what is checked and what was deliberately not.
- **A public symbol that moved to another file no longer weighs as breaking.** The surface signal
  compared `file::symbol`, so a class that moved with its name intact read as a removal — the one
  change a consumer cannot survive — and an addition besides, which turned a release whose notes
  were a patch into one that opened a minor line. Symbols are compared by name now, because the
  name is what a consumer imports under PSR-4 and the path is not; a move that also gained a
  required argument is still narrowing, and the inventory, which has a path column, reads the same
  move the same way.
- The test that pairs those keys with their readers stopped asserting anything the moment the
  last unwired key was wired, and a test that asserts nothing is reported as risky — which this
  suite fails on. It now checks both directions whether or not either list is empty, so the
  clean state is tested rather than skipped.
- **The release fixtures stop leaving themselves behind in the temp directory.** Every fixture a
  release test built left a directory there: the sweep removed the tree and then failed on
  `.git`, because git writes its object files read-only and Windows will not unlink a read-only
  file, so the fixture root could never be removed. It chmods as it goes now, and retries the
  directory rather than giving up on the first refusal. Measured: 52 fixtures, 0 leftovers. The
  ones earlier runs had left behind — about 1900 directories, some 105 MB of them — were cleared
  after the fix was proven: they were nobody's working state, and none has been created since.

## Inherited from upstream

These releases are not this package's. They were cut from
[`chr15k/laravel-response-compression`](https://github.com/chr15k/laravel-response-compression),
the upstream this package was taken from, and they are kept whole — the notes, the credits and the
dates exactly as that project wrote them — rather than renumbered into a sequence they were never
part of. `0.1.0` was its first release, and `0.2.0` put Laravel 12 and PHP 8.2 support on top of
it. Nothing in this repository tagged either one, so neither is a version of this package, and the
numbers they used are not spent: the tags here count on from where this package's own line
stopped, and `0.1.0` is a number the line may still reach.

They sit at the third level rather than the second for that reason. `## [v0.1.0]` is a heading
this repository *documents*, which is the whole of what the release script refuses over: promoting
the notes in `## Unreleased` under it would leave one version with two sets of notes — the ones
written there and the ones being released — where the ones written there came from a release this
repository never made. Written as a sub-heading the history is still readable, the versions still
searchable, and the numbering free to reach `0.1.0` on its own.

### v0.2.0 - 2025-09-09

#### Changed

- Adds support for Laravel 12, PHP 8.2 onward, while maintaining full backwards compatibility with Laravel 11 by [@botnetdobbs](https://github.com/botnetdobbs) in https://github.com/chr15k/laravel-response-compression/pull/1
- Updated Pint configuration to improve type safety

#### Added

- Add GitHub Actions workflow for automated CI and repository checks

### v0.1.0 - 2024-12-29

#### Added

- Initial release