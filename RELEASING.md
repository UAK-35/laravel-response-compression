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

`composer release --` passes the rest of the line to the script, and running the script itself is
the same thing under the same interpreter:

```bash
composer release -- --weigh --push
"$(python .agents/render_local.py --value __PHP_EXE__)" bin/release.php --weigh --push
```

The second form is the one to reach for where `composer` is not on `PATH`. Neither writes an
interpreter's path down: `.agents/machine.local.json` names this machine's PHP once, and the hook
and the suite resolve the same value from it. Both work off-Windows, where Composer's argument
passthrough is the only difference.

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

Four signals, each read on its own; the loudest decides the bump, and a signal that
cannot be read costs a second opinion and never a lower bump. One of them is the release's
own record, written by the release before it: the inventory below.

| Signal | Read from | Loud when |
|---|---|---|
| `CHANGELOG` | the `###` headings under `## Unreleased` | `### Removed`, or `BREAKING` anywhere, is a breaking change; `### Added` / `### Changed` / `### Deprecated` are minor; anything else is a patch |
| `commits` | the subjects since the last tag, as Conventional Commits | `type!:` or a `BREAKING CHANGE:` body is breaking; `feat` is minor; `fix` and unprefixed subjects are patch |
| `surface` | the public symbols added, removed or narrowed between the last tag and HEAD | a removed or narrowed public class or member is breaking; a file that moved with its symbol intact costs nothing |
| `inventory` | `files.tsv` and `methods.tsv` as the last release wrote them — read only when their stamp names the tag being released from | a removed file or public method, or a method that gained a required argument, is breaking; an added file or method is a minor; a file that moved with its class name intact costs nothing |

The surface signal is why the weighing is worth reading rather than trusting: it is the one
that notices a rename that no commit message mentioned. It reads the source at both
revisions rather than the diff, so a member that moved between files is not mistaken for a
removal — symbols are compared by name rather than by path, which is what a consumer imports,
and a move is therefore free unless the symbol it carries changed shape on the way. The
inventory reads the same move from the other direction, off its path column.

## The inventory

`files.tsv` and `methods.tsv` sit at the package root. `bin/release.php` writes both in the
release commit, next to the CHANGELOG, stamped with the tag it is creating; the next release reads
them as its fourth signal. They are the record a tag cannot always be: what was shipped, written
down, so a renamed file or a method that changed shape can be seen rather than remembered.

| File | One row per | Columns |
|---|---|---|
| `files.tsv` | file under `src/` or `config/` | `name`, `path`, `symbol` — the class the file declares, or `(none)` for a config file |
| `methods.tsv` | public method | `method`, `file`, `class`, `required` — how many arguments the method requires |

TSV rather than JSON, and not for speed: a row is an `explode("\t", $line)` with no quoting rule to
get wrong, and one symbol per line means `git diff` shows a rename as two lines a person can read.

Both files open with two `#` lines — what the file is, and which tag it *describes*. The stamp is
the whole safety property:

- **both** stamps name the tag being released from → the rows are evidence, and the tree is weighed
  against them;
- anything else → the file was written at some other moment, which makes "nothing changed" and "not
  refreshed" indistinguishable on disk. It is reported as **stale** and skipped, and the tag diff
  stays the authority. Both stamps have to match rather than just one, because the pair is written
  together: a disagreement between them is one file edited on its own, and a half-refreshed
  inventory is the state this signal must never guess at.

Believing a lazily refreshed inventory is what would let a breaking change ship as a patch, and
that is the one direction a versioning signal must never be wrong in. Losing one costs a second
opinion and nothing else, because no signal ever lowers the bump.

**`composer release -- --inventory` writes both files and stops.** The release is what keeps them
current, and it is the writer that matters — but a package adopting this tooling mid-life has
nothing for the signal to read, and the release that would write it is the one that cannot weigh
it: the rows describe the tree at the tag *before* the one being cut. So `--inventory` writes them
once, for the tag the tree is built on, and the next release weighs a real record instead of
starting blind. That is what this package did for `v0.0.9`, and it is why the plan for the release
after it reads `fresh, weighed against v0.0.9` rather than `nothing written down yet`.

It is asked before every other rail, because it writes two tracked files and stops: the branch
the tree is on, whether it is dirty, and whether a tag already exists are all things a release
refuses over and none of them is a reason not to write a record. Before the first tag it stamps
`(no tag)` and describes the working tree, which is the only tree there is. Asked again when the
files are already current, it says so and writes nothing.

