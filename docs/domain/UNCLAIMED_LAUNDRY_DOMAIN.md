# Unclaimed Laundry Domain — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** `NOT IMPLEMENTED` (documentation only)
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.1.0
**Decision record:** [DEC-0008](../decisions/DEC-0008-h1-h3-h7-reminder-as-core-product.md)
**State machine:** [`../state-machines/UNCLAIMED_LAUNDRY_STATE_MACHINE.md`](../state-machines/UNCLAIMED_LAUNDRY_STATE_MACHINE.md)

*Cucian menumpuk* — finished laundry the customer never collects — consumes shelf space and traps
cash. Recovering it is a **core product capability**, not a reporting nicety.

---

## 1. Scope

Owns: aging computation, the reminder ladder, the H+7 follow-up task, the H+14 escalation, the
"reason not collected" record, and the unclaimed dashboard's aggregation logic.

Does not own: money (it **reads** from Payment and Receivables), message delivery (Notification), or
the order lifecycle.

---

## 2. The aging rule

> **Aging starts when an order FIRST reaches `READY_FOR_PICKUP`. The first-ready timestamp is
> recorded once, is immutable, and NEVER restarts.** (`UCL-001`, `UCL-002`, `UCL-017`)

"First" is literal.

- If an order returns to `REWORK` and reaches `READY_FOR_PICKUP` again, the aging clock **does not
  reset**. The customer's laundry has been finished since the first time, and the business has been
  carrying it since then.
- If an order is flagged `ISSUE` and returns to ready, the clock does not reset.
- If a delivery attempt fails and the order returns to ready, the clock does not reset.
- The anchor is `OrderReachedReadyForPickupFirstTime`, an event emitted **exactly once in the order's
  entire life**.
- Aging is computed in **outlet local time** against Asia/Jakarta business-day semantics — never
  against an arbitrary server UTC midnight (`UCL-005`, `TEN-010`).

**There is no `ResetAging` command and no policy that resets the anchor.** Its absence is the
invariant. A recomputed or mutated first-ready timestamp is a defect requiring a regression test.

---

## 3. Aging buckets

The canonical reporting bands (`UCL-008`):

| Bucket | Range |
| --- | --- |
| 1 | **1–2 days** |
| 2 | **3–6 days** |
| 3 | **7–13 days** |
| 4 | **14–30 days** |
| 5 | **More than 30 days** |

The aging distribution across these buckets is a monitored business-health signal (`UCL-025`).

---

## 4. The reminder ladder

| Stage | Action | Rules |
| --- | --- | --- |
| **H+1** | Friendly reminder to the customer | Transactional; respects quiet hours and opt-out |
| **H+3** | Second reminder | Same |
| **H+7** | Priority reminder **plus an assignable follow-up task** | The task is real, assignable, and closable, with a named owner (`UCL-009`) |
| **H+14** | **Escalation to the outlet manager or the owner** | Internal in-product notification, not a customer WhatsApp message (`UCL-010`, `NOT-025`) |

Hard rules:

1. These four stages are the canonical ladder. **Adding, removing, or renumbering a stage requires an
   accepted decision record** (`UCL-016`).
2. **Each stage fires exactly once per order.** Deduplication is mandatory and survives scheduler
   restarts, retries, and queue replays (`UCL-004`, `NOT-002`).
3. Reminders respect **quiet hours (default 20.00–08.00 outlet local time)** and customer **opt-out**
   (`UCL-006`, `UCL-023`, `NOT-003`, `NOT-005`). They are transactional messages, and they still
   respect opt-out.
4. The H+7 follow-up task is a **real assignable task with an owner** — not a flag on a report, not a
   notification.
5. The H+14 escalation reaches **a human accountable for the outcome**, and surfaces in the manager
   and owner dashboards (`UCL-020`).
6. **A reminder that fails to send is retried and made visible. It is never silently dropped, and its
   failure never alters the order's state** (`UCL-007`, `NOT-001`).
7. Skipping a stage — because the order was collected first, for instance — records a reason
   (`UCL-021`).

