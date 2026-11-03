#!/usr/bin/env bash
# =============================================================================
# jb-php.sh - write .idea/php.xml from the template this repository carries
# =============================================================================
#     bash .githooks/jb-php.sh
#
# PhpStorm owns `.idea/php.xml`. It rewrites the file whenever it re-reads an
# interpreter, a tool path or the vendor include paths, and what it writes is this
# machine's answer rather than the project's - which is why the tracked copy is the
# template beside this script, one whose every path is `$PROJECT_DIR$`, and why the
# editor's file is put back to that state rather than edited by hand.
#
# It lives in `.githooks/` beside `jb-vcs.sh`, for the reason that script gives: this
# is the directory the checkout already points git at, and a file in it that is not a
# hook name is never executed by git - it is a program a person runs. The template
# sits beside it, so the two move together.
#
# The write is a replacement, not an edit: the template is copied to a sibling of
# the target and moved over it in one step, so a run that stops halfway leaves the
# file it found rather than half of each.
#
#     exit 0   .idea/php.xml was written from the template
#     exit 1   the template is missing, or .idea/ is not here to write into
# =============================================================================
set -euo pipefail

# The template is this script's own neighbour and the checkout is one directory
# above them both, so neither path is guessed and the script writes the tree it
# lives in whatever directory it is called from - and nothing outside it.
here="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
root="$(dirname -- "$here")"
template="$here/php.xml.template"
target="$root/.idea/php.xml"

if [ ! -d "$root/.idea" ]; then
    printf 'refusing: %s is not here, so there is nowhere to write\n' '.idea/' >&2
    exit 1
fi

if [ ! -f "$template" ]; then
    printf 'refusing: %s is not here, so there is nothing to write\n' '.githooks/php.xml.template' >&2
    exit 1
fi

temporary="$target.tmp.$$"

trap 'rm -f -- "$temporary"' EXIT

cp -- "$template" "$temporary"
mv -f -- "$temporary" "$target"

printf 'wrote %s from %s (%s bytes)\n' '.idea/php.xml' '.githooks/php.xml.template' "$(wc -c < "$target" | tr -d ' ')"