There is deliberately **no `--check`**, and nothing in CI reads these files. A check in the gate
would keep the inventory in step with every commit, which is the one state in which it cannot
witness anything — and a tree whose inventory is stale already says so in the plan, which is where
it matters.

**A declared bump never undersells the weighing.** `--minor` on a release whose notes are
under `### Removed` is refused, with the evidence, unless `--ignore-policy` says to release
anyway. `--patch` is not an option at all: the weighing's floor is a patch, so declaring one
would say nothing. A version named with `--version=X.Y.Z` is measured the same way — the step
its digits imply is what is compared, so `0.0.9` to `0.0.10` is held to a patch's floor
whatever the notes say, and a version below the weighing is overridden out loud like any
other declaration.

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

The notes are the signal you control, so an entry filed under the wrong category is the answer
to a weighing you disagree with: a change that is really a fix belongs under `### Fixed`.
Moving the entry is the fix. There is no flag that lowers the policy: `--ignore-policy` releases
anyway and says so in the plan, and it is for a weighing that misread the tree or for notes you
are choosing to publish thin — not for a bump you would rather not hear.

**A removal the notes leave out is refused on its own.** The surface signal and the inventory
each read a symbol that is gone, and each wraps it in a breaking change, so the release takes the
right number whatever the notes claim — while the notes, which are what a consumer upgrades on,
can still list the change as a fix. When the two readings agree that a public symbol was removed
and `## Unreleased` declares no removal, the release stops, naming the symbol and the reading that
saw it. Filing the entry under `### Removed` is the fix, and `--ignore-policy` is what publishes
notes that thin. Both readings have to agree before anything is refused: a public constant or
property is a symbol the inventory has no column for, so a removal only the surface saw is left to
the notes rather than turned into a refusal the next person works around.

## The changelog

`CHANGELOG.md` follows Keep a Changelog, so a release promotes its `## Unreleased` section:

| Before | After |
|---|---|
| `## Unreleased` with the notes | `## [v0.1.0] - YYYY-MM-DD` with the same notes |
| | a fresh, empty `## Unreleased` above it |

The heading style is read from the file's own released sections rather than imposed, the
file's line endings are kept as they are, and a `[Unreleased]: …/compare/…` link reference
is repointed — a file that keeps no such references gets none invented for it.

## The branch alias

`extra.branch-alias` in `composer.json` gives the dev lanes a version to resolve as, so a consumer
requiring `^0.0` can install one as readily as a tag. Two keys name this repository's own branches,
and the release keeps them honest:

- `dev-main` and `dev-development` both point at `X.Y.x-dev` for the line being developed;
- a patch on that line leaves them alone — `X.Y.Z` with `Z > 0` is not a new line;
- a release that opens one moves them, because the trunk is that line from then on.

Only those two keys are touched, and only their values, as bytes in the file rather than a decoded
and re-encoded structure: the rest of `composer.json` — its key order, its indentation — is not this
script's to normalise, and a release commit that reformats it is a diff nobody can review. An alias
the dev lanes do not own names a different line, so rewriting it would be wrong rather than
thorough; the plan says which keys it owns and the file is otherwise left alone.

`composer.json` goes into the release commit beside the CHANGELOG and the inventory, and only when
a value actually moved.

## The numbering, and the decision taken

This package's tags begin at `v0.0.1`. Its changelog began earlier, with
**`## [v0.2.0] - 2025-09-09`** and **`## [v0.1.0] - 2024-12-29`**, which are inherited: the
`v0.2.0` entry credits `@botnetdobbs` and links a pull request against
`chr15k/laravel-response-compression`, the upstream this package was taken from. No tag here
corresponded to either, so the changelog documented two versions this repository had never
released — and the first weighed release after `v0.0.9` landed on one of them, because a minor
above `0.0.9` is `0.1.0`.

So a weighed release was refused, and the refusal named every way out of it. This is that
output, as it read at `v0.0.9` — the numbers in it are the ones the script computed from that
tree, and the rail itself is unchanged:

```
✗ CHANGELOG.md already has a section for v0.1.0, and no v0.1.0 tag exists, so that heading
  is history this repository never released — not notes an earlier run promoted.

Promoting these notes under it would leave one version with two sets of notes: the ones
written there and the ones in `## Unreleased`.

