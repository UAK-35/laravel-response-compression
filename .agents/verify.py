#!/usr/bin/env python3
"""Verify this package: the tools and processes it needs, and the layer that holds its rules.

TWO HALVES, ASKED IN ONE RUN
----------------------------
The `tools` step runs programs. It asks whether PHP is there and whether it carries the extensions
`composer.json` requires, whether a coverage driver can count a line for the floor, whether the one
manifest of tool paths still resolves to four tools that start, whether the hook this repository
documents is enabled in this clone, and whether there is a `vendor/` to run any of it from. A
toolchain that half works is the failure mode this is for: it does not look like a bug in the
package, it looks like a bad day.

The layer steps read files. Rules, skills and pointers are the one part of a repository nothing runs:
a `src/` file that stops compiling fails the gate, where a `CLAUDE.md` that points at a file which
was renamed fails nothing at all and goes on being the first thing a tool reads. So those steps are
about *agreement* - the pointer files name the canonical file, every skill has a row in the index and
a target generated from its source, the command table in `AGENTS.md` is what the manifest says, the
settings files are what their templates produce, and a token is one of the four that exist.

WHAT IT DELIBERATELY DOES NOT DO
--------------------------------
It does not re-implement the package's guards. A machine path is read by
`tests/Unit/Support/MachinePathsTest.php` and a climb out of the checkout by `RepoEscapesTest.php`,
and both own that reading, its excuses and the tests that prove they fire - a second implementation
here would be a guard of its own, which is the thing `docs/guards.md` exists to refuse. Those two run
as the `guards` step and the whole gate as the `gate` step, and neither is replaced by this program.

WHERE IT SITS
-------------
`composer checks` is the gate, `composer test:unit` is the coverage floor, and this is the third
question: do the tools and the processes those two depend on still work, and does the layer that
describes them still agree with itself. `--layer` is the fast subset with no PHP in it (what the
pre-commit hook runs), `--package` is the tools and the two package checks alone, and `--only=STEP`
runs one reading.

Exit status: 0 everything agreed, 1 at least one finding, 2 a usage error.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
AGENTS_DIR = ROOT / ".agents"
CANONICAL = ROOT / "AGENTS.md"
INDEX = AGENTS_DIR / "README.md"
TOOLCHAIN = AGENTS_DIR / "toolchain.json"
MACHINE = AGENTS_DIR / "machine.local.json"
SKILL_SOURCE = ROOT / ".skills"
SKILL_TARGET = AGENTS_DIR / "skills"

TOOLCHAIN_BEGIN = "<!-- TOOLCHAIN:BEGIN -->"
TOOLCHAIN_END = "<!-- TOOLCHAIN:END -->"
GEMINI_BEGIN = "<!-- SKILLS-IMPORT:BEGIN -->"
GEMINI_END = "<!-- SKILLS-IMPORT:END -->"

# The files a tool loads, and the file each one has to name for the layer to have one source of truth.
POINTERS = [
    "CLAUDE.md",
    "GEMINI.md",
    ".trae/TRAE.md",
    ".trae/rules/project_rules.md",
    ".cursor/rules/agents.mdc",
    ".continue/rules/00-agents.md",
    ".junie/guidelines.md",
    ".kiro/steering/package-rules.md",
    ".github/copilot-instructions.md",
    ".windsurf/rules/000-agents.windsurf.md",
]

# The layer, as the set of files a check has to be able to see. The selftest copies exactly this
# into a temporary directory, so a mutation is proven against the same shape the real run reads.
LAYER = [
    "AGENTS.md",
    "GEMINI.md",
    "CLAUDE.md",
    ".gitignore",
    "composer.json",
    "bin",
    ".agents",
    ".skills",
    ".trae",
    ".cursor",
    ".continue",
    ".junie",
    ".kiro",
    ".windsurf",
    ".github/copilot-instructions.md",
]

TOKEN = re.compile(r"__[A-Z][A-Z0-9_]*__")

# Colour is not part of an answer. Composer colourises its output even when it is written to a pipe,
# so `Composer version 2.10.3` arrives as `\x1b[32mComposer\x1b[39m version \x1b[33m2.10.3\x1b[39m`
# and a version read out of that is not a version. The gate's own checks strip the same sequences
# (`bin/checks.php`, stripAnsi) for the same reason.
ANSI = re.compile(r"\x1b\[[0-9;]*[A-Za-z]")

# The placeholder's own name is not a token. `AGENTS.md` says "write a `__TOKEN__` instead", and that
# sentence is about tokens rather than one of them - so the word is read as the word.
GENERIC_TOKENS = {"__TOKEN__"}

# A file whose job is to plant a wrong token is allowed to name one: the `tokens` mutation below
# writes `__PHP_PATH__` into a copy of the layer to prove the step still fires at all.
FIXTURE_TOKENS = {".agents/verify.py": {"__PHP_PATH__"}}
SECTION = re.compile(r"^## (\d+)\. (.+)$", re.MULTILINE)
INSTRUCTION_COMMAND = re.compile(r"^-\s+`([^`]+)`\s*:", re.MULTILINE)
COMMAND_NAME = re.compile(r"^[a-z][a-z0-9]*(?:-[a-z0-9]+)+$")
VENDOR_SHIM = re.compile(r"vendor/bin/")


# ---------------------------------------------------------------------------
# reading
# ---------------------------------------------------------------------------


def read(path: Path) -> str:
    try:
        return path.read_text(encoding="utf-8")
    except OSError:
        return ""


def rel(path: Path, root: Path) -> str:
    return path.relative_to(root).as_posix()


def tables(text: str) -> list[list[list[str]]]:
    """Every markdown table in a document, as blocks of consecutive pipe lines."""
    blocks: list[list[list[str]]] = []
    current: list[str] = []

    for line in text.splitlines() + [""]:
        if line.startswith("|"):
            current.append(line)
            continue
        if current:
            blocks.append(current)
            current = []

    parsed: list[list[list[str]]] = []
    for block in blocks:
        rows = [[cell.strip() for cell in line.strip("|").split("|")] for line in block]
        parsed.append(rows)

    return parsed


def table_rows(text: str, contains: str) -> list[list[str]]:
    """The body rows of the first table whose header names something, header and rule aside."""
    for block in tables(text):
        if not block or contains not in " ".join(block[0]):
            continue
        body = block[1:]
        if body and all(re.fullmatch(r":?-{2,}:?", cell) for cell in body[0]):
            body = body[1:]
        return body

    return []


def whole_cell(text: str, name: str) -> bool:
    """A name is indexed when a whole cell of a table row is that name, backticks aside."""
    for line in text.splitlines():
        if not line.startswith("|"):
            continue
        for cell in line.strip("|").split("|"):
            if cell.strip().strip("`").strip() == name:
                return True
    return False


def skill_targets(root: Path) -> list[str]:
    directory = root / ".agents" / "skills"
    if not directory.is_dir():
        return []
    return sorted(path.name for path in directory.iterdir() if (path / "SKILL.md").is_file())


def skill_sources(root: Path) -> list[str]:
    directory = root / ".skills"
    if not directory.is_dir():
        return []
    return sorted(path.name for path in directory.iterdir() if (path / "skill.yaml").is_file())


def source_hash(root: Path, name: str) -> str:
    instructions = root / ".skills" / name / "instructions.md"
    return hashlib.sha256(read(instructions).encode("utf-8")).hexdigest()[:16]


def frontmatter(text: str, key: str) -> str | None:
    if not text.startswith("---"):
        return None
    for line in text.split("---", 2)[1].splitlines():
        name, _, value = line.partition(":")
        if name.strip() == key:
            return value.strip().strip('"')
    return None


def command_names(text: str) -> list[str]:
    """The commands a description names: a bare or backticked token of the command shape."""
    found: list[str] = []

    for chunk in re.split(r"[\s,.;:()]+", text):
        token = chunk.strip("`\"'")
        if COMMAND_NAME.match(token):
            found.append(token)

    return found


# ---------------------------------------------------------------------------
# the steps
# ---------------------------------------------------------------------------


def step_canonical(root: Path) -> list[str]:
    findings: list[str] = []
    text = read(root / "AGENTS.md")

    if not text.strip():
        return ["AGENTS.md: missing or empty - there is nothing for the other files to point at"]

    numbers = [int(number) for number, _title in SECTION.findall(text)]
    expected = list(range(0, 15))

    if numbers != expected:
        findings.append(
            f"AGENTS.md: its sections are {numbers or 'none'}, and a reader is told there are "
            f"{expected[0]}..{expected[-1]}"
        )

    titles = {title.strip().rstrip(".") for _number, title in SECTION.findall(text)}

    for pointer in POINTERS:
        path = root / pointer
        if not path.is_file():
            continue
        for _number, title in SECTION.findall(read(path)):
            if title.strip().rstrip(".") in titles:
                findings.append(
                    f"{pointer}: restates the section `{title.strip()}` - a pointer names the "
                    "canonical file instead of repeating it"
                )

    return findings


def step_pointers(root: Path) -> list[str]:
    findings: list[str] = []

    for pointer in POINTERS:
        path = root / pointer
        if not path.is_file():
            findings.append(f"{pointer}: missing - nothing tells this tool where the rules are")
            continue
        if "AGENTS.md" not in read(path):
            findings.append(f"{pointer}: does not name AGENTS.md")

    return findings


def step_index(root: Path) -> list[str]:
    findings: list[str] = []
    index = read(root / ".agents" / "README.md")

    if not index.strip():
        return [".agents/README.md: missing or empty - it is the index of everything loadable"]

    names = [("skill", name) for name in skill_targets(root)]
    names += [
        ("agent", path.stem)
        for path in sorted((root / ".agents" / "agents").glob("*.md"))
    ]
    names += [
        ("command", path.stem)
        for path in sorted((root / ".agents" / "commands").glob("*.md"))
    ]

    for kind, name in names:
        if not whole_cell(index, name):
            findings.append(f"{kind} `{name}`: not in the index in .agents/README.md")

    # Reverse direction: a row naming something that is not there. It is read from the two index
    # tables by their first cell, so a row whose subject was renamed is a finding rather than a
    # silence - which is the half the table cannot catch by itself.
    for header in ("Authored in", "Defined in"):
        for row in table_rows(index, header):
            if not row:
                continue
            name = row[0].strip().strip("`")
            if not name:
                continue
            if name.startswith(".") or "/" in name:
                continue
            present = name in skill_targets(root)
            present = present or (root / ".agents" / "agents" / f"{name}.md").is_file()
            present = present or (root / ".agents" / "commands" / f"{name}.md").is_file()
            if not present:
                findings.append(f".agents/README.md: the row for `{name}` names nothing that exists")

    return findings


def step_skills(root: Path) -> list[str]:
    findings: list[str] = []

    for name in skill_sources(root):
        target = root / ".agents" / "skills" / name
        skill = target / "SKILL.md"
        meta = target / "_meta.json"

        if not skill.is_file():
            findings.append(f".agents/skills/{name}/SKILL.md: missing - run python .skills/_sync.py")
            continue

        declared = frontmatter(read(skill), "name")
        if declared != name:
            findings.append(
                f".agents/skills/{name}/SKILL.md: its frontmatter says `{declared}`, and the "
                "directory says otherwise"
            )

        if not meta.is_file():
            findings.append(f".agents/skills/{name}/_meta.json: missing - run python .skills/_sync.py")
            continue

        try:
            data = json.loads(read(meta))
        except json.JSONDecodeError:
            findings.append(f".agents/skills/{name}/_meta.json: not readable as JSON")
            continue

        if data.get("source_hash") != source_hash(root, name):
            findings.append(
                f".agents/skills/{name}: generated from an older .skills/{name}/instructions.md - "
                "run python .skills/_sync.py"
            )

    return findings


def step_gemini(root: Path) -> list[str]:
    text = read(root / "GEMINI.md")
    if not text.strip():
        return ["GEMINI.md: missing - the tools that cannot read .agents/skills/ have no import list"]

    block = re.search(re.escape(GEMINI_BEGIN) + r"(.*?)" + re.escape(GEMINI_END), text, re.DOTALL)
    if block is None:
        return ["GEMINI.md: there is no SKILLS-IMPORT block - run python .skills/_sync.py"]

    imported = re.findall(r"@\.agents/skills/([^/]+)/SKILL\.md", block.group(1))
    present = skill_targets(root)
    findings: list[str] = []

    for name in present:
        if name not in imported:
            findings.append(f"GEMINI.md: `{name}` is not imported - run python .skills/_sync.py")
    for name in imported:
        if name not in present:
            findings.append(f"GEMINI.md: imports `{name}`, which is not a skill - run python .skills/_sync.py")

    return findings


def step_windsurf(root: Path) -> list[str]:
    findings: list[str] = []
    rules = root / ".windsurf" / "rules"

    for name in skill_targets(root):
        if not (rules / f"{name}.windsurf.md").is_file():
            findings.append(
                f".windsurf/rules/{name}.windsurf.md: missing - a tool that cannot read "
                ".agents/skills/ has no pointer to this skill (run python .skills/_sync.py)"
            )

    return findings


def table_block(manifest: dict) -> str:
    commands = manifest.get("commands", {})
    tools = manifest.get("tools", {})

    lines = [
        "| Command | Runs | For |",
        "| --- | --- | --- |",
    ]
    for name, entry in commands.items():
        lines.append(f"| `{name}` | `{entry['line']}` | {entry['purpose']} |")

    lines += ["", "| Tool | Runs as | Version asked for | For |", "| --- | --- | --- | --- |"]
    for name, entry in tools.items():
        target = entry.get("token") or entry.get("command") or ""
        lines.append(f"| `{name}` | `{target}` | `{entry.get('pin', '')}` | {entry.get('purpose', '')} |")

    return "\n".join(lines)


def rendered_toolchain(manifest: dict) -> str:
    return (
        f"{TOOLCHAIN_BEGIN}\n"
        "<!-- rendered from .agents/toolchain.json by `python .agents/verify.py --fix`; "
        "edit the manifest, not this table -->\n\n"
        f"{table_block(manifest)}\n"
        f"{TOOLCHAIN_END}"
    )


def step_toolchain(root: Path) -> list[str]:
    findings: list[str] = []
    manifest = json.loads(read(root / ".agents" / "toolchain.json") or "{}")

    if not manifest.get("commands"):
        return [".agents/toolchain.json: no commands - there is nothing to render or check"]

    composer = json.loads(read(root / "composer.json") or "{}")
    requirement = composer.get("require", {}).get("php")
    pin = manifest.get("tools", {}).get("php", {}).get("pin")

    if requirement and pin and requirement != pin:
        findings.append(
            f".agents/toolchain.json: the php pin is `{pin}` and composer.json requires `{requirement}` "
            "- one decision written twice, and the two disagree"
        )

    scripts = composer.get("scripts", {})
    script_names = set(scripts) if isinstance(scripts, dict) else set()

    for name, entry in manifest.get("commands", {}).items():
        line = str(entry.get("line", ""))
        head = line.split()[0] if line else ""

        if head == "composer":
            script = line.split()[1] if len(line.split()) > 1 else ""
            if script and script not in script_names:
                findings.append(f".agents/toolchain.json: `{name}` runs `{line}`, which no composer.json script provides")
        elif head == "python":
            if not (root / line.split()[1]).is_file():
                findings.append(f".agents/toolchain.json: `{name}` names {line.split()[1]}, which is not here")
        elif head in ("php", "__PHP_EXE__"):
            program = line.split()[1] if len(line.split()) > 1 else ""
            if program and not (root / program).is_file():
                findings.append(f".agents/toolchain.json: `{name}` names {program}, which is not here")

    canonical = read(root / "AGENTS.md")
    block = re.search(re.escape(TOOLCHAIN_BEGIN) + r".*?" + re.escape(TOOLCHAIN_END), canonical, re.DOTALL)
    expected = rendered_toolchain(manifest)

    if block is None:
        findings.append("AGENTS.md: no TOOLCHAIN block - run python .agents/verify.py --fix")
    elif block.group(0).strip() != expected.strip():
        findings.append(
            "AGENTS.md: the command table is not what .agents/toolchain.json says "
            "- run python .agents/verify.py --fix"
        )

    for path in [root / pointer for pointer in POINTERS] + [root / "AGENTS.md", root / ".agents" / "README.md"]:
        if path.is_file() and VENDOR_SHIM.search(read(path)):
            findings.append(
                f"{rel(path, root)}: names a vendor/bin shim - a shim runs whichever php is on PATH, "
                "and bin/tool.php is how a tool is run here"
            )

    return findings


def step_tokens(root: Path) -> list[str]:
    findings: list[str] = []
    known = {"__PHP_EXE__", "__PHP_DIR__", "__PWSH_EXE__", "__PACKAGE_DIR__"}

    candidates = [root / pointer for pointer in POINTERS]
    candidates += [root / "AGENTS.md"]
    candidates += [
        path
        for base in (".agents", ".skills")
        for path in sorted((root / base).rglob("*"))
        if path.is_file() and path.suffix in (".md", ".json", ".py")
    ]

    for path in candidates:
        if not path.is_file():
            continue

        where = rel(path, root)
        excused = GENERIC_TOKENS | FIXTURE_TOKENS.get(where, set())

        for token in sorted(set(TOKEN.findall(read(path))) - known - excused):
            findings.append(
                f"{where}: `{token}` is not one of {', '.join(sorted(known))} - "
                "a token nothing knows is a name nothing resolves"
            )

    # The one file that names this machine is the one file that must not be committed. The question
    # is asked of git's own ignore rules rather than of a copy of them kept here.
    if is_a_repo(root) and not ignored(root, ".agents/machine.local.json"):
        findings.append(
            ".agents/machine.local.json: not ignored - it holds this machine's paths, and a commit "
            "would carry them"
        )

    # Every settings template has to parse, and the rendered file has to be what its template would
    # produce. The rendering is not repeated here: `render_local.py` owns it, and it answers --check
    # for exactly this question.
    for template in sorted(root.glob(".*/**/*.example")):
        if not template.is_file():
            continue
        try:
            json.loads(read(template))
        except json.JSONDecodeError:
            findings.append(f"{rel(template, root)}: not readable as JSON")

    renderer = root / ".agents" / "render_local.py"
    if renderer.is_file() and is_a_repo(root):
        if (root / ".agents" / "machine.local.json").is_file():
            result = subprocess.run(
                [sys.executable, str(renderer), "--check"],
                cwd=root,
                capture_output=True,
                text=True,
                check=False,
            )
            findings += [line.strip() for line in result.stdout.splitlines() if line.startswith("[FAIL]")]
        else:
            # A machine that has not been set up is a machine, not a defect in this commit. Saying so
            # is the point: the alternative is a green run that silently judged nothing.
            NOTES.append(
                "tokens: no .agents/machine.local.json here, so the rendered settings were not judged "
                "- run python .agents/render_local.py --init"
            )

    return findings


def is_a_repo(root: Path) -> bool:
    try:
        result = subprocess.run(
            ["git", "-C", str(root), "rev-parse", "--git-dir"],
            capture_output=True,
            text=True,
            check=False,
        )
    except OSError:
        return False

    return result.returncode == 0


def ignored(root: Path, path: str) -> bool:
    result = subprocess.run(
        ["git", "-C", str(root), "check-ignore", "-q", path],
        capture_output=True,
        text=True,
        check=False,
    )

    return result.returncode == 0


def step_descriptions(root: Path) -> list[str]:
    findings: list[str] = []

    for source in skill_sources(root):
        manifest = read(root / ".skills" / source / "skill.yaml")
        instructions = read(root / ".skills" / source / "instructions.md")

        description = ""
        for line in manifest.splitlines():
            if line.startswith("description:"):
                description = line.partition(":")[2].strip().strip('"')
                break

        if not description:
            findings.append(f".skills/{source}/skill.yaml: no description")
            continue

        defined = {match.split()[0] for match in INSTRUCTION_COMMAND.findall(instructions)}

        if not defined:
            findings.append(
                f".skills/{source}/instructions.md: defines no commands, so the description can only "
                "name things the skill does not do"
            )
            continue

        for command in command_names(description):
            if command not in defined:
                findings.append(
                    f".skills/{source}: the description names `{command}`, and its instructions.md "
                    "defines no command by that name"
                )

    return findings


def capture(root: Path, *command: str, timeout: int = 300) -> str | None:
    """A command's output, or None when it could not be run at all.

    A probe is asking a tool a question, so a non-zero exit is an answer too - it is the caller that
    decides whether the answer is bad news.
    """
    try:
        result = subprocess.run(
            list(command),
            cwd=root,
            capture_output=True,
            text=True,
            timeout=timeout,
            check=False,
        )
    except (OSError, subprocess.TimeoutExpired):
        return None

    return ANSI.sub("", (result.stdout or "") + (result.stderr or ""))


def satisfies(version: str, pin: str) -> bool:
    """Whether a version answers a pin written as `^X.Y`, `X.Y` or a bare major."""
    if not pin:
        return True

    found = [int(part) for part in re.findall(r"\d+", version)[:3]]
    wanted = [int(part) for part in re.findall(r"\d+", pin)]

    if not found or not wanted:
        return False

    if pin.startswith("^"):
        return found[0] == wanted[0] and found[: len(wanted)] >= wanted

    return found[: len(wanted)] >= wanted


def manifest_tools(root: Path) -> dict[str, str]:
    """The tool manifest the package runs its tools from: `bin/tool-paths.php`, read as data.

    It is read rather than restated because it is the repository's own answer to "where is Pest" -
    `tests/Unit/Support/ToolPathsTest.php` owns the rules about what may be in it, and a second copy
    of the map here would be the very thing that guard refuses.
    """
    text = read(root / "bin" / "tool-paths.php")
    return {name: path for name, path in re.findall(r"'([a-z0-9-]+)'\s*=>\s*'([^']+)'", text)}


def step_tools(root: Path) -> list[str]:
    """Every tool and every process this package needs, asked to work rather than assumed to.

    The layer steps above read files; this one runs programs. Each thing it asks is a question the
    package's own commands depend on the answer to: which PHP is being used and does it carry the
    extensions `composer.json` requires, is there a coverage driver for the floor, does the tool
    manifest still resolve to four tools that start, is the hook this repository documents actually
    enabled in this clone, and is there a `vendor/` at all.

    A missing tool is a finding here, not a skip: the gate can skip a check on a machine that lacks
    the tool, but "the processes this package needs do not run here" is the answer to the question
    this step was asked.
    """
    findings: list[str] = []
    manifest = json.loads(read(root / ".agents" / "toolchain.json") or "{}")
    tools = manifest.get("tools", {})
    composer = json.loads(read(root / "composer.json") or "{}")

    # 1. PHP, its version against the pin, the extensions it must carry, and a coverage driver.
    machine = root / ".agents" / "machine.local.json"
    declared = ""

    if machine.is_file():
        try:
            declared = str((json.loads(read(machine)).get("php", {}) or {}).get("exe", ""))
        except json.JSONDecodeError:
            declared = ""

    # A machine file that names an interpreter which is not there is answered by falling back to
    # PATH, and a silent fallback is how a machine ends up running the gate under a second PHP.
    if declared and not Path(declared).is_file():
        NOTES.append(
            f"tools: .agents/machine.local.json names {declared} as this machine's PHP, and there is "
            "nothing there - the probes below fell back to whatever `php` is on PATH"
        )

    php = php_binary(root)
    loaded: set[str] = set()

    if php is None:
        findings.append(
            "php: no interpreter found - neither .agents/machine.local.json nor PATH names one, and "
            "every command this package runs needs it (python .agents/render_local.py --init)"
        )
    else:
        version = capture(root, php, "-r", "echo PHP_VERSION;")
        pin = str(tools.get("php", {}).get("pin", ""))

        if version is None:
            findings.append(f"php: {php} could not be run")
        elif not satisfies(version.strip(), pin):
            findings.append(
                f"php: {php} reports {version.strip()}, and .agents/toolchain.json pins `{pin}` - the "
                "gate and the floor were not measured under the interpreter they were written for"
            )

        modules = capture(root, php, "-m") or ""
        loaded = {line.strip().lower() for line in modules.splitlines()}

        for requirement in sorted(name for name in composer.get("require", {}) if name.startswith("ext-")):
            extension = requirement[4:].lower()
            if extension not in loaded:
                findings.append(
                    f"php: `{requirement}` is required by composer.json and not loaded by {php} - the "
                    "package cannot run here, whatever the gate says about the other checks"
                )

        loadable = capture(root, php, "-r", "echo implode(',', array_filter(['pcov','xdebug'], 'extension_loaded'));") or ""
        if not loadable.strip():
            findings.append(
                "php: neither PCOV nor Xdebug is loaded, so composer test:unit cannot measure the "
                "floor - it reports that rather than a number, which is the honest answer to this"
            )

    # 2. Composer, and the PHP it runs under. The version is probed, because a library gitignores its
    #    lock and the manifest is the only committed statement of anything.
    phar = root / "composer.phar"
    composer_command: list[str] = [php, str(phar)] if php and phar.is_file() else ["composer"]
    composer_output = capture(root, *composer_command, "--version")

    if composer_output is None:
        findings.append("composer: not runnable - neither composer.phar beside this checkout nor composer on PATH")
    else:
        match = re.search(r"Composer version\s+(\S+)", composer_output)
        pin = str(tools.get("composer", {}).get("pin", ""))
        if match is None:
            findings.append(f"composer: could not read a version out of `{' '.join(composer_command)} --version`")
        elif not satisfies(match.group(1), pin):
            findings.append(
                f"composer: {match.group(1)} does not answer the pin `{pin}` - every script in "
                "composer.json is a Composer 2 script, and this is what runs them"
            )

    # 3. Python, which is running this program, and PowerShell 7, which the Windows snippets assume.
    if sys.version_info < (3, 9):
        findings.append(f"python: {sys.version.split()[0]} is older than the 3.9 these programs need")

    if os.name == "nt":
        pwsh = str((json.loads(read(machine) or "{}").get("pwsh", {}) or {}).get("exe", "")) if machine.is_file() else ""
        pwsh = pwsh or (shutil.which("pwsh") or "")
        output = capture(root, pwsh, "-NoProfile", "-Command", "$PSVersionTable.PSVersion.ToString()") if pwsh else None
        pin = str(tools.get("pwsh", {}).get("pin", ""))

        if output is None:
            findings.append(
                "pwsh: PowerShell 7 was not found - the Windows snippets in AGENTS.md and the skills "
                "are written for it (python .agents/render_local.py --init)"
            )
        elif not satisfies(output.strip(), pin):
            findings.append(f"pwsh: reports {output.strip()}, and the pin is `{pin}`")
    else:
        NOTES.append("tools: not Windows, so the PowerShell probe was left out")

    # 4. Git, and the hook this repository documents. The hook is a clone-local setting, so a clone
    #    that has not enabled it is a note rather than a finding - but it is worth saying out loud,
    #    because the gate is what a contributor is told runs before a commit.
    git = shutil.which("git")
    if git is None or capture(root, "git", "--version") is None:
        findings.append("git: not runnable, and every part of this repository's process is around it")
    elif is_a_repo(root):
        hooks = (capture(root, "git", "config", "--get", "core.hooksPath") or "").strip()
        if hooks != ".githooks":
            NOTES.append(
                f"tools: core.hooksPath is `{hooks or 'unset'}`, so .githooks/pre-commit did not run on "
                "this commit - enable it with: git config core.hooksPath .githooks"
            )

    # 5. The tools themselves. Without a vendor/ there is nothing to run them from, which is a state
    #    a fresh clone is in rather than a defect: it is reported so a green run cannot be mistaken
    #    for "the tools were checked".
    paths = manifest_tools(root)

    if not paths:
        findings.append("bin/tool-paths.php: no tool paths could be read - the manifest is what runs a tool")
    elif php is None:
        NOTES.append("tools: there is no PHP to run the four dev tools under")
    elif not (root / "vendor").is_dir():
        NOTES.append(
            "tools: no vendor/ beside this checkout, so the four dev tools were not run - "
            "composer install puts them there"
        )
    else:
        for name, path in sorted(paths.items()):
            if not (root / path).is_file():
                findings.append(
                    f"bin/tool-paths.php: `{name}` names {path}, which is not on disk - a caller that "
                    "reached for it would fail rather than report a skip"
                )
                continue

            # Run through the same door the composer scripts use, so what is checked is the process
            # as well as the tool: bin/tool.php resolves the path from the manifest and runs it under
            # PHP_BINARY, which is the interpreter that installed the dependencies.
            output = capture(root, *tool_command(root, name, "--version"), timeout=120)

            if output is None:
                findings.append(f"{name}: `bin/tool.php {name} --version` could not be run")
            elif not output.strip():
                findings.append(f"{name}: answered `bin/tool.php {name} --version` with nothing - it did not start")

    for program in ("checks.php", "coverage.php", "release.php", "tool.php", "tool-paths.php"):
        if not (root / "bin" / program).is_file():
            findings.append(f"bin/{program}: missing, and this repository's process is built on it")

    return findings


def step_guards(root: Path) -> list[str]:
    return run_process(root, *tool_command(root, "pest", "tests/Unit/Support/MachinePathsTest.php", "tests/Unit/Support/RepoEscapesTest.php"))


def step_gate(root: Path) -> list[str]:
    php = php_binary(root)
    if php is None:
        return ["composer checks: no PHP interpreter found - the gate was not run"]

    phar = root / "composer.phar"
    command = [php, str(phar)] if phar.is_file() else ["composer"]
    return run_process(root, *command, "checks", "--", "--require-all")


# ---------------------------------------------------------------------------
# running the package's own checks
# ---------------------------------------------------------------------------


def php_binary(root: Path) -> str | None:
    data = {}
    machine = root / ".agents" / "machine.local.json"
    if machine.is_file():
        try:
            data = json.loads(read(machine))
        except json.JSONDecodeError:
            data = {}

    candidate = data.get("php", {}).get("exe") if isinstance(data.get("php"), dict) else None
    if candidate and Path(str(candidate)).is_file():
        return str(candidate)

    return shutil.which("php") or shutil.which("php.exe")


def tool_command(root: Path, *args: str) -> list[str]:
    php = php_binary(root) or "php"
    return [php, "bin/tool.php", *args]


def run_process(root: Path, *command: str) -> list[str]:
    try:
        result = subprocess.run(
            list(command),
            cwd=root,
            capture_output=True,
            text=True,
            timeout=1800,
            check=False,
        )
    except (OSError, subprocess.TimeoutExpired) as error:
        return [f"{' '.join(command)}: could not be run ({error})"]

    if result.returncode == 0:
        return []

    tail = (result.stdout + result.stderr).strip().splitlines()[-8:]
    return [f"{' '.join(command)}: exited {result.returncode}", *[f"    {line}" for line in tail]]


# ---------------------------------------------------------------------------
# the run
# ---------------------------------------------------------------------------


STEPS = [
    ("canonical", step_canonical, "AGENTS.md has its sections, and no pointer restates one"),
    ("pointers", step_pointers, "every tool's pointer file exists and names AGENTS.md"),
    ("index", step_index, "every skill, agent and command is in the index, and every row names something"),
    ("skills", step_skills, "each target was generated from the source beside it"),
    ("gemini", step_gemini, "the GEMINI.md import block lists exactly the skills present"),
    ("windsurf", step_windsurf, "every skill has a pointer rule"),
    ("toolchain", step_toolchain, "the commands and pins are the ones the package actually has"),
    ("tokens", step_tokens, "every token is one of the four, and no rendered file has drifted"),
    ("descriptions", step_descriptions, "a description names only commands its skill defines"),
]

# The steps that are about the package rather than about this directory: what the package needs to
# run, and the two checks that own a reading this program deliberately does not repeat.
PACKAGE_STEPS = [
    ("tools", step_tools, "every tool and process this package needs, asked to work rather than assumed to"),
    ("guards", step_guards, "the two path guards, which own that reading rather than this program"),
    ("gate", step_gate, "composer checks -- --require-all, the package's own gate"),
]

ALL_STEPS = STEPS + PACKAGE_STEPS

# The steps the selftest can plant a fault for: every layer step, plus the toolchain probe, which is
# the one package step whose answer can be broken without running a suite. `guards` and `gate` are
# programs already driven by the package's own tests (ChecksTest), and a second fixture for them
# would be a second implementation of the thing they check.
SELFTESTABLE = list(STEPS) + [step for step in PACKAGE_STEPS if step[0] == "tools"]


def repair(root: Path) -> list[str]:
    repaired: list[str] = []
    manifest = json.loads(read(root / ".agents" / "toolchain.json") or "{}")
    canonical = read(root / "AGENTS.md")

    if manifest.get("commands") and TOOLCHAIN_BEGIN in canonical:
        expected = rendered_toolchain(manifest)
        pattern = re.compile(re.escape(TOOLCHAIN_BEGIN) + r".*?" + re.escape(TOOLCHAIN_END), re.DOTALL)
        updated = pattern.sub(lambda _match: expected, canonical)
        if updated != canonical:
            (root / "AGENTS.md").write_text(updated, encoding="utf-8", newline="\n")
            repaired.append("AGENTS.md: the command table was rendered from .agents/toolchain.json")

    sync = root / ".skills" / "_sync.py"
    if sync.is_file():
        result = subprocess.run(
            [sys.executable, str(sync)],
            cwd=root,
            capture_output=True,
            text=True,
            check=False,
        )
        if result.returncode == 0:
            # It prints its summary whether or not it changed anything, so the repair is reported on
            # the lines that say a file was written - otherwise `--fix` would claim a repair it did
            # not make, which is the shape of a check that reports green while doing nothing.
            wrote = [line for line in result.stdout.splitlines() if line.startswith("wrote ")]
            if wrote:
                repaired.append(f"skills: {len(wrote)} file(s) regenerated by python .skills/_sync.py")
        else:
            tail = result.stderr.strip().splitlines()
            repaired.append(f"skills: _sync.py failed - {tail[-1] if tail else 'no output'}")

    return repaired


NOTES: list[str] = []


def run(root: Path, only: list[str] | None, scope: str) -> int:
    NOTES.clear()

    if only is not None:
        unknown = [name for name in only if name not in {step[0] for step in ALL_STEPS}]
        if unknown:
            print(f"unknown step(s): {', '.join(unknown)}")
            return 2
        selected = [step for step in ALL_STEPS if step[0] in only]
    elif scope == "layer":
        # What the pre-commit hook runs: this directory, no PHP, a second or two.
        selected = list(STEPS)
    elif scope == "package":
        # The tools and the two package checks, without the layer's own readings.
        selected = list(PACKAGE_STEPS)
    else:
        selected = list(ALL_STEPS)

    width = max(len(name) for name, _fn, _why in ALL_STEPS)
    failed = 0

    for name, function, why in selected:
        findings = function(root)
        if findings:
            failed += 1
            print(f"[FAIL] {name.ljust(width)}  {why}")
            for finding in findings:
                print(f"        {finding}")
        else:
            print(f"[ OK ] {name.ljust(width)}  {why}")

    for name, _fn, why in [step for step in ALL_STEPS if step not in selected]:
        print(f"[SKIP] {name.ljust(width)}  {why} (not selected - --only={name} runs it)")

    for note in NOTES:
        print(f"[NOTE] {note}")

    print(f"\n{len(selected)} step(s) run, {failed} with findings, {len(ALL_STEPS) - len(selected)} not selected")
    return 1 if failed else 0


# ---------------------------------------------------------------------------
# the selftest
# ---------------------------------------------------------------------------


def plant(root: Path) -> None:
    for entry in LAYER:
        source = ROOT / entry
        target = root / entry
        if not source.exists():
            continue
        target.parent.mkdir(parents=True, exist_ok=True)
        if source.is_dir():
            shutil.copytree(source, target, dirs_exist_ok=True)
        else:
            shutil.copy2(source, target)

    # The phar travels with it, because the toolchain probe would otherwise judge the layer with
    # whatever `composer` happens to be on PATH - and on a machine with the phar beside the checkout
    # there is often no PATH composer at all, which is the arrangement this repository documents.
    phar = ROOT / "composer.phar"
    if phar.is_file():
        shutil.copy2(phar, root / "composer.phar")


def mutate(name: str, root: Path) -> None:
    """Break the layer one way, and only that way."""
    if name == "canonical":
        # A pointer is not allowed to become a second rule file, which is the failure that makes a
        # stale rule survive: the canonical file changes and the pointer goes on being obeyed.
        path = root / "CLAUDE.md"
        path.write_text(read(path) + "\n## 5. The gate\n\nRun composer checks before a commit.\n", encoding="utf-8")
    elif name == "pointers":
        (root / "GEMINI.md").unlink()
    elif name == "index":
        skill = root / ".agents" / "skills" / "an-unlisted-skill"
        skill.mkdir(parents=True, exist_ok=True)
        (skill / "SKILL.md").write_text("---\nname: an-unlisted-skill\n---\n", encoding="utf-8")
    elif name == "skills":
        (root / ".skills" / "package-dev" / "instructions.md").write_text(
            read(root / ".skills" / "package-dev" / "instructions.md") + "\n- `not-a-command`: and so the hash moves\n",
            encoding="utf-8",
        )
    elif name == "gemini":
        path = root / "GEMINI.md"
        path.write_text(read(path).replace("@.agents/skills/package-dev/SKILL.md", ""), encoding="utf-8")
    elif name == "windsurf":
        (root / ".windsurf" / "rules" / "package-dev.windsurf.md").unlink()
    elif name == "toolchain":
        path = root / ".agents" / "toolchain.json"
        manifest = json.loads(read(path))
        manifest["tools"]["php"]["pin"] = "^8.3"
        path.write_text(json.dumps(manifest, indent=4) + "\n", encoding="utf-8")
    elif name == "tokens":
        path = root / "CLAUDE.md"
        path.write_text(read(path) + "\nThe PHP binary is `__PHP_PATH__`.\n", encoding="utf-8")
    elif name == "descriptions":
        # Hyphenated, because that is the shape the step reads as a command name: `teleport` alone is
        # prose, and this check is only about the names a description advertises.
        path = root / ".skills" / "package-dev" / "skill.yaml"
        path.write_text(read(path).replace("Commands - gate,", "Commands - gate-teleport, gate,"), encoding="utf-8")
    elif name == "tools":
        # An extension required by the manifest and not loaded by the interpreter: the question the
        # step asks is whether the interpreter was asked at all, and this is the plainest way to see.
        path = root / "composer.json"
        data = json.loads(read(path))
        data.setdefault("require", {})["ext-nonesuch"] = "*"
        path.write_text(json.dumps(data, indent=4) + "\n", encoding="utf-8")


def selftest() -> int:
    failures = 0

    for name, function, _why in SELFTESTABLE:
        with tempfile.TemporaryDirectory(prefix="agentic-selftest-") as directory:
            root = Path(directory)
            plant(root)

            clean = function(root)
            if clean:
                print(f"[FAIL] selftest {name}: the planted layer is not clean - {clean[0]}")
                failures += 1
                continue

            mutate(name, root)
            broken = function(root)

            if not broken:
                print(f"[FAIL] selftest {name}: the mutation was not reported - this step has stopped checking")
                failures += 1
            else:
                print(f"[ OK ] selftest {name.ljust(12)} {broken[0]}")

    print(f"\n{len(SELFTESTABLE)} mutation(s) planted, {failures} not reported")
    return 1 if failures else 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        prog="verify.py",
        description=(
            "Verify this package: the tools and processes it needs, its own two path guards, its gate, "
            "and the agentic layer that holds the rules."
        ),
    )
    parser.add_argument("--fix", action="store_true", help="repair what a generator owns, then re-check")
    parser.add_argument("--only", metavar="STEP", help="run one step, or a comma separated list of them")
    parser.add_argument("--list", action="store_true", help="list the steps and what each one proves")
    parser.add_argument("--layer", action="store_true", help="only the agentic layer, with no PHP (what the hook runs)")
    parser.add_argument("--package", action="store_true", help="only the tools, the guards and the gate")
    parser.add_argument("--selftest", action="store_true", help="prove each step still reports a planted fault")
    args = parser.parse_args(argv)

    if args.list:
        width = max(len(name) for name, _fn, _why in ALL_STEPS)
        for name, _function, why in ALL_STEPS:
            print(f"{name.ljust(width)}  {why}")
        return 0

    if args.selftest:
        return selftest()

    if args.fix:
        for line in repair(ROOT):
            print(f"[FIX ] {line}")
        print()

    only = [name.strip() for name in args.only.split(",")] if args.only else None
    scope = "layer" if args.layer else "package" if args.package else "all"

    return run(ROOT, only, scope)


if __name__ == "__main__":
    raise SystemExit(main())
