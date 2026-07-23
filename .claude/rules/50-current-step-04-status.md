# Rule 50 — Current Step 4 Status

## Purpose

To hold one honest statement of what Step 4 is, what authorised it, what it has actually delivered so
far, and — most importantly — what it has not, so that a step being under way is never mistaken for a
step being done.

Canonical status: [`../../docs/STATUS.md`](../../docs/STATUS.md). Master Source version **1.4.1**.

## Status snapshot

| Item | Status |
|---|---|
| Step 0 — Master Source and Governance | **GO WITH ACCEPTED DEVIATION** |
| Step 1 — Product Requirement and Domain Model | **GO WITH ACCEPTED DEVIATION** |
| Step 2 — Design System and UX Foundation | **GO WITH ACCEPTED DEVIATION** |
| Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC | **GO WITH ACCEPTED DEVIATION** |
| Step 4 — Laundry Master Data | **GO** |
| Steps 5–14 | **PLANNED** |
| Step 5+ product business features | **NOT IMPLEMENTED** |
| Deployment | **ABSENT** |
| UAT | **NOT STARTED** |

**Step 4 is now `GO`.** The repository owner conferred it on 22 July 2026 against exact-SHA evidence
after PR #18 merged as merge commit `af31ea3b0945b274b249ff21cf30918cb2d17a5f`. The immutable annotated
tag `aish-laundry-step-04-laundry-master-data-v1.0.0-go` (object
`55ed19761714aea945ecfcc919a78bae769339ac`) peels to that merge commit — never to the later evidence
commit. Closure evidence is [`../../evidence/step-04/`](../../evidence/step-04/), and the
independent-review chronology (three rounds, twenty-three findings, three refuted first remediations) is
in [`../../evidence/step-04/INDEPENDENT-REVIEW-CLOSURE.md`](../../evidence/step-04/INDEPENDENT-REVIEW-CLOSURE.md).

**`GO` is not an unqualified endorsement.** It carries accepted deviations and boundaries: NEW-04
(`ACCEPTED_OPERATIONAL_RESIDUAL`), single-maintainer governance with no independent human review
(DEC-0017), the database guarantees holding only at the application connection boundary — a non-owner,
non-superuser runtime role is `REQUIRED_FOR_FUTURE_DEPLOYMENT` and `NOT_YET_PROVISIONED` — and seven
requirements (FR-029, FR-033, FR-036, FR-039, FR-044, FR-046, FR-047) still `STEP_5_E2E_PENDING`, FR-036
a mandatory financial-integrity obligation. **Step 4 `GO` does not start Step 5 and does not authorise
deployment.**

While its pull request was open the maximum status Step 4 could carry was `IN PROGRESS`; `GO` is
conferred by the repository owner and is never self-declared by an agent (Rule 01). Both statements
remain true — the first is now history, the second is why the tag is owner-authorized rather than
agent-created.

## What authorised Step 4

Step 4 began under
[DEC-0028](../../docs/decisions/DEC-0028-step-04-scope-resolution-and-canonical-authorization.md), the
separate canonical authorization Rule 49 required. Two things happened in that record and they must not
be confused with one another:

1. **A scope conflict was resolved.** An execution brief proposed Step 4 as *"Domain, Branding,
   Environment, and SaaS Planning Foundation"*. The locked roadmap records Step 4 as **Laundry Master
   Data**. The locked roadmap stands; the brief is **WITHDRAWN as a Step 4 brief** and is carried as
   `UNSCHEDULED / REQUIRES SEPARATE CANONICAL DECISION`. It must never be implemented under Step 4's
   authorization, recorded as Step 4, aliased to Step 4, or assigned another step number by an agent.

2. **Step 4 was authorised to start.** That confers `IN PROGRESS` and nothing else.

**Authorization to start is not a status, and it is not evidence.** DEC-0028 records permission to
begin. It records no implementation, no passing test, and no `GO`.

## What Step 4 delivers

Per Master Source §24 and [`ROADMAP.md`](../../docs/ROADMAP.md), and no more than this:

- customer master data — identity, contacts, addresses, and consent state;
- service master data — kiloan, satuan, packages, and add-ons;
- price lists per brand, with historical price capture behaviour **prepared**;
- outlet master data — operating hours, capacity, service zones, printers, shift definitions;
- staff and role assignment within a tenant.

## What Step 4 does NOT deliver

Step 4 is master data. It is **not** the workflows that consume master data. The following remain
`NOT IMPLEMENTED` throughout Step 4 regardless of how complete the master data becomes:

order creation · order lifecycle · POS and cashier flows · production, washing, drying, ironing ·
quality control · packaging · invoices · payments · QRIS · refunds · promotions · discount engines ·
pickup scheduling · delivery routing · courier applications · the customer tracking portal runtime ·
WhatsApp message sending · the H+1/H+3/H+7/H+14 reminder ladder · unclaimed-laundry automation ·
billing · tenant subscription payments · reporting and owner portfolio · deployment.

