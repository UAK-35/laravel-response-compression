# Which guards does this package keep, and which did it refuse?

**Because a guard is a promise about the future, and the only honest way to make one is to
say what it catches.** A companion package, `laravel-weighted-dbmanager`, carries a larger
set of checks than this one does, and comparing the two is what produced the list below:
each guard was judged on what it would have caught *here*, and for a package this size the
answer is not always "port it".

## Kept

The table records 38 guards, and it is rendered from the files the guards live in: each row's name
and its argument are a comment block in that file, and the file itself is the third column. What each
one catches is in the second column, and where it lives is in the third.

| Guard | What it catches | Where |
|---|---|---|
| `* text=auto eol=lf` | whether a working tree agrees with the repository about line endings, which decides what everything that reads a file as bytes on disk is answering for — this package, or the machine that checked it out, since Pint's `line_ending` rule, the `$`-anchored patterns and the two readings that own a declaration line all read the bytes a checkout wrote | [`../.gitattributes`](../.gitattributes) |
| `.githooks/pre-commit` | a commit carrying PHP that does not parse, style the project does not use, a source file the suite that covers it would fail on, a link between the records that no longer resolves, or a claim about a config key that the source has outgrown — by running `bin/checks.php --staged` | [`../.githooks/pre-commit`](../.githooks/pre-commit) |
| CI on `tags: ["v*"]` | a published version — release or prerelease — with no CI record against it | [`../.github/workflows/main.yml`](../.github/workflows/main.yml) |
| CI running the gate | a gate that is weaker than the one a tag is cut behind | [`../.github/workflows/main.yml`](../.github/workflows/main.yml) |
| `composer checks -- --require-all` in CI | a check that *skipped* on the runner — a tool that did not install is a hole in the build, not a fact about the machine | [`../.github/workflows/main.yml`](../.github/workflows/main.yml) |
| `bin/bootstrap.php` | a clone that is half set up and reads as ready: the three steps a fresh checkout needs — name this machine's PHP, install the dependencies, verify the tree — in the one order that works, each ending the run when it fails rather than being followed by the next | [`../bin/bootstrap.php`](../bin/bootstrap.php) |
| `bin/checks.php` | eleven checks in one process: syntax, an AST parse, the composer schema, the platform requirements, the toolchain probe, the agentic layer, the workflow YAML, PHPStan, Pint, Rector, Pest — one summary, one exit code | [`../bin/checks.php`](../bin/checks.php) |
| a lock-aware `composer validate` | a stale lock: the check runs only when the repository actually ships one | [`../bin/checks.php`](../bin/checks.php) |
| `composer check-platform-reqs` | a missing `ext-brotli` or `ext-zstd`, reported as a build problem rather than as a failing test | [`../bin/checks.php`](../bin/checks.php) |
| `bin/commit.php` | a commit that carries a file it was not told about: the index is read before anything is staged and the commit refused when it holds a path outside the named set — and the changelog bullet is written and the staged checks run before the commit exists | [`../bin/commit.php`](../bin/commit.php) |
| `bin/coverage.php` | the two scripts in `bin/` that no test can run directly: the coverage each child process writes is collected, mapped back onto the file it was copied from, and merged before the floor is read — 100.0% for every `src/` file on its own, a ratchet per script that may only rise, and a header naming the PHP and the coverage driver the run is under | [`../bin/coverage.php`](../bin/coverage.php) |
| `bin/index.php` and the three rendered tables | a table that has stopped being a rendering of the tree: which list a file in `tests/Support/` is in, the words beside its row, and each guard's own row are read from the file itself, this program writes the three tables below from that, and `--check` is what fails when the document and the tree disagree | [`../bin/index.php`](../bin/index.php) |
| `bin/push.php` | a push reported as done before the run it started was read — the commit that was pushed is asked of GitHub, the run for it is waited for, and only `success` exits 0; a push that started no run is refused rather than read as a pass | [`../bin/push.php`](../bin/push.php) |
| `bin/release.php` | a version cut from the wrong branch, over uncommitted work, over a tag that exists, over a red gate, over a commit the remote has not seen — and the numbering of prerelease lanes | [`../bin/release.php`](../bin/release.php) |
| `bin/tool.php` | a composer script that writes a tool's path down instead of naming a tool: the script names this program and the tool, the manifest answers with the path, and the exit code is the tool's own — a wrapper that swallowed it would report every script as passing | [`../bin/tool.php`](../bin/tool.php) |
| `phpVersion: 80400` | analysis against the PHP the analyst happens to run rather than the floor the package promises | [`../phpstan.neon.dist`](../phpstan.neon.dist) |
| `failOnWarning`/`failOnRisky`/`failOnDeprecation` | a test that warns, is risky, or leans on a deprecation and still reports green | [`../phpunit.xml.dist`](../phpunit.xml.dist) |
| the config readers | a value that cannot be read: refused with the key and the value rather than replaced by a default | [`../src/Support/Config.php`](../src/Support/Config.php) |
| `Config::validate()` | the same, at boot rather than on the request that first needs the value | [`../src/Support/Config.php`](../src/Support/Config.php) |
| the config-claim test | a docblock or a record that says a key is unread while the source reads it | [`../tests/Unit/Config/ConfigClaimsTest.php`](../tests/Unit/Config/ConfigClaimsTest.php) |
| the README↔config test | a published default that disagrees with the config file it documents | [`../tests/Unit/Config/ConfigDocTest.php`](../tests/Unit/Config/ConfigDocTest.php) |
| the config-key test | a key read that the config does not publish, and a key published that nothing reads | [`../tests/Unit/Config/ConfigKeysTest.php`](../tests/Unit/Config/ConfigKeysTest.php) |
| the changelog-preamble test | a preamble that goes on naming a version as inherited once this package has released it — the stale claim the next release would otherwise write into the file | [`../tests/Unit/Docs/ChangelogPreambleTest.php`](../tests/Unit/Docs/ChangelogPreambleTest.php) |
| the docs-link test | a broken link between the records, and a record nothing links to | [`../tests/Unit/Docs/DocsLinksTest.php`](../tests/Unit/Docs/DocsLinksTest.php) |
| the guard-index test | a table that has stopped being a rendering of the tree: a guard a file declares and the table has no row for, a row no file declares, a row whose words or link have drifted from the file, and rows out of the order the files declare them in — and beside it every file in `tests/Support/` says which list it is in, and every test in `tests/Unit/` says which guard it declares or that it is a test of the code, so the next guard cannot arrive unrecorded from either side | [`../tests/Unit/Docs/GuardIndexTest.php`](../tests/Unit/Docs/GuardIndexTest.php) |
| the number-claims test | a number this page states having stopped matching the code that produces it — how many checks the gate runs, the floor every file is held to, and how many guards and files the page is about — read out of the document and out of `bin/checks.php`, `bin/coverage.php` and the directory | [`../tests/Unit/Docs/IndexNumbersTest.php`](../tests/Unit/Docs/IndexNumbersTest.php) |
| the release-lane test | a branch written where a release or a push runs that is not the one releases are cut from: the lane is read out of the release command in `.agents/toolchain.json`, and the runbooks and the workflow's own trigger comment have to name it — so all four drift to `main` together or none of them does, and a sentence that mentions the default is left alone because the runbooks have to explain it | [`../tests/Unit/Docs/ReleaseLaneTest.php`](../tests/Unit/Docs/ReleaseLaneTest.php) |
| the gate's own tests | the script everything else is measured by, which no test had ever run — its exit codes, its skips and its refusals, in a planted tree | [`../tests/Unit/Gate/ChecksTest.php`](../tests/Unit/Gate/ChecksTest.php) |
| `tests/Unit/Gate/CoverageTest.php` | a floor run that keeps quiet about the machine it is on: the interpreter is named before the suite starts and again at the foot of a failure, so a floor that moved is attributable to the code or to the PHP — and a run that collected nothing says so rather than reporting every file at 0.0% | [`../tests/Unit/Gate/CoverageTest.php`](../tests/Unit/Gate/CoverageTest.php) |
| the log-message test | two reasons the middleware reports a skip as carrying one code, or two that write the same sentence — read out of the type that holds each sentence once, so a line a branch invents and a line two branches share are both the file's to refuse, and nothing in PHP refuses the duplicate code a backed enum is loaded with | [`../tests/Unit/Middleware/LogMessagesTest.php`](../tests/Unit/Middleware/LogMessagesTest.php) |
| the release rails | the rails of `bin/release.php` that decide where a release may be cut from — no repository, uncommitted work, the wrong branch, the lane the repository declares — each refusing the condition it is about through the fixture hand that puts a rewritten releaser in front of this test, which is the file the mutation harness takes the branch rail out of; the rails about what a release publishes and the number it takes are asked the same way in the files beside it | [`../tests/Unit/Release/RailsTest.php`](../tests/Unit/Release/RailsTest.php) |
| the handoff's state section | the state section of the handoff, held to what `handoff/state.sh` measures: its branch, its gate wiring and its changelog endings, every count and colour it states dated to the moment it was measured, and a section that says the script outranks it — so the snapshot a reader is handed cannot go on saying what the checkout has stopped doing | [`../tests/Unit/Support/HandoffStateTest.php`](../tests/Unit/Support/HandoffStateTest.php) |
| the environment table | the environment `AGENTS.md` §2 states — and the same section of the handoff beside it — held to `.agents/machine.local.json`: a name the renderer has never heard of, a token the machine file holds no value for, a recorded path that is not there, and a machine file naming another checkout while reading as a complete answer | [`../tests/Unit/Support/HandoffTest.php`](../tests/Unit/Support/HandoffTest.php) |
| the machine-path test | a path that only resolves on the machine it was written on — a drive letter, a home directory, a network share, and the same drive as a POSIX shell mounts it (`/e/…`, `/mnt/e/…`, `/cygdrive/e/…`) — in any file a commit would carry | [`../tests/Unit/Support/MachinePathsTest.php`](../tests/Unit/Support/MachinePathsTest.php) |
| the machine-file shape | the shape a fresh clone is opened with — `.agents/machine.local.json.example`, the committed shape a reader copies, and the sections `python .agents/render_local.py --init` writes — held to each other: a section declared in one and missing from the other, a key `--init` writes that the example shows nowhere, a section the example shows that no fresh clone receives, a token whose value `--init` never writes, and the sections a machine file has recorded nothing in — which `--check` names rather than letting the file read as complete | [`../tests/Unit/Support/MachineShapeTest.php`](../tests/Unit/Support/MachineShapeTest.php) |
| the mutation harness | the guards above, going quiet: one break at a time is planted in a copy of this checkout — the package's own work, with a spill from the checkout beside it left out rather than read through — a reading's detector is rewritten under its own tests, and a rail is taken out of the program whose test holds it — so a guard that stopped asking the right question, or a test that stopped asserting, fails there instead of reporting nothing forever | [`../tests/Unit/Support/MutationHarnessTest.php`](../tests/Unit/Support/MutationHarnessTest.php) |
| the repository-escape test | a relative path that climbs above the repository root — written out, or computed as `dirname(__DIR__, n)` — in any file a commit would carry | [`../tests/Unit/Support/RepoEscapesTest.php`](../tests/Unit/Support/RepoEscapesTest.php) |
| the tool-path test | a tool's path written down in a second place — in a composer script, or in one of the three programs that runs a tool — a `vendor/bin` shim, which is a second interpreter, a path that is not on disk, a name nothing asks for, an entry nothing runs, and the two facts an entry carries beside the path: a pin that is not the version `composer.json` requires or not the one installed, and an entry with no line saying what its tool is for; and the runner — `.github/workflows/main.yml`, the third place a tool can be invoked and the one that had no reader — held to the gate it has to run (`composer checks -- --require-all` and `composer test:unit`, with a shell comment in a step no substitute for the command) and to the manifest for a tool path written into it | [`../tests/Unit/Support/ToolPathsTest.php`](../tests/Unit/Support/ToolPathsTest.php) |

