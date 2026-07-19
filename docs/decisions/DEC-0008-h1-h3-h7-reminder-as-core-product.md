# DEC-0008 — H+1 H+3 H+7 Reminder as Core Product

## ID

DEC-0008

## Title

H+1 H+3 H+7 Reminder as Core Product

## Status

ACCEPTED

## Date

19 July 2026

## Context

*Cucian menumpuk* — finished laundry that the customer never collects — is one of the most consistently
damaging and least discussed problems in Indonesian laundry businesses.

The work is complete. The detergent, water, electricity, and labour are spent. The garments occupy shelf
space that the business needs for the next batch. And in very many cases the balance is unpaid, so the
business has financed the customer's laundry indefinitely.

The current state of the art is the owner noticing that a shelf is full, then asking a staff member to
"chase the old ones", who scrolls through WhatsApp trying to work out which orders are old and who to
contact. There is no aging, no ladder, no ownership, and no record of what was already tried. Reminders
happen when someone remembers, which is to say rarely and unevenly.

Two design questions had to be settled canonically rather than left to implementation:

1. **When does aging start?** Order creation is wrong — an order in production is not uncollected.
   Payment is wrong — many orders are unpaid by design. The only defensible anchor is the moment the
   laundry became collectable.
2. **What happens when reminding fails?** Software that only sends messages produces a customer who
   ignores four messages and laundry that still occupies the shelf. Escalation to a human must be part of
   the design.

## Decision

**Structured unclaimed-laundry recovery is a core product capability**, with a canonical aging rule and a
canonical reminder ladder.

### Aging rule

**Aging starts when an order FIRST reaches status `READY_FOR_PICKUP`.**

"First" is literal. If an order returns to production for rework and reaches `READY_FOR_PICKUP` again,
the aging clock is **not** reset — the business has been carrying that laundry since the first time it
was collectable.

### Reminder ladder

| Age | Action |
| --- | --- |
| H+1 | Friendly reminder to the customer |
| H+3 | Second reminder |
| H+7 | Priority reminder **and** a follow-up task assigned to a staff member |
| H+14 | Escalation to the outlet manager or the owner |

Rules:

- Reminders are transactional, and still respect opt-out and quiet hours (§14).
- Reminders are de-duplicated; a retry never sends the same reminder twice.
- The H+7 follow-up task is a real, assignable, closable task with a named owner — not a notification.
- The H+14 escalation surfaces in the manager and owner dashboards.

### Dashboard minimum

Order count; customer count; held invoices; unpaid balance; order age; outlet; last reminder; follow-up
officer; reason not collected.

### Absolute prohibition

**The product never automatically discards, sells, or transfers ownership of laundry.** No configuration,
no plan, no escalation level, and no automation may dispose of a customer's property. The software's
responsibility ends at surfacing, reminding, escalating to a human, and recording the reason.

## Consequences

The order status machine must define `READY_FOR_PICKUP` precisely and record the timestamp of its **first**
occurrence as an immutable field (Step 1, Step 6). The reminder ladder becomes scheduled background work
with deduplication and quiet-hours handling (Step 7, Step 9). Follow-up tasks introduce an assignable
task concept. The dashboard becomes a required deliverable of Step 9 with a specified minimum content.
Delivery (DEC-0007) becomes an available recovery action.

## Positive consequences

- Converts a chronic, invisible loss into a measured, worked queue with named owners.
- Recovers shelf space, which is a hard physical constraint in small outlets.
- Recovers cash on held invoices and unpaid balances, which is the most direct commercial benefit the
  product can demonstrate to an owner.
- The recovery rate after H+1, H+3, and H+7 is measurable (§29.1), so the product can prove its own value
  with real numbers rather than a claim.
- The "reason not collected" field builds a real dataset about why customers do not return — information
  no owner currently has.
- The prohibition on automatic disposal protects both the customer's property and the business from a
  legally hazardous automation.

## Negative consequences / trade-offs

- **Four scheduled messages per uncollected order is a real cost**, both in provider fees (DEC-0011) and
  in customer patience. A customer who genuinely intends to collect may find four reminders irritating.
- **Reminder fatigue can damage the tenant's WhatsApp sender reputation** if templates or volumes are
  poor.
- **Quiet hours and opt-out reduce reach.** A customer who has opted out of marketing still receives
  transactional reminders, and the boundary between "reminder" and "marketing" must be maintained
  carefully to stay honest.
- **The rework rule is counter-intuitive** and will be questioned: an order reworked on day six still ages
  from day one. It is correct — the shelf has been occupied since day one — but it must be explained
  in the interface, not just in this record.
- **The H+7 follow-up task creates work for staff** who are already busy. If the task queue is not
  well-designed it will be ignored, and the ladder will terminate in practice at H+3.
- **Refusing automatic disposal means the product cannot fully close the loop.** Laundry from a customer
  who never responds remains on the shelf, visible and unresolved. This is accepted deliberately.

## Verification

- Step 6 tests assert that the first-`READY_FOR_PICKUP` timestamp is recorded and is **immutable across
  rework cycles**.
- Step 9 tests assert that H+1, H+3, H+7, and H+14 fire at the correct ages, exactly once each.
- Tests assert deduplication under retry and queue replay.
- Tests assert quiet-hours deferral using outlet local time, and opt-out handling.
- Tests assert that the H+7 follow-up task is created with an assignee and that H+14 surfaces to the
  manager and owner.
- Tests assert the dashboard exposes all nine minimum fields.
- Review asserts that no code path can discard, sell, or transfer ownership of laundry.
- At the Step 0 baseline: unclaimed laundry recovery is `NOT IMPLEMENTED`.

## Supersession policy

Superseded only by a decision record that redefines the ladder or the aging anchor, supported by pilot
evidence about recovery rates and customer tolerance. **The prohibition on automatic disposal is not
subject to supersession by an implementation preference**; changing it would require an explicit owner
decision recording the legal and ethical basis. Requires at least a **minor** version bump of
[`../MASTER_SOURCE.md`](../MASTER_SOURCE.md).

## Related Master Source sections

- §2 Vision
- §11 Unclaimed laundry
- §12 Owner dashboard and portfolio
- §14 Notifications and WhatsApp
- §22 MVP
- §29 Success metrics
