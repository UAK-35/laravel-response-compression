# Why the opencode configs fail the machine-path reading, and which readings they take down

**Asked** 2026-10-27, with the `/rc-investigation` marker: *"why is opencode files failing machine
path tests and other tests? and what are those tests? and list which path values are at error and
why?"*

## What was read

- `git log --oneline -3` — `63a2465` is *"Put the two opencode session configs in the history,
  because this checkout's editor setup is a decision the repository should carry rather than leave to
  two untracked files"*. The two files are now **tracked**.
- `tests/Support/MachinePaths.php` — `scan()`, `files()`, the `DRIVE`/`SHARE`/`HOME` patterns and
  `tolerated()`.
- `tests/Unit/Support/MachinePathsTest.php` — the reading over this checkout.
- `tests/Support/MutationHarness.php` and `tests/Unit/Support/MutationHarness/MachinePathsTest.php` —
  the holds, which require a pristine read to answer with nothing before a break is planted.
- `tests/Unit/Support/MutationHarness/Controls/InversionsTest.php` — the control that requires every
  guard's tests to be green on an unmutated tree.

Commands run against this checkout:

```
bin/tool.php pest tests/Unit/Support/MachinePathsTest.php
bin/tool.php pest tests/Unit/Support/MutationHarness/MachinePathsTest.php
bin/tool.php pest tests/Unit/Support/MutationHarness/Controls/InversionsTest.php
bin/tool.php pest tests/Unit/Support/RepoEscapesTest.php
rg -n "[A-Za-z]:[\\/]" opencode.jsonc opencode.v1-backup.jsonc
```

(the same search was also run with a mount root and a home directory among the alternatives; the drive
form is the one that answered)

## The two path values at error

Both are the same directory — a temp directory under this machine's user profile — written twice,
once plain and once JSON-escaped:

| File and line | The value, as the reading reports it | Why it is a finding |
|---|---|---|
| `opencode.jsonc:269` | a drive-letter path: `<letter>:/Users/<account>/AppData/Local/Temp/opencode` | It is absolute, it starts with a drive letter, and the directory after it is a **user profile**. `MachinePaths::tolerated()` excuses only `Windows`, `Program Files` (and its `(x86)` sibling), and `/Users/runner` or `/home/runner`; a named account under `Users` is this machine and nobody else's. |
| `opencode.v1-backup.jsonc:95` | the same directory with backslashes, escaped for JSON: `<letter>:\\Users\\<account>\\AppData\\Local\\Temp\\opencode\\*` | The `DRIVE` pattern reads back-slashed drive paths too, so the escaped spelling is the same finding said a second way. |

The literal text is deliberately not reproduced here: this record is itself a file a commit would
carry, and a drive path in it would be a third finding of the same kind.

## Why they started failing now

`MachinePaths::files()` answers *the files a commit would carry* — `git ls-files --cached --others
--exclude-standard`. While the two configs were untracked, `63a2465`'s predecessor state kept them
either out of the tree or out of that listing, so the reading never opened them. `63a2465` put them
in the history, which puts them in the listing: the scan now reads **228 files**, and these two are
among them. Nothing about the detector changed; the input did.

## The tests that fail, and what each one is

1. **`tests/Unit/Support/MachinePathsTest.php`** — the machine-path guard. It scans this checkout
   and requires `MachinePaths::scan()['findings']` to be empty. Result: **1 failed, 35 passed (40
   assertions)**, the failure being the two lines above, printed as
   `opencode.jsonc:269  …` and `opencode.v1-backup.jsonc:95  …`.
2. **`tests/Unit/Support/MutationHarness/MachinePathsTest.php`** — the harness holds for that guard.
   Each hold first requires the *pristine* read of the planted tree to answer with nothing, then
   plants a break and requires the reading to name it. The planted tree is a copy of this checkout,
   so it carries both configs; the pristine read comes back with the two findings, and **every hold
   in the file fails for a reason no mutation planted**.
3. **`tests/Unit/Support/MutationHarness/Controls/InversionsTest.php`** — the control that runs every
   guard's tests on an unmutated tree and requires them green. With (1) red, the control is red:
   **1 failed, 122 passed (382 assertions)**, naming `MachinePathsTest`.

**Passing, and worth saying so:** `tests/Unit/Support/RepoEscapesTest.php` — **24 passed (28
assertions)**. The value is absolute but does not climb, so the repository-escape reading has nothing
to say about it. The failure is the machine-path question only, not "a path in a file".

## The answer, in one sentence

The two configs are now tracked, and each holds an absolute path into this machine's user profile;
the machine-path guard reads tracked files, does not tolerate a named account under `Users`, and so
reports both — and the harness hold and the inversion control inherit that one red because they
require the guard to be green on an unmutated tree.

## What would clear it, and the trade each way

- **Tolerate it** by widening `MachinePaths::tolerated()` to `Users/<account>/AppData/Local/Temp`.
  Cheapest, but it weakens the rule for every path of that shape, not just this one.
- **Move the value out of the file** — an editor config need not name an absolute temp directory; a
  relative one, or the editor's own variable, keeps the decision and loses the machine. This is the
  only option that leaves the detector as strict as it is.
- **Do not track the files** (revert `63a2465`, or ignore them). Returns to the earlier state, where
  the reading never saw them — but then the editor setup the commit deliberately adopted is untracked
  again.

## What this did not establish

- Whether `63a2465` intended the configs to carry this machine's temp directory, or whether the value
  arrived with the file and was committed with it.
- The full-suite colour: only the four files above were run. Other readings that copy the checkout
  (any other harness hold) were not measured, though they fail by the same mechanism when the guard
  they hold is the machine-path one.
