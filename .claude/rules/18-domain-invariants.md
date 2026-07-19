# Rule 18 — Domain Invariants

## Purpose

An invariant is a statement that must be true of the data at all times, regardless of which code path
ran, which client sent the request, or what the network did. Invariants are where correctness actually
lives; validation messages are only how they are explained to a user. Delivered in Step 1, enforced from
Step 3 onward.

Canonical catalogue: `docs/domain/DOMAIN_INVARIANTS.md`.

## Hard rules

1. **Invariants are enforced server-side.** A client-side check is a user-experience affordance, never an
   invariant (Rule 03).
2. **An invariant holds at every entry point** — API, background job, queue replay, import, admin tool,
   and any future migration or backfill. An invariant that only one code path honours is not an
   invariant.
3. **An invariant is never suspended** to unblock a demo, a pilot, a deadline, or a data fix. If real
   data violates an invariant, the data is corrected through an audited path — the rule is not relaxed.
4. **Every invariant is stated positively and testably**, names the aggregate that owns it, and names the
   roadmap Step that will enforce it.
5. **A violated invariant fails closed.** The operation is rejected; it never partially applies.

## The non-negotiable invariants

These derive directly from the Master Source hard gates and may never be weakened:

### Tenant isolation (Rule 02)

6. Every business record carries a `tenant_id`, and every business query is tenant-scoped.
7. A client-supplied tenant identifier is never authorisation proof.
8. No record is ever merged across tenants because name, email, phone, device, or owner match.
9. A customer profile is tenant-scoped. The same phone number in two tenants is two unrelated profiles.

### Financial integrity (Rule 04)

10. Money is **integer Rupiah**. Floating point never appears in a money path.
11. An order captures the price that applied when it was created. **A price-list change never alters a
    historical order, invoice, or reprint.**
12. A payment is idempotent on its `ClientReference`. A retry produces exactly one payment.
13. A financial record is never hard-deleted. Corrections are reversal or adjustment entries; the ledger
    is append-only in effect.
14. An order is never marked paid on a client claim.

### Order lifecycle (Rule 19)

15. An order's status is always one of the fifteen canonical statuses. There is no free-text status.
16. Only an enumerated transition may occur. There is no arbitrary status assignment.

### Unclaimed laundry (Rule 10)

17. **The first-`READY_FOR_PICKUP` timestamp is written once and is immutable.** Aging is computed from
    it and **never restarts**, even if the order returns to `REWORK` and becomes ready again.
18. Each reminder stage fires at most once per order.

### Tracking (Rule 21, Master Source §9)

19. A tracking token is never the order number and is never derivable from it.
20. Only the token hash is stored. The plaintext token exists only in the link.
21. The public tracking projection never contains a full address, an internal note, staff identity beyond
    operational necessity, cost, or margin.

### Custody and proof (Rule 09)

22. No custody transfer is recorded without proof appropriate to the tenant's configured policy.
23. Courier cash is reconciled; a variance is recorded and acknowledged, never absorbed silently.

### Offline (Rule 07)

24. A retried operation reuses its original `ClientReference`. Regenerating it on retry is forbidden.
25. A duplicate order or duplicate payment produced by a retry is unacceptable.

## Concurrency

26. Operations that must not interleave — applying a payment, closing a shift, transitioning status,
    assigning a courier — are serialised by a database transaction or a distributed lock (Rule 06).
27. An invariant that holds only when requests arrive one at a time is not implemented; it is hoped for.

## Step 1 note

No invariant is enforced yet, because no runtime exists. Step 1 records the catalogue. Enforcement and
its negative tests begin at **Step 3** and are a Definition-of-Done gate for every Step that introduces
an aggregate (Rule 13).

## Violation handling

- **An invariant enforced only in a client** — reject until server-side enforcement exists.
- **An invariant bypassed by a background job, import, admin tool, or backfill** — treat as the same
  severity as bypassing it in the API.
- **Any tenant-isolation invariant violated (6–9)** — automatic **NO-GO** (Rule 02).
- **Any financial invariant violated (10–14)** — automatic **NO-GO** (Rule 04).
- **The aging clock restarted, or the first-ready timestamp mutated** — defect; fix and add a regression
  test before the Step closes.
- **A proposal to relax an invariant for convenience, performance, reporting, a demo, or staging** —
  refuse and escalate to the owner. There is no staging exemption.
