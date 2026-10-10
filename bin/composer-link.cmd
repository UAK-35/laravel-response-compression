@echo off
rem ===========================================================================
rem  bin\composer-link.cmd
rem
rem  Link this package into a Laravel application and require it, so the
rem  application runs the working copy on disk instead of a tagged release. Edit
rem  the package, refresh the app, no commit in between.
rem
rem  This is a Windows CMD port of the `composer-link` bash function published in
rem  Joel Male's "Installing & testing composer packages locally"
rem  (https://joelmale.com/blog/installing-testing-composer-packages-locally).
rem  It performs the same steps, in the same order, with the same guards:
rem
rem      1. reject a missing argument or one that is not a directory
rem      2. reject a directory with no composer.json
rem      3. read `name` out of that composer.json
rem      4. reject a missing or `null` name
rem      5. composer config repositories.local {"type":"path","url":"<dir>"}
rem      6. composer require <name>:@dev
rem
rem  WHY THE BASH VERSION CANNOT BE REUSED AS-IS
rem  -------------------------------------------
rem  It needs a POSIX shell - `[[ ]]`, a `-d` file test, `$( )` - and `jq`. CMD
rem  has none of those. The harder problem is step 5:
rem  `'{"type": "path", "url": "..."}'` is one argument whose quoting is
rem  processed twice, once by CMD and once by the C runtime that the interpreter
rem  uses, and backslashes are special to the second pass only. The fix here is to
rem  normalise the path to forward slashes before the JSON is built, so the JSON
rem  never contains a bare backslash and the payload needs no escaping of its own.
rem
rem  PHP replaces jq, so this script has no JSON tool of its own to install -
rem  Composer already requires PHP.
rem
rem  WHICH PHP, AND WHICH COMPOSER
rem  ----------------------------
rem  This repository decides which interpreter it runs in one place, so the script
rem  asks that answer before it falls back to PATH: `.agents/render_local.py
rem  --value` reads this machine's PHP out of .agents/machine.local.json. Composer
rem  is taken the same way and in the same order - the composer.phar beside
rem  composer.json, under the interpreter just resolved, and only then whatever is
rem  named `composer` on PATH - because the thing on PATH is often a .bat shim
rem  that runs whichever `php` is first on PATH, which is the second interpreter
rem  this repository refuses elsewhere, and a machine with no `php` on PATH makes
rem  that shim fail with nothing but "The system cannot find the path specified."
rem  COMPOSER_LINK_PHP and COMPOSER_LINK_COMPOSER override both, and neither
rem  machine path is written here: they are asked for at run time.
rem
rem  WHY EVERY EXTERNAL PROGRAM IS INVOKED THROUGH `call`
rem  ---------------------------------------------------
rem  On Windows `php` and `composer` are usually .bat shims rather than the
rem  programs themselves, and invoking a .bat from inside another .bat without
rem  `call` does not return - control transfers to the shim, and when the shim ends
rem  the whole script ends with it. That failure is silent: no error text, no
rem  output, just the shim's exit code. `call` works for .bat, .cmd and .exe alike,
rem  so every external program below goes through it and the problem cannot come
rem  back through a different shim.
rem
rem  The shims are also resolved to their FULL path, never left as a bare name.
rem  A composer shim finds its own phar through its script directory, and that only
rem  resolves when cmd was handed the path to the shim.
rem
rem  USAGE  (run it FROM the Laravel application, never from the package)
rem  -------------------------------------------------------------------
rem      bin\composer-link.cmd packages\laravel-response-compression
rem      bin\composer-link.cmd path\to\another-package --dry-run
rem
rem  The first argument is the package directory. Anything after it is forwarded
rem  verbatim to `composer require`, so --dry-run, -W, --no-scripts and
rem  --prefer-source all work.
rem
rem  ENVIRONMENT  (all optional - each is only an override of what is detected)
rem  -------------------------------------------------------------------------
rem      COMPOSER_LINK_COMPOSER   Composer command. Default: the composer.phar
rem                               beside composer.json, under the interpreter
rem                               below, then `composer` on PATH.
rem      COMPOSER_LINK_PHP        interpreter. Default: the PHP this checkout
rem                               names (.agents/machine.local.json), then on
rem                               PATH.
rem      COMPOSER_LINK_JQ         jq executable. Consulted only when no PHP was
rem                               found at all.
rem
rem  EXIT CODES
rem  ----------
rem      0   the package is linked and required
rem      1   a guard failed, or composer did
rem
rem  WHAT IT LEAVES BEHIND
rem  ---------------------
rem      composer.json    gains a `repositories.local` entry
rem      composer.lock    records the package at its dev version
rem      vendor\<name>    a directory junction pointing at the package, or a
rem                       full copy where symlinks are not permitted
rem
rem  To undo the whole thing:
rem      composer remove <name>
rem      composer config --unlink repositories.local
rem
rem  This is a Windows convenience for whoever works on this repository rather
rem  than part of the package: `.gitattributes` `export-ignore`s it so no consumer
rem  receives it, and docs/scripts.md is where it is listed.
rem ===========================================================================

setlocal EnableExtensions EnableDelayedExpansion

set "NAME_TMP=%TEMP%\composer-link-%RANDOM%%RANDOM%.name"

rem -- arguments -------------------------------------------------------------
if "%~1"==""          goto :usage
if /i "%~1"=="--help" goto :help
if /i "%~1"=="-h"     goto :help
if /i "%~1"=="/?"     goto :help

set "PKG_ARG=%~1"

rem `shift` does not touch the all-arguments token, so the tail of the command
rem line is collected into EXTRA by hand. The loop is label-based rather than
rem parenthesised, because a block would freeze the first argument as it was
rem before the shift.
shift
:collect_extra
if "%~1"=="" goto :collected_extra
set "EXTRA=!EXTRA! %~1"
shift
goto :collect_extra
:collected_extra

rem The full-path modifier of FOR collapses dotted and slash-separated forms into
rem one absolute Windows path. A directory path usually carries a trailing
rem separator; drop it so the path can be quoted safely later on.
for %%I in ("%PKG_ARG%") do set "PKG=%%~fI"
if "!PKG:~-1!"=="\" set "PKG=!PKG:~0,-1!"

rem -- 1. the argument is a directory ----------------------------------------
if not exist "%PKG%\" (
    echo ERROR: not a directory: %PKG_ARG%
    goto :fail
)