The gate drops a check to a skip when a tool is missing, which is right on a developer's
machine — half of these tools are `require-dev` — and wrong on a runner, where a tool that
failed to install reads as a green build. That is why CI runs the gate with `--require-all`:
it is the same gate, with the skips turned into failures where a skip can only be a mistake.

The hook is that same gate, earlier and narrower. `bin/checks.php --staged` — which is what
`.githooks/pre-commit` runs, under the PHP that `.agents/php-bin.sh` resolves — points its staged
checks at the files a commit holds: syntax over the staged PHP, Pint over the staged PHP, and
the tests those files are covered by. Which tests those are is `tests/Support/TestMap.php`: the
docs-link, config-claim and release-lane tests for a document or the config, and the suite that
covers a changed source file for the rest — so a commit that breaks a release rail runs the rails.
A mapped set runs as one process per path, all started together rather than one after another:
`--parallel` is paratest, which takes a single path and refuses several, and the narrowest
mappings are the ones one process at a time costs the most — the harness's hold directory is
fifteen process-spawning files, minutes on its own where the whole suite is one parallel run.
A mapped path that is a directory carries `--parallel` itself, so it still splits across
workers.
Those are what a commit can break on its own; the rest of the gate is a tree-shaped
question, because PHPStan and Rector read every file that references the ones you changed. It
reuses `bin/checks.php` rather than being a script of its own for the same reason CI runs the
whole gate rather than a subset: a second list of tools is a second list to keep in step.
Deletions are in the staged set even though no tool can be handed one — a deletion is what
stops a link resolving, and the file that broke is not the one that changed. When nothing
staged is something the three can read, they report a skip rather than a pass. And when no PHP
interpreter can be found, the hook skips with a message instead of blocking: a machine without
PHP must not become a machine that cannot commit.

