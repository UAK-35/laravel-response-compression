# Agentic setup inventory and bug audit

Created: 2026-10-30 (Asia/Karachi). Report: 0001. Status: findings recorded; setup fixes not applied.

## Scope and numbering

The request was to list the agentic setup files, scan them for code bugs and logical incoherences,
and keep both in one report. The primary corpus includes canonical rules, all skill sources and
generated targets, agent and command definitions, editor pointers and templates, the hook, and the
Python and shell programs that maintain them. The supporting inventory identifies the package
tooling, tests and records those files depend on; it is not a claim of an independent audit of every
release algorithm or middleware behavior. Local settings and session notes are listed separately;
their machine paths, project identifiers and permission histories are not copied here. Dependencies,
Git internals, caches, crash dumps and the audit's own ignored scratch fixtures are excluded.

No `docs/reports/` directory or earlier reports existed at the initial inventory. There was therefore
nothing to renumber; this report is the first creation and receives `0001`. Its date uses the user's
timezone. The durable naming and creation-order rule is in `AGENTS.md` section 9. Existing
`docs/investigations/` records are a separate collection and were not renamed.

The only pre-existing tracked modification was `pint.json`. It was left untouched. CodeGraph and
Graphify output were absent; the user chose to skip CodeGraph initialization. No graph was created.

## Findings

Priority P1 means a workflow can silently accept broken setup or select the wrong execution context.
P2 means a reproducible defect or conflicting instruction affects a supported operation. Findings
describe the state read during this audit, before adding the report rule. Suggested corrections are
follow-up work, not changes claimed by this report.

### F01 — P1: Generated skill body changes pass the drift check

Evidence: `.agents/verify.py:416` (`step_skills`) checks the frontmatter name and compares the
metadata's `source_hash` with the source instructions at line 445. It never compares the generated
body with the source or the generator output. Appending a sentence to a fixture's generated
`rc-dev/SKILL.md`, without changing its metadata, returned `[]`. An agent can therefore load altered
instructions while verification says the target was generated from its source. A source manifest
description change is also outside the instructions-only hash. Compare the complete expected
generated content and relevant metadata; plant mutations on both the source and target sides.

### F02 — P1: A crashed settings renderer is reported as clean

Evidence: `.agents/verify.py:888` (`step_tokens`) starts the renderer, then at line 959 reads only
stdout lines beginning with `[FAIL]` or `[NOTE]`. Its exit code and stderr are ignored. A fixture
renderer raising `RuntimeError` returned no token findings (`[]`). A syntax error or import failure
can consequently disable rendered-settings validation while the step reports success. Report failed
process exits and stderr independently of the renderer's structured messages.

### F03 — P1: The verifier chooses PHP independently of the canonical resolver

Evidence: `.agents/verify.py:1378` (`php_binary`) reads the machine record and falls back directly to
`PATH`. `.agents/php-bin.sh:216` instead gives `RESPONSE_COMPRESSION_PHP` first priority and also
supports the Git configuration rung. With an override set in the reproduction process, the verifier
did not return that override. The hook and verification can run under different interpreters, and
a clone configured only through Git may work in the hook and fail verification. Have the verifier
consume the resolver's answer and preserve its origin information.

### F04 — P2: Unknown tokens survive settings rendering

Evidence: `.agents/render_local.py:271` (`substitute`) iterates known values only; `render` at line
400 trusts the resulting `missing` list. A fixture containing an unknown placeholder returned the
placeholder unchanged and an empty missing list. In addition, `.agents/verify.py:888` does not scan
the `.example` templates for unknown tokens; it checks them for valid JSON. A typo in a settings
template can thus be rendered and accepted as a literal path. Validate all tokens in template strings
against the declared token set before writing or comparing the result.

### F05 — P2: OpenCode substitution does not escape JSON string content

Evidence: `manage-config.py:101` (`render`), especially line 125, inserts machine values directly
into JSONC text. A synthetic value made from `scratch`, a backslash and `new` did not survive a JSON
round trip: the backslash-n became a newline. Other Windows paths can produce invalid escapes, and
quotes can break the document. The current initializer normalizes its temporary path, but this
renderer also accepts manually recorded machine values. Escape replacements for their JSON string
context and validate the rendered JSONC before writing it.

### F06 — P2: OpenCode instructs its default agent to read a missing definition

