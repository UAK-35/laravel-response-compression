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

A value goes in as a JSON string, escaped. The substitution is text, so a value carrying a quote, a
backslash or a newline used to be written as itself and took the document with it: a directory whose
path held `\n` became a line ending inside a quoted string, which is a machine path silently
changed rather than a crash, and the editor read whatever was left. So each value is escaped for the
context it lands in, and the rendered document is parsed before it is written - a config that does
not read as JSONC is refused with the line rather than handed to the editor.

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


def escaped(value: str) -> str:
    """A value as it can sit inside a JSON string: quotes, backslashes and line endings and all.

    `json.dumps` is the escaper rather than a hand-written one, because it is the same library that
    will parse the document back: what it writes a string as is, by construction, what it reads a
    string from. The surrounding quotes are dropped, because the template already wrote the pair the
    value sits between - the value is what goes inside them.
    """
    return json.dumps(value)[1:-1]


def without_comments(text: str) -> str:
    """A JSONC document with its comments removed and its string bodies left alone.

    Written as a scan rather than a regular expression because of one line in the template: the
    schema is a URL, and a rule that deleted `//` to the end of a line would cut the document in
    half at `https://opencode.ai/config.json`. A string is copied through character by character,
    an escape inside it included, so a `//` or a `/*` within one is text.
    """
    out: list[str] = []
    index = 0

    while index < len(text):
        char = text[index]

        if char == '"':
            out.append(char)
            index += 1

            while index < len(text):
                held = text[index]
                out.append(held)

                if held == "\\" and index + 1 < len(text):
                    out.append(text[index + 1])
                    index += 2
                    continue

                index += 1

                if held == '"':
                    break

            continue

        if text.startswith("//", index):
            end = text.find("\n", index)
            index = len(text) if end == -1 else end
            continue

        if text.startswith("/*", index):
            end = text.find("*/", index + 2)

            if end == -1:
                raise ValueError("a block comment is never closed")

            index = end + 2
            continue

        out.append(char)
        index += 1

    return "".join(out)


def without_trailing_commas(text: str) -> str:
    """The same document with a comma before a closing brace or bracket taken out.

    JSONC permits it and `json` does not, and the template uses the same brace and bracket style the
    rest of this repository's JSON does - so the renderer has to tolerate one rather than teach every
    editor's config to be stricter than the editor requires.
    """
    out: list[str] = []
    index = 0
    in_string = False

    while index < len(text):
        char = text[index]

        if in_string:
            out.append(char)

            if char == "\\" and index + 1 < len(text):
                out.append(text[index + 1])
                index += 2
                continue

            if char == '"':
                in_string = False

            index += 1
            continue

        if char == '"':
            in_string = True
            out.append(char)
            index += 1
            continue

        if char == ",":
            look = index + 1

            while look < len(text) and text[look] in " \t\r\n":
                look += 1

            if look < len(text) and text[look] in "}]":
                index += 1
                continue

        out.append(char)
        index += 1

    return "".join(out)


def validate(text: str, name: str) -> None:
    """Refuse a document that does not read as JSONC, naming where it stops making sense."""
    try:
        json.loads(without_trailing_commas(without_comments(text)))
    except (json.JSONDecodeError, ValueError) as error:
        raise SystemExit(f"{name}: what was rendered does not read as JSONC ({error}) - nothing was written")


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

        # Escaped rather than pasted: the value lands inside a JSON string, and a path holding a
        # quote, a backslash or a line ending would otherwise end the string it was meant to be.
        written = escaped(found)
        rendered = bracketed(token).sub(lambda _match: written, rendered)
        rendered = rendered.replace(token, written)

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

    # Before the write, not after: the editor reads this file happily and a placeholder only surfaces
    # later as a path that does not exist, so a document that does not parse is refused here.
    validate(rendered, TEMPLATE.name)
    OUTPUT.write_text(rendered, encoding="utf-8", newline="\n")

    print(f"wrote {OUTPUT.name} from {TEMPLATE.name} and {MACHINE.relative_to(ROOT).as_posix()}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
