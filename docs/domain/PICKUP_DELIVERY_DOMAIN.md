# Pickup and Delivery Domain — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** `NOT IMPLEMENTED` (documentation only)
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.0.1
**Decision record:** [DEC-0007](../decisions/DEC-0007-pickup-and-delivery-as-core-product.md)
**State machines:**
[`../state-machines/PICKUP_DELIVERY_STATE_MACHINE.md`](../state-machines/PICKUP_DELIVERY_STATE_MACHINE.md),
[`../state-machines/COURIER_SETTLEMENT_STATE_MACHINE.md`](../state-machines/COURIER_SETTLEMENT_STATE_MACHINE.md)

Antar-jemput is a **core product capability**, not an add-on. It also introduces the two riskiest
operational surfaces in the system: **physical custody of a customer's belongings**, and **cash held
by a courier**.

---

## 1. Scope

Owns: pickup requests, confirmation, zone matching, scheduling and time windows, courier assignment
(internal and external), stop ordering, custody transfer, proof capture, failure and reschedule
handling, and courier cash reconciliation.

Does not own: the order lifecycle, money as a system of record, or notification delivery.

---

## 2. The eleven canonical job statuses

`REQUESTED`, `CONFIRMED`, `SCHEDULED`, `ASSIGNED`, `EN_ROUTE`, `ARRIVED`, `PICKED_UP`, `DELIVERED`,
`FAILED`, `RESCHEDULED`, `CANCELLED`.

There is no twelfth. Transitions are enumerated exhaustively in the state machine; anything not
enumerated is forbidden (`DEL-001`).

---

## 3. Proof is mandatory

> **Every custody transfer requires proof. A parcel never silently changes hands.** (`DEL-002`,
> `DEL-011`)

- Proof applies to **both** directions: proof of pickup at the customer's door, and proof of delivery
  at handover.
- Accepted methods, per tenant policy: **OTP, photo, signature, recipient name**. The method may vary;
  *some* recorded proof is always required.
- **`DELIVERED` is unreachable without a captured `DeliveryProof`** (`DEL-027`). This is enforced in
  the state machine, not merely in the UI.
- `ARRIVED` must be recorded before `DELIVERED`. A job never jumps from `EN_ROUTE` to `DELIVERED`
  (`DEL-028`).
- **Proof artefacts are private data** (`DEL-012`): private object storage, tenant-scoped unguessable
  keys, signed expiring URLs, and **never exposed on the public tracking portal** (`TRK-017`,
  `DEL-021`). A proof photograph may show the inside of a customer's doorway; a signature is
  biometric-adjacent handwriting.
- **Proof capture works offline** and syncs later (`DEL-013`, `OFF-022`). A courier in a dead zone is
  never forced to skip proof — that is precisely the situation in which a lost proof becomes a
  dispute the tenant cannot win.

---

## 4. No route optimization claims

> **The product suggests an order of stops. It never claims an optimal route.** (`DEL-005`,
> `DEL-010`, `DEL-026`)

- The canonical phrase is **"usulan rute"** — route suggestion. The product must never say
  "rute optimal".
- No guaranteed arrival time. No ETA the system does not actually compute.
- The customer is given a **`TimeWindow`**, never a fictitious exact minute (`DEL-004`). A window is a
  commitment that is measurable after the fact (`DEL-032`).
- This is a direct application of the honesty value: telling a courier a route is optimal when it is
  merely ordered is the same class of failure as telling a customer laundry is ready when it is not.

---

## 5. Internal courier versus external ojek lokal

| | Internal courier (kurir) | External ojek lokal |
| --- | --- | --- |
| Identity | Holds a `Membership` (`DEL-006`) | **No membership, no account, no application access** (`DEL-007`) |
| Access mechanism | Aish Laundry Ops Android, authenticated | A **guest job link** only |
| Scope | Assigned jobs within their outlet scope | **Exactly one assigned job** |
| Sees customer history | No | **Never** (`DEL-024`) |
| Sees other orders | No | **Never** (`DEL-024`) |
| Sees pricing | Per role | **Never** (`DEL-024`) |
| Sees address | Full, for the assigned job | **Only what the assigned delivery genuinely requires**, never in a shareable or indexable form (`DEL-020`) |
| Offline capable | Yes | Limited to the job at hand |

### 5.1 The guest job link

The guest job link is a **scoped, expiring, revocable, minimal-privilege temporary credential**
(`DEL-008`):

- **high-entropy token, stored hashed** server-side;
- **revocable**, effective immediately (`DEL-033`);
- **expiring**, always bounded;
- **not** the order number, and **not derivable from it**;
- exposes **only the assigned job and the minimum data needed to complete it**;
- grants **no** access to customer history, other orders, pricing, or any tenant data beyond the
  assignment (`DEL-024`);
