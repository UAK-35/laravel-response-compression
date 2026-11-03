#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Fetch a static Linux PHP CLI on the Windows side and run this package's POSIX paths through it from WSL.

.DESCRIPTION
    `tests/Support/Processes.php` answers a stopped child differently on each platform, and the
    Windows half is the one a Windows workstation can prove: `taskkill /F /T` and the CIM sweep.
    The other half — the process table walked downwards with `ps -e -o pid=,ppid=`, `posix_kill()`
    and the `kill -9` fallback — never runs here, so it is read rather than exercised. This script
    is how a Linux interpreter is given to the same tree, so that half is exercised too.

    The place this came from had both a WSL distribution and no egress inside it, and the same is
    true here: nothing can be installed into the distribution and nothing can be downloaded from
    inside it. Windows has the network, and a distribution reads a Windows directory at
    `/mnt/<drive>/…`. So the download happens on this side, into the machine's temp directory, and
    the run happens through the mount. Nothing is installed into the distribution, no root is
    needed, and the only thing written is that one temporary directory.

    The build is static-php-cli's prebuilt CLI, in the `bulk` preset. That is not a preference:
    `common` carries neither pcntl nor posix, which is the whole half this exists to run, while
    `bulk` carries both plus zlib. What no published preset carries is `brotli`, `zstd` or
    `pdo_sqlite` — measured, not assumed: `bulk` 8.4.23 reports 63 extensions and none of the
    three. So the encoder tests (`BrotliEncoder`, `ZstdEncoder`) cannot run under this interpreter
    and are out of scope for it, and the report says which requirement it is not answering rather
    than failing a run that never needed it. What the interpreter does answer is every POSIX path
    this package has.

    `bin/env.local.cmd` is where the patch build is pinned, through the `PHP_VERSION` environment
    variable; `-PhpVersion` overrides it for one run. The version is worth pinning: static-php-cli
    publishes one directory per release train and deletes the rest, so a version that is not there
    is a 404 rather than a slightly newer interpreter.

.PARAMETER Setup
    Ensure the build is there and print the status. This is the default action.

.PARAMETER Check
    Print the status and judge it, downloading nothing. Exits 1 when the build is missing or cannot
    run the code paths this package needs.

.PARAMETER Tool
    A repository tool and its arguments as one bash fragment — `-Tool 'pest tests/Unit/Support'`.
    It is run as `bin/tool.php` under the interpreter this script fetched, named by its own path,
    which is the one thing `-Run` cannot be relied on for: the toolchain step refuses the word
    `php` written in front of a program, because it resolves to whichever interpreter is first on
    `PATH` — the question this script exists to answer.

.PARAMETER Run
    A bash line to run inside WSL, in the repository root, with the Linux PHP first on `PATH`.
    Because the `PATH` is exported, `php -v` and a shell line both resolve without naming the
    binary again. A repository tool is named with `-Tool` instead; exit status is the command's own.

.PARAMETER Distro
    The WSL distribution to use. Empty means the machine's default distribution.

.PARAMETER Preset
    `bulk` (default) or `common`. See the description — `bulk` is what carries brotli and zstd.

.PARAMETER PhpVersion
    The static-php-cli patch build to fetch. Defaults to `PHP_VERSION`, which `bin/env.local.cmd`
    sets; the pin in this file answers when neither is given.

.PARAMETER Arch
    `x86_64` (default) or `aarch64`.

.PARAMETER InstallDir
    Where the binary and its tarball live. Defaults to `%TEMP%\laravel-response-compression\linux-php`.

.PARAMETER Force
    Download again even when a usable build is already there.

.EXAMPLE
    pwsh -File .\bin\wsl-linux-php.ps1

.EXAMPLE
    pwsh -File .\bin\wsl-linux-php.ps1 -Check

.EXAMPLE
    pwsh -File .\bin\wsl-linux-php.ps1 -Tool 'pest tests/Unit/Support'

.EXAMPLE
    pwsh -File .\bin\wsl-linux-php.ps1 -Run 'php -v'
