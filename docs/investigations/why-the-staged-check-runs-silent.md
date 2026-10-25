# Why the staged check can run for nine minutes with no output

**Asked** 2026-10-25: *"Find out why the pre-commit hook's staged check can run for nine minutes
with no output, and make it either finish or say what it is waiting on."*

## What was read

- `.githooks/pre-commit` — its last step is `"$php_bin" bin/checks.php --staged`; nothing else runs
  the checks, and the hook prints nothing itself before that line.
- `bin/checks.php` — the main loop, `runCommand()`, `runCommands()` and the `tests` check.
- `tests/Support/TestMap.php` — `covering()`, and the `SUITE` constant it falls back to.
- `tests/Unit/Gate/ChecksTest.php` — the gate's own tests, run afterwards.
- The `f745bfd` commit message, which recorded the observed nine-minute run: *"`bin/checks.php
  --staged` … held a PHP process for nine minutes without writing a line the last time it was tried
  in this session."*

## Findings

1. **The nine minutes is the whole suite, not one slow test.** `TestMap::covering()` maps a path
   under `tests/` that is not itself a `*Test.php` under `tests/Unit` to `TestMap::SUITE`, which is
   `tests/Unit`. So a commit carrying `tests/Support/Notes.php`, `Handoff.php` or `PhpBinary.php`
   stages a gate whose `tests` check runs the whole suite. The comment in `TestMap` argues the
   mapping deliberately: a support file is "a fixture the whole suite is built on, which is the only
   narrower true answer there is." The suite under `--parallel` on this machine measures between
   roughly three and six minutes; with the pool's retries and a loaded machine, nine is within the
   same run.

2. **The silence is the gate's output shape.** Each check's output is captured into
   `$results[$key]['output']` and printed only in the summary after every check has finished. The
   check's title is printed before it starts only under `-v`, and `-v` also streams the check's whole
   output — which is not what a commit hook wants. With neither, a run that spends minutes in one
   check writes nothing until the summary.

3. **One layer down, a child could wait on a prompt.** `runCommand()` and `runCommands()` called
   `proc_open()` with descriptors 1 and 2 only. Descriptor 0 was inherited, so a tool that read stdin
   would wait on a terminal the hook does not give it — the same silence, one process further in. The
   suite's own fixtures already declare all three descriptors for this reason
   (`tests/Support/ReleaseRepo.php`).

## The answer

The hook is silent for as long as the `tests` check runs because the staged mapping can be the whole
suite and the gate buffers every check's output until the summary. It is slow because the mapping is
correct — a support file really does underlie the suite — so the honest fix is to say what is
running, and to make a child that reads stdin end rather than wait.

## What was changed (`5a48ebf`)

- `bin/checks.php` writes `▶ <title>` to stderr as each check starts when not under `-v`, so a commit
  sees which check it is waiting on.
- `runCommand()` and `runCommands()` declare descriptor 0 as `NUL` on Windows and `/dev/null`
  elsewhere, so a prompt nobody can answer becomes EOF.
- `tests/Unit/Gate/ChecksTest.php` holds it: `says which check is running on the error stream, so a
  commit is not waiting on silence` requires the title on stderr and keeps it off stdout.

**Measured after the change:** `bin/checks.php --only=syntax` printed `▶ PHP syntax (php -l)` before
its table; `ChecksTest` 16 passed (55 assertions); Pint in test mode, PHPStan at max and Rector's
dry run clean. The same line then appeared in the next two commits' hooks, which is the run that
was silent before: `▶ PHP syntax (php -l)`, `▶ Code style (pint --test)`, `▶ Staged tests (pest)`.

## What this did not establish

- A full nine-minute `--staged` run was not reproduced in this session: staging the working tree would
  have changed the index another writer was using. The duration is attributed to the suite from the
  mapping and the measured suite runs, not from a timed staged run.
- The change makes the run legible; it does not make the whole-suite mapping faster. A staged support
  file still runs the suite, by design.