The two README/config guards and the config-key guard are the three worth the most here,
because all three of this package's silent defects were a disagreement between two files:
the README advertised `algorithm` as `gzip` while the config shipped `br`, `min_length` as
`1024` while the config shipped `2048`, and the encoder read `brotli.level` while the config
published `br.level`.

The config-claim guard is the fourth of that family, one layer up from the code: a docblock
that says a key is unread is a claim *about* the read set, and `enable_logging`'s docblock in
the config file and in the README went on saying it was inert after the middleware had been
wired to read it. Neither file was wrong on its own, which is why nothing caught it — the
claim is only false beside the code that reads the key.

The machine-path guard is the one that came from something that had already happened rather
than from the comparison that started this list: [PUSHING.md](../PUSHING.md) was written on a
Windows machine, and a credentials table and a `cd` line came out of it carrying that
machine's paths. Both were edited out by hand, which is a fix that lasts until the next
runbook is written the same way. So the paths are read out of every file a commit would
carry — the untracked ones included, because a file that has just been written is exactly
where a path gets copied from a terminal — and the two shapes that name nobody are named in
the detector instead of left to the eye: `C:/Windows/…`, which is the same on every Windows
install and is the deliberate evidence in one row of that same table, and `/home/runner/…`,
which is the same on every CI runner. Each is tolerated in the mount spelling of itself too,
because `/c/Windows/…` is that same directory written the way a shell that mounts drives
writes it — one rule, so the two spellings cannot come to disagree about who they name.

