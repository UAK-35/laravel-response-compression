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

- **Two branches that report a skip with the same sentence are refused.** Two of the middleware's
  checks sat next to each other and both wrote the same line, so an unsuccessful response was
  recorded as a binary file — in the one output `enable_logging` exists to produce, which is where
  anyone looks to find out why a response was left alone. It was found by reading the file and
  fixed by hand, which is the kind of fix that comes back, because a test that pins a message pins
  it one branch at a time: a third branch copied from one of them passes every one of them.
  `tests/Unit/Middleware/LogMessagesTest.php` reads each `logDebugStatus(…)` call as the sentence
  it writes with the values taken out, and fails when two of those sentences are the same, naming
  both lines and the sentence they share. The reading is held to two things of its own as well: it
  has to find every call in the file rather than the ones it understood, and the shapes it reasons
  about are checked against the messages the middleware really composes when six of its branches
  are driven through it in one test.

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

### Fixed

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