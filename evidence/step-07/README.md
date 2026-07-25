# Step 7 — Customer Tracking and WhatsApp: Evidence Pack

**Step:** 7 — Customer Tracking and WhatsApp
**Status:** `IN PROGRESS` — runtime built and `TESTED`; **`GO` is NOT conferred and is the owner's to give** (Rule 01)
**Master Source version:** 1.4.13
**Runtime scope opened by:** [DEC-0039](../../docs/decisions/DEC-0039-step-07-runtime-scope-transition.md)
**Open questions resolved by the owner:** [DEC-0040](../../docs/decisions/DEC-0040-oq-018-user-initiated-security-transaction-quiet-hours-exemption.md) (OQ-018) and [DEC-0041](../../docs/decisions/DEC-0041-oq-014-laravel-blade-as-the-public-tracking-portal-stack.md) (OQ-014)
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

### `RUNTIME-VERIFICATION.md`

The FR-086 … FR-099 runtime and its executed results: the backend suites, the migration run against the
authoritative PostgreSQL engine, the live schema scope check, the Flutter workspace, the adversarial
harnesses, the requirement → evidence traceability, and — in §7 — the two open questions the owner
resolved as DEC-0040 and DEC-0041.

### `VERIFY-STEP-07-FINAL.md`

The captured `bash scripts/verify-step-07.sh` run, verbatim, bound to the exact commit it measured.

## This paragraph was stale and is corrected

It previously read: *"FR-086 … FR-099 remain `NOT IMPLEMENTED`. No tracking-token, portal, OTP, or
notification runtime exists yet, so there is no test evidence for any of them."* That was true when
written, at the Phase 1 governance transition, and stopped being true when the runtime was built and
verified. Leaving it in place would have been a false claim in the other direction (Rule 01).

## What is NOT claimed

- **Step 7 is not `GO`.** `TESTED` is not `GO`; `GO` is owner-conferred against exact-SHA evidence and
  is never self-declared by an agent (Rule 01).
- **Live WhatsApp provider delivery is NOT VERIFIED.** No official-provider credentials are present, and
  no delivery may be claimed until real credentials and owner authorization exist. `SENT` means the
  provider accepted a message, never that a customer received one.
- **No deployment exists.** Deployment remains `ABSENT` and is not authorised by anything in Step 7.
- **The runtime-scope guard permitting a label is not evidence that a feature works**, is tenant-safe, or
  is secure (Rule 36 hard rule 6).
- **No performance figure and no accessibility conformance result is asserted.**

## Standing external / owner-gated items

- Step 7 `GO` is owner-conferred against exact-SHA evidence; it is never self-declared by an agent.
- Merging to `main` is the owner's decision.
- Live WhatsApp Business provider credentials, an approved sender identity, and production template
  approval are external blockers; their absence does not block the provider abstraction, the fail-closed
  official adapter, the deterministic fake adapter, or the manual deep-link fallback.
- Deployment remains `ABSENT` and is not authorised by anything in Step 7.
