# RC Push - getting a cut version's tag to the remote

Use this skill when a tag that was already cut has to reach the remote, or when a push was refused or
carried nothing. A version is a **tag**, and Packagist publishes tags: until the tag is on the remote
the version is not published, which is the whole subject of this skill. Cutting the tag is not here —
that is `rc-deploy` — and this skill never weighs or cuts anything.

It is one half of the former `package-release`, which was split because the two halves answer to
different things: this half touches the remote and can be refused by it, and the other half writes a
commit and a tag and touches nothing outside the checkout.

## Environment

- The push is one command, `composer push`, and on Windows it runs from **PowerShell 7**, never from
  MSYS or Git Bash: MSYS has no usable askpass, so a credential prompt has no console to be answered
  on and the push either hangs or ends in `Permission denied (publickey)`. Pushing over HTTPS is what
  the remote is configured for.
- Machine-local paths are tokens; resolve one with `python .agents/render_local.py --value __TOKEN__`.
  `.agents/machine.local.json` is the only file in this repository that carries a PHP path, and
  `.agents/php-bin.sh` is the one place that decides which interpreter runs here.
- The branch is not named here. `.release-lane` holds it, `bin/push.php` reads it as the release
  program does, and a run of this skill pushes the branch `HEAD` is on — so the lane is stated in one
  file rather than repeated in a command that would drift from it.
- The releaser that wrote the tag is `composer release` (`bin/release.php`), or
  `"$(python .agents/render_local.py --value __PHP_EXE__)" bin/release.php` where `composer` is not on
  `PATH`. This skill does not run it; it reads what it left behind.

## Commands

- `push`: run `composer push` (`bin/push.php`) from PowerShell 7, never from MSYS — a credential
  prompt is answered by the console, and a non-interactive shell has no console to answer it on. It
  pushes the current branch with `--follow-tags`, so the annotated tags pointing at those commits
  travel with them, and then it does the half `git push` cannot: it finds the GitHub Actions run
  GitHub started for the commit it pushed and waits for that run to conclude. `success` is the only
  answer that exits 0; a failure, a cancellation, a timeout, a push that started no run at all, and a
  reader that could not be asked each exit 1 and say what was read. That is why this is one command
  rather than two: "the bytes left this machine" and "what ran because of them passed" are different
  claims, and reporting the first as the second is the shape of every push followed by a red build.
  `-- --no-watch` pushes and stops, saying the run was not read; `-- --dry-run` says what would be
  pushed and read, and changes nothing.
- `verify-pushing`: read `PUSHING.md` against the repository as it is now — the lane, the tag range,
  which tags are annotated, and the numbers in its evidence table — and fix what has gone stale in the
  same change. A runbook whose numbers nobody read again is worse than a runbook that says which day it
  was read.

## What the push carries, and what it does not

`bin/push.php` pushes with `--follow-tags`, which carries the annotated tags that point at commits in
the range being pushed. A **lightweight** tag is not carried: `v0.0.1` is the one lightweight tag in
this repository, and it is therefore the one such a push would leave behind. If a version must
travel, make its tag annotated — or name it in a `git push` beside the wrapper — and check with
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
`rc-deploy`. This skill starts from a tag that already exists and ends when the remote has it.
