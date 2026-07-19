# Tooling Report — Step 1

Records what tooling was actually used, what was looked for and not found, and what was deliberately
not connected. Nothing here claims a capability that was not exercised.

Canonical policy: [`../../docs/TOOLING_POLICY.md`](../../docs/TOOLING_POLICY.md).

---

## 1. Limit Saver Level 1

**Status: `NOT INSTALLED` — LEVEL 1 PROTOCOL USED.**

A skill named `limit-saver-1`, `limit saver 1`, or `limit_saver_1` was searched for across the project
skill directory (`.claude/skills/`), the available-skills listing, and the plugin/slash-command
inventory. **No such skill exists in this environment.** No package with a similar name was installed;
installing an unvetted look-alike would be a supply-chain risk and is forbidden by the tooling policy.

The absence of the skill is not a blocker. The Level 1 protocol was applied manually:

| Practice | How it was applied |
|---|---|
| Targeted reads | Files were read in the ranges needed rather than wholesale where practical. |
| `rg` / `grep` for search | Content location used search rather than full-file reads. |
| `git diff --stat` before full diffs | Change review started from the stat summary. |
| Long output stored, not echoed | Validator output was captured to evidence files rather than repeated. |
| One canonical status file | `docs/STATUS.md` remains the single status authority. |
| Maximum three parallel subagents | Exactly three were used; see §2. |
| No overlapping subagent scopes | Each agent owned a disjoint document set and a disjoint requirement-ID range. |
| No agent reads the whole repository | Each agent was given the canonical facts it needed in its brief. |
| Deterministic validators | All validators are standard-library Python or shell; no LLM in the gate path. |
| Security review not traded for context | The security review was performed in full; see `security-review.md`. |
| Failed output never hidden | Every validator failure encountered during the step is recorded, including the ones that turned out to be validator bugs. |

## 2. Subagents

Three parallel subagents, matching the mandated split. Each was given the canonical vocabulary
(statuses, bounded contexts, aggregates, personas, pricing, the reminder ladder) inline in its brief so
that no agent had to read the whole repository, and so that terminology could not drift between them.

| Agent | Scope | Requirement-ID ownership |
|---|---|---|
| Product and Journey | `docs/product/` (10 documents) | `FR-`, `RPT-`, `SUB-` |
| Domain and State Machine | `docs/domain/` (19), `docs/state-machines/` (10) | `TEN-`, `FIN-`, `OFF-`, `TRK-`, `DEL-`, `UCL-`, `NOT-` |
| Security and Traceability | `docs/security/` (6), `docs/quality/` (3) | `SEC-`, `NFR-` |

Disjoint ID ranges were assigned deliberately: without them, three agents writing concurrently would
have collided on identifiers, and a requirement ID collision silently changes the meaning of every
document that cites it.

**Main-agent verification.** Subagent output was not accepted on trust. The main agent ran every
validator against the produced corpus, investigated each failure, and distinguished genuine content
gaps from validator defects. Both categories were fixed; neither was hidden. Where a subagent reported
a cross-agent gap it could not fix, that gap was resolved by the main agent or dispatched back with a
specific brief — not waived.

**Honesty note.** One subagent reported that it had needed two fixes during its own verification and
that it did not edit any validator to obtain a green result. That is the required behaviour and it is
recorded here rather than omitted.

## 3. Graphify

| Item | Value |
|---|---|
| Tool | Graphify |
| Version | 0.8.35 — the same version used at Step 0 |
| Location | `~/.local/bin/graphify` (pre-existing installation; not installed by this session) |
| Command | `graphify update . --no-cluster` |
| Mode | Deterministic re-extraction. No LLM backend and no API key configured. |

Graphify was run **only after** the Step 1 corpus was complete, so that the graph reflects the finished
document set rather than a partial one. Results are in
[`graphify-summary.md`](graphify-summary.md).

`graphify-out/` is git-ignored; no cache or internal output is committed. Only the summary is.

## 4. Skills

| Skill | Used | Note |
|---|---|---|
| `aish-laundry-governance` (project skill) | **Yes** | Invoked before governance work, as required by `CLAUDE.md` §14. |
| `limit-saver-1` | No | Not installed in this environment (§1). |

No skill was claimed as used that was not used.

## 5. MCP servers

**No MCP server was used to mutate this repository**, to create infrastructure, or to publish anything
on the owner's behalf.

MCP servers connected in the environment were available but not required for Step 1, which is a
documentation step. The following were deliberately **NOT** connected or used, because Step 1 has no
need of them and connecting them would create risk with no benefit:

- database MCP;
- payment provider MCP;
- WhatsApp provider MCP;
- browser automation MCP;
- production server MCP;
- cloud deployment MCP;
- map provider MCP;
- any customer data source.

**No production connection of any kind was made.** No repository content was sent to an external
service for training or storage.

## 6. GitHub tooling

`gh` CLI and `git` were used for branch, commit, push, pull request, and check-run inspection.

**The repository ruleset was NOT modified.** Adding the new Step 1 checks to branch protection is a
repository-settings change and therefore owner territory (Rule 11 rule 21, Rule 12). This is raised in
the pull request rather than performed.

**No tag was created, moved, or deleted by the operations recorded in this report.** The Step 0 GO tag
was verified as unmoved.

## 7. What was not done

Stated plainly so that absence is not mistaken for completion:

- No application code was written, because Step 1 creates no runtime.
- No test framework was installed and no application test was run.
- No diagram was visually rendered; Mermaid validation is **structural only** and is labelled as such
  in the validator's own output.
- No independent human review occurred. Governance is single-maintainer and independent human approval
  is **`ABSENT`** (DEC-0016).
