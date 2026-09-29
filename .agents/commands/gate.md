# `gate`

Run the package's gate and report what each check said. The gate is nine checks in one process and
it is the same gate a tag is cut behind; this command runs it and reads the summary, rather than
replacing any part of it.

## Usage

```bash
composer checks                      # a workstation: a missing tool is a skip
composer checks -- --require-all     # a release or CI: a missing tool is a failure
composer checks -- --only=phpstan,pint,tests    # a subset, when iterating
composer checks -- -v                # stream every check's output, not just failures
composer test:unit                   # the coverage floor, which the gate deliberately does not run
```

## What to do with it

1. Read the summary table first: `9 checks: 9 passed, 0 failed, 0 skipped` is green, and a skip is
   not a pass — say so instead of reporting the run as green.
2. For a failure, take the check's own output seriously rather than re-running the tool by hand: the
   gate runs it through `bin/tool.php`, under the interpreter that installed the dependencies, which
   is the whole reason it exists.
3. A failure in `tests` is reported again by `composer test:unit` if it is a coverage floor. The gate
   runs Pest without a driver on purpose, so a floor failure belongs to the coverage command.
4. Fix the finding rather than narrowing the gate. A check that was skipped or excluded to get a
   green run is a finding in itself, and the `--require-all` run is what proves it was not.

## Related

- `.agents/toolchain.json` — the `gate`, `gate-local`, `staged`, `checks-list` and `test-*` commands.
- `AGENTS.md` section 5 — what the nine checks prove, and why the floor is separate.
- `.githooks/pre-commit` — the same tools, on staged files, before a commit.
