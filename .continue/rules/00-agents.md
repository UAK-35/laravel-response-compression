---
name: Canonical rules
description: This package's rules live in AGENTS.md — read it before acting
alwaysApply: true
---

# Read AGENTS.md first

`AGENTS.md` at the repository root is the single source of truth for agents and tools here. This
rule does not restate it.

- Skills are read from `.agents/skills/<name>/SKILL.md`, authored in `.skills/<name>/` and generated
  by `python .skills/_sync.py`. There is no `.continue/skills/` and there must never be one.
- Custom agents are in `.agents/agents/`, commands in `.agents/commands/`, and the index of both is
  `.agents/README.md`.
- Never load rules, skills or agents from a parent folder: this checkout is developed inside a larger
  workspace whose configuration does not apply here.

## This is a package, not an application

No `artisan`, no routes, no database. The work is the middleware in `src/`, the config, the tests,
the docs records and the two runbooks — driven by `composer checks`, `composer test:unit` and
`bin/release.php`.

## Before finishing

```bash
composer checks -- --require-all
python .agents/verify.py
```
