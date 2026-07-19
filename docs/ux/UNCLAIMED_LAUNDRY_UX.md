# Unclaimed Laundry UX (Cucian Menumpuk)

**Surfaces:** Console Web `SCR-CON-014` · Ops Android follow-up actions
**Roadmap step:** Step 9 — Unclaimed Laundry and Cashflow Recovery
**Step 2 status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED · **Backend runtime:** ABSENT

> **Documentation is not implementation.** No aging calculation, no reminder scheduler, no dashboard,
> and no follow-up task system exists.

Accessibility posture: **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**

---

## 1. The problem

Finished orders pile up. Shelf space disappears. The money owed on those orders is never collected.
The owner finds out when the shelf is full. This is a **core product differentiator**, not a
reporting nicety.

The product's role is precisely three things: **remind, escalate, report.** It ends there.

---

## 2. The absolute prohibition — stated first

> **The system NEVER automatically discards, sells, auctions, donates, or transfers ownership of a
> customer's laundry.**

Not after H+14. Not after H+30. Not with an unpaid balance. Not at a tenant's request. Not behind a
feature flag. Not as a prototype. Not as a TODO.

These are legal questions belonging to the tenant and its customer, not decisions a SaaS product may
automate. No screen, no bulk action, no scheduled job, and no backlog item in this product implements
or suggests them. Any future policy in this area would require an accepted decision record and
explicit owner approval, and it is out of scope for the entire current roadmap.

---

## 3. The aging anchor

> **Aging starts when an order FIRST reaches `READY_FOR_PICKUP`** (`UCL-001`).

| Rule | Consequence for the interface |
|---|---|
| "First" is literal | The dashboard displays the **first-ready timestamp**, not the most recent one |
| The clock **never restarts** | An order that went to `REWORK` and reached `READY_FOR_PICKUP` again keeps its original anchor. The interface shows the original date and, where rework occurred, says so without changing the anchor |
| The timestamp is written once and is **immutable** | No screen offers a way to edit it. Not to a manager, not to an owner, not to platform support |
| Aging is computed against **outlet local time** / Asia/Jakarta business days | Not against an arbitrary UTC midnight. The displayed age matches what the outlet experiences |

The dashboard shows both the anchor date and the derived age, so a manager can see the arithmetic
rather than trust it.

---

## 4. The reminder ladder

Exactly four stages. Adding, removing, or renumbering one requires a decision record.

| Stage | Action | Tone | Interface obligation |
|---|---|---|---|
| **H+1** | Friendly reminder | Warm, brief, assumes the customer simply forgot | Shown as `Pengingat H+1 terkirim` with timestamp |
| **H+3** | Second reminder | Neutral, adds the balance and the outlet's hours | Shown as `Pengingat H+3 terkirim` |
| **H+7** | Priority reminder **plus a follow-up task** | Direct, names the balance and asks for a collection date | Creates a **real assignable task with an owner** — never merely a flag on a report |
| **H+14** | **Escalation to manager / owner** | Operational, internal | Surfaces in the manager and owner dashboards (`UCL-020`) |

### Copy must differ at each stage

The three customer-facing stages are **written differently**. Sending the same sentence three times
trains the customer to ignore it.

| Stage | Copy intent (Bahasa Indonesia, illustrative — final copy is Step 9) |
|---|---|
| H+1 | "Cucian Anda sudah siap diambil di Outlet Cempaka. Kami tunggu, ya." |
| H+3 | "Cucian Anda masih menunggu di Outlet Cempaka. Sisa pembayaran Rp54.000. Buka 08:00–20:00." |
| H+7 | "Cucian Anda sudah 7 hari menunggu di Outlet Cempaka. Sisa pembayaran Rp54.000. Kapan kira-kira bisa diambil? Bisa juga kami antar." |

H+7 offers **delivery as a recovery action** (`UCL-018`, `DEL-025`), because converting a pile into a
delivery solves the shelf and the cash at once.

### H+14 is operational escalation, not punitive automation

H+14 raises the case to a human who is accountable — a manager or the owner. It does **not**:

- send a threatening message;
- apply a penalty automatically;
- change the order's status to anything punitive;
- start any disposal, sale, or transfer process, because none exists;
- close the case on the customer's behalf.

It creates visibility and an owner. That is the whole of it.

### Ladder mechanics

1. **Each stage fires at most once per order.** Deduplication is mandatory across retries, queue
   replays, and scheduler restarts.
2. Reminders respect **quiet hours 20.00–08.00 outlet local time** and customer **opt-out**
   (`UCL-006`, `NOT-003`). A reminder queued in the quiet window is **deferred to the next permitted
   window**, not dropped and not sent anyway.
3. A reminder that fails to send is retried under a bounded policy and made **visible**. It is never
   silently dropped, and **its failure never alters the order's state**.
4. The dashboard records and shows **the last reminder sent** (`UCL-030`).

---

## 5. The dashboard — required fields

`SCR-CON-014` must expose at least these, all tenant-scoped (`UCL-019`):

