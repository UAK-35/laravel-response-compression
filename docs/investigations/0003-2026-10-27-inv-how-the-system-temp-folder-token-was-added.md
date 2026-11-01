# How the `systemTempFolder` keys and `__SYSTEM_TEMP_FOLDER__` were added

**Asked** 2026-10-27: add a group to the machine-local file (`systemTempFolder.systemTempFolderPath`,
after an initial `tempFolder`/`tempFolderPath`), whose value a placeholder substitutes —
`__SYSTEM_TEMP_FOLDER__`. This is the record of the steps and why each one exists.

It is a work record rather than a `docs/` design record: it explains how this layer is extended, not
a behaviour of the package, so it lives here and links nothing.

## The layer being extended

A token is not a string the renderer happens to know. Three declarations in
[`.agents/render_local.py`](../../.agents/render_local.py) have to agree, and a fourth file is what a
reader copies:

| Declaration | What it says | What breaks without it |
|---|---|---|
| `TOKENS` | the placeholder, the section and key it is answered from, and what it is for | nothing resolves the token |
| `SECTIONS` | every section a machine file may carry, and what each is for | `--init` will not open the section on a fresh clone |
| `OWNED` | the sections and keys `--init` writes from what it knows | `MachineShape` refuses the token: *"a fresh clone is opened with that key absent"* |
| `.agents/machine.local.json.example` | the committed shape a reader copies | `MachineShape` refuses the section, or the owned key it does not show |

## The steps, in order

1. **Write the value into the machine file.** `.agents/machine.local.json` gains

   ```json
   "systemTempFolder": {
       "systemTempFolderPath": "<the platform's temp directory>"
   }
   ```

   This file is gitignored and is the only place a machine path belongs.

2. **Register the placeholder in `TOKENS`.** One entry, mapping the token to
   `("systemTempFolder", "systemTempFolderPath")` with a sentence saying what it is for. `--value`,
   every rendered settings file and the `Handoff` reading all answer from this one entry.

3. **Declare the section in `SECTIONS`.** Without it `--init` writes a file that does not contain the
   section, so a fresh clone has no key for the token to read.

4. **Add the key to `OWNED`.** This is the step that is easy to miss and is not optional: the shape
   guard holds *every* token to a key `--init` writes, precisely so a token cannot be added that a
   fresh clone would have to fill in by hand.

5. **Give `--init` a default.** `OWNED` says the key is written; the writer has to know what to write.
   The temp directory is a fact about the machine, so it is defaulted from `tempfile.gettempdir()`
   rather than asked for, and `import tempfile` was added for it. An empty value would also make the
   fresh-clone report stop calling the file a fresh clone.

6. **Show it in the example.** `.agents/machine.local.json.example` gains the section and a sentence
   under the key saying what a reader should put there. The guard refuses a declared section the
   example does not show, and a key `--init` writes that the example shows nowhere.

7. **Name it in `AGENTS.md`.** The token table in §3 is where a reader learns the placeholders; a
   token that exists but is not in the table is a name only the renderer knows.

8. **Count it in the handoff.** `handoff/HANDOFF.md` §2 says how many tokens the renderer reads behind
   a token (four → five) and measures the machine file's size and section count; both moved.

9. **Teach the layer's token step the name.** `.agents/verify.py`'s `tokens` step scans `AGENTS.md`,
   `.agents/` and the pointer files and refuses any token outside its `known` set. The name was added
   there — but only once the token took the double-underscore shape, see below.

10. **Write the changelog bullet.** `CHANGELOG.md` under `## Unreleased` → `### Changed`, saying what
    the machine file now carries and what answers from it.

## The rename, and why it mattered

The first draft used `_TEMP_FOLDER_`, as asked. It resolved, `--check` passed, and the guards were
green — which was the problem: `Handoff`'s own token pattern reads `__[A-Z0-9_]+__`, so a
single-underscore name is **invisible to the reading**. The token worked, but nothing held it to
`OWNED`, and no document naming it was compared with the renderer.

Renaming to `__SYSTEM_TEMP_FOLDER__` — and `systemTempFolder` / `systemTempFolderPath` with it —
brought it inside the shape the layer reads. That made step 4 and step 9 mandatory rather than
optional, and turned the token from "named in a table" into "held like the other four".

## What each guard says if a step is skipped

| Skip | The finding |
|---|---|
| step 2 | `--value` answers nothing; every render leaves the token in place |
| step 3 | `--init` writes no such section, so a fresh clone has no key to read |
| step 4 | `MachineShape`: *"`__SYSTEM_TEMP_FOLDER__` is answered from `systemTempFolder.systemTempFolderPath`, and `--init` writes no key there"* |
| step 5 | the fresh clone's report stops saying `--init` was handed answers for every owned section |
| step 6 | `MachineShape`: *"the example shows no `systemTempFolder` section"*, or a missing owned key |
| step 9 | `verify.py`'s `tokens` step: *"`__SYSTEM_TEMP_FOLDER__` is not one of … a token nothing knows is a name nothing resolves"* |

## Verification

```
python .agents/render_local.py --value __SYSTEM_TEMP_FOLDER__   # this machine's temp directory
python .agents/render_local.py --check                          # exit 0
bin/tool.php pest tests/Unit/Support/MachineShapeTest.php        # 9 passed (70 assertions)
bin/tool.php pest tests/Unit/Support/MutationHarness/MachineShapeTest.php   # 4 passed (12)
bin/tool.php pest tests/Unit/Support/HandoffTest.php             # 6 passed (20)
bin/tool.php pest tests/Unit/Support/HandoffStateTest.php        # 9 passed (32)
python .agents/verify.py --layer                                 # 11 steps, 0 findings
```

The hold is the part worth keeping: it plants each shape break and requires the reading to name it,
so the token is held by a planted fault rather than by a green run alone.

## What this did not do

- No settings file uses the token yet. It resolves, and a template may name it, but nothing in
  `.trae/` or `.kiro/` was changed to consume it.
- The value in the machine file is this machine's; the record deliberately does not reproduce it.
  `tempfile.gettempdir()` is what a `--init` writes, and it is what to compare against if the two
  ever disagree.
