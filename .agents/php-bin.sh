#!/bin/sh
# =============================================================================
# .agents/php-bin.sh — which PHP this repository runs
# =============================================================================
# Four things in this checkout need an interpreter, and each of them used to
# decide for itself: the pre-commit hook looked in four places, the workflow
# installed a version written into it, `bin/tool.php` ran whatever started it,
# and `bin/coverage.php` measured its floors under whatever Composer runs. Four
# answers to one question is the failure this package writes guards about, so
# the question is answered here instead and everything else asks.
#
#   sh .agents/php-bin.sh            the interpreter, as a path
#   sh .agents/php-bin.sh --why      which rung answered: the environment,
#                                    .agents/machine.local.json, git config, PATH
#   sh .agents/php-bin.sh --report   the rung and the value on one line, for a
#                                    program that reads both (Tests\Support\PhpBinary)
#   sh .agents/php-bin.sh --show     the same, for a reader, with the version CI
#                                    installs
#   sh .agents/php-bin.sh --pin      the version composer.json requires, as the
#                                    workflow installs it: 8.4
#   sh .agents/php-bin.sh --matrix   that version as a GitHub Actions matrix:
#                                    ["8.4"]
#   sh .agents/php-bin.sh --selftest plant a fault per rung and prove each one is
#                                    still answered, in a throwaway tree
#
# Exit is 0 when it named an interpreter, 1 when it could not - with the two
# lines that would give this machine one - and 2 for a usage error.
#
# THE ORDER, AND WHY IT IS THIS ORDER
# -----------------------------------
#   1. $RESPONSE_COMPRESSION_PHP - an override for one command rather than a
#      setting. Taken as given: a person who typed it has decided.
#   2. `.agents/machine.local.json` - the one file in this repository that names
#      this machine's PHP, read through `python .agents/render_local.py --value
#      __PHP_EXE__`. It is read through the renderer rather than parsed here,
#      because the shape of that file is the renderer's to own: a second reader
#      would come to disagree with it about a key.
#   3. `git config --get response-compression.php` - how a clone configured
#      before this file existed still works.
#   4. `php` (or `php.exe`) on PATH, answered as a path rather than a name.
#
# (2) is the answer to "which PHP is this repository using", and the settings
# files a tool reads, the hook and this file all resolve the same one, so
# replacing this machine's PHP is one edit to that file. (3) and (4) exist so
# that a clone which has not run `--init` still commits, and they are fallbacks
# rather than declarations - which is the distinction `Tests\Support\PhpBinary`
# reads when it decides whether a disagreement is worth a word: a run measured
# under an interpreter other than the one this machine names is worth one, and a
# run measured under the first `php` on a runner's PATH is not.
#
# WHY IT IS A SHELL SCRIPT
# ------------------------
# Because the hook needs an interpreter before it can run one, so the resolver
# cannot itself be PHP. It is POSIX sh with no dependency of its own beyond the
# renderer it drives, and PHP asks it through `sh` where PHP needs the same
# answer - `Tests\Support\PhpBinary` is that side of it. A machine with no `sh`
# is a machine where the PHP side reports the interpreter as unknown rather than
# guessing, which is why that answer is a `?` and not a `false`.
#
# `--root` names the tree to resolve for, and is how the selftest asks about a
# tree this file describes rather than about this checkout. Nothing else needs
# it: every caller stands in the tree it is asking about.
#
# WHY THE ROOT IS ENTERED RATHER THAN PREFIXED
# --------------------------------------------
# Every command below that is not a shell builtin runs from *inside* the tree,
# naming what is beside it, rather than being handed `$root/...`. A path is a
# spelling a shell agrees with, not a fact a Windows program can open: the
# interpreter behind `python` is a native binary that cannot open the `/e/...`
# this checkout's own shell calls it by, and `git -C /tmp/x` fails where `git`
# beside `x` does not. That is the same mistake as a drive path written into a
# script, one layer down - the path resolves for whoever wrote it and for nobody
# else - so the resolution happens from inside the tree and the only absolute
# path this file ever produces is the one it was told to, or one the renderer
# answered with.
#
# Every variable below the options is prefixed `pb_`. POSIX sh has no `local`,
# so a helper that named a variable `value` would be writing the caller's - and
# the selftest, which calls these helpers about a dozen fixture trees in one
# process, is where that shows up as a path that grows a directory per call.
# =============================================================================

set -u

NAME="php-bin"
home="$(CDPATH= cd -- "$(dirname -- "$0")" 2>/dev/null && pwd)" || home=""
root="$(CDPATH= cd -- "$home/.." 2>/dev/null && pwd)" || root=""

