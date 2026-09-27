# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## Unreleased

### Added

- `bin/checks.php` through `composer checks`: PHP syntax, an AST parse of the same files,
  `composer validate --strict`, the platform requirements, the workflow YAML, PHPStan, Pint,
  Rector and Pest in one run, with one summary and one exit code. CI runs it.
- `bin/release.php` through `composer release`: works the next version out from the last tag and
  the changes since it, promotes the changelog, commits and tags — and cuts prereleases with
  `--prerelease=alpha|beta|rc`, counted from the tags themselves rather than remembered. It
  refuses a release over a red gate, over a commit the remote has not seen, over a tag that
  already exists, and over a changelog heading with no tag behind it. See RELEASING.md.
- `Uak35\ResponseCompression\Support\Config` and `InvalidConfigurationException`: the readers
  every configured value now goes through.

### Changed

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

### Fixed

- A `min_length` set in `.env` no longer becomes a floor of zero: `RESPONSE_COMPRESSION_MIN_LENGTH=4096`
  arrives as the string `'4096'`, which is now read as 4096 rather than rejected by `is_int()`.
- A `gzip` or `zstd` level set in `.env` is honoured for the same reason, rather than being
  silently replaced by the documented default.
- Broken links between the docs are caught by a test, and so is a design record nothing links to.
- A key read that the config file does not publish, and a key published that nothing reads, are
  caught by a test that reads both sides — the pair of defects that made the brotli level and
  `enable_logging` invisible. See docs/guards.md for what is checked and what was deliberately not.

## [v0.2.0] - 2025-09-09

### Changed

- Adds support for Laravel 12, PHP 8.2 onward, while maintaining full backwards compatibility with Laravel 11 by [@botnetdobbs](https://github.com/botnetdobbs) in https://github.com/chr15k/laravel-response-compression/pull/1
- Updated Pint configuration to improve type safety

### Added
- Add GitHub Actions workflow for automated CI and repository checks

## [v0.1.0] - 2024-12-29

### Added

- Initial release