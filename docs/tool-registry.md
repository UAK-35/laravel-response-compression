# Which tools does this package run, where do they live, and who calls them?

**Because a tool is a fact about a caller, and a file nothing calls is a path nothing resolves.**
This package writes guards about the same mistake in several places — a tool path written twice,
a list of files drifting from the directory it names, an interpreter decided by four things at
once — and every one of those is a tool that had stopped being called by the thing it was written
for. So the registry below has four columns and the last one is the point: what a tool *is* comes
from what asks it to run.

## What this document is, and what it deliberately is not

It is a registry of the **agentic tools**: the rules a session loads, the layer in `.agents/` that
keeps those rules consistent, the programs in `bin/` that gate, index, measure, release and push,
the hook that runs them before a commit, the workflow that runs them on a runner, and the two
runbooks a person follows afterwards. Each row says where the tool lives, what it does, and which
file calls it.

It is **not** a second copy of two lists that already have an owner:

- **The guards.** Every guard this package keeps, what each one catches, which files in
  `tests/Support/` it is built from, and which were considered and refused, are indexed in
  [docs/guards.md](guards.md). That page is read as a claim about a directory by
  [`../tests/Unit/Docs/GuardIndexTest.php`](../tests/Unit/Docs/GuardIndexTest.php), so a guard
  cannot be added without a row there. Restating those rows here would be the copy this package
  writes guards about, and it would rot first.
- **The tool paths and pins.** Which file runs `pint`, which version this package pins it at, and
  what it is for are written once, in
  [`../bin/tool-paths.php`](../bin/tool-paths.php), and read from there by every caller. A path
  repeated here would be the `vendor/bin` failure one more time.

What is left is the layer between those two: the things an agent or a maintainer starts, and the
chain by which one starts the next.

**This page is the registry; [the scripts list](scripts.md) is the list.** The same tools appear
there as a flat inventory — one row per script, with the command that starts it, and no argument
beside it — so a reader who wants to know whether a tool exists, and what to type, does not have to
read a four-column account to find out. This page keeps the two things an inventory cannot carry:
what each tool is *in the chain*, and **who calls it** — which is what tells a reader whether
touching it is safe.

## The rules and the agentic layer

