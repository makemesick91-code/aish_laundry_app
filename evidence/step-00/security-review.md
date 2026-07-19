# Security Review — Step 0

## Method

An independent security reviewer agent performed an adversarial read-only review
of the whole repository, with instructions not to rubber-stamp and to reproduce
every finding by executing the code before reporting it. Remediation was then
performed and **independently re-verified by the main agent** against the
reviewer's exact reproduction payloads. No finding was closed on the basis of a
remediation claim alone.

## Classification summary

| Severity | Found | Closed | Open |
|---|---|---|---|
| CRITICAL | 4 | 4 | 0 |
| HIGH | 6 | 6 | 0 |
| MEDIUM | 3 | 3 | 0 |
| LOW | 2 | 2 | 0 |
| INFORMATIONAL (clean categories) | 9 | — | — |

All CRITICAL and HIGH findings are closed, as required before GO.

## CRITICAL

### C1 — Guard failed open on duplicate JSON key — CLOSED

`.claude/hooks/guard-destructive-operations.sh`

`json.load` silently keeps the last value for a duplicate key, so
`{"tool_input":{"command":"git push --force","command":"ls"}}` was classified as
`ls` and **allowed**. This is the production `PreToolUse` path.

**Fix:** parse with an `object_pairs_hook` that rejects any object containing
duplicate keys, failing closed with exit 2.
**Verified:** rc=2.

### C2 — Guard failed open on array-valued command — CLOSED

`{"tool_input":{"command":["git","push","--force"]}}` stringified to
`['git', 'push', '--force']`; the quote characters broke every classifier anchor
and nothing matched.

**Fix:** a non-string `command` value now fails closed with exit 2.
**Verified:** rc=2.

### C3 — Guard failed open on one leading space — CLOSED

The JSON branch tested `case "$candidate" in '{'*)` **before** trimming, so a
single leading space skipped JSON extraction entirely and the raw envelope was
flat-matched, where every dangerous token sat behind a quote and matched nothing.

**Fix:** the candidate is trimmed before the JSON detection test.
**Verified:** rc=2.

### C4 — Secret scanner silenced by a filter word in the file path — CLOSED

`scripts/validate-secrets.sh`

The placeholder filter was applied with `grep -v -E` to the full
`path:line:content` grep record, so the **file path** could suppress a real
finding. The word list also contained ordinary prose words (`never`, `example`,
`TODO`, `masked`, `hashed`, `tidak`, `jangan`, `dilarang`, `tanpa`).

Reproduced: two live-format GitHub tokens were committed and the validator
returned **PASS with exit 0** — one in `docs/never.md` (silenced by the path),
one carrying a trailing `# example` comment (silenced by the content). Any file
named `docs/examples.md`, `NEVER_COMMIT.md`, or `TODO.md` was a credential
free-fire zone while the required `Security / secret-scan` check stayed green.

On a **PUBLIC** repository this is a live credential-exfiltration path, which is
why it is rated CRITICAL rather than HIGH.

**Fix:** the filter is now applied only to the matched content with the
`path:line:` prefix stripped, and the word list is narrowed to unambiguous
placeholder markers only.
**Verified by reproduction:** all three planted tokens are now detected
(`docs/leak1.md`, `docs/leakE.md`, `docs/never.md`); the clean repository still
returns exit 0.

## HIGH — all CLOSED

