# Graphify Summary — Step 1

**Exact commit SHA:** `663f432d68eeaec4a7cd7d5f7b0d477bd9fa2948`  
**Timestamp:** 2026-07-19 17:36:51 WIB

## Tool

| Item | Value |
|---|---|
| Tool | Graphify |
| Version | graphify 0.8.35 — the same version used at Step 0 |
| Location | `~/.local/bin/graphify` (pre-existing installation; not installed by this session) |
| Command | `graphify update . --no-cluster` |
| Mode | Deterministic re-extraction. **No LLM backend and no API key configured.** |
| Scope | Repository root |
| Exclusions | `.git/`; `graphify-out/` is git-ignored, so no cache or internal output is committed |
| Exit code | 0 |

Graphify was run **only after** the Step 1 corpus was complete, so the graph reflects the finished
document set rather than a partial one.

## Graph result

| Metric | Value |
|---|---|
| Nodes | 2353 |
| Links | 3391 |
| **Orphan nodes** | **0** |
| Node file types | document 2189, code 143, rationale 21 |
| Link relations | contains 2948, calls 189, imports_from 83, imports 62, defines 50, rationale_for 27, references 18, method 12, uses 2 |

### Nodes by repository area

| Area | Nodes |
|---|---|
| `other` | 563 |
| `docs/domain/` | 355 |
| `.claude/rules/` | 235 |
| `docs/decisions/` | 234 |
| `docs/product/` | 203 |
| `evidence/` | 188 |
| `docs/security/` | 169 |
| `docs/state-machines/` | 156 |
| `scripts/` | 145 |
| `docs/quality/` | 105 |

## Findings

| Target | Result |
|---|---|
| Orphan critical governance documents | **0** |
| Orphan nodes of any kind | **0** |
| Critical broken traceability links | **0** |

Every Step 1 area is represented in the graph, and every node participates in at least one
relation. No Step 1 document is disconnected from the corpus.

## Requirement traceability

Requirement-level traceability is **not** derived from the graph. It is computed deterministically
by `scripts/validate-step-01-traceability.py`, which is the authoritative check and is bound to the
same SHA:

- Requirements defined: **498**, each in its authoritative register.
- Traced to an acceptance criterion or the traceability matrix: **498 / 498 (100%)**.
- **Orphaned requirements: 0.**
- Acceptance criteria citing a requirement that does not exist: **0**.
- `CRITICAL` / `HIGH` threats: **34**, every one referenced by an acceptance criterion.

## Honesty notes

- Graphify ran **without** an LLM backend. No semantic extraction was performed, and none is
  claimed. The graph is a deterministic structural extraction.
- The graph is a **structural** artefact. It shows that documents reference one another; it does
  not verify that the referenced content is correct. Correctness is asserted by the validators,
  not by the graph.
- No Graphify cache or internal output is committed; only this summary.
