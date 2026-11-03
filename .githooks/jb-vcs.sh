#!/usr/bin/env bash
#
# Render `.idea/vcs.xml` from `.githooks/vcs.xml.template`.
#
# WHY THIS EXISTS
# ---------------
# `.idea/vcs.xml` is the editor's own file and is not tracked: the editor rewrites it, and while
# `build/` holds scratch copies that are themselves git repositories — which is what a killed
# `verify.py --selftest` leaves behind — it registers one `<mapping>` per copy. Left alone that is a
# file of directories that existed for the length of one run, read back as if they were checkouts.
# The artifact this repository carries is the template beside this script: the one mapping that is
# true in every checkout, the repository itself. This script writes the editor's copy back from it,
# and the `.idea` rule in `.gitignore` keeps that copy out of a commit.
#
# It lives in `.githooks/` because that is the directory this checkout already points git at, and it
# is named `jb-vcs.sh` rather than after a hook: a file in that directory that is not a hook name is
# never executed by git, and this is a program a person runs rather than a step of a checkout. The
# template sits beside it, so the two move together.
#
# The template is copied byte for byte rather than rebuilt from parts, so a render with nothing to
# do is one `cmp` rather than a rewrite. That is also why the template carries no trailing newline:
# the editor writes this file ending at `>`, and a template that ended with a newline would make
# every run a one-byte change to a file nobody reads for its bytes.
#
# WHAT IT REFUSES
# ---------------
# A template that is not there, one with no VcsDirectoryMappings component, and a mapping whose
# directory is absolute, drive-lettered or climbs out of this checkout — the three ways a scratch
# path gets written down as if it were a property of the repository.
#
# USAGE
# -----
#   bash .githooks/jb-vcs.sh           # write .idea/vcs.xml from the template
#   bash .githooks/jb-vcs.sh --check   # report the drift and write nothing; exit 1 when there is any
set -euo pipefail

# The template is this script's own neighbour and the checkout is one directory above them both, so
# neither path is guessed and the script renders the tree it lives in.
here="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
root="$(dirname -- "$here")"
template="$here/vcs.xml.template"
output="$root/.idea/vcs.xml"

check=0

for argument in "$@"; do
    case "$argument" in
        --check) check=1 ;;
        --help | -h)
            sed -n '/^# USAGE$/,/--check   #/p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *)
            printf 'unknown argument: %s\n' "$argument" >&2
            exit 2
            ;;
    esac
done

if [ ! -f "$template" ]; then
    printf 'refusing: %s is not here, so there is nothing to render from\n' "$template" >&2
    exit 1
fi

if ! grep -q '<component name="VcsDirectoryMappings">' "$template"; then
    printf 'refusing: %s has no VcsDirectoryMappings component\n' "$template" >&2
    exit 1
fi

# A mapping names a directory the editor is told to treat as a git root. The empty string is this
# checkout; anything else has to be a relative path that stays inside it.
while IFS= read -r directory; do
    case "$directory" in
        '') continue ;;
        '..' | ../* | */../* | */.. | /* | [A-Za-z]:*)
            printf 'refusing: %s maps a directory outside this checkout: %s\n' "$template" "$directory" >&2
            exit 1
            ;;
    esac
done < <(sed -n 's/.*<mapping directory="\([^"]*\)".*/\1/p' "$template")

if [ -f "$output" ] && cmp -s -- "$template" "$output"; then
    printf '.idea/vcs.xml already matches .githooks/vcs.xml.template\n'
    exit 0
fi

if [ "$check" -eq 1 ]; then
    printf '.idea/vcs.xml differs from .githooks/vcs.xml.template; run: bash .githooks/jb-vcs.sh\n' >&2
    exit 1
fi

cp -- "$template" "$output"
printf 'wrote .idea/vcs.xml from .githooks/vcs.xml.template\n'
