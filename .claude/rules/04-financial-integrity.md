# Rule 04 — Financial Integrity

## Purpose

Aish Laundry App handles cash at the counter, courier cash collection, digital payments, invoices,
and shift closing for small businesses whose owners reconcile by hand. Money that does not add up
destroys trust faster than any missing feature. Financial correctness is a hard gate, equal in
severity to tenant isolation.

Backed by **DEC-0012 — Tenant Isolation and Financial Integrity Hard Gate**.

## Hard rules

1. **Money is stored as integer Rupiah.** The smallest unit is one Rupiah; there are no sub-Rupiah
   amounts in stored balances.
2. **Floating point is forbidden for financial transactions.** No `float`, no `double`, no binary
   floating-point arithmetic anywhere in pricing, totals, discounts, taxes, payments, refunds, or
   reconciliation. Rounding rules must be explicit and applied at a defined point, not left to a
   language's default numeric behaviour.
3. **Payments are idempotent.** A retried payment request must never produce a second payment.
   Idempotency is keyed on a stable client-supplied reference (see `client_reference`, Rule 07).
4. **Gateway callbacks are verified server-side** — signature/authenticity checked, amount and
   currency checked against the expected order, replay rejected.
5. **Never mark an order paid on a client claim.** Payment state changes originate from a verified
   server-side event or an authorized in-person action recorded by an authenticated staff member.
6. **Refund and void require permission and a reason.** Both are recorded with actor, timestamp,
   amount, and reason text. A refund is never a silent operation.
7. **Financial transactions are never deleted through ordinary UI.** There is no "delete payment"
   button for regular roles.
8. **Corrections are made via reversal or adjustment entries**, preserving the original record. The
   ledger is append-only in effect: history is added to, never rewritten.
9. **Historical order prices are immune to price-list changes.** An order captures the price that
   applied when it was created. Editing the master price list never retroactively changes a past
   order's total or a past invoice.
10. **Shift closing compares expected cash against actual cash**, records the variance explicitly,
    and requires the variance to be acknowledged rather than hidden.
11. **Courier cash is reconciled** — cash collected on delivery is tracked from collection to
    handover, per courier, per shift (see Rule 09).
12. **Financial integrity failure is an automatic NO-GO.**

## Supporting expectations

- Every financial record is tenant-scoped and carries `tenant_id` (Rule 02). A financial query that
  is not tenant-scoped is simultaneously a security and a financial defect.
- Totals are computed and authoritative on the server. Client-computed totals are display only.
- Concurrent operations on the same order or payment must be serialized (database transaction or
  distributed lock) so double submission cannot create double payment.
- Financial audit trails record actor, tenant, outlet, timestamp, before/after amounts, and reason.
- Money is never inferred from a display string. Formatting for Rupiah presentation is a view
  concern applied to an integer value.

## Testing expectation (later steps)

When payment functionality is built (Step 5 and beyond), the Definition of Done requires explicit
tests for: retry idempotency, duplicate callback replay, offline queue replay after reconnect,
refund permission enforcement, price-list change not affecting historical orders, and shift-closing
variance calculation. Absence of these tests blocks the step.

## Step 0 note

No payment code, ledger, schema, or gateway integration exists. Step 0 records these rules only.
Creating any payment implementation in Step 0 is forbidden by the Step 0 scope guard.

## Violation handling

- **Any discrepancy in stored money, duplicated payment, lost payment, or unexplained balance
  change** — automatic **NO-GO**. Stop, preserve evidence at the exact SHA, notify the repository
  owner, and do not ship until root cause is understood and covered by a regression test.
- **Floating point found in a financial path** — the change is rejected outright; no "it works in
  practice" exemption.
- **A hard-delete path for a financial record** — remove it and replace with reversal/adjustment.
- **A payment marked paid from a client claim** — treat as a critical security and financial defect.
- Never mask a reconciliation variance, auto-round it away, or suppress it from a report. Report the
  variance honestly; a visible discrepancy is a feature, a hidden one is fraud-shaped.
