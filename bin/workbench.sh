#!/usr/bin/env bash
# =============================================================================
# bin/workbench.sh — a throwaway copy of this checkout, with a vendor of its own
# =============================================================================
#     bash bin/workbench.sh [--into DIR] [-- composer install args…]
#
# An experiment here is a reading taken on a tree — a suite run, a tool under a
# pool of another width, a break planted on purpose — and the reading is worth
# something only while the tree it was taken on is the tree it is attributed to.
# A copy is how that is arranged, and two things quietly break it:
#
#   * a vendor/ that is not the copy's own. `bin/composer-link.cmd` leaves a
#     directory junction in an application, and a checkout that has been linked —
#     or whose vendor was shared with another tree to save an install — has a
#     vendor that resolves somewhere else. A copy carrying it would read that
#     tree's bytes and report them as its own.
#   * a link anywhere the copy would carry. A recursive copy follows one: a link
#     back into this checkout copies the tree into itself, and a link out of it
#     puts another tree's bytes inside a copy that looks whole.
#
# So the copy is built from the files a commit would carry — `git ls-files
# --cached --others --exclude-standard`, the set the staged gate reads — and never
# from vendor/, which the copy is given by running this checkout's own Composer
# against its own lock. The paths that would be carried are asked about links
# first, and the run refuses when there is one, naming it; the vendor the install
# produces is read back afterwards, and a copy whose vendor is not inside the copy
# is refused rather than handed over. `git clone --no-hardlinks` is what gives the
# copy a history of its own, and the clone's `origin` is removed, so nothing in
# the copy reaches back into the tree it was made from.
#
# The three ignored files that decide how the copy behaves travel with it: the
# machine record (which interpreter), `composer.lock` (which versions) and
# `composer.phar` (which Composer). Everything else ignored stays behind — a
# workbench is meant to be small enough to throw away.
#
# Which interpreter the copy is installed under is not decided here: `sh
# .agents/php-bin.sh --report` answers that for this checkout, so the copy gets
# the PHP this checkout runs, whichever rung of its ladder answered.
#
#     exit 0   the copy was built, and its path is the last line of stdout
#     exit 1   a guard refused, or git or Composer failed
# =============================================================================

set -u
set -o pipefail

here="$(cd "$(dirname "$0")" && pwd)" || exit 1
root="$(dirname "$here")"

usage() {
    echo "usage: bash bin/workbench.sh [--into DIR] [-- composer install args…]"
    echo
    echo "  --into DIR   build the copy in DIR, which has to be empty and outside"
    echo "               this checkout. Default: a fresh directory under the"
    echo "               machine's temp area."
    echo "  --           everything after it is handed to \`composer install\`."
}

dest=""
while [ $# -gt 0 ]; do
    case "$1" in
        -h|--help) usage; exit 0 ;;
        --into)
            if [ $# -lt 2 ]; then
                echo "workbench: --into needs a directory." >&2
                exit 1
            fi
            dest="$2"
            shift 2
            ;;
        --into=*) dest="${1#--into=}"; shift ;;
        --) shift; break ;;
        *) echo "workbench: unknown argument: $1 (composer arguments go after --)." >&2; exit 1 ;;
    esac
done
install_args=("$@")

if ! git -C "$root" rev-parse --git-dir >/dev/null 2>&1; then
    echo "workbench: $root is not a git repository, so there is no checkout here to copy." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# what the copy would carry, and the links in it
# ---------------------------------------------------------------------------
carried="$(git -C "$root" ls-files -z --cached --others --exclude-standard | tr '\0' '\n')" || exit 1

# A directory is what a recursive copy walks through, so a junction on one matters as much as a
# symlinked file does: every carried path has its directory taken off and is asked about as well.
travelled="$(printf '%s\n%s\n' "$carried" "$(printf '%s\n' "$carried" | sed 's|/[^/]*$||' | sort -u)" | sort -u)"
linked="$(printf '%s\n' "$travelled" | while IFS= read -r path; do
    if [ -n "$path" ] && [ -L "$root/$path" ]; then
        printf '%s\n' "$path"
    fi
done)"

if [ -n "$linked" ]; then
    echo "workbench: refusing — the copy would carry a link, and a link is another tree's bytes:" >&2
    printf '%s\n' "$linked" | while IFS= read -r path; do
        printf '  %s -> %s\n' "$path" "$(readlink "$root/$path" 2>/dev/null)" >&2
    done
    echo "Nothing was created. Remove the link, or make the copy by hand and say which tree the reading belongs to." >&2
    exit 1
fi

