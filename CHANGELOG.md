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