# CLAUDE.md

> Canonical project rules live in **`AGENTS.md`**. This file is a pointer — it does not restate
> them, so it cannot drift.

## Read, in order

1. `AGENTS.md` — canonical rules (what this package is, the gate, the guards, the code rules, the
   release and push runbooks, the commands).
2. `.agents/README.md` — skills, custom agents and commands: the index of everything loadable.
3. `.agents/agents/` — custom agents (`@code-reviewer`).

Claude Code reads skills **directly** from `.agents/skills/<name>/SKILL.md`. There is no
`.claude/skills/` copy and there must never be one.

## Hard rule — no parent-folder config

This checkout is developed inside a larger workspace whose root carries its own `AGENTS.md`,
`.agents/`, `.claude/` and `.trae/`. **None of it applies here.** Never load rules, skills or agents
from a parent folder: only this repository root is authoritative for this package, and
`python .agents/verify.py` fails on any file that points outside it.

## Before finishing

```bash
python .agents/verify.py --fix    # repair what a generator owns, then re-check
python .agents/verify.py          # detect only; exits 1 when something is inconsistent
```

The package's own gate is separate, and is the one that matters:

```bash
composer checks -- --require-all   # the nine checks CI and a release run
composer test:unit                 # the coverage floor: every src/ file at 100.0%
```