Evidence: `opencode.jsonc.template:22` names `.agents/agents/php-code-analyzer.md`; the backup
template carries the same instruction. That file does not exist. The available analyzer is a skill
at `.agents/skills/php-code-analyzer/SKILL.md`, while the only custom agent definition is
`code-reviewer.md`. The default OpenCode workflow starts with an instruction it cannot fulfill.
Point at the actual skill or deliberately add and index the missing agent. These root templates are
outside `verify.py`'s `POINTERS`/`LAYER` lists, so the passing pointer check does not validate them.

### F07 — P2: Documented executable invocation is invalid in the mandated Windows shell

Evidence: `AGENTS.md` section 2, the rendered command table and several skill bodies put a quoted
command-substitution string directly before a program argument. PowerShell's parser rejected the
`checks-list` example with `Unexpected token 'bin/checks.php'`. PowerShell requires the call operator
before a resolved executable string. The quoted PowerShell wrapper example has the same problem
when copied into PowerShell. The command checker recognizes these strings but does not parse them
for the mandated shell. Provide shell-specific executable forms and validate their syntax.

### F08 — P2: A new skill's dry run omits pointer creation

Evidence: `.skills/_sync.py:259` calls `present_targets()` after writes, including during dry runs.
In a fixture with an authored skill and no generated targets, `sync(True, False)` did not announce
the Windsurf pointer; `sync(False, False)` did create it. The dry run also derives Gemini imports
from the old on-disk target list. Its preview does not describe the operation it previews. Compute
the prospective target set by combining discovered authored skills with existing in-place targets.

### F09 — P2: Supported import opt-outs contradict verification

Evidence: `.skills/_sync.py` reads `sync.gemini.enabled` and `sync.windsurf.enabled`, but
`.agents/verify.py:454` and `:477` require an import and a pointer for every target. With Gemini
disabled in a fixture manifest, generation succeeded and verification complained that `rc-dev` was
not imported. Windsurf has the same unconditional validator, and an old pointer can also remain when
its flag is turned off. Make the generator and validator share the same supported opt-out semantics,
or explicitly reject those options instead of advertising them. Current manifests enable both.

### F10 — P2: Forced initialization can delete an existing machine-record value

Evidence: `.agents/render_local.py:476` (`initialise`) preserves declared non-owned sections only
when their value is a dictionary, or initializes them when absent (`elif held is None`, line 526).
An existing string, list, boolean or number under a declared non-owned section falls through and
disappears. A fixture's `python` string was lost on forced initialization. This contradicts the
promise to keep everything else as it stands. Also, `_read` maps malformed JSON to `{}`, so forcing
initialization of a malformed record overwrites it without reporting the parse failure. Preserve
unowned values verbatim or refuse an invalid shape before writing, and refuse unreadable records.

### F11 — P2: Failed tool startup can count as a successful health probe

Evidence: `.agents/verify.py:1038` (`capture`) drops the process return code. In `step_tools`, the
vendor-tool checks at lines 1331 and 1333 reject only absent or empty output. A failed process
printing a startup error therefore passes that branch and can be recorded as having run. A stubbed
exit-1 process returned its fatal message as ordinary capture output. Keep the exit status, require
successful startup, and distinguish a diagnostic message from a tool's version response. This
finding concerns the health probe; the separate package gate can still catch the broken tool.

### F12 — P2: The push skill bypasses the documented CI verdict workflow

Evidence: `.skills/rc-push/instructions.md:30` defines `push` as a direct Git command with a literal
release branch. `.agents/toolchain.json` defines `push` as `composer push`; `PUSHING.md`'s
"Then read the run" section explains the wrapper's Actions lookup and verdict.
The skill's command does not perform that read and can drift when `.release-lane` changes.
Update the authored skill to use the canonical wrapper, documenting tag delivery explicitly, then
regenerate its target and pointer.

### F13 — P2: Startup instructions and PHP precedence disagree

Evidence: `AGENTS.md:172` requires `BEHAVE.mdc` second and the handoff when present.
`.trae/TRAE.md:26`, `GEMINI.md:5` and `CLAUDE.md` publish different startup lists that omit parts
of that sequence. `AGENTS.md:308` says the hook takes the machine record before the environment,
whereas section 2 and `.agents/php-bin.sh:216` put the environment override first. An agent following
an entry point can omit behavioral or handoff context and receive opposite answers about which PHP
runs. Keep the complete startup order and precedence in the canonical file and have pointers defer
to those sections rather than publishing competing lists.

