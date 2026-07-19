# Notification Domain — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** `NOT IMPLEMENTED` (documentation only)
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.0.1

WhatsApp is the channel Indonesian customers actually read. It is also a third-party service with
real per-message costs, policy constraints, and delivery failures. This domain prevents the two
classic mistakes: **coupling the product to one vendor**, and **over-promising messaging the business
cannot afford to deliver**.

---

## 1. Scope

Owns: notification requests, send-policy evaluation, deduplication, quiet-hours deferral, dispatch,
delivery outcomes, and the manual deep-link fallback.

Does not own: any business state, ever.

---

## 2. The structural guarantee

> **A provider failure never changes business state.** (`NOT-001`, `NOT-027`, `NOT-029`)

This is enforced structurally, not by convention:

- Every arrow into Notification is inbound. **No business aggregate subscribes to a notification
  event.** A future subscriber wiring `NotificationFailed` into an order, payment, job, or case is a
  design rejection (see [`DOMAIN_EVENTS.md`](DOMAIN_EVENTS.md) §13).
- **Sending is always asynchronous by design** (`NOT-026`), so that no business transaction can block
  on a provider.
- If the provider is down, the order still proceeds, the payment still captures, the courier still
  delivers, and the message is retried or flagged.

An order cancelled, blocked, or failed because messaging failed is a rejected design.

---

## 3. Provider abstraction

> **No vendor SDK type, payload shape, error code, or identifier leaks into business logic.**
> (`NOT-009`)

- Sending sits behind an internal interface. Swapping providers is an **adapter and configuration
  change**, never a product rewrite.
- The **official WhatsApp Business API provider is the automated path** (`NOT-010`).
- The **manual deep-link fallback** — a prepared `wa.me`-style link a staff member taps — is an
  acceptable fallback for tenants without a provider. It is **explicit, visible, and never described
  or sold as automation** (`NOT-007`).
- Vendor specifics leaking into business logic must be refactored behind the abstraction before the
  Step can meet its Definition of Done.

---

## 4. Deduplication

> **The same notification for the same order, event, and recipient is never sent twice.** (`NOT-002`)

- The deduplication key is a stable identity: **recipient + event + order + intended send window**.
- It holds across retries, queue replays, scheduler restarts, and double-triggers.
- The deduplication key **is** the idempotency key for this aggregate.
- A duplicate message reaching a customer is treated as a defect of the same class as a duplicate
  payment: investigate the key, fix it, add a regression test (`NOT-028`).

---

## 5. Quiet hours

> **Quiet hours default to 20.00–08.00 outlet local time.** (`NOT-003`, `NOT-004`)

- Evaluated in **outlet local time**, not server time, not the recipient's device time.
- Non-urgent messages queued during quiet hours are **deferred to the next permitted window** — never
  dropped, and never silently sent anyway (`NOT-021`).
- A quiet-hours exception path exists **only** where the Master Source or an accepted decision record
  explicitly grants it. Absent such a record, quiet hours apply (`NOT-022`).
- A message sent inside quiet hours without a recorded exception is a product defect: fix the
  scheduler before further messaging work, so it does not repeat at scale.

---

## 6. Consent and message classes

| | Transactional | Marketing |
| --- | --- | --- |
| Examples | Order received; ready for pickup; payment received; delivery completed; H+1/H+3/H+7 reminders | Promotions, loyalty campaigns |
| Consent required | No (the customer's own order) | **Yes**, explicitly |
| Opt-out | Respected for reminders (`UCL-023`) | Respected absolutely and permanently |
| Templates | Separate | Separate |
| Reporting | Separate | Separate |

Rules:

- Classes are **separated**: categories, templates, consent handling, and reporting (`NOT-006`).
- **A marketing message is never routed through a transactional path to evade opt-out** (`NOT-024`).
- **Opt-out is honoured at send time**, not merely at campaign-build time, permanently, across every
  outlet of the tenant (`NOT-005`).
- Consent is recorded **per customer per tenant**, with a timestamp and a source. **Absence of a
  refusal is not consent** (`NOT-011`).
- **Opt-out is never reset by a data import** (`NOT-013`).
- A marketing message sent to an opted-out recipient stops marketing sends, notifies the owner, and
  is fixed before resuming. It is a trust and compliance failure, not a minor bug.

---

## 7. Content rules

- Messages are written in **Bahasa Indonesia** (`NOT-012`).
- **A message never contains a full address** (`NOT-015`).
- **A message never echoes an OTP value alongside a tracking link** in a way that enables
  one-message account takeover (`NOT-014`, `TRK-029`).
- **Message logs never contain token plaintext, credentials, or OTP values** (`NOT-016`).
- Content carries no sensitive personal data beyond what the recipient already owns.
- Copy never overstates: no "instant", no "optimal", no "unlimited" where those are untrue.

---

## 8. The unlimited-WhatsApp prohibition

> **The product never promises "unlimited WhatsApp".** (`NOT-008`, `NOT-030`)

Not in the app, not on a pricing page, not in documentation, not in marketing copy, not in a sales
conversation. Message volume has a real third-party per-message cost, and claiming otherwise is a
false claim.

Corollaries:

- **Provider costs are transparent** to the tenant (`NOT-020`).
- **WhatsApp provider fees are billed separately** from the subscription plan.
- Tenants can see what messaging is costing them.

Any appearance of "unlimited WhatsApp" or equivalent is removed immediately as a false claim and a
pricing guardrail breach.

---

## 9. Delivery outcomes and retry

- Every send records **tenant, outlet, order, recipient, template, category, status, timestamp, and
  provider reference** (`NOT-019`).
- Delivery failures are **visible to the tenant** and retried under a **bounded** policy (`NOT-017`).
- **Not retried forever, and not silently discarded** (`NOT-018`).
- When the automated path is unavailable, the manual deep-link fallback is offered explicitly to
  staff.

---

## 10. The notification catalogue

Canonical intent, per Master Source §14.2:

| Event | Class | Channel |
| --- | --- | --- |
| Order received | Transactional | WhatsApp + tracking link |
| Order in production | Transactional | WhatsApp (optional per tenant) |
| Order ready for pickup | Transactional | WhatsApp + tracking link |
| Pickup scheduled / courier assigned | Transactional | WhatsApp |
| Delivery completed | Transactional | WhatsApp |
| Payment received | Transactional | WhatsApp |
| H+1 / H+3 / H+7 unclaimed reminder | Transactional | WhatsApp |
| H+14 escalation | **Internal** | In-product to manager/owner (`NOT-025`) |
| Promotions, loyalty campaigns | Marketing | WhatsApp, consent required |

---

## 11. Tenant rules

- Templates are **tenant-scoped** (`NOT-023`).
- Every send is tenant-scoped and fully attributed (`NOT-019`).
- Opt-out applies across **all outlets of the tenant**, and only that tenant — a customer opted out
  with one tenant has said nothing about another, because the profiles are unrelated (`TEN-011`).

---

## 12. Status

The notification domain is `NOT IMPLEMENTED`. No provider integration, adapter, template, scheduler,
deduplication key, or send path exists. Backend runtime is `ABSENT`. This document claims no test,
build, deployment, CI run, or UAT.

---

## Related documents

- [`UNCLAIMED_LAUNDRY_DOMAIN.md`](UNCLAIMED_LAUNDRY_DOMAIN.md)
- [`TRACKING_DOMAIN.md`](TRACKING_DOMAIN.md)
- [`ORDER_DOMAIN.md`](ORDER_DOMAIN.md)
- [`DOMAIN_INVARIANTS.md`](DOMAIN_INVARIANTS.md)
