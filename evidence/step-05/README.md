# Step 5 — POS, Order, and Payment Foundation: Evidence Pack

**Step:** 5 — POS, Order, and Payment Foundation
**Status:** `IN PROGRESS` — backend foundation complete and verified; `GO` is the owner's to confer after merge and the remaining scope below.
**Authorised by:** the canonical roadmap (Master Source §24) — runtime scope opened by
[DEC-0035](../../docs/decisions/DEC-0035-step-05-runtime-scope-transition.md).
**Master Source version:** 1.4.6

## Exact-SHA binding (Rule 01, DEC-0013)

Every gate result in this pack was produced at commit
**`c2565fca4d64bad15b75830d37ab39c8ac06f060`**, on a clean working tree, against the
authoritative **PostgreSQL** development database (Rule 43). This README and the captured `.txt`
files are committed in the immediately following commit; the results belong to the SHA above, not to
the commit that stores them.

**Sanitisation.** Every captured file is test/gate console output — test names, pass counts, and
classification lines only. No customer datum, phone number, address, token, secret, or credential
appears in any file; every test fixture uses recognisably fictional data (e.g. `0812-0000-0000`),
scanned before commit (Rule 23).

## What was verified (captured files)

| Gate | File | Result |
|---|---|---|
| Full backend suite (regression + Step 5), live PostgreSQL | [`full-suite.txt`](full-suite.txt) | **536 passed** (5204 assertions), 0 failed |
| Step 5 suites (Ordering + Payments) | [`step5-suites.txt`](step5-suites.txt) | **70 passed** (136 assertions) |
| Migration up / rollback / re-apply (orders, payments) | [`migrations.txt`](migrations.txt) | all `DONE` |
| Governance validator suite (7 validators) | [`governance.txt`](governance.txt) | **PASS** |
| Runtime-scope guard (`classify`) | [`runtime-scope.txt`](runtime-scope.txt) | PASS — within scope |
| DEC-0035 label audit (Step 5 residual) | [`dec-0035-labels.txt`](dec-0035-labels.txt) | PASS |
| DEC-0030 label audit (step-aware) | [`dec-0030-labels.txt`](dec-0030-labels.txt) | PASS |
| Step 5 adversarial harness | [`adversarial-step05.txt`](adversarial-step05.txt) | **12/12** |
| Step 4 adversarial harness (step-aware) | [`adversarial-step04.txt`](adversarial-step04.txt) | **4/4** |
| Live-schema Step 5 scope guard | [`schema-scope.txt`](schema-scope.txt) | within Step 5 scope — 3 Step 5 tables, 0 forbidden |
| No float in any money path | [`money-rules.txt`](money-rules.txt) | PASS (21/21) |
| MASTER_SOURCE checksum | [`checksum.txt`](checksum.txt) | OK |

The canonical Step 5 verifier is [`scripts/verify-step-05.sh`](../../scripts/verify-step-05.sh); it
orchestrates the above plus the delegated Step 0-4 regression (including the Flutter gates, which run
in CI / an environment with Flutter on PATH).

## Requirement → evidence traceability (Step 5 backend)

| Requirement | Enforced by | Proven in |
|---|---|---|
| FR-048/050/053/055/058/059/060, FR-036 snapshot | orders/order_lines schema + `OrderRegistry` | `OrderingSchemaTest` (16), `OrderRegistryTest` (13) |
| FR-051 server-authoritative totals | `OrderPricing`, DB CHECK `total = subtotal - discount` | `OrderPricingTest` (10), surface test (client total ignored) |
| FR-052 nota | `ReceiptProjection` (captured-price snapshot) | `PaymentRegistryTest`, surface test |
| FR-061/062/064 payment methods, idempotency, no client-claimed paid | `PaymentRegistry`, UNIQUE(client_reference) | `PaymentRegistryTest` (14), `OrderPaymentSurfaceTest` |
| FR-063 gateway callback verification + replay reject | `PaymentRegistry::confirmGateway` | `PaymentRegistryTest` |
| FR-065/067 refund/void by reversal, with reason | `PaymentRegistry::reverse` | `PaymentRegistryTest` |
| FR-066 no hard delete of financial records | `payments` ENABLE ALWAYS delete trigger | `PaymentsSchemaTest` ("can never be hard deleted") |
| FR-070 receivable (derived) | `OrderBalance` | `PaymentRegistryTest`, `OrderPaymentSurfaceTest` |
| Tenant isolation (Rule 02/48) — every access path | composite FKs + tenant-scoped queries + policies | schema tests (cross-tenant reject), surface test (404 + empty list) |
| RBAC (Rule 40) | `OrderPolicy`/`PaymentPolicy` + `PermissionRegistry` | `OrderPaymentSurfaceTest` (403s), full auth suite |

## What is NOT yet done (honest scope, Rule 01)

This pack evidences the **backend foundation**. The following remain and are why Step 5 is not `GO`:

- **Unit F — Flutter/operator POS UI** (customer/service selection, item entry, payment confirmation,
  receipt, and their loading/empty/error/unauthorized/conflict states) is **NOT IMPLEMENTED**. The
  backend API contract it consumes is complete and tested.
- **Full `verify-step-05.sh` run including the delegated Flutter Step 0-4 gates** is intended for CI /
  an environment with Flutter on PATH; the backend gates above were captured directly here.
- **Owner merge to `main`** (Rule 12) and **owner-conferred `GO`** (Rule 01) — `GO` is never
  self-declared by an agent.
- **Deployment** remains `ABSENT` and is not authorised by anything in Step 5.
- **OQ-017** (order-line rounding mode) is a flagged open question; the foundation uses a single,
  changeable `HALF_UP` constant pending owner ratification.
