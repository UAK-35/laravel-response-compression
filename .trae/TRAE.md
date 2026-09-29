# TRAE.md — Session Anchor

Guidance for a TRAE session in this repository: a **Composer package**, not an application — a
Laravel middleware that compresses response bodies, with no `artisan`, no routes and no database.
This file is the TRAE-specific anchor; it restates no project rule.

## Canonical sources

| Need | Canonical source |
|---|---|
| The rules — what this package is, the gate, the guards, code style, releases, commits | `AGENTS.md` |
| Skills, custom agents, commands — the index of everything loadable | `.agents/README.md` |
| Skills | `.agents/skills/<name>/SKILL.md` — the single real skill target |
| Custom agents | `.agents/agents/<id>.md` |
| Commands | `.agents/commands/<name>.md` |
| Registered commands and tool pins | `.agents/toolchain.json` |

## Hard rule — no parent-folder config

Never load agentic configuration from a parent folder. Only this repository root is authoritative.
The workspace this checkout sits inside carries its own `AGENTS.md`, `.agents/` and `.trae/`, and
none of it applies here; `python .agents/verify.py` fails on a file that points at it.

## Session startup sequence

At the start of every TRAE session, load in order:

1. `AGENTS.md` — canonical rules.
2. `.agents/README.md` — the index.
3. `.agents/toolchain.json` — the commands and the tool pins, rendered into section 13 of `AGENTS.md`.
4. this file.

Skills and agents are read from `.agents/` through `.trae/settings.json`, whose committed template is
`.trae/settings.json.example`:

```json
{ "aiAssistant": { "skillRoots": [".agents/skills"], "agentRoots": [".agents/agents"] } }
```

`settings.json` itself is rendered per machine — it holds `__PWSH_EXE__`, `__PHP_EXE__` and
`__PACKAGE_DIR__` and `render_local.py` fills them from the gitignored
`.agents/machine.local.json`:

```bash
python .agents/render_local.py --init   # asks for the paths, then renders every settings file
python .agents/render_local.py          # render again after a template changes
```

## Before finishing

```bash
python .agents/verify.py --fix   # repair what a generator owns, then re-check
python .agents/verify.py         # detect only; exits 1 on a finding
composer checks -- --require-all # the package's own gate, which verify.py does not replace
```

The pre-commit hook is `.githooks/pre-commit`; enable it once per clone with
`git config core.hooksPath .githooks`.

## Directory restrictions

Do not read or load unless the user asks:

- `vendor/`, `build/`, `.phpunit.cache/`, `.php-cs-fixer.cache`
- every `.*` directory except `.agents/`, `.skills/`, `.github/`, `.githooks/` and `.trae/`