### F14 — P2: "Fix exactly one finding" invokes a repository-wide rewrite

Evidence: `.skills/php-code-analyzer/instructions.md:62` promises that `analyze-apply <id>` fixes
exactly one numbered finding, then prescribes `composer tidy` for style or modernization findings.
That script runs Pint, Rector and Pint over their configured paths, so unrelated findings can be
rewritten as well. The promise cannot be guaranteed by the prescribed operation. Narrow the tool
scope where supported and review its diff, or explicitly obtain a broader scope before using the
whole-tree rewrite.

### F15 — P2: Runtime scratch allocation conflicts with the repository-only rule

Evidence: `AGENTS.md` section 0 forbids writes outside the repository, and the commit skill says
scratch belongs under the ignored `build/` directory. `.agents/verify.py:1733` and `:1786` call
`tempfile.TemporaryDirectory` without a directory; `.agents/php-bin.sh:331` calls `mktemp -d`
without a repository location. Their default destinations are controlled by the host's temporary
directory settings and are not guaranteed to be inside this checkout. Following the required
selftest can therefore violate the canonical scope rule. Allocate under an explicitly checked
repository-local scratch root, or deliberately revise the rule and its permitted boundary together.
The new temporary-folder token does not itself grant an exception to section 0.

## Verification and limits

- Initial `verify.py --layer`: 11 steps ran, zero findings; three package steps were not selected.
- Initial `_sync.py --dry-run`: six authored skills, six generated targets, no writes proposed.
- Complete generated skill bodies matched generator output for all six skills at inventory time.
  This is a direct comparison made for the audit, separate from the defective drift check in F01.
- Scratch reproductions were executed against imported real functions in `build/agentic-audit/`;
  canonical implementations and the user's existing change were not mutated. They confirmed F01,
  F02, F03, F04, F05, F08, F09 and F10. F11 used a stubbed nonzero subprocess result. F07 used the
  real PowerShell parser. F06 and F12–F15 are anchored source/contract comparisons.
- OpenCode and other editor applications were not launched, and external editor schemas were not
  fetched. Permission ordering comments in the OpenCode templates were not treated as verified
  runtime behavior. No push, commit, release, dependency installation or network lookup was made.
- Python launcher resolution changed during the audit: new `python` invocations could not start,
  the recorded Python executable was unavailable, and `py` reported no installation. The interpreter
  already running verification remained usable and ran the reproductions. This is local environment
  evidence, separate from the portable code defects above.

Final verification: the full `verify.py` run completed successfully: **14 steps, zero findings,
zero steps unselected**, including tool health, the package path guards and the strict package gate.
It reported PHP 8.4.27, Composer 2.10.3 and Python 3.15.0. The layer was also rerun after adding the
report rule: **11 steps, zero findings**. Direct readings of the final documentation tree returned
empty lists for machine paths, repository escapes, broken documentation links and unlinked design
records. All **190 inventory paths** exist and are unique; finding IDs run consecutively from F01 to
F15, and the report filename matches the naming convention. `git diff --check` passed. The full
gate's success does not invalidate the isolated reproductions: these findings identify cases its
current checks do not reject. Coverage was not rerun for this documentation-only change.

## File inventory

Paths below are relative to the repository root. Each path is listed individually. Primary files
were read as text; supporting files establish dependencies and validation scope. Generated skill
bodies were also compared to their source rendering. Ignored local files are not proposed for commit.

### Primary agentic setup (60 files)

