# Copilot instructions

The rules for this repository live in **`AGENTS.md`**, the single source of truth for agents and
tools. Read it first; read `.agents/README.md` second for the index of skills, custom agents and
commands. This file does not restate the rules — it says what a session needs before it reads them.

## What this repository is

`uak35/laravel-response-compression` — a **Laravel middleware package** (a library) that compresses
response bodies with gzip, brotli or zstd according to `Accept-Encoding`.

- PHP `^8.4`; `illuminate/http` and `illuminate/support` `^12.4.1|^13.0`
- required extensions: `ext-zlib`, `ext-brotli`, `ext-zstd`
- dev tools pinned exactly: Pest 4 (with Testbench 11), PHPStan (max), Rector, Pint
- **no application**: no `artisan`, no routes, no controllers, no database, no `.env`

Do not generate Laravel application scaffolding here. The work is the middleware, the config, the
tests, the design records and the two runbooks.

## Conventions

- Follow the file you are editing: `declare(strict_types=1)`, explicit types, PHPDoc where a type
  cannot say it, one class per file, PSR-4 under `Uak35\ResponseCompression\`.
- Comments argue for a decision. A comment that restates the code is removed.
- Run a tool through `bin/tool.php` (or the matching Composer script), never a `vendor/bin` shim:
  the shim runs whichever `php` is on `PATH`, which is a different interpreter on a machine with more
  than one.
- Never write a drive path, a home directory or a network share into a committed file — the
  machine-path guard refuses it. Use the `__TOKEN__` placeholders and `.agents/render_local.py`.

## Verification

```bash
composer checks -- --require-all   # nine checks: syntax, AST, schema, platform, YAML, PHPStan, Pint, Rector, Pest
composer test:unit                 # the coverage floor — every src/ file at 100.0%
python .agents/verify.py           # the agentic layer, checked against itself
```

A behaviour change carries a test that fails without it and a `CHANGELOG.md` bullet under
`## Unreleased`. A new guard carries its row in `docs/guards.md`.
