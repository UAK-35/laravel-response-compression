# A Linux PHP for the code that only runs off Windows

Created: 2026-11-03 (Asia/Karachi). Runbook: 0001.

## The prompt

> read the file : ext/wsl-linux-php.md
> and also scan the folder ext/ for other files.
> use the ext/scripts/windows/env.local.cmd to set environment for the script: ext/scripts/windows/wsl-linux-php.ps1
> and use the environment variable "PHP_VERSION" in the script to set value of parameter/variable $PhpVersion
>
> modify/adjust the files are per need and use a suitable place to put all these files appropriately.
> finally try to create a linux php and run the code that is waiting to be tested - the POSIX related code

The three files arrived under `ext/`, which `.gitignore` holds, from a checkout of a different
repository: their paths named `scripts/windows/`, their measured table named that project's suites,
and their preset reasoning assumed a manifest with no `ext-brotli` or `ext-zstd`. They now live at
`bin/wsl-linux-php.ps1`, `bin/env.local.cmd` and here, and the reasoning below is this package's.

## When this runbook is used

The question is about code whose POSIX half has never run. `tests/Support/Processes.php` answers a
stopped child two ways: `taskkill /F /T` plus the CIM sweep on Windows, and on everything else the
process table walked downwards through `ps -e -o pid=,ppid=`, `posix_kill()`, and a `kill -9`
fallback. The same is true of the `/dev/null` descriptors in `bin/checks.php` and `bin/coverage.php`,
the non-Windows half of `bin/checks.php`'s tool runner, and the `wslpath`/`cygpath` translation in
`handoff/state.sh`. On a Windows workstation all of that is read rather than exercised, and CI is the
only place it runs. This runbook gives the same tree a namespace where they do run.

It is not for measuring this package's behaviour — that is `composer checks` and `composer test:unit`
on the machine the package is written on, and on the runner. It is for the branch of a test helper
that a Windows run cannot reach.

## Preconditions

| Condition | How to check | If it is not met |
|---|---|---|
| WSL2 with a distribution | `wsl.exe -l -v` lists one, `Running` or not | install WSL2 and a distribution; the report's `wsl` row fails by name |
| PowerShell 7 | `pwsh -v` answers `7.` | `pwsh` is what `AGENTS.md` mandates for every Windows command |
| Network on the **Windows** side | the `download` row either fetches or reports the URL | nothing is fetched from inside the distribution; that is the design rather than a bug |
| A `vendor/` installed on the Windows side | `vendor/autoload.php` exists | run `composer install` first: the distribution reads the mount, and the packages are PHP source it can read |
| Composer's platform check passes under Linux | PHP `>=` `composer.json`'s `require.php`, which `-Check`'s `version` row judges | the row fails with both numbers |

The distribution's own state does not matter and is deliberately not used: nothing is installed into
it, no package manager runs, and no root is needed. A distribution with no PHP is the expected
starting point, and the one this was measured on had none.

## What it makes

| Path (Windows) | What it is |
|---|---|
| `%TEMP%\laravel-response-compression\linux-php\php` | the interpreter, 31,737,684 bytes, executable through the mount |
| `%TEMP%\laravel-response-compression\linux-php\php-8.4.23-cli-linux-x86_64.tar.gz` | the download, kept so a re-run needs no network |
| `%TEMP%\laravel-response-compression\linux-php\SOURCE.txt` | url, sha256, byte count, preset, version, arch, and when it was fetched |

The build is static-php-cli's prebuilt CLI: one self-contained file, so there is no extension
directory to mount and no `php.ini` to point anywhere. `%TEMP%` is spelled as an environment
variable rather than a directory: a drive path in a file a commit carries is what the machine-path
guard refuses, and the guards read this script and this runbook.

## Steps

### 1. Pin the build, then fetch it

```powershell
cmd /c "call bin\env.local.cmd && pwsh -File bin\wsl-linux-php.ps1 -Setup"
```

