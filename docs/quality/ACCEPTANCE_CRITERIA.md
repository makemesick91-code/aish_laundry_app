# Acceptance Criteria — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED. Backend runtime ABSENT. Flutter workspace ABSENT.
Deployment ABSENT. Application CI NOT APPLICABLE. UAT NOT STARTED.
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §4, §9, §10, §11, §12, §13, §14, §16, §21, §25
**Related decisions:** [DEC-0012](../decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md),
[DEC-0006](../decisions/DEC-0006-public-tracking-without-app-installation.md)

---

## 1. Purpose and reading instructions

These are the acceptance criteria the product must eventually satisfy. Each is written to be
**testable and unambiguous**: a competent engineer should be able to read one and know whether an
implementation passes or fails, without asking what was meant.

**None of these has been executed.** There is no application, so there are no unit, widget, integration,
or end-to-end tests, and none may be claimed. Each scenario names the **future roadmap Step** that will
implement and verify it.

**How each scenario is structured:**

- **Context** — the bounded context it belongs to.
- **Requirements** — the `SEC-###` and `NFR-###` identifiers it exercises, from
  [`../security/SECURITY_ACCEPTANCE_CRITERIA.md`](../security/SECURITY_ACCEPTANCE_CRITERIA.md) and
  [`NON_FUNCTIONAL_REQUIREMENTS.md`](NON_FUNCTIONAL_REQUIREMENTS.md). Functional and domain identifiers
  are owned by the product and domain documentation of this Step and are referenced there.
- **Step** — the roadmap Step accountable.
- **Given / When / Then** — the happy path.
- **Negative path** — what must *not* happen. A scenario with no negative path is incomplete.

**All example data below is fictional**, following the placeholder convention in
[`../security/DATA_CLASSIFICATION.md`](../security/DATA_CLASSIFICATION.md) §6. `Tenant Contoh A`,
`Tenant Contoh B`, `Contoh Pelanggan A`, and `ORD-CONTOH-0001` are invented and refer to no real person
or business.

**Canonical vocabulary used throughout.** Order statuses: `DRAFT`, `RECEIVED`, `AWAITING_PROCESS`,
`SORTING`, `WASHING`, `DRYING`, `FINISHING`, `QUALITY_CONTROL`, `REWORK`, `READY_FOR_PICKUP`,
`SCHEDULED_FOR_DELIVERY`, `OUT_FOR_DELIVERY`, `COMPLETED`, `CANCELLED`, `ISSUE`. Pickup and delivery
statuses: `REQUESTED`, `CONFIRMED`, `SCHEDULED`, `ASSIGNED`, `EN_ROUTE`, `ARRIVED`, `PICKED_UP`,
`DELIVERED`, `FAILED`, `RESCHEDULED`, `CANCELLED`.

---

## 2. Identity, tenancy, and authorisation

### AC-001 — Owner with multiple tenants
- **Context:** Identity and Tenancy
- **Requirements:** SEC-005, SEC-006, SEC-007, NFR-020
- **Step:** 3

**Given** an owner holds memberships in `Tenant Contoh A` and `Tenant Contoh B`, and a third tenant
`Tenant Contoh C` exists in which they hold no membership,
**When** they authenticate and open the tenant switcher,
**Then** exactly `Tenant Contoh A` and `Tenant Contoh B` are offered, and selecting one scopes every
subsequent request to that tenant alone.

**Negative path.** The owner must **not** see `Tenant Contoh C` in the switcher, must **not** reach any
record of `Tenant Contoh C` by supplying its identifier in a request, and must **not** carry any data
from `Tenant Contoh A` into the session after switching to `Tenant Contoh B`. A client-supplied tenant
identifier is never authorisation proof. Any cross-tenant read is an **automatic NO-GO**.

### AC-002 — Customer number reused in different tenants
- **Context:** Identity and Tenancy
- **Requirements:** SEC-007, SEC-041, NFR-020
- **Step:** 3, consent behaviour in 7

**Given** the same fictional phone number `+62-8XX-CONTOH-0001` is registered as a customer in both
`Tenant Contoh A` and `Tenant Contoh B`,
**When** each tenant views its own customer record,
**Then** each sees only its own customer, its own order history, and its own consent state, and the two
records remain entirely separate.