rem -- 2. it holds a composer.json -------------------------------------------
set "PKG_COMPOSER_JSON=%PKG%\composer.json"
if not exist "%PKG_COMPOSER_JSON%" (
    echo ERROR: composer.json not found in %PKG%
    goto :fail
)

rem -- the current directory is the application ------------------------------
if not exist "composer.json" (
    echo ERROR: no composer.json in %CD%
    echo        Run composer-link from the root of the Laravel application.
    goto :fail
)
if /i "%PKG%"=="%CD%" (
    echo ERROR: the package directory and the application directory are the same.
    goto :fail
)

rem -- 3. read `name` out of the package's composer.json ---------------------
rem Done before anything is written, so a package that cannot be named never
rem gets half-linked.
call :resolve_php
call :resolve_jq
set "NAME="
call :read_name
if not defined NAME call :read_name_with_jq

rem -- 4. the name is present and looks like vendor/package ------------------
if not defined NAME (
    echo ERROR: no usable `name` in %PKG_COMPOSER_JSON%
    echo        Set COMPOSER_LINK_PHP, or COMPOSER_LINK_JQ when PHP is absent.
    goto :fail
)
if /i "!NAME!"=="null" goto :bad_name

set "NAME_OK="
for /f "tokens=1,2 delims=/" %%A in ("!NAME!") do (
    if not "%%A"=="" if not "%%B"=="" set "NAME_OK=1"
)
if not defined NAME_OK goto :bad_name

rem -- 5. point the application at the package's directory -------------------
call :resolve_composer
if not defined COMPOSER_CMD (
    echo ERROR: composer was not found: not on PATH, and no composer.phar
    echo        beside composer.json.
    echo        Set COMPOSER_LINK_COMPOSER to a shim or a phar.
    goto :fail
)

rem JSON has no bare backslash, so the path is normalised to forward slashes
rem first. Composer resolves either form on Windows, and the payload then
rem survives CMD's quoting pass untouched. The escapes below are for the C
rem runtime that parses the interpreter's argv, not for CMD.
set "PKG_URL=%PKG:\=/%"
set "REPO_JSON={\"type\": \"path\", \"url\": \"%PKG_URL%\"}"

echo Linking %PKG_URL%
echo   into  %CD%
call "!COMPOSER_CMD!" !COMPOSER_PREFIX! config repositories.local "%REPO_JSON%" --file composer.json
if errorlevel 1 (
    echo ERROR: composer config repositories.local failed.
    goto :fail
)

rem -- 6. require the package at its dev version -----------------------------
echo Requiring !NAME!:@dev
call "!COMPOSER_CMD!" !COMPOSER_PREFIX! require "!NAME!:@dev" --no-interaction !EXTRA!
if errorlevel 1 (
    echo.
    echo ERROR: composer could not require "!NAME!:@dev".
    echo        The path repository is already registered in composer.json, so
    echo        the failure can be retried with a bare `composer require`.
    goto :fail
)

echo.
echo Linked and required: !NAME!
echo   package  %PKG%
echo   app      %CD%
echo   vendor   vendor\!NAME:/=\!
echo.
echo Edits to the package are live from now on - no commit, no update.
echo To undo:
echo   composer remove !NAME!
echo   composer config --unlink repositories.local

del "%NAME_TMP%" 2>nul
endlocal & exit /b 0

rem -- failures --------------------------------------------------------------

:bad_name
echo ERROR: "%NAME%" is not a vendor/package name in %PKG_COMPOSER_JSON%
goto :fail

:fail
del "%NAME_TMP%" 2>nul
endlocal & exit /b 1

rem -- helpers (never fallen into: everything above ends in exit /b) ---------