**A price list is not a priced order. A service catalogue is not a POS.** An interface or immutable
reference that a later step will need may be added where Step 4 genuinely requires it, but it must
never become a premature implementation of that later step's workflow (Rule 36, Rule 42).

## The claim that must never be made

**A migration is not a tested schema, and a table is not a feature.** Creating a `customers` table does
not implement customer management; it creates the place customer management will later be verified
against. Every Step 4 claim is bound to executed output at an exact 40-character commit SHA (Rule 01,
DEC-0013), and a claim without that binding is an unverified claim and must say so in plain words.

Specifically, it is a false claim under Rule 01 to say or imply that Step 4 has delivered a working
screen, a passing test, a verified tenant boundary, a deployment, or any UAT result before the captured
evidence for that specific claim exists.

## The hard gates that do not relax for Step 4

- **Tenant isolation (Rule 02, Rule 39, Rule 48).** Every Step 4 business table carries `tenant_id`
  from its introducing migration; every query against it is tenant-scoped server-side. Negative tests
  must prove a member of tenant A cannot reach a tenant B record through **every** access path that
  exists — direct ID, list, filter, free-text search, export, and file URL — not merely the direct-ID
  path. Isolation evidence is produced against PostgreSQL, never a substitute engine (Rule 43). Any
  actual or suspected cross-tenant exposure is an automatic `NO-GO`.
- **Financial integrity (Rule 04, Rule 18).** Price-list money is **integer Rupiah**. Floating point is
  forbidden in every price path. Step 4 builds no payment, but it builds the prices payments will
  later read, and a `float` column introduced here would be inherited by Step 5 (Rule 42).
- **Historical price immutability (Rule 04, invariant 11).** Step 4 prepares the capture behaviour that
  makes a later order's price immune to a subsequent price-list change. Preparing it is Step 4's
  obligation; proving it against a real order is Step 5's.
- **Server-side authorization (Rule 03, Rule 40).** A client-supplied tenant, outlet, role, or
  permission claim is never authorization proof. Hiding a control is never the access control.
- **Public repository safety (Rule 23, Rule 45).** Every seed, fixture, and example customer name,
  phone number, and address is fictional and recognisably so. Master data is exactly where a real
  phone number quietly slips in, and deletion is not remediation.

## Step boundary

- **Step 5 does not begin until Step 4 has `GO`**, and Step 4 `GO` is not itself Step 5 authorization —
  Step 5 requires its own, exactly as Step 4 required DEC-0028 (Rule 49's precedent).
- Deployment remains `ABSENT` and is not authorised by anything in Step 4.
- Step numbers are locked and are never reused, renumbered, swapped, merged, or split without an
  accepted decision record (Master Source §24) — the constraint DEC-0028 applied when it rejected a
  brief that would have redefined Step 4.

## Maintenance

1. This snapshot is updated only when reality changes, alongside
   [`../../docs/STATUS.md`](../../docs/STATUS.md) and Master Source §24.
2. Statuses move forward on **exact-SHA evidence** only (Rule 01).
3. Use the approved status vocabulary only: `PLANNED`, `IN PROGRESS`, `TESTED`, `WATCH`,
   `NOT IMPLEMENTED`, `ABSENT`, `NOT APPLICABLE`, `NOT STARTED`, `NO-GO`, `GO`. A compound qualifier
   may narrow an approved base status; it never replaces one and never implies progress the evidence
   does not support.
4. If another document contradicts this snapshot, the other document is wrong — unless the Master
   Source itself has moved, in which case this file is updated to match it.

## Violation handling

- **Any claim that Step 4 delivered a working feature, a passing test, a verified tenant boundary, a
  deployment, or a UAT result without captured exact-SHA evidence** — correct it immediately and
  visibly, and state that the earlier claim was wrong (Rule 01).
- **The withdrawn "Domain, Branding, Environment, and SaaS Planning" brief implemented under Step 4,
  aliased to Step 4, or assigned a step number by an agent** — remove it and report the scope breach;
  it requires its own canonical decision (DEC-0028).
- **A Step 5+ feature built during Step 4, however named** — reject outright; renaming to evade
  structural detection is a governance breach in itself (Rule 36, hard rule 4).
- **A business table introduced without `tenant_id`, or a query against it that is not tenant-scoped** —
  reject; treat as a tenant-isolation defect, not a modelling nitpick (Rule 02, Rule 48).
- **A money column declared `float` or `double` anywhere in the price path** — reject outright
  (Rule 04).
- **A tenant-isolation claim covering only the direct-ID access path** — the claim is incomplete; list,
  filter, search, export, and file-URL paths are untested and the surface is not done (Rule 48).
- **A real customer name, phone number, or address found in a seed, fixture, or example** — remove and
  replace with a recognisably fictional value; if it was already pushed, treat it as a disclosure, not
  a typo (Rule 23, Rule 45).
- **`GO` written for Step 4 by an agent while its pull request is open** — revert the wording; `GO` is
  the owner's to confer (Rule 01).
- **A status advanced without exact-SHA evidence** — revert the advancement.
