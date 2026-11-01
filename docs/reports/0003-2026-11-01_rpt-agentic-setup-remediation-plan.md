# Agentic setup remediation plan

Created: 2026-11-01 21:34 (Asia/Karachi, UTC+05:00). Updated: 2026-11-02 (Asia/Karachi, UTC+05:00).
Report: 0003. Status: plan recorded against the tree as it stood on 2026-11-01; all fifteen findings
closed since by the six batches below, each row of the State column naming the commit that closed it.

## What this plan is

`docs/reports/0001-2026-10-30_rpt-agentic-setup-audit.md` recorded fifteen findings against the
agentic layer — the rules, the skills and their generated targets, the renderers, the resolver and the
verifier — and said plainly that "setup fixes not applied". This document turns those fifteen into an
ordered body of work: what changes, in which file, what proves the change, and what the repository
requires beside it before the commit is allowed to land.

It fixed nothing when it was written. Every finding it names was still open on 2026-11-01, and the
plan's own value is that it was written against the tree as it stood rather than against the report's
memory of it.

All six batches have since landed, and the plan is left as the record of what they were told to do
rather than rewritten to agree with what they did. The table under *State of the findings today*
carries the outcome: one row per finding, the batch that closed it, and the commit.

## Numbering and inventory

`docs/reports/` held two reports before this one: `0001-2026-10-30_rpt-agentic-setup-audit.md` and
`0002-2026-10-31_rpt-config-src-tests-bug-audit.md`. Both carry a recorded creation date in their
first line, they are three days and one day old respectively, and the dates order them without a tie,
so no renumbering was needed and this document appends as `0003`. The date in the filename is the
creation date above; editing this plan later does not change it.

`AGENTS.md` section 9's rule is the one applied: inventory, order by recorded creation time, number
consecutively from `0001`, preserve date and slug. Evidence used: the recorded dates inside the two
existing reports, not filesystem timestamps — the checkout's file times describe when the tree was
written here, which for a staged file is not the same claim as authorship.

The audience of this plan is different from report 0001's, which is why it is a separate document.
That report answers "what is wrong"; this one answers "in what order, judged by what, and what does
each step owe the repository". A reader who wants the evidence behind a finding should read 0001; this
document repeats a finding only far enough to place it in a batch.

## State of the findings today

Every cited site was re-read on 2026-11-01 for this plan. All fifteen findings were present in the code
as report 0001 described them; none was fixed, by the commits since or by the uncommitted work in the
tree. Two things are worth saying about the reading rather than leaving to assumption:

- **Not re-reproduced.** This plan re-read the source. The reproductions that confirm F01, F02, F03,
  F04, F05, F08, F09 and F10 remain report 0001's, and F11's stubbed exit and F07's PowerShell parse
  remain its evidence too. A plan is not the place to plant the faults again, and a claim that they
  were planted here would be one this document cannot support.
- **Line numbers have moved** since the audit, because `.agents/verify.py` grew (the `.codex` pointer
  and layer entries among the growth). The plan therefore names functions — `step_skills`,
  `step_tokens`, `php_binary`, `capture`, `step_tools` — rather than lines that are already stale.

**Read again on 2026-11-02.** All fifteen are closed. The six batches were implemented and landed as
`2392dcc` (1), `278eb40` (2), `4a21bc6` (3), `157582f` (4), `9d73763` (5) and `a59c95f` (6), and the
`State` column records which one closed each finding. The `Finding` and `Site today` columns are left
exactly as they were written: they are where each finding was found and what the code said there, so a
row mentioning `step_skills()` comparing `source_hash` only, or `capture()` dropping the return code,
describes the site *at the audit* - the batch sections and the `CHANGELOG.md` bullets say what replaced
it. One column cannot hold both moments without becoming a claim about neither.