| ID | Finding | Fix | Verified |
|---|---|---|---|
| H1 | `git -C <path>` and `--git-dir=` defeated every git rule, because patterns required the subcommand to follow `git` directly | `normalize()` repeatedly strips git global options (`-C`, `-c`, `--git-dir`, `--work-tree`, `--namespace`, `--exec-path`, in both `=` and space forms) | rc=2 |
| H2 | `bash -c`, `sh -c`, `eval` wrappers hid the payload behind quotes, which were absent from the anchor class | quotes added to the anchor class, quotes stripped in `normalize()`, and wrapper constructs blocked as a class | rc=2 |
| H3 | Force push without a `--force` flag: `git push origin +main`, `--force-if-includes` | `+refspec` form matched; `--force-if-includes` added to the flag alternation | rc=2 |
| H4 | History-destruction commands entirely absent: `reflog expire`, `gc --prune`, `filter-branch`, `filter-repo`, `update-ref -d`, `stash clear`, `rebase --root`, `checkout HEAD -- .` | rules added for all of them | rc=2 |
| H5 | `rm` rule missed quoted, absolute-path and home targets: `rm -rf "/"`, `/bin/rm -rf /`, `rm -rf /home/fikri`, `rm -rf ~/Projects` | quote stripping; `rm` matched as a path suffix; token-scanning root-ish target detection covering `/`, `~`, `$HOME`, ancestors of `$HOME`, and any absolute path with ≤2 segments | rc=2 |
| H6 | Non-`rm` destruction primitives unguarded: `find / -delete`, `dd of=/dev/sda`, `shred`, `> <governance file>` | rules added for each | rc=2 |

**H4 is worth stating plainly:** the guard previously blocked the *recoverable*
`git reset --hard` while allowing `git reflog expire` followed by
`git gc --prune=now`, which together make that reset **permanently
unrecoverable**. The guard was protecting against the reversible operation and
permitting the irreversible one.

## MEDIUM — all CLOSED

| ID | Finding | Fix | Verified |
|---|---|---|---|
| M1 | `validate-no-runtime.py` covered only `.dart .php .kt .java .swift`, so a complete Node, Deno, Go, Rust, or Python backend could be committed while the validator printed "no application runtime present" and the required check stayed green | extensions extended to `.ts .tsx .js .jsx .mjs .cjs .go .rs .rb .cs .py`; manifests `deno.json`, `go.mod`, `Cargo.toml`, `Gemfile`, `requirements.txt`, `pyproject.toml`, gradle and `pom.xml` added; `.py`/`.sh` exempt **only** under `scripts/`, `.claude/hooks/`, `.github/` | 5 planted runtime files detected; clean tree still exit 0 |
| M2 | `gh` destructive subcommands only partially covered — `gh repo edit --visibility` (the exact unrequested-visibility-change the adjacent rule exists to prevent), `gh release delete`, `gh workflow disable` (disables the required CI gates outright) | rules added | rc=2 |
| M3 | The guard's `--self-test` reported 51/51 PASS while ~20 confirmed bypasses existed, and CI ran it as a gate — the suite was certifying a guard that did not hold. A governance-truthfulness problem as much as a security one | every confirmed bypass added as an expected-BLOCK case, plus must-allow regression cases; suite grew from 51 to **171 cases**, including a JSON-envelope section that drives `main()` end-to-end rather than only `classify()` | 171/171 PASS |

## LOW — all CLOSED

| ID | Finding | Fix |
|---|---|---|
| L1 | A malformed envelope returned exit 1. In the `PreToolUse` contract **only exit 2 denies**, so schema drift would have silently downgraded the guard from fail-closed to fail-noisy | JSON payload paths now return exit 2; exit 1 is reachable only for genuine usage errors and self-test failure |
| L2 | SQL rules matched only `DROP DATABASE` / `DROP SCHEMA` | `DROP TABLE`, `ALTER TABLE … DROP`, and `DELETE FROM` without a `WHERE` clause added |

## Independent re-verification by the main agent

The remediation was not accepted on trust. Every reviewer payload was re-run:

| Set | Cases | Result |
|---|---|---|
| C1–C3, L1 JSON fail-opens | 4 | 4/4 rc=2 |
| H1–H6, M2, L2 classifier bypasses | 26 | 26/26 rc=2 |
| Must-allow regression set | 34 | **0 failures** — every legitimate operation still permitted |
| Guard self-test | 171 | 171/171 PASS |
| Full governance suite | 11 gates | 11/11 PASS, exit 0 |