**Negative path.** The two records must **not** be merged, de-duplicated, or linked merely because the
phone number, owner name, or email match — identical contact details across tenants are expected.
`Tenant Contoh A` must **not** see any order, note, or consent state belonging to `Tenant Contoh B`. An
opt-out recorded in one tenant must **not** silently apply as consent in the other, nor be reset by an
import in either.

### AC-003 — Cross-tenant order access denied
- **Context:** Identity and Tenancy
- **Requirements:** SEC-004, SEC-005, SEC-007, SEC-024, NFR-020
- **Step:** 3

**Given** a kasir authenticated in `Tenant Contoh A`, and an order `ORD-CONTOH-0001` belonging to
`Tenant Contoh B`,
**When** the kasir requests that order by identifier, by list filter, by search term, by count, by report
endpoint, and by direct file URL,
**Then** every one of those paths returns a response indistinguishable from the record not existing.

**Negative path.** No path may return the record, confirm its existence, differ in error code between
"forbidden" and "not found", or differ materially in response timing. Discovering the record through
**any** access path is an **automatic NO-GO** under DEC-0012.

### AC-004 — Portfolio dashboard authorisation
- **Context:** Finance and Reporting
- **Requirements:** SEC-007, SEC-010, NFR-020
- **Step:** 10

**Given** an owner holding memberships in `Tenant Contoh A` and `Tenant Contoh B` opens the portfolio
dashboard,
**When** consolidated revenue and order figures are produced,
**Then** the figures are the union of results from **individually tenant-scoped queries** over the
memberships actually held, and every aggregate drills down to underlying records within permission and
within the tenant.

**Negative path.** The dashboard must **not** be implemented as one broad query filtered afterwards, must
**not** include any figure from `Tenant Contoh C`, and must **not** widen the query surface for
convenience or performance. Convenience is never sufficient reason to relax isolation.

---

## 3. Pricing, order, and payment

### AC-005 — Immutable price snapshot
- **Context:** Order and Payment
- **Requirements:** SEC-032, NFR-039
- **Step:** 5

**Given** an order was created in `Tenant Contoh A` capturing the price that applied at that moment,
**When** the tenant later edits the master price list,
**Then** the existing order's line prices, total, and reprinted nota all show the **original** captured
values, and only orders created after the edit use the new price.

**Negative path.** The price-list edit must **not** retroactively change any past order's total, any past
invoice, or any reprinted nota. Money is **integer Rupiah**; no floating point appears anywhere in the
pricing, total, discount, or tax path.

### AC-006 — Partial payment
- **Context:** Order and Payment
- **Requirements:** SEC-032, SEC-033, NFR-014
- **Step:** 5

**Given** an order in `Tenant Contoh A` with a total of a fictional integer Rupiah amount and a deposit
recorded at intake,
**When** the customer pays a further partial amount at collection,
**Then** the order's paid total is the exact integer sum of the recorded payments, the outstanding
balance is the exact integer remainder, and both are computed and authoritative **on the server**.

**Negative path.** Rounding must **not** occur at any point other than the single defined rounding point.
No floating-point arithmetic may appear in the calculation. A client-computed total must **not** be
stored — client totals are display only. The order must **not** be marked paid until the recorded
payments equal the total.

### AC-007 — Payment replay
- **Context:** Order and Payment
- **Requirements:** SEC-031, SEC-033, NFR-014
- **Step:** 5

**Given** a payment for `ORD-CONTOH-0001` has been recorded following a verified gateway callback,
**When** the identical callback is delivered a second time, and separately when a forged callback with an
altered amount is delivered,
**Then** the replay is rejected by its gateway reference and the order's payment state is unchanged, and
the forged callback is rejected on signature and amount verification.

**Negative path.** A second payment record must **not** be created. The order must **not** be marked paid
on any client claim. **Zero duplicate payments** is a hard target; any occurrence is an **automatic
NO-GO**.

### AC-008 — Duplicate offline order
- **Context:** Order and Payment (offline)
- **Requirements:** SEC-033, NFR-013, NFR-018, NFR-029
- **Step:** 5

