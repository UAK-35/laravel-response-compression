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
| `bin/release.php` | a version cut from the wrong branch, over uncommitted work, over a tag that exists, over a red gate, over a commit the remote has not seen — and the numbering of prerelease lanes | [RELEASING.md](../RELEASING.md) |
| the config readers | a value that cannot be read: refused with the key and the value rather than replaced by a default | [config-reading.md](config-reading.md) |
| `Config::validate()` | the same, at boot rather than on the request that first needs the value | [`../src/Support/Config.php`](../src/Support/Config.php) |
| the README↔config test | a published default that disagrees with the config file it documents | [`../tests/Unit/Config/ConfigDocTest.php`](../tests/Unit/Config/ConfigDocTest.php) |
| the config-key test | a key read that the config does not publish, and a key published that nothing reads | [`../tests/Unit/Config/ConfigKeysTest.php`](../tests/Unit/Config/ConfigKeysTest.php) |
| the docs-link test | a broken link between the records, and a record nothing links to | [`../tests/Unit/Docs/DocsLinksTest.php`](../tests/Unit/Docs/DocsLinksTest.php) |
| the machine-path test | a path that only resolves on the machine it was written on — a drive letter, a home directory, a network share — in any file a commit would carry | [`../tests/Unit/Support/MachinePathsTest.php`](../tests/Unit/Support/MachinePathsTest.php) |
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

The two README/config guards and the config-key guard are the three worth the most here,
because all three of this package's silent defects were a disagreement between two files:
the README advertised `algorithm` as `gzip` while the config shipped `br`, `min_length` as
`1024` while the config shipped `2048`, and the encoder read `brotli.level` while the config
published `br.level`.

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
- **The coverage floor inside the gate.** `pest --coverage --min=100` needs a PCOV or Xdebug
  driver, and the gate is run on machines that have neither. Running it in the gate would
  fail every check there for a reason about the machine rather than about the code, so the
  floor is CI's second step and `composer test:unit`.
- **A `version` field in `composer.json`.** `composer validate` recommends leaving it out on
  a package published from tags, and a number in the repository is a second source of truth
  that drifts from the tag Packagist actually reads. See [RELEASING.md](../RELEASING.md).

## Adding one

A guard earns its place by catching something that already happened, or by making a decision
that would otherwise be re-argued. It has to fail loudly on the day the disagreement appears
— a check that reports green when it read nothing is worse than no check, which is why the
config, README and docs guards all assert that they found something before they compare it.
