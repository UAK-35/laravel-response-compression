# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

**Versions count on from the newest tag**, and the last two sections of this file are not part of
that sequence: the ones for `0.2.0` and `0.1.0` were inherited from
[`chr15k/laravel-response-compression`](https://github.com/chr15k/laravel-response-compression),
the upstream this package was taken from, and no tag here corresponds to either. The tags are
what Packagist publishes, so they are what the numbering follows. RELEASING.md records the
decision and the one command that takes it.

## Unreleased

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

## [v0.2.0] - 2025-09-09

### Changed

- Adds support for Laravel 12, PHP 8.2 onward, while maintaining full backwards compatibility with Laravel 11 by [@botnetdobbs](https://github.com/botnetdobbs) in https://github.com/chr15k/laravel-response-compression/pull/1
- Updated Pint configuration to improve type safety

### Added
- Add GitHub Actions workflow for automated CI and repository checks

## [v0.1.0] - 2024-12-29

### Added

- Initial release