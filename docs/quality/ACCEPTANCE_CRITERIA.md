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
- **Requirements:** SEC-005, SEC-006, SEC-007, NFR-020, FR-011, FR-012, FR-013, FR-014, FR-015, FR-016, FR-018, TEN-001, TEN-004, TEN-005, TEN-007, TEN-008, TEN-009
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
- **Requirements:** SEC-007, SEC-041, NFR-020, FR-019, FR-021, FR-022, FR-023, FR-029, TEN-011, TEN-012, TEN-013
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
- **Requirements:** SEC-004, SEC-005, SEC-007, SEC-024, NFR-020, FR-007, FR-017, FR-020, FR-057, TEN-015, TEN-024, TEN-025, TEN-030
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
- **Requirements:** SEC-007, SEC-010, NFR-020, RPT-004, RPT-018
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
- **Requirements:** SEC-032, NFR-039, FR-034, FR-035, FR-036, FR-040, FIN-010, FIN-011, FIN-012, FIN-017, FIN-033, FIN-036
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
- **Requirements:** SEC-032, SEC-033, NFR-014, FR-037, FR-038, FR-051, FR-056, FR-061, FR-070, FIN-001, FIN-002, FIN-009, FIN-013, FIN-014, FIN-015, FIN-022, FIN-034, FIN-035, FIN-037
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
- **Requirements:** SEC-031, SEC-033, NFR-014, FR-062, FR-063, FR-064, FR-068, FIN-003, FIN-004, FIN-005, FIN-016, FIN-039, FIN-040
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
- **Requirements:** SEC-033, NFR-013, NFR-018, NFR-029, FR-048, FR-053, FR-059, FIN-019, OFF-001, OFF-002, OFF-007, OFF-017, OFF-018, OFF-019, OFF-025
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
- **Requirements:** SEC-002, SEC-051, NFR-005, FR-060, FR-071, FR-072, FR-073, FR-076, FR-080
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
- **Requirements:** SEC-051, NFR-005, FR-077, FR-081, FR-082, FR-084, FR-085, UCL-001, UCL-002, UCL-017
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
- **Requirements:** SEC-017, SEC-018, SEC-025, SEC-034, NFR-002, NFR-024, NFR-048, FR-086, FR-088, FR-089, FR-090, FR-091, FR-119, OFF-023, TRK-005, TRK-008, TRK-009, TRK-010, TRK-011, TRK-012, TRK-013, TRK-014, TRK-015, TRK-016, TRK-017, TRK-018, TRK-025, TRK-026, TRK-027, TRK-028
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
- **Requirements:** SEC-012, SEC-017, SEC-018, SEC-019, FR-087, FR-092, TRK-001, TRK-002, TRK-003, TRK-004, TRK-006, TRK-007, TRK-019, TRK-020, TRK-021, TRK-022, TRK-023, TRK-024, TRK-030
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
- **Requirements:** SEC-042, SEC-043, SEC-044, SEC-045, FR-097, FR-098, FR-112, FR-113, FR-114, FR-115, NOT-003, NOT-004, NOT-021, NOT-022, UCL-003, UCL-004, UCL-005, UCL-006, UCL-007, UCL-009, UCL-016, UCL-021, UCL-023
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
- **Requirements:** SEC-041, SEC-042, FR-027, FR-028, FR-096, NOT-005, NOT-006, NOT-011, NOT-013, NOT-023, NOT-024
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
- **Requirements:** SEC-045, NFR-015, NFR-035, FR-093, FR-094, FR-095, FR-099, DEL-016, DEL-035, NOT-001, NOT-007, NOT-009, NOT-010, NOT-017, NOT-018, NOT-026, NOT-027, NOT-029
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
- **Requirements:** SEC-018, SEC-039, SEC-040, FR-108, FR-109, DEL-006, DEL-007, DEL-008, DEL-009, DEL-020, DEL-024, DEL-033
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
- **Requirements:** SEC-028, SEC-037, SEC-038, NFR-028, FR-103, FR-104, FR-105, FR-110, DEL-002, DEL-011, DEL-012, DEL-013, DEL-014, DEL-018, DEL-019, DEL-021, DEL-027, DEL-028, DEL-029, DEL-034, OFF-022
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
- **Requirements:** SEC-038, SEC-039, NFR-016, FR-106, FR-107, DEL-001, DEL-003, DEL-022, DEL-023, DEL-031
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
- **Requirements:** SEC-043, SEC-044, NFR-035, FR-116, FR-117, RPT-011, RPT-012, RPT-013, RPT-014, DEL-025, FIN-023, NOT-025, UCL-008, UCL-010, UCL-011, UCL-012, UCL-013, UCL-014, UCL-015, UCL-018, UCL-019, UCL-020, UCL-022, UCL-024, UCL-025, UCL-026, UCL-027, UCL-028, UCL-029, UCL-030
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
- **Requirements:** SEC-046, NFR-040, NFR-042, NFR-049, SUB-002, SUB-003, SUB-004, SUB-005, SUB-006, SUB-010, SUB-014, TEN-002, TEN-019, TEN-028
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
- **Requirements:** SEC-010, SEC-051, SEC-058, SUB-016, SUB-017, TEN-029
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
- **Requirements:** SEC-007, NFR-031, FR-017, FR-059, FR-079, OFF-006, OFF-016, OFF-020
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

