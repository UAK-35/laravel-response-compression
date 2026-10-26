# SCRIPTS.md — the tools an agent runs in this package

This is the inventory of the **agent tools**: the checks in `.agents/`, the generator in `.skills/`,
the programs in `bin/`, the hook that runs them before a commit, the workflow that runs them on a
runner, and the composer names that reach the programs. They are spread across four directories and
a manifest, and a reader who wanted to know whether a tool exists — and which command starts it —
had nowhere to look.

Paths here are relative to the **package root**, the directory `CHANGELOG.md` is in. Every row of
the inventory tables opens with its path — a link in these tables, so the file can be opened from
the row — and the last column is the command that starts it rather than a description of it. A tool
that is named here is therefore a file a reader can open, and a line a reader can run.

**This file is the list; [the tool registry](tool-registry.md) is the registry.** The same tools
appear there with the two things a list of names cannot carry: what each one *is* in the chain, and
**who calls it**. Read this page to find out that a tool exists and how to start it; read the
registry to find out whether touching it is safe. That split is also what keeps this page from
being a third copy: it says *that* a tool exists and *how* it runs, and never *why*, which is
argued once, in the document that owns the decision.

**Three lists already have an owner, and are not repeated here:**

- **The guards, the readings they are built from and the fixtures beside them** — every guard this
  package keeps, what each one catches, which `tests/Support/` file it asserts with, and which were
  considered and refused — are indexed in [the guards page](guards.md). Those tables are written
  rather than maintained: the files declare themselves, `bin/index.php` renders them, and the suite
  asserts the document against the directory.
- **The tool paths and the pin on each tool** are written once, in
  [`../bin/tool-paths.php`](../bin/tool-paths.php), and read from there by every caller. A path
  repeated on this page would be the `vendor/bin` failure one more time.
- **What is loadable** — every skill, custom agent and command, and where it is authored — is
  indexed in [`../.agents/README.md`](../.agents/README.md), which `verify.py`'s `index` step reads
  in both directions.

## The agentic layer — `.agents/`

| Tool | What it is | How it runs |
|---|---|---|
| [`../.agents/verify.py`](../.agents/verify.py) | the check over the layer itself: fourteen steps in two halves — `tools` **runs programs**, the other thirteen **read files** | `python .agents/verify.py`; `--list`, `--only=STEP`, `--layer` (the fast subset the hook runs), `--package`, `--fix`, `--selftest` |
| [`../.agents/render_local.py`](../.agents/render_local.py) | owns `.agents/machine.local.json` — the one file in this repository that names this machine's paths, and it is gitignored | `--init`, `--check`, `--value [__TOKEN__]`, `--force`, `--yes`, and `--php` / `--pwsh` / `--package` for `--init` with no terminal; `--init` updates the keys it owns and keeps the rest of the file, because the sections beside them are this machine's own record — and the same sections `--check` names where this machine has recorded nothing in them, so a file that is the shape with nothing under it says so rather than reading as complete |
| [`../.agents/php-bin.sh`](../.agents/php-bin.sh) | the one place that decides which PHP this checkout runs | `sh .agents/php-bin.sh`; `--show`, `--why`, `--report`, `--pin`, `--matrix`, `--root DIR`, `--selftest` |
| [`../.agents/toolchain.json`](../.agents/toolchain.json) | data: every command this package runs, and the pin on each tool | read by `verify.py`'s `toolchain` step; section 13 of `AGENTS.md` is rendered from it |
| [`../.agents/machine.local.json.example`](../.agents/machine.local.json.example) | the committed shape of the machine file above — the only place a machine path is allowed to be written | `render_local.py --init` writes the real file from it |
| [`../.agents/README.md`](../.agents/README.md) | the index of everything loadable in this directory, and how each tool loads it | `verify.py`'s `index` step, in both directions |
| [`../.agents/agents/code-reviewer.md`](../.agents/agents/code-reviewer.md) | the custom agent (`@code-reviewer`) that reviews a change against this package's rules | loaded by the agent tool; `verify.py`'s `index` step |
| [`../.agents/commands/gate.md`](../.agents/commands/gate.md) | the `gate` command: run the package's gate and report what each of the checks said | loaded by the agent tool; `verify.py`'s `index` step |
| [`../.agents/commands/release.md`](../.agents/commands/release.md) | the `release` command: weigh the tree, and cut a version only when asked for one | loaded by the agent tool; `verify.py`'s `index` step |

