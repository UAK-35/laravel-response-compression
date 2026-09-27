# Pushing this package — where it goes, what authenticates, and what was verified

`origin` is **`https://github.com/UAK-35/laravel-response-compression.git`**, for fetch
and for push. [RELEASING.md](RELEASING.md) covers what a version *is*; this file covers
getting it to the remote.

## What was checked, and what was not

Everything marked ✓ was read off this machine on 2026-09-27. **No push was exercised** —
this file was written from reads only — so the recipe below is assembled from verified
parts and has no verified end-to-end push behind it yet.

| Claim | Evidence |
|---|---|
| `origin` is HTTPS, for fetch and push | `git remote -v` → `https://github.com/UAK-35/laravel-response-compression.git` |
| the branch is `main`, tracking `origin/main` | `git rev-parse --abbrev-ref HEAD` → `main`; `@{u}` → `origin/main` |
| the repository is public | `GIT_TERMINAL_PROMPT=0 git -c credential.helper= ls-remote --heads origin` answered `1ef9860… refs/heads/main` — with **every** credential helper disabled, so there was no credential available to have been used ✓ |
| the remote's `main` is the local `HEAD` | the same call answered `1ef9860b0cfc2ef611fb690c69c788cb35e5ef89`, which is the local `HEAD` and the commit `v0.0.9` points at |
| every release tag is on the remote | `git ls-remote --tags origin` lists `refs/tags/v0.0.1` … `refs/tags/v0.0.9` |
| the tags are annotated | each tag ref is accompanied by a peeled `refs/tags/v0.0.N^{}` entry, which only annotated tags produce |
| HTTPS credentials come from Git Credential Manager | `git config --show-origin --get-all credential.helper` → `manager`, set in both the system gitconfig and the user's own |
| ssh is globally redirected to Windows OpenSSH | `core.sshCommand = C:/Windows/System32/OpenSSH/ssh.exe`, from `~/.gitconfig` |

The last row is inert here: `origin` is HTTPS, so no ssh process is started. It would
matter the moment the remote were switched to `git@github.com:…`.

## The push

Run it from **PowerShell 7**, not from MSYS / Git Bash. A credential prompt is answered by
the console, and a non-interactive shell has no console to answer it on — the parent
workspace documents the same trap, and its longer version is at
`LPR/git/PUSHING.md`.

```powershell
cd <the package root>          # the directory holding this file

git push origin main --follow-tags
```

`--follow-tags` carries the **annotated** tags pointing at the commits being pushed — the
kind this package has — so a release tag travels with its commit and no second command is
needed.

Push before tagging and the tag stays local: Packagist derives every published version
from tags on the remote, so a release is not published until its tag is there. See
[RELEASING.md](RELEASING.md).

## Prerelease tags

An alpha, beta or rc tag is pushed exactly as a release tag is — `--follow-tags` does not
care what the number says — and Packagist publishes it as a version with a prerelease
suffix rather than as the package's latest:

```powershell
composer release -- --prerelease=alpha --push   # 0.1.0-alpha1, pushed with its commit
```

Two things worth knowing before one goes out:

- **A prerelease is not what `composer require` gets by default.** `^0.1.0` at the default
  stability skips `0.1.0-alpha1`; a consumer installs it with `"minimum-stability": "alpha"`,
  or with a constraint that names the lane — `^0.1.0@alpha`.
- **The tag is the published version, so it cannot be un-published quietly.** Packagist
  keeps what it saw; deleting the tag afterwards leaves a page for a version nobody can
  install. Cut the next lane number (`-alpha2`) rather than moving a tag.

## What a push would send right now

Every tag up to `v0.0.9` is on the remote, and so is the commit that tag points at, so the
release history is in sync and nothing published has been rewritten.

Local `main` is ahead of it: the middleware and config fixes, the PHP 8.4 floor, the `bin/`
gate and releaser, the `docs/` records and the `## Unreleased` section the releaser
promotes. They are committed locally and unreleased — a bare `git push` would carry them as
plain history, and `composer release -- --weigh --push` is what turns them into a version,
because a push publishes nothing on its own: Packagist only ever sees tags.

Read the numbers off this repository rather than trusting this paragraph, which is a
snapshot: `git rev-parse HEAD origin/main`, then `git log --oneline v0.0.9..HEAD`.