The same drive is read in both spellings, and the mount one is the half a rule written around a
drive letter cannot see: `/e/workspace` is the same directory as the drive-letter form of it,
and only the drive-letter form was in the pattern — so a runbook rewritten into the shells this repository actually runs
from walked past the guard doing the rewriting. Three roots are read, the bare letter and
WSL's `/mnt/<letter>/` and Cygwin's `/cygdrive/<letter>/`, and two boundaries are deliberate.
The segment after the root has to be a single letter, which is what keeps a container path
(`/mnt/laravel/html`) out of it, because the name after `/mnt/` there is not a drive. And
there has to be a segment after that one, because no rule can tell a one-segment `/a/b` in a
sentence from prose — so a leak written as a bare mount root is the one shape this misses, and
that is the stated cost of not reporting every ratio in this document.

The repository-escape guard is the other half of that same question, and it exists because the
machine-path reading had a hole the shape of a relative path. `.idea/php.xml` named Pint and
PHPStan by climbing out of the checkout: no drive letter, no home directory and no share, so
nothing fired, and it is not a link, so the docs guard had nothing to resolve either. A relative
path leaks a layout rather than a machine, which is why reading it as a path is the mistake — it
resolves wherever the checkout happens to sit, so it is right for exactly one position and reads
as correct everywhere else. So the reading is a *walk* rather than a pattern, because the same
text means two things in two places: a climb from a record in `docs/` that stops at the package's
own directory is a link, and the same climb written in a file sitting at the root has nowhere to
go but out. Every token is walked from the depth of the file that wrote it, and only a walk that
ends above the root is a finding. `$PROJECT_DIR$/…` starts at the root, because a marker stands
for the root wherever it is written, and `dirname(__DIR__, n)` is walked the same way — a guard
that can be walked around by writing the same location in the other language is a guard that will
be. The shapes themselves are in the detector rather than in this paragraph, for the same reason
the machine-path ones are, and the listing and the binary test come from `MachinePaths` so that
the two readings cannot come to disagree about which files a commit would carry.

The log-message guard came from a defect that had already shipped: two of the middleware's skip
checks sat next to each other and wrote the same sentence, so an unsuccessful response was
recorded as a binary file — in the one output `enable_logging` exists to produce, which is where
anyone looks to find out why a response was left alone. It was found by reading the file and
fixed by hand, and a hand fix is what comes back. A test that pins a message pins it one branch
at a time, so a third branch copied from one of them passes every one of them. The fix is a type
rather than another reading: the middleware holds no sentence of its own, each branch names a
`Diagnostic` case, that reason's sentence is written once in the type, and every line the log holds
carries the reason's code in front of the sentence — so a line one branch invents and a line two
branches share are both impossible to write, and the two can never be read as one another. What
is left is what one file can still be written wrong, and that is what the guard reads: two cases
carrying the same code, which nothing in PHP refuses — the duplicate-value error arrives only when
the enum is asked for a case by value, which this package never does — and two cases writing the
same sentence, the original defect one file along. Both are reported with the cases and the code
or the sentence they share. The reading is held to more than its own collision rules, because a
guard that read nothing would pass: it has to find every case and every sentence rather than the
ones it happened to understand, a file it cannot read throws rather than reporting agreement with
one it never understood, and the codes and sentences it read are required to be the ones the type
declares — so the reading and the type cannot come to disagree about what is declared.

The coverage floor became a program of its own for the same reason the log-message guard reads
what the branches write instead of pinning each message: the number it had was not the number it
looked like. `src/` was measured from inside the suite, and the two scripts in `bin/` were not
measured at all — they run on include, they call `exit()`, and the suite reaches them as child
processes, so the floor read 100.0% while sixty tests drove `bin/release.php` end to end. What
changed is where the evidence comes from, not what is asserted: a child writes down what it
covered, and the merge maps a fixture's copy back onto the repository's file by comparing
contents, because a fixture's `bin/checks.php` is a three-line stub and reading it by name would
have handed the gate 100.0% coverage for a script that exits on line three. The floors are 100.0%
per file for `src/`, and a ratchet for each script — `bin/bootstrap.php` at 75%, `bin/checks.php`
at 54%, `bin/commit.php` at 70%, `bin/index.php` at 81%, `bin/push.php` at 81%,
`bin/release.php` at 78% and `bin/tool.php` at 89% — written down, because closing the rest of
those lines is writing more scenarios rather than deleting dead code. Two files in `bin/` are left
out of the report, each for a reason of its own. `bin/coverage.php` is the parent of the run it
reads, so its own lines are in no file any child writes; it opens by naming the machine instead:
the PHP it is under — version, path, and whether Composer started it, since `@php` is Composer's
own binary — and the coverage driver, or the sentence saying there is none and what would give
this machine one. The driver is asked of the process that will measure rather than of the one
the command was typed into — the suite is a child started with none of this invocation's
flags, so the script starts a second copy of itself in a probe mode and reads that answer —
and a run whose answer is that nothing can count stops before the suite, because a floor of
0.0% for every file is not a floor that was missed. `bin/tool-paths.php` is a list rather than a script — a `return [...]` of records,
whose lines PHPUnit counts as statements while PCOV reports the one statement once — so it reads
one low number however many processes read it, and a floor that cannot rise is noise; what can be
wrong with it is which entries it holds, which the tool-path test asserts. Neither file has a
number, and a floor that fails is either the code or the interpreter that ran it.