## The generator and the skills — `.skills/`

`.skills/` is where a skill is **authored**; `.agents/skills/` is the single real target, generated
from it. Nothing under `.agents/skills/` is edited by hand.

| Tool | What it is | How it runs |
|---|---|---|
| [`../.skills/_sync.py`](../.skills/_sync.py) | generates every skill target and every pointer from the sources beside it: `.agents/skills/`, the `GEMINI.md` import block, the Windsurf rules | `python .skills/_sync.py`, then `python .agents/verify.py` |
| [`../.skills/package-commit/skill.yaml`](../.skills/package-commit/skill.yaml) and [`instructions.md`](../.skills/package-commit/instructions.md) | the authored source of the `package-commit` skill — the guarded named-set commit | edit here; `_sync.py` writes the target |
| [`../.skills/package-dev/skill.yaml`](../.skills/package-dev/skill.yaml) and [`instructions.md`](../.skills/package-dev/instructions.md) | the authored source of the `package-dev` skill — this package's daily loop | the same |
| [`../.skills/package-deploy/skill.yaml`](../.skills/package-deploy/skill.yaml) and [`instructions.md`](../.skills/package-deploy/instructions.md) | the authored source of the `package-deploy` skill — weighing and cutting a version, on this side of the remote | the same |
| [`../.skills/package-push/skill.yaml`](../.skills/package-push/skill.yaml) and [`instructions.md`](../.skills/package-push/instructions.md) | the authored source of the `package-push` skill — getting a cut tag to the remote | the same |
| [`../.skills/php-code-analyzer/skill.yaml`](../.skills/php-code-analyzer/skill.yaml) and [`instructions.md`](../.skills/php-code-analyzer/instructions.md) | the authored source of the `php-code-analyzer` skill — reading a path: syntax, types, complexity, modernization, duplicates, tools | the same |
| [`../.skills/README.md`](../.skills/README.md) | how a `skill.yaml` is written, what the generator reads, and what a description may name | read |

## The programs in `bin/`

Every composer script is a thin name for one of these, and the program names no interpreter unless
the platform makes it necessary.

