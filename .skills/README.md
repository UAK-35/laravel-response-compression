# `.skills/` — where a skill is authored

`.skills/` is the **source**. `.agents/skills/` is the **single real target** — the only place skill
content is written — and the three pointer mechanisms (the `GEMINI.md` import block, the Windsurf
rules, and nothing else, because the other tools read the target directly) are generated from it.
Both directions are one command:

```
.skills/<name>/                        .agents/skills/<name>/
    skill.yaml          ----------->       SKILL.md        (the body, with frontmatter)
    instructions.md     python              _meta.json      (name, version, tags, source_hash)
                        .skills/_sync.py
                                           GEMINI.md                    <!-- SKILLS-IMPORT --> block
                                           .windsurf/rules/<name>.windsurf.md   pointer rule
```

Edit the source, run `python .skills/_sync.py`, then `python .agents/verify.py`. A pointer never
contains a skill body, so a pointer cannot drift from the skill it points at.

## Writing a `skill.yaml`

This generator reads a small subset of YAML — `key: value`, and one level of nesting — because a full
YAML reader would be a dependency for four keys of structure, and a silent misread is worse than a
refusal. So:

- `tags` and `categories` are one comma separated line, not a list.
- `files:` is not supported. A skill that ships a script needs that support added to `_sync.py`
  rather than left out silently, and `_sync.py` fails on the key until it is.
- The keys are `name`, `version`, `description`, `instructions`, `author`, `license`, `tags`,
  `categories`, and a `sync:` block with `freebuff:` / `gemini:` / `windsurf:` under it.

## Writing a `description`

A description is the one line every tool shows when deciding whether a skill is relevant, and it is
the most copied text here: it lands in `SKILL.md`, in `_meta.json` and in the Windsurf pointer. Two
rules keep it honest, both enforced by `python .agents/verify.py` (steps `descriptions` and `index`):

1. **It may only name commands its own `instructions.md` defines.** The check reads the commands out of
   the instruction lines — `- \`gate\`: …` — so a description cannot advertise something the skill
   does not do.
2. **A hyphenated word in a description is read as a command name.** Write "the coverage floor", not
   "the coverage-floor check", unless the skill really defines a command by that name.

## Adding a skill

1. Create `.skills/<name>/skill.yaml` and `.skills/<name>/instructions.md`.
2. Define every command the description names, one line each, in the form
   ``- `name <argument>`: what it does``.
3. Run `python .skills/_sync.py`, then add the row to `.agents/README.md` — the `index` step fails
   until it is there — and then `python .agents/verify.py`.

## Not a skill location

Skill content belongs in exactly one place. These are never written and are reported as duplicates if
they ever hold a copy: `.claude/skills/`, `.codex/skills/`, `.cursor/skills/`, `.trae/skills/`,
`.windsurf/skills/`, `.gemini/skills/`.
