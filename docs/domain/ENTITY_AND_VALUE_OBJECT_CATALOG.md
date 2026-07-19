# Entity and Value Object Catalog — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** `NOT IMPLEMENTED` (documentation only; no schema, no class, no migration)
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.2.0

This catalogue defines the **24 canonical value objects** of the domain and the rules that make them
correct. A value object is defined by its value, is immutable once constructed, validates itself at
construction, and is compared by value rather than identity.

All examples are **fictional** and recognisably so. This repository is PUBLIC; no real phone number,
name, or address appears here.

---

## 1. Entities versus value objects

| Concept | Rule |
| --- | --- |
| **Entity** | Has identity that persists across changes of state. Two entities with identical attributes are still different entities. Example: two `OrderLine` records for the same service on one order. |
| **Value object** | Has no identity. Two `Money` values of `Rp15000` are the same value. Immutable; a "change" produces a new value. |
| **Aggregate root** | The single entity through which an aggregate is loaded and modified. Enumerated in [`AGGREGATE_CATALOG.md`](AGGREGATE_CATALOG.md). |

**Construction validity is a domain rule, not a UI rule.** A value object that cannot be valid is not
constructed; there is no "invalid `Money`" waiting to be sanitised later.

---

## 2. Identity value objects

### 2.1 `TenantId`
The isolation boundary identifier. Opaque, non-sequential, and non-enumerable from outside.
**Rules.** Every business aggregate carries one. A `TenantId` arriving in a request body, header, or
query string is an **untrusted hint** that must be validated against the authenticated user's
memberships — it is **never authorisation proof** (`TEN-024`). A missing tenant scope yields **no
rows**, never another tenant's rows: the model fails closed.

### 2.2 `UserId`
The platform identity of a person. **Rules.** Carries no business authority by itself; authority is
derived from a `Membership`. A `UserId` is platform-scoped by design, because one person has one
account across the platform, but that fact grants nothing inside any tenant.

### 2.3 `OutletId`
A physical location within a brand within a tenant. **Rules.** Always resolvable to exactly one
`TenantId`. Outlet local time governs quiet hours and business-day aging.

### 2.4 `OrderId`
The internal, opaque, non-guessable identifier of a `LaundryOrder`. **Rules.** Never printed as the
customer-facing reference, never used as a tracking credential, never derivable from a
`HumanOrderNumber`.

### 2.5 `HumanOrderNumber`
The short, human-readable reference printed on the nota, sequential **within an outlet**.
**Rules.** **Guessable by design** — it must be short enough for a customer to read over the phone.
Precisely because it is guessable, it **never grants access to anything** (`TRK-003`). It is not
unique across tenants, and any lookup by it is authenticated and tenant-scoped.
**Fictional example.** `OUT01-260719-0042`.

### 2.6 `ClientReference`
The stable identifier the client generates **before** attempting an important operation.
**Rules.** Generated once, persisted with the queued operation, and **reused unchanged on every
retry** (`OFF-001`). It is the server's idempotency key for order creation, payment, proof capture,
and courier cash collection. Regenerating it on retry is the highest-risk bug class in the offline
design and is explicitly forbidden. It is high-entropy so that two devices cannot collide.
**Fictional example.** `cref_9f2a7c1e-4b60-4d3f-8a11-000000000000`.

### 2.7 `IdempotencyKey`
The server-side deduplication key. **Rules.** Derived from a `ClientReference` for client-originated
operations, and from a stable event identity for system-originated operations such as notification
sends (recipient + event + order + intended send window). A repeated key returns the **original
result** rather than creating a second record, and the suppression is observable rather than silent.

### 2.8 `Version`
The optimistic-concurrency counter on an aggregate. **Rules.** Every mutating command carries the
version it read. A mismatch means someone else changed the aggregate; the command is rejected and the
caller re-reads. Version is never used as a business identifier and never exposed as a sequence a
client can reason about.

---

## 3. Money and measurement value objects

### 3.1 `Money`
**The single most constrained value object in the model.**

- **Representation.** An **integer number of Rupiah**. The smallest representable unit is Rp1.
- **`FIN-001` — money is stored as integer Rupiah**, in every aggregate, projection, export, and API
  payload.
- **`FIN-002` — floating point is forbidden in every financial path**: storage, computation,
  transport, and any display path that round-trips through a float. No `float`, no `double`, no
  binary floating-point arithmetic anywhere in pricing, totals, discounts, taxes, payments, refunds,
  reconciliation, or reporting.
