---
name: rc-runbook
description: "Write a runbook about a prompt and keep it under docs/runbooks/, named by a sequence number, the date it was made and a slug. Activate when a prompt begins with the /rc-runbook marker or the /lpr-runbook marker, or when a prompt asks to create a runbook or to create this as a runbook and the user answers yes to 'Is this an RC runbook?'. Commands - rc-runbook, record."
license: "MIT"
---

# RC Runbook — a procedure, set down and kept

Use this skill when a prompt asks for a **runbook**: the steps somebody follows to carry out an
operation they do not get to improvise — a release, a migration, a recovery, a handover between
sessions. It is two things in one pass: the **response**, which works out the procedure, and the
**document** under `docs/runbooks/` that sets it down — when the runbook is used, what must be true
before the first step, the steps in the order they are run, what each one proves, what to do when one
fails, and how to get back.

A procedure that only exists in a response is one the next session reconstructs from memory, which is
what this skill is for. The response is the work; the document is what survives it.

## When this skill activates

There are two ways in, and only the first of them is automatic:

- **A prompt that begins with the `/rc-runbook` marker activates this skill, always.** The
  `/lpr-runbook` spelling is the same marker: this skill was asked for with both names, so both fire
  rather than one being guessed at. The marker is the user saying "this is an RC runbook", so it *is*
  the confirmation and nothing is asked first. A prompt that carries the marker never reaches the
  second rule.
- **A prompt that does not begin with a marker, but asks for a runbook, asks one question before
  anything else.** The two phrases that lead here are *create a runbook* and *create this as a
  runbook*. Ask, exactly:

  > Is this an RC runbook?

  On **yes**, activate this skill **before** beginning the response. On **no**, do not activate it,
  and answer the prompt as any other.

The marker is read first, so the question is only ever asked of a prompt that does not carry it. Those
two phrases are the only triggers that lead to the question; a prompt that merely mentions a runbook
without asking for one is not this skill's.

## Commands

- `rc-runbook <prompt>`: work out the procedure and, once the response is finished, write the
  document. This is the whole skill: read and decide until the steps are settled, answer it, then set
  it down in `docs/runbooks/`.
- `record <name>`: write one runbook as a markdown document under `docs/runbooks/` — the prompt as it
  was put, when the runbook is used, the preconditions, the steps with the command each one runs and
  what it proves, the failure path, and the way back. The name is the folder's next sequence number,
  the date the runbook was made and a few words from the prompt, so a reader can find it again and the
  folder stays in the order the runbooks were written.

## The runbook

Every activated runbook ends with **one** markdown document under `docs/runbooks/`. It is written
**after** the response is finished rather than during it, because it is the procedure kept and not a
draft of it. The folder is created by the first runbook; nothing else here creates it.

- **Name it** `NNNN-YYYY-MM-DD_runbook-<slug>.md` —
  `docs/runbooks/0001-2026-11-01_runbook-tag-a-release.md`. `NNNN` is the folder's sequence number,
  at least four digits, and the runbook you add is the number that belongs to its place in the order
  (see **Keeping the folder in order**). `YYYY-MM-DD` is the date the runbook was made, and `runbook`
  marks the file as a procedure rather than a report, a plan or a design record. `<slug>` is a few
  lowercase words from the prompt joined by hyphens, because it is what a reader searches for.
- **Record the creation date and timezone inside it**, beside the sequence number — `Created:
  2026-11-01 (Asia/Karachi). Runbook: 0001.` Editing the runbook does not change that date.
- **Open with the prompt** as it was actually put, and **when this runbook is used**: the situation
  that sends a reader here, and the state they should be in when they start.
- **State the preconditions** as things that can be checked rather than assumed — the branch, the
  tools, the credentials, the clean tree — and say what to do when one is not met, because a procedure
  followed from the wrong starting point fails later and less obviously.
- **Put the steps in the order they are run**, one after another, each naming the exact command, what
  it touches, **what it proves**, and what to check before moving on. A step whose result nobody reads
  is a step that will be skipped on the run where it mattered.
- **Say what to do when a step fails**, beside that step rather than in a paragraph at the end: the
  failure that is expected, the one that means stop, and the one that is safe to ignore.
- **Give the way back**: what undoes the runbook, how far back it can be undone, and what cannot be
  undone once a later step has run.
- **Say what the runbook does not cover** — the neighbouring procedure, the case left out, the
  decision that is somebody else's — so a reader does not borrow it for an operation it was not
  written for.

## Keeping the folder in order

The number is the folder's order rather than a label a runbook is stuck with, and the date in the name
is the runbook's own creation date.

- **Inventory the folder before adding a runbook**, and order what is there by original creation time,
  earliest first. Use the creation timestamp a runbook records where it has one, otherwise the date of
  the commit that added it, otherwise the file's own creation time. A filesystem date can describe a
  checkout or a copy rather than authorship, so record the evidence used and any uncertainty about it.
- **Break a tie by the existing filename**, and then number the resulting order consecutively from
  `0001`, including the runbook being added.
- **Renumber when the evidence establishes an order** that the numbers do not state, preserving each
  runbook's date and slug, and update the references to a renamed file in the same change.
- **Plan the complete rename map before moving anything**, and use temporary names inside the folder
  while doing it, so two runbooks never land on the same name.
- **If the original order cannot be established**, preserve the existing relative order, append the
  new runbook, and say in the document which evidence was missing. A wrong order stated plainly is
  better than an invented one.
- **Reports, plans, issues and investigations are not runbooks.** The documents under `docs/reports/`,
  `docs/planning/`, `docs/issues/` and `docs/investigations/` are separate collections with their own
  conventions, and a runbook's numbering never reaches them. The two release runbooks at the root,
  `RELEASING.md` and `PUSHING.md`, are runbooks this package ships rather than records of one, and
  they stay where they are.

## Environment

- Search with `rg`, and read the package's own records before the code wherever one explains the
  other: `AGENTS.md`, `docs/guards.md` and the `docs/` records are where a decision is argued, and the
  `CHANGELOG.md` is where the reason for a change was written at the time it was made.
- The gate is `composer checks`, and the floor is `composer test:unit`. A runbook may read them; it
  does not weaken them to make a step convenient, and a runbook that contradicts a guard is a runbook
  about the guard.
- Which interpreter this checkout runs is the answer `.agents/php-bin.sh` gives, and a value a command
  needs is rendered from `.agents/machine.local.json` rather than written down.

## Writing the runbook safely

A runbook is a file a commit would carry, so it lives under the same two rules as every other file in
this repository:

- **No machine path.** A drive path, a home directory or a network share in a runbook is refused by
  the path guards, which read every file a commit would carry. Where a path must be named, write a
  `__TOKEN__` instead, and keep the runbook readable on a machine that is not this one.
- **No path that climbs above this root**, and no link to a file outside it.

A runbook is not a `docs/` design record — those explain a decision, are linked from the README, and
are read by the docs-link guard. This folder is not, so a runbook links nothing and nothing links to
it and neither is expected of it. It is a procedure a reader carries out, rather than a decision
argued.

## Usage

- `/rc-runbook the steps to cut and publish a version, from a green gate to the tag on the remote`
- `/rc-runbook create this as a runbook: recovering a checkout whose machine file was lost`
- *create a runbook for moving the repository to a new remote* — this asks the question first, and
  proceeds only on yes.
- *what does the release runbook say about a refused push?* — this is a question about a runbook
  rather than a request for one, so the skill is not activated.
