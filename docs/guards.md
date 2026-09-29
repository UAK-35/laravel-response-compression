# Which guards does this package keep, and which did it refuse?

**Because a guard is a promise about the future, and the only honest way to make one is to
say what it catches.** This package's sibling, `laravel-weighted-dbmanager`, carries a
larger set of checks than this one does. Comparing the two is what produced the list below:
each guard was judged on what it would have caught *here*, and for a package this size the
answer is not always "port it".

## Kept

| Guard | What it catches | Where |
|---|---|---|
| `bin/checks.php` | nine checks in one process: syntax, an AST parse, the composer schema, the platform requirements, the workflow YAML, PHPStan, Pint, Rector, Pest — one summary, one exit code | [`../bin/checks.php`](../bin/checks.php) |
| `.githooks/pre-commit` | a commit carrying PHP that does not parse, style the project does not use, or a link between the records that no longer resolves — by running `bin/checks.php --staged` | [`../.githooks/pre-commit`](../.githooks/pre-commit) |
| `bin/release.php` | a version cut from the wrong branch, over uncommitted work, over a tag that exists, over a red gate, over a commit the remote has not seen — and the numbering of prerelease lanes | [RELEASING.md](../RELEASING.md) |
| the config readers | a value that cannot be read: refused with the key and the value rather than replaced by a default | [config-reading.md](config-reading.md) |
| `Config::validate()` | the same, at boot rather than on the request that first needs the value | [`../src/Support/Config.php`](../src/Support/Config.php) |
| the README↔config test | a published default that disagrees with the config file it documents | [`../tests/Unit/Config/ConfigDocTest.php`](../tests/Unit/Config/ConfigDocTest.php) |
| the config-key test | a key read that the config does not publish, and a key published that nothing reads | [`../tests/Unit/Config/ConfigKeysTest.php`](../tests/Unit/Config/ConfigKeysTest.php) |
| the config-claim test | a docblock or a record that says a key is unread while the source reads it | [`../tests/Unit/Config/ConfigClaimsTest.php`](../tests/Unit/Config/ConfigClaimsTest.php) |
| the docs-link test | a broken link between the records, and a record nothing links to | [`../tests/Unit/Docs/DocsLinksTest.php`](../tests/Unit/Docs/DocsLinksTest.php) |
| the changelog-preamble test | a preamble that goes on naming a version as inherited once this package has released it — the stale claim the next release would otherwise write into the file | [`../tests/Unit/Docs/ChangelogPreambleTest.php`](../tests/Unit/Docs/ChangelogPreambleTest.php) |
| the machine-path test | a path that only resolves on the machine it was written on — a drive letter, a home directory, a network share — in any file a commit would carry | [`../tests/Unit/Support/MachinePathsTest.php`](../tests/Unit/Support/MachinePathsTest.php) |
| the log-message test | two branches that report a skip with the same sentence, which is a line the log cannot tell apart — read from what the branches write, not from what they do | [`../tests/Unit/Middleware/LogMessagesTest.php`](../tests/Unit/Middleware/LogMessagesTest.php) |
| the tool-path test | a tool's path written down in a second place — in a composer script, or in one of the three programs that runs a tool — a `vendor/bin` shim, which is a second interpreter, a path that is not on disk, a name nothing asks for, an entry nothing runs | [`../tests/Unit/Support/ToolPathsTest.php`](../tests/Unit/Support/ToolPathsTest.php) |
| `bin/tool.php` | a composer script that writes a tool's path down instead of naming a tool: the script names this program and the tool, the manifest answers with the path, and the exit code is the tool's own — a wrapper that swallowed it would report every script as passing | [`../bin/tool.php`](../bin/tool.php) |
| the gate's own tests | the script everything else is measured by, which no test had ever run — its exit codes, its skips and its refusals, in a planted tree | [`../tests/Unit/Gate/ChecksTest.php`](../tests/Unit/Gate/ChecksTest.php) |
| `bin/coverage.php` | the two scripts in `bin/` that no test can run directly: the coverage each child process writes is collected, mapped back onto the file it was copied from, and merged before the floor is read — 100.0% for every `src/` file on its own, a ratchet per script that may only rise, and a header naming the PHP and the coverage driver the run is under | [`../bin/coverage.php`](../bin/coverage.php) |
| `tests/Unit/Gate/CoverageTest.php` | a floor run that keeps quiet about the machine it is on: the interpreter is named before the suite starts and again at the foot of a failure, so a floor that moved is attributable to the code or to the PHP — and a run that collected nothing says so rather than reporting every file at 0.0% | [`../tests/Unit/Gate/CoverageTest.php`](../tests/Unit/Gate/CoverageTest.php) |
| `phpVersion: 80400` | analysis against the PHP the analyst happens to run rather than the floor the package promises | [`../phpstan.neon.dist`](../phpstan.neon.dist) |
| `failOnWarning`/`failOnRisky`/`failOnDeprecation` | a test that warns, is risky, or leans on a deprecation and still reports green | [`../phpunit.xml.dist`](../phpunit.xml.dist) |
| `* text=auto` | whether a CRLF file is committed as CRLF depending on each contributor's `core.autocrlf` | [`../.gitattributes`](../.gitattributes) |
| a lock-aware `composer validate` | a stale lock: the check runs only when the repository actually ships one | [`../bin/checks.php`](../bin/checks.php) |
| `composer check-platform-reqs` | a missing `ext-brotli` or `ext-zstd`, reported as a build problem rather than as a failing test | [`../bin/checks.php`](../bin/checks.php) |
| CI on `tags: ["v*"]` | a published version — release or prerelease — with no CI record against it | [`../.github/workflows/main.yml`](../.github/workflows/main.yml) |
| CI running the gate | CI that is weaker than the gate a tag is cut behind | the same workflow |
| `composer checks -- --require-all` in CI | a check that *skipped* on the runner — a tool that did not install is a hole in the build, not a fact about the machine | the same workflow |