| Tool | Where it lives | What it does | Who calls it |
|---|---|---|---|
| [`AGENTS.md`](../AGENTS.md) | repository root | the canonical rules — sections 0–14 — and the one place a rule is edited | read first by every agent session; `CLAUDE.md`, `GEMINI.md`, `.trae/TRAE.md`, `.cursor/`, `.continue/`, `.junie/`, `.kiro/`, `.windsurf/` and `.github/copilot-instructions.md` are pointers to it, and `verify.py`'s `canonical` step refuses a pointer that restates a section |
| [`../.agents/README.md`](../.agents/README.md) | `.agents/` | the index of everything loadable here — every skill, custom agent and command has a row | `verify.py`'s `index` step reads it in both directions: every skill, agent and command is present, and every row names something that exists |
| `CLAUDE.md`, `GEMINI.md`, `.trae/TRAE.md`, `.cursor/`, `.continue/`, `.junie/`, `.kiro/`, `.windsurf/`, `.github/copilot-instructions.md` | repository root and each tool's dot-directory | one pointer file per tool: it names `AGENTS.md` and restates nothing | the tool's own loader; `verify.py`'s `pointers` step |
| [`../.agents/toolchain.json`](../.agents/toolchain.json) | `.agents/` | every command this package runs, and the pin on each tool | section 13 of `AGENTS.md` is rendered from it by `python .agents/verify.py --fix`, and `verify.py`'s `toolchain` and `descriptions` steps read it |
| [`../.agents/render_local.py`](../.agents/render_local.py) | `.agents/` | owns `.agents/machine.local.json`: `--init` asks for this machine's paths, `--check` reports unresolved tokens and the declared sections this machine has recorded nothing in, `--value` answers one for prose or a shell | `.agents/php-bin.sh` reads a token through it, the pre-commit hook and the commands in `AGENTS.md` call it, and `verify.py`'s `tokens` step asks it `--check` |
| [`../.agents/php-bin.sh`](../.agents/php-bin.sh) | `.agents/` | the one place that decides which PHP this checkout runs — four rungs, with `--show`, `--why`, `--report`, `--pin`, `--matrix` and `--selftest` | the pre-commit hook, [`../.github/workflows/main.yml`](../.github/workflows/main.yml), [`../bin/tool.php`](../bin/tool.php), [`../bin/coverage.php`](../bin/coverage.php) and `tests/Support/PhpBinary.php` all ask it; `verify.py`'s `interpreter` step refuses a second copy |
| [`../.agents/verify.py`](../.agents/verify.py) | `.agents/` | the agentic check: fourteen steps in two halves — `tools` runs programs, the other thirteen read files — with `--layer`, `--package`, `--only=STEP`, `--fix` and `--selftest`; its `toolchain` step is also where a command that restates an interpreter is refused anywhere in the repository, by the rule for the platform the check is running on; the `tools` step also collects a health table - tool, version found, pin, and whether it ran - that every run prints, so a red run names the part of the machine that moved | the pre-commit hook runs `--layer` when an agentic file is staged; a maintainer runs the whole thing; it is deliberately not part of `composer checks` |
| [`../.agents/machine.local.json.example`](../.agents/machine.local.json.example) | `.agents/` | the committed shape of the machine file the row above owns — the one place a machine path is allowed to be written, since the real file is gitignored | read by `.agents/render_local.py --init`, which writes the real one from it; `verify.py`'s `tokens` step reads the renderer rather than repeating the shape |
| [`../.skills/_sync.py`](../.skills/_sync.py) | `.skills/` | generates every skill target and pointer from the sources in `.skills/` — `.agents/skills/`, the `GEMINI.md` import block, the Windsurf rules | run by hand; `verify.py`'s `skills`, `gemini` and `windsurf` steps assert its output |
| [`../.skills/package-commit/skill.yaml`](../.skills/package-commit/skill.yaml), [`../.skills/package-deploy/skill.yaml`](../.skills/package-deploy/skill.yaml), [`../.skills/package-dev/skill.yaml`](../.skills/package-dev/skill.yaml), [`../.skills/package-push/skill.yaml`](../.skills/package-push/skill.yaml), [`../.skills/php-code-analyzer/skill.yaml`](../.skills/php-code-analyzer/skill.yaml) and the `instructions.md` beside each | `.skills/` | the authored sources of the five skills — the one place a skill's body, its description and its command list are written | edited by hand, then generated by `_sync.py` into the targets below; `verify.py`'s `descriptions` step spells a description against `.agents/vocabulary.txt` and its `index` step refuses a skill with no row in `.agents/README.md` |
| `../.agents/skills/<name>/SKILL.md` and `_meta.json` | `.agents/skills/` | the single real skill target — `package-commit`, `package-deploy`, `package-dev`, `package-push` and `php-code-analyzer` — generated from `.skills/`, never hand-edited | loaded by the agent tool; `verify.py`'s `skills` step checks each target against the source beside it |
| [`../.agents/agents/code-reviewer.md`](../.agents/agents/code-reviewer.md) | `.agents/agents/` | the custom agent (`@code-reviewer`) that reviews a change against *this* package's rules rather than generic advice | loaded by the agent tool; `verify.py`'s `index` step |
| [`../.agents/commands/gate.md`](../.agents/commands/gate.md), [`../.agents/commands/release.md`](../.agents/commands/release.md) | `.agents/commands/` | the two command definitions — run the gate and read it, and weigh or cut a version | loaded by the agent tool; `verify.py`'s `index` step |

## The programs in `bin/`, and the hook before a commit

Every composer script in [`../composer.json`](../composer.json) is a thin name for one of these:
a maintainer reaches a program through `composer <script>`, and the program names no interpreter
unless the platform makes it necessary.

