# Windows gate failures involving `sh` and process cleanup

Investigated on 2026-11-04 (Asia/Karachi, UTC+05:00), at `b6259aa` with the existing working tree changes.

The request was to verify two reported failures: the gate rejecting a Windows workstation without
`sh` on PATH, and the release fixture's deadline test leaving a grandchild able to write its marker.

## Missing `sh` reproduces the gate failure

For the control, PowerShell removed the Git shell directories from PATH for the child process only.
`Get-Command sh` then returned no command, and the targeted Python probe verified that
`shutil.which("sh")` returned `None`. Both conditions used the same Python installation after
excluding a Windows application alias that failed to launch in this environment.

- `python .agents/verify.py --selftest` without `sh` exited 1. It reported
  `override-ignored: the mutation was not reported`, with 24 mutations planted and one missed.
  With `sh` available, all 24 mutations were reported and the command exited 0.
- Calling `step_interpreter()` on a pristine fixture and again after planting `override-ignored`
  returned an empty findings list both times. The step logged that it could not ask the resolver.
  The behavioral branch at `.agents/verify.py:1156` checks for `sh` before calling `php_resolution()`.
  The resolution function itself also falls back to PATH without `sh`. In the failing self-test,
  the immediate cause is the branch declining to call the resolver while the mutation runner
  still requires that behavioral probe to report a finding.
- `checkAgentic()` at `bin/checks.php:1794` takes the maximum of the `--layer` and `--selftest`
  exit statuses. The observed self-test exit 1 therefore makes the agentic gate row fail. This
  conclusion comes from the isolated command and gate wiring.
- `bin/tool.php pest tests/Unit/Support/PhpBinaryTest.php --compact`, run through the repository's
  interpreter, returned one failure and five passes without `sh`. Line 58 expected
  `/other/install/php` and received an empty string. With `sh`, all six tests passed.
- The no-`sh` run of `tests/Unit/Support/MutationHarness/Controls/InversionsTest.php` passed its one
  test. `MutationHarness::unblinded()` builds its file list from the inversion entries, which do
  not name `PhpBinaryTest.php`. The reported overlay failure was not reproduced on this checkout.

`docs/commit-environment.md:88` describes the missing-shell probe. The workflow selects
`ubuntu-latest`, so this Windows PATH experiment does not establish the current CI result.

## The deadline failure has prior evidence and did not recur in this run

The earlier full gate and coverage logs both show the grandchild-marker assertion failing on
November 3. Their modification times precede `32e4213`, the commit that consolidated fixture
process handling in `tests/Support/Processes.php`. A marker establishes that the grandchild
wrote it. The assertion does not timestamp the write against termination or identify how the
process escaped or outlasted cleanup.

On the current checkout, `bin/tool.php pest tests/Unit/Support/ReleaseRepoDeadlockTest.php --compact`
ran with `sh` on PATH and exited 0 with **3 passed, 19 assertions in 35.19 seconds**. This included
the marker assertion, the stderr flood and the two-stream capture case. The test file was selected
directly while other verification commands were running. The reported isolated failure did not
reproduce in this check, and one pass does not establish reliability under full-suite load.

`ReleaseRepo::script()` now delegates to `Processes::bounded()`. On Windows, the timeout path
calls `taskkill /F /T`, terminates the direct child, and then invokes `sweepOrphans()` at
`tests/Support/Processes.php:439`. That sweep queries `Win32_Process` by the former parent's PID
through `orphans()` and issues another tree kill for any surviving children it finds. The
comment near line 394 describes the initial snapshot race that this second sweep addresses.
Neither the historical marker assertion nor today's passing test isolates that race in the
current implementation.

Report 0004 records a green run for bug 12. The two later November 3 failures qualify that
historical result. The current focused pass and the older full-suite failures are evidence from
different implementations of the process helper.

Local outputs are under `build/bug-audit/` with the `nov04-` prefix. The earlier runs are
`full-gate-2026-11-03.log` and `full-coverage-2026-11-03.log` in the same directory.