- **tenant-scoped**: an external rider working for two tenants receives **two unrelated links** and
  can never traverse from one to the other (`DEL-009`).

A guest link that is guessable, non-expiring, non-revocable, stored in plaintext, or that grants
access beyond its assignment is a security defect of the highest severity and must be fixed before
any external courier feature ships.

---

## 6. Failure is a first-class outcome

> **A failed delivery is a first-class outcome with a recorded reason, not an error state.**
> (`DEL-003`)

- `FAILED` records a `ReasonCode` and free text: nobody home, address not found, customer refused,
  payment not available, parcel damaged in transit.
- The laundry **returns to the outlet** and the order returns to a defined status (`DEL-031`).
- A reschedule preserves the original schedule in a recorded chain; the original is **never
  overwritten** (`DEL-022`).
- Cancellation records a `ReasonCode`, free text, and the actor (`DEL-023`), and revokes any active
  guest job link.
- `DELIVERED` is **terminal** (`DEL-029`). A dispute after delivery is recorded as an order-level
  `ISSUE`, never by mutating the completed job.

---

## 7. Courier cash

Cash collected at the door is a **financial transaction** and inherits every rule in
[`PAYMENT_DOMAIN.md`](PAYMENT_DOMAIN.md) (`DEL-014`, `FIN-027`):

- integer Rupiah;
- idempotent on `ClientReference`, because it is captured at the door and frequently offline;
- never deleted through ordinary UI;
- corrected by reversal or adjustment entries only;
- audited with actor, timestamp, and reason.

Reconciliation (`FIN-028`, `FIN-029`, `DEL-030`):

- tracked **per courier, per shift or route**, from collection to hand-over;
- expected versus actual compared **explicitly**;
- any variance **recorded and acknowledged**, never hidden, auto-adjusted, or written off silently;
- a settlement **cannot be accepted while an unacknowledged variance exists**.

A courier cash variance that the system cannot account for is a financial integrity failure and an
automatic `NO-GO`.

---

## 8. Zones, scheduling, and assignment

- **Zones** are defined per outlet; a request is matched to a zone for coverage and grouping
  (`DEL-017`).
- A job references **exactly one order in the same tenant** (`DEL-018`).
- A job has **at most one active `CourierAssignment`** at a time (`DEL-019`). Reassignment is
  serialized so a job never has two active couriers.
- Scheduling communicates a `TimeWindow` to the customer. Time-window adherence is measurable and is
  a monitored operational signal.

---

## 9. Courier user experience

Courier UX is **deliberately simple** (`DEL-015`): large tap targets, few steps, one job at a time,
usable one-handed, outdoors, in sunlight, on a cheap phone, under time pressure. Complexity here does
not produce a slower courier — it produces **skipped proofs and lost cash**.

---

## 10. Offline behaviour

- Every courier-captured transition is idempotent on `ClientReference` (`DEL-034`).
- Proof capture and cash recording queue persistently and survive app kill and device reboot
  (`OFF-002`, `OFF-019`).
- A retry after a dead zone does **not** create a second delivery record or a second cash collection.
- The courier always sees what is pending sync (`OFF-013`).

---

## 11. Notification relationship

Delivery notifications follow every notification rule, including quiet hours, deduplication, and
opt-out (`DEL-016`). **No notification outcome ever changes job state** (`DEL-035`, `NOT-001`). If
the provider is down, the courier still delivers and the proof is still captured.

---

## 12. Relationship to unclaimed laundry

Delivery is the strongest remedy for unclaimed laundry: laundry a customer will not collect can often
be delivered. A `ProposeDeliveryAsRecovery` command exists on the unclaimed case (`DEL-025`,
`UCL-018`). This is a **human-initiated** recovery action, not an automatic one.

---

## 13. Status

The pickup and delivery domain is `NOT IMPLEMENTED`. No job, zone, courier assignment, guest link,
proof, or settlement path exists. Backend runtime is `ABSENT`. This document claims no test, build,
deployment, CI run, or UAT.

---

## Related documents

- [`PAYMENT_DOMAIN.md`](PAYMENT_DOMAIN.md)
- [`ORDER_DOMAIN.md`](ORDER_DOMAIN.md)
- [`UNCLAIMED_LAUNDRY_DOMAIN.md`](UNCLAIMED_LAUNDRY_DOMAIN.md)
- [`OFFLINE_SYNC_DOMAIN.md`](OFFLINE_SYNC_DOMAIN.md)
- [`DOMAIN_INVARIANTS.md`](DOMAIN_INVARIANTS.md)