- **`FIN-033` — rounding is explicit.** Where a rule produces a fractional intermediate (a percentage
  discount, a weight band), the rounding mode and the rounding point are defined by the rule and
  recorded in the price snapshot. Rounding is never left to a language's default numeric behaviour
  and never happens implicitly at a display boundary.
- **`FIN-034` — money is never inferred from a display string.** Rupiah formatting is a view concern
  applied to an integer; parsing a formatted string back into money is forbidden.
- **`FIN-035` — arithmetic is total and checked.** Addition, subtraction, and multiplication by an
  integer quantity are permitted. Division is permitted only through an explicitly defined allocation
  rule that distributes remainders deterministically so that the parts always sum to the whole.
- **Currency.** Rupiah only. A `Money` value carries its currency so that a future currency cannot be
  added silently, but no second currency exists in this model.

**Fictional example.** `Money(15000, "IDR")` renders as `Rp15.000`. It is never `15000.0`.

### 3.2 `Weight`
The billable weight for kiloan work. **Rules.** Stored as an **integer number of grams**, never as a
floating-point kilogram figure. Conversion to a displayed kilogram value is a view concern. Weight
bands and minimum charges are evaluated on the integer gram value.
**Fictional example.** `Weight(3500)` displays as `3,5 kg`.

### 3.3 `Quantity`
The billable count for satuan work. **Rules.** A non-negative integer. Zero is permitted on a draft
line and rejected at intake confirmation.

---

## 4. Contact and location value objects

### 4.1 `PhoneNumber`
**Rules.** Stored in a normalised international form. Validated at construction. Used for
authentication (Identity and Access) and for messaging (Notification). **Never logged in full
alongside an OTP or a token.** Two tenants holding the same `PhoneNumber` hold two unrelated customer
profiles; a matching phone number is **never** grounds for merging, linking, or deduplicating across
the tenant boundary (`TEN-012`).
**Fictional example.** `+62-800-0000-0001` — an obviously invented placeholder.

### 4.2 `MaskedPhoneNumber`
The partially redacted form used wherever the full number is not required.
**Rules.** Masking level depends on context: a kasir preparing a delivery sees more than a public
portal visitor. **The public tracking projection carries only a `MaskedPhoneNumber`** — never a
`PhoneNumber` (`TRK-009`). Masking is applied when the projection is **built**, not when it is
rendered, so a rendering bug cannot leak the full value.
**Fictional example.** `+62-800-****-0001`.

### 4.3 `Address`
A structured postal address plus optional access notes.
**Rules.** **The full address is never shown on the public tracking portal** (`TRK-010`) and never
appears in a notification body (`NOT-015`). An external ojek lokal sees only what the assigned
delivery genuinely requires, and never in a shareable or indexable form (`DEL-020`). Addresses are
masked per context for staff according to role.
**Fictional example.** `Jalan Contoh Nomor 1, Kelurahan Fiktif, Kota Percontohan` — invented.

### 4.4 `GeoPoint`
A latitude and longitude pair used for zone matching and stop ordering.
**Rules.** Precision is bounded to what the operation needs. A `GeoPoint` is tenant-scoped
operational data and is never exposed on the public portal. It is **never** used to claim a computed
ETA or an optimal route that the system does not actually calculate (`DEL-005`).

### 4.5 `TimeWindow`
A start and end instant, resolved in outlet local time.
**Rules.** The customer is always given a **window**, never a fictitious exact minute (`DEL-004`). A
window is a commitment that is measurable after the fact. A window is not an ETA and is never
presented as one.

---

## 5. Status value objects

### 5.1 `OrderStatus`
A closed enumeration of exactly **fifteen** values:
`DRAFT`, `RECEIVED`, `AWAITING_PROCESS`, `SORTING`, `WASHING`, `DRYING`, `FINISHING`,
`QUALITY_CONTROL`, `REWORK`, `READY_FOR_PICKUP`, `SCHEDULED_FOR_DELIVERY`, `OUT_FOR_DELIVERY`,
`COMPLETED`, `CANCELLED`, `ISSUE`.
**Rules.** Transitions are enumerated exhaustively in
[`../state-machines/ORDER_STATE_MACHINE.md`](../state-machines/ORDER_STATE_MACHINE.md); **anything
not enumerated is forbidden**. There is no arbitrary status update path. Status is never conveyed to
a user by colour alone — every status carries a label and, where useful, an icon.

### 5.2 `PaymentStatus`
A closed enumeration covering the payment lifecycle, defined in
[`../state-machines/PAYMENT_STATE_MACHINE.md`](../state-machines/PAYMENT_STATE_MACHINE.md).
**Rules.** A `PaymentStatus` of `CAPTURED` is only ever set by a server-verified event or an
authorised in-person action recorded by an authenticated staff member. **Never on a client claim**
(`FIN-005`). An offline device may set `INTENT_RECORDED`; it may never set `CAPTURED` for a gateway
payment.

