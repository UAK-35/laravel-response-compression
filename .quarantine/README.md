# What was in here, what became of it, and what this directory is for now

Three files were sitting in this checkout on 2026-10-25 and belong to something else. They were put
here rather than deleted or matched by an ignore rule, because both path readings take the files a
commit would carry: a foreign machine record inside one is a finding, and an ignore rule that hid it
instead would be the quiet answer this repository refuses — `.gitignore` carries that argument, one
paragraph above the rule for this directory.

Nothing foreign is here now: all three are resolved, and what this directory holds is this README.

- `opencode.jsonc`, `opencode.v1-backup.jsonc` — the session configs a workspace agent wrote for the
  editor on this machine. Both arrived as staged additions rather than history, so unstaging them
  removed nothing a commit had ever carried. **Committed 2026-10-27 as `63a2465`**, and tracked from
  there as `opencode.jsonc.template` and `opencode.v1-backup.jsonc.template`: this checkout decided
  the editor setup it is worked in is its own. Each rendered config is written from its template by
  `python manage-config.py` and ignored at the root, so the machine's own temp directory the config
  names is a fact about the file on disk rather than about any commit.
- `.agents/sample.machine.local.json.txt` — the machine record for `laravel-weighted-dbmanager`, the
  package next door. **Deleted 2026-10-27.** The decision of 2026-10-25 was to keep it here rather
  than delete or adopt it, and what changed is not its owner but what keeping it costs: a copy of
  another checkout's record names a branch, a head, a tag and a GPG signing key that only that
  checkout can keep true, so nothing in this tree could tell whether it was still right — and it was
  the one entry every run printed a `[NOTE] Ignored:` line about, which is how a rule holding
  somebody else's record gets said, not a way to keep one on file. `handoff/HANDOFF.md` §7 item 2
  carries the decision and what replaced it. There is nothing to put back: the file is the
  neighbour's, it is not this checkout's to keep, and no reading here asks for it.

What keeps this directory from being a rule over names is `.gitignore`, which refuses it: nothing
inside can be committed while it is here, and a file that is not this checkout's to commit or to
delete has somewhere to be that is not somebody else's tree. That room is what the directory is for
now — the next file of that kind goes in here rather than outdoors — and the mechanism it leaves
working is `tests/Support/Ignored.php`: a machine record that arrives under any name is named in the
note a run prints, whether the rule holding it is this directory or something else.
