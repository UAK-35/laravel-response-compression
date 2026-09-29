# Guidelines

The rules for this repository live in **`AGENTS.md`**, which is the single source of truth. This file
is a pointer: read `AGENTS.md`, then `.agents/README.md` for the index of skills, agents and
commands.

## What this repository is

`uak35/laravel-response-compression` — a Laravel middleware package that compresses response bodies
(gzip, brotli, zstd). PHP `^8.4`, Pest 4 with Testbench, PHPStan at max. **Not** an application:
there is no `artisan`, no routes, no database and no `.env`.

## The rules that matter most

- `composer checks -- --require-all` is the gate — nine checks, and a missing tool is a failure.
- `composer test:unit` holds every `src/` file at 100.0% coverage; a change that lowers a floor fails
  there.
- A change to behaviour carries a `CHANGELOG.md` bullet, and a contradiction with a `docs/` record is
  fixed in that record in the same commit.
- Nothing a commit would carry may contain a drive path, a home directory or a network share — write
  `__TOKEN__` and let `.agents/render_local.py` resolve it.
- Never load agentic configuration from a parent folder. Only this repository root is authoritative.
