---
name: rc-report
description: "Write a report about a prompt and keep it under docs/reports/, named by a sequence number, the date it was made and a slug. Activate when a prompt begins with the /rc-report marker or the /lpr-report marker, or when a prompt asks to create a report, to report the state of something, or says a report is needed and the user answers yes to 'Is this an RC report?'. Commands - rc-report, record."
license: "MIT"
---

# RC Report — a prompt answered, and the answer kept as a report

Use this skill when a prompt asks for a **report**: an account of a state, a question answered at
length, a change or an audit set down for someone who was not there. It is two things in one pass: the
**response**, which is the work, and the **report**, which is a markdown document under
`docs/reports/` that says what was asked, what was examined, what was found, and what stands behind
each finding.

A response nobody wrote down is one the next reader has to ask for again, which is what this skill is
for. The response is the work; the report is what survives it.

## When this skill activates

There are two ways in, and only the first of them is automatic:

- **A prompt that begins with the `/rc-report` marker activates this skill, always.** The
  `/lpr-report` spelling is the same marker: this skill was asked for with both names, so both fire
  rather than one being guessed at. The marker is the user saying "this is an RC report", so it *is*
  the confirmation and nothing is asked first. A prompt that carries the marker never reaches the
  second rule.
- **A prompt that does not begin with a marker, but asks for a report, asks one question before
  anything else.** The three phrases that lead here are *create a report*, *output a report*, *report the* and *need a
  report*. Ask, exactly:

  > Is this an RC report?

  On **yes**, activate this skill **before** beginning the response. On **no**, do not activate it,
  and answer the prompt as any other.

The marker is read first, so the question is only ever asked of a prompt that does not carry it. Those
three phrases are the only triggers that lead to the question; a prompt that merely mentions a report
without asking for one is not this skill's.

## Commands

- `rc-report <prompt>`: answer the prompt and, once the response is finished, write the report. This
  is the whole skill: read and work until the prompt is answered, answer it, then set the answer down
  in `docs/reports/`.
- `record <name>`: write one report as a markdown document under `docs/reports/` — the prompt as it
  was put, what was examined, what was found and the evidence behind it, and what the response did
  not establish. The name is the folder's next sequence number, the date the report was made and a
  few words from the prompt, so a reader can find it again and the folder stays in the order the
  reports were written.

## The report

Every activated report ends with **one** markdown document under `docs/reports/`. It is written
**after** the response is finished rather than during it, because it is the answer kept and not a
draft of it. It is the response's content set down — not a second answer, and not a summary that
drops the evidence the response argued from.

- **Name it** `NNNN-YYYY-MM-DD_rpt-<slug>.md` —
  `docs/reports/0001-2026-10-30_rpt-agentic-setup-audit.md`. `NNNN` is the folder's sequence number,
  at least four digits, and the report you add is the number that belongs to its place in the order
  (see **Keeping the folder in order**). `YYYY-MM-DD` is the date the report was made, and `rpt` marks
  the file as a report rather than a design record. `<slug>` is a few lowercase words from the prompt
  joined by hyphens, because it is what a reader searches for.
- **Record the creation date and timezone inside it**, beside the sequence number — `Created:
  2026-10-30 (Asia/Karachi). Report: 0001.` Editing the report does not change that date.
- **Open with the prompt** as it was actually put, and the scope the response took: what was examined
  and what was deliberately left out.
- **Say what was read or run**, each file, reading and command named exactly, so the work can be
  repeated rather than believed. A finding that cannot be re-read is a claim, not a report.
- **Put the evidence beside each finding**: the command and what it printed, the path and the line,
  the test and its name — the reader should not have to take the report's word for anything it can
  check for itself.
- **Give the answer** in a sentence or two, and then **the limits**: what the response did not
  establish, or could not, so the next reader knows where this one stopped.

## Keeping the folder in order

The number is the folder's order rather than a label a report is stuck with, and the date in the name
is the report's own creation date.

- **Inventory the folder before adding a report**, and order what is there by original creation time,
  earliest first. Use the creation timestamp a report records where it has one, otherwise the date of
  the commit that added it, otherwise the file's own creation time. A filesystem date can describe a
  checkout or a copy rather than authorship, so record the evidence used and any uncertainty about it.
- **Break a tie by the existing filename**, and then number the resulting order consecutively from
  `0001`, including the report being added.
- **Renumber when the evidence establishes an order** that the numbers do not state, preserving each
  report's date and slug, and update the references to a renamed file in the same change.
- **Plan the complete rename map before moving anything**, and use temporary names inside the folder
  while doing it, so two reports never land on the same name.
- **If the original order cannot be established**, preserve the existing relative order, append the
  new report, and say in the report which evidence was missing. A wrong order stated plainly is
  better than an invented one.
- **Investigations are not reports.** The records under `docs/investigations/` are a separate
  collection with their own convention, and a report's numbering never reaches them.

## Environment

- Search with `rg`, and read the package's own records before the code wherever one explains the
  other: `AGENTS.md`, `docs/guards.md` and the `docs/` records are where a decision is argued, and the
  `CHANGELOG.md` is where the reason for a change was written at the time it was made.
- The gate is `composer checks`, and the floor is `composer test:unit`. A report may read them; it
  does not weaken them to make an answer convenient, and a finding that contradicts a guard is a
  finding about the guard.
- Which interpreter this checkout runs is the answer `.agents/php-bin.sh` gives, and a value a command
  needs is rendered from `.agents/machine.local.json` rather than written down.

## Writing the report safely

A report is a file a commit would carry, so it lives under the same two rules as every other file in
this repository:

- **No machine path.** A drive path, a home directory or a network share in a report is refused by the
  path guards, which read every file a commit would carry. Where a path must be named, write a
  `__TOKEN__` instead, and keep the report readable on a machine that is not this one.
- **No path that climbs above this root**, and no link to a file outside it.

A report is not a `docs/` design record — those explain a decision, are linked from the README, and
are read by the docs-link guard. This folder is not, so a report links nothing and nothing links to
it and neither is expected of it. It is the answer kept, rather than a decision argued. It is not an
investigation either: an investigation answers a question about how the package behaves, and a report
sets down what a prompt's response established.

## Usage

- `/rc-report audit the release programs for paths that are not checked`
- `/rc-report report the state of the guard index against tests/Support/`
- *create a report on what changed in the encoder this week* — this asks the question first, and
  proceeds only on yes.
- *output a report containing the findings after running syntax check*
- *need a report on the coverage floor after the last three commits* — the same question, the same
  rule.