# This file, by absolute path: the selftest runs about twenty children, and one
# of them has to run with a PATH that holds nothing at all - which is how the
# last rung is asked about a machine that has no PHP on it.
script="${home}/${NAME}.sh"

# Said as what is missing rather than as what failed: this is the line a reader
# acts on, and it names the one command that writes the file behind it.
refuse() {
    echo "$NAME: no PHP interpreter found, so nothing that needs one can run." >&2
    echo "  name one with: python .agents/render_local.py --init" >&2
    echo "  (for one command only: RESPONSE_COMPRESSION_PHP=<path to php>)" >&2
}

usage() {
    cat <<'USAGE'
Usage: sh .agents/php-bin.sh [--why | --report | --show | --pin | --matrix | --selftest] [--root DIR]

  (no option)  the interpreter this repository runs, as a path
  --why        which rung answered, for a reader
  --report     the rung and the value on one line, tab separated
  --show       the value, the rung and the version CI installs, as a block
  --pin        the PHP version composer.json requires, as a version
  --matrix     that version as a GitHub Actions matrix
  --selftest   plant a fault per rung and prove each one is still answered
  --root DIR   resolve against DIR instead of this checkout
USAGE
}

# The first of `python`, `python3`, `py` that is on PATH, or nothing. The same
# three names `render_local.py` is reached by everywhere else - and asked for by
# `command -v` rather than assumed, because a machine with only one of them is
# the machine this is written for.
python_bin() {
    for candidate in python python3 py; do
        if command -v "$candidate" >/dev/null 2>&1; then
            echo "$candidate"

            return 0
        fi
    done

    return 1
}

# The token's value, or nothing when the renderer cannot answer it. The answer
# is tested as a file rather than as a string: the renderer explains what is
# missing in a sentence when it cannot resolve a token, and only a path is a
# program.
from_machine_file() {
    if [ ! -f "$root/.agents/machine.local.json" ] || [ ! -f "$root/.agents/render_local.py" ]; then
        return 1
    fi

    pb_python="$(python_bin)" || return 1
    pb_answer="$(cd "$root" 2>/dev/null && "$pb_python" .agents/render_local.py --value __PHP_EXE__ 2>/dev/null || true)"

    if [ -z "$pb_answer" ] || [ ! -f "$pb_answer" ]; then
        return 1
    fi

    echo "$pb_answer"
}

# The requirement composer.json states, as a version a workflow can install.
# Refused rather than guessed at when it is a constraint this cannot read: a
# matrix built from a half-understood requirement is a build that tests a
# version nobody asked for.
requirement() {
    pb_composer="$root/composer.json"

    if [ ! -f "$pb_composer" ]; then
        echo "$NAME: composer.json is not here, so the required PHP version cannot be read." >&2

        return 1
    fi

    if ! pb_python="$(python_bin)"; then
        echo "$NAME: no Python found, so composer.json cannot be read for its PHP requirement." >&2

        return 1
    fi

    # Read by the name it has in the tree rather than by the path this shell would
    # spell it with: `composer.json` is what Python opens, from the directory the
    # subshell is standing in.
    pb_raw="$(cd "$root" 2>/dev/null && "$pb_python" -c 'import json;print(json.load(open("composer.json"))["require"]["php"])' 2>/dev/null || true)"

    if [ -z "$pb_raw" ]; then
        echo "$NAME: composer.json states no require.php, so there is no version to install." >&2

        return 1
    fi

    # The operator is stripped with a case rather than with a regular
    # expression, because the alternation a `sed` would need here is the one
    # GNU's dialect has and the portable one does not - and a constraint read
    # wrongly is a build that tests a version nobody asked for.
    pb_version="$pb_raw"

    case "$pb_version" in
        '^'*) pb_version="${pb_version#^}" ;;
        '~'*) pb_version="${pb_version#\~}" ;;
        '>='*) pb_version="${pb_version#>?}" ;;
        '='*) pb_version="${pb_version#=}" ;;
    esac

    case "$pb_version" in
        '' | *[!0-9.]* | *. | .* | *.*.*)
            echo "$NAME: composer.json requires php $pb_raw, which is not a constraint this reads into one version." >&2
            echo "  it reads ^8.4, ~8.4, >=8.4 and 8.4 - and a range, an upper bound or a patch level is none of those." >&2

            return 1
            ;;
        *.*) ;;
        *)
            echo "$NAME: composer.json requires php $pb_raw, which is not a constraint this reads into one version." >&2
            echo "  it reads ^8.4, ~8.4, >=8.4 and 8.4 - and a range, an upper bound or a patch level is none of those." >&2

            return 1
            ;;
    esac

    echo "$pb_version"
}

