#!/usr/bin/env bash
# =============================================================================
# handoff/phantom-eol.sh — rebuild the stat-cache phantom, and measure the cures
# =============================================================================
#     bash handoff/phantom-eol.sh
#
# A Windows checkout of the package lists the same files as modified again and
# again, with nothing in them: `git status` says modified, `git diff` says empty,
# and the working copy is byte-identical to the blob `HEAD` holds. It is the
# index's recorded stat that is stale, not the file. This script rebuilds that
# state from scratch, in throwaway repositories under the machine's temp area,
# and then measures what clears it — so the explanation can be checked rather
# than believed. `handoff/HANDOFF.md` section 4 is the same finding in prose,
# with the same two tables.
#
# It touches neither repository. Everything is created under
# `${TMPDIR:-/tmp}/lpr-phantom-eol.$$` and removed on exit.
#
# The reproduction, in one line: a file is staged while it holds CRLF, so the
# index records its size (13 bytes); something then rewrites the file as LF (11
# bytes) — a checkout, a redirect, an editor — and from then on the recorded size
# never matches again. `git diff` stays empty because the content comparison runs
# through the clean filter and lands back on the blob.
# =============================================================================

set -u

scratch="${TMPDIR:-/tmp}/lpr-phantom-eol.$$"
rm -rf "$scratch"
mkdir -p "$scratch" || exit 1
trap 'rm -rf "$scratch"' EXIT

# -c rather than the machine's own identity, so this never depends on, or writes to, a config.
ident=(-c user.name=handoff -c user.email=handoff@example.invalid)

# A repo with the package's own settings, whose index records the CRLF size while
# the file on disk is LF. $1 = directory.
build_phantom() {
    dir="$1"
    rm -rf "$dir"
    mkdir -p "$dir" || return 1
    git -C "$dir" init -q || return 1
    git -C "$dir" config core.autocrlf true
    printf '* text=auto\n' > "$dir/.gitattributes"
    printf 'alpha\nbeta\n' > "$dir/f.txt"
    git -C "$dir" add -A
    git -C "$dir" "${ident[@]}" commit -qm init
    printf 'alpha\r\nbeta\r\n' > "$dir/f.txt"   # staged while CRLF: the index records 13
    git -C "$dir" add -f f.txt
    printf 'alpha\nbeta\n' > "$dir/f.txt"       # rewritten as LF (11): what a checkout leaves
}

# A repo where the index was recorded while the file was already LF — the control.
build_control() {
    dir="$1"
    rm -rf "$dir"
    mkdir -p "$dir" || return 1
    git -C "$dir" init -q || return 1
    git -C "$dir" config core.autocrlf true
    printf '* text=auto\n' > "$dir/.gitattributes"
    printf 'alpha\nbeta\n' > "$dir/f.txt"
    git -C "$dir" add -A
    git -C "$dir" "${ident[@]}" commit -qm init
    printf 'alpha\nbeta\n' > "$dir/f.txt"       # same bytes again: nothing changes
}

listed() { git -C "$1" status --porcelain 2>/dev/null | wc -l | tr -d ' '; }
indexed_size() {
    git -C "$1" ls-files --debug f.txt 2>/dev/null \
        | sed -n 's/.*size: \([0-9]*\).*/\1/p' | head -1
}

echo "handoff/phantom-eol.sh — scratch directory: $scratch"
echo

# ---------------------------------------------------------------------------
# the control: an index recorded while the file was LF has no phantom
# ---------------------------------------------------------------------------
build_control "$scratch/control"
echo "control — the index was recorded while the file was LF"
echo "   listed by git status: $(listed "$scratch/control")   (0 means no phantom)"
echo

# ---------------------------------------------------------------------------
# the phantom itself, examined
# ---------------------------------------------------------------------------
build_phantom "$scratch/phantom"
echo "phantom — the index was recorded while the file was CRLF, then it was rewritten as LF"
echo "   listed by git status:            $(listed "$scratch/phantom")"
echo "   with a real content diff:        $(git -C "$scratch/phantom" diff --name-only | wc -l | tr -d ' ')"
echo "   git diff --numstat (lines):      $(git -C "$scratch/phantom" diff --numstat | wc -l | tr -d ' ')"
echo "   size recorded in the index:      $(indexed_size "$scratch/phantom")"
echo "   size of the file on disk:        $(wc -c < "$scratch/phantom/f.txt" | tr -d ' ')"
echo "   CR bytes in the file:            $(tr -cd '\r' < "$scratch/phantom/f.txt" | wc -c | tr -d ' ')"

if cmp -s <(git -C "$scratch/phantom" show :f.txt) "$scratch/phantom/f.txt"; then
    echo "   index blob vs file on disk:      IDENTICAL BYTES"
else
    echo "   index blob vs file on disk:      DIFFERENT"
fi

echo
echo "   git status says (| marks the start of the line, so the leading space is visible):"
git -C "$scratch/phantom" status --porcelain 2>/dev/null | sed 's/^/   |/'
echo "   ^^ column 1 is the index, column 2 the worktree: a space first means NOT staged."
echo

# ---------------------------------------------------------------------------
# each cure, measured in a fresh repository
# ---------------------------------------------------------------------------
echo "cure             listed           notes"
echo "---------------- ---------------- ----------------------------------------------"

for cure in none add renormalize checkout refresh autocrlf-off; do
    dir="$scratch/cure-$cure"
    build_phantom "$dir"

    before="$(listed "$dir")"

    case "$cure" in
        none)          ;;
        add)           git -C "$dir" add -f f.txt ;;
        renormalize)   git -C "$dir" add --renormalize . 2>/dev/null ;;
        checkout)      git -C "$dir" checkout -- f.txt ;;
        refresh)       git -C "$dir" update-index --refresh >/dev/null 2>&1 ;;
        autocrlf-off)  git -C "$dir" config core.autocrlf false ;;
    esac

    after="$(listed "$dir")"
    staged="$(git -C "$dir" diff --cached --name-only 2>/dev/null | wc -l | tr -d ' ')"
    cr="$(tr -cd '\r' < "$dir/f.txt" | wc -c | tr -d ' ')"

    case "$cure" in
        none)          note="nothing done: the phantom stays until something touches the entry" ;;
        add)           note="rewrites the entry, same blob; staged=$staged CR-in-file=$cr" ;;
        renormalize)   note="same for the whole tree; staged=$staged CR-in-file=$cr" ;;
        checkout)      note="clears it by rewriting the FILE to CRLF; CR-in-file=$cr" ;;
        refresh)       note="prints 'needs update' and exits 1; staged=$staged" ;;
        autocrlf-off)  note="stops future drift, leaves the recorded size alone; staged=$staged" ;;
    esac

    printf '%-16s %-14s %s\n' "$cure" "$before -> $after" "$note"
done

echo
echo "Reading the table: only 'git add' (or 'git add --renormalize') clears it without changing a"
echo "byte of content and without staging anything — a file whose cleaned content already equals the"
echo "blob rewrites the same blob. 'git checkout --' also clears it, but by putting CRLF back on disk."
echo "'git update-index --refresh' and 'core.autocrlf false' do not clear it at all."
echo
echo "The scratch directory has been removed. This package was not touched."
