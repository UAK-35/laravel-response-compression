# Releasing

The version of this package is the **git tag**, and nothing else. There is no `version`
field in `composer.json` and no version file: a number stored in the repository is a
second source of truth that drifts from the tag, and Packagist derives every published
version from tags anyway — which is why `composer validate` recommends leaving the field
out on a published package.

`bin/release.php` is what cuts one. It works the next version out from the last tag and
the changes since it, writes the changelog, commits, tags, and can push — and it cuts
prereleases as well as releases, counted from the tags themselves.

## The short version

```powershell
# 1. the gate, exactly as CI runs it — the script runs it again before it tags
composer checks

# 2. see the plan: the number, the promoted changelog, the commands. Writes nothing.
composer release -- --weigh --dry-run

# 3. cut it, and take the branch and the tag to the remote
composer release -- --weigh --push
```

`composer release --` passes the rest of the line to the script; `php bin/release.php
--weigh --push` is the same thing run directly. Both work off-Windows, where Composer's
argument passthrough is the only difference.

The script runs `composer checks` before it tags, so a red gate stops the release rather
than being discovered afterwards — and it refuses to tag a commit the remote has not
seen, because a tag is where CI's answer about that commit is published.

`composer checks` is the portable gate, and the reason `bin/checks.php` exists: it calls
every tool as `PHP_BINARY <entry file>`, so it depends on neither the platform nor
`vendor/bin` having the right shims. The coverage floor lives in `composer test:unit`,
which needs a PCOV or Xdebug driver, so it is CI's second step rather than part of the
gate — a machine with neither cannot measure it.

## Cutting a prerelease

```powershell
composer release -- --prerelease=alpha     # 0.1.0-alpha1, then -alpha2, -alpha3 …
composer release -- --prerelease=beta      # 0.1.0-beta1 — same line, later lane
composer release -- --prerelease=rc        # 0.1.0-rc1
composer release -- --weigh                # 0.1.0 — the release the line was named after
```

The rules the lanes follow, all of them counted from the tags rather than remembered:

- **A prerelease is cut ahead of the release it is named after**, and keeps that release's
  line. `0.1.0-alpha2` does not move the line, whatever the notes weigh — the line was
  chosen when the first alpha of it was cut.
- **`--minor` moves the line on purpose**, so `--minor --prerelease=alpha` after
  `0.1.0-alpha2` cuts `0.2.0-alpha1`.
- **A prerelease is never cut for a line that is already released.** Once `v0.1.0` exists,
  `0.1.0-alpha1` preceding it is refused by name.
- **A release promotes its line rather than starting a new one.** With `v0.1.0-rc2` as the
  newest tag, `--weigh` releases `0.1.0`; `--minor` is what would open `0.2.0` instead.
- **The lane number is the next free one in that lane.** `v0.1.0-alpha1` already tagged
  means the next alpha is `-alpha2`, and the refusal for a reused tag says so.
- **The suffix is written the way Composer reads it.** `alpha1`, `beta2`, `rc3` and a bare
  `dev` are legal; `-alpha.1`, `-nightly` and `-ALPHA1` are usage errors rather than tags
  Composer cannot install quietly.

A prerelease is not matched by `^0.1.0` at the default stability, and the run says so when
it finishes: consumers install one with `"minimum-stability": "alpha"`, or with a
constraint that spells the lane out — `^0.1.0@alpha`.

Alpha, beta and rc tags are ordinary annotated tags, so `--follow-tags` carries them and
Packagist publishes them exactly as it publishes a release. A `dev` tag is the one to
avoid publishing: it names a commit rather than a build anyone should install.

## What the weighing reads

Three signals, each read on its own; the loudest decides the bump, and a signal that
cannot be read costs a second opinion and never a lower bump.

| Signal | Read from | Loud when |
|---|---|---|
| `CHANGELOG` | the `###` headings under `## Unreleased` | `### Removed`, or `BREAKING` anywhere, is a breaking change; `### Added` / `### Changed` / `### Deprecated` are minor; anything else is a patch |
| `commits` | the subjects since the last tag, as Conventional Commits | `type!:` or a `BREAKING CHANGE:` body is breaking; `feat` is minor; `fix` and unprefixed subjects are patch |
| `surface` | the public symbols added, removed or narrowed between the last tag and HEAD | a removed or narrowed public class or member is breaking |

The surface signal is why the weighing is worth reading rather than trusting: it is the one
that notices a rename that no commit message mentioned. It reads the source at both
revisions rather than the diff, so a member that moved between files is not mistaken for a
removal.

**A declared bump never undersells the weighing.** `--minor` on a release whose notes are
under `### Removed` is refused, with the evidence, unless `--ignore-policy` says to release
anyway. `--patch` is not an option at all: the weighing's floor is a patch, so declaring one
would say nothing.

## Version policy

This package is 0.x, so the compatibility rules are looser than 1.0.0's, but they exist:

| Change | Bump |
|---|---|
| a fix that changes no configuration and no response shape | patch |
| a new config key, a new accepted value for an existing key, or a change in what the middleware compresses | minor |
| a removed or renamed public symbol — a config key, `Encoder`, `CompressResponse` — before 1.0.0 | minor, called out in the changelog |
| the same removal after 1.0.0 | major |

A breaking change is a **minor** while this package is 0.x and a **major** past 1.0.0 —
that is the one line of the table the script implements rather than infers, and it is why
the surface signal can weigh as breaking while the version still moves by a minor.

Behaviour changes are the ones worth weighing carefully. Honouring a setting that was
previously ignored *raises* the amount of compression for anyone who had set it, which is a
minor bump rather than a patch — including the ones this package has already taken:
`min_length` and the encoder levels now honour a value set in `.env`
([docs/env-types.md](docs/env-types.md)), and a value that cannot be read is refused rather
than replaced by a default ([docs/config-reading.md](docs/config-reading.md)).

## The changelog

`CHANGELOG.md` follows Keep a Changelog, so a release promotes its `## Unreleased` section:

| Before | After |
|---|---|
| `## Unreleased` with the notes | `## [v0.1.0] - YYYY-MM-DD` with the same notes |
| | a fresh, empty `## Unreleased` above it |

The heading style is read from the file's own released sections rather than imposed, the
file's line endings are kept as they are, and a `[Unreleased]: …/compare/…` link reference
is repointed — a file that keeps no such references gets none invented for it.

## The numbering to reconcile, before the next tag

Tags stop at **`v0.0.9`**, which is what `HEAD` and the remote's `main` point at. The
changelog's newest sections are **`## [v0.2.0] - 2025-09-09`** and **`## [v0.1.0]`**, and
they are inherited: the `v0.2.0` entry credits `@botnetdobbs` and links a pull request
against `chr15k/laravel-response-compression`, the upstream this package was taken from.
No tag exists for either.

So a weighed release is refused, and the refusal names both ways out:

```
✗ CHANGELOG.md already has a section for v0.1.0, and no v0.1.0 tag exists, so that heading
  is history this repository never released — not notes an earlier run promoted.
  …
  go above it      --version=0.3.0 is the next minor above every version this repository has
                   written down (tags: newest v0.0.9 of 9; changelog: v0.2.0, v0.1.0)
  reuse the line   re-label or fold the inherited heading first, then release v0.1.0 from
                   the tag sequence
```

The weighing asks for a minor, and a minor above `v0.0.9` is `0.1.0` — which is a heading
already in the file. That is the whole collision, and one of these has to be chosen:

- **Go above it** — `composer release -- --version=0.3.0`. The tag sequence jumps over the
  inherited numbers, the changelog's sections stay as upstream wrote them, and nothing is
  republished. This is the choice that needs no edit to the changelog, and the refusal
  computes the exact number.
- **Reuse the line** — re-label or fold the inherited heading first (it describes someone
  else's release), then release `v0.1.0` from the tag sequence as usual. The changelog then
  reads as one history rather than two, at the cost of editing sections this repository did
  not write.

Neither is a repair the script could make on its own, which is why it refuses rather than
picking one. The one thing not to do is tag `v0.2.0` now: Packagist would publish a version
whose heading carries a date from 2025 and a set of changes that do not match what would
actually be in it.

## What it refuses

| Rail | Condition |
|---|---|
| no repository | the directory is not a git repository |
| wrong branch | `HEAD` is not the branch being released (default `main`) |
| dirty tree | tracked files are uncommitted, so the tag would point at a commit that lacks them |
| tag exists | the version is already tagged — a published tag is never moved or reused |
| not newer | the version is not above the newest tag |
| prerelease precedes its release | the release the prerelease is named after is already tagged |
| heading without a tag | the changelog documents the version but no tag does — see above |
| no notes | there is no `## Unreleased` section, or it is empty |
| under-declared bump | a declared bump is smaller than the weighing |
| red gate | `composer checks` did not pass |
| unseen commit | `HEAD` is not the tip of the remote branch |
| no remote | there is no remote branch to compare `HEAD` against |

`--dry-run` reports the dirty tree and the uncommitted work instead of refusing them: a
plan publishes nothing, so nothing about it is a lie. `-y`/`--yes` is what a run without a
terminal needs — the script asks before it commits, and a pipe is not a terminal.

Exit codes: **0** released or planned, **1** a precondition failed, **2** the arguments
were wrong.

## Doing it by hand

What the script writes is three things, and nothing about it is magic:

```powershell
git add CHANGELOG.md
git commit -m "Release v0.1.0"
git tag -a v0.1.0 -m v0.1.0
git push origin main --follow-tags
```

**Annotated, not lightweight**: every existing tag is annotated (`git ls-remote --tags`
shows a peeled `^{}` ref for each), and `--follow-tags` only carries annotated tags — a
lightweight tag reaches the remote but not with the push that carries its commit. Pushing
the branch and the tag together is the point: a tag whose commit is not on the branch is a
version nobody can install from it.

`git push` from this repository belongs in PowerShell 7 and over HTTPS — see
[PUSHING.md](PUSHING.md) for why, and for what to do when it fails.