The tool-path guard is the third one that came from a defect rather than from the comparison
that started this list, and it is the same shape as the machine-path guard: a path that reads
correctly and is wrong. Every tool was named twice — four of the five in `composer.json`, because a
script is a shell string, and all five in `bin/checks.php`, which cannot be asked for its list
because it runs on include — and the two copies had drifted. The scripts reached their tools through
`vendor/bin`; the gate named the file inside `vendor/`. `vendor/bin/pest` and
`vendor/pestphp/pest/bin/pest` both read as Pest, so the difference is invisible in the path
and visible only in what it does: the shim is a `.bat` that runs whichever `php` is first on
`PATH`, and on a machine with two installs that is the interpreter without the coverage
driver.

The two lists are gone rather than reconciled: `bin/tool-paths.php` is the one place a tool is
written, the scripts reach a tool by naming it to `bin/tool.php`, and the gate asks the same
manifest. What one file cannot say about itself is what the guard reads — that nobody has written
a path down again, that every path is the entry file inside `vendor/` and is on disk, and that
every name is one something asks for and every asker's name exists. A name that is not there is
the quiet failure of the three: it reads as "not installed", which is a skip in the summary that
looks like a machine without the tool rather than a typo in a script.

An entry is a record rather than a path, and the same question is asked of the rest of it. A tool
is what the gate measures this repository with, so the version belongs beside the file: each entry
names the file that runs it, the version the package pins the tool at, and one line saying what it
is for. The pin is then read in both directions it can drift — against `composer.json`'s
`require-dev`, which is what this package asks Composer for, and against
`vendor/composer/installed.json`, which is what it got — because a tool that moved measures
something other than this tree, which is the `vendor/bin` failure one level up and just as
invisible in a path: a Pint that formats differently and a Pest that counts differently both read
as the tool that was named. The pin is the version of the package a tool's file lives in, and that
package is derived from the path — `vendor/acme/thing/bin/thing` is inside `acme/thing` — rather
than written beside it, so the path and the version cannot disagree about which tool they are
about. The third fact is what the gate reads: `bin/checks.php --list` prints each tool with its pin
and its purpose out of the manifest, so what the gate says about a tool is what the manifest says
about it rather than a sentence beside the gate that nothing holds it to.

The guard-index guard is the one that reads this file. A guard is only information about the
future if it is in front of whoever adds the next one, and this document is where that happens —
which makes the index the one file whose drift is every guard's drift. One direction was watched
already: a row linking a test that has been renamed fails the docs-link guard, because a row's link
is a link like any other. The other direction could not be watched at all, because no test knew
what a guard is — a guard added with no row reads as though it had always been in the list.
`GuardIndex` reads the table as a claim about a directory: every row links a file the repository
has, and every file in `tests/Support/` is named here — as a reading below, or as support in the
section that says it is not a guard. The two rows whose `Where` cell said "the same workflow" in
prose now link the workflow they meant, which is the same rule applied to the table itself: a row
that resolves nothing sends nobody.

Naming every file was the first half of that question, and it left the second half in prose. Once
every file has to be in one of the two lists below, each of those lists is a list of files — and a
list of files written into a document is a copy of a directory, which is the thing this page exists
to catch elsewhere. It went stale between releases: a file renamed in `tests/Support/` left its old
name in a row and its new name nowhere, the reading resolved the row's link, and the page read as
though a file that had moved had never existed. So all three tables are rendered now: the guards
from the files that declare them — a guard's own comment block carries `@guards-index guard <name>`,
and the file the block is in is the `Where` cell — and the two lists from the files that make them up,
each file saying which list it is in, in its own header, with its own first sentence as the words
beside its row. `bin/index.php` writes them; the
reading above the write, `GuardIndex::drift`, is one call and is asserted empty by the suite; and
the mutation harness breaks the tree under it the way it breaks it under every other reading,
because a rendering that answered "nothing to write" for every tree would pass every assertion
about a document in step. The one row this page had that the table reader could not see — the
guard-index row, sitting below a blank line of its own — moved back into the table it belongs to
for the same reason: a row no reader reads is a row that reads as though it were not there.

