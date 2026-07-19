# Subscription Domain — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** `NOT IMPLEMENTED` (documentation only)
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.1.0
**State machine:** [`../state-machines/SUBSCRIPTION_STATE_MACHINE.md`](../state-machines/SUBSCRIPTION_STATE_MACHINE.md)

Subscription is where the commercial decision meets the domain model. Pricing itself is a **locked
owner decision** and is canonical in Master Source §21; this document models the *behaviour* around
it and never restates a figure.

---

## 1. Scope

Owns: the tenant's plan assignment, trial state, fair-use usage counters, entitlement evaluation,
lapse and grace behaviour, and the tenant data export right.

Does not own: pricing figures (Master Source §21), money as a system of record (Payment and
Receivables), or tenant lifecycle (Tenant and Organization).

---

## 2. Pricing is not modelled here

**Canonical pricing figures live in Master Source §21 and are not reproduced in this document.**

Rules the model must honour (`FIN-030`):

- Figures are never rounded, reformatted, converted, "simplified", translated, or restated from
  memory. Where pricing appears anywhere — UI, documentation, tests, fixtures — it matches the Master
  Source character for character.
- **Pricing displayed to a user is read from a single canonical configuration derived from the Master
  Source**, never hard-coded in scattered UI strings.
- Subscription amounts are `Money` — integer Rupiah — and follow every rule in
  [`PAYMENT_DOMAIN.md`](PAYMENT_DOMAIN.md).
- A pricing change requires a decision record, a Master Source version bump, and a checksum refresh.
  It is never an engineering variable.
- The repository is PUBLIC, so pricing is publicly visible and must be accurate at all times. A stale
  price here is a commercial risk, not a typo.

---

## 3. Tenant boundary

- **Exactly one `Subscription` per `Tenant`** (`TEN-002`).
- **Subscription and billing operate at the tenant boundary** — never per user, never per outlet
  (`TEN-017`).
- Plan limits (outlets, staff, orders per month) are **enforced server-side**, tenant-scoped, and
  presented honestly to the tenant.
- An owner holding several tenants holds several subscriptions. Consolidating them into one bill
  would require crossing the isolation boundary and is not modelled.

---

## 4. Fair use, not a cutoff

> **Fair-use ceilings signal. They never silently degrade service, delete data, or stop a laundry
> mid-shift.** (`TEN-019`)

- Exceeding a ceiling triggers a **conversation and a plan recommendation**, emitted as
  `PlanLimitExceededFairUse`.
- The Starter order limit is explicitly **fair-use** and must be described as fair-use — not as a
  hard cutoff — unless a decision record changes that.
- `PlanLimitApproached` is a courtesy signal, presented honestly rather than as a scare tactic.
- **Order intake is never blocked by an entitlement failure.** A laundry in the middle of an evening
  rush does not stop because a counter crossed a threshold.

---

## 5. Lapse behaviour and the export right

> **Tenant data remains exportable per policy when a subscription lapses.** (`TEN-018`, `TEN-028`)

- A lapse **restricts features**. It does **not** hold a tenant's business records hostage.
- `RequestTenantDataExport` remains available through lapse and suspension. Policy P-31 makes this
  explicit.
- Export is tenant-scoped and carries the same access rules as the underlying records; exported files
  are private, tenant-keyed, and signed-URL only.
- A suspended tenant retains its data and its export right (`TEN-003`).
- **There is no `BlockDataExport` command in this model.** Its absence is the invariant.
- Downgrade, lapse, and grace behaviour must be fully defined before billing ships, and must honour
  the export right.

---

## 6. Guardrails carried into the model

These are commercial guardrails with structural consequences (`TEN-020`, `TEN-021`):

| Guardrail | Model consequence |
| --- | --- |
| **No lifetime cloud plan, ever** | No plan state representing perpetual service for a one-off fee exists. A cloud service carries perpetual cost; a one-time fee for perpetual service is a promise that cannot be kept honestly. |
| **No per-nota fee on normal plans** | No per-document metering exists on standard plans. |
| **The security baseline is not behind a tier** | Authentication, authorisation, secure storage, rate limiting, and audit logging are available on **every** plan including the entry plan. Security is not an upsell. |
| **Tenant isolation is not an add-on** | It is the architecture. It is never a paid feature, a premium tier, or an option. |
| **Backup is not a premium add-on** | Encrypted backup is baseline. |
| **WhatsApp provider fees are billed separately** | Messaging cost is transparent and is not bundled into the plan (`NOT-020`). |
| **No fake "unlimited WhatsApp"** | Message volume has a real cost and the product says so (`NOT-008`, `NOT-030`). |

**No plan may be constructed that violates these guardrails as a "custom Enterprise deal" without an
owner decision record.** Enterprise is a price point, not a guardrail exemption. An agent never
invents a plan, discount, promotion, trial extension, or limit that is not in the canonical pricing
table.

---

## 7. Trial

The trial is **14 hari gratis** (Master Source §21.1). Modelled as a bounded state that either
converts to an active subscription or lapses. A trial extension is a commercial decision requiring
the owner; it is not an entitlement the system grants itself.

---

## 8. Entitlement evaluation

- Evaluated **server-side**, tenant-scoped, on the operations that consume plan capacity (outlet
  creation, staff membership grants, monthly order counts).
- Presented honestly to the tenant: current usage, the ceiling, and what happens on exceeding it.
- An entitlement evaluation failure degrades **open for operations and closed for provisioning**: a
  laundry keeps taking orders; a new outlet is not silently created outside the plan.
- Usage counters are derived from tenant-scoped records and are never inferred across tenants.

---

## 9. Relationship to platform administration

Tenant lifecycle actions driven by subscription state — suspension for non-payment, reactivation —
travel through the **explicitly separated, audited** platform-administration path (`TEN-029`). There
is no silent tenant access, and there is no path by which a billing process quietly reads or alters
tenant business data.

---

## 10. Status

The subscription domain is `NOT IMPLEMENTED`. No subscription, billing, metering, plan-limit, trial,
or export implementation exists. Subscription and platform administration arrive in Step 12. Backend
runtime is `ABSENT`. This document claims no test, build, deployment, CI run, or UAT.

---

## Related documents

- [`TENANT_BOUNDARIES.md`](TENANT_BOUNDARIES.md)
- [`PAYMENT_DOMAIN.md`](PAYMENT_DOMAIN.md)
- [`DATA_OWNERSHIP.md`](DATA_OWNERSHIP.md)
- [`DOMAIN_INVARIANTS.md`](DOMAIN_INVARIANTS.md)