#>
[CmdletBinding()]
param(
    [switch] $Setup,
    [switch] $Check,
    [string] $Run,
    [string] $Tool,
    [string] $Distro,
    [ValidateSet('common', 'bulk')]
    [string] $Preset = 'bulk',
    [string] $PhpVersion = $env:PHP_VERSION,
    [ValidateSet('x86_64', 'aarch64')]
    [string] $Arch = 'x86_64',
    [string] $InstallDir,
    [switch] $Force
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

# The version ladder, spelled once: `-PhpVersion` where a run names one, then `PHP_VERSION` where
# `bin/env.local.cmd` exported it, then the pin. The pin is the newest build the publisher still
# serves; the directory listing is the authority for that, not this number.
if (-not $PhpVersion) {
    $PhpVersion = '8.4.23'
}

# --- where things are --------------------------------------------------------
# Both roots are derived from this file's own location, so the wrapper runs the checkout it lives
# in. This file is one directory below the repository root, which is why the climb is one level: a
# path that left the checkout would be a path the guards read as somebody else's tree.
$appRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$composerJson = Join-Path $appRoot 'composer.json'

if (-not $InstallDir) {
    $InstallDir = Join-Path $env:TEMP 'laravel-response-compression\linux-php'
}
$InstallDir = [System.IO.Path]::GetFullPath($InstallDir)

$tarball = Join-Path $InstallDir (("php-{0}-cli-linux-{1}.tar.gz") -f $PhpVersion, $Arch)
$phpPath = Join-Path $InstallDir 'php'
$provenance = Join-Path $InstallDir 'SOURCE.txt'

# The extensions a run needs, each for a reason that would otherwise arrive as a failure
# somewhere else:
#   pcntl, posix, sockets, zlib   what `Processes::bounded()`'s POSIX half, the programs in
#                                 `bin/`, and `proc_open()` over `/dev/null` need
#   openssl, fileinfo, ctype, session, phar, filter, iconv, dom, simplexml, mbstring, tokenizer
#                                 Laravel's boot, Composer's bootstrap, and the XML this suite parses
# `brotli`, `zstd` and `pdo_sqlite` are deliberately not in this list. No published preset carries
# them, so a run that failed on them could never pass; the report names them under the manifest
# and the database instead, which is the difference between "this interpreter was asked for the
# POSIX half" and "this interpreter passed everything".
$runtimeExtensions = @(
    'zlib', 'pcntl', 'posix', 'sockets',
    'openssl', 'fileinfo', 'ctype', 'session', 'phar', 'filter', 'iconv', 'dom', 'simplexml',
    'mbstring', 'tokenizer'
)

function Write-Step {
    param([string] $Tag, [string] $Name, [string] $Text)
    Write-Host ('[{0}] {1,-12} {2}' -f $Tag, $Name, $Text)
}

function Stop-WithError {
    param([string] $Name, [string] $Message, [int] $Code = 2)
    Write-Step 'FAIL' $Name $Message
    exit $Code
}

# -Check is the read-only half and -Setup is the writing one; asking for both is a typo rather
# than a request, because the writing half would then decide what the read-only one reports.
if ($Check -and $Setup) {
    Stop-WithError 'args' '-Check judges without downloading and -Setup fetches; pass one of them'
}

# --- the WSL side ------------------------------------------------------------
# One argv prefix, so every call goes to the same distribution: the named one, or the default.
$wslArgs = @()
if ($Distro) {
    $wslArgs = @('-d', $Distro)
}

function Invoke-WslText {
    param([Parameter(Mandatory)][string] $Line)
    $argv = $wslArgs + @('-e', 'bash', '-lc', $Line)
    $output = & wsl.exe @argv 2>&1
    # Anything wsl.exe itself says - "There is no distribution with the supplied name" - arrives
    # as UTF-16, which reads as one NUL between every character once PowerShell has decoded it as
    # UTF-8. The bash output is UTF-8 and carries no NUL, so dropping them fixes the one without
    # touching the other.
    return [pscustomobject]@{
        Output = ((($output | Out-String) -replace "`0", '').TrimEnd())
        Code   = $LASTEXITCODE
    }
}

function ConvertTo-WslPath {
    param([Parameter(Mandatory)][string] $WindowsPath)
    # wslpath is the distribution's own answer to "where is this Windows path", so the mount
    # prefix is never guessed here.
    $quoted = "'" + ($WindowsPath -replace "'", "'\''") + "'"
    $result = Invoke-WslText ("wslpath -a " + $quoted)
    if ($result.Code -ne 0 -or -not $result.Output) {
        Stop-WithError 'mount' (("cannot resolve {0} inside WSL: {1}") -f $WindowsPath, $result.Output)
    }
    return $result.Output.Trim()
}

function Quote-Bash {
    param([string] $Text)
    return "'" + ($Text -replace "'", "'\''") + "'"
}

# --- WSL itself --------------------------------------------------------------
if (-not (Get-Command wsl.exe -ErrorAction SilentlyContinue)) {
    Stop-WithError 'wsl' 'wsl.exe is not on PATH - install WSL2 and a Linux distribution; docs/runbooks/0001-2026-11-03_runbook-linux-php-for-posix-code.md says what a run needs.'
}

$release = Invoke-WslText ('echo "$WSL_DISTRO_NAME|$(uname -r)|$(. /etc/os-release; echo $PRETTY_NAME)"')
if ($release.Code -ne 0) {
    $wanted = if ($Distro) { "the '$Distro' distribution" } else { 'the default distribution' }
    Stop-WithError 'wsl' (("cannot start {0}: {1}") -f $wanted, $release.Output)
}
$distroName, $kernel, $prettyName = $release.Output.Split([char] '|', 3)
$linuxInstall = ConvertTo-WslPath $InstallDir

# --- the build ---------------------------------------------------------------
$url = 'https://dl.static-php.dev/static-php-cli/{0}/php-{1}-cli-linux-{2}.tar.gz' -f $Preset, $PhpVersion, $Arch

function Get-RecordedPreset {
    # The preset is a fact about the tarball rather than about the binary, so it is read back from
    # the record written beside it. A cached `common` build answers `php -v` exactly as well as a
    # `bulk` one and is missing the two extensions this package requires, which is the one way a
    # reuse could be silently wrong.
    if (-not (Test-Path $provenance)) {
        return $null
    }
    $recorded = Get-Content -Path $provenance | Where-Object { $_ -like 'preset*' } | Select-Object -First 1
    if (-not $recorded) {
        return $null
    }
    return ($recorded -replace '^preset\s+', '').Trim()
}

function Test-InstalledBuild {
    # A cached binary is only reused when it is the version asked for, the preset asked for, and
    # still runs. `php -v` is the authority on the last one.
    if (-not (Test-Path $phpPath)) {
        return $false
    }
    if ((Get-RecordedPreset) -ne $Preset) {
        return $false
    }
    $probe = Invoke-WslText ((ConvertTo-WslPath $phpPath) + ' -v')
    if ($probe.Code -ne 0) {
        return $false
    }
    return ($probe.Output -match ('PHP ' + [regex]::Escape($PhpVersion) + '\b'))
}

function Install-Build {
    New-Item -ItemType Directory -Force -Path $InstallDir | Out-Null

    if ((Test-Path $tarball) -and -not $Force) {
        Write-Step ' .. ' 'download' (("reusing {0}") -f $tarball)
    }
    else {
        Write-Step ' .. ' 'download' $url
        $started = Get-Date
        try {
            Invoke-WebRequest -Uri $url -OutFile $tarball
        }
        catch {
            Stop-WithError 'download' (("{0} - the Windows side needs the network; this is the half WSL cannot do") -f $_.Exception.Message)
        }
        $seconds = [math]::Round(((Get-Date) - $started).TotalSeconds, 1)
        $size = [math]::Round((Get-Item $tarball).Length / 1MB, 1)
        Write-Step ' OK ' 'download' (("{0} MB in {1} s") -f $size, $seconds)
    }

    # Extracted by the distribution's own tar, from the mount. The archive holds one file, `php`,
    # and the mode it carries is the one it gets - which is why the chmod below is a guard rather
    # than a fix. Windows' tar is deliberately not used: `tar.exe` on PATH can be Git's GNU tar,
    # which reads a drive-lettered path as a remote host and fails with "Cannot connect to C:".
    $extract = Invoke-WslText ('tar -xzf {0} -C {1} php' -f (Quote-Bash (ConvertTo-WslPath $tarball)), (Quote-Bash $linuxInstall))
    if ($extract.Code -ne 0 -or -not (Test-Path $phpPath)) {
        Stop-WithError 'extract' (('{0} did not yield a php binary: {1}') -f $tarball, $extract.Output)
    }

    $chmod = Invoke-WslText ('chmod +x ' + (Quote-Bash ($linuxInstall + '/php')))
    if ($chmod.Code -ne 0) {
        Stop-WithError 'extract' 'the extracted binary is not executable inside WSL'
    }

    # No checksum is published beside these builds, so the hash of what was downloaded is recorded
    # next to it: a later run can tell a same-version rebuild from the one this machine measured,
    # which is the only integrity claim the source allows.
    $hash = (Get-FileHash -Algorithm SHA256 -Path $tarball).Hash.ToLower()
    $record = @(
        "url      $url",
        "sha256   $hash",
        "bytes    $((Get-Item $tarball).Length)",
        "preset   $Preset",
        "version  $PhpVersion",
        "arch     $Arch",
        "fetched  $((Get-Date).ToString('yyyy-MM-dd HH:mm:ss'))"
    )
    Set-Content -Path $provenance -Value $record -Encoding utf8
    Write-Step ' OK ' 'extract' (("{0} ({1} bytes, sha256 {2}...)") -f $phpPath, (Get-Item $phpPath).Length, $hash.Substring(0, 12))
}

# --- what the manifest asks for ---------------------------------------------
$manifest = Get-Content -Raw $composerJson | ConvertFrom-Json
$declared = @(
    $manifest.require.PSObject.Properties.Name |
        Where-Object { $_ -like 'ext-*' } |
        ForEach-Object { $_.Substring(4).ToLower() }
)
$constraint = [string] $manifest.require.php

# --- run ---------------------------------------------------------------------
$installed = Test-InstalledBuild

if ($Check -and -not $installed) {
    Write-Step 'FAIL' 'binary' (("nothing usable at {0} - run this script with -Setup") -f $InstallDir)
    exit 1
}

if (-not $installed) {
    Install-Build
}
else {
    Write-Step ' OK ' 'binary' (("reusing {0}") -f $phpPath)
}

$linuxPhp = $linuxInstall + '/php'
$linuxApp = ConvertTo-WslPath $appRoot

$version = Invoke-WslText ($linuxPhp + ' -v')
if ($version.Code -ne 0) {
    Stop-WithError 'binary' (("{0} does not run inside WSL: {1}") -f $linuxPhp, $version.Output)
}
$phpVersion = ($version.Output -split "`n")[0].Trim()
$builder = (($version.Output -split "`n") | Where-Object { $_ -match 'static-php-cli' } | Select-Object -First 1)

$modules = Invoke-WslText ($linuxPhp + ' -m')
if ($modules.Code -ne 0) {
    Stop-WithError 'binary' (("{0} -m failed") -f $linuxPhp)
}
$loaded = @(
    $modules.Output -split "`n" |
        ForEach-Object { $_.Trim().ToLower() } |
        Where-Object { $_ -and -not $_.StartsWith('[') }
)

$bits = Invoke-WslText ($linuxPhp + " -r 'echo PHP_INT_SIZE * 8;'")
$missingDeclared = @($declared | Where-Object { $loaded -notcontains $_ })
$missingRuntime = @($runtimeExtensions | Where-Object { $loaded -notcontains $_ })

# The version is judged against the constraint composer.json states, rather than against the patch
# build this script pins: the pin says which download, the constraint says what is legal.
$versionProblem = $null
if ($constraint -match '(\d+)\.(\d+)') {
    $wantMajor = [int] $Matches[1]
    $wantMinor = [int] $Matches[2]
    $haveVersion = ($version.Output -split "`n")[0]
    if ($haveVersion -match 'PHP (\d+)\.(\d+)\.') {
        $haveMajor = [int] $Matches[1]
        $haveMinor = [int] $Matches[2]
        if ($haveMajor -lt $wantMajor -or ($haveMajor -eq $wantMajor -and $haveMinor -lt $wantMinor)) {
            $versionProblem = ('PHP {0}.{1} is below composer.json''s "{2}"') -f $haveMajor, $haveMinor, $constraint
        }
    }
    else {
        $versionProblem = 'cannot read a version out of: ' + $haveVersion
    }
}

# --- the report --------------------------------------------------------------
Write-Step ' OK ' 'distro' (("{0} (WSL name {1}), kernel {2}") -f $prettyName, $distroName, $kernel)
Write-Step ' OK ' 'mount' (("{0} -> {1}") -f $InstallDir, $linuxInstall)
Write-Step ' OK ' 'php' (("{0}; {1} extensions; {2}-bit") -f $phpVersion, $loaded.Count, $bits.Output.Trim())
if ($builder) {
    Write-Step ' .. ' 'build' $builder.Trim()
}
Write-Step ' OK ' 'app root' $linuxApp

$problems = 0

if ($missingRuntime.Count -gt 0) {
    $problems++
    Write-Step 'FAIL' 'runtime ext' ('missing: ' + ($missingRuntime -join ', '))
}
else {
    Write-Step ' OK ' 'runtime ext' (("all {0} the code paths need are loaded") -f $runtimeExtensions.Count)
}

if ($missingDeclared.Count -gt 0) {
    # Named rather than failed. These are `composer.json`'s own requirements and the encoders call
    # brotli_compress() and zstd_compress() on any request that asks for them, so a suite run here
    # cannot include the encoder tests - but nothing POSIX reaches for either extension, and no
    # preset carries them, so a failure would only make this script unable to do the one thing it
    # was written for. The row is loud so the boundary is read rather than remembered.
    Write-Step ' !! ' 'manifest ext' (('absent: {0} - no published preset carries them, so the encoder tests are out of scope; the POSIX code is not') -f ($missingDeclared -join ', '))
}
else {
    Write-Step ' OK ' 'manifest ext' (("every ext-* composer.json requires is loaded ({0})") -f $declared.Count)
}

# Testbench's in-memory database is `pdo_sqlite`, which `bulk` also omits: a run here boots the
# application but cannot reach the connection, so the tests that do are out of scope with the
# encoders rather than failing for a reason about this script.
if ($loaded -notcontains 'pdo_sqlite') {
    Write-Step ' !! ' 'database' 'pdo_sqlite absent - tests that open the connection cannot run here'
}

if ($versionProblem) {
    $problems++
    Write-Step 'FAIL' 'version' $versionProblem
}
else {
    Write-Step ' OK ' 'version' (("satisfies composer.json's {0}") -f $constraint)
}

if (Test-Path $provenance) {
    $source = (Get-Content -Path $provenance | Where-Object { $_ -like 'url*' } | Select-Object -First 1)
    Write-Step ' .. ' 'source' $source
}

function Invoke-LinuxLine {
    param([string] $Line, [string] $Shown)
    Write-Host ''
    Write-Step ' .. ' 'run' $Shown
    Write-Host ''
    $argv = $wslArgs + @('-e', 'bash', '-lc', $Line)
    & wsl.exe @argv
    exit $LASTEXITCODE
}

if ($Run -and $Tool) {
    Stop-WithError 'args' '-Run takes a bash line and -Tool a repository tool; pass one of them'
}

if ($Tool) {
    # The interpreter is written as this script's own path rather than as the word on `PATH`,
    # because the answer to "which PHP" is exactly what a run here is trying to pin down. `$Tool`
    # is left unquoted on purpose: it is a bash fragment, so a path with a space in it is quoted
    # by the caller the way bash would expect, rather than by a second opinion from here.
    $command = '{0} bin/tool.php {1}' -f (Quote-Bash $linuxPhp), $Tool
    Invoke-LinuxLine ('cd {0} && {1}' -f (Quote-Bash $linuxApp), $command) ('bin/tool.php ' + $Tool)
}

if ($Run) {
    Invoke-LinuxLine ('export PATH={0}:"$PATH"; cd {1} && {2}' -f (Quote-Bash $linuxInstall), (Quote-Bash $linuxApp), $Run) $Run
}

if ($Check -and $problems -gt 0) {
    exit 1
}

Write-Host ''
Write-Host "Next:  pwsh -File .\bin\wsl-linux-php.ps1 -Tool 'pest tests/Unit/Support'"
exit 0
