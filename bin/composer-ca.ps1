#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Run Composer for this package on Windows when a local TLS scanner is
    intercepting HTTPS.

.DESCRIPTION
    A local antivirus or proxy that scans TLS -- Avast Web Shield is the one this
    was found with -- can make every `composer install` / `composer update` fail
    with:

        curl error 60 ... SSL certificate problem: unable to get local issuer
        certificate

    Git, Git Bash's curl and the Windows certificate store keep working, because
    they use Schannel; PHP's curl uses OpenSSL with curl.cainfo / openssl.cafile,
    which does not carry the scanner's root. The two stacks disagreeing about the
    same URL is the fingerprint.

    This script rebuilds a CA bundle from Git's own ca-bundle.crt plus every
    intercepting root it finds in the Windows trust store, points Composer at it
    with COMPOSER_CAFILE (which Composer reads itself, so no PHP ini override is
    needed for a composer.bat shim), and then runs Composer. The bundle is built
    from answers rather than from paths written here: Git is asked for its own
    bundle, and the executable of the Git that answered is where the rest are
    looked for, because an install directory written into a file resolves in one
    place and the machine-path guard in `tests/Support/` exists to catch exactly
    that.

    It is a Windows convenience for whoever works on this repository rather than
    part of the package: nothing under `src/` calls it, `.gitattributes`
    `export-ignore`s it so no consumer receives it, and `docs/scripts.md` is where
    it is listed. This description is the record of why it exists.

    Nothing about the platform requirements is ignored on the way through. This
    package requires `ext-zlib`, `ext-brotli` and `ext-zstd` on every platform, so
    there is no Windows-only requirement to skip for it -- and a PHP without one of
    them is a build problem the gate's `platform` check is written to report, which
    a Composer told to ignore it would hide.

.PARAMETER ComposerArgs
    Everything after the script name is forwarded to Composer verbatim, e.g.
    `install`, `update`, `test`, `release -- --weigh`.

.EXAMPLE
    pwsh -File bin/composer-ca.ps1 install
    pwsh -File bin/composer-ca.ps1 update
    pwsh -File bin/composer-ca.ps1 -- test
    pwsh -File bin/composer-ca.ps1 validate --no-check-publish

.NOTES
    The bundle is written to %TEMP% and is machine-local: never commit it, and
    never hard-code its path in composer.json. COMPOSER_CA_COMPOSER overrides the
    Composer program (a shim, a phar, or an absolute path).
#>
[CmdletBinding()]
param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]] $ComposerArgs
)

$ErrorActionPreference = 'Stop'

# `bin/` is this file's own directory, so the package root is the level above it -- the
# relationship every program in `bin/` has to the package it belongs to.
$packageRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if (-not (Test-Path (Join-Path $packageRoot 'composer.json'))) {
    throw "No composer.json at $packageRoot -- run this from the package checkout."
}

# ── 1. Git's own CA bundle, wherever this machine keeps it ───────────────────
# `http.sslcainfo` is Git's own statement of it and the first thing to believe, but a
# stock install does not always set it -- so the executable that just answered is asked
# for its prefix, which is where every layout Git ships puts the same file.
$gitBundle = $null
$configured = & git config --get http.sslcainfo 2>$null

if ($configured -and (Test-Path $configured)) {
    $gitBundle = $configured
}

