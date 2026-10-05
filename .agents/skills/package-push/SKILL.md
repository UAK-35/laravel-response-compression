---
name: package-push
description: "Carry a version's tag to the remote, and check the pushing runbook against the tree it describes. Commands - push, verify-pushing. Invoke when a cut tag has to reach the remote, when a push was refused or carried nothing, or when a claim in the pushing runbook needs checking again."
license: "MIT"
---

# Package Push — getting a cut version's tag to the remote

Use this skill when a tag that was already cut has to reach the remote, or when a push was refused or
carried nothing. A version is a **tag**, and Packagist publishes tags: until the tag is on the remote
the version is not published, which is the whole subject of this skill. Cutting the tag is not here —
that is `package-deploy` — and this skill never weighs or cuts anything.

It is one half of the former `package-release`, which was split because the two halves answer to
different things: this half touches the remote and can be refused by it, and the other half writes a
commit and a tag and touches nothing outside the checkout.

## Environment

- The push is a `git` command, and on Windows it runs from **PowerShell 7**, never from MSYS or Git
  Bash: MSYS has no usable askpass, so a credential prompt has no console to be answered on and the
  push either hangs or ends in `Permission denied (publickey)`. Pushing over HTTPS is what the remote
  is configured for.
- Machine-local paths are tokens; resolve one with `python .agents/render_local.py --value __TOKEN__`.
  `.agents/machine.local.json` is the only file in this repository that carries a PHP path, and
  `.agents/php-bin.sh` is the one place that decides which interpreter runs here.
- The lane is `development`, written once in `.release-lane`. A push names it as the branch it sends —
  the file is the release program's default branch and is not read here, so this is the one command
  that still says the branch out loud.
- The releaser that wrote the tag is `composer release` (`bin/release.php`), or
  `"$(python .agents/render_local.py --value __PHP_EXE__)" bin/release.php` where `composer` is not on
  `PATH`. This skill does not run it; it reads what it left behind.

## Commands

- `push`: run `git push origin development --follow-tags` from PowerShell 7, never from MSYS. A
  credential prompt is answered by the console, and a non-interactive shell has no console to answer it
  on. `--follow-tags` carries the annotated tags that point at the commits being pushed, so a tag
  travels with its commit.
- `verify-pushing`: read `PUSHING.md` against the repository as it is now — the lane, the tag range,
  which tags are annotated, and the numbers in its evidence table — and fix what has gone stale in the
  same change. A runbook whose numbers nobody read again is worse than a runbook that says which day it
  was read.

## What `--follow-tags` carries, and what it does not

`--follow-tags` pushes the annotated tags that point at commits in the range being pushed. A
**lightweight** tag is not carried: `v0.0.1` is the one lightweight tag in this repository, and it is
therefore the one a `--follow-tags` push would leave behind. If a version must travel, make its tag
annotated, or name it — `git push origin development --follow-tags v0.0.1` — and check with
`git ls-remote --tags origin` that the remote agrees.

## Failure modes worth naming before they happen

| Symptom | What it means |
|---|---|
| the push was refused as non-fast-forward | the remote has commits this checkout does not; fetch and reconcile rather than forcing |
| a tag exists locally and is not on the remote | it was cut without being pushed, or the tag is lightweight and `--follow-tags` did not carry it |
| the push hung with no prompt | it ran from a shell with no console to answer the credential prompt on — push from PowerShell 7 |
| `--follow-tags` carried nothing | the tag is lightweight, or there are no new annotated tags in the range |

## Where the other half is

Weighing what the tree adds up to, choosing the number, cutting the tag and checking `RELEASING.md` are
`package-deploy`. This skill starts from a tag that already exists and ends when the remote has it.
