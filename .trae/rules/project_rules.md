# Package Rules For Trae

These rules are derived from the canonical rules in `AGENTS.md` and say only what a session needs
before it reads them. Where this file and `AGENTS.md` disagree, `AGENTS.md` is right.

## Scope

- This is `uak35/laravel-response-compression`: a **Laravel middleware package**, not an application.
- Stack: PHP `^8.4`, `illuminate/http` + `illuminate/support` `^12.4.1|^13.0`, Pest 4 with
  Testbench, PHPStan at max, Rector, Pint — all pinned to exact versions.
- Required extensions: `ext-zlib`, `ext-brotli`, `ext-zstd`.
- No `artisan`, no routes, no controllers, no database, no queue, no `.env`.

## Environment and shell

- Windows: PowerShell 7, resolved as `__PWSH_EXE__` through
  `python .agents/render_local.py --value __PWSH_EXE__`. PHP likewise: `__PHP_EXE__`.
- `composer` is often not on `PATH`. The package manager here is
  `__PHP_EXE__ composer.phar <script>`.
- Search with `rg`, falling back to `git grep`.
- Never write a drive path, a home directory or a network share into a file a commit would carry —
  `tests/Unit/Support/MachinePathsTest.php` refuses it. Write `__TOKEN__`.

## The rules that are enforced rather than asked for

- `composer checks -- --require-all` must be green: nine checks, no skips.
- `composer test:unit` must still meet every floor — every `src/` file at 100.0%.
- A new guard needs its row in `docs/guards.md`, and the mutation harness in `tests/Support/` either
  mutates it or declines it by name.
- Every behaviour change carries a `CHANGELOG.md` bullet under `## Unreleased`.

## Load order

1. `AGENTS.md`
2. `.agents/README.md`
3. `.agents/toolchain.json`
4. this file