| Tool | What it is | How it runs |
|---|---|---|
| [`../bin/bootstrap.php`](../bin/bootstrap.php) | the one command a fresh clone is set up with: name this machine's PHP, install the dependencies, then verify the tree | `php bin/bootstrap.php` — the first command a person runs, before there is a `vendor/` or a machine file |
| [`../bin/checks.php`](../bin/checks.php) | the gate: eleven checks in one process, one summary and one exit code | `composer checks`; `--staged` (the hook's narrow half), `--require-all` (CI) |
| [`../bin/commit.php`](../bin/commit.php) | the guarded named-set commit: refuses a commit carrying a path it was not told about, a named set whose paths already hold what `HEAD` holds, and a set whose only moved path is the changelog, writes the changelog bullet and stages that bullet alone rather than the file it went into — added to the entry the index already holds, so a note another session left in the same document is not published under it — runs the gate's narrow half, commits exactly the named set | `"$(python .agents/render_local.py --value __PHP_EXE__)" bin/commit.php -m <subject> -- <paths…>`; `--changelog <text>`, `--no-changelog`, `--type`, `--dry-run`, `--list`, `--skip-checks` |
| [`../bin/coverage.php`](../bin/coverage.php) | the coverage floor: every `src/` file at 100.0% and a ratchet per program in `bin/` | `composer test:unit` |
| [`../bin/index.php`](../bin/index.php) | renders the tables of [the guards page](guards.md) from the declarations in the files themselves | `composer index`; `--check` reports what has drifted and writes nothing |
| [`../bin/push.php`](../bin/push.php) | pushes a branch and reads the run it started to a verdict — a push that started no run is refused rather than read as a pass | `composer push`; [the pushing runbook](../PUSHING.md) |
| [`../bin/release.php`](../bin/release.php) | cuts a version from the git tags: weighs the change, promotes the changelog, commits and tags | `composer release`; [the releasing runbook](../RELEASING.md) |
| [`../bin/tool.php`](../bin/tool.php) | runs one tool by the name the manifest gives it, under `PHP_BINARY` | `"$(python .agents/render_local.py --value __PHP_EXE__)" bin/tool.php <name> [args…]`; the `lint`, `refactor` and `test:*` composer names |
| [`../bin/tool-paths.php`](../bin/tool-paths.php) | the manifest itself: every tool this package runs, as a record of `path`, `pin` and `why` | required by `bin/tool.php`, `bin/checks.php` and `bin/coverage.php` — data rather than a program |
| [`../bin/composer-ca.ps1`](../bin/composer-ca.ps1) | runs Composer on Windows through a CA bundle rebuilt from Git's own plus every intercepting root in the Windows trust store, for a machine where a local TLS scanner makes PHP's curl fail where Git succeeds | `pwsh -File bin/composer-ca.ps1 <composer args>` — `install`, `update`, `test`, `release -- --weigh`; `COMPOSER_CAFILE` is set for the run, and `COMPOSER_CA_COMPOSER` overrides the Composer it runs |
| [`../bin/composer-link.cmd`](../bin/composer-link.cmd) | links a package that lives on this machine into a Laravel application and requires it at its dev version, so the application runs the working copy on disk — the CMD port of a published `composer-link` shell function, guard for guard | `bin\composer-link.cmd <path-to-package-directory> [composer require args]`, run **from the application**; `COMPOSER_LINK_PHP`, `COMPOSER_LINK_COMPOSER` and `COMPOSER_LINK_JQ` override what it detects |

| [`../bin/workbench.sh`](../bin/workbench.sh) | a throwaway copy of this checkout with a vendor of its own, so a reading taken on it belongs to the tree it was taken on: the files a commit would carry and never `vendor/`, the clone's `origin` removed, a link among the carried paths refused by name, and the installed vendor read back to prove it resolves inside the copy | `bash bin/workbench.sh [--into DIR] [-- composer install args…]` — `--into` names an empty directory outside this checkout, and the copy's path is the last line it prints |

The last three rows are not reached by a composer name: they are conveniences a maintainer starts
by hand, `.gitattributes` `export-ignore`s all three, and each asks this checkout for the interpreter
and the Composer it runs before falling back to `PATH`.

## The gate, the hook and the runner

| Tool | What it is | How it runs |
|---|---|---|
| [`../.githooks/pre-commit`](../.githooks/pre-commit) | the gate's narrow half: `bin/checks.php --staged`, plus `verify.py --layer` when the commit carries an agentic file — and it skips rather than blocking when there is no PHP or no Python | `git config core.hooksPath .githooks`, then git runs it |
| [`../.github/workflows/main.yml`](../.github/workflows/main.yml) | the gate on the runner: `composer checks -- --require-all` and then `composer test:unit` for the floor, with the PHP version read from `php-bin.sh --matrix` rather than written into the file | GitHub, on a push to `main` or `development`, on a `v*` tag, on a pull request to `main`, monthly, and on demand |

## The composer names

The names below are what a maintainer actually types; each is the program above it. They are listed
as a mapping rather than as rows, because the first cell here is a command rather than a path.

| Name | Runs |
|---|---|
| `composer checks` | `bin/checks.php` |
| `composer index` | `bin/index.php` |
| `composer push` | `bin/push.php` |
| `composer release` | `bin/release.php` |
| `composer lint` | `bin/tool.php pint` |
| `composer refactor` | `bin/tool.php rector` |
| `composer tidy` | `lint`, then `refactor`, then `lint` |
| `composer test:lint` | `bin/tool.php pint --test` |
| `composer test:refacto` | `bin/tool.php rector --dry-run` |
| `composer test:types` | `bin/tool.php phpstan analyse --ansi` |
| `composer test:unit` | `bin/coverage.php` |
| `composer test` | the four `test:*` names, in order |

## Adding one

A tool earns a row by being a file an agent or a maintainer starts. When one is added under
`.agents/`, `.skills/`, `bin/` or `.githooks/`:

1. add its row here, first cell the path;
2. if it decides something the suite could disagree with, it is a guard rather than only a script —
   declare it in its own file and let `composer index` write [the guards page](guards.md) row;
3. if it is a tool on `PATH`, its path and pin belong in [`../bin/tool-paths.php`](../bin/tool-paths.php),
   not here;
4. if an agent tool should load it, add the row to
   [`../.agents/README.md`](../.agents/README.md), which the `index` step enforces.

A row whose path is not there is a reader sent to nothing, which is why the first cell is the path
and why the "How it runs" column names the command rather than describing it.
