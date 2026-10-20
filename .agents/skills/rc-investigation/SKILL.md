---
name: rc-investigation
description: "Investigate a question about this package and record the investigation as a markdown document under docs/investigations/. Activate when a prompt begins with the /rc-investigation marker, or when a prompt asks to investigate or to find out why and the user answers yes to 'Is this an RC package investigation?'. Commands - rc-investigation, record."
license: "MIT"
---

# RC Investigation — answering a question about this package, and keeping the answer

Use this skill when a question about **this package** has to be answered by reading the package rather
than by guessing — why something behaves as it does, where a value comes from, what a reading
measured, whether a report is real — and the answer is worth keeping after the session that found it.
It is two things in one pass: the **investigation**, which is reading this checkout until the question
is answered, and the **record**, which is a markdown document under `docs/investigations/` that says
what was asked, what was found, and what stands behind the finding.

An investigation nobody wrote down is one the next session repeats from nothing, which is what this
skill is for. The reading is the work; the record is what survives it.

## When this skill activates

There are two ways in, and only the first of them is automatic:

- **A prompt that begins with the `/rc-investigation` marker activates this skill, always.** The
  marker is the user saying "this is an RC package investigation", so it *is* the confirmation and
  nothing is asked first. A prompt that carries the marker never reaches the second rule.
- **A prompt that does not begin with the marker, but asks to investigate or to find out why, asks
  one question before anything else.** Ask, exactly:

  > Is this an RC package investigation?

  On **yes**, activate this skill **before** beginning the response. On **no**, do not activate it,
  and answer the prompt as any other.

The marker is read first, so the question is only ever asked of a prompt that does not carry it. The
word *investigate* and the phrase *find out why* are the only two triggers that lead to the question;
a prompt that merely mentions investigation without asking for one is not this skill's.

## Commands

- `rc-investigation <question>`: investigate the question against this package and, once the response
  is finished, write the record. This is the whole skill: read until the question is answered, answer
  it, then set the answer down in `docs/investigations/`.
- `record <name>`: write one investigation as a markdown document under `docs/investigations/` — the
  question as it was asked, what was read, what was found and what stands behind it, and what the
  reading did not establish. The name is a few words from the question, so a reader can find it again.

## The record

Every activated investigation ends with **one** markdown document under `docs/investigations/`. It is
written **after** the response is finished rather than during it, because it is a record of the answer
and not a draft of it. The folder is created by the first record; nothing else here creates it.

- **Name it** after the question in a few lowercase words joined by hyphens, and end it in `.md` —
  `docs/investigations/why-config-is-read-once.md`. The name is a reader's only index into the folder,
  so it should say what the question was rather than what the file is.
- **Open with the question** as it was actually put, and the date the investigation was made.
- **Say what was read**, each file, reading and command named exactly, so the reading can be repeated
  rather than believed. A finding that cannot be re-read is a claim, not a record.
- **Put the evidence beside each finding**: the command and what it printed, the path and the line,
  the test and its name — the reader should not have to take the record's word for anything it can
  check for itself.
- **Give the answer** in a sentence or two, and then **the limits**: what the investigation did not
  establish, or could not, so the next reader knows where this one stopped.

## Environment

- Search with `rg`, and read the package's own records before the code wherever one explains the
  other: `AGENTS.md`, `docs/guards.md` and the `docs/` records are where a decision is argued, and the
  `CHANGELOG.md` is where the reason for a change was written at the time it was made.
- The gate is `composer checks`, and the floor is `composer test:unit`. An investigation may read them;
  it does not weaken them to make an answer convenient, and a finding that contradicts a guard is a
  finding about the guard.
- Which interpreter this checkout runs is the answer `.agents/php-bin.sh` gives, and a value a command
  needs is rendered from `.agents/machine.local.json` rather than written down.

## Writing the record safely

A record is a file a commit would carry, so it lives under the same two rules as every other file in
this repository:

- **No machine path.** A drive path, a home directory or a network share in a record is refused by the
  path guards, which read every file a commit would carry. Where a path must be named, write a
  `__TOKEN__` instead, and keep the record readable on a machine that is not this one.
- **No path that climbs above this root**, and no link to a file outside it.

A record is not a `docs/` design record — those explain a decision, are linked from the README, and
are read by the docs-link guard. This folder is not, so a record links nothing and nothing links to it
and neither is expected of it. It is a working note that happens to be written down.

## Usage

- `/rc-investigation why is the configuration read once rather than per request`
- `/rc-investigation does the brotli encoder fall back to gzip, and where`
- *investigate why a declined encoding is still logged* — this asks the question first, and proceeds
  only on yes.
- *find out why the middleware refuses a user agent* — the same question, the same rule.
