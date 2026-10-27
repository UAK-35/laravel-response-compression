# Why does a commit here have three environment traps?

**Because a commit is the one step that runs both a shell and git, and the three ways that step
breaks are all silent — a scratch file nobody committed, an empty answer that looks like a defect,
and a command written for the shell the machine is not running.**

The `package-commit` skill states the three in its Environment section, in the space a skill has for
them: a list, each item an instruction. This is the record behind that list — what each trap looks
like from the outside, why nothing in this repository reports it, and why the workaround the skill
gives is the narrow one rather than a rule.

## The traps, and what makes each one quiet

| Trap | What it looks like | Why nothing reports it |
|---|---|---|
| Scratch written outside the root | a directory that is not this repository, holding a snapshot, a generated file or a `git show` of a path | section 0 of [`AGENTS.md`](../AGENTS.md) is a rule about where a session may write, and no guard can read a path nobody wrote down |
| Git's `sh` missing from `PATH` | a staged check that answers with nothing, or a ladder that was never asked | the reading that would ask it degrades to a *note*, so the run is green while the question went unasked |
| A command written for the other shell | `sed`/`awk` that is not there, a `|` that became a literal, an empty search that looks like an absent line | the command fails in the shell rather than in the repository, and an empty result reads as a fact about the tree |

None of the three produces a red run in this checkout. That is the property they share, and it is
why they are written down rather than left to be noticed.

## 1. Scratch goes inside the root, under a path `.gitignore` already covers

A commit is a decision about the index, and the index holds paths **in this repository**. The files
a session makes *about* that decision — a snapshot of the changelog as it was authored, a `git show`
of the state a group of commits has to materialise, a scratch copy of a file that serves two
concerns — are part of the work and have no business outside the tree. `build/` is the directory
this checkout uses, and it is already ignored.

The rule is section 0 of [`AGENTS.md`](../AGENTS.md), which is a hard rule rather than a preference:
read and write inside this root, and ask before going anywhere else. The reason is stated there and
it is worth repeating where a commit is concerned: **an edit that crosses this root is silent.** The
tree that receives it looks unchanged to its own guards, because the file is one nobody's rules
enumerate; the tree that made it looks clean; and neither suite reports either direction.

Inside the root the same file is at least visible. This repository's staged run prints the ignored
entries at the root by name — the one kind of uncommitted work `git status` says nothing about —
which is how a machine record for another checkout ends up being *named* by a commit rather than
merely sitting there; [`IgnoredTest`](../tests/Unit/Support/IgnoredTest.php) is the reading behind
that note, and [`ScratchCopiesTest`](../tests/Unit/Support/ScratchCopiesTest.php) is the reading
behind the same answer for the copy itself: a `build/head/<path>` snapshot of a file this package
owns is named too, which is what makes the workaround of putting a file back by copying visible
where this section's rule only asks for it to be kept inside the root. A file outside the root
gets no note, because there is no listing that could hold
it: [`MachinePathsTest`](../tests/Unit/Support/MachinePathsTest.php) reads the files a commit would
carry and [`RepoEscapesTest`](../tests/Unit/Support/RepoEscapesTest.php) reads relative paths that
climb, and a path that was never written into the tree is neither.

The narrow shape of this one matters as much as the rule. It is not "no temporary files"; it is
**the scratch belongs beside the work it is about**, in a directory the ignore rules already cover,
because that is the difference between a file a repository can see and one no rule can explain.

## 2. Git's `sh` is appended to `PATH` for the run, and never prepended

`bin/commit.php` runs `bin/checks.php --staged`, which is `syntax`, `pint` and the tests
`tests/Support/TestMap.php` maps the staged files to. Some of those tests ask this machine which PHP
it runs, and the asking goes through a shell: [`tests/Support/PhpBinary`](../tests/Support/PhpBinary.php)'s
`shell()` is the literal `sh`, deliberately not a path, and it is
[`.agents/php-bin.sh`](../.agents/php-bin.sh) that the question is put to. The fuller gate asks the
same script to prove its own ladder, through [`verify.py`](../.agents/verify.py)'s `tools` step. On a
Windows host where Git is installed but its `usr/bin` is not on `PATH`, neither question can be asked
with the name the code uses.

What that looks like is not a failure. `PhpBinary`'s own docblock says what it means for that class:
*a machine with no `sh` on its PATH is a machine where the question goes unasked, which is an answer
this class can carry.* `verify.py` is blunter about the cost — with no `sh` found it appends a note
rather than a finding:

```
tools: no `sh` on PATH, so .agents/php-bin.sh was not asked to prove its ladder
```

So the run is green, the ladder was never exercised, and the sentence that says so sits under a
table of tools that all passed. That is the trap: **a green run that proves less than a green run.**
`php-bin.sh`'s own `--selftest` is the thing that would have caught a rung answering from the wrong
place, and it is exactly the thing a missing `sh` leaves unrun.

The workaround is one line for one run, and both of its halves are load-bearing:

```powershell
$env:PATH = "$env:PATH;<git>\usr\bin"
```

