#!/usr/bin/env python3
"""Render `opencode.jsonc` from `opencode.jsonc.template`, and the machine's own file.

WHY THIS EXISTS
---------------
`opencode.jsonc` is a tracked config, and one of its values is a path that only exists on the
machine it was written on: the editor's own scratch directory. Committing that path is what makes
the machine-path reading name the file, and replacing it with a portable one is not possible - the
editor has to be told where the directory is.

So the tracked artifact is the **template**, which names the value as a token, and the config the
editor reads is **generated** from it and from `.agents/machine.local.json`, the gitignored file
that already records this machine's answers for everything else.

THE MAP
-------
`SUBSTITUTIONS` is the whole of the mapping: a token, and the section and key of
`.agents/machine.local.json` its value is read from. It is written here rather than in the template
because a template says *where* the value goes and the machine file says *what* it is; a template
that also carried the answer would be a second copy of the machine file.

Adding a token therefore costs two things: the placeholder in the template, and a row here.

WHAT IT REFUSES
---------------
It writes nothing unless every token resolves. A template naming a token this map does not know, a
map row whose key is missing or empty in the machine file, and a machine file that is not there are
each reported and exit 1 - a half-substituted config is worse than none, because the editor reads it
happily and the placeholder only surfaces later, as a path that does not exist.

The angle brackets around a token are consumed with it: `<__TOKEN__>` becomes the value, so the
rendered line names a real directory rather than a bracketed one. A bare `__TOKEN__` with no
brackets is substituted too, for a template that wants the value inside a longer string.

WHAT IT DOES NOT DO
-------------------
It does not read the token table in `.agents/render_local.py`. That table is the settings-file
renderer's, and this is a different artifact with a different lifetime: the renderer fills committed
templates in `.trae/` and `.kiro/`, while this fills one config at the root and is run by hand. Two
readers of the same machine file is a thing this package tolerates where the questions differ; the
mapping is kept here, in one place, rather than shared and then narrowed.
"""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
TEMPLATE = ROOT / "opencode.jsonc.template"
OUTPUT = ROOT / "opencode.jsonc"
MACHINE = ROOT / ".agents" / "machine.local.json"

# The substitution map: the token a template writes, and the key its value is read from.
SUBSTITUTIONS: dict[str, tuple[str, str]] = {
    "__SYSTEM_TEMP_FOLDER__": ("systemTempFolder", "systemTempFolderPath"),
}

# Any token this package writes: two underscores, upper case, two underscores.
TOKEN = re.compile(r"__[A-Z][A-Z0-9_]*__")


def bracketed(token: str) -> re.Pattern[str]:
    """The token as a template writes it when the value stands for a whole path segment."""
    return re.compile(r"<" + re.escape(token) + r">")


def machine(path: Path) -> dict:
    """The machine file, decoded, or a refusal that says how to make one."""
    if not path.is_file():
        raise SystemExit(
            f"{path.relative_to(ROOT).as_posix()} is not here, so there is nothing to substitute from - "
            "python .agents/render_local.py --init writes it"
        )

    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        raise SystemExit(f"{path.relative_to(ROOT).as_posix()} could not be read: {error}")

    if not isinstance(data, dict):
        raise SystemExit(f"{path.relative_to(ROOT).as_posix()} is not a JSON object")

    return data


def value(data: dict, section: str, key: str) -> str | None:
    """One value out of the machine file, or None when this machine has not recorded it."""
    held = data.get(section)

    if not isinstance(held, dict):
        return None

    found = held.get(key)

    return found.strip() if isinstance(found, str) and found.strip() else None


def render(template: str, data: dict) -> str:
    """The template with every token replaced, or a refusal naming what could not be."""
    unknown = sorted({token for token in TOKEN.findall(template) if token not in SUBSTITUTIONS})

    if unknown:
        raise SystemExit(
            "the template names token(s) this script has no row for: "
            + ", ".join(unknown)
            + " - add each to SUBSTITUTIONS with the section and key it is read from"
        )

    rendered = template
    missing: list[str] = []

    for token, (section, key) in SUBSTITUTIONS.items():
        if token not in rendered:
            continue

        found = value(data, section, key)

        if found is None:
            missing.append(f"{token} ({section}.{key})")
            continue

        rendered = bracketed(token).sub(lambda _match: found, rendered)
        rendered = rendered.replace(token, found)

    if missing:
        raise SystemExit(
            "the machine file records nothing for: " + ", ".join(missing)
        )

    return rendered


def main() -> int:
    if len(sys.argv) > 1:
        print(f"usage: python {Path(__file__).name}", file=sys.stderr)
        return 2

    if not TEMPLATE.is_file():
        print(f"{TEMPLATE.name} is not here, so there is nothing to render", file=sys.stderr)
        return 1

    template = TEMPLATE.read_text(encoding="utf-8")
    rendered = render(template, machine(MACHINE))
    OUTPUT.write_text(rendered, encoding="utf-8", newline="\n")

    print(f"wrote {OUTPUT.name} from {TEMPLATE.name} and {MACHINE.relative_to(ROOT).as_posix()}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
