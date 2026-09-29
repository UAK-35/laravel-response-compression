---
inclusion: auto
---

# Package Rules

Canonical rules live in `AGENTS.md`; this steering file introduces the repository and says where each
kind of rule is written, so a session starts in the right place.

## What this repository is

`uak35/laravel-response-compression` is a **Laravel middleware package**: it compresses a response
body with gzip, brotli or zstd according to `Accept-Encoding`, and a config file decides when that is
allowed. It is a library, not an application.

- PHP `^8.4`; `illuminate/http` + `illuminate/support` `^12.4.1|^13.0`
- `ext-zlib`, `ext-brotli`, `ext-zstd` are required
- Pest 4 with Testbench, PHPStan at max, Rector and Pint, all pinned to exact minor versions
- no `artisan`, no routes, no controllers, no database, no queue

## Where each rule is written

| Need | File |
|---|---|
| Rules, gate, guards, code style, releases, commits | `AGENTS.md` |
| Skills, agents, commands | `.agents/README.md` |
| Commands and tool pins | `.agents/toolchain.json` |
| A version, and the numbering | `RELEASING.md` |
| Getting a tag to the remote | `PUSHING.md` |
| Why each guard exists | `docs/guards.md` |

## Environment

Machine-local paths are tokens: `__PHP_EXE__`, `__PHP_DIR__`, `__PWSH_EXE__`, `__PACKAGE_DIR__`. The
values live in the gitignored `.agents/machine.local.json`; resolve one with
`python .agents/render_local.py --value __TOKEN__`. A committed file that carries a real drive path
fails `tests/Unit/Support/MachinePathsTest.php`.

Never load configuration from a parent folder — only this repository root is authoritative.

## Before finishing

```bash
composer checks -- --require-all   # the gate
composer test:unit                 # the coverage floor
python .agents/verify.py           # the agentic layer
```
