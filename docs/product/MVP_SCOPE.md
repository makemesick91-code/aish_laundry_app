# Aish Laundry App — MVP Scope

**Document version: 1.0.0** · **Step: 1 — Product Requirement and Domain Model**
**Status of every capability described here: NOT IMPLEMENTED**

Canonical source: [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §22, locked by
[DEC-0015](../decisions/DEC-0015-mvp-focuses-on-laundry-operations.md). Subordinate to the Master Source.

Related: [`PRODUCT_REQUIREMENTS.md`](PRODUCT_REQUIREMENTS.md) ·
[`REQUIREMENT_TRACEABILITY.md`](REQUIREMENT_TRACEABILITY.md) · [`../ROADMAP.md`](../ROADMAP.md)

---

## 1. MVP definition

**The MVP is the smallest product that lets a single laundry tenant run a real working day end to end,
and lets its customers track their laundry without installing anything.**

Two clauses, both binding. "Run a real working day end to end" means order intake through production
through handover through cash reconciliation, without falling back to paper. "Without installing
anything" means the Portal Tracking Publik works from the first order, with no application, no account,
and no password ([DEC-0006](../decisions/DEC-0006-public-tracking-without-app-installation.md)).

**Rationale.** Operations first, because a laundry that cannot take an order does not care about loyalty
points. The customer application comes after the portal because the portal already solves the customer's
real problem, and the app must never become a prerequisite for tracking
([DEC-0014](../decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md),
[`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §22.4).

---

## 2. In the MVP — Steps 3 to 10

Reproduced from [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §22.2, with the requirement ranges this Step
assigns.

| Capability | Canonical Step | Requirements | Status |
| --- | --- | --- | --- |
| Authentication with phone + OTP | Step 3 | FR-001 … FR-010 | NOT IMPLEMENTED |
| Tenancy, brands, outlets, memberships, tenant switcher | Step 3 | FR-011 … FR-020 | NOT IMPLEMENTED |
| RBAC with server-side authorisation | Step 3 | FR-007, FR-008, FR-009, FR-010 | NOT IMPLEMENTED |
| Customers, services, price lists, outlet master data | Step 4 | FR-021 … FR-047 | NOT IMPLEMENTED |
| POS order intake, nota, payment, refund/void with audit | Step 5 | FR-048 … FR-070 | NOT IMPLEMENTED |
| Production stages, status lifecycle including `READY_FOR_PICKUP` | Step 6 | FR-071 … FR-085 | NOT IMPLEMENTED |
| Public tracking portal with secure tokens | Step 7 | FR-086 … FR-092 | NOT IMPLEMENTED |
| WhatsApp notification with provider abstraction and fallback | Step 7 | FR-093 … FR-099 | NOT IMPLEMENTED |
| Pickup and delivery with proof and courier cash | Step 8 | FR-100 … FR-111 | NOT IMPLEMENTED |
| Unclaimed laundry H+1/H+3/H+7/H+14 and its dashboard | Step 9 | FR-112 … FR-117 | NOT IMPLEMENTED |
| Shift closing, reconciliation, core reports, owner portfolio | Step 10 | RPT-001 … RPT-019 | NOT IMPLEMENTED |

**Requirement count inside the MVP:** FR-001 … FR-117 (117 functional) plus RPT-001 … RPT-019
(19 reporting) = **136 requirements**.

---

## 3. After the MVP — Steps 11 to 14

| Capability | Canonical Step | Requirements |
| --- | --- | --- |
| Customer Android application, loyalty, feedback, invoices | Step 11 | FR-118 … FR-120 |
| Subscription, plan limits, platform administration | Step 12 | SUB-001 … SUB-020, RPT-020 |
| Security hardening, performance budgets, backup and recovery | Step 13 | `NFR` and `SEC` series |
| Pilot and commercial launch | Step 14 | Metric baselining; UAT |

**Requirement count outside the MVP:** FR-118 … FR-120 (3), RPT-020 (1), SUB-001 … SUB-020 (20) =
**24 requirements**.

Deferring these is a deliberate scope decision, not an oversight. In particular:

- The **Customer Android application is deferred to Step 11** precisely because the portal must be proven
  first. Deferring it is the mechanism that guarantees the portal never becomes secondary.
- **Subscription and billing are deferred to Step 12** because a product that cannot run a working day
  has nothing to bill for. The commercial decision is locked at Step 0; only its implementation is
  deferred.
- **Numeric performance budgets are deferred to Step 13** because they must be measured on real devices,
  not invented ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §19.3).

---

## 4. Explicitly out of the MVP and out of the product

Reproduced from [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §23. These are not "later"; they are **not
part of the product**: a general-purpose ERP; a general-purpose POS for other retail verticals; an
accounting system; a payroll system; a marketplace; a courier network; **a route optimisation engine**;
an AI decision-maker for money; **automatic disposal of unclaimed laundry**; offline payment gateway
confirmation; iOS applications at this stage; on-premise deployment at this stage; a lifetime plan;
multi-tenant data blending for cross-tenant analytics.

---

## 5. MVP acceptance criteria

Acceptance criteria are observable, verifiable statements. Each is satisfied only by **exact-SHA
evidence** — the full forty-character commit SHA, the exact command, the captured output, an
Asia/Jakarta timestamp, and the environment
([DEC-0013](../decisions/DEC-0013-exact-sha-evidence-before-go.md)).

**Verification status of every criterion below is `NOT STARTED`.** There is nothing to verify; backend
runtime is **ABSENT** and Flutter workspace is **ABSENT**.

| ID | Acceptance criterion | Verifies | Step |
| --- | --- | --- | --- |
| AC-001 | A user authenticates with phone and OTP, and a repeated OTP request is rate limited. | FR-001, FR-003 | 3 |
| AC-002 | A revoked session stops working immediately, and revoking one device does not force other devices to re-authenticate. | FR-005, FR-006 | 3 |
| AC-003 | A request carrying a `tenant_id` for a tenant the authenticated user has no membership in is denied, and the denial is recorded. | FR-007, FR-010 | 3 |
| AC-004 | A newly created role holds no permissions until permissions are explicitly granted. | FR-009 | 3 |
| AC-005 | A member of tenant A cannot read, list, count, search, export, or mutate any record of tenant B, by direct identifier, filter parameter, report endpoint, or file URL. Proven by negative tests across every access path. | FR-007, FR-019, `TEN` series | 3 |
| AC-006 | Switching tenants issues a new server-side context and leaves no cached data from the previous tenant visible in any client. | FR-017 | 3 |
| AC-007 | The same phone number registered in two tenants produces two separate customer profiles that are never merged or cross-referenced. | FR-022, FR-019 | 4 |
| AC-008 | A recorded marketing opt-out survives a data import and a bulk update unchanged. | FR-028 | 4 |
| AC-009 | Publishing a new price list version leaves every existing order's total and every reprinted nota unchanged. | FR-035, FR-036 | 4 |
| AC-010 | No floating-point type appears in any pricing, total, discount, tax, payment, refund, or reconciliation path; every stored money value is integer Rupiah. | FR-037, FR-038 | 4 |
| AC-011 | A price override without the required permission is refused, and a permitted override records a reason. | FR-039 | 4 |
| AC-012 | An order total presented by a client is recomputed and overridden by the server, and the server value is the one stored. | FR-051 | 5 |
| AC-013 | Submitting the same order twice with the same `client_reference` produces exactly one order. | FR-059, FR-062 | 5 |
| AC-014 | Submitting the same payment twice with the same `client_reference` — by retry, queue replay, double tap, or network timeout — produces exactly one payment, and the second submission returns the original result. | FR-062 | 5 |
| AC-015 | A replayed gateway callback is rejected, and a callback whose signature, amount, or status does not verify against the gateway does not mark the order paid. | FR-063, FR-064 | 5 |
| AC-016 | No interface path exists to delete a financial transaction; a correction produces a reversal or adjustment entry with the original preserved. | FR-066, FR-067 | 5 |
| AC-017 | A refund or void without the required permission is refused; a permitted one records actor, timestamp, amount, and reason. | FR-065 | 5 |
| AC-018 | Concurrent submissions against the same order do not create two payments. | FR-068 | 5 |
| AC-019 | An order intake completed with the network disabled is queued, survives an application kill and a device reboot, and syncs to exactly one order and one payment. | FR-059, `OFF` series | 5 |
| AC-020 | Only transitions defined in the canonical status machine are permitted; an invalid transition is refused server-side. | FR-071, FR-072 | 6 |
| AC-021 | The first transition to `READY_FOR_PICKUP` records a timestamp exactly once; a subsequent `REWORK` cycle and second arrival at `READY_FOR_PICKUP` leaves that timestamp unchanged. | FR-076, FR-077 | 6 |
| AC-022 | An attempt to reach `READY_FOR_PICKUP` without passing `QUALITY_CONTROL`, where tenant policy requires it, is refused server-side. | FR-081 | 6 |
| AC-023 | A tracking token is not the order number, is not derivable from it, and is stored hashed; the plaintext token exists only in the link. | FR-086, FR-087 | 7 |
| AC-024 | A revoked tracking link stops working, and an expired link states plainly that it has expired. | FR-088 | 7 |
| AC-025 | The portal never returns a full address, a full phone number, another order of the same customer, an internal note, or a laundry photograph without OTP verification. | FR-090, FR-091 | 7 |
| AC-026 | The portal is served with `noindex`. | FR-092 | 7 |
| AC-027 | No vendor SDK type, payload, or identifier appears in business logic; swapping the messaging provider is an adapter and configuration change. | FR-093 | 7 |
| AC-028 | A message due inside quiet hours of 20.00–08.00 outlet local time is deferred to the next permitted window, and is neither dropped nor sent inside the window. | FR-097 | 7 |
| AC-029 | A queue replay, a retry, and a scheduler restart each produce zero additional copies of an already-sent notification. | FR-098 | 7 |
| AC-030 | A messaging provider outage leaves order state unchanged, and the failure is visible and retried under a bounded policy. | FR-099 | 7 |
| AC-031 | A delivery cannot be completed without proof by at least one mechanism the tenant's policy requires. | FR-104 | 8 |
| AC-032 | A proof photograph or signature is unreachable without authentication, is served only through a signed expiring URL, and never appears on the public portal. | FR-105 | 8 |
| AC-033 | Courier copy and UI describe route order as *usulan rute*; no surface claims an optimal route or a guaranteed arrival time. | FR-103 | 8 |
| AC-034 | A guest job link exposes exactly one job, expires, is revocable, is stored hashed, and grants no access to customer history, other orders, pricing, or any other tenant data; a rider holding links for two tenants cannot traverse between them. | FR-109 | 8 |
| AC-035 | Cash collected at the door is recorded in integer Rupiah, is idempotent under retry, and cannot be deleted. | FR-110 | 8 |
| AC-036 | Courier cash reconciliation compares expected against actual per courier per shift or route, and any variance is recorded and acknowledged rather than absorbed. | FR-111 | 8 |
| AC-037 | A failed delivery is recorded with a reason and returns the order to a defined status. | FR-106 | 8 |
| AC-038 | Aging is computed from the first `READY_FOR_PICKUP` timestamp and does not restart after a `REWORK` cycle. | FR-112 | 9 |
| AC-039 | Each ladder stage — H+1, H+3, H+7, H+14 — fires exactly once per order across retries, queue replays, and scheduler restarts. | FR-113, FR-114 | 9 |
| AC-040 | The H+7 stage creates a real assignable, closable task with a named owner. | FR-115 | 9 |
| AC-041 | The unclaimed-laundry dashboard presents all nine minimum fields, tenant-scoped, with unpaid balance and held invoices read from the authoritative financial records. | FR-116 | 9 |
| AC-042 | No capability, configuration, flag, scheduled job, or backlog item exists that discards, sells, auctions, donates, or transfers ownership of a customer's laundry. | FR-117 | 9 |
| AC-043 | Shift closing presents expected cash against actual cash, computes the variance, and requires a reason beyond the configured threshold. | RPT-007 | 10 |
| AC-044 | A figure that cannot be computed for a period is shown as unavailable, not as zero; an estimated figure is labelled an estimate. | RPT-002, RPT-003 | 10 |
| AC-045 | Every aggregate is drillable to its underlying records for a user with permission, within the same tenant. | RPT-004 | 10 |
| AC-046 | The owner portfolio consolidates across brands and outlets within one tenant, and no query in it spans tenants. | RPT-018 | 10 |
| AC-047 | An exported report carries the same access rules, tenant scoping, and masking as the underlying records. | RPT-019 | 10 |

**Coverage rule.** Every `MUST` requirement inside the MVP is covered by at least one criterion above, or
by a criterion in the `TEN`, `FIN`, `OFF`, `TRK`, `DEL`, `UCL`, `NOT`, `SEC`, or `NFR` series owned by
the other Step 1 documents. Coverage is asserted as a design intent of this document; it has **not** been
mechanically verified, and this document does not claim that it has.

---

## 6. MVP quality bar

The MVP is small in scope but **not lax in quality**
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §22.5).

1. **Tenant isolation is a hard gate from Step 3 onward.** A dedicated, always-run negative test suite
   attempts cross-tenant access and asserts it fails. There is no staging exemption and no "temporary"
   bypass.
2. **Financial integrity is a hard gate from Step 5 onward.** A dedicated suite covers idempotency,
   retry storms, callback replay, refund and void permissions, reversal, and historical price
   immutability.
3. **A failure in either suite is an automatic NO-GO.**
4. **No security control, tenant isolation, or backup is placed behind a paid tier.** The security
   baseline is on every plan including Starter (SUB-018).
5. **There is no "we will secure it after the pilot."**

---

## 7. MVP exit condition

The MVP is complete when Steps 3 through 10 have each independently met the general Definition of Done
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §25.1) with exact-SHA evidence, and the owner has conferred
`GO` on each. `GO` is never self-declared by an agent.

Meeting the MVP exit condition is **not** the same as commercial launch. Launch readiness is assessed at
Step 14 against the Definition of Done and both hard gates.

---

## 8. Status

| Item | Status |
| --- | --- |
| Every MVP capability | **NOT IMPLEMENTED** |
| Every acceptance criterion | Verification **NOT STARTED** |
| Backend runtime | **ABSENT** |
| Flutter workspace | **ABSENT** |
| Deployment | **ABSENT** |
| Application CI | **NOT APPLICABLE** |
| UAT | **NOT STARTED** |
| Steps 3–10 | **PLANNED** |

Nothing in this document claims that any MVP capability has been built, tested, deployed, or piloted.