| Finding | Site today | State |
|---|---|---|
| F01 | `.agents/verify.py` `step_skills()` compares `source_hash` only | closed - batch 1, `2392dcc` |
| F02 | `.agents/verify.py` `step_tokens()` reads stdout prefixes; exit code and stderr unread | closed - batch 3, `4a21bc6` |
| F03 | `.agents/verify.py` `php_binary()`: machine record, then `PATH`; no resolver, no override | closed - batch 1, `2392dcc` |
| F04 | `.agents/render_local.py` `substitute()` walks known tokens only; templates unread for unknowns | closed - batch 4, `157582f` |
| F05 | `manage-config.py` `render()` inserts machine values into JSONC unescaped | closed - batch 4, `157582f` |
| F06 | `opencode.jsonc.template` names `.agents/agents/php-code-analyzer.md`; only `code-reviewer.md` exists | closed - batch 5, `9d73763` |
| F07 | `AGENTS.md` section 13's rendered table and `.agents/toolchain.json` carry a bash-only form | closed - batch 5, `9d73763` |
| F08 | `.skills/_sync.py` `sync()` calls `present_targets()` after writes, dry run included | closed - batch 2, `278eb40` |
| F09 | `.skills/_sync.py` honours `sync.gemini/windsurf.enabled`; `.agents/verify.py` does not | closed - batch 2, `278eb40` |
| F10 | `.agents/render_local.py` `initialise()` keeps an unowned section only when it is a dict | closed - batch 4, `157582f` |
| F11 | `.agents/verify.py` `capture()` drops the return code; `step_tools` accepts any non-empty output | closed - batch 3, `4a21bc6` |
| F12 | `.skills/rc-push/instructions.md` `push` is a literal `git push origin development` | closed - batch 5, `9d73763` |
| F13 | `AGENTS.md` lines 174–181 start a six-item boot order; line 308 puts the machine record before the override | closed - batch 1, `2392dcc` |
| F14 | `.skills/php-code-analyzer/instructions.md` `analyze-apply` promises one finding, prescribes `composer tidy` | closed - batch 5, `9d73763` |
| F15 | `.agents/verify.py` `tempfile.TemporaryDirectory()` without `dir=`; `.agents/php-bin.sh` bare `mktemp -d` | closed - batch 6, `a59c95f` |

## The batches

Six batches. Each is one commit — named paths, its own `CHANGELOG.md` bullet under `## Unreleased`, and
its reading proven to fail without the fix. The order is an argument about dependencies, not severity:
a later batch's own verification is worth something only because an earlier batch made that reading
trustworthy.

### Batch 1 — One interpreter, one startup order (F03, F13)

The verifier and the hook must answer the same question the same way before anything else here is
judged by a run, because every later batch is verified by a program that starts an interpreter.

- **F03.** `.agents/verify.py` `php_binary()` stops deciding. It asks `sh .agents/php-bin.sh --report`,
  reads the rung and the value, and keeps both, so a run can say *which* rung answered — the shape
  `Tests\Support\PhpBinary` already reads. `PATH` remains a last resort only for a tree with no
  resolver at all, which is a tree this package does not ship. A machine whose resolver exits 1 is a
  finding naming the two lines that would give it an interpreter, not a silent `php`.
- **F13.** The startup list and the precedence live in `AGENTS.md` sections 3 and 5 only, and every
  pointer defers to them instead of publishing a rival list: `CLAUDE.md`, `GEMINI.md`, `.trae/TRAE.md`,
  `.kiro/steering/package-rules.md`, `.cursor/rules/agents.mdc`, `.continue/rules/00-agents.md`,
  `.windsurf/rules/000-agents.windsurf.md`, `.github/copilot-instructions.md` and `.codex/README.md`.
  Section 5's hook paragraph is corrected to the order section 2 and `.agents/php-bin.sh` already
  state — the environment override first — so the two sections stop contradicting each other.

**Proven by:** `Tests\Support\PhpBinary`'s reading over a fixture with `$RESPONSE_COMPRESSION_PHP` set
and a different machine record: the answer must be the override, and the verifier must report the same
one. The pointer check already fails a pointer that does not name `AGENTS.md`; what this batch adds is
that no pointer restates the order — a check over the pointer list for a competing numbered sequence,
or the rule moved into `.agents/verify.py`'s pointer step.

**Owed:** `python .agents/verify.py --layer` green, and `Tests\Support\PhpBinary`'s tests still pass —
the resolver did not change, only who asks it.

### Batch 2 — The generator is the authority on its own output (F01, F08, F09)

