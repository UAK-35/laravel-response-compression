# Bug audit fix status

Created: 2026-11-02 (Asia/Karachi, UTC+05:00). Report: 0004.

## Request and scope

Original review request: "read the file and try to find out which bugs have been fixed - list all bugs with status".

Recording request: "output the above (last prompt's) response into a markdown document inside docs/reports/ folder".

This document preserves the preceding review of `docs/reports/0002-2026-10-31_rpt-config-src-tests-bug-audit.md`.
It describes the working tree inspected during that review, including uncommitted middleware changes;
saving this response does not constitute a new code review or a fresh full-suite verification.

## Bug status

Against the **working tree as it stood on 2026-11-02, including uncommitted changes**: **9 fixed,
3 partially fixed, 1 still open**. That count is the snapshot's, kept as it was recorded; finding 12
was closed on 2026-11-03, which makes the tree **10 fixed, 3 partially fixed, 0 still open** — see
the update below the table.

| # | Bug from the audit | Status | Current evidence |
|---|---|---|---|
| 1 | Existing `Vary` headers overwritten | **Partial** | Encoders now append correctly. However, the user-agent handling reads only the first header field and can discard `Accept-Encoding` or other variation values. |
| 2 | Multiple compression layers advertised as one | **Fixed** | The loop stops after encoding. One decompression recovered the original body. |
| 3 | Already encoded responses compressed again | **Fixed** | Responses containing `Content-Encoding` are skipped; large pre-compressed content stayed unchanged. |
| 4 | Refused encodings with `q=0` still sent | **Fixed** | Explicit refusals, wildcard refusals, and explicit overrides of wildcards were respected. |
| 5 | Partial responses compressed without updating ranges | **Fixed** | Both status 206 and responses containing `Content-Range` are skipped. |
| 6 | Fallback incorrectly depends on the single algorithm | **Partial** | Gzip-only and user-agent fallback now work. However, multiple-encoding mode incorrectly rejects `Accept-Encoding: *`. |
| 7 | Missing `Vary: User-Agent` | **Fixed** | Both accepted and blocked user agents now receive that variation marker. Header preservation still has the issue listed under #1. |
| 8 | Strong ETag retained after compression | **Fixed** | All three encoders weaken strong ETags and preserve existing weak tags. |
| 9 | Case-sensitive encoding matching | **Fixed** | Uppercase `GZIP` now works in single and multiple modes. |
| 10 | Brotli detector rejects compressed empty/zero strings | **Fixed** | Both valid compressed values now return `true`. |
| 11 | Ineffective regression fixtures | **Partial** | The pre-compressed fixture and compression-path `Vary` test improved. Other original tests remain weak, and exploration tests contain stale or incorrect assertions. |
| 12 | Release helper deadlocks on stderr output | **Fixed** (2026-11-03) | `script()` no longer reads a stream to EOF: a reader child drains stdout while the parent polls `proc_get_status()` against a 300-second deadline, and a run that reaches it is stopped with its descendants and reports 124. `d3b2ae4` moved stderr to a file, `491b109` added the deadline and the tree kill, and `6356101` added a case with a megabyte on **each** stream. `tests/Unit/Support/ReleaseRepoDeadlockTest.php` is 3 passed (19 assertions); the gate is green (`build/gate-bug12p2.log`: 11 checks, 621 tests). |
| 13 | Guard declaration PHPDoc omits `name` | **Fixed** | The returned array shape now declares `name: ?string`. |

The focused exploration suite produced **30 passes and 4 failures**. Some failures are test defects,
rather than proof that the corresponding runtime fix failed. Full verification was stopped after
these failures and the confirmed deadlock; **no full-suite pass is claimed**.

The main remaining problem recorded here is header handling and fallback
(`src/Middleware/CompressResponse.php:55`, with header reconstruction at lines 135–150). Process
draining (`tests/Support/ReleaseRepo.php`) was the other one named, and it was closed on 2026-11-03:
the paragraph above and the sixteen-second probe recorded below are what was true on 2026-11-02.