**Given** a kasir on the Ops Android app creates an order while the network is unavailable, and the app
generates a `client_reference` once and persists it with the queued operation,
**When** the submission fails, the kasir retries, the application is killed mid-submit, the device is
rebooted, and the queue finally drains after connectivity returns,
**Then** exactly **one** order exists on the server, and the queue reports the operation as completed.

**Negative path.** The `client_reference` must **not** be regenerated on retry — regenerating it defeats
the entire mechanism and is rejected in review. The queued operation must **not** be lost to the app
kill or the reboot. A second order must **not** exist. **Zero duplicate orders due to retry** is a hard
target; any occurrence is an **automatic NO-GO**.

---

## 4. Production operations

### AC-009 — Order lifecycle
- **Context:** Production Operations
- **Requirements:** SEC-002, SEC-051, NFR-005
- **Step:** 6

**Given** an order in `Tenant Contoh A` at status `RECEIVED`,
**When** staff advance it through `AWAITING_PROCESS`, `SORTING`, `WASHING`, `DRYING`, `FINISHING`,
`QUALITY_CONTROL`, and `READY_FOR_PICKUP`, and finally to `COMPLETED` on collection,
**Then** every transition is validated server-side against the canonical fifteen-status machine,
permissioned, and recorded in the audit trail with actor and timestamp, and the **first** arrival at
`READY_FOR_PICKUP` records the first-ready timestamp exactly once.

**Negative path.** An invalid transition — for example `RECEIVED` directly to `COMPLETED` — must be
**refused**, not merely hidden in the UI. The transition must **not** be accepted from a client that
asserts its own permission. `CANCELLED` and `ISSUE` must be reachable only from the states the machine
permits.

### AC-010 — Quality control rework
- **Context:** Production Operations
- **Requirements:** SEC-051, NFR-005
- **Step:** 6, aging interaction in 9

**Given** an order reached `READY_FOR_PICKUP` on a given date, recording its first-ready timestamp,
**When** quality control returns it to `REWORK` and it subsequently returns to `READY_FOR_PICKUP`,
**Then** the original first-ready timestamp is unchanged, the aging clock continues from it, and the
rework episode is recorded in the status history with actor, timestamp, and reason.

**Negative path.** The first-ready timestamp must **not** be rewritten, and the aging clock must **not**
restart. The field must have no ordinary-UI edit path; any correction is an audited, permissioned
adjustment. A restarted clock is a defect requiring a regression test.

---

## 5. Tracking and notification

### AC-011 — Tracking token expiry
- **Context:** Tracking and Notification
- **Requirements:** SEC-017, SEC-018, SEC-025, SEC-034, NFR-002, NFR-024, NFR-048
- **Step:** 7

**Given** a tracking link was issued for `ORD-CONTOH-0001` with a defined expiry,
**When** the link is opened before expiry and again after expiry,
**Then** before expiry the portal renders order number, brand and outlet identity, service type, current
status and history, estimated completion, amount due, payment state, and available actions — with name
and phone **masked** — and after expiry it renders an honest, recoverable message explaining what
happened and what to do next, in Bahasa Indonesia.

**Negative path.** The portal must **never** show a full address, laundry photographs, internal notes,
other orders belonging to the same customer, or staff identity beyond the operationally necessary. The
page must carry `noindex`. The expired link must **not** render any order content. The portal must
**not** require an application install.

### AC-012 — Tracking token revocation
- **Context:** Tracking and Notification
- **Requirements:** SEC-012, SEC-017, SEC-018, SEC-019
- **Step:** 7

**Given** a customer or an outlet revokes a tracking link that was shared too widely,
**When** the link is opened afterwards,
**Then** access is refused immediately, and the revocation is recorded as a security audit event.

**Negative path.** The revoked token must **not** work from any source or after any cache interval. The
plaintext token must **not** be retrievable from server storage — it is stored **hashed**, and exists in
plaintext only in the link. An order number submitted in place of a token must **not** grant access.
Bulk token guessing must be rate-limited and must not succeed.