# The interpreter, and the rung that answered, in one pass so that the two can
# never come from different answers: `rung<TAB>value`, on one line.
resolve() {
    if [ -n "${RESPONSE_COMPRESSION_PHP:-}" ]; then
        printf 'the environment\t%s\n' "$RESPONSE_COMPRESSION_PHP"

        return 0
    fi

    pb_value="$(from_machine_file || true)"

    if [ -n "$pb_value" ]; then
        printf '.agents/machine.local.json\t%s\n' "$pb_value"

        return 0
    fi

    pb_value="$(cd "$root" 2>/dev/null && git config --get response-compression.php 2>/dev/null || true)"

    if [ -n "$pb_value" ]; then
        printf 'git config\t%s\n' "$pb_value"

        return 0
    fi

    for candidate in php php.exe; do
        if command -v "$candidate" >/dev/null 2>&1; then
            # The path rather than the name where one can be had: what a caller
            # compares it against is the interpreter it is already running
            # under, which is always a path.
            printf 'PATH\t%s\n' "$(command -v "$candidate")"

            return 0
        fi
    done

    return 1
}

# --- the selftest ------------------------------------------------------------
#
# A ladder of four rungs is four chances to answer something else, and this file
# is what every other caller believes about the interpreter - so each rung is
# planted in a throwaway root and asked, and the order between them with it: a
# machine file whose renderer answers a sentence rather than a path has to fall
# through to the next rung, and an override has to win over everything.
#
# Every fixture carries a `python` of its own, and puts it first on PATH. The
# resolver asks the renderer through whichever `python` it finds, and a stand-in
# that prints the answer asks a better question as well as a faster one: the
# subject here is the ladder, not Python - and this machine's interpreter is a
# store shim that takes two seconds to start, which fifteen fixtures would spend
# thirty of. The stand-in answers both calls the resolver makes: the renderer,
# and the one-line read of composer.json behind `--pin`. One case below keeps
# this machine's `python` in front of it, because reading that file is Python's
# job and something has to prove it works.

# One fixture tree: a renderer whose answer is `$1` (a sentence is how a
# renderer that cannot resolve the token answers), and a composer.json requiring
# `$2`.
fixture() {
    pb_root="$1"
    pb_renderer_answer="$2"
    pb_required="$3"

    mkdir -p "$pb_root/.agents" "$pb_root/empty" "$pb_root/tools"
    printf '{"require":{"php":"%s"}}\n' "$pb_required" > "$pb_root/composer.json"
    printf '{}\n' > "$pb_root/.agents/machine.local.json"

    # The renderer is written as Python even though the stand-in answers before
    # it is read: a fixture is also asked once with this machine's PATH, where a
    # renderer that was not a Python file would answer by failing.
    printf 'import sys\nsys.stdout.write("%s\\n")\n' "$pb_renderer_answer" > "$pb_root/.agents/render_local.py"

    cat > "$pb_root/tools/python" <<STANDIN
#!/bin/sh
case "\$*" in
    *--value*) printf '%s\n' '$pb_renderer_answer' ;;
    *-c*) printf '%s\n' '$pb_required' ;;
    *) exit 1 ;;
esac
STANDIN
    chmod +x "$pb_root/tools/python"
}

# A real interpreter file in a fixture, because the rung that reads the machine
# file tests its answer as a file: a fixture answering a path that is not there
# would be asking about the file test rather than about the rung.
interpreter_file() {
    printf '#!/bin/sh\nexit 0\n' > "$1"
    chmod +x "$1"
}

# A fixture's own repository, made from inside it rather than by `git -C`. A `-C`
# argument is a path *this shell* spelled, and a shell's spelling is not always
# one a Windows git can open: these fixtures live under the `/tmp` mount, where
# `git -C` fails and `cd` does not - the same lesson the resolver above learned
# about paths, applied to the program that reads them here.
fixture_git() {
    (cd "$1" 2>/dev/null && git init -q 2>/dev/null && git config response-compression.php "$2" 2>/dev/null) || true
}