if (-not $gitBundle) {
    # `<prefix>/libexec/git-core` is what Git answers with, so two levels up is its prefix;
    # the executable's own directory is the other shape an install takes.
    $execPath = & git --exec-path 2>$null
    $git = Get-Command git -ErrorAction SilentlyContinue
    $roots = @()

    if ($execPath -and (Test-Path $execPath)) {
        $roots += (Split-Path (Split-Path $execPath -Parent) -Parent)
    }
    if ($git) {
        $roots += (Split-Path $git.Source -Parent)
        $roots += (Split-Path (Split-Path $git.Source -Parent) -Parent)
    }

    foreach ($root in $roots) {
        # The first candidate is where Git for Windows keeps it today (ucrt64 and mingw64):
        # the prefix of the running Git, then the same file in the layouts around it.
        foreach ($relative in @(
            'etc/ssl/certs/ca-bundle.crt',
            'mingw64/etc/ssl/certs/ca-bundle.crt',
            'mingw64/ssl/certs/ca-bundle.crt',
            'usr/ssl/certs/ca-bundle.crt'
        )) {
            $candidate = Join-Path $root $relative

            if (Test-Path $candidate) {
                $gitBundle = $candidate
                break
            }
        }

        if ($gitBundle) {
            break
        }
    }
}

if (-not $gitBundle) {
    throw 'Git CA bundle not found -- is Git installed, and does `git --exec-path` answer?'
}

# ── 2. Every intercepting root the scanner installed ────────────────────────
$bundle = Join-Path $env:TEMP 'composer-ca.pem'
Copy-Item $gitBundle $bundle -Force

$interceptors = 'Web/Mail Shield|Kaspersky|ESET|Bitdefender|Sophos|Zscaler|Netskope|Avast'
$roots = @()
foreach ($store in @('Cert:\LocalMachine\Root', 'Cert:\CurrentUser\Root')) {
    $roots += Get-ChildItem $store -ErrorAction SilentlyContinue |
        Where-Object { $_.Subject -match $interceptors }
}
foreach ($root in $roots) {
    $b64 = [Convert]::ToBase64String($root.RawData, 'InsertLineBreaks')
    Add-Content -Path $bundle -Value "`n-----BEGIN CERTIFICATE-----`n$b64`n-----END CERTIFICATE-----"
}

if ($roots.Count -eq 0) {
    Write-Warning 'No intercepting root found in the Windows trust store; using Git''s bundle as-is.'
}

$env:COMPOSER_CAFILE = $bundle

# ── 3. The Composer to run ──────────────────────────────────────────────────
# Order matters on this repository's own machines: `composer` on PATH is often a .bat shim
# that runs whichever `php` is first on PATH -- the second-interpreter failure
# `bin/tool-paths.php` exists to refuse -- and where `php` is not on PATH at all that shim
# fails with nothing but "The system cannot find the path specified." So the pair this
# checkout already documents is tried first: the composer.phar beside composer.json, under the
# interpreter `.agents/render_local.py --value` reads out of `.agents/machine.local.json`,
# which is the one place this machine's PHP is named.
$composerCmd = $env:COMPOSER_CA_COMPOSER
$composerPrefix = @()

if (-not $composerCmd) {
    $phar = Join-Path $packageRoot 'composer.phar'
    $renderer = Join-Path $packageRoot '.agents/render_local.py'
    $machinePhp = $null

    if ((Test-Path $phar) -and (Test-Path $renderer)) {
        $machinePhp = & python $renderer --value __PHP_EXE__ 2>$null | Select-Object -First 1
    }

    if ($machinePhp -and (Test-Path $machinePhp) -and (Test-Path $phar)) {
        $composerCmd = $machinePhp
        $composerPrefix = @($phar)
    }
}

if (-not $composerCmd) {
    $found = Get-Command composer -ErrorAction SilentlyContinue
    if ($found) { $composerCmd = $found.Source }
}

if (-not $composerCmd) {
    throw 'Composer not found: no composer.phar beside composer.json with a PHP this checkout names, and nothing named `composer` on PATH. Set COMPOSER_CA_COMPOSER to a shim or a phar.'
}

Write-Host "CA bundle: $bundle (+$($roots.Count) intercepting root(s))" -ForegroundColor DarkGray

if ($composerPrefix) {
    Write-Host "Composer: $($composerPrefix[0]) (under $composerCmd)" -ForegroundColor DarkGray
}

& $composerCmd @composerPrefix @ComposerArgs
exit $LASTEXITCODE