### AC-013 — H+1, H+3, H+7 reminder ladder
- **Context:** Unclaimed Laundry and Recovery
- **Requirements:** SEC-042, SEC-043, SEC-044, SEC-045
- **Step:** 9, messaging in 7

**Given** an order first reached `READY_FOR_PICKUP` and remains uncollected,
**When** the aging reaches H+1, then H+3, then H+7,
**Then** a friendly reminder is sent at H+1, a second reminder at H+3, and at H+7 a priority reminder
**plus a real, assignable, closable follow-up task with an owner** — each stage firing exactly once, each
respecting **quiet hours 20.00–08.00 outlet local time** by deferral to the next permitted window, and
each respecting customer opt-out.

**Negative path.** No stage may fire twice, including across retries, queue replays, and scheduler
restarts. No message may be sent inside quiet hours; a drained backlog must **defer**, not dump. No
message may reach an opted-out customer. The H+7 follow-up must **not** be merely a flag on a report. A
failed send must be retried and made visible, must never be silently dropped, and must **never** alter
the order's state.

### AC-014 — Opt-out honoured
- **Context:** Tracking and Notification
- **Requirements:** SEC-041, SEC-042
- **Step:** 7

**Given** `Contoh Pelanggan A` has opted out of marketing in `Tenant Contoh A`,
**When** a marketing campaign is built before the opt-out and sent after it, and separately when the
tenant re-imports its customer list,
**Then** the customer receives no marketing message in either case, because opt-out is evaluated **at
send time** and survives the import.

**Negative path.** A marketing message must **not** be delivered through a transactional template or
path to evade the opt-out. The opt-out must **not** be reset by the import, must **not** be limited to
one outlet of the tenant, and must **not** be evaluated only at campaign-build time. The opt-out in
`Tenant Contoh A` must **not** propagate to `Tenant Contoh B` as either consent or refusal — consent is
per customer per tenant.

### AC-015 — Provider notification failure
- **Context:** Tracking and Notification
- **Requirements:** SEC-045, NFR-015, NFR-035
- **Step:** 7

**Given** the WhatsApp provider is unavailable,
**When** a status-change notification and an H+3 reminder are due,
**Then** the order lifecycle proceeds unchanged, the sends are retried under a bounded policy, the
failures are visible to the tenant, and the manual deep-link fallback is offered to staff explicitly as
a fallback.

**Negative path.** The order must **not** be cancelled, blocked, or failed because messaging failed —
messaging is a side effect. The failure must **not** be silently discarded, must **not** be retried
forever, and must **not** produce a duplicate once the provider recovers. The fallback must **not** be
presented or described as automation.

---

## 6. Pickup and delivery

### AC-016 — External courier guest access
- **Context:** Pickup and Delivery
- **Requirements:** SEC-018, SEC-039, SEC-040
- **Step:** 8

**Given** an external ojek is assigned one delivery job in `Tenant Contoh A` and issued a guest link,
**When** the courier opens the link within the job window,
**Then** they see only that assignment — recipient name, a contactable number, the minimum address detail
the delivery genuinely requires, and the actions needed to complete it.

**Negative path.** The link must **not** expose customer history, other orders, pricing, or any other
tenant data. Altering an identifier within the guest session must yield nothing, because scope is
re-derived from the token and never from a request parameter. The same courier's link for
`Tenant Contoh B` must be unrelated and non-traversable. The link must expire, must be revocable with
immediate effect, must be stored hashed, and must not be the order number or derivable from it.

### AC-017 — Proof of delivery
- **Context:** Pickup and Delivery
- **Requirements:** SEC-028, SEC-037, SEC-038, NFR-028
- **Step:** 8

**Given** a delivery job at status `OUT_FOR_DELIVERY` with the tenant's configured proof method,
**When** the courier completes the handover and captures proof — OTP, photo, signature, or recipient name
per tenant policy — and cash is collected at the door,
**Then** the job moves to `DELIVERED`, the proof artefact is stored privately with a tenant-scoped
unguessable key, the cash is recorded as an integer Rupiah financial transaction against the courier and
the shift, and the proof is retrievable only through a signed expiring URL.