# The rung and the value, as one line, in a fixture root: the PATH is the
# fixture's, and no global git config is read, so only the fixture's own files
# and the rungs above PATH can answer.
#
# `$PB_SH` rather than `sh`: a fixture whose PATH is empty is the case the last
# rung is about, and a shell looked up on that PATH is a shell that is not
# there. The shell is resolved once, before any fixture changes PATH.
ask() {
    RESPONSE_COMPRESSION_PHP="${1:-}" PATH="$3" GIT_CONFIG_GLOBAL=/dev/null GIT_CONFIG_SYSTEM=/dev/null \
        "$PB_SH" "$script" --root "$2" --report 2>/dev/null || true
}

selftest() {
    pb_failures=0
    pb_planted=0
    pb_directory="$(mktemp -d)"
    PB_SH="$(command -v sh)"

    expect() {
        pb_planted=$((pb_planted + 1))

        if [ "$1" = "$2" ]; then
            echo "[ OK ] $3"

            return 0
        fi

        echo "[FAIL] $3"
        echo "       wanted: $2"
        echo "       got:    $1"
        pb_failures=$((pb_failures + 1))
    }

    # (1) The environment wins over everything, and is taken as given even when
    # it names no file: an override is a person's decision.
    fixture "$pb_directory/env" "/usr/bin/from-the-file" "^8.4"
    expect "$(ask /usr/bin/from-the-environment "$pb_directory/env" "$pb_directory/env/tools:$PATH")" \
        "the environment	/usr/bin/from-the-environment" \
        'the environment is asked first'

    # (2) The machine file, through the renderer - the rung that is this
    # machine's own answer rather than a fallback.
    fixture "$pb_directory/machine" "$pb_directory/machine/php" "^8.4"
    interpreter_file "$pb_directory/machine/php"
    expect "$(ask "" "$pb_directory/machine" "$pb_directory/machine/tools:$PATH")" \
        ".agents/machine.local.json	$pb_directory/machine/php" \
        'the machine file answers through the renderer'

    # (2b) A renderer that explains itself instead of answering - which is what
    # it prints for a token in a file that is empty or half-written - is not an
    # interpreter, and the climb goes on. This is the case that makes the file
    # test in that rung load-bearing rather than decorative: the answer below it
    # is the fixture's git config, so an answer of anything else means the
    # sentence was taken for a path.
    fixture "$pb_directory/sentence" "no value for __PHP_EXE__" "^8.4"
    fixture_git "$pb_directory/sentence" after-the-sentence
    expect "$(ask "" "$pb_directory/sentence" "$pb_directory/sentence/tools:$PATH")" \
        "git config	after-the-sentence" \
        'a renderer answering a sentence is not an interpreter'

    # (3) git config, read out of the fixture's own repository rather than this
    # checkout's, and read before PATH.
    fixture "$pb_directory/git" "no value for __PHP_EXE__" "^8.4"
    fixture_git "$pb_directory/git" from-git-config
    expect "$(ask "" "$pb_directory/git" "$pb_directory/git/tools:$PATH")" \
        "git config	from-git-config" \
        'git config answers after the machine file'

    # (4) PATH, with a fixture php in front of whatever else is on it: the last
    # rung says "a path rather than a name", because what a caller compares the
    # value against is always a path.
    fixture "$pb_directory/path" "no value for __PHP_EXE__" "^8.4"
    mkdir -p "$pb_directory/path/bin"
    interpreter_file "$pb_directory/path/bin/php"
    expect "$(ask "" "$pb_directory/path" "$pb_directory/path/bin:$pb_directory/path/tools:$PATH")" \
        "PATH	$pb_directory/path/bin/php" \
        'PATH answers last, as a path rather than a name'

    # (5) Nothing at all is a refusal with a status, not an empty line: a caller
    # that read silence as an answer would run the next `php` it found. The PATH
    # here is a directory with nothing in it, and the shell is already resolved,
    # so no rung can answer.
    pb_status=0
    PATH="$pb_directory/path/empty" "$PB_SH" "$script" --root "$pb_directory/path" >/dev/null 2>&1 || pb_status=$?
    expect "$pb_status" "1" 'no rung answering is a refusal'

    # (6) The requirement, and the shape the workflow installs it in. This is
    # why the version is not written into the workflow: the constraint is
    # composer.json's, and reading it is this file's job.
    fixture "$pb_directory/caret" "/usr/bin/from-the-file" "^8.4"
    expect "$(PATH="$pb_directory/caret/tools:$PATH" "$PB_SH" "$script" --root "$pb_directory/caret" --pin 2>/dev/null)" \
        "8.4" 'a caret requirement reads as 8.4'
    expect "$(PATH="$pb_directory/caret/tools:$PATH" "$PB_SH" "$script" --root "$pb_directory/caret" --matrix 2>/dev/null)" \
        '["8.4"]' 'the matrix is that version'

    fixture "$pb_directory/bound" "/usr/bin/from-the-file" ">=8.4"
    expect "$(PATH="$pb_directory/bound/tools:$PATH" "$PB_SH" "$script" --root "$pb_directory/bound" --pin 2>/dev/null)" \
        "8.4" 'a lower bound reads as 8.4'

    fixture "$pb_directory/exact" "/usr/bin/from-the-file" "8.4"
    expect "$(PATH="$pb_directory/exact/tools:$PATH" "$PB_SH" "$script" --root "$pb_directory/exact" --pin 2>/dev/null)" \
        "8.4" 'a bare version reads as itself'

    fixture "$pb_directory/tilde" "/usr/bin/from-the-file" "~8.4"
    expect "$(PATH="$pb_directory/tilde/tools:$PATH" "$PB_SH" "$script" --root "$pb_directory/tilde" --pin 2>/dev/null)" \
        "8.4" 'a tilde requirement reads as 8.4'

    for pb_constraint in '>=8.4 <9.0' '^8' '^8.4.1' '8.4.*'; do
        fixture "$pb_directory/unreadable" "/usr/bin/from-the-file" "$pb_constraint"
        pb_status=0
        PATH="$pb_directory/unreadable/tools:$PATH" "$PB_SH" "$script" --root "$pb_directory/unreadable" --pin >/dev/null 2>&1 || pb_status=$?
        expect "$pb_status" "1" "a constraint this cannot read ($pb_constraint) is refused, not guessed at"
    done

    # (6b) And once with this machine's own `python`, in front of the stand-ins,
    # against this checkout's composer.json: the constraint above is translated by
    # this file, and the reading of it is Python's. The value is not written here -
    # a selftest that repeated the requirement would be a second copy of it, which
    # is the thing the workflow no longer holds either.
    pb_version="$(sh "$script" --pin 2>/dev/null || true)"

    case "$pb_version" in
        [0-9]*.[0-9]*) pb_shape='a version' ;;
        *) pb_shape="$pb_version" ;;
    esac

    expect "$pb_shape" 'a version' 'the requirement is read out of this checkout by the real Python'

    # (7) An option this does not have is a usage error rather than a silent
    # resolution: a typo'd flag that answered an interpreter would look like it
    # had been understood.
    pb_status=0
    "$PB_SH" "$script" --nope >/dev/null 2>&1 || pb_status=$?
    expect "$pb_status" "2" 'an unknown option is a usage error'

    rm -rf "$pb_directory"

    echo ""
    echo "$pb_planted fixture(s) planted, $pb_failures not answered"

    if [ "$pb_failures" -ne 0 ]; then
        return 1
    fi

    return 0
}