### AC-023 — Authentication, session, and device lifecycle
- **Context:** Identity and Tenancy
- **Requirements:** SEC-002, SEC-011, SEC-013, SEC-014, SEC-015, SEC-020, SEC-022, SEC-051, FR-001, FR-002, FR-003, FR-004, FR-005, FR-006, FR-008, FR-009, FR-010, OFF-014, TEN-006, TEN-014
- **Step:** 3

**Given** a person authenticating with a phone number and a one-time password delivered to that number,
holding exactly one user account,
**When** they authenticate on two devices, an administrator later revokes one device, and separately
revokes a session,
**Then** a session is established on each device with permissions derived from Membership and defaulting
to least privilege; the revoked device stops working immediately while the other continues without
re-authenticating; the revoked session stops working immediately; and every authentication, permission
grant, and revocation is recorded in the audit trail.

**Negative path.** OTP issuance must be rate-limited and the code single-use and short-expiry, with the
attempt counter bound to the OTP record so re-issuance does not reset it. A brute-force attempt must
trigger progressive delay and lockout. A second user account must **not** be created for the same
person. No permission may be granted that the granting actor does not already hold. Passwords, OTPs, and
tokens must **not** appear in any log at any level.

### AC-024 — Address and phone masking by context
- **Context:** Customer Management
- **Requirements:** SEC-034, SEC-035, SEC-036, FR-024, FR-025, FR-026
- **Step:** 4, portal enforcement in 7

**Given** `Contoh Pelanggan A` in `Tenant Contoh A` has a stored address `Alamat Contoh 1, Kota Contoh`
and phone `+62-8XX-CONTOH-0001`,
**When** the record is rendered to the customer themselves, to a kasir preparing a delivery, to a
production operator, to an assigned courier, and to a public portal visitor,
**Then** each context receives the masking level defined for it, applied **server-side** in the response
itself.

**Negative path.** The public portal must **never** receive a full address in any response, under any
role, configuration, or tenant setting. A production operator must **not** receive the address at all.
A courier must receive only the minimum detail the assigned job requires, never in a shareable or
indexable form. A client must **not** receive a full value that it then hides — a value delivered is a
value leaked.

### AC-025 — Service catalogue and price override control
- **Context:** Catalogue and Pricing
- **Requirements:** SEC-032, NFR-039, FR-031, FR-039
- **Step:** 4

**Given** `Tenant Contoh A` has defined service types on a per-brand price list,
**When** a kasir without override permission attempts a price override at intake, and a manager with
override permission performs one,
**Then** the kasir's attempt is refused server-side, and the manager's override is applied and recorded
with actor, timestamp, original amount, new amount, and reason.

**Negative path.** The override must **not** be applied because the client offered it. An override must
**not** be recordable without a reason. The override must **not** alter the master price list, and must
**not** propagate to any other order.

### AC-026 — Outlet master data governing operations
- **Context:** Catalogue and Master Data
- **Requirements:** SEC-038, SEC-043, FR-041, FR-043, FR-044, FR-046, FR-047, TEN-010
- **Step:** 4, consumed in 7 and 8

