# `release`

Weigh what the tree adds up to, and cut a version only when one was asked for. The lane is
`development`; the releaser defaults to `main` and refuses a cut anywhere it was not told to expect
one, so every run passes `--branch=development`.

## Usage

```bash
composer release -- --weigh --branch=development            # what the tree adds up to; cuts nothing
composer release -- --weigh --dry-run --branch=development  # the same, with the notes printed
composer release -- --branch=development                    # cut the version and stop
composer release -- --branch=development --push             # cut it and push the commit and the tag
composer release -- --prerelease=alpha --branch=development --push   # a prerelease lane
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

- `.agents/toolchain.json` — the `release` command.
- `AGENTS.md` section 10 — the lane, the numbering and the two runbooks.
- `.github/workflows/main.yml` — the same gate on the runner, and the monthly run that unpins the dev
  tools.
