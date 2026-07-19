# Aish Laundry App — Roadmap

Canonical source: [`MASTER_SOURCE.md`](MASTER_SOURCE.md) §24
Current status: [`STATUS.md`](STATUS.md) · Status vocabulary:
[`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md)

**The roadmap is locked.** Step numbers are never reused or swapped without a decision record. Each Step
delivers exactly its declared scope: nothing from a later Step is pulled forward, and nothing declared is
quietly deferred.

---

## Summary

| Step | Title | Status |
| --- | --- | --- |
| 0 | Master Source and Governance | GO |
| 1 | Product Requirement and Domain Model | GO WITH ACCEPTED DEVIATION |
| 2 | Design System and UX Foundation | PLANNED |
| 3 | Runtime, Authentication, Multi-Tenancy, and RBAC | PLANNED |
| 4 | Laundry Master Data | PLANNED |
| 5 | POS, Order, and Payment Foundation | PLANNED |
| 6 | Production Operations | PLANNED |
| 7 | Customer Tracking and WhatsApp | PLANNED |
| 8 | Pickup and Delivery Operations | PLANNED |
| 9 | Unclaimed Laundry and Cashflow Recovery | PLANNED |
| 10 | Finance, Reports, and Owner Portfolio | PLANNED |
| 11 | Customer Android Experience | PLANNED |
| 12 | Subscription and Platform Administration | PLANNED |
| 13 | Security, Performance, Backup, and Recovery | PLANNED |
| 14 | Pilot and Commercial Launch | PLANNED |

---

## Step 0 — Master Source and Governance

**Status: GO** — conferred by the repository owner on 19 July 2026 against exact-SHA evidence, carrying
one recorded deviation: repository visibility is PUBLIC where the canonical requirement was PRIVATE
([`MASTER_SOURCE.md`](MASTER_SOURCE.md) §15.8,
[DEC-0016](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)).

Establish the canonical foundation before any code exists.

- The Master Source at version 1.0.0 with baseline date 19 July 2026, covering all thirty-three canonical
  sections.
- The status model, the Definition of Done, and the evidence policy.
- Fifteen accepted decision records, DEC-0001 to DEC-0015.
- The tenant isolation and financial integrity hard-gate policies.
- Git, release, AI execution, and tooling policies.
- The governance traceability matrix and the required-files inventory.
- Runtime placeholder folders containing only a README.
- A Step 0 validator, `scripts/verify-step-00.sh`.

**Explicitly out of scope:** every runtime artefact — no Flutter workspace, no Laravel application, no
schema, no migration, no API, no UI, no deployment.

---

## Step 1 — Product Requirement and Domain Model

**Status: GO WITH ACCEPTED DEVIATION** — conferred by the repository owner on 19 July 2026 against
exact-SHA evidence, tagged `aish-laundry-step-01-product-requirement-domain-model-v1.2.0-go` at commit
`4eadbc73f8bacdc9cd2acfcc62280ac932116089`. The accepted deviation is **single-maintainer governance
with no independent human review**
([DEC-0017](decisions/DEC-0017-single-maintainer-approval-standing-deviation.md)).

This step produced **documentation only**: requirements, domain model, business rules, state machines, an
initial threat model, acceptance criteria, and the governance rules that bind them. It created **no
runtime** — no Flutter workspace, no Laravel application, no schema, no migration, no API, no UI, no
deployment. Application CI remains **NOT APPLICABLE**.

Turn the Master Source into precise, buildable requirements.

- Detailed functional requirements per module (see Master Source §8).
- The laundry domain model: customer, order, order item, garment, service, price list, production stage,
  payment, invoice, pickup job, delivery job, courier, reminder.
- The canonical order status machine, including the exact definition and entry conditions of
  `READY_FOR_PICKUP`, which anchors unclaimed-laundry aging.
- Multi-tenancy data ownership rules expressed at the entity level.
- Acceptance criteria for the MVP scope.

**Out of scope:** any code, schema, or migration.

---

## Step 2 — Design System and UX Foundation

**Status: GO WITH ACCEPTED DEVIATION** — conferred by the repository owner on
20 July 2026 against exact-SHA evidence, tagged
`aish-laundry-step-02-design-system-ux-foundation-v1.3.0-go` at
`47c07d360e8802fd78f61d41435cae3f28313137`. Four deviations are recorded:
PUBLIC repository visibility, single-maintainer governance, no independent human
review, and design-only accessibility that is **not yet runtime-tested**.

- The visual language: white, soft blue, dark blue, restrained gold accent.
- Typography, spacing, elevation, and iconography, tuned for low-end Android and bright shop lighting.
- Component inventory: buttons, inputs, lists, status chips, empty states, error states, offline and sync
  indicators.
- Status representation that never relies on colour alone.
- Accessibility rules: contrast, touch targets, device font scaling.
- The courier-simplified interface pattern.
- Delivered as **specification only**. `packages/design_system` remains a placeholder containing a
  `README` or a `.gitkeep`; the Flutter workspace is `ABSENT` and no runtime consumes any of it.

**Out of scope:** product screens, business logic, backend, any runtime, any theme implementation, and
any accessibility test result. A design token is not a theme, a component specification is not a
component, and a wireframe is not a screen.

---

## Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC

**Status: PLANNED**

The first Step permitted to create a runtime.

- The Laravel modular monolith and the Flutter workspace.
- PostgreSQL, Redis, and object storage in local development.
- Versioned REST API at `/api/v1`.
- Phone + OTP authentication, sessions, devices, session and device revocation.
- Tenancy: tenants, brands, outlets, memberships, and the tenant switcher.
- RBAC with server-side authorisation on every request.
- The tenant isolation test suite — mandatory from this Step onward.
- The first application CI pipeline; application CI ceases to be NOT APPLICABLE.

---

## Step 4 — Laundry Master Data

**Status: PLANNED**

- Customers, contacts, addresses, and consent state.
- Services: kiloan, satuan, packages, add-ons.
- Price lists per brand, with historical price capture behaviour prepared.
- Outlet master data: operating hours, capacity, service zones, printers, shift definitions.
- Staff and role assignment within a tenant.

---

## Step 5 — POS, Order, and Payment Foundation

**Status: PLANNED**

- Order intake, quoting, nota generation, deposits.
- Payment: cash, transfer, and gateway, with idempotency by construction.
- Server-side verification of gateway callbacks.
- Refund and void with explicit permission and recorded reason.
- Reversal and adjustment entries; no deletion of financial transactions.
- Historical price immunity to later price-list changes.
- The financial integrity test suite — mandatory from this Step onward.

---

## Step 6 — Production Operations

**Status: PLANNED**

- Production stages, batches, and per-item tracking.
- Quality control and rework.
- The canonical order status lifecycle in operation, including the first transition to
  `READY_FOR_PICKUP` that starts unclaimed-laundry aging.
- Offline-first operation for the Ops Android application: `client_reference`, persistent queue,
  exponential backoff, conflict surfacing, per-tenant and per-user local separation, encrypted sensitive
  local data.

---

## Step 7 — Customer Tracking and WhatsApp

**Status: PLANNED**

- The Portal Tracking Publik: high-entropy tokens stored hashed, not derived from the order number,
  revocable, expiring, `noindex`, masked personal data, never showing a full address, OTP for sensitive
  actions.
- Notification provider abstraction with an official WhatsApp provider as the automated path and a manual
  deep-link fallback.
- Separation of transactional and marketing messages, opt-out handling, quiet hours defaulting to
  20.00–08.00 outlet local time, and message deduplication.
- Guarantee that a WhatsApp failure never cancels an order.

---

## Step 8 — Pickup and Delivery Operations

**Status: PLANNED**

- Pickup requests, scheduling, time windows, and service zones.
- Courier assignment for internal couriers and external ojek lokal.
- Simple route ordering and route suggestion, with no false optimisation claims.
- Proof of pickup and proof of delivery: OTP, photo, signature, recipient name.
- Cash collection at the door and courier cash reconciliation.
- Secure, single-job, expiring guest links for external ojek.

---

## Step 9 — Unclaimed Laundry and Cashflow Recovery

**Status: PLANNED**

- Aging measured from the moment an order **first** reaches `READY_FOR_PICKUP`.
- The reminder ladder: H+1 friendly, H+3 second, H+7 priority plus a follow-up task, H+14 escalation to
  manager or owner.
- The unclaimed laundry dashboard: order count, customer count, held invoices, unpaid balance, order age,
  outlet, last reminder, follow-up officer, reason not collected.
- Recovery actions, including offering delivery.
- Enforcement of the absolute prohibition on automatically discarding, selling, or transferring ownership
  of laundry.

---

## Step 10 — Finance, Reports, and Owner Portfolio

**Status: PLANNED**

- Shift closing comparing expected against actual cash, with variance reasons.
- Courier cash reconciliation reporting.
- Revenue, receivable, and operational reports.
- The owner portfolio dashboard consolidating brands and outlets **within one tenant**, without weakening
  tenant isolation.
- Drill-down from every aggregate to its underlying records, within permission and within the tenant.

---

## Step 11 — Customer Android Experience

**Status: PLANNED**

- The Aish Laundry Customer Android application: phone + OTP login, active orders, order history,
  tracking, pickup request, saved addresses, invoices, loyalty, feedback, notifications.
- Explicit verification that the application **enhances** and never replaces the public tracking portal
  (DEC-0014).

---

## Step 12 — Subscription and Platform Administration

**Status: PLANNED**

- Plans and limits matching the canonical pricing: 14 hari gratis trial; Starter Rp79.000/bulan; Growth
  Rp199.000/bulan; Scale Rp399.000/bulan; Enterprise mulai Rp999.000/bulan; annual Starter
  Rp790.000/tahun, Growth Rp1.990.000/tahun, Scale Rp3.990.000/tahun.
- Trial, upgrade, downgrade, and lapse handling, including continued data exportability per policy.
- Fair-use handling that never stops a laundry operating mid-shift.
- Transparent third-party messaging cost reporting.
- Platform administration: tenant lifecycle, platform health, and audited, time-bound support
  impersonation with no silent tenant access.

---

## Step 13 — Security, Performance, Backup, and Recovery

**Status: PLANNED**

- Rate limiting, brute-force protection, and abuse resistance across public surfaces.
- Upload validation and signed-URL delivery of private files.
- Encrypted backups with a tested restore procedure.
- Numeric performance budgets, measured against real low-end Android devices and congested networks.
- Observability: structured logging with redaction, correlation identifiers, metrics, tracing, and
  actionable alerting.
- Separate, append-only financial and security audit trails.

---

## Step 14 — Pilot and Commercial Launch

**Status: PLANNED**

- Pilot with real laundry tenants under the canonical pricing.
- UAT, moving UAT from NOT STARTED to a real status with evidence.
- Baselining the success metrics defined in Master Source §29 and setting targets from real data.
- Operational runbooks, support processes, and incident response.
- Commercial launch readiness review against the Definition of Done and both hard gates.

---

## Changing the roadmap

The roadmap changes only through a decision record and a Master Source version bump. Renumbering,
merging, splitting, or swapping Steps without a decision record is forbidden
([`MASTER_SOURCE.md`](MASTER_SOURCE.md) §1.4).
