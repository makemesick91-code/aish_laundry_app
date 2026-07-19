# Order Domain — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** `NOT IMPLEMENTED` (documentation only)
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.0.1
**State machine:** [`../state-machines/ORDER_STATE_MACHINE.md`](../state-machines/ORDER_STATE_MACHINE.md)

The `LaundryOrder` is the centre of the domain. Almost every other context exists to serve it,
observe it, or account for it.

---

## 1. Scope

The order domain owns: intake, pricing at intake, the nota, the canonical status lifecycle, condition
evidence, order issues, and completion. It does **not** own money (Payment and Receivables), physical
work (Production Operations), verification (Quality Control), custody transfer (Pickup and Delivery),
or aging (Unclaimed Laundry Recovery).

---

## 2. The intake flow

1. **Identify the customer** — a tenant-scoped `Customer` profile. If the customer is new, a profile
   is created **within this tenant only**. A phone number matching a profile in another tenant is
   irrelevant and invisible (`TEN-011`, `TEN-012`).
2. **Draft the order.** A `ClientReference` is generated **once**, before the first attempt, and is
   reused on every retry (`OFF-001`).
3. **Add lines.** Each line is a catalog item with a `Weight` (kiloan, integer grams) or a `Quantity`
   (satuan, integer count), plus any add-ons.
4. **Quote.** `QuotePrice` evaluates the effective `PriceList` version and its `PriceRule` set using
   integer arithmetic only, with an explicit rounding mode applied at a defined point (`FIN-013`,
   `FIN-014`).
5. **Capture condition evidence** where the tenant's policy or the garment's condition warrants it.
6. **Confirm intake.** The **price snapshot** is written onto every line, the server computes the
   authoritative total, the `HumanOrderNumber` is assigned, and the nota is produced.

**The order total is computed and authoritative on the server** (`FIN-015`). A client-computed total
is display only, so that an offline device showing a total is never the source of that total.

---

## 3. Price snapshot immutability

This is a hard gate and is restated in full.

> **An order captures the price that applied when it was created. Editing a price list never changes
> a past order, invoice, or nota.** (`FIN-011`, `FIN-012`, `FIN-036`)

Consequences carried by the model:

- Each `OrderLine` holds its own unit price, its evaluated add-on contributions, the identity and
  version of the `PriceRule` set applied, and the rounding decision taken (`FIN-033`).
- A line total is derived from **the line's own snapshot**, never re-derived from the live catalog
  (`FIN-017`).
- Retiring a catalog item, superseding a price list, or retiring a rule never touches an existing
  order (`FIN-010`).
- Reprinting an old nota shows the old price. Always.
- After intake confirmation a line is not silently editable. A correction is an authorised
  **amendment** that records actor and reason and emits an adjustment entry (`FIN-018`).
- There is **no** `RecalculateExistingOrders` command and no policy that reacts to
  `PriceListVersionPublished` by touching an order. Its absence is the invariant.

---

## 4. The fifteen canonical statuses

`DRAFT`, `RECEIVED`, `AWAITING_PROCESS`, `SORTING`, `WASHING`, `DRYING`, `FINISHING`,
`QUALITY_CONTROL`, `REWORK`, `READY_FOR_PICKUP`, `SCHEDULED_FOR_DELIVERY`, `OUT_FOR_DELIVERY`,
`COMPLETED`, `CANCELLED`, `ISSUE`.

There is no sixteenth status. Transitions are enumerated exhaustively in the order state machine;
**anything not enumerated is forbidden**, and no free-form `SetOrderStatus` command exists.

Presentation rule: **status is never conveyed by colour alone.** Every status carries a label and,
where useful, an icon — an accessibility requirement and a shop-floor-lighting requirement.

---

## 5. The first-ready timestamp

The single most consequential fact the order aggregate records after money.

> **Aging starts when an order FIRST reaches `READY_FOR_PICKUP`. The first-ready timestamp is
> recorded exactly once and is immutable thereafter. It never resets.** (`UCL-001`, `UCL-002`,
> `UCL-017`)

- The first transition into `READY_FOR_PICKUP` emits `OrderReachedReadyForPickupFirstTime`. That
  event is emitted **once in the order's entire life**.