**Given** `Tenant Contoh A` has configured operating hours, service zones, shift definitions, the proof
policy for custody transfers, and quiet hours for an outlet,
**When** a pickup is requested outside the zone, a delivery is completed under the configured proof
policy, a shift is closed, and a non-urgent message falls due inside the quiet window,
**Then** the out-of-zone request is refused or flagged per the zone definition, the proof method demanded
matches the configured policy, the shift close uses the configured shift boundary, and the message is
deferred to the next permitted window.

**Negative path.** Quiet hours must default to **20.00–08.00 outlet local time** where not configured
otherwise, and a configured window must **not** permit sending inside it. The proof policy must
**not** be configurable to "no proof" — some recorded proof is always required. Zone and hour
configuration is tenant-scoped and must **not** be visible to or settable by another tenant.

### AC-027 — Counter order intake
- **Context:** Order and Payment
- **Requirements:** SEC-002, NFR-004, NFR-005, FR-049, FR-050, FR-052, FR-055, FR-058, FIN-018
- **Step:** 5

**Given** a kasir at a busy counter in `Tenant Contoh A`,
**When** they create an order with several lines, record a special handling instruction, generate the
nota, and later cancel a different order that has not yet entered production,
**Then** the intake follows the shortest primary path, the lines and instruction are stored against the
order, the nota reflects the server-computed integer Rupiah figures, and the cancellation is permissioned
and recorded with a reason.

**Negative path.** An order that has entered production must **not** be cancellable through the ordinary
path. The order list must **not** load an unbounded result set. The nota must **not** be generated from
client-computed totals. A special handling instruction must **not** be silently dropped when the order is
edited.

### AC-028 — Refund, void, and correction by reversal
- **Context:** Order and Payment
- **Requirements:** SEC-032, SEC-050, FR-065, FR-066, FR-067, FR-069, FIN-006, FIN-007, FIN-008, FIN-020, FIN-021, FIN-031, FIN-032, FIN-038
- **Step:** 5

**Given** a completed payment recorded against `ORD-CONTOH-0001`,
**When** a kasir without permission attempts a refund, a manager with permission issues one with a
recorded reason, and separately someone attempts to delete the original payment record,
**Then** the unpermitted attempt is refused, the permitted refund is recorded as a reversal entry
preserving the original payment with actor, tenant, outlet, timestamp, amounts before and after, and
reason, and the deletion attempt is refused because no such path exists.

**Negative path.** No delete path for a financial record may exist in ordinary UI for any regular role.
A refund must **not** exceed what was actually received against that order. The original record must
**not** be modified in place. The financial audit trail must be append-only and must **not** be subject
to log rotation.

### AC-029 — Batch production, item flags, and the ISSUE path
- **Context:** Production Operations
- **Requirements:** SEC-051, NFR-046, FR-074, FR-075, FR-078, FR-079, FR-080, OFF-015
- **Step:** 6

**Given** several orders in `Tenant Contoh A` are processed as a batch through `WASHING` and `DRYING`,
with one item flagged as damaged on arrival,
**When** an operator records stage progress for the batch while offline, a device clock is skewed, and
one order is moved to `ISSUE`,
**Then** progress is recorded against every order in the batch, the item-level flag persists
independently of the order status, the offline records sync without duplication, ordering and reporting
use **server** timestamps, and the `ISSUE` order is surfaced for resolution.

**Negative path.** A skewed device clock must **not** determine the recorded sequence. A batch operation
must **not** advance an order whose state does not permit the transition. An `ISSUE` order must **not**
silently continue through the production flow or reach `COMPLETED` without resolution. Offline stage
records must **not** produce duplicate history entries on replay.

### AC-030 — Pickup request, scheduling, and window adherence
- **Context:** Pickup and Delivery
- **Requirements:** SEC-039, FR-100, FR-101, FR-102, RPT-015, DEL-004, DEL-005, DEL-010, DEL-015, DEL-017, DEL-026, DEL-032
- **Step:** 8, reporting in 10

**Given** a customer in `Tenant Contoh A` raises a pickup request for an address inside a defined service
zone,
**When** the request moves through `REQUESTED`, `CONFIRMED`, `SCHEDULED` with a time window, and
`ASSIGNED`, and the courier subsequently arrives inside or outside the window,
**Then** the zone match is validated, the window is stored against the job, and adherence to the window
is measured and reported.