## Update — finding 12 closed (2026-11-03)

The snapshot above recorded finding 12 as still open, because the concurrent-drain attempt of the
time still hung in the Windows reproduction. It is closed in the repository now, by `491b109` and
`6356101`, on top of `d3b2ae4`'s move of stderr to a file:

- **The wait has a clock.** `ReleaseRepo::script()` starts a reader child that copies stdout to the
  capture file, so this process never enters a blocking read and can poll `proc_get_status()`
  against a deadline — 300 seconds, or whatever `within()` asks for. A read on a `proc_open` pipe
  cannot be made non-blocking on this host (`stream_set_blocking()` answers `false`, the stream's
  meta data still says `blocked`, and the first `fread()` waited 2.1 seconds), and stdout cannot
  become a file either, because `bin/bootstrap.php` hands `STDOUT` to composer and the verifier and
  that write was measured landing at the capture file's first byte.
- **The cleanup is part of it.** A run that reaches the deadline is stopped with its descendants —
  `taskkill /F /T` first on Windows, because `/T` finds a tree through the parent that is still
  there, and a `ps`-walked descendant kill elsewhere — the reader is given a bounded grace to finish
  its file, and the run reports 124 with `timedOut` and `deadline` carried on `ReleaseRun`.
- **The regression case is no longer one-sided.** `6356101` added a fixture that writes a megabyte
  to stdout *and* a megabyte to stderr, a kilobyte at a time in turn; with `capture()` planted to
  keep 1 KiB, both volume cases fail rather than one, which is what shows the second case depends on
  the stdout tail rather than on stderr alone.

Measured: `tests/Unit/Support/ReleaseRepoDeadlockTest.php` 3 passed (19 assertions), and the gate
green with `--require-all` (`build/gate-bug12p2.log`: 11 checks, 621 tests). The probe logged under
`## Evidence retained from the review` and the "still open" row above are kept as the 2026-11-02
reading they were; this section supersedes them.

## Evidence retained from the review

- Inspected the original audit, current middleware, all three encoders, `src/Support/Enc.php`,
  `tests/Support/GuardIndex.php`, `tests/Support/ReleaseRepo.php`, the middleware regression tests,
  `tests/Pest.php`, and `tests/Unit/Support/ReleaseRepoDeadlockTest.php`.
- Ran the existing runtime reproducer `build/bug-audit/reproduce.php` against the current code;
  output is retained in `build/bug-audit/status-reproduce-2026-11-02.log`.
- Ran additional wildcard, separate-field Vary, user-agent fallback, range-header and ETag probes;
  scratch program and output are `build/bug-audit/status-extra-2026-11-02.php` and
  `build/bug-audit/status-extra-2026-11-02.log`.
- Ran `bin/tool.php pest tests/Unit/Middleware/BugConditionExplorationTest.php --compact` through
  the package-resolved interpreter: **30 passed, 4 failed, 124 assertions**, 19.32 seconds.
  Output is retained in `build/bug-audit/status-exploration-tests-2026-11-02.log`.
- Repeated the bounded stderr-flood probe against `ReleaseRepo::script()`: the child started but
  did not complete its stderr write within 15 seconds. The watchdog terminated that probe's tree.
- Started `python .agents/verify.py`, then stopped only that review's verification process tree
  after the focused failures and independently reproduced deadlock. Its log path is
  `build/bug-audit/status-verify-2026-11-02.log`; it does not establish a completed verification.

The scratch programs and logs are ignored local evidence, not durable report dependencies. No
implementation fixes were applied by the status review or by recording this response.

## Report ordering

The existing reports numbered `0001`, `0002` and `0003` record original creation dates of
2026-10-30, 2026-10-31 and 2026-11-01 respectively. Those recorded dates establish their order
before this report's 2026-11-02 creation date. This report is appended as `0004`; no existing
report is renamed. Filesystem timestamps were inventoried but were not substituted for the
recorded creation dates.
