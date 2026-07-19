# DEC-0010 — No Lifetime Cloud Subscription

## ID

DEC-0010

## Title

No Lifetime Cloud Subscription

## Status

ACCEPTED

## Date

19 July 2026

## Context

Lifetime deals are common in the Indonesian software market, especially for UMKM tools, and they are
persuasive. A customer pays once — perhaps ten or fifteen times the monthly price — and receives the
product "selamanya". For a small business owner comparing recurring cost against a single payment, it is
an attractive proposition, and it generates immediate cash for the vendor.

It is also, for a cloud product, a promise that cannot be kept.

Aish Laundry App incurs a recurring, unavoidable, per-tenant cost for as long as a tenant exists:
PostgreSQL storage that only grows, object storage for laundry photographs and delivery proofs that only
grows, Redis, compute, backups (§15.6), and the engineering cost of security patching. A one-time payment
against a permanently recurring cost is not sustainable and must never be offered. It has exactly three
possible endings, none of them acceptable:

1. the vendor eventually stops honouring the deal and the customer's "lifetime" ends;
2. the vendor degrades the lifetime tier until it is unusable, honouring the letter and breaking the
   spirit;
3. the vendor subsidises lifetime customers from recurring customers, which is unfair to the people
   paying properly.

There is a fourth ending that vendors do not advertise: the product shuts down because the revenue model
never covered the cost, and every customer loses the system their business runs on.

Pressure to offer a lifetime deal will be real, particularly during the pilot (Step 14) when early
revenue is scarce.

## Decision

**Aish Laundry App does not offer a lifetime cloud subscription. Ever.**

1. No lifetime plan, no perpetual cloud licence, no "bayar sekali, pakai selamanya" offer, under any
   name, at any price, on any channel.
2. No time-limited lifetime promotion, no founder tier, no early-adopter perpetual deal.
3. Subscriptions are monthly or annual only, per the locked pricing in DEC-0009.
4. Discounts are expressed as annual pricing — Starter Rp790.000/tahun, Growth Rp1.990.000/tahun, Scale
   Rp3.990.000/tahun — which remain recurring commitments with an honest discount.
5. Marketing never uses the word "selamanya", "lifetime", or "perpetual" to describe cloud access.

This is guardrail 1 of the pricing guardrails in §21.4.

## Consequences

The subscription module in Step 12 has no lifetime plan type, so the offer cannot be created by
configuration. Commercial material and sales conversations are constrained accordingly. Cash-flow
strategy relies on annual plans rather than perpetual sales, which produces less money at launch and more
predictable revenue afterwards.

## Positive consequences

- The product only promises what it can deliver indefinitely, which is the honesty value (§3.1) applied
  to the commercial model.
- Recurring revenue funds the recurring cost, which is the only structure under which the product can
  still be running in five years — the outcome that actually matters to a laundry owner who has put their
  entire operation into it.
- No cohort of customers is subsidised by another.
- Removes the incentive to degrade a legacy tier later, which is how lifetime deals usually end.
- Protects the security guardrails: a vendor squeezed by unfunded lifetime obligations is a vendor
  tempted to sell backup and isolation as premium add-ons (§21.4).
- Removes a recurring commercial argument by settling it once, publicly and permanently.

## Negative consequences / trade-offs

- **Lower revenue at launch.** Lifetime deals generate cash exactly when a new product needs it most, and
  refusing them is genuinely costly during the pilot.
- **Competitive disadvantage against vendors who do offer them.** Some prospects will choose a lifetime
  competitor on price alone, and some of those prospects will be lost permanently.
- **Harder sales conversations.** "Kenapa tidak ada paket selamanya?" requires an explanation about
  recurring costs that a busy owner may not want to hear.
- **The rationale is publicly visible** (AMENDMENT-0001), so competitors can read this record and position
  against it.
- **Annual plans are a weaker cash-flow instrument** than perpetual sales, particularly in the first year.
- Refusing even a limited-time founder tier forgoes a common and effective early-adopter incentive.

## Verification

- `scripts/verify-step-00.sh` asserts that the guardrail appears in
  [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §21.4.
- Review rejects any document, pricing surface, or marketing copy offering lifetime, perpetual, or
  "selamanya" cloud access.
- Step 12: the subscription model supports monthly and annual plan types only; there is no lifetime plan
  type to configure, and a test asserts this.
- Step 14: pilot commercial material is reviewed against this record before launch.

## Supersession policy

Superseded only by a decision record that demonstrates how a lifetime offer would be funded for its
entire lifetime — including storage growth, backup, and security maintenance — and states explicitly what
happens to lifetime customers if the product is discontinued. **A supersession motivated by short-term
cash pressure is not acceptable and must be refused.** Requires at least a **minor** version bump of
[`../MASTER_SOURCE.md`](../MASTER_SOURCE.md).

## Related Master Source sections

- §3 Product values
- §21 Pricing
- §23 Non-goals
- §29 Success metrics
- §30 Positioning
