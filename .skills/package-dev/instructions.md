# Package Dev — the daily loop in this checkout

Use this skill for any work inside this repository: a change to `src/`, the config, the tests, the
docs records, the gate in `bin/` or the agentic layer. It is a package, so there is no server to
start, no migration to run and no queue to watch — the loop is *edit, narrow, gate, record*.

## Environment

Every machine-local path is a token; the values live in the gitignored `.agents/machine.local.json`
(section 3 of `AGENTS.md`). Resolve one before a command that needs it spelled out:

```bash
python .agents/render_local.py --value __PHP_EXE__   # this machine's PHP
python .agents/render_local.py --value               # every token and its value
```

- The package manager is `composer` where it is on `PATH`, and
  `"$(python .agents/render_local.py --value __PHP_EXE__)" composer.phar <script>` where it is not —
  which is most machines here. A bare `composer` may be a different installation with a different PHP.
- Most commands below name **no** interpreter, and that is the point: Composer runs a script under the
  interpreter that is already running it, so `composer checks` is whatever PHP invoked Composer, and a
  direct `bin/checks.php` or `bin/release.php` run is the one case that needs the token spelled out.
- The Windows shell is PowerShell 7 (`__PWSH_EXE__`); on Linux, `bash`.
- Nothing here is run through a `vendor/bin` shim. `bin/tool.php` runs a tool under `PHP_BINARY`,
  which is the interpreter that installed the dependencies.
- `.agents/machine.local.json` is the only file in this repository that names an interpreter. The
  pre-commit hook resolves it first and says so when it had to fall back, and
  `python .agents/verify.py` reads it before it looks on `PATH`.

## Commands

Each of these is a command this skill defines. They are thin on purpose: the gate and the programs in
`bin/` are where the behaviour lives.

- `gate`: run `composer checks -- --require-all` — the nine checks, with a missing tool as a failure
  rather than a skip. This is what CI and a release run.
- `gate-local`: run `composer checks` — the same nine, where a missing `require-dev` tool is a skip.
  Use it while iterating; report a skip as a skip rather than as a pass.
- `staged`: run `__PHP_EXE__ bin/checks.php --staged` — syntax, style and the links between the docs,
  on the staged files only. It is what the pre-commit hook runs under the same interpreter, and it is
  the fastest useful answer.
- `checks-list`: run `__PHP_EXE__ bin/checks.php --list` — the nine checks and the condition each one
  skips on, under this machine's PHP rather than whichever `php` is first on `PATH`.
- `index`: run `composer index` (`__PHP_EXE__ bin/index.php`) — rewrite the two lists of files in
  `docs/guards.md` from `tests/Support/`, where each file declares its own row and its own words.
  `__PHP_EXE__ bin/index.php --check` is the same reading with nothing written, which is what the
  suite asserts: use it after adding, renaming or moving a file in `tests/Support/`.
- `test`: run `composer test` — Rector dry-run, Pint in test mode, PHPStan and the coverage floor, in
  that order. The order matters: style before analysis means a formatting complaint is not reported
  as a type error.
- `test-unit`: run `composer test:unit` — Pest under coverage, holding every `src/` file at 100.0% and
  a ratchet per program in `bin/`. A floor failure is this command's, not the gate's.
- `test-types`: run `composer test:types` — PHPStan at max level, through `bin/tool.php`.
- `test-lint`: run `composer test:lint` — Pint in test mode, which writes nothing.
- `tidy`: run `composer tidy` — Pint, then Rector, then Pint again. The second pass judges what
  Rector rewrote; a single pass reports style that Rector then reintroduces.
- `single-test <path>`: run one test file or directory through the tool manifest, for example
  `single-test tests/Unit/Middleware`. Narrow the run first, then run `gate-local` on the whole tree.
- `agentic-check`: run `python .agents/verify.py` — the whole check, in two halves: the tools and
  processes this package needs, and the agentic layer against itself. Read-only, exit 1 on a finding.