`bin/env.local.cmd` is one line, `set "PHP_VERSION=8.4.23"`, and `PHP_VERSION` is the environment
variable the wrapper reads as `-PhpVersion`'s default. The ladder is `-PhpVersion`, then
`PHP_VERSION`, then the pin inside the script; a run that names none of them still works.

**What it proves:** that the Windows side reached the publisher, that the distribution's own `tar`
extracted the archive through the mount, and that the result runs there. Expected rows: `download`
(about 30 MB), `extract`, `binary`, `distro`, `mount`, `php`, `build`, `app root`.

**When it fails:** a 404 means the patch build is no longer served — static-php-cli keeps one
directory per release train and deletes the rest, so read the directory listing for `bulk` and move
the pin to a version it still serves. `tar` answering `Cannot connect to C:` means Windows' GNU tar
read a drive-lettered path as a host; the wrapper avoids it by extracting with the distribution's
`tar` from a path `wslpath` produced. `Invoke-WebRequest` failing is the Windows side having no
network, which is the half the distribution cannot cover either.

### 2. Judge the interpreter

```powershell
pwsh -File bin\wsl-linux-php.ps1 -Check
```

**What it proves:** the version is at least `composer.json`'s constraint, and every extension a run
needs is loaded. Exit `0` is usable, `1` is a finding, `2` is an environment that cannot answer. Two
rows are expected to be `!!` rather than `OK`, and both are named rather than failed — see **What it
does not cover**.

### 3. Run the POSIX code

```powershell
pwsh -File bin\wsl-linux-php.ps1 -Tool 'pest tests/Unit/Support/ProcessesBoundedTest.php tests/Unit/Support/ProcessesTest.php'
```

`-Tool` changes into the repository root and runs `bin/tool.php` under the interpreter this script
fetched, with the quoted string appended to it. The string is a bash fragment, so bash does the
splitting and a path with a space in it is quoted where it is written. The interpreter is a path
there rather than the word on `PATH`, and that is deliberate: the layer's toolchain step refuses the
word in front of a program, because it resolves to whichever interpreter is first on `PATH` — which
for a Linux run is the whole question. `bin/tool.php` is the repository's own path to
a tool; it is not a `vendor/bin` shim.

**What it proves:** both branches of the bounded runner that a Windows run cannot reach. The first
case is a child that starts and never exits: the deadline lands, `descendants()` reads the process
table, `signal()` kills deepest-first, and the run reports `124`. The second is a child that exits
while a grandchild holds its pipe: the child's own exit code is the answer, the reader is given a
bounded grace, and the grandchild's later write is not in the capture.

### 4. Widen to every helper that spawns a child

```powershell
pwsh -File bin\wsl-linux-php.ps1 -Tool 'pest tests/Unit/Support'
```

**What it proves:** the same for `ReleaseRepo`, `Ignored`, `MachinePaths`, `Handoff`, `PhpBinary`,
`Workers` and the mutation harness, and it is 15 minutes rather than one. Read the failures against
**What it does not cover** before treating any of them as this package's.

### 5. (optional) Ask the distribution which PHP it finds

```powershell
pwsh -File bin\wsl-linux-php.ps1 -Run 'php -v'
```

`-Run` is the general half: it exports the mount ahead of `PATH`, changes into the repository root,
and hands the rest of the line to bash. A command with a `-` and a flag in it is what it is for; a
repository tool goes through `-Tool`, because that is where the interpreter has to be named exactly.

**What it proves:** the `PATH` export is what the next command will run, rather than an assumption
about the mount.

## When a step fails

