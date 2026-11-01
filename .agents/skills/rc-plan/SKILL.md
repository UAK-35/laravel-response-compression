---
name: rc-plan
description: "Write a plan about a prompt and keep it under docs/plans/, named by a sequence number, the date it was made and a slug. Activate when a prompt begins with the /rc-plan marker or the /lpr-plan marker, or when a prompt asks to create a plan, to plan the work of something, or says a plan is needed and the user answers yes to 'Is this an RC plan?'. Commands - rc-plan, record."
license: "MIT"
---

# RC Plan — a prompt planned, and the plan kept

Use this skill when a prompt asks for a **plan**: the order the work will be done in, what each step
touches, and how each step is known to have worked. It is two things in one pass: the **response**,
which is the plan worked out, and the **document** under `docs/plans/` that sets it down — the goal,
the steps in order, the evidence each step will be held to, and what the plan does not decide.

A plan that only exists in a response is one the next session re-derives from nothing, which is what
this skill is for. The response is the work; the document is what survives it.

## When this skill activates

There are two ways in, and only the first of them is automatic:

- **A prompt that begins with the `/rc-plan` marker activates this skill, always.** The `/lpr-plan`
  spelling is the same marker: this skill was asked for with both names, so both fire rather than one
  being guessed at. The marker is the user saying "this is an RC plan", so it *is* the confirmation
  and nothing is asked first. A prompt that carries the marker never reaches the second rule.
- **A prompt that does not begin with a marker, but asks for a plan, asks one question before
  anything else.** The three phrases that lead here are *create a plan*, *plan the* and *need a plan*.
  Ask, exactly:

  > Is this an RC plan?

  On **yes**, activate this skill **before** beginning the response. On **no**, do not activate it,
  and answer the prompt as any other.

The marker is read first, so the question is only ever asked of a prompt that does not carry it. Those
three phrases are the only triggers that lead to the question; a prompt that merely mentions a plan
without asking for one is not this skill's.

## Commands

- `rc-plan <prompt>`: work out the plan and, once the response is finished, write the document. This
  is the whole skill: read and decide until the plan is answered, answer it, then set it down in
  `docs/plans/`.
- `record <name>`: write one plan as a markdown document under `docs/plans/` — the prompt as it was
  put, the goal, the steps in order with what each touches and how it is verified, the risks, and
  what the plan does not decide. The name is the folder's next sequence number, the date the plan was
  made and a few words from the prompt, so a reader can find it again and the folder stays in the
  order the plans were written.

## The plan

Every activated plan ends with **one** markdown document under `docs/plans/`. It is written **after**
the response is finished rather than during it, because it is the plan kept and not a draft of it. The
folder is created by the first plan; nothing else here creates it.

- **Name it** `NNNN-YYYY-MM-DD_plan-<slug>.md` —
  `docs/plans/0001-2026-11-01_plan-guard-index-render.md`. `NNNN` is the folder's sequence number, at
  least four digits, and the plan you add is the number that belongs to its place in the order (see
  **Keeping the folder in order**). `YYYY-MM-DD` is the date the plan was made, and `plan` marks the
  file as a plan rather than a report or a design record. `<slug>` is a few lowercase words from the
  prompt joined by hyphens, because it is what a reader searches for.
- **Record the creation date and timezone inside it**, beside the sequence number — `Created:
  2026-11-01 (Asia/Karachi). Plan: 0001.` Editing the plan does not change that date.
- **Open with the prompt** as it was actually put, and the goal in a sentence: the state the work
  reaches when the plan is carried out.
- **Put the steps in order, one after another**, each naming what it touches — the file, the program,
  the setting — why it sits where it sits, and **how it is verified**, because a step nobody can check
  is a step the next session will argue about rather than do.
- **Name the risks and the way back**: what the plan would break if a step is wrong, and what undoes
  it. A plan with no rollback is one to reconsider before it is followed.
- **Say what the plan does not decide**: the open questions, the assumptions it rests on, and the
  work deliberately left out, so the next reader knows where this one stopped.

## Keeping the folder in order

The number is the folder's order rather than a label a plan is stuck with, and the date in the name is
the plan's own creation date.

- **Inventory the folder before adding a plan**, and order what is there by original creation time,
  earliest first. Use the creation timestamp a plan records where it has one, otherwise the date of
  the commit that added it, otherwise the file's own creation time. A filesystem date can describe a
  checkout or a copy rather than authorship, so record the evidence used and any uncertainty about it.
- **Break a tie by the existing filename**, and then number the resulting order consecutively from
  `0001`, including the plan being added.
- **Renumber when the evidence establishes an order** that the numbers do not state, preserving each
  plan's date and slug, and update the references to a renamed file in the same change.
- **Plan the complete rename map before moving anything**, and use temporary names inside the folder
  while doing it, so two plans never land on the same name.
- **If the original order cannot be established**, preserve the existing relative order, append the
  new plan, and say in the document which evidence was missing. A wrong order stated plainly is
  better than an invented one.
- **Reports and investigations are not plans.** The documents under `docs/reports/` and
  `docs/investigations/` are separate collections with their own conventions, and a plan's numbering
  never reaches them.

## Environment

- Search with `rg`, and read the package's own records before the code wherever one explains the
  other: `AGENTS.md`, `docs/guards.md` and the `docs/` records are where a decision is argued, and the
  `CHANGELOG.md` is where the reason for a change was written at the time it was made.
- The gate is `composer checks`, and the floor is `composer test:unit`. A plan may read them; it does
  not weaken them to make a step convenient, and a plan that contradicts a guard is a plan about the
  guard.
- Which interpreter this checkout runs is the answer `.agents/php-bin.sh` gives, and a value a command
  needs is rendered from `.agents/machine.local.json` rather than written down.

## Writing the plan safely

A plan is a file a commit would carry, so it lives under the same two rules as every other file in
this repository:

- **No machine path.** A drive path, a home directory or a network share in a plan is refused by the
  path guards, which read every file a commit would carry. Where a path must be named, write a
  `__TOKEN__` instead, and keep the plan readable on a machine that is not this one.
- **No path that climbs above this root**, and no link to a file outside it.

A plan is not a `docs/` design record — those explain a decision, are linked from the README, and are
read by the docs-link guard. This folder is not, so a plan links nothing and nothing links to it and
neither is expected of it. It is the order the work will take, rather than a decision argued.

## Usage

- `/rc-plan split the guard index render into a reading and a writer`
- `/rc-plan plan the move of the release programs under the rc names`
- *create a plan for moving the machine file checks into the gate* — this asks the question first, and
  proceeds only on yes.
- *need a plan for the next release* — the same question, the same rule.
