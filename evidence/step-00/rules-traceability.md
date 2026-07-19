# Rules Traceability — Step 0

## Command

```bash
python3 scripts/validate-rules-traceability.py
```

Result: **48/48 checks passed**, exit `0`.

## Rule coverage

All 16 modular rule files exist under `.claude/rules/` and are numbered `00`–`15`
with no gap and no duplicate.

| Rule file | Foundation area | Validator assertion |
|---|---|---|
| `00-canonical-source.md` | Master Source is canonical; conflict resolution order | present |
| `01-status-and-evidence.md` | Status vocabulary; exact-SHA evidence | present |
| `02-multi-tenancy.md` | Tenant hierarchy; all 13 hard rules | **tenant isolation hard gate asserted here** |
| `03-security-and-privacy.md` | Security and privacy foundation; tracking portal safeguards | present |
| `04-financial-integrity.md` | Integer Rupiah; idempotency; reversal-only correction | **financial integrity hard gate asserted here** |
| `05-flutter-client-foundation.md` | Flutter surfaces; design and UX foundation; accessibility | present |
| `06-backend-api-foundation.md` | Laravel modular monolith; REST `/api/v1`; PostgreSQL; Redis; S3 | present |
| `07-offline-sync.md` | 9 offline-first rules; `client_reference`; no duplicate order/payment | present |
| `08-notification-and-whatsapp.md` | Provider abstraction; quiet hours 20.00–08.00; dedup; opt-out | present |
| `09-pickup-and-delivery.md` | Pickup/delivery; proof of pickup and delivery; courier cash reconciliation | present |
| `10-unclaimed-laundry.md` | Aging from first `READY_FOR_PICKUP`; H+1/H+3/H+7/H+14 ladder | present |
| `11-git-and-ci.md` | Branch model; PR-only to main; exact-SHA CI; pinned actions | present |
| `12-autonomous-execution.md` | Autonomous execution boundaries; NO-GO conditions | present |
| `13-testing-and-definition-of-done.md` | Testing expectations; DoD gates | present |
| `14-pricing-and-commercial.md` | Canonical pricing table and all guardrails | present |
| `15-current-product-status.md` | Canonical status snapshot | present |

## Hard gate placement

The validator asserts each foundation area appears in the correct rule file, so a
gate cannot be satisfied by an incidental mention elsewhere:

| Foundation area | Required location | Result |
|---|---|---|
| Tenant isolation hard gate | rule `02` | PASS |
| Financial integrity hard gate | rule `04` | PASS |
| Tracking portal foundation | rule `03` | PASS |
| Pickup and delivery foundation | rule `09` | PASS |
| Unclaimed laundry aging ladder | rule `10` | PASS |
| Offline-first foundation | rule `07` | PASS |
| WhatsApp foundation | rule `08` | PASS |
| Git and CI rules | rule `11` | PASS |
| Autonomous execution rules | rule `12` | PASS |
| Pricing and commercial rules | rule `14` | PASS |
| Current product status | rule `15` | PASS |

## Traceability document

`docs/GOVERNANCE_TRACEABILITY.md` maps every Master Source foundation area to its
rule file, its governing decision record, and the validator that enforces it. The
validator confirms the document references **all 16** rule filenames, so a rule
cannot be added or renamed without the matrix being updated.

## Root CLAUDE.md

`CLAUDE.md` carries a 16-row index table linking every rule file. It references
the Master Source rather than duplicating it, per rule `00`.

## Structural corroboration

Graphify reports `CLAUDE.md` at degree 17 and `docs/MASTER_SOURCE.md` at degree 35
— the highest in the graph — with **no orphaned governance document**. Every
foundation area participates in at least one relationship, so none exists only as
an isolated file without traceability.
