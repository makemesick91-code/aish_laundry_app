# Tooling Report — Step 0

Only tooling that was genuinely used is listed. Nothing was installed merely to
satisfy a count, and no tool is claimed to have been used when it was not.

## Limit Saver 1

```
LIMIT-SAVER-1: NOT INSTALLED
```

Searched for `limit-saver-1`, `limit saver 1`, and `limit_saver_1` across:

- the project skill directory,
- the user Claude skill directory (`~/.claude/skills`),
- the installed plugin registry (`~/.claude/plugins/installed_plugins.json`),
- available slash commands.

No match, and no local equivalent. No repository was guessed and no
similarly-named package was installed. The equivalent **Level 1 protocol** was
applied instead:

- targeted file reads rather than whole-file dumps;
- `git diff --stat` and `git diff --check` before full diffs;
- validator output captured to files and summarised rather than echoed in full;
- exactly three parallel subagents with non-overlapping scope, plus two
  sequential specialist agents (security review, guard hardening);
- one canonical status file (`docs/STATUS.md`) rather than repeated restatement;
- checksums and a file manifest used for comparison instead of re-reading files.

No validation, security gate, or evidence requirement was reduced to save
context. This condition is non-blocking.

## Graphify

| Item | Value |
|---|---|
| Status | AVAILABLE AND USED |
| Version | 0.8.35 |
| Source | Pre-existing installation at `~/.local/bin/graphify` |
| Installer executed | NONE — already present, nothing downloaded |
| Credentials supplied | NONE |
| Mode | `graphify update . --no-cluster` (deterministic, no LLM backend) |

Full detail in `graphify-summary.md`. `graphify-out/` is git-ignored, so no cache
or internal Graphify output is committed. Only the summary document is committed.

## Subagents used

Three parallel agents with non-overlapping scope, per the execution policy:

| Agent | Scope | Outcome |
|---|---|---|
| Governance Documentation | `README`, `CONTRIBUTING`, `SECURITY`, `docs/**`, 15 decision records, runtime placeholder READMEs | 47 files; Master Source 1574 lines |
| Security and Rules | `CLAUDE.md`, `.claude/rules/**` (16 files), destructive guard, governance skill | 19 files; guard self-test initially 51/51 |
| CI and GitHub | `scripts/**` (14 validators), `.github/**`, `.editorconfig`, `.gitignore` | 24 files; validators adversarially tested |

Two further specialist agents were run sequentially:

| Agent | Scope | Outcome |
|---|---|---|
| Independent Security Reviewer | Adversarial review, read-only | 4 CRITICAL, 6 HIGH, 3 MEDIUM, 2 LOW findings |
| Guard Hardening | Remediation of the confirmed guard bypasses | See `security-review.md` |

The main agent did not accept any subagent claim without independent
verification. Two subagent claims were found to be materially wrong on
re-verification, and both are documented in `security-review.md`.

## MCP servers

| Server | Used | Reason |
|---|---|---|
| GitHub MCP | NO | Not connected in this session. `gh` CLI and `gh api` were sufficient for every required operation. |
| Supabase MCP | NO | Not required for Step 0. No database exists. |
| Higgsfield MCP | NO | Not required for Step 0. |
| context-mode MCP | NO | Level 1 protocol was applied directly. |

No production service was connected. No database, browser automation, payment,
WhatsApp, or cloud deployment MCP was used, in line with the Step 0 tooling policy.

## GitHub access method

All GitHub operations used the `gh` CLI and `gh api` (REST):

| Operation | Method |
|---|---|
| Repository inspection | `gh repo view --json` |
| Visibility change | `gh api -X PATCH repos/{owner}/{repo}` |
| `main` bootstrap | `gh api -X PUT .../contents/README.md` |
| Ruleset creation and verification | `gh api .../rulesets` |
| Pull request creation and merge | `gh pr` |
| CI run inspection | `gh run` / `gh api .../check-runs` |

## Local tooling

| Tool | Version / path | Use |
|---|---|---|
| `git` | `/usr/bin/git` | version control |
| `gh` | `/usr/bin/gh` | GitHub API and PR operations |
| `python3` | `/usr/bin/python3` | all validators, stdlib only, no third-party dependency |
| `sha256sum` | `/usr/bin/sha256sum` | Master Source checksum and file manifest |
| `actionlint` | v1.7.7 in CI only | workflow linting; downloaded from a pinned release and verified against the authentic upstream SHA-256 |

No package manager installed anything into the repository. No runtime dependency
manifest was created, which is why the validators are restricted to the Python
standard library and bash.

## Credential handling

- No token, key, or credential was written to any tracked file.
- The GitHub token value was never printed or stored; only authentication status
  and the account login were recorded.
- `.claude/settings.local.json` is git-ignored and is not committed.
- No user-level Claude configuration was modified or overwritten.
- The committed project `.claude/settings.json` contains only a `PreToolUse` hook
  registration and no secret; it does not weaken any user-level deny rule.