The ways out, and each is a decision:

  go above it        --version=0.3.0 is the next minor above every version this repository …
                     (tags: newest v0.0.9 of 9; changelog: v0.2.0, v0.1.0)
  count the tags on  --version=0.0.10 steps past v0.0.9, the newest tag, and no heading
                     claims it: the tags go on counting from where they stopped while the
                     inherited sections stay as they are. That step is smaller than these
                     notes weigh, so --ignore-policy is what says so out loud.
  reuse the line     re-label or fold the inherited heading first, then release v0.1.0 …
```

None of the three is a repair the script could make on its own, which is why it refuses rather
than picking one. The decision taken then was the second way, recorded here rather than left to
the next person to rediscover: the tags went on counting from where they stopped while the
inherited sections stayed as upstream wrote them. That is why `v0.0.10` was **declared**
rather than weighed — the next section has the record of what that cost — and it is the number
that put this package's own line one tag past the collision.

The third way is the one that then had to be taken, because nothing about it was a one-off.
From `v0.0.10` a weighed release asks for a minor, and a minor is `0.1.0`: the same heading,
the same refusal, one tag later — so every release from that sequence would have had to carry
the same declaration. The inherited sections have therefore been **folded into a single
`## Inherited from upstream` section** at the end of the changelog, where the two releases are
third-level headings and keep their notes, their credits and their dates.

`## Inherited from upstream` is not a version, so the script no longer reads `0.2.0` or `0.1.0`
as versions this repository documented, and `--weigh` takes the minor it was refusing over:

```powershell
composer release -- --weigh --dry-run     # plans 0.1.0, no declaration, no override
```

Editing sections this repository did not write is the cost of that way, and the fold is
deliberately the smallest form of it: a heading, a level, and a paragraph saying whose history
this is. Nothing was renumbered and nothing removed, so the two releases are as findable as
they were — and when `0.1.0` is released from here, the promoted `## [v0.1.0]` heading carries a
tag behind it, which is the one shape no rail objects to.

The one thing not to do, then or now, is tag `v0.2.0`: Packagist would publish a version whose
heading carries a date from 2025 and a set of changes that do not match what would actually be
in it.

### What declaring `v0.0.10` cost

`0.0.9` to `0.0.10` is a **patch**, and those notes weighed a **minor** — an `### Added`
heading is the package gaining something. So the number was below the floor the policy sets,
and a version named outright is held to that floor exactly as `--minor` is: `--version`
chooses inside the policy rather than around it, which is the one hole a floor on `--minor`
alone would have left. The command said so out loud:

```powershell
composer release -- --version=0.0.10 --ignore-policy
```

and the plan printed what was overridden rather than quietly shipping a minor's worth of
changes under a patch number:

```
  bump          patch  (declared: --version=0.0.10)
  …
  note: --ignore-policy: releasing 0.0.10, below the minor the changes call for
```

This was the shape of a first release under this policy: the weighing describes the size of the
change, and a number that deliberately disagrees with it is a decision that gets **declared**.
`--dry-run` prints the same plan and reports the refusal it is avoiding — the rail itself is
asked when the run is real.

It was declared once, for `v0.0.10`, and it does not have to be declared again. The one
consequence the declaration came with was that the inherited headings made **every** release
from that tag sequence a declaration too, `--weigh` from `v0.0.10` asking for a minor and a
minor being `0.1.0` — a heading the changelog already carried. That is what the fold in the
section above removed, and it was removed the way that section describes rather than with a
flag: `--ignore-policy` is for a weighing that misread the tree, not for a changelog that
cannot hold the number the tree asks for.

## What it refuses

| Rail | Condition |
|---|---|
| no repository | the directory is not a git repository |
| wrong branch | `HEAD` is not the branch being released (default `main`) |
| dirty tree | tracked files are uncommitted, so the tag would point at a commit that lacks them |
| tag exists | the version is already tagged — a published tag is never moved or reused |
| not newer | the version is not above the newest tag |
| prerelease precedes its release | the release the prerelease is named after is already tagged |
| heading without a tag | the changelog documents the version but no tag does — see [the numbering](#the-numbering-and-the-decision-taken) |
| no notes | there is no `## Unreleased` section, or it is empty |
| undersold declaration | a declared `--minor`, `--major` or `--version` is a smaller step than the weighing |
| undeclared removal | the surface and the inventory both saw a public symbol removed and `## Unreleased` does not declare one — see [the policy](#version-policy) |
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