# The source's vendor is never carried, so a link in it cannot reach the copy — but it is the reason
# the copy's vendor is not the bytes this checkout reads, and that is worth naming rather than
# leaving to whoever notices the install doing work.
vendor_links="$(find "$root/vendor" -maxdepth 3 -type l 2>/dev/null | head -5)"
if [ -n "$vendor_links" ]; then
    echo "workbench: note — this checkout's vendor/ is, or holds, a link, and the copy does not carry it:"
    printf '%s\n' "$vendor_links" | while IFS= read -r path; do
        printf '  %s -> %s\n' "${path#"$root"/}" "$(readlink "$path" 2>/dev/null)"
    done
    echo "  the copy installs what composer.lock names, into a vendor of its own."
fi

# ---------------------------------------------------------------------------
# the copy itself
# ---------------------------------------------------------------------------
if [ -n "$dest" ]; then
    mkdir -p "$dest" || { echo "workbench: could not make $dest." >&2; exit 1; }
    dest="$(cd "$dest" && pwd -P)"
    if [ -n "$(ls -A "$dest" 2>/dev/null)" ]; then
        echo "workbench: refusing — $dest already holds something, and a copy is built into an empty directory." >&2
        exit 1
    fi
else
    dest="$(mktemp -d "${TMPDIR:-/tmp}/lpr-workbench.XXXXXX")" \
        || { echo "workbench: could not make a directory under ${TMPDIR:-/tmp}." >&2; exit 1; }
    dest="$(cd "$dest" && pwd -P)"
fi

case "$dest" in
    "$root" | "$root"/*)
        rmdir "$dest" 2>/dev/null
        echo "workbench: refusing — $dest is inside this checkout, and a copy belongs outside the tree it copies." >&2
        exit 1
        ;;
esac

git clone --quiet --no-hardlinks "$root" "$dest" \
    || { echo "workbench: git clone failed, so nothing was built." >&2; rmdir "$dest" 2>/dev/null; exit 1; }
git -C "$dest" remote remove origin >/dev/null 2>&1 || true

# The tree as it stands: the clone holds HEAD's bytes, and the carried set now replaces them —
# including the paths this tree has deleted, which the clone would otherwise still be showing.
git -C "$root" ls-files -z --cached --others --exclude-standard | tar -C "$root" --null -T - -cf - \
    | tar -C "$dest" -xf - \
    || { echo "workbench: copying the tree into $dest failed; it is left there." >&2; exit 1; }
git -C "$root" ls-files -z --deleted | xargs -0 -r -I{} rm -f "$dest/{}"

for path in .agents/machine.local.json composer.lock composer.phar; do
    if [ -f "$root/$path" ]; then
        mkdir -p "$dest/$(dirname "$path")"
        cp "$root/$path" "$dest/$path"
    fi
done

# ---------------------------------------------------------------------------
# a vendor of the copy's own
# ---------------------------------------------------------------------------
report="$(sh "$root/.agents/php-bin.sh" --report 2>/dev/null || true)"
interpreter="$(printf '%s' "$report" | cut -f2-)"

if [ -z "$interpreter" ]; then
    echo "workbench: no interpreter — sh .agents/php-bin.sh answers none, and the copy cannot be installed without one." >&2
    exit 1
fi

if [ -f "$dest/composer.phar" ]; then
    composer=("$interpreter" "$dest/composer.phar")
elif [ -n "$(command -v composer 2>/dev/null)" ]; then
    composer=("$(command -v composer)")
else
    echo "workbench: no Composer — not the composer.phar beside composer.json, not on PATH." >&2
    exit 1
fi

echo "workbench: installing into $dest"
"${composer[@]}" install --no-interaction --working-dir "$dest" "${install_args[@]}" \
    || { echo "workbench: composer install failed; the copy is left at $dest." >&2; exit 1; }

vendor="$(cd "$dest/vendor" 2>/dev/null && pwd -P)"
if [ -z "$vendor" ]; then
    echo "workbench: refusing — the install left no vendor/ in $dest, so it is not a tree an experiment can run in." >&2
    exit 1
fi

case "$vendor" in
    "$dest"/*) ;;
    *)
        echo "workbench: refusing — the copy's vendor resolves to $vendor, which is outside $dest: a reading taken there would belong to that tree." >&2
        exit 1
        ;;
esac

if [ ! -f "$dest/vendor/autoload.php" ]; then
    echo "workbench: refusing — $vendor holds no autoload.php, so nothing in the copy can be loaded." >&2
    exit 1
fi

echo
echo "workbench: $dest"
echo "  interpreter:   $interpreter"
echo "  version:       $("$interpreter" -r 'echo PHP_VERSION;' 2>/dev/null)"
echo "  composer:      $("${composer[@]}" --version --no-ansi 2>/dev/null | head -1)"
echo "  vendor:        $vendor"
echo "  vendor files:  $(find "$dest/vendor" -type f 2>/dev/null | wc -l | tr -d ' ')"
echo "  origin:        removed — nothing in the copy reaches this checkout"
echo
echo "  the tree stands as this checkout's does, uncommitted work included, and the history is the"
echo "  clone's own. Remove it with: rm -rf $dest"
echo "$dest"
