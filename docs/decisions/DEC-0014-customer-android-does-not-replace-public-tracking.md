# DEC-0014 — Customer Android Does Not Replace Public Tracking

## ID

DEC-0014

## Title

Customer Android Does Not Replace Public Tracking

## Status

ACCEPTED

## Date

19 July 2026

## Context

DEC-0006 established public tracking with no application installation as a core differentiator. Step 11
then delivers the Aish Laundry Customer Android application, with login, order history, saved addresses,
invoices, loyalty, and feedback.

Once both exist, a predictable pressure appears — the same pressure that has degraded countless consumer
products. The application is measurable: installs, active users, session length, retention. The portal is
not. The application supports loyalty and future engagement features; the portal does not. Someone will
observe that pushing customers into the application improves every metric anyone is tracking.

The degradation then happens gradually and always for defensible-sounding reasons:

1. A new tracking detail is added to the app only, "because the portal is limited".
2. The portal gains an interstitial suggesting the app.
3. The portal's information is trimmed "for security", with the app offered as the full-featured
   alternative.
4. The portal becomes a landing page whose real purpose is app installation.

At the end of that path, the product has abandoned its most valuable differentiator — the one that
removes all friction for the customer — in exchange for an install metric.

The market reality has not changed at any point along that path: a customer visiting a laundry twice a
month will not install an application, and the person collecting the laundry is frequently a family
member who is not the account holder.

## Decision

**The Aish Laundry Customer Android application enhances the public tracking portal. It never replaces
it.**

1. **Installation is always optional.** A customer who never installs anything receives full tracking
   through the portal.
2. **Any capability a customer genuinely needs in order to follow their laundry must be reachable from
   the portal.** Status, history, estimated completion, amount due, payment state, and available actions
   are portal capabilities, not app-exclusive ones.
3. **The portal is never degraded to drive installation.** No removal of useful information, no
   artificial limitation, no interstitial or modal whose purpose is to push the application.
4. **The portal is never gated behind an install prompt**, a download wall, or an account requirement.
5. **App installs are not a success metric** (§29.4). The product does not optimise for installs,
   time-in-app, or notification volume.
6. **App-exclusive features are permitted** where they genuinely require an account: order history across
   time, saved addresses, invoices, loyalty, and feedback. These are enhancements, not tracking.
7. **Tracking links continue to work in a browser** even for customers who have the application
   installed. A link is never made app-only.

## Consequences

Step 11 planning treats the customer application as an additive surface, and its Definition of Done
includes an explicit verification that no portal capability regressed. Feature decisions that would move
a tracking capability into the application require this record to be revisited. Product metrics
deliberately exclude installs as a success measure, which changes what Step 14's pilot review looks at.

## Positive consequences

- Protects the product's most valuable differentiator from the most likely internal threat to it — its own
  success metrics.
- Keeps the zero-friction path available to the customer who will never install anything, which is the
  majority of the target market.
- Continues to serve the person who actually collects the laundry, who is often not the account holder.
- Keeps the promise made in positioning (§30.5) truthful: tracking genuinely requires no installation, not
  "requires no installation for a limited subset".
- Removes an entire category of dark-pattern pressure by settling it before the application exists.
- Lets the customer application be judged on genuine value — loyalty, history, convenience — rather than
  on artificial scarcity created elsewhere.

## Negative consequences / trade-offs

- **Forgoes install-driven growth.** Aggressive portal-to-app funnels work, and refusing them means fewer
  installs and a weaker direct channel to customers.
- **Duplicated effort.** Tracking capability must be maintained on two surfaces, in two stacks, and every
  tracking improvement must be considered for both.
- **Limits the customer application's strategic value.** Without exclusive access to the core use case,
  the app must earn its place, and it may see modest adoption.
- **Constrains future engagement and marketing features** that would depend on a large installed base.
- **The portal's security constraints become a product ceiling.** Because the portal must remain useful
  and the portal masks personal data (DEC-0006), some capabilities can be offered nowhere convenient —
  the app could show them but the rule prevents making them app-exclusive necessities.
- **Ongoing enforcement cost.** Every future product decision touching either surface must be checked
  against this record.

## Verification

- Step 11 Definition of Done includes an explicit check that no portal capability was removed, reduced,
  or gated during the Step.
- Tests assert that the portal continues to expose status, history, estimated completion, amount due, and
  payment state without authentication.
- Tests assert that a tracking link resolves in a browser regardless of whether the application is
  installed.
- Review rejects any portal interstitial, modal, or banner whose primary purpose is application
  installation.
- Product metrics reviewed at Step 14 exclude installs as a success measure (§29.4).
- At the Step 0 baseline: both the customer application and the tracking portal are `NOT IMPLEMENTED`.

## Supersession policy

Superseded only by a decision record demonstrating, with pilot evidence, that the public tracking portal
is not used by customers — which would contradict the core premise of DEC-0006 and would itself require
that record to be revisited. **Supersession motivated by install metrics, engagement metrics, or
monetisation of the customer application is not acceptable.** Requires at least a **minor** version bump
of [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md).

## Related Master Source sections

- §2 Vision
- §5 Platforms
- §9 Public tracking portal
- §22 MVP
- §29 Success metrics
- §30 Positioning
