# Package Release — weighing, cutting and pushing a version

Use this skill when the question is what the tree adds up to, what the next version should be, or how
a version reaches the remote. A version is a **tag**, and Packagist publishes tags: a commit on a
branch is invisible to a consumer, which is the fact every step below is arranged around.

## Environment

- The releaser is `composer release -- <options>`, which is the same program CI runs, or
  `"$(python .agents/render_local.py --value __PHP_EXE__)" bin/release.php <options>` where
  `composer` is not on `PATH`. Neither form writes an interpreter down: Composer runs the script
  under `PHP_BINARY`, and the machine file names this machine's PHP once.
- Machine-local paths are tokens; resolve one with `python .agents/render_local.py --value __TOKEN__`.
  `.agents/machine.local.json` is the only file in this repository that carries a PHP path.
- The lane is `development`. `bin/release.php` defaults to `main` and refuses a cut anywhere it was
  not told to expect one, so every run passes `--branch=development`.

## Commands

- `unreleased`: read `## Unreleased` in `CHANGELOG.md` and say what it adds up to in one line per
  bullet group. This is the notes a release promotes, so a release that cannot be weighed from it is a
  changelog that needs writing first.
- `weigh`: run `composer release -- --weigh --branch=development`. It cuts nothing: it reads the
  commits since the last tag and the public surface between the two revisions and answers whether the
  next number is a patch, a minor or a major.
- `weigh-dry-run`: run the same command with `--dry-run`, which prints the notes it would write. Use it
  whenever the number is in question, and quote the surface change it found rather than the number it
  printed.
- `release-cut`: run `composer release -- --branch=development` after the weighing has been agreed. It
  writes the release commit — the version heading, the notes, the inventory — and tags it. Nothing is
  pushed by this command.
- `prerelease`: cut an alpha, beta or rc with
  `composer release -- --prerelease=alpha --branch=development --push`. The tag is what makes it
  installable, and a prerelease is skipped by a default `composer require` — a consumer needs
  `"minimum-stability": "alpha"` or a constraint like `^0.1.0@alpha`.
- `push`: run `git push origin development --follow-tags` from PowerShell 7, never from MSYS. A
  credential prompt is answered by the console, and a non-interactive shell has no console to answer it
  on. `--follow-tags` carries the annotated tags that point at the commits being pushed, so a tag
  travels with its commit; `v0.0.1` is the one lightweight tag in this repository, and therefore the
  one it would not carry.
- `verify-runbooks`: re-read `RELEASING.md` and `PUSHING.md` against the repository as it is now —
  the lane, the tag range, which tags are annotated, and the numbers in their evidence tables — and
  fix what has gone stale in the same change. A runbook whose numbers nobody re-read is worse than a
  runbook that says which day it was read.

## What a version is, and what a cut writes

- The numbering counts on from the newest tag; the `0.1.0` and `0.2.0` headings in `CHANGELOG.md` are
  the inherited upstream project's and are not part of this sequence.
- The releaser reads the public surface between two revisions: a class or method removed, a signature
  or a default changed, a namespace moved. That is the weight, and it is why the changelog bullets
  matter more than the commit count.
- A tag that is pushed is a published version within minutes, and Packagist keeps what it saw:
  deleting the tag leaves a page for a version nobody can install. Cut the next lane number rather
  than moving a tag.

## Failure modes worth naming before they happen

| Symptom | What it means |
|---|---|
| the releaser refuses the branch | the run has no `--branch=development`, or the checkout is on another branch |
| the weighing says major and nobody agrees | the surface signal found a change to `src/`; read what it named before rewording anything |
| a tag exists locally and is not on the remote | it was cut without `--push`; the version is not published, and `PUSHING.md` has the rest |
| `--follow-tags` carried nothing | the tag is lightweight, or there are no new annotated tags to carry |
| a prerelease is not installable | default stability skips it; that is the tag working as intended |