rem Reads .name with the PHP that Composer needs anyway. Prints nothing when the
rem file is unreadable or the key is missing, which leaves NAME undefined and is
rem how the caller detects failure.
:read_name
if not defined PHP_CMD goto :eof
call "%PHP_CMD%" -r "echo @json_decode(file_get_contents($argv[1]))->name;" "%PKG_COMPOSER_JSON%" > "%NAME_TMP%" 2>nul
if errorlevel 1 goto :eof
for /f "usebackq delims=" %%N in ("%NAME_TMP%") do if not defined NAME set "NAME=%%N"
goto :eof

:read_name_with_jq
if not defined JQ_CMD goto :eof
call "%JQ_CMD%" -r ".name // empty" "%PKG_COMPOSER_JSON%" > "%NAME_TMP%" 2>nul
if errorlevel 1 goto :eof
for /f "usebackq delims=" %%N in ("%NAME_TMP%") do if not defined NAME set "NAME=%%N"
goto :eof

rem The first rung is this checkout's own answer, asked of the one program that
rem decides it: `.agents/render_local.py --value` reads the interpreter out of
rem .agents/machine.local.json. PATH and the common shim directories are the
rem fallbacks for a machine that has not run `.agents/render_local.py --init`.
rem
rem The PATH modifier of FOR returns the first match on PATH as a fully
rem qualified path, extension included. That is what keeps a composer shim able to
rem find its own phar. When nothing matches it expands to empty, which `if
rem defined` reads as an unset variable, so the next candidate is tried.
:resolve_php
set "PHP_CMD="
if defined COMPOSER_LINK_PHP set "PHP_CMD=%COMPOSER_LINK_PHP%"
if defined PHP_CMD goto :eof
if exist "%~dp0..\.agents\render_local.py" for /f "usebackq delims=" %%P in (`python "%~dp0..\.agents\render_local.py" --value __PHP_EXE__ 2^>nul`) do if not defined PHP_CMD set "PHP_CMD=%%P"
if defined PHP_CMD goto :eof
for %%P in (php) do set "PHP_CMD=%%~$PATH:P"
if defined PHP_CMD goto :eof
if exist "%USERPROFILE%\.config\herd\bin\php84.bat" set "PHP_CMD=%USERPROFILE%\.config\herd\bin\php84.bat"
if defined PHP_CMD goto :eof
if exist "%USERPROFILE%\.config\herd\bin\php.bat" set "PHP_CMD=%USERPROFILE%\.config\herd\bin\php.bat"
goto :eof

:resolve_jq
set "JQ_CMD="
if defined COMPOSER_LINK_JQ set "JQ_CMD=%COMPOSER_LINK_JQ%"
if defined JQ_CMD goto :eof
for %%J in (jq) do set "JQ_CMD=%%~$PATH:J"
goto :eof

rem This repository's own fallback is two words - the composer.phar beside
rem composer.json, under the interpreter `.agents/` names - so the program and the
rem argument it takes are held apart: COMPOSER_CMD is always one quoted path, and
rem COMPOSER_PREFIX is empty or one quoted path before the real arguments. Putting
rem both in one variable works until `call` re-parses the line, which is where the
rem quotes stop meaning what they meant.
:resolve_composer
set "COMPOSER_CMD="
set "COMPOSER_PREFIX="
if defined COMPOSER_LINK_COMPOSER set "COMPOSER_CMD=%COMPOSER_LINK_COMPOSER%"
if defined COMPOSER_CMD goto :eof
for %%C in (composer) do set "COMPOSER_CMD=%%~$PATH:C"
if defined COMPOSER_CMD goto :eof
if exist "%~dp0..\composer.phar" if defined PHP_CMD set "COMPOSER_CMD=%PHP_CMD%"
if defined COMPOSER_CMD if exist "%~dp0..\composer.phar" set "COMPOSER_PREFIX="%~dp0..\composer.phar""
goto :eof

rem -- usage -----------------------------------------------------------------

:usage
echo Usage: composer-link ^<path-to-package-directory^> [composer require args]
echo.
echo Run it from the root of the Laravel application, not from the package.
echo See --help for the full description.
endlocal & exit /b 1

:help
echo composer-link - run a local package inside a Laravel application.
echo.
echo Usage:
echo   bin\composer-link.cmd ^<path-to-package-directory^> [composer require args]
echo.
echo Examples:
echo   bin\composer-link.cmd packages\laravel-response-compression
echo   bin\composer-link.cmd path\to\another-package --dry-run
echo.
echo The directory is registered as a path repository in the application's
echo composer.json under the name `local`, the package is required at its dev
echo version, and composer symlinks it into vendor. Everything after the
echo directory is forwarded to `composer require`.
echo.
echo The interpreter and Composer are this checkout's own answers first - the PHP
echo .agents/machine.local.json names, and the composer.phar beside composer.json
echo - then whatever is on PATH.
echo.
echo Exit codes: 0 linked and required, 1 a guard or composer failed.
echo.
echo Environment overrides: COMPOSER_LINK_COMPOSER, COMPOSER_LINK_PHP,
echo COMPOSER_LINK_JQ.
echo.
echo Undo with `composer remove ^<name^>` and
echo `composer config --unlink repositories.local`.
endlocal & exit /b 0
