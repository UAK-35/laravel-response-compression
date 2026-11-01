# RC Issue — a prompt recorded as an issue, and kept

Use this skill when a prompt asks for an **issue**: a defect, a gap or a question that should be named
now and worked later. It is two things in one pass: the **response**, which establishes what the issue
is and what stands behind it, and the **document** under `docs/issues/` that sets it down — what is
wrong, where it was seen, how to reach it again, what a fix would have to satisfy, and what the
reading did not establish.

An issue that only exists in a response is one the next session re-discovers from nothing, which is
what this skill is for. The response is the work; the document is what survives it.

## When this skill activates

There are two ways in, and only the first of them is automatic:

- **A prompt that begins with the `/rc-issue` marker activates this skill, always.** The `/lpr-issue`
  spelling is the same marker: this skill was asked for with both names, so both fire rather than one
  being guessed at. The marker is the user saying "this is an RC issue", so it *is* the confirmation
  and nothing is asked first. A prompt that carries the marker never reaches the second rule.
- **A prompt that does not begin with a marker, but asks for an issue, asks one question before
  anything else.** The two phrases that lead here are *create an issue* and *create this as an issue*.
  Ask, exactly:

  > Is this an RC issue?

  On **yes**, activate this skill **before** beginning the response. On **no**, do not activate it,
  and answer the prompt as any other.

The marker is read first, so the question is only ever asked of a prompt that does not carry it. Those
two phrases are the only triggers that lead to the question; a prompt that merely mentions an issue
without asking for one is not this skill's.

## Commands

- `rc-issue <prompt>`: establish the issue and, once the response is finished, write the document.
  This is the whole skill: read and reproduce until the issue is answered, answer it, then set it down
  in `docs/issues/`.
- `record <name>`: write one issue as a markdown document under `docs/issues/` — the prompt as it was
  put, what the issue is, where it was observed, how to reproduce it, the evidence, the impact, and
  what the fix would have to satisfy. The name is the folder's next sequence number, the date the
  issue was made and a few words from the prompt, so a reader can find it again and the folder stays
  in the order the issues were written.

## The issue

Every activated issue ends with **one** markdown document under `docs/issues/`. It is written **after**
the response is finished rather than during it, because it is the issue kept and not a draft of it. The
folder is created by the first issue; nothing else here creates it.

- **Name it** `NNNN-YYYY-MM-DD_issue-<slug>.md` —
  `docs/issues/0001-2026-11-01_issue-vary-header-replaced.md`. `NNNN` is the folder's sequence
  number, at least four digits, and the issue you add is the number that belongs to its place in the
  order (see **Keeping the folder in order**). `YYYY-MM-DD` is the date the issue was made, and
  `issue` marks the file as an issue rather than a report, a plan or a design record. `<slug>` is a
  few lowercase words from the prompt joined by hyphens, because it is what a reader searches for.
- **Record the creation date and timezone inside it**, beside the sequence number — `Created:
  2026-11-01 (Asia/Karachi). Issue: 0001.` Editing the issue does not change that date.
- **Open with the prompt** as it was actually put, and then **what the issue is** in a sentence: the
  one thing that is wrong, stated so somebody who disagrees can say which part they disagree with.
- **Say where it was observed** — the file, the class, the setting, the version — and **how to reach
  it again**: the request, the input, the command, and what it printed against what was expected. An
  issue nobody can reproduce is a claim, not a report of one.
- **Put the evidence beside each claim**: the command and its output, the path and the line, the test
  and its name.
- **Say who is affected and how far it reaches**, so the issue can be weighed against the others in
  the folder rather than guessed at.
- **Say what a fix would have to satisfy**, and **what the reading did not establish**: the cases not
  tried, the versions not checked, the work left out — so the next reader knows where this one
  stopped rather than assuming the silence was coverage.

## Keeping the folder in order

The number is the folder's order rather than a label an issue is stuck with, and the date in the name
is the issue's own creation date.

- **Inventory the folder before adding an issue**, and order what is there by original creation time,
  earliest first. Use the creation timestamp an issue records where it has one, otherwise the date of
  the commit that added it, otherwise the file's own creation time. A filesystem date can describe a
  checkout or a copy rather than authorship, so record the evidence used and any uncertainty about it.
- **Break a tie by the existing filename**, and then number the resulting order consecutively from
  `0001`, including the issue being added.
- **Renumber when the evidence establishes an order** that the numbers do not state, preserving each
  issue's date and slug, and update the references to a renamed file in the same change.
- **Plan the complete rename map before moving anything**, and use temporary names inside the folder
  while doing it, so two issues never land on the same name.
- **If the original order cannot be established**, preserve the existing relative order, append the
  new issue, and say in the document which evidence was missing. A wrong order stated plainly is
  better than an invented one.
- **Reports, plans and investigations are not issues.** The documents under `docs/reports/`,
  `docs/planning/` and `docs/investigations/` are separate collections with their own conventions, and
  an issue's numbering never reaches them.

## Environment

- Search with `rg`, and read the package's own records before the code wherever one explains the
  other: `AGENTS.md`, `docs/guards.md` and the `docs/` records are where a decision is argued, and the
  `CHANGELOG.md` is where the reason for a change was written at the time it was made.
- The gate is `composer checks`, and the floor is `composer test:unit`. An issue may read them; it
  does not weaken them to make a reproduction convenient, and an issue that contradicts a guard is an
  issue about the guard.
- Which interpreter this checkout runs is the answer `.agents/php-bin.sh` gives, and a value a command
  needs is rendered from `.agents/machine.local.json` rather than written down.

## Writing the issue safely

An issue is a file a commit would carry, so it lives under the same two rules as every other file in
this repository:

- **No machine path.** A drive path, a home directory or a network share in an issue is refused by the
  path guards, which read every file a commit would carry. Where a path must be named, write a
  `__TOKEN__` instead, and keep the issue readable on a machine that is not this one.
- **No path that climbs above this root**, and no link to a file outside it.

An issue is not a `docs/` design record — those explain a decision, are linked from the README, and are
read by the docs-link guard. This folder is not, so an issue links nothing and nothing links to it and
neither is expected of it. It is a defect or a gap named and evidenced, rather than a decision argued.

## Usage

- `/rc-issue the middleware replaces a Vary header instead of adding to it`
- `/rc-issue create this as an issue: the staged check maps every tests/Support file to the whole suite`
- *create an issue for the brotli detection rejecting a valid compressed empty string* — this asks the
  question first, and proceeds only on yes.
- *is this worth an issue, or is it working as intended?* — this is a question rather than a request,
  so the skill is not activated.