---

## 5. The dashboard

The unclaimed laundry dashboard exposes at minimum all **nine** canonical fields (`UCL-012`):

1. order count
2. customer count
3. held invoices
4. unpaid balance
5. order age
6. outlet
7. last reminder
8. follow-up officer
9. reason not collected

These are a **minimum, not a maximum**. Rules:

- Every figure is **tenant-scoped** (`UCL-019`).
- **Held invoices and unpaid balance are read from the authoritative financial records** — the
  dashboard never recomputes money independently (`UCL-014`, `FIN-023`).
- Cashflow recovery reporting likewise reads from financial records (`UCL-024`).
- The last reminder sent (`UCL-030`) and the assigned follow-up officer (`UCL-029`) are recorded
  fields, not derived guesses.
- The dashboard is an **operational tool**: it must make the next action obvious, per the
  shortest-path UX rule. A dashboard that shows a problem without offering the next step is a report,
  not a recovery tool.

---

## 6. Reason not collected

"Reason not collected" is a **first-class recorded field** captured from staff follow-up (`UCL-011`),
not an optional note. It is the data that actually reduces the pile: a customer who moved away, a
customer waiting on payday, an order the customer believes was already collected, a dispute over
condition, a phone number that no longer works.

Recorded as a `ReasonCode` plus free text, with an actor and a timestamp.

---

## 7. Recovery actions

The product's role is **reminding, escalating, and reporting**. Within that, the permitted recovery
actions are:

- send the next ladder stage;
- create and assign a follow-up task;
- escalate to a manager or owner;
- **propose a delivery** — often the strongest remedy, since laundry a customer will not collect can
  frequently be delivered (`UCL-018`, `DEL-025`). This is human-initiated, never automatic;
- record why the laundry has not been collected;
- close the case when the order is collected or delivered (`UCL-028`).

---

## 8. Absolute prohibition

> **The product NEVER automatically discards, sells, auctions, donates, or transfers ownership of a
> customer's laundry.** (`UCL-013`, `UCL-026`, `UCL-027`)

Stated exhaustively, because this is the rule most likely to arrive as a "reasonable" feature
request:

- No configuration flag enables it.
- No plan tier enables it.
- No escalation level enables it.
- No age enables it — not 30 days, not 90, not a year.
- No unpaid balance enables it.
- No tenant request enables it.
- It is not prototyped, not built behind a feature toggle, not left as a `TODO`, and not recorded as
  a backlog item.

**No command, event, state, or transition representing disposal exists anywhere in this model, and
none may be added.** Disposal of a customer's property is a legal and ethical matter between a
business and its customer. The software's job ends at surfacing the problem, reminding the customer,
escalating to a human, and recording the reason it was never collected.

A proposal to implement any form of automated disposal is **refused outright and escalated to the
repository owner**. Implementing it behind a flag, prototyping it, or leaving it as a comment is
itself a violation.

---

## 9. Tenant rules

- Cases, schedules, tasks, escalations, and every dashboard aggregate are tenant-scoped (`UCL-019`,
  `TEN-015`).
- Aging statistics never cross a tenant boundary. An owner comparing outlets compares **their own**
  outlets, within one tenant.
- A case is opened at most once per order, idempotently (`UCL-015`).

---

## 10. Status

The unclaimed laundry domain is `NOT IMPLEMENTED`. No aging logic, scheduler, ladder, task system, or
dashboard exists. Backend runtime is `ABSENT`. This document claims no test, build, deployment, CI
run, or UAT.

---

## Related documents

- [`ORDER_DOMAIN.md`](ORDER_DOMAIN.md)
- [`NOTIFICATION_DOMAIN.md`](NOTIFICATION_DOMAIN.md)
- [`PICKUP_DELIVERY_DOMAIN.md`](PICKUP_DELIVERY_DOMAIN.md)
- [`PAYMENT_DOMAIN.md`](PAYMENT_DOMAIN.md)
- [`DOMAIN_INVARIANTS.md`](DOMAIN_INVARIANTS.md)
