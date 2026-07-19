# Graphify Summary — Step 0

## Tool

| Item | Value |
|---|---|
| Tool | Graphify |
| Version | 0.8.35 |
| Location | `~/.local/bin/graphify` (pre-existing installation, not installed by this session) |
| Command | `graphify update . --no-cluster` |
| Mode | Deterministic code/document re-extraction. No LLM backend, no API key configured. |
| Scope | Repository root `/home/fikri/Projects/aish_laundry` |
| Exclusions | `.git/`, and `graphify-out/` is git-ignored so no cache or internal output is committed |
| Exit code | 0 |

Graphify was run only AFTER the governance corpus existed (Master Source, rules,
decision records, validators), per the Step 0 tooling policy. Running it against an
empty repository would have produced no meaningful signal.

## Graph result

| Metric | Value |
|---|---|
| Nodes | 958 |
| Links | 967 |
| Governance source files represented | 60 |
| Node file types | document 883, code 67, rationale 8 |
| Link relations | contains 842, calls 44, imports 21, imports_from 20, defines 19, rationale_for 8, references 6, method 6, uses 1 |

## Findings

### F1 — Canonical centre is correct (INFORMATIONAL)

The highest-degree node in the graph is `Aish Laundry App — Master Source`
(`docs/MASTER_SOURCE.md`, degree 35). The next governance hubs are
`docs/ROADMAP.md` (18), `.github/pull_request_template.md` (18) and `CLAUDE.md` (17).

This matches the intended governance topology: the Master Source is the single
canonical centre, and `CLAUDE.md` references rather than duplicates it.

### F2 — Decision records are structurally uniform (INFORMATIONAL)

All fifteen `DEC-####` nodes report an identical degree of 13. A malformed or
truncated decision record would show a lower degree. The uniform degree is
consistent with all fifteen records carrying the same required heading set,
which `scripts/validate-decisions.py` independently confirms.

### F3 — No orphaned governance documents (INFORMATIONAL)

Zero files had all of their nodes unlinked. Every governance document participates
in at least one relationship, so no foundation area exists only as an isolated file
without traceability.

### F4 — Pricing is asserted in eight files (WATCH)

The canonical pricing figures appear in eight tracked files:

- `docs/MASTER_SOURCE.md` (canonical definition)
- `docs/decisions/DEC-0009-initial-commercial-pricing.md` (the deciding record)
- `.claude/rules/14-pricing-and-commercial.md`
- `docs/ROADMAP.md`
- `docs/DEFINITION_OF_DONE.md`
- `docs/decisions/DEC-0002`, `DEC-0005`, `DEC-0011` (contextual references)

Multi-sourcing a commercial fact is a genuine drift risk: a future price change
edited in one file would silently contradict the other seven.

**Status:** accepted with mitigation, not silently ignored.

**Mitigation:** `scripts/validate-pricing.py` parses every `docs/**` and
`.claude/rules/**` markdown file and fails the build when any plan's figure
disagrees with the canonical value. Drift therefore breaks CI rather than
surviving into a release. The validator was adversarially tested: injecting
`Starter Rp89.000 / 2 outlet / 9 staff`, a lifetime-plan offer, and a 30-day
trial were all detected.

**Residual risk:** the validator enforces consistency against the canonical
figures, so a coordinated edit of all eight files would pass. That is correct
behaviour — a deliberate repricing is legitimate, and
`DEC-0009` plus the pricing guardrails require a new decision record for it.

### F5 — Roadmap step numbers are asserted in thirteen files (WATCH)

`Step 14` is referenced across thirteen files. Mitigated by
`scripts/validate-roadmap.py`, which asserts Steps 0–14 exist exactly once with
canonical titles and that Steps 1–14 remain `PLANNED`.

## Remediation performed

No broken governance relationship required remediation. F4 and F5 are structural
observations with existing automated mitigations, recorded here as WATCH items
rather than closed defects.

## Honest limitations

- Graphify ran in deterministic extraction mode only. No semantic/LLM clustering
  was performed because no LLM backend is configured, so community naming and
  semantic relationship inference were not exercised.
- `rationale_for` and `references` edge counts are low (8 and 6). Graphify's
  markdown extraction does not resolve every prose cross-reference into a typed
  edge, so the graph understates documentation linkage. Internal link integrity
  is therefore verified independently and exhaustively by
  `scripts/validate-markdown-links.py`, which checked every relative link in every
  tracked markdown file and found zero broken links.
- The graph is a structural aid. It is not treated as proof of governance
  correctness; the validators are the authority.

## Result

`GRAPHIFY: AVAILABLE AND USED` — deterministic mode, exit 0, no blocking finding.
