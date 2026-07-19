# DEC-0006 — Public Tracking Without App Installation

## ID

DEC-0006

## Title

Public Tracking Without App Installation

## Status

ACCEPTED

## Date

19 July 2026

## Context

The single most common interaction between a laundry customer and a laundry business is the question
"sudah selesai belum?" Today it is asked by phone or WhatsApp, and answered manually by a kasir who is
simultaneously serving someone at the counter. It costs the business time on every order and costs the
customer certainty.

The obvious software answer — build an app and tell customers to install it — fails in this market:

- A customer who visits a laundry twice a month will not install an application for it.
- Low-end Android devices have constrained storage, and a laundry app loses that competition.
- The person collecting the laundry is often not the person who dropped it off — a spouse, a child, a
  household helper — and they will certainly not have the app.
- Requiring an account and a password adds a second barrier on top of installation.

Meanwhile the channel that customers genuinely use is WhatsApp, where a link is a native, trusted,
forwardable object.

The counter-consideration is security. A tracking surface with no login is, by construction, publicly
reachable. Getting it wrong means exposing customer names, addresses, and order histories to anyone who
guesses a URL — which would be a privacy failure and, given multi-tenancy, potentially a competitive
intelligence leak.

## Decision

**Order tracking is available publicly, in a browser, with no application installation and no login.**

The Portal Tracking Publik is a canonical platform (§5.4).

**Canonical behaviour:**

1. The customer receives a **secure tracking link**, normally over WhatsApp.
2. **No login is required** for safe information.
3. The link is **shareable via WhatsApp**, so whoever is collecting the laundry can use it.

**Canonical security rules, none of which may be relaxed by any implementation:**

1. The token is **high-entropy**, from a cryptographically secure random source.
2. The token is **stored hashed**; plaintext exists only in the link.
3. The token is **not the order number**. Order numbers are sequential, guessable, and printed on nota;
   they must never grant access.
4. Tokens are **revocable**.
5. Tokens are **expiring**.
6. The portal is served with **`noindex`**.
7. **Personal data is masked.**
8. The portal **never shows the full address**.
9. **Sensitive actions require OTP.**

**Information boundaries:**

- Safe by default: order number, brand and outlet, service type, current status and history, estimated
  completion, amount due and payment state, available customer actions.
- Never shown without OTP verification: full address, full phone number, the customer's other orders,
  internal notes, and laundry photographs.

## Consequences

Tracking-token issuance, hashing, expiry, and revocation become part of the tracking module in Step 7.
The portal is a distinct surface with its own performance budget (§19.2) and is exempt from the
Flutter-everywhere rule (DEC-0004) precisely because cold-start weight matters most there. Rate limiting
and abuse protection on token lookup are mandatory (§15.4). Masking rules become a cross-cutting concern
in the presentation layer rather than a portal-specific afterthought.

## Positive consequences

- Removes the single largest friction point between the customer and the product: nothing to install,
  nothing to remember, nothing to sign up for.
- Directly reduces the manual status-enquiry load on kasir, which is a measured success metric (§29.1).
- Works for whoever actually collects the laundry, not only the person who dropped it off.
- Uses WhatsApp as the delivery channel, which is where Indonesian customers already are (§14).
- Becomes the product's most visible differentiator in a market where competitors offer nothing
  comparable (§30.5).
- Makes the customer application genuinely optional, which is then locked as DEC-0014.

## Negative consequences / trade-offs

- **It is a permanent, unauthenticated, internet-facing attack surface.** This is the cost of the
  benefit, and it is why nine security rules are attached to the decision rather than left to
  implementation judgement.
- **A shared link is a shared capability.** Forwarding is a feature, so anyone with the link sees what
  the link shows. This is mitigated by masking, expiry, and revocation, not eliminated.
- Masked data reduces usefulness: a customer cannot confirm the delivery address from the portal without
  OTP verification, which adds friction to exactly the case where they most want to check.
- Expiring tokens produce a support case when a customer returns to an old link, and the expiry window is
  a genuine tradeoff between security and convenience.
- The portal cannot be personalised or upsold in the way an authenticated app can, which forgoes a
  commercial opportunity deliberately.
- Maintaining a second, potentially non-Flutter, stack for this surface adds technology surface area
  (DEC-0004).

## Verification

- Step 7 tests assert: tokens are high-entropy; only a hash is stored; a token derived from or equal to
  an order number is rejected; expired tokens are refused; revoked tokens are refused immediately.
- Tests assert the portal response contains a `noindex` directive.
- Tests assert that no response body contains a full address, a full phone number, another order
  belonging to the same customer, an internal note, or a photograph URL.
- Tests assert that sensitive actions require OTP verification.
- Rate-limit tests assert that token enumeration is throttled.
- Performance evidence for the portal is measured on a low-end Android device over a constrained network
  (§19.2).
- At the Step 0 baseline: the tracking portal is `NOT IMPLEMENTED`.

## Supersession policy

Superseded only by a decision record that explains why public tracking is no longer offered and what
replaces it for customers who will not install an application. **Individual security rules may not be
relaxed by implementation choice**; weakening any of the nine rules requires its own decision record
stating the residual risk and the owner's acceptance of it. Requires at least a **minor** version bump of
[`../MASTER_SOURCE.md`](../MASTER_SOURCE.md).

## Related Master Source sections

- §2 Vision
- §5 Platforms
- §9 Public tracking portal
- §15 Security
- §17 Privacy
- §19 Performance
- §30 Positioning