- `.agents/README.md`
- `.agents/agents/code-reviewer.md`
- `.agents/commands/gate.md`
- `.agents/commands/release.md`
- `.agents/machine.local.json.example`
- `.agents/php-bin.sh`
- `.agents/render_local.py`
- `.agents/skills/php-code-analyzer/SKILL.md`
- `.agents/skills/php-code-analyzer/_meta.json`
- `.agents/skills/rc-commit/SKILL.md`
- `.agents/skills/rc-commit/_meta.json`
- `.agents/skills/rc-deploy/SKILL.md`
- `.agents/skills/rc-deploy/_meta.json`
- `.agents/skills/rc-dev/SKILL.md`
- `.agents/skills/rc-dev/_meta.json`
- `.agents/skills/rc-investigation/SKILL.md`
- `.agents/skills/rc-investigation/_meta.json`
- `.agents/skills/rc-push/SKILL.md`
- `.agents/skills/rc-push/_meta.json`
- `.agents/toolchain.json`
- `.agents/verify.py`
- `.continue/rules/00-agents.md`
- `.cursor/rules/agents.mdc`
- `.githooks/pre-commit`
- `.github/copilot-instructions.md`
- `.junie/guidelines.md`
- `.kiro/settings/local.json.example`
- `.kiro/steering/package-rules.md`
- `.skills/README.md`
- `.skills/_sync.py`
- `.skills/php-code-analyzer/instructions.md`
- `.skills/php-code-analyzer/skill.yaml`
- `.skills/rc-commit/instructions.md`
- `.skills/rc-commit/skill.yaml`
- `.skills/rc-deploy/instructions.md`
- `.skills/rc-deploy/skill.yaml`
- `.skills/rc-dev/instructions.md`
- `.skills/rc-dev/skill.yaml`
- `.skills/rc-investigation/instructions.md`
- `.skills/rc-investigation/skill.yaml`
- `.skills/rc-push/instructions.md`
- `.skills/rc-push/skill.yaml`
- `.trae/TRAE.md`
- `.trae/rules/project_rules.md`
- `.trae/settings.json.example`
- `.trae/settings.local.json.example`
- `.windsurf/rules/000-agents.windsurf.md`
- `.windsurf/rules/php-code-analyzer.windsurf.md`
- `.windsurf/rules/rc-commit.windsurf.md`
- `.windsurf/rules/rc-deploy.windsurf.md`
- `.windsurf/rules/rc-dev.windsurf.md`
- `.windsurf/rules/rc-investigation.windsurf.md`
- `.windsurf/rules/rc-push.windsurf.md`
- `AGENTS.md`
- `BEHAVE.mdc`
- `CLAUDE.md`
- `GEMINI.md`
- `manage-config.py`
- `opencode.jsonc.template`
- `opencode.v1-backup.jsonc.template`

### Supporting tooling, checks and records (117 files)

