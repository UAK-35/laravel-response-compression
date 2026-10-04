# `release`

Weigh what the tree adds up to, and cut a version only when one was asked for. The lane is
`development`, written once in `.release-lane`; the releaser reads that file as its default branch and
refuses a cut anywhere else, so no command here passes `--branch=`.

## Usage

```bash
composer release -- --weigh             # what the tree adds up to; cuts nothing
composer release -- --weigh --dry-run   # the same, with the notes printed
composer release --                      # cut the version and stop
composer release -- --push               # cut it and push the commit and the tag
composer release -- --prerelease=alpha --push   # a prerelease lane
```

## What to do with it

1. **Weigh first, and report the number.** The weight is derived from the commits since the last tag
   and the public surface between the two revisions — a signature, a namespace, a default or a
   removed symbol. Say which of those it found, because that is the reasoning a reader needs.
2. **The changelog is the notes.** `## Unreleased` is what the release promotes; if the bullets do not
   add up to the change, fix the changelog before cutting rather than after.
3. **A tag is a published version.** Packagist reads tags, so a cut that is pushed is a version a
   consumer can install within minutes. Cutting is deliberate; `--weigh --dry-run` is cheap.
4. **After a cut, the runbooks are the record.** `RELEASING.md` says how a version is worked out and
   how to do it by hand; `PUSHING.md` says where the tag goes and carries the evidence table for the
   remote. Neither is installed with the package, so nothing downstream links to them.

## Related

- `.release-lane` — the lane itself, one line, read by the releaser as its default branch.
- `.agents/toolchain.json` — the `release` command.
- `AGENTS.md` section 10 — the lane, the numbering and the two runbooks.
- `.github/workflows/main.yml` — the same gate on the runner, and the monthly run that unpins the dev
  tools.