# --- the options -------------------------------------------------------------

report=""
while [ $# -gt 0 ]; do
    case "$1" in
        --root)
            shift
            root="${1:-}"
            ;;
        --help | -h)
            usage
            exit 0
            ;;
        *)
            report="$1"
            ;;
    esac

    shift
done

if [ -z "$root" ]; then
    echo "$NAME: this script's own directory could not be resolved, so the repository is unknown." >&2
    exit 1
fi

case "$report" in
    --selftest)
        selftest
        exit $?
        ;;
    --pin)
        requirement
        exit $?
        ;;
    --matrix)
        if ! pb_version="$(requirement)"; then
            exit 1
        fi

        printf '["%s"]\n' "$pb_version"
        exit 0
        ;;
    "" | --why | --report | --show)
        ;;
    *)
        echo "$NAME: $report is not an option this has." >&2
        usage >&2
        exit 2
        ;;
esac

pb_line="$(resolve)" || {
    refuse
    exit 1
}

pb_rung="${pb_line%%	*}"
pb_value="${pb_line#*	}"

case "$report" in
    "")
        printf '%s\n' "$pb_value"
        ;;
    --why)
        printf '%s\n' "$pb_rung"
        ;;
    --report)
        printf '%s\t%s\n' "$pb_rung" "$pb_value"
        ;;
    --show)
        printf '%s\n' "$NAME"
        printf '  %-13s %s\n' 'interpreter' "$pb_value"
        printf '  %-13s %s\n' 'rung' "$pb_rung"

        if pb_version="$(requirement 2>/dev/null)"; then
            printf '  %-13s %s\n' 'requirement' "$pb_version  (composer.json, and what CI installs)"
        else
            printf '  %-13s %s\n' 'requirement' 'not readable from composer.json'
        fi
        ;;
esac

exit 0