The number-claims guard is the same question one step further out, and it asks it of the prose. The
lists under the table are rendered now, so a file that moves cannot leave a stale row; the numbers
in the sentences were left out of that. "N checks in one process" is a fact about the `$checks`
array in `bin/checks.php`, "a ratchet for each script" is a fact about two constants in
`bin/coverage.php`, and the count of guards above is a fact about the table itself — each of them
true the day it was typed, and nothing to tell the sentence if the code moved. `IndexNumbers` reads
each number out of the document and compares it with the code that produces it, and it reads the
phrase that names the gate's size wherever a commit would carry it, because the README, the rules,
the gate's command and the workflow write the same claim this page does. A number that is no
longer stated at all is a finding rather than a silence, which is the difference between a reading
that found nothing and one that was never looking.

The mutation harness is the one guard on this page that is about the other guards rather than about
the package, and it exists because of the shape every reading here shares: a guard that stopped
asking the right question does not fail. It reports the same nothing it reports when there is
nothing wrong, which is what it is supposed to do — so the way a guard goes quiet is by going on
looking correct, and the way it is found is by handing it a tree with something wrong in it. Each
mutation plants one break in a copy of this checkout and requires the guard's own reading to name
it, twice on the same tree: once as it was planted, which is this checkout with nothing wrong with
it, and once with the break in it, so the mutation is the only difference between the two answers.
The reading is the call the guard's test asserts on rather than a second implementation of it,
which is why the harness covers the guards whose answer is one call — the two path readings, the
changelog preamble, the config claims, the README-versus-config comparison, the docs links, the
config keys, the tool paths, the guard index and the log messages. Where that call did not exist,
because the walk was composed in the test rather than in the support class the guard is about, the
walk was moved into that class, so there is one definition of the reading rather than a second one
written out in the harness: the docs links, the config keys and the tool paths each gave up the
walks their tests used to compose — `Docs::broken()` and `Docs::unlinkedRecords()`,
`ConfigKeys::unknown()` and `ConfigKeys::unknownReaders()`, `ConfigClaims::stale()` for the claims,
and `ToolPaths::copies()`, `ToolPaths::asked()`, `ToolPaths::unsharedNames()` and
`ToolPaths::required()` for the paths, names and pins — and each is now the call its guard asserts
on and the call the harness reads back.

Each hold is driven by a file of its own, one per guard, in `tests/Unit/Support/MutationHarness/` —
and that is a measurement rather than a tidy-up. `paratest` spreads work a file at a time and by
nothing finer, so one file holding every guard was one file the suite waited on: it was five hundred
and forty seconds of test time on one worker, while the other fifteen ran out of work beside it in
under a tenth of that — which is what most of the suite's wall clock was. The directory is what keeps the split from becoming a second list of
names to keep in step — a hold file asks the harness for the guard it carries, and the harness reads
that name back out of the file itself, so a guard that arrived with no file fails the harness's own
test and a file asking for a guard nobody holds is refused rather than run. The two controls sit in
`Controls/` beside the holds rather than inside one of them, because they are about the machinery
rather than about a guard — and because they are the slowest runs of the three directions, so each
of them gets a worker of its own rather than the file that carries the hold waiting on it.

One reading a guard asserts on is not mutated, and the reason is the tree rather than the reading:
`ToolPaths::pinDrift()` holds a pin to what `composer.json` requires and to what Composer recorded
installing, and the second half is `vendor/composer/installed.json`, which this repository ignores
and a planted tree therefore never carries. A fixture record would be the harness writing down the
pins the guard exists to keep in one place, so the pin half stays held by its own test against the
real record, and the exception is named here rather than left as a silence.

The harness is also inverted, once per reading, and it is the same guard broken the other way. A
mutation breaks the tree and requires the reading to name the break, which catches a reading that
stopped asking the right question of its input. What it cannot catch is a reading whose *test*
stopped asserting: a test that asks a call whose answer is empty both before and after, or one
whose expectations a walk that reached nothing satisfies. The reading would be broken and the suite
green, in both directions at once.

So the detector itself is rewritten — a pattern that cannot match, a walk that returns before it
descends, a marker no longer recognised — and that guard's own tests are run against it, and have
to go red. A test that stays green while the reading it is supposed to be checking cannot fire is
the vacuous test this exists to refuse. The rewrite is planted in an overlay directory that a
bootstrap prepends to Composer's PSR-4 mapping for `Tests\Support`, so the guard's tests run in a
child process with every other class resolved from this checkout and the rewrite the only
difference between the two answers — which is what the control run, every reading copied in
verbatim, is asserted green for.

The twelve readings with a detector of their own are all inverted: the two path readings, the
changelog preamble, the README-versus-config comparison, the config keys and claims, the docs
links, the guard index, the number claims, the log messages, the tool paths and the release lane.
Each inversion
carries the rewrite as a closure over the reading's own text, so a needle that stopped matching
raises rather than planting nothing — the same rule the mutations above are held to.