F01, F08 and F09 are one defect wearing three hats: `_sync.py` knows what it generated, and neither
program can be asked whether a target is current. The plan's recommended shape is to give the
generator a reading of its own rather than to teach the verifier to re-render.

- **F01.** `.skills/_sync.py` gains `--check`: no writes, every authored skill compared against its
  `SKILL.md`, `_meta.json` and frontmatter name, every pointer and Gemini import compared against the
  set the run would write, with `[FAIL]` / `[NOTE]` lines like `render_local.py --check`. It compares
  **complete expected content**, not `source_hash`, so a hand-edited body or a manifest description
  change is caught.
- **F08.** The dry run computes its prospective target set as discovered authored skills **plus**
  existing in-place targets, before the pointer and import work, instead of reading the directory
  after writes it did not make. `--dry-run` then describes the operation it previews.
- **F09.** The `sync.gemini.enabled` / `sync.windsurf.enabled` flags are read once, by `_sync.py`, and
  the verifier consumes its answer. Either the flags are honoured end to end — an opted-out target
  gets no import and no pointer, and a pointer left behind is reported as stale — or the options are
  refused by name as unsupported. The repository must pick one; advertising a flag the checker ignores
  is the state the audit found.

**Proven by:** `.agents/verify.py` `step_skills()` delegating to `python .skills/_sync.py --check` and
reading its exit code *and* stderr, not only its `[FAIL]` lines — the delegation is only safe once
Batch 3's rule about process outcomes is in place, or this batch adopts that rule for its own call.
Plants: a body sentence appended to a fixture target; a manifest description changed; a Gemini-disabled
fixture; a new authored skill with no targets, in a dry run.

**Owed:** a mutation-harness hold that plants the body edit and requires the finding, so the reading
cannot go quiet; `python .skills/_sync.py` regenerated; `CHANGELOG.md` naming the delegation.

### Batch 3 — A reading that cannot fail is not a reading (F02, F11)

Both findings are the same habit: a program's exit status is thrown away and its output read as though
it were an answer.

- **F02.** `step_tokens()` judges the renderer's process: a non-zero exit is a finding with the exit
  code and stderr attached, independently of the `[FAIL]` / `[NOTE]` lines it parses. A renderer that
  crashes must not read as a clean run — the current shape makes a syntax error and an import failure
  look identical to "nothing to report".
- **F11.** `capture()` returns the return code beside the output — a small frozen record, or a tuple,
  rather than a bare `str | None`. `step_tools` requires a successful start before recording a tool as
  having run: `returncode != 0` with output is a finding that quotes the first line of that output, and
  the health table's "ran" column follows the return code rather than non-emptiness.

**Proven by:** fixture renderer raising `RuntimeError` → one finding naming the crash; stubbed tool
exiting 1 with a fatal line on stdout → a finding, not a version. Both are cheap tests and neither
needs the network. `bin/checks.php` remains the backstop for a broken tool; this is the health probe
telling the truth, which is a different claim.

**Owed:** `python .agents/verify.py --only=tools` and `--only=tokens` green on the real tree, with the
notes it prints unchanged for a machine that has no vendor or no record.

### Batch 4 — Renderers refuse what they cannot render (F04, F05, F10)

The settings path is the one place a typo becomes a path a tool reads.

- **F04.** `render_local.py` validates the tokens a template actually names against the declared set
  before substituting, so an unknown placeholder is refused by name instead of rendered as a literal.
  `verify.py`'s `.example` scan reads the templates for both problems it can see without a machine
  record: invalid JSON, and a token nothing knows.
- **F05.** `manage-config.py` escapes each machine value for its JSON string context before insertion,
  and validates the rendered document before writing it. The backslash case is the evidence: a value
  holding `\n` became a newline, which is a machine path silently changed rather than a crash.
- **F10.** `initialise()` preserves every non-owned section as it stands — a string, list, boolean or
  number under a declared section is kept verbatim rather than dropped, because "keeps the rest" is the
  promise `--force` makes when it asks to re-point a machine. A malformed record is refused before
  anything is written; `_read`'s mapping of a parse failure to `{}` must not become an overwrite.

**Proven by:** `render_local.py --check` and the settings drift test over fixtures — an unknown token,
a forced init over a record with a scalar in a declared section, a malformed record; `manage-config.py`
against a synthetic value with a backslash, a quote and a newline, then a JSON round trip.