| Symptom | What it means | What to do |
|---|---|---|
| `nothing usable … run this script with -Setup` | `-Check` with no build in the install directory | run `-Setup`, or `-Force` when a partial download is there |
| `reusing …` but the preset is wrong | a `common` build left behind by an earlier run | nothing: the preset is read back from `SOURCE.txt` and a mismatch reinstalls |
| `runtime ext missing: …` | the fetched build is not the one this script judged | `-Force`, and read the `php` row's extension count |
| `manifest ext absent: brotli, zstd` | expected — no published preset carries either | run the POSIX tests; do not run the encoder tests here |
| `database pdo_sqlite absent` | expected — `bulk` omits it too | tests that open the connection are out of scope |
| a Pest failure in `EncTest` | the same missing extensions, now as three failing cases | not a regression; it is why step 4's count is read with this row |
| `php: command not found` inside `-Run` | the line was run outside the wrapper, which is what exports the `PATH` | run it through `-Run`, or name a repository tool with `-Tool` |

## The way back

```powershell
Remove-Item -Recurse -Force (Join-Path $env:TEMP 'laravel-response-compression')
```

Nothing is installed in the distribution, so that one directory is the whole of it. Nothing in the
checkout is written by a run: the fixture trees a test builds live under `build/` and go with any
other run's.

## What it does not cover

- **The encoders.** `composer.json` requires `ext-brotli` and `ext-zstd`; no published preset carries
  either, so `BrotliEncoder` and `ZstdEncoder` cannot be exercised under this interpreter and
  `tests/Unit/Support/EncTest` fails three cases for that reason alone. That is a property of the
  build, not of the middleware, and the Windows run is what covers them.
- **The database.** `pdo_sqlite` is likewise absent, so anything that opens Testbench's connection is
  out of scope.
- **The coverage floor.** No `pcov` and no `xdebug` in a static build, so `bin/coverage.php` cannot
  measure here. The floor is the runner's, and the gate's `tests` row is the one that runs without a
  driver.
- **The runner.** CI on `ubuntu-latest` is a different Linux — it has the extensions, the driver and
  a native filesystem. A green run here is evidence about the POSIX branches, not a substitute for it.
- **Nothing calls this automatically.** It is started by a person; no guard, no composer script and no
  hook depends on it, and a checkout without WSL stays green.

## What was measured

On 2026-11-03, Debian GNU/Linux 13 (trixie) under WSL2, kernel `6.18.54.1-microsoft-standard-WSL2`,
from this checkout mounted at its own path:

| Run | Result |
|---|---|
| `-Setup` | 29.6 MB in 105.1 s; `php` 31,737,684 bytes; `sha256 5fa2b5f1cc9d7f79b19718926b4f4f0bb6949db52073b2fabf8ae52de3993af5`; `SOURCE.txt` written |
| `-Setup` again | `reusing …`, no download |
| `-Check` | exit 0; PHP `8.4.23 (cli)`, static-php-cli `2.8.6`, 63 extensions, 64-bit; `brotli`, `zstd`, `pdo_sqlite` named as `!!` |
| `ProcessesBoundedTest` + `ProcessesTest` | **4 passed (15 assertions), 51.89 s, exit 0** — the deadline case and the grandchild-holds-the-pipe case both green on Linux |
| `tests/Unit/Support` | **275 passed (761 assertions), 5 failed, 920.71 s** |

The five failures, by name and by cause:

| Failing case | Cause |
|---|---|
| `EncTest` — `it is brotli encoded` | `brotli_compress()` is not in this build |
| `EncTest` — `it is zstd encoded`, `it is not zstd encoded` | `zstd_compress()` is not in this build |
| `HandoffTest.php:52` — the environment table against the machine file | `Handoff::stale()` reads `.agents/machine.local.json`, whose `package.dir` names the Windows path of this checkout; under the mount the same tree has a different path, so the record reads as stale |
| `PhpBinaryTest.php:165` | `PhpBinary`'s machine rung answers the record's `php.exe`, which is a Windows path, and the test requires a value it names to be a file — true on the machine the record was written on, false where it is being read |

The last two are findings rather than noise: they are readings that assume the machine file
describes the tree it is being read in, which is exactly what a second platform on the same tree
violates. Nothing in this runbook fixes them, and a fix is a change to those readings rather than to
this procedure.