**Negative path.** A request for an address outside every defined zone must **not** be silently accepted
as schedulable. The time-window adherence report must **not** be presented as a delivery guarantee, and
no interface may claim route optimisation, guaranteed arrival time, or ETA accuracy the system does not
provide. A window missed must be reported honestly rather than recomputed to appear met.

### AC-031 — Courier cash reconciliation and shift close
- **Context:** Finance and Reporting
- **Requirements:** SEC-032, SEC-064, FR-111, RPT-007, RPT-008, DEL-030, FIN-024, FIN-025, FIN-026, FIN-027, FIN-028, FIN-029
- **Step:** 8, reporting in 10

**Given** a courier collected cash across several deliveries during one shift in `Tenant Contoh A`,
**When** the shift is closed and the courier hands over an amount that does not match the recorded total,
**Then** the shift-closing report compares expected against actual, the courier cash report attributes
the collection and handover per courier per shift, and the variance is computed, recorded, and requires
explicit acknowledgement.

**Negative path.** The variance must **not** be auto-rounded, absorbed, written off silently, or omitted
from the report. The shift must **not** close with an unacknowledged variance. Expected figures must be
read from the authoritative financial records rather than recomputed independently.

### AC-032 — Reporting integrity and drill-down
- **Context:** Finance and Reporting
- **Requirements:** SEC-007, SEC-046, NFR-005, RPT-001, RPT-002, RPT-003, RPT-004, RPT-005, RPT-006, RPT-009, RPT-010, RPT-016, RPT-019
- **Step:** 10

**Given** revenue, order volume, receivables, payment channel reconciliation, and rework rate reports for
`Tenant Contoh A`,
**When** a permitted user opens each report, drills into an aggregate, exports one, and opens a report for
a period whose underlying data is unavailable,
**Then** every figure reads from the single authoritative source, any estimated figure is labelled as an
estimate, each aggregate drills down to its underlying records within permission and within the tenant,
the export carries the same access rules as those records, and the unavailable period is shown as
unavailable.

**Negative path.** An unavailable figure must **not** be rendered as zero — absence and zero are
different facts and conflating them misleads an owner reconciling by hand. No report may recompute money
independently of the financial records. No drill-down may reach a record the user lacks permission for or
that belongs to another tenant. An export must **not** be fetchable by an unsigned URL or a guessable
key.

### AC-033 — Messaging cost transparency
- **Context:** Subscription and Platform Administration
- **Requirements:** SEC-045, NFR-042, RPT-020, SUB-019, SUB-020, NOT-008, NOT-020, NOT-030
- **Step:** 12, messaging in 7

**Given** `Tenant Contoh A` sends transactional and marketing messages through the official provider,
**When** the tenant opens the messaging cost report and the subscription invoice,
**Then** provider message volume and cost are shown to the tenant, and provider fees appear **separately**
from the subscription plan amount.

**Negative path.** Provider costs must **not** be buried inside the plan amount. No interface,
documentation, pricing page, or marketing copy may claim **"unlimited WhatsApp"** or any equivalent —
message volume has a real per-message cost, and claiming otherwise is a false claim and a pricing
guardrail breach. A cost spike from an OTP or notification flood must be visible to the tenant rather
than silently absorbed.

### AC-034 — Subscription lifecycle at the tenant boundary
- **Context:** Subscription and Platform Administration
- **Requirements:** SEC-046, NFR-042, NFR-049, SUB-001, SUB-007, SUB-008, SUB-009, SUB-011, SUB-012, SUB-013, SUB-015, SUB-018, FIN-030, TEN-003, TEN-017, TEN-018, TEN-020, TEN-021
- **Step:** 12

**Given** `Tenant Contoh A` holds a subscription at the tenant boundary with metered usage,
**When** it upgrades, later downgrades, and later still lapses into grace and beyond,
**Then** every subscription amount is integer Rupiah following every financial rule, usage is metered per
tenant, upgrade and downgrade apply per the defined behaviour, and lapse and grace behave as documented
while tenant data **remains exportable per policy**.