| # | Field | Format and rule |
|---|---|---|
| 1 | **Aging bucket** | H+1 · H+3 · H+7 · H+14, derived from the immutable first-ready anchor |
| 2 | **Order count** | Per bucket and total |
| 3 | **Customer count** | Distinct customers, per bucket — a customer with four orders is one customer |
| 4 | **Outstanding balance** | Integer Rupiah, `Rp54.000`, read from the authoritative financial records |
| 5 | **Paid amount** | Integer Rupiah — an order can be fully paid and still uncollected, and that is a different problem |
| 6 | **Reminder state** | Which stages have fired, which are pending, which failed |
| 7 | **Last response** | The customer's most recent reply or contact outcome |
| 8 | **Assigned follow-up owner** | A named person, not a queue. Empty is a visible gap, not a blank cell |
| 9 | **Promise to collect** | A date the customer committed to, captured from follow-up |
| 10 | **Dispute** | Flagged with a reason where the customer contests the order or the balance |
| 11 | **Converted to delivery** | Whether the case was resolved by scheduling a delivery |

Supporting fields carried alongside: outlet, brand, order reference (`AL-2026-000123`), customer name,
order age in days, and **reason not collected** — a first-class captured field, because it is the data
that actually reduces the pile.

**Money rules:** outstanding balance and paid amount are integer Rupiah and are read from the
authoritative financial records. This dashboard **never recomputes money independently**.

---

## 6. Storage fee

> **Storage fee is OPTIONAL / TENANT-CONFIGURED / SUBJECT TO POLICY / NOT ASSUMED ACTIVE.**

| Rule | Detail |
|---|---|
| Default | **Off.** No tenant has a storage fee unless they deliberately configure one |
| Configuration | A tenant-level policy, set explicitly, not inherited from a template or a default plan |
| Display | Where inactive, the dashboard shows no storage-fee column at all — not a zero, which would imply the mechanism is running |
| Legality | Whether a storage fee is lawful and enforceable is **the tenant's question**, not the product's. The interface says so where the policy is configured |
| Money | If active, it is a financial transaction: integer Rupiah, idempotent, never hard-deleted, corrected by reversal or adjustment, audited |
| Customer communication | A storage fee is never introduced to a customer for the first time in a reminder message; the tenant's own terms govern |
| Never | A storage fee is never applied automatically to an order that predates the policy, and never accrued silently |

---

## 7. Making the next action obvious

The dashboard is an **operational tool**, not a report. Each row surfaces the next action:

| Situation | Next action offered |
|---|---|
| No reminder sent yet, H+1 due | *Kirim pengingat* (or confirm the scheduler will) |
| H+7 reached, no follow-up owner | *Tetapkan petugas tindak lanjut* |
| Customer promised a date that has passed | *Hubungi kembali* |
| Customer unreachable across all stages | *Catat alasan tidak diambil* |
| Customer willing but cannot come | *Jadwalkan pengantaran* — the recovery path |
| Customer disputes the order or balance | *Buka sengketa* with a reason |
| Collected | *Tutup kasus* with a recorded outcome (`UCL-028`, `UCL-022`) |

Case closure always records the outcome **and its reason**. A case is never closed silently, and
never closed by the passage of time.

---

## 8. Escalation view

The manager and owner views (`UCL-020`) show the H+14 bucket first, with: order age, outstanding
balance, reminder history, follow-up owner, last response, and the recommended recovery action. The
aging distribution is a **monitored operational signal** (`UCL-025`) shown as a trend, so an owner can
see whether the pile is growing or shrinking.

In Portfolio Mode, the aging distribution appears as a **per-tenant aggregate** — never as a
cross-tenant customer list.

---

## 9. Accessibility

- Aging buckets carry **text labels**, never colour alone. H+14 is not "the red one".
- The table scrolls inside its own container; the page body never scrolls horizontally.
- Every column has a proper header; sorting state is announced.
- Money figures include their currency in the accessible name, so `Rp54.000` is not read as a bare
  number.
- The table survives large font scaling by reflowing to stacked cards on the compact breakpoint,
  keeping all eleven required fields.

**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**

---

## 10. What this feature must never do

1. Restart the aging clock (the first-ready timestamp is immutable).
2. Fire a ladder stage twice for the same order.
3. Send a reminder inside quiet hours without an explicitly recorded exception.
4. Send a reminder to a customer who opted out.
5. Change an order's state because a reminder failed to send.
6. Recompute money outside the authoritative financial records.
7. Ship a dashboard missing any required field.
8. Assume a storage fee is active.
9. Close a case without a recorded outcome and reason.
10. **Discard, sell, auction, donate, or transfer ownership of customer laundry — in any form, ever.**

---

## 11. Related documents

- [`./CONSOLE_WEB_UX.md`](./CONSOLE_WEB_UX.md)
- [`./journeys/UNCLAIMED_LAUNDRY_JOURNEYS.md`](./journeys/UNCLAIMED_LAUNDRY_JOURNEYS.md)
- [`./SCREEN_INVENTORY.md`](./SCREEN_INVENTORY.md)
- [`./UX_ACCEPTANCE_CRITERIA.md`](./UX_ACCEPTANCE_CRITERIA.md)
- [`./UX_STATE_MODEL.md`](./UX_STATE_MODEL.md)

## 12. Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| Aging calculation | **NOT IMPLEMENTED** |
| Reminder ladder | **NOT IMPLEMENTED** |
| Follow-up task system | **NOT IMPLEMENTED** |
| Unclaimed laundry dashboard | **NOT IMPLEMENTED** |
| Backend runtime | **ABSENT** |

`GO` is conferred by the repository owner and is never self-declared.
