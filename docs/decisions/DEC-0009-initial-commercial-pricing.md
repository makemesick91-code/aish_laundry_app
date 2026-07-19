# DEC-0009 — Initial Commercial Pricing

## ID

DEC-0009

## Title

Initial Commercial Pricing

## Status

ACCEPTED

## Date

19 July 2026

## Context

Pricing had to be settled at the foundation rather than at launch, for three reasons.

First, **price determines architecture**. A product sold at Rp79.000 per month cannot afford a dedicated
database per customer; that constraint is what forced the shared-database multi-tenancy of DEC-0002 and
the single-deployment modular monolith of DEC-0005. Deciding pricing after building would risk an
architecture the business cannot afford to run.

Second, **price determines scope**. Fair-use limits on outlets, staff, and monthly orders define what the
subscription and platform administration module must enforce (Step 12).

Third, **price is a commitment that must not drift**. Autonomous agents writing marketing copy, pricing
pages, and plan-limit code across many sessions will invent numbers unless the numbers are locked and
reproduced exactly.

The market context is Indonesian laundry UMKM. The reference points are informal: an owner comparing the
subscription against a fraction of one staff member's daily wage, and against the value of a single
recovered uncollected order.

## Decision

The following pricing is **locked**. Figures are reproduced exactly and may only change through a new
decision record.

### Trial

**Trial: 14 hari gratis**

### Monthly plans

| Plan | Price | Limits |
| --- | --- | --- |
| Starter | **Rp79.000/bulan** | 1 outlet, 5 staff, hingga 1.000 order/bulan fair-use |
| Growth | **Rp199.000/bulan** | hingga 3 outlet, 20 staff, hingga 5.000 order/bulan |
| Scale | **Rp399.000/bulan** | hingga 10 outlet, 75 staff, hingga 20.000 order/bulan |
| Enterprise | **mulai Rp999.000/bulan** | negotiated |

### Annual plans

| Plan | Annual price |
| --- | --- |
| Starter | **Rp790.000/tahun** |
| Growth | **Rp1.990.000/tahun** |
| Scale | **Rp3.990.000/tahun** |

### Guardrails

1. No lifetime cloud plan (DEC-0010).
2. No per-nota fee on normal plans.
3. Transparent provider costs (DEC-0011).
4. The security baseline is not locked behind expensive plans.
5. Tenant isolation is not an add-on.
6. Backup is not a premium security add-on.
7. Pricing changes require a decision record.
8. WhatsApp provider fees are billed separately.
9. Tenant data remains exportable per policy when a subscription lapses.

### Fair use

Order limits are fair-use ceilings. Exceeding one triggers a conversation and a plan recommendation. It
does not silently degrade service, does not delete data, and does not stop a laundry operating mid-shift.

## Consequences

Subscription and plan-limit enforcement in Step 12 implements exactly these numbers. Marketing copy,
onboarding, and any pricing surface reproduce them exactly rather than paraphrasing. The annual prices
represent ten months' cost for twelve months' service, and that ratio is a deliberate, locked
relationship. Because the repository is PUBLIC (AMENDMENT-0001), these figures and the reasoning behind
them are publicly visible (§21.6).

## Positive consequences

- An entry price low enough that a single-outlet laundry can decide without deliberation, which suits a
  14-day trial and self-service onboarding.
- A ladder that grows with the customer: adding outlets and staff is the natural trigger to move up,
  aligning revenue with the customer's own growth.
- Annual plans improve cash flow and retention while giving the customer a clear, honest discount.
- The guardrails prevent a whole class of predatory patterns — security paywalls, per-transaction fees,
  hidden messaging costs — before commercial pressure can introduce them.
- Locking the numbers makes them mechanically checkable, so no agent can invent a price.
- Explicit fair-use ceilings, rather than hard cut-offs, mean the product never stops a working laundry.

## Negative consequences / trade-offs

- **Rp79.000 is a low entry price** and imposes a permanent constraint on infrastructure cost per tenant.
  Every architectural decision must respect it.
- **Locking prices before any customer exists** means they are set without market evidence. They may prove
  too low, too high, or wrongly shaped, and correcting them requires a decision record and a public
  change.
- **Public visibility** means competitors can see the full commercial ladder immediately (§21.6). This was
  accepted as a consequence of AMENDMENT-0001.
- **Fair use is softer than a hard limit** and is therefore harder to enforce and easier to abuse. It
  will occasionally be exploited; the alternative — cutting off a laundry mid-shift — is worse.
- **Excluding WhatsApp provider fees** makes the total cost of ownership less predictable for a customer,
  even though the exclusion is what makes the pricing honest (DEC-0011).
- **The security guardrails forgo revenue.** Backup, isolation, and the security baseline are common
  upsells in comparable products and are deliberately not sold here.

## Verification

- `scripts/verify-step-00.sh` asserts that [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §21 reproduces
  every figure exactly.
- Review rejects any document, pull request, or user-facing copy that paraphrases or alters a price.
- Step 12 tests assert that plan limits in code match this record exactly — outlets, staff, and monthly
  order ceilings per plan.
- Step 12 tests assert that exceeding a fair-use ceiling does not halt operations or delete data.
- Step 12 tests assert that a lapsed subscription preserves data exportability.
- Step 14 measures trial-to-paid conversion and plan distribution against real pilot data (§29.3).

## Supersession policy

Superseded only by a new decision record that states the complete replacement price list exactly,
explains the commercial reason, states the effect on existing subscribers including any grandfathering,
and confirms that every guardrail in this record still holds. Prices are never changed by editing this
record or by editing §21 alone. Requires at least a **minor** version bump of
[`../MASTER_SOURCE.md`](../MASTER_SOURCE.md).

## Related Master Source sections

- §21 Pricing
- §12 Owner dashboard and portfolio
- §22 MVP
- §29 Success metrics
- §30 Positioning