The third direction is for a guard whose shape is a program rather than a reading. `bin/release.php`
is a rail per condition it refuses, and what holds one is the run that asserts on it: that program is
the one here whose fixture can be built from a script a caller names — `ReleaseRepo::mutated()` and
`RC_RELEASE_FIXTURE_SCRIPT` — so one rail is taken out of the program, the rewritten releaser is
handed to `tests/Unit/Release/RailsTest.php` through that variable, and the run has to go red naming
the rail. A test cannot be handed a program as an argument, which is what the variable is for; and
the same run with the program copied verbatim is the control, for the reason the overlay's is,
because a red run has to be the rail that moved rather than the process that carried it.

Declining is a claim rather than a silence, and what is still declined is named and reasoned: the two
programs whose fixture is a tree their own test plants rather than one built from a script a caller
names — `bin/checks.php`, which is run against a repository `ChecksTest` builds, and
`bin/coverage.php`, which is the parent of the run it measures — and the harness's own test. Every
guard the table above records is either held or declined, both lists are read back against this
document, and a guard added without a decision fails there — the same rule this page imposes on
itself, one layer out.

## The readings the guards are built from

A guard is two files: the test that asserts, and the reading it asserts with. The table above names
the first — in this package a guard is at its test, or at its program — and this section names the
second, so that a file in `tests/Support/` is never a thing nobody decided about.

Which of the two lists a file is in, and the words beside its row, are the file's own: it declares
itself in its header with `@guards-index reading` or `@guards-index support`, and the first
sentence of that header is what the row says. Both tables are therefore written rather than
maintained — `bin/index.php` renders them from the directory, and `bin/index.php --check`, which
is what the suite runs, fails when the document and the directory disagree. A file that declares
nothing, declares a list this page does not have, or has no sentence to quote is refused rather
than left out, because "not in the list" and "in it and correct" are the two answers a rendering
has to be able to tell apart.

The two lists name 20 readings and 5 files that are not guards, 25 files in `tests/Support/` in
all.

| Reading | What it reads |
|---|---|
| [`../tests/Support/ChangelogPreamble.php`](../tests/Support/ChangelogPreamble.php) | the changelog read for the claim its preamble makes about the versions it does not own |
| [`../tests/Support/ConfigClaims.php`](../tests/Support/ConfigClaims.php) | the claims this package's documents make about which config keys are read |
| [`../tests/Support/ConfigDoc.php`](../tests/Support/ConfigDoc.php) | a config file — or the README's copy of one — read as data |
| [`../tests/Support/ConfigKeys.php`](../tests/Support/ConfigKeys.php) | the two halves of the package's own config contract: what the config file publishes, and what the source asks it for |
| [`../tests/Support/CoverageEnvironment.php`](../tests/Support/CoverageEnvironment.php) | the PHP a coverage run is measured under, and the driver that can count a line |
| [`../tests/Support/Docs.php`](../tests/Support/Docs.php) | the markdown this package ships, read for the links in it |
| [`../tests/Support/GuardIndex.php`](../tests/Support/GuardIndex.php) | `docs/guards.md` read as a claim about a directory |
| [`../tests/Support/Handoff.php`](../tests/Support/Handoff.php) | the environment as this checkout's own documents state it, held to the one file on a machine that answers it |
| [`../tests/Support/HandoffState.php`](../tests/Support/HandoffState.php) | the state section of the handoff, held to what `handoff/state.sh` measures: its branch, its gate wiring, its changelog endings, and the date every count and colour it states was measured on |
| [`../tests/Support/IndexNumbers.php`](../tests/Support/IndexNumbers.php) | the numbers `docs/guards.md` states, read out of it and checked against the code that produces them |
| [`../tests/Support/LogMessages.php`](../tests/Support/LogMessages.php) | the diagnostics the compression middleware writes, each declared once with a code it logs under |
| [`../tests/Support/MachinePaths.php`](../tests/Support/MachinePaths.php) | paths that only resolve on the machine they were written on, found in the files a commit would carry |
| [`../tests/Support/MachineShape.php`](../tests/Support/MachineShape.php) | the committed machine file's shape, held to the sections and keys `--init` opens a fresh clone with |
| [`../tests/Support/MutationHarness.php`](../tests/Support/MutationHarness.php) | one break at a time, planted in a copy of this checkout, read back through the guard that is supposed to catch it |
| [`../tests/Support/PhpBinary.php`](../tests/Support/PhpBinary.php) | which PHP this repository runs, read the one way it is decided |
| [`../tests/Support/ReleaseLane.php`](../tests/Support/ReleaseLane.php) | the branch releases are cut from, read out of the one file that states it, and held in every other place this repository states it again |
| [`../tests/Support/RepoEscapes.php`](../tests/Support/RepoEscapes.php) | paths that climb out of this repository, found in the files a commit would carry |
| [`../tests/Support/ScriptCoverage.php`](../tests/Support/ScriptCoverage.php) | what the spawned scripts covered, read back out of the files they wrote and mapped onto the repository's own copies |
| [`../tests/Support/TestMap.php`](../tests/Support/TestMap.php) | the tests a commit runs, chosen from the files it carries: which test covers each source file, what a document change can stale, and which test a staged test file is measured by |
| [`../tests/Support/ToolPaths.php`](../tests/Support/ToolPaths.php) | the one manifest of the tools this package runs, and every way a fact in it could be stated twice |

