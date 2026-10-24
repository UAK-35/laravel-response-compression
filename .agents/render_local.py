#!/usr/bin/env python3
"""Own this machine's settings, so no committed file has to name this machine.

WHY THIS EXISTS
---------------
A drive path in a committed file is a path that resolves for exactly one person, which is why this
repository's machine-path guard refuses one and why there is a second guard for a relative path that
climbs out of the checkout. Both of those readings need a place for a real path to live, and there is
exactly one: `.agents/machine.local.json`, which is gitignored. Everything committed refers to a path
through a `__TOKEN__` instead.

Two kinds of file are rendered from it.

  - A tool's settings file, committed as its `.example` template and written from it. The template is
    what a reader sees, so it holds a token where a path would be.
  - A token in prose, which is not rendered at all: a runbook or a skill is a document a reader
    executes later, so it keeps the `__TOKEN__` and the value is asked for when it is needed, with
    `--value`.

WHAT IT DOES NOT DO
-------------------
It does not invent a value it was not given. A token with no value is reported with the key it is
missing under, and a settings file is not written from a template that would produce an unresolved
token - a rendered file holding `__PHP_EXE__` is worse than no rendered file, because it looks
finished.

Nor does it delete a value it was not given. The machine file carries this machine's own record
beside the three sections `--init` is asked for - versions, tool paths, what this checkout is at,
what an install receives - and `--init` updates the keys it owns and keeps every other line as it
stands. It used to write its three answers over the file and take the record with them, which is a
setup command quietly discarding the facts somebody collected about the machine it is setting up.

WHAT `--check` REPORTS
----------------------
A rendered file that is not what its template would produce now, and why, in one sentence. Most of
the time the answer is a machine value that moved: the file was rendered on this machine when
`machine.local.json` named one install, and it names another now. That is reported as the token, the
value the file holds and the value the machine holds, which is the answer the reader wanted rather
than the work of finding it - two quoted lines are a diff to do by hand. When no token explains the
difference it is a hand edit, and the two lines are shown, because there is no token to name.

And how much of itself the machine file has recorded, which is the other way the file can be wrong
about itself: every section `SECTIONS` names is opened by `--init`, so a file that has just been
written holds the whole shape with nothing under most of it, and a check that listed only tokens and
templates would call that complete. That is said as one summary line naming which state the record is
in - nothing, the three answers `--init` was handed, or a record that has grown - and then each
section still empty is named. Those are printed as notes rather than findings - the state is one
`--init` creates on purpose, and the sections are this machine's own record rather than anything a
commit can be wrong about - which `unrecorded()` below argues in full.
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
AGENTS_DIR = ROOT / ".agents"
MACHINE = AGENTS_DIR / "machine.local.json"
EXAMPLE = AGENTS_DIR / "machine.local.json.example"

# Every settings file a tool actually reads, with the template beside it. The rendered file is
# gitignored; the template is committed and is what a reader can follow.
#
# There is no `.vscode/settings.json` among them on purpose: this repository ignores `.vscode/`
# outright, and re-including one file inside an ignored directory is a rule that only looks tidy.
# An editor's settings are local here, and the tokens are what a reader copies from this file.
TARGETS: list[str] = [
    ".trae/settings.json",
    ".trae/settings.local.json",
    ".kiro/settings/local.json",
]

# The tokens, and the key of machine.local.json each one comes from.
TOKENS: dict[str, tuple[tuple[str, str], str]] = {
    "__PHP_EXE__": (("php", "exe"), "the PHP binary the gate, the suite and bin/ run under"),
    "__PHP_DIR__": (("php", "dir"), "the directory holding it, derived from the binary when absent"),
    "__PWSH_EXE__": (("pwsh", "exe"), "PowerShell 7, the shell every Windows command runs through"),
    "__PACKAGE_DIR__": (("package", "dir"), "this checkout - the repository root"),
}

DEFAULT_PWSH = "pwsh.exe"

# The sections this file may carry, and what each one is for.
#
# The first three are the ones `--init` is asked for: they are the tokens of TOKENS above, and every
# settings file and every command in the layer is rendered from them. The rest are this machine's
# own record, written beside them by whoever set the machine up - the versions, the tool paths, what
# this checkout is at, what an install receives - and read by whoever arrives next.
#
# They are declared rather than merely tolerated because `--init` used to lose them: it built the
# three sections from its own answers and wrote that dict over the file, so re-naming a machine
# threw away every fact the file had recorded. A file holding eleven sections that a program's
# writer did not know about is a file the writer shortens the next time it runs, and the facts go
# quietly. So the shape is written here, a new file is opened with it, and a re-run updates the keys
# it owns and keeps everything else as it stands.
#
# A section nobody declared is kept for the same reason the declared ones are: the rule is "write
# the answers I was given and keep the rest", and a rule with an exception for the names the program
# happens to know is a rule that deletes the next person's work.
SECTIONS: dict[str, str] = {
    "php": "the interpreter the gate, the suite and bin/ run under",
    "pwsh": "PowerShell 7, the shell every Windows command runs through",
    "package": "this checkout - the repository root",
    "python": "the interpreter the agentic checks and the generators beside them run under",
    "composer": "the package manager, when it is the `composer.phar` beside `composer.json`",
    "git": "the origin, the signing key, and the line endings this checkout is written with",
    "search": "the ripgrep this package's own rule names as the search utility",
    "skills": "the index the skills are listed in, and the targets generated beside it",
    "checks": "the gate's commands, and the checks it runs",
    "tools": "the version each tool in `bin/tool-paths.php` is pinned at",
    "style": "the style configuration, its preset and the command that runs it",
    "staticAnalysis": "the analyser's configuration, its level, its paths and the PHP floor",
    "tarball": "what an installed package receives, and what is left out of it",
    "envVars": "the environment variables the package and this checkout's tooling read",
}

# The three sections `--init` writes, and the keys of each it owns. Everything else in the file is
# kept as it stands, which is what makes it safe to re-point a machine without losing its record.
OWNED: dict[str, tuple[str, ...]] = {
    "php": ("exe", "dir"),
    "pwsh": ("exe",),
    "package": ("dir",),
}


# ---------------------------------------------------------------------------
# what the machine has not written down
# ---------------------------------------------------------------------------


def nothing(value) -> bool:
    """Whether a value is this machine having written nothing down for a key.

    A `false`, a `0` and a non-empty string are all facts somebody recorded - `onPath` off,
    `commitGpgSign` off, a version - so only absence, empty text, and a mapping or list whose every
    entry is itself nothing answer yes. Anything else is an answer.
    """
    if value is None:
        return True
    if isinstance(value, str):
        return value.strip() == ""
    if isinstance(value, dict):
        return all(nothing(item) for item in value.values())
    if isinstance(value, list):
        return all(nothing(item) for item in value)

    return False


def unrecorded(data: dict) -> list[str]:
    """The declared sections this machine has written nothing in, as the lines that say so.

    WHY THIS IS PRINTED AND NOT FAILED
    ----------------------------------
    `--init` opens every section the shape declares, and one it cannot fill from its three answers is
    opened empty on purpose - the file a fresh clone gets is the shape it documents rather than the
    three answers alone. What that leaves is a file that reads as complete: every section present,
    a dozen of them holding nothing, and a `--check` with as little to say about it as about a
    machine that has recorded everything. So the sections are named.

    WHICH HALF IS LEFT
    ------------------
    A machine file spends most of its life somewhere between those two states, and one sentence about
    "empty sections" reads the same whether nobody has started or almost everything is in. So the
    summary says which state the file is in, because it is the one answer that tells a reader what to
    do next: nothing recorded, the answers `--init` wrote and nothing beside them (a fresh clone), or
    a record that has been grown and the sections still left. The per-section lines below are the
    same in all three - each names what that section is for, which the summary cannot.

    They are notes rather than findings, and the reason is the state `--init` creates rather than a
    matter of taste. `bin/bootstrap.php` verifies the clone it has just set up, so a `[FAIL]` here
    would fail that run on the very file the step before it wrote - a fresh clone could never be
    green. An empty section is also a fact about this machine rather than a defect in the tree: the
    shape is the renderer's and what is missing is somebody's record of this checkout. And
    `verify.py`'s `tokens` step reads the `[FAIL]` lines out of this program as findings and carries
    every note into its own report, so these lines reach a session that never runs `--check` - and
    still fail nothing, because a fresh `--init` opens every section it cannot fill.
    """
    missing = [section for section in SECTIONS if nothing(data.get(section))]

    if not missing:
        return []

    total = len(SECTIONS)
    recorded = total - len(missing)
    answered = [section for section in OWNED if section not in missing]

    if recorded == 0:
        lines = [
            f"[NOTE] .agents/machine.local.json: none of the {total} sections the shape declares has"
            " been recorded - the file is the shape it documents, and nothing in it is this machine's"
            " yet"
        ]
    elif len(answered) == len(OWNED) and len(missing) == total - len(OWNED):
        # The three `--init` was handed answers for are the three a fresh clone is guaranteed, and
        # nothing else is recorded: the file `bin/bootstrap.php` leaves on a clone nobody has lived
        # in yet. Saying so is the difference between "set up" and "recorded".
        lines = [
            f"[NOTE] .agents/machine.local.json: the {len(answered)} sections `--init` was handed"
            f" answers for are here and the other {len(missing)} have recorded nothing - this is the"
            " file a fresh clone gets, and the record beside those answers is what is left"
        ]
    else:
        lines = [
            f"[NOTE] .agents/machine.local.json: {recorded} of the {total} sections the shape declares"
            f" are recorded; still empty: {', '.join(missing)}"
        ]

    for section in missing:
        lines.append(
            f"[NOTE] .agents/machine.local.json: {section} is empty - the section for {SECTIONS[section]}"
        )

    return lines


# ---------------------------------------------------------------------------
# values
# ---------------------------------------------------------------------------


def _read(path: Path) -> dict:
    if not path.is_file():
        return {}
    try:
        with path.open(encoding="utf-8") as handle:
            data = json.load(handle)
    except (json.JSONDecodeError, OSError):
        return {}
    return data if isinstance(data, dict) else {}


def values_from(data: dict, root: Path = ROOT) -> dict[str, str]:
    """The token table, with the two values that are derived rather than asked for."""
    values: dict[str, str] = {}

    for token, (key, _purpose) in TOKENS.items():
        section = data.get(key[0]) if isinstance(data.get(key[0]), dict) else {}
        raw = section.get(key[1]) if isinstance(section, dict) else None
        values[token] = raw.strip() if isinstance(raw, str) else ""

    if not values["__PHP_DIR__"] and values["__PHP_EXE__"]:
        values["__PHP_DIR__"] = str(Path(values["__PHP_EXE__"]).parent).replace("\\", "/")

    if not values["__PACKAGE_DIR__"]:
        values["__PACKAGE_DIR__"] = root.as_posix()

    return values


def unresolved(values: dict[str, str]) -> list[str]:
    return [token for token in TOKENS if not values.get(token)]


def load() -> dict[str, str]:
    return values_from(_read(MACHINE))


# ---------------------------------------------------------------------------
# rendering
# ---------------------------------------------------------------------------


def substitute(node, values: dict[str, str], missing: list[str]):
    """Replace every token in a parsed JSON structure, keeping its shape."""
    if isinstance(node, dict):
        return {key: substitute(value, values, missing) for key, value in node.items()}
    if isinstance(node, list):
        return [substitute(item, values, missing) for item in node]
    if not isinstance(node, str):
        return node

    for token, value in values.items():
        if token in node:
            if not value:
                missing.append(token)
                continue
            node = node.replace(token, value)

    return node


def _template_data(template: Path):
    """A template as the parsed JSON it is, or None when it cannot be read as one."""
    try:
        with template.open(encoding="utf-8") as handle:
            data = json.load(handle)
    except (json.JSONDecodeError, OSError):
        return None

    return data


def rendered(template: Path, values: dict[str, str], missing: list[str]) -> str | None:
    """What a template would produce on this machine, or None when it cannot be read."""
    data = _template_data(template)

    if data is None:
        return None

    return json.dumps(substitute(data, values, missing), indent=4, ensure_ascii=False) + "\n"


def _first_difference(expected: str, actual: str) -> str:
    left = expected.splitlines()
    right = actual.splitlines()
    for index in range(max(len(left), len(right))):
        one = left[index] if index < len(left) else "<absent>"
        two = right[index] if index < len(right) else "<absent>"
        if one != two:
            return f"line {index + 1}: template has {one.strip()!r}, the file has {two.strip()!r}"
    return "the two differ only in trailing whitespace"


def _changed_values(
    template: Path, values: dict[str, str], actual: str
) -> list[tuple[int, str, str, str]]:
    """The machine values a rendered file was written with, where one of them has moved since.

    A drift is nearly always a value rather than a line somebody typed: the file was rendered when
    `machine.local.json` named one install and the file holds that install's path, the file now names
    another, and the two files disagree by a path. Naming the token and both of its values is the
    answer; the two lines are the working out.

    So the file is asked what it holds where the template has the token. The template is rendered a
    second time with that one token left in place, so the line it sits on is known and the literal
    around it is the same on both sides, and the text the file holds between those two pieces of
    literal is the value it was written with - the old one. What the machine names now is the new
    one. A difference no token accounts for is a hand edit, which the caller reports as it always did.

    @return The line, the token, the value the file holds and the value the machine holds, in line
            order; empty when no token's value has moved.
    """
    data = _template_data(template)
    held = actual.splitlines()

    if data is None or not held:
        return []

    changed: list[tuple[int, str, str, str]] = []

    for token, value in values.items():
        if not value:
            continue

        # This token left as itself and every other one at its value, which is the template's own
        # line for it: the same text on both sides of the token, so what sits between them in the
        # file is the value it was rendered with.
        partial = json.dumps(
            substitute(data, {**values, token: token}, []), indent=4, ensure_ascii=False
        ).splitlines()

        for index, line in enumerate(partial):
            if token not in line or index >= len(held):
                continue

            prefix, _separator, suffix = line.partition(token)
            written = held[index]

            if len(written) < len(prefix) + len(suffix):
                continue
            if not written.startswith(prefix) or not written.endswith(suffix):
                continue

            was = written[len(prefix) : len(written) - len(suffix)]

            # One line per token: the token is what the sentence is about, and a path that appears
            # six times in a file is one value that moved rather than six findings.
            if was != value:
                changed.append((index + 1, token, was, value))
                break

    return sorted(changed)


def _drift(template: Path, values: dict[str, str], expected: str, actual: str) -> str:
    """Why a rendered file and its template disagree, as the shortest true sentence about it.

    A machine value that moved is named with its token and both of its values; anything else is still
    the two lines, because a hand edit has no token to name and the reader has to see it.
    """
    changed = _changed_values(template, values, actual)

    if changed:
        return "; ".join(
            f"line {line}: {token} changed from {was!r} to {now!r}"
            for line, token, was, now in changed
        )

    return _first_difference(expected, actual)


def render(values: dict[str, str], check: bool = False) -> int:
    findings: list[str] = []

    for target in TARGETS:
        path = ROOT / target
        template = Path(str(path) + ".example")

        if not template.is_file():
            findings.append(f"{template.relative_to(ROOT).as_posix()}: the template is missing")
            continue

        missing: list[str] = []
        expected = rendered(template, values, missing)

        if expected is None:
            findings.append(f"{template.relative_to(ROOT).as_posix()}: not readable as JSON")
            continue

        if missing:
            findings.append(
                f"{template.relative_to(ROOT).as_posix()}: no value for {', '.join(sorted(set(missing)))}"
            )
            continue

        if check:
            actual = path.read_text(encoding="utf-8") if path.is_file() else ""
            if actual != expected:
                findings.append(
                    f"{target}: differs from its template - {_drift(template, values, expected, actual)}; "
                    "run python .agents/render_local.py to write it"
                )
            continue

        if not path.is_file() or path.read_text(encoding="utf-8") != expected:
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(expected, encoding="utf-8", newline="\n")
            print(f"wrote {target}")

    for finding in findings:
        print(f"[FAIL] rendered {finding}")

    return 1 if findings else 0


# ---------------------------------------------------------------------------
# commands
# ---------------------------------------------------------------------------


def show(values: dict[str, str], token: str | None) -> int:
    if token is not None:
        if token not in TOKENS:
            print(f"unknown token: {token}")
            print("known tokens: " + ", ".join(TOKENS))
            return 1
        value = values.get(token, "")
        if not value:
            print(f"{token} has no value: add it to .agents/machine.local.json")
            return 1
        print(value)
        return 0

    width = max(len(name) for name in TOKENS)
    for name, (_key, purpose) in TOKENS.items():
        value = values.get(name, "") or "<unset>"
        print(f"{name.ljust(width)}  {value}  ({purpose})")
    return 0


def ask(label: str, default: str, interactive: bool) -> str:
    if not interactive:
        return default
    answer = input(f"{label} [{default}]: ").strip()
    return answer or default


def initialise(args: argparse.Namespace) -> int:
    if MACHINE.is_file() and not args.force:
        print(
            f"{MACHINE.relative_to(ROOT).as_posix()} already exists - pass --force to name this "
            "machine again, which rewrites the three sections this program owns and keeps the rest"
        )
        return 1

    interactive = sys.stdin is not None and sys.stdin.isatty() and not args.yes
    php = args.php or ask("PHP binary", "php", interactive)
    pwsh = args.pwsh or ask("PowerShell 7", DEFAULT_PWSH, interactive)
    package = args.package or ask("Package root", ROOT.as_posix(), interactive)

    # The three answers, and whatever else the file already holds. A key `--init` owns is written
    # from the answer it was given; every other key - a section of this machine's own record, or one
    # somebody added - is kept exactly as it stands. The file is written in one order: the owned
    # sections as they are declared, then the rest as the file had them.
    existing = _read(MACHINE)
    owned = {
        "php": {"exe": php, "dir": str(Path(php).parent).replace("\\", "/")},
        "pwsh": {"exe": pwsh},
        "package": {"dir": package},
    }

    data: dict = {}
    kept: list[str] = []

    # An owned section is merged key by key rather than replaced, which is the difference between
    # re-pointing a machine and re-inventing it: `php.exe` is written from the answer, while the
    # version, the extensions and the coverage driver recorded beside it are this machine's, and are
    # facts nothing asked this program for.
    for section, keys in OWNED.items():
        held = existing.get(section)
        section_data: dict = dict(held) if isinstance(held, dict) else {}

        for key in keys:
            section_data[key] = owned[section][key]

        data[section] = section_data

    for section in SECTIONS:
        held = existing.get(section)

        if section in OWNED:
            continue

        if isinstance(held, dict):
            data[section] = held
            kept.append(section)
        elif held is None:
            # A section the shape declares and this file does not carry yet: opened empty, so the
            # file it writes is the shape it documents rather than the three answers alone.
            data[section] = {}

    # And anything the shape does not declare, which is somebody else's section rather than this
    # program's: kept verbatim and reported, because a file this program did not write in full is
    # not one it gets to shorten.
    undeclared = [key for key in existing if key not in SECTIONS]

    for section in undeclared:
        data[section] = existing[section]

    MACHINE.parent.mkdir(parents=True, exist_ok=True)
    MACHINE.write_text(json.dumps(data, indent=4) + "\n", encoding="utf-8", newline="\n")
    print(f"wrote {MACHINE.relative_to(ROOT).as_posix()}")

    if kept:
        print(f"kept {len(kept)} section(s) this machine had already recorded: {', '.join(kept)}")

    if undeclared:
        print(f"kept {len(undeclared)} section(s) this program does not declare: {', '.join(undeclared)}")

    return render(values_from(data))


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        prog="render_local.py",
        description="Render every per-machine settings file from .agents/machine.local.json.",
    )
    parser.add_argument("--init", action="store_true", help="ask for the paths, write machine.local.json, then render")
    parser.add_argument(
        "--check",
        action="store_true",
        help="report unresolved tokens, settings files that have drifted, and how much of its own "
        "record this machine has written; write nothing",
    )
    parser.add_argument("--value", nargs="?", const="", metavar="__TOKEN__", help="print a token's value, or every token")
    parser.add_argument("--php", help="the PHP binary, for --init without a terminal")
    parser.add_argument("--pwsh", help="the PowerShell 7 binary, for --init without a terminal")
    parser.add_argument("--package", help="this checkout's root, for --init without a terminal")
    parser.add_argument(
        "--force",
        action="store_true",
        help="name this machine again: the keys --init owns are rewritten, and the rest of the file is kept",
    )
    parser.add_argument("--yes", action="store_true", help="accept the defaults instead of asking")
    args = parser.parse_args(argv)

    if args.value is not None:
        # `--value` with no token prints every token and its value, which is what a reader wants when
        # they do not yet know which one they need.
        return show(load(), args.value or None)

    if args.init:
        return initialise(args)

    data = _read(MACHINE)
    values = values_from(data)
    missing = unresolved(values)

    if missing:
        print(f".agents/machine.local.json has no value for: {', '.join(missing)}")
        print("write it with: python .agents/render_local.py --init")

    # Asked only where there is a file to have recorded nothing in: a machine that has never run
    # `--init` is already told so twice by the two lines above, and fourteen notes on top of that
    # would be the report burying the answer.
    if args.check and MACHINE.is_file():
        for line in unrecorded(data):
            print(line)

    return render(values, check=args.check) or (0 if not missing else 1)


if __name__ == "__main__":
    raise SystemExit(main())
