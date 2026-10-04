# Pushing this package — where it goes, what authenticates, and what was verified

`origin` is **`https://github.com/UAK-35/laravel-response-compression.git`**, for fetch
and for push. [RELEASING.md](RELEASING.md) covers what a version *is*; this file covers
getting it to the remote.

## What was checked, and what was not

Everything marked ✓ was read off this machine on **2026-10-04**, and **the push has been
exercised** since the reading before this one: the tip the reflog records moved nine commits,
which is a push this machine made rather than a fetch. This is not a recipe assembled from
verified parts and standing behind no run. The branch named below is the one `v0.0.10` was
cut on and pushed from, and the reflog of the remote-tracking ref is where that is recorded
rather than remembered.

| Claim | Evidence |
|---|---|
| `origin` is HTTPS, for fetch and push | `git remote -v` → `https://github.com/UAK-35/laravel-response-compression.git`, on its fetch line and on its push line |
| the branch is `development`, tracking `origin/development` | `git status -sb` → `## development...origin/development [ahead 30]`; `@{u}` is `origin/development`, the tracking ref that status line compares against. It names `4e1f561`, the tip the last push left there; `HEAD` is `84eefbd`, thirty commits past it, and what is ahead of its own remote is the work since ✓ |
| `development` is the lane a release is cut from | `.release-lane` holds it — one line, read by `bin/release.php` as its default branch — and `.github/workflows/main.yml` gates `main` and `development`, so the lane has a CI record. `git branch --contains v0.0.10` names `development` alone: `v0.0.9` is the last tag that is on both ✓ |
| a push from this machine is what put `development` there | `git reflog show origin/development` is six `update by push` entries, the newest moving `9e00b11` → `4e1f561` — nine commits, and the tip the remote's `development` still names. A remote-tracking ref that had only ever been fetched names a different action |
| the repository is public | `GIT_TERMINAL_PROMPT=0 git -c credential.helper= ls-remote --heads origin` answered `4e1f561 refs/heads/development` and `1ef9860 refs/heads/main`, exit 0 — with **every** credential helper disabled, so there was no credential available to have been used ✓ |
| the remote's `main` is not the `HEAD` | it reads `1ef9860b0cfc2ef611fb690c69c788cb35e5ef89`, the commit `v0.0.9` points at. `main` trails the lane here rather than leading it |
| `main` trails without anything being lost on it | local `main` is `f4e207a`, three commits ahead of `origin/main`, and it is an ancestor of `development`: `git rev-list --left-right --count main...development` → `0 51`, so no commit on `main` is missing from `development` ✓ |
| every release tag is on the remote | `git ls-remote --tags origin` lists `refs/tags/v0.0.1` … `refs/tags/v0.0.10`, and `refs/tags/v0.0.10-alpha1` with them — eleven tags, and the listing is identical to this clone's own: a `diff` of the remote's answers against `git ls-remote --tags .` is empty |
| no published tag has been rewritten | the tag object and the peeled commit each name on the remote are the hashes this clone holds for the same name: `v0.0.10` → `e409167…` → `f25d5c4…`, `v0.0.10-alpha1` → `f855b9a…` → `0e89ae7…` ✓ |
| the tags are annotated, except the first | each tag ref is accompanied by a peeled `refs/tags/v0.0.N^{}` entry, which only annotated tags produce — every tag but `v0.0.1`, which `git for-each-ref refs/tags/v0.0.1 --format='%(objecttype)'` reports as `commit` rather than `tag` |
| HTTPS credentials come from Git Credential Manager | `git config --show-origin --get-all credential.helper` → `manager`, set in both the system gitconfig and the user's own |
| ssh is globally redirected to Windows OpenSSH | `core.sshCommand = C:/Windows/System32/OpenSSH/ssh.exe`, from `~/.gitconfig` |

The last row is inert here: `origin` is HTTPS, so no ssh process is started. It would
matter the moment the remote were switched to `git@github.com:…`.

Two rows are the ones a reader would otherwise assume backwards, so they are worth saying
twice. **The lane is `development`**, not `main`: `0.0.10` was cut there, and `main` names
`v0.0.9` and sits fifty-one commits back. And **the tags are not uniform**: `v0.0.1` is the one
lightweight tag in the set, which makes it the one tag `--follow-tags` would not carry.

## The push