The gate drops a check to a skip when a tool is missing, which is right on a developer's
machine — half of these tools are `require-dev` — and wrong on a runner, where a tool that
failed to install reads as a green build. That is why CI runs the gate with `--require-all`:
it is the same gate, with the skips turned into failures where a skip can only be a mistake.

The hook is that same gate, earlier and narrower. `php bin/checks.php --staged` — which is
what `.githooks/pre-commit` runs — points three checks at the files a commit carries: syntax
over the staged PHP, Pint over the staged PHP, and the docs-link test when markdown is staged.
Those three are what a commit can break on its own; the rest of the gate is a tree-shaped
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
which is the same on every CI runner.

The log-message guard came from a defect that had already shipped: two of the middleware's skip
checks sat next to each other and wrote the same sentence, so an unsuccessful response was
recorded as a binary file — in the one output `enable_logging` exists to produce, which is where
anyone looks to find out why a response was left alone. It was found by reading the file and
fixed by hand, and a hand fix is what comes back. A test that pins a message pins it one branch
at a time, so a third branch copied from one of them passes every one of them; what can see the
duplicate is what the branches have in common, the text they write. So each `logDebugStatus(…)`
call is read out of the file as a *shape* — the sentence it writes with the values taken out —
and two shapes that match are reported with both line numbers. The reading is held to two things
of its own, because a guard that read nothing would pass: it has to find every call in the file
rather than the ones it happened to understand, and the shapes it reasons about are compared
against the messages the middleware really composes when six of its branches are driven.

The coverage floor became a program of its own for the same reason the log-message guard reads
what the branches write instead of pinning each message: the number it had was not the number it
looked like. `src/` was measured from inside the suite, and the two scripts in `bin/` were not
measured at all — they run on include, they call `exit()`, and the suite reaches them as child
processes, so the floor read 100.0% while sixty tests drove `bin/release.php` end to end. What
changed is where the evidence comes from, not what is asserted: a child writes down what it
covered, and the merge maps a fixture's copy back onto the repository's file by comparing
contents, because a fixture's `bin/checks.php` is a three-line stub and reading it by name would
have handed the gate 100.0% coverage for a script that exits on line three. The floors are 100.0%
per file for `src/` and a ratchet for each script — written down, because closing the rest of
those lines is writing more scenarios rather than deleting dead code. Two files in `bin/` are left
out of the report, each for a reason of its own. `bin/coverage.php` is the parent of the run it
reads, so its own lines are in no file any child writes; it opens by naming the machine instead:
the PHP it is under — version, path, and whether Composer started it, since `@php` is Composer's
own binary — and the coverage driver, or the sentence saying there is none and what would give
this machine one. `bin/tool-paths.php` is a list rather than a script — a `return [name => path]`
whose seven lines PHPUnit counts as seven statements while PCOV reports the one statement once —
so it reads 14.3% however many processes read it, and a floor that cannot rise is noise; what can
be wrong with it is which entries it holds, which the tool-path test asserts. Neither file has a
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

The two lists are gone rather than reconciled: `bin/tool-paths.php` is the one place a tool's
path is written, the scripts reach a tool by naming it to `bin/tool.php`, and the gate asks the
same manifest. What one file cannot say about itself is what the guard reads — that nobody has
written a path down again, that every path is the entry file inside `vendor/` and is on disk,
and that every name is one something asks for and every asker's name exists. A name that is not
there is the quiet failure of the three: it reads as "not installed", which is a skip in the
summary that looks like a machine without the tool rather than a typo in a script.

## Refused, and why

- **A surface inventory (`files.tsv`, `methods.tsv`) and the scripts that diff it.** The
  sibling package has a wide public API and an inventory worth reviewing line by line. This
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
  `composer test:unit` — which is `bin/coverage.php`, and not part of the nine.
- **A `version` field in `composer.json`.** `composer validate` recommends leaving it out on
  a package published from tags, and a number in the repository is a second source of truth
  that drifts from the tag Packagist actually reads. See [RELEASING.md](../RELEASING.md).

## Adding one

A guard earns its place by catching something that already happened, or by making a decision
that would otherwise be re-argued. It has to fail loudly on the day the disagreement appears
— a check that reports green when it read nothing is worse than no check, which is why the
config, README and docs guards all assert that they found something before they compare it.
