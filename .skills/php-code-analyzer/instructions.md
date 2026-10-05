# PHP Code Analyzer — reading this package's PHP

Use this skill when a path is being **read** rather than gated: you want to know what is wrong with a
file or a directory — a syntax error, a type a checker can see, a method too tangled to follow, a
construct PHP 8.4 would spell better, two blocks that are the same code twice — and you want the
findings numbered so that one of them can be fixed deliberately. It is the other half of
`package-dev`: the gate asks whether the tree is *right*, and this asks what is *wrong* with a path.

It runs the tools this package already pins, and it invents none. Two of the six readings below have
no tool in `bin/tool-paths.php` at all, so they are readings rather than programs, and the skill says
so in its report rather than dressing a reading up as a check. `recommend-tools` is what names that
gap; closing it is a change to the manifest, made deliberately, never from here.

## Environment

Every machine-local path is a token; the values live in the gitignored
`.agents/machine.local.json`, and `python .agents/render_local.py --value <token>` answers one. A
command that needs the interpreter spells it out in the line itself rather than naming a `php` for
the shell to resolve again — the same rule the rest of the layer follows:

```bash
python .agents/render_local.py --value __PHP_EXE__   # this machine's PHP
"$(python .agents/render_local.py --value __PHP_EXE__)" -l src/Support/Config.php
```

- The package manager is `composer` where it is on `PATH`, and
  `"$(python .agents/render_local.py --value __PHP_EXE__)" composer.phar <script>` where it is not.
  Most commands below reach a tool through a composer script and name no interpreter at all, because
  Composer runs a script under the interpreter already running it.
- Nothing here is run through a `vendor/bin` shim. `bin/tool.php` runs a tool under `PHP_BINARY`,
  which is the interpreter that installed the dependencies, so `composer test:types` and the
  `bin/tool.php` line behind it are the same run.

## Commands

Each of these is a command this skill defines. It is deliberately narrow: it analyzes a path and
reports, and it fixes one finding at a time.

- `analyze [path]`: run every reading below over `path` (default `src/`), then present the findings
  as one numbered list, grouped by reading, each row naming the file and the line it is at and which
  reading produced it. The numbering is the interface: it is what `analyze-apply` takes, so a finding
  is addressed rather than described.
- `syntax-sweep [path]`: lint every `.php` file under `path` with PHP's own linter, one file at a
  time — `"$(python .agents/render_local.py --value __PHP_EXE__)" -l <file>`. A file that does not
  parse is the first thing to fix, because nothing below it can be trusted about a file the
  interpreter cannot read.
- `analyze-types [path]`: run PHPStan at the level `phpstan.neon.dist` sets — `max` — over `path`:
  `composer test:types -- <path>` (which is `bin/tool.php phpstan analyse --ansi <path>`). It reads
  the same config the gate reads, so a finding here is the finding the gate would report, narrowed
  to one path and reported as a reading rather than as a red check.
- `analyze-modernize [path]`: run Rector's dry run over `path`: `composer test:refacto -- <path>`
  (`bin/tool.php rector --dry-run <path>`). `rector.php` already configures `withPhpSets()` and the
  `deadCode`, `codeQuality`, `typeDeclarations`, `privatization` and `earlyReturn` sets, so this is
  where the PHP 8.4 opportunities are found — the typed constants and readonly properties used in
  `tests/Support/` are the house style precisely because Rector suggests them first.
- `recommend-tools`: read `composer.json`'s `require-dev` against `bin/tool-paths.php` and say what is
  missing for the readings this skill performs itself. Nothing in the manifest computes cyclomatic
  complexity or duplicate blocks, so those two readings are the agent's own, and naming the tool that
  would replace them is the recommendation. It names a tool rather than adding one: an entry in
  `bin/tool-paths.php` carries a path, a pin and a reason, and it is edited deliberately, with
  `tests/Unit/Support/ToolPathsTest.php` holding the pin to the version installed.
- `analyze-apply <id>`: fix exactly one numbered finding from `analyze`, through the tool that owns
  it rather than by hand. A style or modernization finding is `composer tidy` (Pint, then Rector, then
  Pint again, so the second pass judges what Rector rewrote); a type finding is a change to the code
  with `analyze-types` re-run until it is quiet; a syntax finding is the parse error itself. Re-run
  `analyze <path>` afterwards and report the finding as fixed or still open, so a fix that did not
  take is visible rather than assumed.

## The six readings

| Reading | What it finds | What backs it |
|---|---|---|
| syntax | a file PHP cannot parse | PHP's own linter (`-l`), per file |
| types | a type error, a dead branch, an inconsistent return | PHPStan at `max`, the level `phpstan.neon.dist` sets |
| complexity | a method with too many decision points to follow | **a reading** — no tool in the manifest computes it |
| modernization | a construct PHP 8.4 spells better, and the quality sets `rector.php` configures | Rector, as a dry run |
| duplicates | two blocks that are the same code twice | **a reading** — no tool in the manifest finds them |
| tools | the tool a reading above would use if the package had one | `composer.json` against `bin/tool-paths.php` |

The two readings in bold are the honest part of this skill: they are what an agent does by opening
the file, and their evidence is the file and the line they name. A method is over the line when its
decision points (`if`, `elseif`, `for`, `foreach`, `while`, `case`, `catch`, `&&`, `||`, `??`, `? :`)
count past ten; a duplicate is two spans of more than five lines whose token sequences match after
whitespace and identifiers are normalised. Both thresholds are stated so a report can be argued with
rather than believed, and `recommend-tools` is where the tool that would decide them instead is named.

## How a report is shaped

One numbered list, grouped by reading, and each row is a file, a line and the reading that produced
it. A reading with nothing to report says so by being absent rather than by printing an empty
section. The run is a **reading, not a gate**: it exits with what it found rather than with a verdict,
and a finding here is not a red build until `package-dev`'s `gate` says the tree is not right.

## What this skill does not do

- **It does not fix style by hand.** A Pint finding is `composer tidy`; the second pass is what checks
  what Rector rewrote, and hand-fixing the spacing is how the two passes come to disagree.
- **It does not add a tool.** A recommendation names a package and a reason; the pin, the path and the
  test that holds them are a deliberate change to `bin/tool-paths.php`.
- **It does not widen the gate.** The gate runs eleven checks over the whole tree; this reads one path.
  They share the tools on purpose — a second analyzer would be the copy this package writes guards
  against — and neither replaces the other.
