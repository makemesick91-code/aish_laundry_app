# DEC-0011 — Transparent Third-Party Messaging Costs

## ID

DEC-0011

## Title

Transparent Third-Party Messaging Costs

## Status

ACCEPTED

## Date

19 July 2026

## Context

WhatsApp is central to Aish Laundry App. It carries the tracking link that makes DEC-0006 work, the
status updates that reduce counter enquiries, the pickup and delivery coordination of DEC-0007, and the
entire H+1 / H+3 / H+7 / H+14 reminder ladder of DEC-0008. Without WhatsApp, the product's three
differentiators lose their delivery channel.

WhatsApp messaging through an official Business provider costs real money per message or per
conversation. Those costs are set by the provider and by Meta, change without the product's involvement,
and scale directly with a tenant's order volume — a busy Scale-plan laundry sending order, ready,
reminder, and delivery messages generates far more messaging cost than a quiet Starter-plan outlet.

Two dishonest patterns are common in this market:

1. **"Unlimited WhatsApp"** — advertised as included, then quietly rate-limited, throttled, or
   restricted once real volume arrives. The promise is impossible; only the discovery is delayed.
2. **Bundled and hidden** — messaging cost folded into the subscription without disclosure, forcing the
   vendor either to price defensively high for everyone or to discourage the very messaging that makes
   the product valuable.

The second pattern creates a direct conflict with the product's design: if every reminder costs the
vendor money invisibly, the vendor is incentivised to send fewer reminders — which would gut DEC-0008.

## Decision

**Third-party messaging costs are transparent and billed separately from the subscription.**

1. **WhatsApp provider fees are billed separately** from the plan price (§21.4 guardrail 8).
2. **Provider costs are shown transparently** to the tenant: what was sent, in what class, and what it
   cost.
3. **Never promise fake "unlimited WhatsApp."** No plan, no marketing material, and no sales conversation
   describes messaging as unlimited.
4. **Provider abstraction** is required so the product is not captive to one vendor's pricing (§14.1).
5. **Message deduplication is required**, so a retry or queue replay never bills a tenant twice for one
   customer-visible message.
6. **Transactional and marketing messages are separated**, so a tenant can see and control what drives
   its cost.
7. **Quiet hours and opt-out** are honoured, which reduces both nuisance and spend (§14.1).
8. **The manual deep-link fallback** is always available, giving a tenant a zero-provider-cost path at the
   expense of staff effort — and it is presented honestly as a fallback, never disguised as automation.
9. **WhatsApp failure never cancels an order** (§14.1 rule 8), so messaging cost decisions never
   compromise the order lifecycle.

## Consequences

Notification dispatch records the class, the provider, the outcome, and the cost of every message
(Step 7), and the subscription module surfaces messaging spend per tenant per period (Step 12).
Deduplication becomes a billing correctness requirement as well as a customer-experience one. Provider
delivery outcomes and cost per active outlet become monitored signals (§20.3, §29.3).

## Positive consequences

- The tenant can see exactly what messaging costs and can control it by adjusting which optional
  notifications are enabled.
- The subscription price stays low and predictable — Starter at Rp79.000/bulan does not have to carry a
  defensive messaging buffer for the heaviest possible user.
- Removes the perverse incentive to under-send reminders, protecting the H+1/H+3/H+7/H+14 ladder that is
  a core differentiator.
- Provider abstraction preserves the ability to change provider when pricing or reliability changes.
- Honesty about a real cost builds the credibility the product depends on (§3.1, §3.7).
- The manual fallback means a tenant unwilling to pay provider fees still has a usable path.

## Negative consequences / trade-offs

- **Total cost of ownership is less predictable** for the customer. "Rp79.000 plus messaging" is a harder
  proposition than a single number, and some prospects will prefer a competitor's simpler-looking bundle.
- **Competitive disadvantage against "unlimited WhatsApp" claims**, which will look better on a comparison
  table right up to the moment they are enforced.
- **Additional billing complexity**: metering, attribution per tenant, provider reconciliation, and
  currency handling are real engineering work in Step 12.
- **Cost anxiety may suppress good behaviour.** A tenant watching a per-message cost may disable reminders
  that would have recovered more money than they cost. Reporting must show recovery value alongside
  messaging spend to counter this.
- **The product is exposed to provider price changes** it does not control, and must pass them through
  honestly — which means occasionally delivering unwelcome news.
- Separate billing lines require clearer invoicing and more support explanation than a single flat fee.

## Verification

- `scripts/verify-step-00.sh` asserts the guardrail text in [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md)
  §14 and §21.4.
- Review rejects any material describing messaging as unlimited.
- Step 7 tests assert: messages dispatch through the provider abstraction, not a vendor SDK directly;
  deduplication prevents a repeat send under retry; quiet hours defer non-urgent messages using outlet
  local time; opt-out is honoured; a provider failure does not affect order state.
- Step 12 tests assert that messaging spend is attributed per tenant and reported separately from the
  subscription charge.
- Step 13: provider delivery outcomes and costs are monitored signals (§20.3).
- At the Step 0 baseline: notifications and WhatsApp are `NOT IMPLEMENTED`.

## Supersession policy

Superseded only by a decision record that specifies the replacement commercial treatment of messaging
cost and demonstrates it remains honest — in particular that it does not create an incentive to suppress
reminders and does not reintroduce an unlimited claim. **The prohibition on promising unlimited
messaging is not subject to supersession by marketing preference.** Requires at least a **minor** version
bump of [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md).

## Related Master Source sections

- §3 Product values
- §14 Notifications and WhatsApp
- §11 Unclaimed laundry
- §20 Observability
- §21 Pricing
- §29 Success metrics