**Owed:** `python .agents/render_local.py --check` clean on this checkout, and the `[NOTE]` half (how
much of the record the machine has written) still reported rather than swallowed.

### Batch 5 — Instructions that name a thing that is not there (F06, F07, F12, F14)

The layer's prose is executable in the sense that matters: an agent follows it. These four tell it to
do something the repository does not support.

- **F06.** Decide, then write it once: either `.agents/agents/php-code-analyzer.md` is authored and
  indexed in `.agents/README.md`, or `opencode.jsonc.template` and its v1 backup point at
  `.agents/skills/php-code-analyzer/SKILL.md`, which exists. Recommendation: point at the skill —
  the analyzer's content is a skill, and a second copy as an agent is the duplication the layer exists
  to prevent. `code-reviewer.md` stays the only custom agent.
- **F07.** The command table carries the form for each mandated shell, and `AGENTS.md` section 2 says
  which is which: `"$(python .agents/render_local.py --value __PHP_EXE__)" bin/checks.php` for `bash`,
  `& (python .agents/render_local.py --value __PHP_EXE__) bin/checks.php` for PowerShell 7, where the
  call operator is what makes a resolved executable a command. The table is rendered from
  `.agents/toolchain.json`, so the manifest changes and `python .agents/verify.py --fix` writes it. The
  `toolchain` step parses each form in the shell it is written for, where that shell is present.
- **F12.** `.skills/rc-push/instructions.md`'s `push` becomes `composer push` and says what the wrapper
  does — the Actions run read to a verdict — instead of naming a branch, which `.release-lane` owns.
  Then `python .skills/_sync.py` regenerates the target and the Windsurf pointer.
- **F14.** `analyze-apply <id>` stops promising one finding and prescribing a tree-wide rewrite. Either
  the tool scope narrows to the finding's file where the tool supports it (`composer tidy` does not),
  or the instruction asks before a whole-tree rewrite — and names the other findings it may touch.

**Proven by:** the pointer/agent check over `.agents/agents/` and `.agents/README.md`; the command
checker, which already refuses an interpreter in front of a program and now reads each form for its
shell; `_sync.py --check` clean after regeneration; and the skill-description check, which reads
commands a description names against the instructions that define them.

**Owed:** all four are touched text, so the changelog bullet names them; `bin/index.php --check` stays
quiet because no guard is added here.

### Batch 6 — Scratch inside the root (F15)

This one is a decision before it is a change, because the two ways out are genuinely different rules
and only one of them can be canonical.

- **Recommended:** allocate under an explicit, checked repository-local scratch root — `build/`, which
  is already ignored and which the commit skill already names for scratch. `verify.py`'s selftest gives
  `tempfile.TemporaryDirectory(dir=...)` a root it created and verified inside the checkout, and falls
  back to a note rather than the host's temporary directory when it cannot; `.agents/php-bin.sh`'s
  selftest does the same with `mktemp -d`. Section 0 then needs no exception, which is the version of
  this rule that cannot be read two ways.
- **Alternative, if the rule is meant to bend:** section 0 names the system temporary folder as the one
  permitted write outside the root, and says why the selftest is the case that needs it. That is a
  deliberate revision of a rule, not an exception discovered by a program, and it belongs in section 0
  in the same commit as the code that relies on it.

**Proven by:** a run of `--selftest` with the root made unavailable, which must report the note/refusal
and not quietly use the host's temp; and the two path guards unchanged — this batch adds no
machine-named path, which is the point.

**Owed:** `docs/commit-environment.md`'s first trap is the rule this touches; if the recommended path
is taken, it says the scratch root is the repository's own `build/` and that the selftest obeys it.

## Order, and why it is this order

1. **Batch 1** because a fix verified under an interpreter the verifier chose by itself is a fix whose
   measurement nobody agreed to. It is also the smallest: two call sites and a paragraph.
2. **Batch 2** because Batches 4 and 5 change `.skills/` sources, and the reading that judges a
   regenerated target has to be the one that can fail first.
