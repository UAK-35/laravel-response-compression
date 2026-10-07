#!/usr/bin/env bash
# =============================================================================
# handoff/state.sh — what this package looks like right now
# =============================================================================
# Read-only. It runs git queries and prints them: it stages nothing, commits
# nothing, changes no configuration and writes no file anywhere.
#
#     bash handoff/state.sh
#
# The root is derived from this script's own location, so nothing here names a
# machine and the script runs from any directory. This file sits in
# `<package>/handoff`, so `<package>` — the repository root, the directory that
# holds AGENTS.md and bin/ — is one level up from here.
#
# What to read it for:
#
#   index:                    the `N staged` count and `identical to HEAD` are
#                             the answer to "is anything staged?" — not the
#                             `N listed` count beside them.
#   reports with no content   the paths a Windows checkout lists as modified
#   difference                while they hold nothing at all.
#                             handoff/HANDOFF.md section 4 explains them: the
#                             index recorded a CRLF size before the file was
#                             rewritten LF, so `git status` says modified and
#                             `git diff` says empty. They are not work; nothing
#                             should be staged, committed or reverted for them.
#   worktree:  N with a real  those are the only modified paths that carry an
#   content diff              edit.
#
# The script is deliberately quiet about any other repository: a path that
# reaches out of this package is refused by this package's own guards.
# =============================================================================

set -u

case "${1:-}" in
    -h|--help)
        echo "usage: bash handoff/state.sh"
        echo
        echo "Prints, read-only: the branch and HEAD, how far ahead of its upstream this"
        echo "branch is, whether anything is staged, which modified paths carry a real"
        echo "content diff, how many are stat-cache reports with nothing in them, the"
        echo "machine file and gate files this package needs, and the line endings of"
        echo "CHANGELOG.md — which bin/commit.php can only write into while it is LF."
        exit 0
        ;;
esac

here="$(cd "$(dirname "$0")" && pwd)" || exit 1
root="$(cd "$here/.." && pwd)" || exit 1

if ! git -C "$root" rev-parse --git-dir >/dev/null 2>&1; then
    echo "handoff/state.sh: $root is not a git repository — nothing to report."
    exit 1
fi

echo "handoff/state.sh — read-only; nothing below is written by this run"
echo

# git answers this one in the platform's spelling, so compare it as a directory
# rather than as a string.
toplevel="$(git -C "$root" rev-parse --show-toplevel 2>/dev/null)"
toplevel="$(cd "$toplevel" 2>/dev/null && pwd)" || toplevel=""

echo "== this package =="
echo "   path:     $root"
if [ -n "$toplevel" ] && [ "$toplevel" != "$root" ]; then
    echo "   the repository's top level is: $toplevel (handoff/ is not at its root)"
fi

branch="$(git -C "$root" rev-parse --abbrev-ref HEAD 2>/dev/null)"
head="$(git -C "$root" log -1 --format='%h %s' 2>/dev/null)"
upstream="$(git -C "$root" rev-parse --abbrev-ref '@{u}' 2>/dev/null || true)"

if [ -n "$upstream" ]; then
    counts="$(git -C "$root" rev-list --left-right --count "$upstream...HEAD" 2>/dev/null)"
    ahead="$(printf '%s' "$counts" | awk '{print $2}')"
    behind="$(printf '%s' "$counts" | awk '{print $1}')"
    position="$ahead ahead / $behind behind $upstream"
else
    position="no upstream configured"
fi

staged="$(git -C "$root" diff --cached --name-only 2>/dev/null | wc -l | tr -d ' ')"
listed="$(git -C "$root" status --porcelain 2>/dev/null | wc -l | tr -d ' ')"
content="$(git -C "$root" diff --name-only 2>/dev/null | wc -l | tr -d ' ')"
worktree_only="$(git -C "$root" status --porcelain 2>/dev/null | sed -n 's/^ M //p' | wc -l | tr -d ' ')"
untracked="$(git -C "$root" status --porcelain 2>/dev/null | grep -c '^??' || true)"
stashes="$(git -C "$root" stash list 2>/dev/null | wc -l | tr -d ' ')"

if git -C "$root" diff --cached --quiet 2>/dev/null; then
    index_state="identical to HEAD"
else
    index_state="DIFFERS from HEAD"
fi

reports=$((worktree_only - content))

echo "   branch:   $branch"
echo "   HEAD:     $head"
echo "   position: $position"
echo "   index:    $staged staged; index $index_state"
echo "   worktree: $listed listed — $content with a real content diff, $untracked untracked"
echo "   reports with no content difference: $reports   (HANDOFF.md section 4; not work)"
echo "   stashes:  $stashes"

# The tell for the reports above: the size the index recorded against the size
# the file has now. A pair that disagrees is the stat cache, not an edit.
if [ "$reports" -gt 0 ]; then
    echo
    echo "   the first few, with the size the index recorded and the size on disk:"
    git -C "$root" status --porcelain 2>/dev/null | sed -n 's/^ M //p' | head -5 | while IFS= read -r path; do
        [ -n "$path" ] || continue
        recorded="$(git -C "$root" ls-files --debug -- "$path" 2>/dev/null | sed -n 's/.*size: \([0-9]*\).*/\1/p' | head -1)"
        disk="$(wc -c < "$root/$path" 2>/dev/null | tr -d ' ')"
        printf '   %-58s index %s, disk %s\n' "$path" "${recorded:-?}" "${disk:-?}"
    done
fi

echo
echo "== the files this package runs on =="
for f in .agents/machine.local.json .agents/render_local.py .agents/php-bin.sh .agents/verify.py \
         .agents/toolchain.json .githooks/pre-commit bin/checks.php bin/commit.php bin/index.php \
         bin/coverage.php bin/tool.php bin/tool-paths.php composer.phar; do
    if [ -f "$root/$f" ]; then
        printf '   %-30s present\n' "$f"
    else
        printf '   %-30s MISSING\n' "$f"
    fi
done

hooks_path="$(git -C "$root" config core.hooksPath 2>/dev/null || true)"
if [ -n "$hooks_path" ]; then
    echo "   core.hooksPath: $hooks_path (this package's own gate is wired)"
else
    echo "   core.hooksPath: unset — no hook runs on a commit in this repository"
fi

if [ -f "$root/CHANGELOG.md" ]; then
    cr="$(tr -cd '\r' < "$root/CHANGELOG.md" | wc -c | tr -d ' ')"
    if [ "$cr" = "0" ]; then
        echo "   CHANGELOG.md: LF on disk (0 CR bytes) — bin/commit.php can write a bullet into it"
    else
        echo "   CHANGELOG.md: $cr CR bytes — CRLF, which bin/commit.php cannot write into (HANDOFF.md section 7 item 1)"
    fi
fi

echo
if [ -f "$here/HANDOFF.md" ]; then
    echo "context:   $here/HANDOFF.md — read it before changing anything."
else
    echo "context:   HANDOFF.md is not beside this script — it has been moved or copied."
fi
