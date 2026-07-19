# DEC-0015 — MVP Focuses on Laundry Operations

## ID

DEC-0015

## Title

MVP Focuses on Laundry Operations

## Status

ACCEPTED

## Date

19 July 2026

## Context

Aish Laundry App's full canonical scope spans fifteen Steps and covers operations, customer experience,
finance, subscription, platform administration, security hardening, and commercial launch. Built in the
wrong order, the product could spend a year in development and still be unable to take a single real
laundry order.

Several tempting orderings were available and each fails for a specific reason:

- **Customer application first.** It is the most visible surface and the easiest to demonstrate. But a
  customer application with no operational backend behind it shows nothing real, and DEC-0006 already
  solves the customer's actual problem without an application.
- **Subscription and billing first.** It is where revenue comes from. But there is nothing to charge for
  until a laundry can run a working day inside the product.
- **Loyalty and engagement first.** These are attractive differentiators in a pitch. A laundry that
  cannot take an order does not care about loyalty points.
- **Everything at once.** Guarantees a long build with no feedback, and no point at which the assumptions
  in §2 are tested against a real shop floor.

The binding constraint is that the product's value proposition — tracking, delivery, and unclaimed-laundry
recovery — depends entirely on operational data. A tracking portal needs real orders with real statuses. A
reminder ladder needs a real `READY_FOR_PICKUP` transition. A delivery module needs real orders to
deliver. Nothing differentiating can be validated until operations work.

## Decision

**The MVP focuses on laundry operations.**

The MVP is the smallest product that lets a single laundry tenant run a real working day end to end and
lets its customers track their laundry without installing anything.

### In the MVP

| Capability | Step |
| --- | --- |
| Authentication with phone + OTP | Step 3 |
| Tenancy, brands, outlets, memberships, tenant switcher | Step 3 |
| RBAC with server-side authorisation | Step 3 |
| Customers, services, price lists, outlet master data | Step 4 |
| POS order intake, nota, payment, refund and void with audit | Step 5 |
| Production stages, status lifecycle including `READY_FOR_PICKUP` | Step 6 |
| Public tracking portal with secure tokens | Step 7 |
| WhatsApp notification with provider abstraction and fallback | Step 7 |
| Pickup and delivery with proof and courier cash | Step 8 |
| Unclaimed laundry H+1/H+3/H+7/H+14 and its dashboard | Step 9 |
| Shift closing, reconciliation, core reports, owner portfolio | Step 10 |

### After the MVP

| Capability | Step |
| --- | --- |
| Customer Android application, loyalty, feedback, invoices | Step 11 |
| Subscription, plan limits, platform administration | Step 12 |
| Security hardening, performance budgets, backup and recovery | Step 13 |
| Pilot and commercial launch | Step 14 |

### Quality bar

The MVP is small in scope but not lax in quality. Both hard gates of DEC-0012 apply from the Step that
introduces the relevant capability — tenant isolation from Step 3, financial integrity from Step 5. There
is no "we will secure it after the pilot".

## Consequences

The roadmap order in §24 follows this decision directly. All three differentiators — public tracking
(DEC-0006), pickup and delivery (DEC-0007), and unclaimed-laundry recovery (DEC-0008) — are inside the
MVP, because a product without them is not this product. The customer application is deliberately
after the MVP, which is only tenable because DEC-0006 and DEC-0014 guarantee that customers are fully
served without it. Subscription enforcement arriving at Step 12 means the pilot in Step 14 runs on
manually administered tenants.

## Positive consequences

- The earliest possible point at which a real laundry can use the product for a real working day, which is
  the only way the assumptions in §2 get tested.
- All three differentiators are validated with real operational data before effort is spent on secondary
  surfaces.
- Operational data accumulates from the MVP onward, so that Step 14 can baseline the success metrics in
  §29 against reality rather than estimates.
- Both hard gates are enforced from the Steps that introduce money and tenancy, so security is not
  retrofitted.
- Deferring the customer application avoids building an engagement surface before knowing what customers
  actually do with the portal.
- Each Step in the MVP produces something a laundry owner can see working, which sustains momentum and
  invites feedback.

## Negative consequences / trade-offs

- **The MVP is still eleven Steps.** "Minimum" here means minimum for this product, not minimum for a
  demonstration. It is a long path to first revenue.
- **No revenue until Step 12–14.** Subscription enforcement arrives late, so the pilot runs on manually
  managed tenants and early commercial validation is delayed.
- **The customer application arrives late**, which will disappoint anyone who evaluates the product by
  its consumer-facing surface. The portal must carry that impression alone.
- **Operations-first is unglamorous.** POS, master data, and production stages demonstrate poorly compared
  to a polished consumer app, which makes the product harder to pitch mid-build.
- **Security hardening and performance budgets sit at Step 13**, after the MVP. The hard gates cover the
  catastrophic categories, but rate limiting, backup testing, and measured performance budgets arrive
  later than ideal.
- **Loyalty and engagement features are deferred** past the point where a competitor might ship them.

## Verification

- The roadmap in [`../ROADMAP.md`](../ROADMAP.md) and §24 matches the ordering in this record exactly.
- Each MVP Step's Definition of Done confirms its declared capability works end to end, with evidence.
- Step 10 verifies that a single tenant can complete a full working day: order intake, production,
  ready, tracking link delivered, collection or delivery with proof, payment, and shift close.
- Step 9 verifies the unclaimed-laundry ladder against real aging data.
- Step 14 verifies that the MVP scope was sufficient for a real pilot, and records honestly anything that
  was not.
- At the Step 0 baseline: every MVP capability is `NOT IMPLEMENTED` and every MVP Step is `PLANNED`.

## Supersession policy

Superseded only by a decision record that restates the MVP scope, explains what changed in the market or
the evidence, and confirms that both hard gates still apply from the Steps that introduce money and
tenancy. Moving a capability into or out of the MVP requires a decision record and a corresponding update
to §22 and §24. **Reducing the quality bar to accelerate the MVP is not an acceptable basis for
supersession.** Requires at least a **minor** version bump of
[`../MASTER_SOURCE.md`](../MASTER_SOURCE.md).

## Related Master Source sections

- §2 Vision
- §8 Product modules
- §22 MVP
- §23 Non-goals
- §24 Roadmap
- §25 Definition of Done
- §29 Success metrics
