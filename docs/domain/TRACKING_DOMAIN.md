# Customer Tracking Domain — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** `NOT IMPLEMENTED` (documentation only)
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.1.0
**Decision records:**
[DEC-0006](../decisions/DEC-0006-public-tracking-without-app-installation.md),
[DEC-0014](../decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md)
**Lifecycle:** [`../state-machines/TRACKING_ACCESS_LIFECYCLE.md`](../state-machines/TRACKING_ACCESS_LIFECYCLE.md)

The Portal Tracking Publik lets a customer see their order without installing anything and without
creating an account. It is the product's most visible differentiator **and its most exposed attack
surface**. Both facts govern this design.

---

## 1. Scope

Owns: tracking token issuance, resolution, throttling, OTP challenge, revocation, expiry, and the
**public tracking projection**.

Does not own: the order itself, any money, or any personal data of record.

---

## 2. The separate projection

> **The public tracking projection is a SEPARATE read model from the internal order
> representation.** (`TRK-008`)

This is a structural decision, not a rendering preference.

- The projection is **built** from the order, carrying only the enumerated safe field set. Masking is
  applied **at build time**, not at render time, so a rendering bug cannot leak a full value
  (`TRK-018`).
- The safe field set is an **allow-list**. **A field not enumerated is not served** (`TRK-028`).
  There is no "hide this field in the template" path, because a deny-list fails open the moment
  someone adds a field.
- Merging this projection into the internal order representation is forbidden. See
  [`CONTEXT_MAP.md`](CONTEXT_MAP.md) §6.

### 2.1 Safe fields (may be shown without OTP)

Order number; brand and outlet identity; service type; current status and status history; estimated
completion; amount due and payment state; the actions available to the customer.

### 2.2 Never shown, with or without OTP

**The full address** (`TRK-010`). Laundry photographs and delivery proof artefacts (`TRK-017`). Other
orders belonging to the same customer (`TRK-015`). Internal notes, and staff identity beyond what is
operationally necessary (`TRK-016`).

### 2.3 Shown only after OTP verification

Detail behind a sensitive action — changing a delivery address, requesting a schedule change
(`TRK-012`). Even then, the full address is not rendered back to the portal; the customer supplies a
new one, they do not read the old one out of the page.

---

## 3. The token

| Property | Rule |
| --- | --- |
| Entropy | High, from a cryptographically secure random source (`TRK-001`). |
| Storage | **Hashed** as `TrackingTokenHash`. The plaintext exists only inside the link (`TRK-002`). |
| Derivation | **It is not the order number and is not derivable from it** (`TRK-003`). |
| Revocation | Revocable, effective immediately, with actor and reason recorded (`TRK-004`, `TRK-022`). |
| Expiry | Always bounded. **Canonical default: expires 30 days after order completion** (`TRK-005`). |
| Logging | Plaintext is **never** logged, never written to an audit entry, and never returned by any API after issuance (`TRK-019`). |
| Lookup | Rate-limited and enumeration-protected (`TRK-007`). |
| Scope | Exactly one order in exactly one tenant (`TRK-020`). |

### 3.1 Why the order number cannot be the token

`HumanOrderNumber` is short, sequential within an outlet, printed on the nota, and read aloud over
the phone. It is **guessable by design** — that is what makes it useful. Precisely because it is
guessable, it grants access to nothing. Anyone who could guess a neighbour's order number would
otherwise be able to read their order.

### 3.2 Sharing is a feature

The link is **shareable via WhatsApp by design** (`TRK-014`): a customer forwards it to a family
member who is collecting. This is why the projection carries only masked, safe fields — the design
assumes the link will be forwarded, and is safe under that assumption.

---

## 4. Tenant rules

- A `TrackingAccess` grants visibility of **exactly one order in exactly one tenant**. It never lists
  other orders and never traverses to another tenant (`TRK-020`).
- **Tenant context is derived server-side from the stored record**, never from the request
  (`TRK-021`). An unauthenticated visitor supplies a token; they never supply a tenant.
- If the stored record's tenant cannot be established, the lookup **fails** (fail closed).

---

## 5. Privacy

- Names are **partially masked** (`TRK-011`).
- Phone numbers appear only as `MaskedPhoneNumber` (`TRK-009`).
- **The portal never shows the full address** (`TRK-010`). Not to the customer, not to a forwarded
  recipient, not behind OTP.
- The portal is served with **`noindex`**; tracking pages never enter search engines (`TRK-006`).
- A message never echoes an OTP value alongside a tracking link, which would enable one-message
  account takeover (`TRK-029`, `NOT-014`).

**Fictional illustration.** A portal page for order `OUT01-260719-0042` shows
`Pelanggan: B*** S***`, `Telepon: +62-800-****-0001`, `Outlet: Outlet Percontohan 1`. It does not show
a street address in any form. All values here are invented.

---

## 6. Abuse resistance

- Token lookup is **rate-limited**, with progressive backoff on repeated failure (`TRK-007`).
- Enumeration attempts emit `TrackingAccessThrottled` and are recorded as security events
  (`TRK-024`).
- Issuance, views, OTP challenges, throttling, and revocation are all recorded as security events.
- **A throttle, a failed lookup, or a revocation never changes order state** (`TRK-030`).

---

## 7. Relationship to the Customer Android app

> **The Customer Android application is an enhancement, never a replacement** (`TRK-026`,
> [DEC-0014](../decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md)).

- Any capability a customer genuinely needs in order to follow their laundry must be reachable from
  the portal.
- **The portal is never degraded into "install the app first"** (`TRK-025`).
- The portal is the customer's default tracking experience; the app is for customers who want more.

---

## 8. Honest limitations

Stated plainly rather than papered over:

- **Public tracking is server-rendered and requires the network by nature** (`TRK-027`). It does not
  work offline, and the product does not pretend it does.
- Estimated completion is an **estimate** and is labelled as one. The product never presents an
  estimate as a guarantee.
- A revoked or expired link stops working. That is the intended behaviour, and the page says so
  clearly with a recovery step — "minta tautan baru dari outlet" — rather than an error code.

---

## 9. Performance note

The portal is the **most performance-critical surface in the product**. It is opened by customers on
unknown devices over unknown networks, often exactly once. Flutter is not mandatory here; a lighter
web stack is permitted if it loads materially faster on low-end Android browsers. That choice is
recorded in a decision record in the Step that builds it (Master Source §5.4), and is not made here.

---

## 10. Status

The tracking domain is `NOT IMPLEMENTED`. No token, projection, portal, throttle, or OTP path exists.
Backend runtime is `ABSENT`. This document claims no test, build, deployment, CI run, or UAT.

---

## Related documents

- [`ORDER_DOMAIN.md`](ORDER_DOMAIN.md)
- [`NOTIFICATION_DOMAIN.md`](NOTIFICATION_DOMAIN.md)
- [`TENANT_BOUNDARIES.md`](TENANT_BOUNDARIES.md)
- [`DOMAIN_INVARIANTS.md`](DOMAIN_INVARIANTS.md)
