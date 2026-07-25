# Step 7 — Customer Tracking and WhatsApp: Evidence Pack

**Step:** 7 — Customer Tracking and WhatsApp
**Status:** `IN PROGRESS` — Phase 1 governance/runtime-scope transition landed; feature runtime pending
**Master Source version:** 1.4.12
**Runtime scope opened by:** [DEC-0039](../../docs/decisions/DEC-0039-step-07-runtime-scope-transition.md)
**Baseline SHA:** `cfa7cf7399cd9769b522dc31d03edae09349a823` (post-Step-6 canonical `main`)

---

## What this pack is

This directory holds the **exact-SHA evidence** for Step 7. Every claim of a passing gate, test, or
verification is bound to the full 40-character commit SHA it was produced from (Rule 01, DEC-0013). A row
in the requirement matrix that says a requirement *will be* verified is a plan, not a result — only
captured output bound to a SHA proves anything.

**Sanitisation.** This repository is `PUBLIC` (AMENDMENT-0001, DEC-0016). Every datum in this pack is
fictional and recognisably so; no real customer name, phone number, address, token, OTP, or credential
appears. Deletion is not remediation, so nothing sensitive is committed in the first place (Rule 23,
Rule 45).

## Current contents

### `PHASE-1-GOVERNANCE-TRANSITION.md`

The DEC-0039 runtime-scope guard transition: the guard split (`STEP7_PLUS_FEATURE_TOKENS` →
`STEP7_FEATURE_TOKENS` + `STEP8_PLUS_FEATURE_TOKENS`), the `CANONICAL_CURRENT_STEP` advance `6 → 7`, the
Master Source `1.4.11 → 1.4.12` bump with refreshed checksum, the three-way status advance
(`PLANNED → IN PROGRESS`), and the captured governance-suite output at the transition SHA.

## What is NOT yet evidenced

FR-086 … FR-099 remain `NOT IMPLEMENTED`. No tracking-token, portal, OTP, or notification runtime exists
yet, so there is no test evidence for any of them. The runtime-scope guard permitting a label is **not**
evidence that a feature works, is tenant-safe, or is secure (Rule 36 hard rule 6). Live WhatsApp provider
delivery is **NOT VERIFIED** — no official-provider credentials are present, and none may be claimed until
real credentials and owner authorization exist.

## Standing external / owner-gated items

- Step 7 `GO` is owner-conferred against exact-SHA evidence; it is never self-declared by an agent.
- Merging to `main` is the owner's decision.
- Live WhatsApp Business provider credentials, an approved sender identity, and production template
  approval are external blockers; their absence does not block the provider abstraction, the fail-closed
  official adapter, the deterministic fake adapter, or the manual deep-link fallback.
- Deployment remains `ABSENT` and is not authorised by anything in Step 7.