- **Append, never prepend.** Git for Windows ships an MSYS `git` beside its `sh`, and putting that
  ahead of the Windows `git` on `PATH` changes which git every fixture runs. This suite's fixtures
  are git repositories with real tags and remotes
  ([`tests/Support/ReleaseRepo`](../tests/Support/ReleaseRepo.php)), so the symptom is not a missing
  shell but a release rail that behaves differently on one machine — the same class of defect,
  arriving one layer down.
- **No file and no git config carries it.** A `PATH` is a fact about a host. Written into the tree it
  would be a machine path in a file a commit carries, which is what
  [`MachinePathsTest`](../tests/Unit/Support/MachinePathsTest.php) refuses and what
  `.agents/machine.local.json` exists to hold instead — gitignored, and the only file in this
  repository allowed to name this machine. A command that works because *this* shell's `PATH` had
  something on it is a command with a dependency nothing records.

## 3. The two mandated shells are not the same shell

The Windows shell here is PowerShell 7 and the Linux shell is `bash` — section 2 of
[`AGENTS.md`](../AGENTS.md), which also says how a command reaches PHP
(`$(python .agents/render_local.py --value __PHP_EXE__)`, never a bare `php`). Half of a command
written for one of them does not exist on the other, and the failure arrives as an empty answer:

| Written for `bash` | What PowerShell does with it |
|---|---|
| `sed -n '1,40p' file`, `awk`, `wc -l`, `head` | not a program it has; the pipeline answers nothing or errors in the shell |
| a literal newline as a statement separator | a newline only ends the line; `;` and `&&` are the separators that carry |
| `rg -N 'pattern'` | fine where `rg` is installed, which is why section 2 mandates it — and `git grep` is the fallback, not `grep` |
| `2>&1 \| head` | `2>&1` is the same, but `head` is the part that is not there |
| a `$`-interpolated `$(…)` | `$(…)` runs a command in both, and `$var` is a variable in both, and they are still not the same expansion rules |

Two consequences that are about *reading* rather than running, and both cost a session real time
before they were written down:

- **Statements are joined with `;` or `&&`, not with a literal newline.** A multi-line command handed
  to one shell and run by the other becomes one line with the newlines eaten, which turns a script
  into a single argument.
- **An empty marker search is a claim about the quoting before it is a claim about the file.** An
  apostrophe inside the pattern — `it's`, `commit's` — ends a single-quoted shell string early, and
  what comes back is nothing, which reads exactly like a line that is not there. The file is the
  thing to check second.

This is also why the layer's commands are written the way they are: a command a skill hands over
carries the rendering of the token rather than the token, `@php` is left alone because it is
Composer's word for the interpreter it is already under, and no command in the layer puts an
interpreter in front of a program. Those rules are
[`verify.py`](../.agents/verify.py)'s `interpreter` and `toolchain` steps, and they exist because a
command that only runs on the machine it was written on is this trap with a longer fuse.

## What the three have in common

Each one is a fact about the machine or the session that the repository cannot hold: where a scratch
file went, which `PATH` a shell had, which shell ran a line. The guards in
[the guards page](guards.md) read files a commit would carry; `IgnoredTest`, `ScratchCopiesTest`
and the staged run's note read the files a commit would *not* carry but this repository still
holds. **Nothing reads the space outside the tree, and nothing reads a shell's environment.** So
the three are kept where they
can be kept — in the skill's Environment section, as the instruction a session applies before it
runs the command — and argued here, where the reasoning behind an instruction is allowed to be
longer than the instruction.

## What the skill says, and what this record adds

[`.skills/package-commit/instructions.md`](../.skills/package-commit/instructions.md) carries the
three as bullets under **Environment**, beside the two commands that answer "which PHP" and the rule
that no `php` is written in front of a program. That file is the source; `.agents/skills/package-commit/SKILL.md`
is generated from it by [`.skills/_sync.py`](../.skills/_sync.py), so the bullet is the one place a
session following the skill will read it, and it is not edited in the generated copy.

This record adds what a bullet cannot: why appending is the direction that works and prepending is
the one that breaks the release fixtures, what a green run actually proves on a host with no `sh`,
and why an ignored scratch file inside the root is visible where a temporary directory outside it is
not. [The scripts list](scripts.md) is where the programs named above are inventoried, with the
command that starts each one.

## What this record does not claim

- **That a guard could catch these.** None can: a log line cannot say where a session wrote a file
  outside the tree, and no test can ask a shell what its `PATH` was an hour ago. That is the reason
  for a rule and an instruction rather than a reading.
- **That they are traps only for a commit.** They are traps for every command in this repository. A
  commit is where they surface, because it is the step that runs the staged checks, which spawn
  shells, against an index that is a statement about paths inside a root.
- **That the `PATH` line is the fix for a missing shell.** It is the fix for a shell that exists and
  is not on `PATH` — Git's own, on a Windows host. A machine with no Git has no release rail to run,
  and `verify.py` says so rather than pretending.
