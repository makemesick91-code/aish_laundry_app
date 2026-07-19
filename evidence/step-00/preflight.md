# Preflight — Step 0

## Environment

| Item | Observed value |
|---|---|
| Working directory | `/home/fikri/Projects/aish_laundry` |
| Initial state | Empty directory (`total 8`, only `.` and `..`) |
| Initial git state | `fatal: not a git repository` — confirmed not a repository |
| Platform | Linux |
| Shell | bash |

The canonical fact "direktori kosong total / bukan Git repository" was verified by
direct observation before any file was written. It was not assumed.

## Tool availability

| Tool | Status |
|---|---|
| `git` | `/usr/bin/git` |
| `gh` | `/usr/bin/gh` |
| `python3` | `/usr/bin/python3` |
| `jq` | `/usr/bin/jq` |
| `sha256sum` | `/usr/bin/sha256sum` |
| `actionlint` | NOT installed locally — installed inside CI from a pinned, checksum-verified release tarball |
| `gitleaks` | NOT installed — secret scanning implemented as `scripts/validate-secrets.sh` using stdlib/grep patterns |

## Authentication

| Item | Status |
|---|---|
| GitHub authentication | PRESENT |
| Authenticated account | `makemesick91-code` |
| Protocol | HTTPS |
| Token type | Fine-grained personal access token |

The token value is deliberately NOT recorded in this evidence pack. Only the
authentication status and account login are recorded, per the credential rules.

## Skill and tooling discovery

| Item | Result |
|---|---|
| `limit-saver-1` / `limit saver 1` / `limit_saver_1` | **NOT INSTALLED** |

Searched the project skill directory, the user Claude skill directory
(`~/.claude/skills`), the installed plugin registry
(`~/.claude/plugins/installed_plugins.json`), and available slash commands.
No skill with that name or a local equivalent was found.

No repository was guessed, no similarly-named package was installed, and no
claim is made that the skill was used. The equivalent Level 1 context-saving
protocol was applied instead. This condition is non-blocking.

| Item | Result |
|---|---|
| Graphify | **AVAILABLE** — v0.8.35 at `~/.local/bin/graphify`, pre-existing installation |

Graphify was already installed. No installer was downloaded or executed, and no
credential was supplied to it. See `graphify-summary.md`.

## Target repository preflight

| Check | Result |
|---|---|
| `gh repo view makemesick91-code/aish_laundry_app` | Repository EXISTS |
| Owner | `makemesick91-code` — matches the authenticated and permitted account |
| `isEmpty` | `true` — no other project present |
| Visibility at discovery | `PUBLIC` |
| Default branch at discovery | empty (repository had no commits) |

Because the repository existed but was verifiably empty and owned by the
permitted account, it was NOT treated as a foreign project. No overwrite, no
force push, and no deletion was performed at any point.

## Blocker discovered during preflight

Attempting to create a branch ruleset while the repository was PRIVATE returned:

```
HTTP 403
"Upgrade to GitHub Pro or make this repository public to enable this feature."
```

The account is on the GitHub free plan, where rulesets and branch protection are
unavailable on private repositories. This produced a direct conflict between two
mandatory requirements: private visibility, and an enforced ruleset on `main`.

The conflict, the tradeoff, and the resolution are recorded in
`repository-verification.md` and in `docs/ASSUMPTIONS.md` as `AMENDMENT-0001`.
