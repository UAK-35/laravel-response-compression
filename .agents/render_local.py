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


def rendered(template: Path, values: dict[str, str], missing: list[str]) -> str | None:
    """What a template would produce on this machine, or None when it cannot be read."""
    try:
        with template.open(encoding="utf-8") as handle:
            data = json.load(handle)
    except (json.JSONDecodeError, OSError):
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
                    f"{target}: differs from its template - {_first_difference(expected, actual)}; "
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
        print(f"{MACHINE.relative_to(ROOT).as_posix()} already exists - pass --force to replace it")
        return 1

    interactive = sys.stdin is not None and sys.stdin.isatty() and not args.yes
    php = args.php or ask("PHP binary", "php", interactive)
    pwsh = args.pwsh or ask("PowerShell 7", DEFAULT_PWSH, interactive)
    package = args.package or ask("Package root", ROOT.as_posix(), interactive)

    data = {
        "php": {"exe": php, "dir": str(Path(php).parent).replace("\\", "/")},
        "pwsh": {"exe": pwsh},
        "package": {"dir": package},
    }

    MACHINE.parent.mkdir(parents=True, exist_ok=True)
    MACHINE.write_text(json.dumps(data, indent=4) + "\n", encoding="utf-8", newline="\n")
    print(f"wrote {MACHINE.relative_to(ROOT).as_posix()}")

    return render(values_from(data))


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        prog="render_local.py",
        description="Render every per-machine settings file from .agents/machine.local.json.",
    )
    parser.add_argument("--init", action="store_true", help="ask for the paths, write machine.local.json, then render")
    parser.add_argument("--check", action="store_true", help="report unresolved tokens and drift; write nothing")
    parser.add_argument("--value", nargs="?", const="", metavar="__TOKEN__", help="print a token's value, or every token")
    parser.add_argument("--php", help="the PHP binary, for --init without a terminal")
    parser.add_argument("--pwsh", help="the PowerShell 7 binary, for --init without a terminal")
    parser.add_argument("--package", help="this checkout's root, for --init without a terminal")
    parser.add_argument("--force", action="store_true", help="replace machine.local.json if it exists")
    parser.add_argument("--yes", action="store_true", help="accept the defaults instead of asking")
    args = parser.parse_args(argv)

    if args.value is not None:
        # `--value` with no token prints every token and its value, which is what a reader wants when
        # they do not yet know which one they need.
        return show(load(), args.value or None)

    if args.init:
        return initialise(args)

    values = load()
    missing = unresolved(values)

    if missing:
        print(f".agents/machine.local.json has no value for: {', '.join(missing)}")
        print("write it with: python .agents/render_local.py --init")

    return render(values, check=args.check) or (0 if not missing else 1)


if __name__ == "__main__":
    raise SystemExit(main())
