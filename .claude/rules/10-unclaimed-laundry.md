# Rule 10 — Unclaimed Laundry (Cucian Menumpuk)

## Purpose

Unclaimed laundry is a real and expensive problem for Indonesian laundry businesses: finished orders
pile up, shelf space disappears, and the money owed on those orders is never collected. Handling this
systematically is a **core product differentiator**, not a reporting nicety.

Backed by **DEC-0008 — H+1 H+3 H+7 Reminder as Core Product**. Delivered in Step 9.

## Aging definition

**Aging starts when an order FIRST reaches status `READY_FOR_PICKUP`.**

- "First" is literal. If an order returns to `READY_FOR_PICKUP` after a status change, the aging
  clock **does not restart**. The original first-ready timestamp is the anchor.
- The first-ready timestamp is recorded once and is immutable thereafter.
- Aging is computed against outlet local time / Asia/Jakarta business days as defined in the Master
  Source — not against a server's arbitrary UTC midnight.

## The reminder ladder

| Stage | Action |
|---|---|
| **H+1** | Friendly reminder |
| **H+3** | Second reminder |
| **H+7** | Priority reminder **plus a follow-up task** |
| **H+14** | Escalation to manager / owner |

Hard rules for the ladder:

1. The four stages are the canonical ladder. Adding, removing, or renumbering a stage requires a
   decision record.
2. Each stage fires **once** per order. Deduplication is mandatory (Rule 08).
3. Reminders respect **quiet hours 20.00–08.00 outlet local time** and customer **opt-out** (Rule 08).
4. The **H+7 follow-up task** is a real assignable task with an owner, not merely a flag on a report.
5. The **H+14 escalation** reaches a manager or owner — a human accountable for the outcome.
6. A reminder that fails to send is retried and made visible; it is never silently dropped, and its
   failure never alters the order's state.

## Dashboard — minimum required fields

The unclaimed-laundry dashboard must expose at least:

- order count
- customer count
- held invoices
- unpaid balance
- order age
- outlet
- last reminder
- follow-up officer
- reason not collected

These are a **minimum**, not a maximum. All figures are tenant-scoped (Rule 02); unpaid balance and
held invoices are integer Rupiah and read from the authoritative financial records (Rule 04).

## Absolute prohibition

**Never automatically discard, sell, or transfer ownership of laundry.**

The system must not implement, schedule, or suggest any automated disposal, auction, donation, resale,
or ownership transfer of a customer's belongings — regardless of age, unpaid balance, or tenant
request. These are legal questions belonging to the tenant and its customer, not decisions a SaaS
product may automate. The product's role ends at reminding, escalating, and reporting.

Any future policy in this area would require an accepted decision record and explicit owner approval;
it is out of scope for the entire current roadmap.

## Supporting expectations

- "Reason not collected" is captured from staff follow-up and is a first-class field, because it is
  the data that actually reduces the pile.
- The dashboard is an operational tool: it must make the next action obvious, per the shortest-path
  UX rule (Rule 05).
- Cashflow recovery reporting reads from financial records; it never recomputes money independently.

## Step 0 note

No aging logic, reminder scheduler, dashboard, or task system exists. In Step 0 it is forbidden to
create any H+1/H+3/H+7 implementation. This rule records the constraints only.

## Violation handling

- **Aging clock restarted by a status change back to `READY_FOR_PICKUP`** — defect; the first-ready
  timestamp is immutable. Fix and add a regression test.
- **A reminder stage sent twice, or sent inside quiet hours, or sent to an opted-out customer** —
  handle under Rule 08 violation handling; stop the scheduler before it repeats at scale.
- **A dashboard missing any of the nine minimum fields** — the step does not meet its Definition of
  Done.
- **Any implementation, script, backlog item, or suggestion to auto-discard, sell, auction, donate,
  or transfer customer laundry** — refuse outright and escalate to the repository owner. Do not
  implement it behind a flag, do not prototype it, do not leave it as a TODO.
- **Unpaid balance or held-invoice figures computed outside the financial records** — reject; they
  must read from the authoritative source (Rule 04).