## Support that is not a guard

Not everything beside the readings is one. A fixture is a thing a guard is run against, and an
instrument is a thing a guard is run through; neither decides anything the suite could disagree
with. They are listed all the same, because a file in that directory that nothing records is a
decision nobody made — and the list is rendered from the files for the same reason it exists: a
name that moved is a name a person has to remember, and this page has one of those already, in
the table at the top.

| File | What it is |
|---|---|
| [`../tests/Support/Notes.php`](../tests/Support/Notes.php) | the channel a reading writes to when it declines to ask a question, so that a skip is printed where a clean answer and a question nobody could ask would otherwise look the same |
| [`../tests/Support/ReleaseRepo.php`](../tests/Support/ReleaseRepo.php) | a throwaway git repository with a real tag and a real remote, holding this package's release script |
| [`../tests/Support/ReleaseRun.php`](../tests/Support/ReleaseRun.php) | what one run of a `bin/` script did: its exit code and the two streams, kept apart on purpose |
| [`../tests/Support/ResponseWithNoReadableContent.php`](../tests/Support/ResponseWithNoReadableContent.php) | a response that reports a body it will not hand over |
| [`../tests/Support/collect-coverage.php`](../tests/Support/collect-coverage.php) | the collector a spawned script runs under — an instrument rather than a reading |

## Refused, and why

- **A surface inventory (`files.tsv`, `methods.tsv`) and the scripts that diff it.** The
  companion package has a wide public API and an inventory worth reviewing line by line. This
  one publishes four classes, one middleware, and a config file — and `bin/release.php`
  already reads the public surface between two revisions to weigh a release, which is the
  only question the inventory answers. An inventory nothing diffs is a file that goes stale
  and then gets believed.
- **A `publish-config` script with `post-install-cmd`/`post-update-cmd` hooks.** The config
  here is one file published under a tag, which `php artisan vendor:publish` already does.
  Wrapping a built-in would add a hook that runs in every consumer's `composer install`, to
  do something the framework does on request.
- **`suggest` for `ext-brotli`/`ext-zstd`.** The README tells an operator who does not want
  brotli or zstd to remove the extension, and a `suggest` entry would recommend the opposite
  of the documentation beside it. Two pieces of metadata disagreeing is what the guards above
  exist to prevent.
- **The coverage floor inside the gate.** A floor needs a PCOV or Xdebug driver, and the gate
  is run on machines that have neither. Running it in the gate would fail every check there for
  a reason about the machine rather than about the code, so the floor is CI's second step and
  `composer test:unit` — which is `bin/coverage.php`, and not part of the eleven.
- **A `version` field in `composer.json`.** `composer validate` recommends leaving it out on
  a package published from tags, and a number in the repository is a second source of truth
  that drifts from the tag Packagist actually reads. See [RELEASING.md](../RELEASING.md).

## Adding one

A guard earns its place by catching something that already happened, or by making a decision
that would otherwise be re-argued. It has to fail loudly on the day the disagreement appears
— a check that reports green when it read nothing is worse than no check, which is why the
config, README and docs guards all assert that they found something before they compare it.

A guard is declared in the file it lives in: a comment block there carries `@guards-index guard
<name>`, and the first sentence of that block is what the guard catches. A guard with a reading of
its own adds the file in `tests/Support/` that says which of the two lists it is in — an
`@guards-index` line, reading `reading` or `support` — and a guard whose own test is a test under
`tests/Unit/` declares its row from that test. Nor is a declaration taken on trust: it is held to
the kind of file it is in, so a `guard` row in `tests/Support/`, which is where readings and
fixtures live, is refused, and to what the file actually holds, so a file that says it is a test
while carrying none — or a reading that carries one — is refused rather than listed. Then
`bin/index.php` (or `composer index`) writes the
rows, and the suite refuses a document that has not caught up: the reading that compares them is what
the guard-index test asserts on, so the places cannot drift apart. A file whose block carries no
declaration is refused by name rather than left out, which is the one thing that would let a decision
go unmade while the page still read as complete.

The test side is the same question the other way. Every test under `tests/Unit/` declares
`@guards-index guard <name>`, which names its own row, or `@guards-index test`, which the table is not
about; a test that declares nothing is refused rather than read as one of them, because an ordinary
test that stayed silent and a guard that forgot are the same file from the outside. So a guard cannot
arrive with a test, a reading and a real defect behind it and read as though it had always been part
of the list.