**Negative path.** The transition to `DELIVERED` must be **refused** without proof — no custody transfer
is recorded silently. The proof artefact must **not** be reachable without authentication, must **not**
appear on the public tracking portal, and must **not** be served from a public or listable bucket; any
such exposure is an **automatic NO-GO**. No claim of route optimisation, guaranteed arrival time, or ETA
accuracy may appear in the courier or customer interface.

### AC-018 — Failed delivery
- **Context:** Pickup and Delivery
- **Requirements:** SEC-038, SEC-039, NFR-016
- **Step:** 8

**Given** a courier arrives at status `ARRIVED` and nobody is available to receive the laundry,
**When** the courier records the outcome,
**Then** the job moves to `FAILED` with a recorded reason, the laundry's custody remains with the courier
until an explicit return is recorded, any collected cash position is unchanged, and the job can be moved
to `RESCHEDULED` with a new window.

**Negative path.** `FAILED` must **not** be harder to record than `DELIVERED` — if the honest path costs
more effort, the dishonest one will be taken. The order must **not** be marked `COMPLETED`. Cash must
**not** be recorded as collected. The reason must **not** be optional. The customer notification for the
failure must respect quiet hours and opt-out.

---

## 7. Unclaimed laundry and recovery

### AC-019 — Overdue laundry escalation
- **Context:** Unclaimed Laundry and Recovery
- **Requirements:** SEC-043, SEC-044, NFR-035
- **Step:** 9

**Given** an order whose first `READY_FOR_PICKUP` timestamp is fourteen days past and which remains
uncollected,
**When** the H+14 stage evaluates,
**Then** the escalation reaches the outlet manager or the owner — a human accountable for the outcome —
it surfaces in the manager and owner dashboards, and the unclaimed-laundry dashboard shows all nine
minimum fields: order count, customer count, held invoices, unpaid balance, order age, outlet, last
reminder, follow-up officer, and reason not collected.

**Negative path.** The dashboard must **not** omit any of the nine fields. Unpaid balance and held
invoices must **not** be recomputed independently — they read from the authoritative financial records
as integer Rupiah. All figures are tenant-scoped.

**Absolute prohibition, restated.** The system must **never** automatically discard, sell, auction,
donate, transfer ownership of, or otherwise dispose of a customer's laundry — regardless of age, unpaid
balance, or tenant request. No such behaviour may be implemented, scheduled, prototyped, hidden behind a
flag, or left as a TODO. The product's role ends at reminding, escalating, and reporting. Any request to
build it is refused outright and escalated to the repository owner.

---

## 8. Subscription and platform

### AC-020 — Subscription entitlement
- **Context:** Subscription and Platform Administration
- **Requirements:** SEC-046, NFR-040, NFR-042, NFR-049
- **Step:** 12

**Given** `Tenant Contoh A` is on the Starter plan — 1 outlet, 5 staff, up to 1.000 order per month
fair-use,
**When** the tenant attempts to create a second outlet through the API directly rather than the UI, and
separately when its monthly order count approaches the fair-use figure mid-shift,
**Then** the outlet creation is refused **server-side** with an honest explanation and an upgrade path,
and the fair-use figure is presented as fair-use rather than as a hard cutoff.

**Negative path.** Limits must **not** be enforced only in the client. Fair-use handling must **not**
stop a laundry operating mid-shift. Security controls, tenant isolation, and backup must **not** be
placed behind any tier — they are baseline on every plan including Starter. When the subscription
lapses, tenant data must **remain exportable per policy**; blocking export is rejected. Pricing shown
anywhere must match the Master Source character for character, read from a single canonical
configuration.

---

## 9. Cross-cutting scenarios

### AC-021 — Support impersonation is never silent
- **Context:** Subscription and Platform Administration
- **Requirements:** SEC-010, SEC-051, SEC-058
- **Step:** 12

**Given** platform support needs to view a tenant's configuration to resolve a ticket,
**When** an impersonation session is started and later ends,
**Then** the session required an explicit reason, is time-bound, ends automatically, and writes
immutable start and end audit records naming actor, tenant, duration, and reason — visible to the
tenant.