3. **Batch 3** because Batch 2's delegation is only trustworthy if an exit status means something.
   The two are adjacent in intent and separate in diff; either order works, and doing 3 first is
   equally defensible if the delegation is written to read the code itself.
4. **Batch 4** because it is machinery with no dependants — a settings renderer that refuses bad input
   is never *needed* by another batch, and it is the batch most likely to be interrupted safely.
5. **Batch 5** because it ends in regeneration, and regeneration after Batch 2's check is a green
   reading that means something.
6. **Batch 6** last because it changes a rule, and rules are cheaper to change once the readings that
   cite them are honest.

If the work has to stop early, stop after Batch 3. That is the point past which the layer's own
readings can be believed; everything after it is the layer telling the truth about things it already
knew were wrong.

## Decisions the plan left to the repository

- **F06:** author the missing agent, or point at the existing skill. Recommended: the skill.
- **F09:** support the `sync.*.enabled` opt-outs, or refuse them by name. Recommended: support them —
  the manifest already offers them, and two flags are cheaper than a second reading of what "disabled"
  means.
- **F07:** two forms in one table, or one wrapper program that both shells can call. Recommended: two
  forms; a wrapper is a new program for a quoting problem.
- **F14:** narrow the tool scope where it exists, or keep `composer tidy` and require consent.
  Recommended: keep `tidy`, ask first, name what else it may touch.
- **F15:** repository-local scratch, or a named boundary in section 0. Recommended: repository-local.

None of these was settled by the report, and a batch was not to start with one of them still open in
the committer's head. Each was settled by the batch that needed it: F06, F07, F09 and F15 on the
recommended side, and F14 by naming the tool that owns a finding and the other findings that tool may
touch, rather than by keeping a tree-wide `composer tidy`. The batch sections and the changelog say
which.

## Definition of done, per batch

A batch is done when all of the following hold, and the list is the repository's own rather than this
plan's invention:

- the finding's own reading is proven to fail without the fix — a planted fault, a fixture, or a
  control — not merely green with it;
- a new guard has its row in `docs/guards.md`, its test's header says `@guards-index guard`, its
  reading in `tests/Support/` says which list it is in, and the mutation harness either holds it (one
  file under `tests/Unit/Support/MutationHarness/`) or declines it by name with a reason;
- `bin/index.php --check` is quiet;
- `CHANGELOG.md` has a bullet under `## Unreleased`, in the section it belongs to, in the file's voice;
- a `docs/` record the change contradicts is corrected in the same commit — this plan expects
  `AGENTS.md` sections 2, 3, 5 and 13, `docs/commit-environment.md`, and `docs/scripts.md` /
  `docs/tool-registry.md` where a program's described behaviour moved;
- `python .agents/verify.py` is green, and any command in the layer is written in the mandated shell's
  form for the platform it runs on.

## Whole-effort verification

After the last batch, one run has to answer for all six: `python .agents/verify.py` (tools, both path
guards, the strict gate, ten layer steps), `python .agents/verify.py --selftest` (each step still names
a planted fault, now including the ones Batches 1–4 added), `composer checks -- --require-all`, and
`composer test:unit` for the coverage floor if any `bin/` program gained a branch. Batches 1–5 add
readings rather than package behaviour, so the floor should not move; if it does, the ratchet in
`bin/coverage.php` is the thing that says by how much and why.

## What this plan does not cover

- **The thirteen bugs in report 0002.** `docs/reports/0002-2026-10-31_rpt-config-src-tests-bug-audit.md`
  is middleware behaviour — negotiation, response metadata, fixtures — and its fixes are already in
  progress in this working tree. Batch 2's untracked `tests/Unit/BugfixExploration/` work is that
  thread's, not this one's.
- **The environment note at the end of report 0001.** A Python launcher that could not start is a fact
  about the host, not a defect in the layer, and no batch here addresses it.
- **External editors.** No batch launches OpenCode, Trae, Cursor or Gemini CLI, and none fetches an
  external schema. Where a batch changes a template those tools read, the reading is a text and shape
  comparison, and it is described as one.
- **Severity.** The audit's P1/P2 labels are kept rather than re-argued. Two P1s (F01, F02) are in
  Batch 2 and 3; starting from the labels alone would have reordered the batches around a dependency
  that the labels do not see.
