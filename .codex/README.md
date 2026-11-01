# Codex project setup

`AGENTS.md` at the repository root is the canonical instruction file. Follow its startup sequence;
`.agents/README.md` indexes skills, custom agents and commands. Codex uses the existing
`.agents/skills/` targets. This directory contains no second skill or agent definitions.

`config.toml` supplies portable project defaults: workspace-write execution, restricted shell
network access and automatic review of eligible approval requests. The working directory is the
implicit writable workspace; no extra writable roots are configured. Temporary-directory access
retains the current sandbox allowances. Repository-owned work follows `AGENTS.md` section 0.
Workspace-write is not an operating-system restriction
on all reads: the repository's reading boundary remains the instruction in that section.

The 64 KB project instruction limit accommodates the root `AGENTS.md`. The developer instruction
only points to the canonical files. Models, account credentials, tool paths, trust decisions and
session-specific artifact directories are left to the host; machine-local tool values remain in
`.agents/machine.local.json` as described by `AGENTS.md`.

Project configuration loads only for a trusted project, and host requirements and higher-priority
overrides can constrain it. These files do not change the permissions of an already running chat.
Open a new Codex chat rooted at this checkout to use the project defaults. No global trust setting
is written by this setup.

The configuration keys and loading behavior were checked against the official OpenAI
[configuration reference](https://learn.chatgpt.com/docs/config-file/config-reference) and
[configuration basics](https://learn.chatgpt.com/docs/config-file/config-basic).