The regression set matters as much as the block set: a guard that blocks
legitimate work is its own failure mode. Confirmed still allowed:
`git push origin <feature-branch>`, `git push origin <tag>`, `git tag -a`,
`gh pr merge`, `rm -rf build/`, `rm -rf ./node_modules`, deep in-project paths,
`git cleanup-notes`, `chmod -R 755 scripts`, and `grep -n ">" docs/MASTER_SOURCE.md`.

## Categories verified CLEAN — no finding

| Category | Result |
|---|---|
| Credential leakage | No PAT, no email address, no private key, no `.env`, no AWS/Slack material in any tracked or untracked file. **Neither the developer's token nor their email leaked.** The GitHub username `makemesick91-code` appears in 13 files as required repository-identity data, which is public and not a credential |
| GitHub Actions security | `actions/checkout` pinned to full SHA `11bd71901bbe5b1630ceea73d27597364c9af683` (v4.2.2) at all 5 call sites; no third-party actions at all; `permissions: contents: read` at workflow and job level; `persist-credentials: false` on every checkout; `timeout-minutes` on every job; no `pull_request_target`; no secrets referenced |
| Script injection | Every `run:` block checked. The only `${{ }}` values reaching a shell (`needs.*.result`) are routed through `env:` and quoted — the correct pattern. Remaining uses are in `concurrency.group`, which is not a shell context |
| Supply chain | No curl-pipe-to-shell anywhere. actionlint is version-pinned, `sha256sum --check --strict` verified against the authentic upstream digest, then extracted, then cross-checked by reported version |
| Guard cannot execute its input | No `eval`/`exec` on the candidate; injection probes produced no side effects. The candidate is only ever matched as text |
| Governance truthfulness | No file claims a feature is implemented, tests pass, deployment exists, or CI is green. `runtime-detection.yml` explicitly refuses to fake results and reports `NOT_APPLICABLE_UNTIL_RUNTIME_EXISTS` |
| Visibility honesty | 8 files correctly state the repository is PUBLIC. **No file claims it is private.** The Master Source, the AI execution policy, and rule 15 each explicitly forbid that false claim |
| Pricing / roadmap divergence | 20/20 consistent across `docs/` and `.claude/rules/`. No lifetime-cloud-plan contradiction |
| Tenant isolation / financial integrity | Dedicated rules 02 and 04, backing policies, and `DEC-0012`, all cross-linked and traceability-validated 48/48 |
| Path traversal / symlink | `validate-no-runtime.py` walks with `followlinks=False` and detects repo-escaping symlinks. `validate-secrets.sh` quotes correctly and is newline-safe |

## Accepted residual risks

These are documented rather than silently carried.

| Risk | Rating | Rationale |
|---|---|---|
| `TRUNCATE` matches on any word boundary, so a command merely containing the word "truncate" is blocked | LOW | Fail-closed was the deliberate preference for destructive SQL. False positive, not a security hole |
| `git stash clear` also covers `drop`; `git gc` also blocks `--aggressive` alone | LOW | Intentionally broader than specified, on fail-closed grounds. May refuse commands a user considers routine |
| The output-redirection rule matches against a quote-preserving view while all other rules use the quote-stripped view | LOW | Necessary so `grep -n ">" docs/MASTER_SOURCE.md` is not mistaken for a truncating redirect. Documented in the script |
| `Required Gate` can only depend on jobs within its own workflow file | MEDIUM | Documented in `governance.yml`. Mitigated by requiring all six checks independently in the ruleset rather than relying on the gate to cover the other two workflows |
| Repository is PUBLIC, not PRIVATE | — | Not a defect but a deliberate owner decision under a plan constraint. See `repository-verification.md` and `AMENDMENT-0001` |

## Process note

Two subagent claims were found to be materially wrong on independent
verification, which is why every claim in this evidence pack is re-tested rather
than relayed:

1. The rules agent reported the guard self-test passing at 51/51 and the guard
   tester as probing "the JSON-stdin PreToolUse convention". The guard in fact
   **failed open** on that exact convention.
2. The guard's own self-test certified it as correct while roughly twenty
   bypasses existed. A self-test is not evidence of its own adequacy.