| Tool | Where it lives | What it does | Who calls it |
|---|---|---|---|
| [`../bin/bootstrap.php`](../bin/bootstrap.php) | `bin/` | the one command a fresh clone is set up with: name this machine's PHP (through `.agents/render_local.py --init`), install the dependencies, then run the whole verification — each step ending the run when it fails, and the interpreter it names being the one running it | nothing calls it: it is the first command a person runs, before there is a `vendor/` or a machine file — which is why its line in `.agents/toolchain.json` is the one that names a bare `php`, and why it is `export-ignore`d; `tests/Unit/Bootstrap/BootstrapTest.php` drives it in an empty checkout |
| [`../bin/checks.php`](../bin/checks.php) | `bin/` | the gate: eleven checks in one process — syntax, an AST parse, the composer schema, the platform requirements, the toolchain probe, the agentic layer, the workflow YAML, PHPStan, Pint, Rector and Pest — one summary and one exit code | `composer checks`; CI runs it `--require-all`; [`../.githooks/pre-commit`](../.githooks/pre-commit) runs it `--staged`, which narrows to syntax, Pint, and the tests the staged files are covered by — `tests/Support/TestMap.php` answers that: the docs-link, config-claim and release-lane tests for a document or the config, and the suite that covers a source file for the rest; `bin/release.php` refuses to cut behind a red one; `tests/Unit/Gate/ChecksTest.php` drives it |
| [`../bin/commit.php`](../bin/commit.php) | `bin/` | the guarded named-set commit: reads the index and refuses a commit that carries a path it was not told about, refuses a named set whose every path already holds what `HEAD` holds — before the bullet is written, so a decline leaves the changelog as it found it — writes the changelog bullet under `## Unreleased` and stages that bullet alone — as the entry the index already holds with it added, never as the file, so a note another session left in the same document is not carried by this commit — runs `bin/checks.php --staged`, and commits exactly the named set | the `package-commit` skill authors the workflow; `tests/Unit/Commit/CommitTest.php` drives the program |
| [`../bin/tool.php`](../bin/tool.php) | `bin/` | runs one tool by the name the manifest gives it, under `PHP_BINARY`, says so when that is not the interpreter this machine names, and sizes a pest `--parallel` pool that was asked for without one — the size read from `tests/Support/Workers.php`, which is where `bin/checks.php` and `bin/coverage.php` read it too, because paratest's default is one worker per core and a worker of this suite is several processes, which is a start the machine refuses — while a caller who named a pool keeps it | the `composer.json` scripts `refactor`, `lint`, `test:refacto`, `test:lint` and `test:types`; `bin/checks.php`; `verify.py`'s `tools` step |
| [`../bin/tool-paths.php`](../bin/tool-paths.php) | `bin/` | the manifest: every tool this package runs, as a record of `path`, `pin` and `why` | required by `bin/tool.php`, `bin/checks.php` and `bin/coverage.php`; read as data by `tests/Support/ToolPaths.php`, which is where a second statement of any of it is refused |
| [`../bin/index.php`](../bin/index.php) | `bin/` | renders the two lists of files in `docs/guards.md` from `tests/Support/`; `--check` reports what has drifted and writes nothing | `composer index`; `tests/Unit/Docs/GuardIndexTest.php` asserts the `--check` reading and that the writing form repairs only those two lists |
| [`../bin/coverage.php`](../bin/coverage.php) | `bin/` | the coverage floor: every `src/` file at 100.0% and a ratchet per program in `bin/`, collected from the child processes the suite spawns and mapped back onto the files they were copied from | `composer test:unit`; the second step of the CI job; `tests/Unit/Gate/CoverageTest.php` |
| [`../bin/release.php`](../bin/release.php) | `bin/` | cuts a version from the git tags: weighs the change against the policy, promotes the changelog, commits and tags — and refuses a cut from the wrong branch, over a red gate, or over a commit the remote has not seen | `composer release`; [`../RELEASING.md`](../RELEASING.md); `tests/Unit/Release/*` |
| [`../bin/push.php`](../bin/push.php) | `bin/` | pushes a branch and then reads the GitHub Actions run it started to a verdict: only `success` exits 0, and a push that started no run is refused rather than read as a pass | `composer push`; [`../PUSHING.md`](../PUSHING.md); `tests/Unit/Release/PushTest.php` |
| [`../bin/composer-ca.ps1`](../bin/composer-ca.ps1) | `bin/` | runs Composer on Windows through a CA bundle it rebuilds from Git's own plus every intercepting root in the Windows trust store, and then leaves the run alone: nothing about this package's platform requirements is ignored, because a PHP without `ext-zlib`, `ext-brotli` or `ext-zstd` is a build problem the gate's `platform` check reports | nothing calls it: a person runs it from a Windows checkout whose TLS is being intercepted, which is why it is `export-ignore`d — the bundle it builds is written to `%TEMP%` and named nowhere in this repository |
| [`../bin/composer-link.cmd`](../bin/composer-link.cmd) | `bin/` | links a package that lives on this machine into a Laravel application and requires it at its dev version, so the application runs the working copy on disk — the CMD port of a published `composer-link` shell function, with its six guards, its `call` rule for shims, and the path normalised to forward slashes so the JSON payload survives CMD's quoting pass | nothing calls it: a person runs it from the application, which is why it is `export-ignore`d — the interpreter and the Composer it uses are asked of the checkout it lives in (`.agents/render_local.py --value __PHP_EXE__`, then the `composer.phar` beside `composer.json`) before `PATH` is believed |
| [`../bin/workbench.sh`](../bin/workbench.sh) | `bin/` | builds a throwaway copy of this checkout with a vendor of its own: the files a commit would carry and never `vendor/`, a clone with no `origin` and no hardlinked object, a link among the carried paths refused before anything is created, and the installed vendor read back so a copy whose `vendor/` resolves outside the copy is refused rather than handed over as an experiment's tree | nothing calls it: a person runs it before an experiment whose reading has to belong to the tree it was taken on, which is why it is `export-ignore`d — the interpreter is not decided in it (`.agents/php-bin.sh --report` answers that), and the Composer it installs with is the `composer.phar` it carried, then `PATH` |
| [`../.githooks/pre-commit`](../.githooks/pre-commit) | `.githooks/` | the gate's narrow half: `bin/checks.php --staged` under the interpreter `.agents/php-bin.sh` resolves, plus `verify.py --layer` when the commit carries an agentic file — and it skips rather than blocking when there is no PHP or no Python | git, once `git config core.hooksPath .githooks` has been run in the clone |

