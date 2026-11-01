# Investigations

Working notes, one per question answered about this package — what was asked, what was read, what
was found and what stands behind it, and what the reading did not establish. They are written by the
`rc-investigation` skill, which is authored in `.skills/rc-investigation/` and read from
`.agents/skills/rc-investigation/SKILL.md`.

A record here is **not** a design record. The `docs/` records explain a decision and are linked from
the README, so the docs-link guard resolves every link in them and notices one nothing links to; this
folder is not read by that guard, so a record links nothing and nothing links to it and neither is
expected of it. It is the reading kept, rather than a decision argued.

Each file is named `NNNN-YYYY-MM-DD-<slug>.md` — a sequence number, the date the record was made and a
few lowercase words from the question joined by hyphens, such as
`0001-2026-10-25-why-config-is-read-once.md`. The number keeps the folder in the order the
investigations were made, and the slug is what a reader searches for.

## The rules a record follows

The same two the rest of this repository does, because a record is a file a commit would carry:

- **No machine path** — no drive path, home directory or network share. Where a path has to be named,
  write a `__TOKEN__` instead, and keep the record readable on a machine that is not this one.
- **No path that climbs above this root**, and no link to a file outside it.