- `verify-tools`: run `python .agents/verify.py --package` — the tools and the two package readings
  without the layer's: PHP and the version pin, the `ext-*` requirements, a coverage driver for the
  floor, composer, git, every tool in `bin/tool-paths.php` asked to start through `bin/tool.php`, the
  hook enabled in this clone, and whether there is a `vendor/` at all. This is the half that answers
  "does this machine still work" rather than "is this change right".
- `agentic-layer`: run `python .agents/verify.py --layer` — the layer alone, with no PHP in it: the
  pointer files, the index, each skill target against the source it was generated from, the rendered
  command table, the settings files against their templates, the tokens. A second or two, and what
  the pre-commit hook runs.
- `agentic-fix`: run `python .agents/verify.py --fix` — repair what a generator owns (the rendered
  command table in `AGENTS.md`, the skill targets), then re-check. It never overwrites a file a person
  edited: a rendered settings file that has drifted is reported instead.
- `agentic-selftest`: run `python .agents/verify.py --selftest` — plant one fault per step in a copy of
  the layer and require that step to name it. It answers "is the check still checking", and it is the
  thing to run after changing a step rather than after changing the package.

## The loop

1. **Read the file you are about to change, and the one beside it.** This repository's style is
   argued, not decorative: a comment says why, and a test says what would otherwise be assumed.
2. **Narrow the run.** `single-test <path>` while the change is in flight, so a failure is about the
   change rather than about the tree.
3. **Run the gate.** `gate-local` for the nine checks, then `test-unit` when the change touches `src/`
   or `bin/` — the gate deliberately does not measure coverage, so it cannot catch a floor.
4. **Record it.** A behaviour change carries a `CHANGELOG.md` bullet under `## Unreleased`, in the
   section it belongs to. A change that contradicts a `docs/` record fixes the record in the same
   commit. A new guard carries its row in `docs/guards.md`; a new file in `tests/Support/` declares
   which of the page's two lists it is in, in its own header, and `index` writes the row.
5. **Before finishing, `agentic-check`.** Its `tools` half is what notices that the machine under the
   package moved — a second PHP answering, an extension gone, a tool the manifest names that no
   longer starts — which is the class of failure that reads as a bad day rather than as a defect.
6. **Commit named paths.** This checkout is developed beside other work; `git add -A` is how an
   unrelated file joins a commit.

## What a red check means

| Check | Where the failure belongs |
|---|---|
| `syntax`, `ast` | the file the trace names — the AST parse finds what `php -l` tolerates |
| `schema` | `composer.json`, and usually a key Composer's own schema rejects |
| `platform` | the machine: a missing `ext-brotli` or `ext-zstd` is a build problem, not a test failure |
| `yaml` | `.github/workflows/main.yml` — the workflow is parsed, this file included |
| `phpstan` | the type, not the style; `@phpstan-…` is a last resort, not the first |
| `pint` | run `tidy`, do not hand-fix the spacing |
| `rector` | another `tidy` pass; Rector's output is what the second Pint judges |
| `tests` | the assertion, and the guard it belongs to |
| `test:unit` floor | the lines the change added without a test, or a driver the machine lacks — `bin/coverage.php` names the PHP and the driver it found |

## Rules this skill does not bend

- **The public surface is a promise.** `src/` and the config keys are what a consumer installs against;
  a signature, a namespace or a default that changes is a breaking change, and `bin/release.php
  --weigh` is the weighing, not the opinion.
- **Config is read once.** `env()` appears nowhere outside `config/`, and an unreadable value is
  refused rather than silently defaulted.
- **No machine name in a committed file.** No drive path, no home directory, no network share, and no
  relative path that climbs out of the checkout. The two guards own that reading; write a `__TOKEN__`.
- **A guard with no teeth is worse than none.** A new one gets its `docs/guards.md` row and a mutation
  or a named decline in `tests/Support/MutationHarness.php`.