- `.editorconfig`
- `.gitattributes`
- `.github/workflows/main.yml`
- `.gitignore`
- `.release-lane`
- `CHANGELOG.md`
- `PUSHING.md`
- `README.md`
- `RELEASING.md`
- `bin/bootstrap.php`
- `bin/checks.php`
- `bin/commit.php`
- `bin/composer-ca.ps1`
- `bin/composer-link.cmd`
- `bin/coverage.php`
- `bin/index.php`
- `bin/push.php`
- `bin/release.php`
- `bin/tool-paths.php`
- `bin/tool.php`
- `bin/workbench.sh`
- `composer.json`
- `docs/commit-environment.md`
- `docs/guards.md`
- `docs/investigations/README.md`
- `docs/investigations/how-the-system-temp-folder-token-was-added.md`
- `docs/investigations/why-opencode-files-fail-machine-path-tests.md`
- `docs/investigations/why-the-staged-check-runs-silent.md`
- `docs/scripts.md`
- `docs/tool-registry.md`
- `handoff/HANDOFF.md`
- `handoff/phantom-eol.sh`
- `handoff/state.sh`
- `phpstan.neon.dist`
- `phpunit.xml.dist`
- `pint.json`
- `rector.php`
- `tests/Support/ChangelogPreamble.php`
- `tests/Support/CoverageEnvironment.php`
- `tests/Support/Docs.php`
- `tests/Support/GuardIndex.php`
- `tests/Support/Handoff.php`
- `tests/Support/HandoffState.php`
- `tests/Support/Ignored.php`
- `tests/Support/IndexNumbers.php`
- `tests/Support/MachinePaths.php`
- `tests/Support/MachineShape.php`
- `tests/Support/MutationHarness.php`
- `tests/Support/Notes.php`
- `tests/Support/PhpBinary.php`
- `tests/Support/Processes.php`
- `tests/Support/ReleaseLane.php`
- `tests/Support/ReleaseRepo.php`
- `tests/Support/ReleaseRun.php`
- `tests/Support/RepoEscapes.php`
- `tests/Support/ScratchCopies.php`
- `tests/Support/ScriptCoverage.php`
- `tests/Support/TestMap.php`
- `tests/Support/ToolPaths.php`
- `tests/Support/Workers.php`
- `tests/Support/collect-coverage.php`
- `tests/Unit/Bootstrap/BootstrapTest.php`
- `tests/Unit/Commit/CommitTest.php`
- `tests/Unit/Docs/ChangelogPreambleTest.php`
- `tests/Unit/Docs/DocsLinksTest.php`
- `tests/Unit/Docs/GuardIndexTest.php`
- `tests/Unit/Docs/IndexNumbersTest.php`
- `tests/Unit/Docs/ReleaseLaneTest.php`
- `tests/Unit/Gate/ChecksTest.php`
- `tests/Unit/Gate/CoverageTest.php`
- `tests/Unit/Gate/ToolTest.php`
- `tests/Unit/Release/BranchAliasTest.php`
- `tests/Unit/Release/CiTest.php`
- `tests/Unit/Release/InventoryTest.php`
- `tests/Unit/Release/NotesTest.php`
- `tests/Unit/Release/PolicyTest.php`
- `tests/Unit/Release/PrereleaseLineTest.php`
- `tests/Unit/Release/PrereleaseSuffixTest.php`
- `tests/Unit/Release/PrereleaseTest.php`
- `tests/Unit/Release/PushTest.php`
- `tests/Unit/Release/RailsTest.php`
- `tests/Unit/Release/ReleaseApplyTest.php`
- `tests/Unit/Release/ReleaseLinksTest.php`
- `tests/Unit/Release/ReleasePlanTest.php`
- `tests/Unit/Release/SurfaceTest.php`
- `tests/Unit/Support/CoverageEnvironmentTest.php`
- `tests/Unit/Support/HandoffStateTest.php`
- `tests/Unit/Support/HandoffTest.php`
- `tests/Unit/Support/IgnoredTest.php`
- `tests/Unit/Support/MachinePathsTest.php`
- `tests/Unit/Support/MachineShapeTest.php`
- `tests/Unit/Support/MutationHarness/ChangelogPreambleTest.php`
- `tests/Unit/Support/MutationHarness/Controls/InversionsTest.php`
- `tests/Unit/Support/MutationHarness/Controls/ProgramsTest.php`
- `tests/Unit/Support/MutationHarness/Controls/SpillTest.php`
- `tests/Unit/Support/MutationHarness/DocsLinksTest.php`
- `tests/Unit/Support/MutationHarness/GuardIndexTest.php`
- `tests/Unit/Support/MutationHarness/HandoffStateTest.php`
- `tests/Unit/Support/MutationHarness/HandoffTest.php`
- `tests/Unit/Support/MutationHarness/IndexNumbersTest.php`
- `tests/Unit/Support/MutationHarness/MachinePathsTest.php`
- `tests/Unit/Support/MutationHarness/MachineShapeTest.php`
- `tests/Unit/Support/MutationHarness/RailsTest.php`
- `tests/Unit/Support/MutationHarness/ReleaseLaneTest.php`
- `tests/Unit/Support/MutationHarness/RepoEscapesTest.php`
- `tests/Unit/Support/MutationHarness/ToolPathsTest.php`
- `tests/Unit/Support/MutationHarnessTest.php`
- `tests/Unit/Support/NotesTest.php`
- `tests/Unit/Support/PhpBinaryTest.php`
- `tests/Unit/Support/ProcessesTest.php`
- `tests/Unit/Support/RepoEscapesTest.php`
- `tests/Unit/Support/ScratchCopiesTest.php`
- `tests/Unit/Support/ScriptCoverageTest.php`
- `tests/Unit/Support/TestMapTest.php`
- `tests/Unit/Support/ToolPathsTest.php`
- `tests/Unit/Support/WorkersTest.php`
- `tests/Unit/Workbench/WorkbenchTest.php`

### Ignored local setup and session records (13 files)

- `.agents/machine.local.json`
- `.freebuff/.gitignore`
- `.freebuff/project-id`
- `.kiro/settings/local.json`
- `.quarantine/README.md`
- `.trae/settings.json`
- `.trae/settings.local.json`
- `oc-session-01-issues-faced.md`
- `oc-session-01-summarized.md`
- `oc-session-01-workflow.md`
- `oc-session-01.md`
- `opencode.jsonc`
- `permissions-01.md`

The report itself is `docs/reports/0001-2026-10-30_rpt-agentic-setup-audit.md`; the report naming rule is in `AGENTS.md` section 9.
