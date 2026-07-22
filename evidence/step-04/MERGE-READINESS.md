# Step 4 — Merge-Readiness Report

**Classification: `NO-GO — STEP 4 IN PROGRESS / MERGE-READY HANDOFF`**

This is not a product failure and not an external blocker. Every gate that can be
executed has been executed and passes. The classification records that the two
remaining actions — **merging PR #18** and **conferring a `GO` tag** — are the
repository owner's, and are deliberately reserved (Rule 01, Rule 12).

---

## 1. SHA equality

| | |
|---|---|
| Local HEAD at verification | `1363998bb918cc74d1bb54e13d8bd7c119b675aa` |
| Remote feature branch | `1363998bb918cc74d1bb54e13d8bd7c119b675aa` |
| PR #18 head | `1363998bb918cc74d1bb54e13d8bd7c119b675aa` |
| CI-tested SHA | `1363998bb918cc74d1bb54e13d8bd7c119b675aa` |
| Evidence anchor | `6abd3fdc918a740ea400819a23e9b0cc371778f5` |

Evidence indirection is documented in [`README.md`](README.md) §7, including the
one file outside `evidence/` and `docs/` in that delta and why it cannot affect a
result.

## 2. Authoritative CI

**11 workflows, 11 successful**, all at the exact SHA above. Run identifiers are
recorded in [`authoritative-ci.txt`](authoritative-ci.txt).

Every CI run against an earlier SHA is checkpoint evidence only and is not cited
for closure.

## 3. Local gates, freshly counted at the final SHA

| Gate | Result |
|---|---|
| Backend suite (PostgreSQL 18.4) | **466 passed**, 4212 assertions |
| `migrate:fresh` → rollback (5) → re-apply (5) | clean |
| Consent + price-list triggers | all five `tgenabled='A'` |
| Supersession composite FK | present |
| `price_lists` unique constraints | 2, no duplicate |
| `dart format` / `dart analyze` | clean / no issues |
| Ops Android + packages | **268 passed** |
| Console Web | **59 passed** |
| Customer Android | **27 passed** |
| Governance validators (8) | all PASS |
| Master Source checksum | OK |
| Adversarial harnesses | 11/11, 9/9, 10/10 |
| verify-step-00 / 01 / 02 | PASS / PASS / PASS |
| verify-step-03 | 52 passed, 0 failed, 1 skipped |
| **verify-step-04** | **27 passed, 0 failed, 1 skipped** |

**The one skip, named exactly:** `DEC-0026 scaffolding suite — precondition not
met in this environment (exit 78)`. It is an owner-approved branch/path pin: the
scaffold-authorization suite runs 38/38 only on a Step 3 feature branch and
reports a visible exit-78 SKIP elsewhere. It is never represented as PASS, and no
Step 4 gate hides behind it — the Step 4 verifier runs its own gates
independently and reports this as a named skip. A skip is not a pass.

## 4. Findings

Three independent review rounds, twenty-three findings.

**Three first remediations were refuted by a later round** — SEC-12, N2, N5. Each
failed identically: a control documented as absolute, an unenumerated bypass, and
a green test proving only the narrower case. Full chronology in
[`INDEPENDENT-REVIEW-CLOSURE.md`](INDEPENDENT-REVIEW-CLOSURE.md).

All `FIXED_AND_VERIFIED` except **NEW-04**, `ACCEPTED_OPERATIONAL_RESIDUAL` —
local developer and reviewer reliability only, accepted after CI database
isolation was proven across six conditions.

## 5. What is NOT claimed

- **Deployment is `ABSENT`.** Nothing ran in production or against production
  data, and nothing here authorises a deployment.
- **UAT is `NOT STARTED`.**
- **Step 5 remains `NOT IMPLEMENTED`** — orders, POS, payments, invoices,
  receipts, production, pickup, delivery, tracking, WhatsApp, reminders,
  reporting, subscription. A price list is not a priced order.
- **Seven requirements are `PARTIAL_STEP_4_FOUNDATION_COMPLETE /
  STEP_5_E2E_PENDING`**, each with a handoff entry naming the proof Step 5 owes.
  FR-036 is among them and is a mandatory financial-integrity obligation.
- **The database guarantees hold at the application connection boundary only.**
  They do not bind a principal that may rewrite the schema — in development, the
  application role *is* the superuser and table owner. The required non-owner,
  non-superuser role is `REQUIRED_FOR_FUTURE_DEPLOYMENT` /
  `NOT_YET_PROVISIONED` / `NOT_CLAIMED_AS_CURRENT_CONTROL`.
- **No independent human review.** Governance is single-maintainer; independent
  human approval is `ABSENT` (DEC-0017). The three review rounds were separate
  agent contexts under adversarial instruction — a compensating control, not a
  second human reader.

## 6. Owner actions reserved

1. Merge PR #18 — **not performed**.
2. Confer and tag Step 4 `GO` — **not performed**. No Step 4 tag exists.
3. The Step 3 `GO` tag is untouched.