**Negative path.** Access must **not** be possible without an impersonation session. The audit record
must **not** be suppressible by the impersonator. Platform administration must **not** be implemented by
relaxing tenant scoping for ordinary roles, and no scope-bypass parameter may exist. Silent or unaudited
platform access is an **automatic NO-GO**.

### AC-022 — Offline tenant switch leaks nothing
- **Context:** Identity and Tenancy (offline)
- **Requirements:** SEC-007, NFR-031
- **Step:** 5

**Given** a staff member holds memberships in `Tenant Contoh A` and `Tenant Contoh B` on one Ops device,
with cached orders and a pending financial queue in `Tenant Contoh A`,
**When** they switch to `Tenant Contoh B` and browse, search, and then return online so the queue drains,
**Then** no record from `Tenant Contoh A` is visible while in `Tenant Contoh B`, and every queued
operation syncs into `Tenant Contoh A` where it originated, carrying its explicit tenant context.

**Negative path.** Cached data must **not** survive the switch in a readable form. A queued operation
must **not** be re-attributed to the tenant that happens to be active at drain time. Local data is
separated per tenant **and** per user. Any leak is treated as a tenant-isolation defect and is an
**automatic NO-GO**.

---

## 10. Coverage summary

| ID | Scenario | Context | Step |
| --- | --- | --- | --- |
| AC-001 | Owner with multiple tenants | Identity and Tenancy | 3 |
| AC-002 | Customer number reused in different tenants | Identity and Tenancy | 3 / 7 |
| AC-003 | Cross-tenant order access denied | Identity and Tenancy | 3 |
| AC-004 | Portfolio dashboard authorisation | Finance and Reporting | 10 |
| AC-005 | Immutable price snapshot | Order and Payment | 5 |
| AC-006 | Partial payment | Order and Payment | 5 |
| AC-007 | Payment replay | Order and Payment | 5 |
| AC-008 | Duplicate offline order | Order and Payment (offline) | 5 |
| AC-009 | Order lifecycle | Production Operations | 6 |
| AC-010 | Quality control rework | Production Operations | 6 / 9 |
| AC-011 | Tracking token expiry | Tracking and Notification | 7 |
| AC-012 | Tracking token revocation | Tracking and Notification | 7 |
| AC-013 | H+1, H+3, H+7 reminder ladder | Unclaimed Laundry and Recovery | 9 / 7 |
| AC-014 | Opt-out honoured | Tracking and Notification | 7 |
| AC-015 | Provider notification failure | Tracking and Notification | 7 |
| AC-016 | External courier guest access | Pickup and Delivery | 8 |
| AC-017 | Proof of delivery | Pickup and Delivery | 8 |
| AC-018 | Failed delivery | Pickup and Delivery | 8 |
| AC-019 | Overdue laundry escalation | Unclaimed Laundry and Recovery | 9 |
| AC-020 | Subscription entitlement | Subscription and Platform | 12 |
| AC-021 | Support impersonation is never silent | Subscription and Platform | 12 |
| AC-022 | Offline tenant switch leaks nothing | Identity and Tenancy (offline) | 5 |

**Total: 22 scenarios.** Every one carries both a happy path and a negative path. Tenant boundary,
financial integrity, and offline behaviour are named explicitly wherever they apply.

---

## 11. Related documents

- [`NON_FUNCTIONAL_REQUIREMENTS.md`](NON_FUNCTIONAL_REQUIREMENTS.md)
- [`STEP_01_DEFINITION_OF_DONE.md`](STEP_01_DEFINITION_OF_DONE.md)
- [`../security/SECURITY_ACCEPTANCE_CRITERIA.md`](../security/SECURITY_ACCEPTANCE_CRITERIA.md)
- [`../security/ABUSE_CASES.md`](../security/ABUSE_CASES.md)
- [`../security/INITIAL_THREAT_MODEL.md`](../security/INITIAL_THREAT_MODEL.md)
- [`../security/TRUST_BOUNDARIES.md`](../security/TRUST_BOUNDARIES.md)

Functional and domain detail authored elsewhere in Step 1: `docs/product/FUNCTIONAL_REQUIREMENTS.md`,
`docs/domain/ORDER_DOMAIN.md`, `docs/state-machines/ORDER_STATUS_MACHINE.md`.