- If the order later moves to `REWORK` and returns to `READY_FOR_PICKUP`, **no** new first-ready
  event is emitted and the aging anchor does not move. The customer's laundry has been finished since
  the first time, and the business has been carrying it since then.
- Aging is computed in outlet local time against Asia/Jakarta business-day semantics, never against
  an arbitrary server UTC midnight (`UCL-005`, `TEN-010`).

---

## 6. Order issues

`ISSUE` is a first-class status, not an error. It covers a lost item, a damaged garment, a customer
dispute, a mismatch found at handover, or a delivery dispute after the fact.

- Entering `ISSUE` requires a `ReasonCode` and free text, and records the actor.
- An issue **does not** delete, reverse, or hide anything. Money implications are handled by the
  Payment context through reversal or adjustment entries (`FIN-008`).
- Resolution records what was decided and by whom.
- A delivery dispute after `DELIVERED` is recorded as an order-level `ISSUE`; the delivery job itself
  is terminal and is never mutated (`DEL-029`).

---

## 7. Cancellation

- Cancellation requires permission, a `ReasonCode`, and free text.
- Cancellation **never deletes** the order, its lines, its evidence, or any money already taken.
  Captured money is corrected by reversal or adjustment (`FIN-008`).
- Cancellation after production has begun is permitted but records the production state reached, so
  that cost is visible in reporting.
- Cancellation revokes any active `TrackingAccess` and any active guest job link.

---

## 8. Offline behaviour

Order intake is the **most latency-critical internal surface** in the product and must keep working
without a network.

- Intake is queued with a stable `ClientReference` and is idempotent on it. **A retry after a network
  timeout produces exactly one order** (`OFF-001`, `OFF-007`).
- The queue is persistent: it survives application restart, device reboot, and crash (`OFF-002`).
- Queue ordering respects dependencies — create order, then add payment. An operation whose
  predecessor failed does not jump ahead (`OFF-009`).
- The kasir always sees whether an order is synced or pending. **A kasir must never believe an order
  or payment was recorded while it sits in a queue** (`OFF-013`).
- A failed intake is never silently dropped; it remains visible and actionable (`OFF-008`).

---

## 9. Tenant rules

- Every order carries `TenantId`, brand, and outlet (`TEN-015`).
- `HumanOrderNumber` is sequential **within an outlet**, is guessable by design, and is **never** an
  access credential (`TRK-003`). Lookup by it is always authenticated and tenant-scoped.
- Order lists, searches, filters, counts, and exports are tenant-scoped and fail closed (`TEN-025`).

---

## 10. Relationship to other domains

| Domain | Relationship |
| --- | --- |
| [`PAYMENT_DOMAIN.md`](PAYMENT_DOMAIN.md) | The order carries the authoritative amount due; Payment owns every financial record. |
| [`PRODUCTION_AND_QC_DOMAIN.md`](PRODUCTION_AND_QC_DOMAIN.md) | Production executes the work; only a `PASSED` or `WAIVED_WITH_AUTHORIZATION` inspection permits `READY_FOR_PICKUP`. |
| [`TRACKING_DOMAIN.md`](TRACKING_DOMAIN.md) | Tracking serves a **separate masked projection** of this order, never the internal representation. |
| [`PICKUP_DELIVERY_DOMAIN.md`](PICKUP_DELIVERY_DOMAIN.md) | Delivery moves the order between `SCHEDULED_FOR_DELIVERY`, `OUT_FOR_DELIVERY`, and `COMPLETED`. |
| [`UNCLAIMED_LAUNDRY_DOMAIN.md`](UNCLAIMED_LAUNDRY_DOMAIN.md) | Consumes the immutable first-ready timestamp. |
| [`NOTIFICATION_DOMAIN.md`](NOTIFICATION_DOMAIN.md) | Subscribes to order events. **No notification outcome ever changes order state** (`NOT-001`). |
| [`OFFLINE_SYNC_DOMAIN.md`](OFFLINE_SYNC_DOMAIN.md) | Delivers queued intake and status operations exactly once. |

---

## 11. Status

The order domain is `NOT IMPLEMENTED`. No order, line, status machine, nota, or intake path exists.
Backend runtime is `ABSENT`; Flutter workspace is `ABSENT`. This document claims no test, build,
deployment, CI run, or UAT.