## The runner, and the two runbooks

| Tool | Where it lives | What it does | Who calls it |
|---|---|---|---|
| [`../.release-lane`](../.release-lane) | repository root | one line holding the branch releases are cut from — the single statement of it, since three other copies had drifted to `main` | read by [`../bin/release.php`](../bin/release.php) as the branch it releases from, by [`../.github/workflows/main.yml`](../.github/workflows/main.yml) to require that the branch its trigger gates is the one a version comes from, and by the release-lane reading in [`../tests/Unit/Docs/ReleaseLaneTest.php`](../tests/Unit/Docs/ReleaseLaneTest.php) |
| [`../.github/workflows/main.yml`](../.github/workflows/main.yml) | `.github/workflows/` | the gate on the runner: `composer checks -- --require-all` and then `composer test:unit` for the floor, with the PHP version read from `php-bin.sh --matrix` rather than written into the file; a scheduled run loosens the dev pins to ask what the newest tools would do | GitHub, on a push to `main` or `development`, on a `v*` tag, on a pull request to `main`, monthly, and on demand |
| [`../RELEASING.md`](../RELEASING.md) | repository root | what a version *is*: the numbering, the prerelease lanes, what the release commit carries, and how to do it by hand | read by whoever cuts a version and by `bin/release.php`; `export-ignore`, so an installed package never receives it |
| [`../PUSHING.md`](../PUSHING.md) | repository root | where a tag goes, what authenticates, and the evidence table for the remote — re-verified claim by claim | read by whoever pushes and by `bin/push.php`; `export-ignore` |

## How the pieces call each other

Read the four columns above as edges and the chain is one line each time:

- **A session** loads `AGENTS.md`, then `.agents/README.md`, then whichever skill in
  `.agents/skills/` the work needs. Nothing in that chain is duplicated: the index names the skill,
  the skill names its commands, and `.agents/toolchain.json` is where a command is defined.
- **A commit** runs `.githooks/pre-commit`, which asks `.agents/php-bin.sh` for an interpreter,
  runs `bin/checks.php --staged` on the files the commit carries, and runs `verify.py --layer`
  when one of them is agentic. A finding stops the commit.
- **The gate** is `bin/checks.php`, which runs its tools through `bin/tool.php`, which looks each
  path up in `bin/tool-paths.php`. CI runs the same program with `--require-all`, so a tool that
  did not install is a hole in the build rather than a fact about the machine.
- **The floor** is `bin/coverage.php`, which is why the gate does not run Pest under a coverage
  driver: a machine without one must still be able to run the gate.
- **A push** is `bin/push.php`, which starts the workflow and then reads the run it started; a
  **release** is `bin/release.php`, which refuses to cut unless the commit it is tagging is one the
  remote has built. The two are halves of one promise: a version is a tag, and the tag is behind a
  green run.
- **The agentic check** is `verify.py`, and it is the only one of these that is about the *tools*
  rather than about the code: it asks every tool to start, reads the layer against itself, and
  delegates the two path guards and the gate to the programs that own them rather than repeating
  them.

When a tool is added or moves, the row here follows the same rule as everything else in this
package: put the path in the one file that owns it — `bin/tool-paths.php` for a tool the package
runs, `.agents/toolchain.json` for a command, `.agents/README.md` for something loadable — and add
the sentence here, rather than letting a second copy of the answer appear.