**Negative path.** No lifetime cloud plan may exist, and no per-nota fee may be applied on a normal plan.
Subscription and billing must **not** operate per user or per outlet — the tenant is the billing
boundary. The security baseline, tenant isolation, and backup must **not** be placed behind any tier;
they are available on every plan including Starter. A lapsed subscription must **not** block export of the
tenant's own business records. Floating point must **not** appear in any billing calculation.

### AC-035 — Tenant scoping of derived stores, artefacts, and audit
- **Context:** Identity and Tenancy
- **Requirements:** SEC-008, SEC-009, SEC-029, SEC-050, TEN-016, TEN-022, TEN-023, TEN-026, TEN-027
- **Step:** 3, then every Step that adds a derived store

**Given** `Tenant Contoh A` and `Tenant Contoh B` both hold orders, cached reads, queued jobs, search
index entries, generated export files, uploaded proof artefacts, and audit entries,
**When** a request in `Tenant Contoh B` is served from cache, a background job runs outside any request
context, a search is executed, an export file is generated, and an audit entry is written,
**Then** every cache key, queue message, index entry, export file, and object key carries a tenant
dimension; the background job carries explicit tenant context; artefacts are tenant-scoped and private;
and the audit entry carries tenant context and is append-only.

**Negative path.** A cache populated by `Tenant Contoh A` must **never** be readable by
`Tenant Contoh B` — a tenant-less cache key is a cross-tenant leak waiting to happen. A background job
must **not** infer its tenant from "the last request". An object key must **not** be sequential or
otherwise predictable, because that is an enumeration vulnerability. An audit entry must **not** be
editable or deletable after it is written. Any leak through a derived store is an **automatic NO-GO**
under DEC-0012, exactly as a leak through a primary read would be.

### AC-036 — Offline queue integrity, ordering, and conflict resolution
- **Context:** Order and Payment (offline)
- **Requirements:** SEC-033, NFR-016, NFR-019, NFR-030, NFR-032, OFF-003, OFF-004, OFF-005, OFF-008, OFF-009, OFF-010, OFF-011, OFF-012, OFF-013, OFF-021, OFF-024
- **Step:** 5

**Given** an Ops device in `Tenant Contoh A` holding a queue containing a created order, a payment that
depends on it, and a failed non-financial update,
**When** connectivity returns intermittently, the predecessor operation fails, the server holds a
different payment value from the device, the user performs a cache wipe and a logout, and a manager
later purges one queued financial operation deliberately,
**Then** retries back off exponentially within bounded limits; the dependent payment does not jump ahead
of its failed predecessor; the payment conflict surfaces both values to a human; the resolution records
actor, timestamp, chosen value, and reason; the queue survives the cache wipe and the logout; the
deliberate purge requires an explicit permissioned action and is audited; and pending, failed, and
attention states are visible on screen throughout.

**Negative path.** A payment conflict must **never** be resolved silently or by the client picking a
winner — conflicts affecting money escalate to a human, and a non-financial last-write rule is
permissible only where that rule is written down. A failed operation must **not** be silently dropped.
A financial operation must **not** be pruned from the queue on a timer or before confirmed server
acceptance. A kasir must **never** be shown a payment as recorded while it still sits unsent in the
queue. On divergence the server prevails and the client reconciles to it.

### AC-037 — Notification content safety and deduplication
- **Context:** Tracking and Notification
- **Requirements:** SEC-044, SEC-045, SEC-047, NOT-002, NOT-012, NOT-014, NOT-015, NOT-016, NOT-019, NOT-028, TRK-029
- **Step:** 7

**Given** a customer in `Tenant Contoh A` due to receive an order-ready message containing a tracking
link,
**When** the message is composed and sent, the send is retried after a transport error, the scheduler
restarts mid-batch, and the queue is replayed,
**Then** the message body is written in Bahasa Indonesia, contains no full address and no OTP value
alongside the tracking link, and exactly one message reaches the customer — deduplicated on recipient,
event, order, and intended send window — with the send recorded against tenant, outlet, order,
recipient, template, category, status, timestamp, and provider reference.