### 5.3 `DeliveryStatus`
A closed enumeration of exactly **eleven** values:
`REQUESTED`, `CONFIRMED`, `SCHEDULED`, `ASSIGNED`, `EN_ROUTE`, `ARRIVED`, `PICKED_UP`, `DELIVERED`,
`FAILED`, `RESCHEDULED`, `CANCELLED`.
**Rules.** `FAILED` is a **first-class outcome with a recorded reason**, not an error state
(`DEL-003`). `DELIVERED` is unreachable without a captured `DeliveryProof` (`DEL-002`).

---

## 6. Security and consent value objects

### 6.1 `TrackingTokenHash`
The stored, hashed form of a public tracking token.
**Rules.** The **plaintext token exists only inside the link** — it is never stored, never logged,
never placed in an audit entry, and never returned by any API after issuance (`TRK-002`). The token
is high-entropy from a cryptographically secure source (`TRK-001`), **is not the order number and is
not derivable from it** (`TRK-003`), is revocable (`TRK-004`), and expires (`TRK-005`). The same
value-object discipline governs the external ojek **guest job link** token (`DEL-008`).

### 6.2 `ReasonCode`
A machine-readable enumerated reason, paired with free-text where a human explanation is required.
**Rules.** Mandatory on: refund, void, quality-control waiver, order cancellation, failed delivery,
job reschedule, cash variance, tracking revocation, sync-conflict resolution, and reason not
collected. **A `ReasonCode` alone is not an acceptable user-facing error message** — errors explain
what happened and what to do next.

### 6.3 `NotificationChannel`
The delivery channel for a message.
**Rules.** Channel identity is **domain vocabulary, not vendor vocabulary**. No vendor SDK type,
payload shape, error code, or identifier leaks into business logic; swapping providers is an adapter
and configuration change (`NOT-009`). The manual deep-link fallback is a distinct, visible channel
and is **never described as automation** (`NOT-007`).

### 6.4 `NotificationPreference`
The recipient's per-tenant preference set: which categories, which channel, which language.
**Rules.** Preferences are recorded **per customer per tenant**. A preference in one tenant has no
effect in another, because the profiles are unrelated.

### 6.5 `ConsentState`
Marketing consent: granted or withdrawn, with a timestamp and a recorded source.
**Rules.** **Opt-out is honoured at send time**, not merely at campaign-build time, permanently, and
across every outlet of the tenant (`NOT-005`). **Absence of a refusal is not consent.** Opt-out is
**never reset by a data import** (`NOT-013`). Transactional messaging is governed separately from
marketing consent, and a marketing message is never routed through a transactional path to evade
opt-out (`NOT-006`).

### 6.6 `AuditActor`
The identified principal behind an action.
**Rules.** One of: a `Membership` (the normal case), a platform admin operating under an active,
time-bound, audited impersonation, or a named system process. **Never anonymous, never "system"
without a named process.** Every financial and security-relevant action carries one.

---

## 7. Value object rules that cut across the model

| Rule | Statement |
| --- | --- |
| **Immutability** | A value object never mutates. A correction constructs a new value and records the change through the owning aggregate. |
| **Validation at construction** | Invalid values are not representable. There is no post-hoc sanitisation step. |
| **No primitive obsession in money paths** | A raw integer is never passed where `Money` is meant. This is what prevents an accidental float from ever entering a total. |
| **Masking at projection build time** | `MaskedPhoneNumber` and masked names are produced when the public projection is built, never by hiding a full value at render time. |
| **Tenant carriage** | `TenantId` accompanies every business value that leaves an aggregate, so that a cache key, queue message, search index entry, export row, or object-storage key can never lack a tenant dimension. |
| **No secrets in values** | No value object ever carries a password, an OTP, a credential, or a token plaintext into a log, an audit entry, an export, or an error report. |

---

## 8. Status

Every value object and entity described here is `NOT IMPLEMENTED`. No class, type, schema, or
migration exists. Backend runtime is `ABSENT`; Flutter workspace is `ABSENT`. This document claims no
test, build, deployment, CI run, or UAT.

---

## Related documents

- [`DOMAIN_GLOSSARY.md`](DOMAIN_GLOSSARY.md)
- [`AGGREGATE_CATALOG.md`](AGGREGATE_CATALOG.md)
- [`DOMAIN_INVARIANTS.md`](DOMAIN_INVARIANTS.md)