Run it from **PowerShell 7**, not from MSYS / Git Bash. A credential prompt is answered by
the console, and a non-interactive shell has no console to answer it on.

```powershell
cd <the package root>          # the directory holding this file

git push origin development --follow-tags
```

`--follow-tags` carries the **annotated** tags pointing at the commits being pushed — the
kind every tag here but `v0.0.1` is — so a release tag travels with its commit and no second
command is needed. It is the same command the releaser runs for the branch it released, so a
cut and a push by hand cannot disagree about what goes out.

### Then read the run

`git push` answers one question — did the bytes leave this machine — and it answers it in a
second. The question a reader has is whether the run they started passed, and that answer arrives
somewhere else, minutes later. `composer push` is that push with the reading made part of it: it
pushes the branch, finds the Actions run GitHub started for the commit it pushed, waits for that
run to finish, and exits 0 only when it concluded `success`.

```powershell
cd <the package root>          # the directory holding this file

composer push                  # push the current branch and read its run
composer push -- --dry-run     # what would be pushed and read; changes nothing
composer push -- --no-watch    # push and stop, saying the run was not read
```

It reads the run through `gh`, which is the tool here that already knows who you are
(`gh auth login`), so nothing holds a token and none is written down. A push that started no run
is a refusal rather than a pass: the workflow gates `main`, `development` and `v*` tags, so a push
to any other branch builds nothing, and "nothing ran" cannot be read as a verdict. `--gh=COMMAND`
names the reader instead, if your GitHub is not the one the CLI knows.

The release lane already assumes this run exists — `bin/release.php` refuses a release whose
commit is not the tip of the remote branch, precisely because that commit is one *nothing has
built*. This is the other half of that sentence: the commit is built, and this is whether it
passed.

Push before tagging and the tag stays local: Packagist derives every published version
from tags on the remote, so a release is not published until its tag is there. See
[RELEASING.md](RELEASING.md).

## Prerelease tags

An alpha, beta or rc tag is pushed exactly as a release tag is — `--follow-tags` does not
care what the number says — and Packagist publishes it as a version with a prerelease
suffix rather than as the package's latest:

```powershell
# 0.1.0-alpha1, pushed with the commit it names
composer release -- --prerelease=alpha --push
```

The branch is not named here because there is one place to name it: `.release-lane`, which
`bin/release.php` reads as its default. The rail still refuses a cut anywhere but the branch it
expects, so a run on another branch stops rather than releasing from it.

Two things worth knowing before one goes out:

- **A prerelease is not what `composer require` gets by default.** `^0.1.0` at the default
  stability skips `0.1.0-alpha1`; a consumer installs it with `"minimum-stability": "alpha"`,
  or with a constraint that names the lane — `^0.1.0@alpha`.
- **The tag is the published version, so it cannot be un-published quietly.** Packagist
  keeps what it saw; deleting the tag afterwards leaves a page for a version nobody can
  install. Cut the next lane number (`-alpha2`) rather than moving a tag.

## What a push would send right now

**Whatever the three commands at the foot of this section answer — that is what the section is
for.** It is a snapshot, and this file is part of a tree that moves. When the evidence above
was read, `origin/development` named `4e1f561` — the `update by push` at the head of that
reflog — and `development` was **thirty commits past it**, so a bare `git push` has thirty
commits to send and the lane is not in sync. Every tag was on the remote, so nothing published
had been rewritten, and no annotated tag was left for `--follow-tags` to carry: the branch is
what a push would send. The commit that publishes this sentence is already past that reading,
and it will not be the last one: what holds here is the shape of the answer rather than its
size at any one moment.

The commits past `v0.0.10` divide in two, and the difference is why this section is worth
reading rather than skimming. The **fifteen** up to `origin/development` are already
*published*: they went out as plain history with the push that carried them. The **thirty**
from there to `HEAD` are local only, and no push has carried them. None of any of them is a
version, because no tag names one, and Packagist reads tags rather than branches — for a
consumer, a commit on a branch does not exist. They are the guard work, the records around it
and this runbook, which have landed since the release, and `composer release -- --weigh --push`
is what turns them into a version, with `--weigh --dry-run` first whenever the number is in
question.

Read the numbers off this repository rather than trusting this paragraph, which is a
snapshot: `git status -sb`, then `git rev-parse HEAD origin/development`, then
`git log --oneline v0.0.10..HEAD`.