**Negative path.** A message must **never** echo an OTP value alongside a tracking link, because that
single message would be sufficient for account takeover on its own. Message logs must **not** contain
token plaintext, credentials, or OTP values. A retry, scheduler restart, or queue replay must **not**
produce a second identical message; a duplicate reaching a customer is treated as a defect of the same
class as a duplicate payment — investigate the deduplication key, fix it, and add a regression test.

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
| AC-023 | Authentication, session, and device lifecycle | Identity and Tenancy | 3 |
| AC-024 | Address and phone masking by context | Customer Management | 4 / 7 |
| AC-025 | Service catalogue and price override control | Catalogue and Pricing | 4 |
| AC-026 | Outlet master data governing operations | Catalogue and Master Data | 4 / 7 / 8 |
| AC-027 | Counter order intake | Order and Payment | 5 |
| AC-028 | Refund, void, and correction by reversal | Order and Payment | 5 |
| AC-029 | Batch production, item flags, and the ISSUE path | Production Operations | 6 |
| AC-030 | Pickup request, scheduling, and window adherence | Pickup and Delivery | 8 / 10 |
| AC-031 | Courier cash reconciliation and shift close | Finance and Reporting | 8 / 10 |
| AC-032 | Reporting integrity and drill-down | Finance and Reporting | 10 |
| AC-033 | Messaging cost transparency | Subscription and Platform | 12 / 7 |
| AC-034 | Subscription lifecycle at the tenant boundary | Subscription and Platform | 12 |
| AC-035 | Tenant scoping of derived stores, artefacts, and audit | Identity and Tenancy | 3 |
| AC-036 | Offline queue integrity, ordering, and conflict resolution | Order and Payment (offline) | 5 |
| AC-037 | Notification content safety and deduplication | Tracking and Notification | 7 |

**Total: 37 scenarios.** Every one carries both a happy path and a negative path. Tenant boundary,
financial integrity, and offline behaviour are named explicitly wherever they apply.

### 10.1 Requirement coverage

**Product series.** Every **MUST**-priority requirement defined in
`docs/product/PRODUCT_REQUIREMENTS.md` — all 150 of `FR`, `RPT`, and `SUB` — is cited by at least one
scenario above.

**Domain series.** Every one of the 220 domain requirements defined in `docs/domain/DOMAIN_INVARIANTS.md`
— `TEN-001`…`TEN-030`, `FIN-001`…`FIN-040`, `OFF-001`…`OFF-025`, `TRK-001`…`TRK-030`,
`DEL-001`…`DEL-035`, `UCL-001`…`UCL-030`, `NOT-001`…`NOT-030` — is cited by at least one scenario above.

The domain series carries no `SHOULD` or `COULD` tier. Its severities are `GATE` (103), `CRITICAL` (79),
and `REQUIRED` (38), and **all three are mandatory** — an invariant is not an aspiration. All 220 are
therefore treated as MUST-priority and covered, with none left out on priority grounds.

Several closely-related requirements are grouped under one scenario and cited together on its
**Requirements** line; that is deliberate coverage of a single coherent behaviour, not one scenario per
identifier. The hard-gate and differentiator areas keep dedicated scenarios rather than being folded
into grouped ones: tenant isolation (AC-001, AC-003, AC-035), the same phone number producing two
unrelated profiles (AC-002), integer-Rupiah money and price-snapshot immutability (AC-005, AC-006),
payment idempotency (AC-007), reversal-only correction (AC-028), offline `client_reference` reuse
(AC-008, AC-036), tracking token properties (AC-011, AC-012), proof of custody transfer (AC-017),
courier cash reconciliation (AC-031), aging anchored to the first `READY_FOR_PICKUP` (AC-010) with the
H+1/H+3/H+7/H+14 ladder (AC-013, AC-019), and quiet hours, deduplication, opt-out, and provider failure
never changing order state (AC-013, AC-014, AC-015, AC-037).

**Deliberately uncovered.** Product-series requirements at `SHOULD` and `COULD` priority — FR-030,
FR-032, FR-033, FR-042, FR-045, FR-054, FR-083, FR-118, FR-120, and RPT-017 — are not covered here. They
are not subject to the must-have-a-criterion rule, and writing criteria for behaviour that may not ship
would assert more certainty than the roadmap supports. Nothing in the domain series is uncovered, because
nothing in it is optional.

No identifier is cited that is not defined in `docs/product/PRODUCT_REQUIREMENTS.md` or
`docs/domain/DOMAIN_INVARIANTS.md`.

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
