# Package Deploy — weighing and cutting a version

Use this skill when the question is what the tree adds up to, what the next version should be, or how
a version is cut and tagged. A version is a **tag**, and Packagist publishes tags: a commit on a
branch is invisible to a consumer, which is the fact every step below is arranged around.

This is one half of the former `package-release`, and it is the half that touches nothing outside the
checkout: it reads the tree, writes the release commit and the tag, and stops. **It never pushes.**
Getting the tag to the remote — `git push`, and the `PUSHING.md` runbook that goes with it — is
`package-push`.

## Environment

- The releaser is `composer release -- <options>`, which is the same program CI runs, or
  `"$(python .agents/render_local.py --value __PHP_EXE__)" bin/release.php <options>` where
  `composer` is not on `PATH`. Neither form writes an interpreter down: Composer runs the script
  under `PHP_BINARY`, and the machine file names this machine's PHP once.
- Machine-local paths are tokens; resolve one with `python .agents/render_local.py --value __TOKEN__`.
  `.agents/machine.local.json` is the only file in this repository that carries a PHP path, and
  `.agents/php-bin.sh` is the one place that decides which interpreter runs here — `sh
  .agents/php-bin.sh --show` prints it, and the hook and the workflow ask the same file.
- The lane is `development`, written once in `.release-lane`. `bin/release.php` reads that file as its
  default branch and refuses a cut anywhere other than it, so no run here has to name the branch.

## Commands

- `unreleased`: read `## Unreleased` in `CHANGELOG.md` and say what it adds up to in one line per
  bullet group. This is the notes a release promotes, so a release that cannot be weighed from it is a
  changelog that needs writing first.
- `weigh`: run `composer release -- --weigh`. It cuts nothing: it reads the commits since the last tag
  and the public surface between the two revisions and answers whether the next number is a patch, a
  minor or a major.
- `weigh-dry-run`: run the same command with `--dry-run`, which prints the notes it would write. Use it
  whenever the number is in question, and quote the surface change it found rather than the number it
  printed.
- `release-cut`: run `composer release --` after the weighing has been agreed. It writes the release
  commit — the version heading, the notes, the inventory — and tags it. **Nothing is pushed by this
  command**; the tag is local until `package-push` sends it.
- `prerelease`: cut an alpha, beta or rc with `composer release -- --prerelease=alpha`. The tag is what
  makes it installable, and a prerelease is skipped by a default `composer require` — a consumer needs
  `"minimum-stability": "alpha"` or a constraint like `^0.1.0@alpha`. This cuts the tag and stops; the
  `--push` that would send it is not passed here, and pushing it afterwards is `package-push`.
- `verify-releasing`: read `RELEASING.md` against the repository as it is now — what a version is, the
  numbering, the prerelease lanes, what the release commit carries — and fix what has gone stale in the
  same change. The pushing half of the runbooks is `package-push`'s.

## What a version is, and what a cut writes

- The numbering counts on from the newest tag; the `0.1.0` and `0.2.0` headings in `CHANGELOG.md` are
  the inherited upstream project's and are not part of this sequence.
- The releaser reads the public surface between two revisions: a class or method removed, a signature
  or a default changed, a namespace moved. That is the weight, and it is why the changelog bullets
  matter more than the commit count.
- A cut writes the release commit and the tag and leaves both in the checkout. A tag that is pushed is
  a published version within minutes, and Packagist keeps what it saw: deleting the tag leaves a page
  for a version nobody can install. Cut the next lane number rather than moving a tag — and publish it
  through `package-push` rather than by re-running the releaser.

## Failure modes worth naming before they happen

| Symptom | What it means |
|---|---|
| the releaser refuses the branch | the checkout is on a branch other than the lane `.release-lane` names |
| the weighing says major and nobody agrees | the surface signal found a change to `src/`; read what it named before rewording anything |
| a tag exists locally and is not on the remote | it was cut and never pushed — the version is not published, and `package-push` has the rest |
| a prerelease is not installable | default stability skips it; that is the tag working as intended |
