# DEC-0007 — Pickup and Delivery as Core Product

## ID

DEC-0007

## Title

Pickup and Delivery as Core Product

## Status

ACCEPTED

## Date

19 July 2026

## Context

Antar-jemput is how many Indonesian laundry businesses compete. A shop that collects and returns laundry
wins customers from the shop next door that does not. For a growing number of laundries it is not a
service add-on but the primary way orders arrive.

Yet in practice it is run entirely outside any system: a customer messages on WhatsApp, the owner asks a
courier informally, the courier remembers the addresses, cash is collected at the door and handed over at
the end of the day from memory, and when a customer says "nobody came" or "I already paid the courier"
there is no record to consult.

The failures are specific and expensive:

- **Unrecorded requests.** A pickup agreed in a chat thread is forgotten.
- **No proof.** A disputed delivery cannot be resolved. The business usually absorbs the loss.
- **Unreconciled cash.** Money collected at doors is the least controlled money in the business.
- **Invisible courier work.** The owner cannot see what was collected, what was delivered, or what failed.
- **External riders.** Many laundries use ojek lokal who are not staff, cannot be given full system
  access, and currently receive addresses by chat message.

Treating this as a module to add after the POS is built would mean designing the order lifecycle, the
payment model, and the proof model without it, and then retrofitting — which historically produces a
bolted-on feature that nobody uses.

## Decision

**Pickup and delivery is a core product capability, designed into the foundation, not an optional
add-on.**

Canonical capabilities:

- **Pickup request** — raised by the customer or by staff on the customer's behalf.
- **Schedule** — a job is scheduled, not merely requested.
- **Time window** — the customer receives a window, never a fictitious exact minute.
- **Zone** — outlets define service zones and requests are matched to them.
- **Courier assignment** — a specific courier to a specific job.
- **Internal courier** — staff couriers using Ops Android.
- **External ojek lokal** — third-party riders without full application access.
- **Simple route ordering.**
- **Route suggestion with no false optimization claims.**
- **Proof of pickup** and **proof of delivery**.
- Proof mechanisms: **OTP, photo, signature, recipient name**.
- **Cash collection** at the door.
- **Courier cash reconciliation.**
- **Secure guest link for external ojek.**

Canonical rules:

1. A delivery is never marked complete without proof appropriate to the tenant's configured policy.
2. Cash collected by a courier is a financial transaction and is fully subject to §16 and DEC-0012.
3. An external ojek guest link grants access to **one job**, expires, and never exposes the customer's
   address history, other orders, or any other tenant data.
4. Route suggestions are labelled as suggestions. Claiming optimisation the product does not perform is
   forbidden (§3.1).
5. A failed delivery is a first-class outcome with a recorded reason, not an error state.
6. Time windows are commitments shown to the customer and their adherence is measurable (§29.1).

## Consequences

The order lifecycle is designed from the start to include pickup and delivery states rather than assuming
counter drop-off and counter collection. Proof capture — OTP, photo, signature, recipient name — becomes
part of the domain model in Step 1 and the file-storage and privacy model in §17. Courier cash becomes a
tracked balance subject to hard gate 2. The guest-link mechanism reuses the secure-token discipline
established for the tracking portal (DEC-0006) and is itself a tenant isolation concern. The capability
is delivered in Step 8 and included in the MVP (DEC-0015).

## Positive consequences

- Supports the way many target businesses already compete, rather than asking them to change.
- Converts the least controlled money in the business — cash at the door — into recorded, reconciled
  financial transactions.
- Proof of delivery ends the category of dispute the business currently always loses.
- External ojek can be used safely, without handing a third party access to customer data.
- Delivery becomes the strongest available remedy for unclaimed laundry (DEC-0008): laundry a customer
  will not collect can often be delivered.
- Time-window adherence becomes a measurable service-quality metric rather than an impression.

## Negative consequences / trade-offs

- **Substantial scope in the MVP.** Scheduling, zones, assignment, proof, and cash reconciliation are a
  large module, and including it lengthens the path to a usable product.
- **Courier cash increases financial risk surface.** Every cash collection is a transaction subject to a
  hard gate, so defects here are automatically NO-GO.
- **Guest links are a second unauthenticated surface**, with the same class of risk as the tracking
  portal and the same requirement for high-entropy, hashed, expiring, single-scope tokens.
- **Refusing to claim route optimisation forgoes a marketing line** that competitors will use. This is
  accepted under the honesty value (§3.1); a suggestion presented as an optimum would be a lie.
- **Time windows create commitments the business can miss**, and the product will measure and surface
  those misses. This is uncomfortable and correct.
- **Proof capture costs the courier time** at each door, on a low-end phone, sometimes in the rain. The
  courier interface must be minimal (§18.2) or the proof will be skipped.
- Photographs of proof are personal data with storage, privacy, and cost implications (§17).

## Verification

- Step 8 tests assert: a delivery cannot be completed without configured proof; proof artefacts are
  stored privately and served only via signed URL.
- Tests assert that a guest link resolves to exactly one job, expires, and cannot reach any other data.
- Financial tests assert that courier cash collection is idempotent, reconciled, and non-deletable
  ([`../governance/FINANCIAL_INTEGRITY_POLICY.md`](../governance/FINANCIAL_INTEGRITY_POLICY.md)).
- Tenant isolation tests assert a guest link cannot cross a tenant boundary.
- Review asserts that no user-facing copy claims route optimisation.
- Reporting verifies that time-window adherence is computed from recorded windows and recorded completion
  times.
- At the Step 0 baseline: pickup and delivery is `NOT IMPLEMENTED`.

## Supersession policy

Superseded only by a decision record demoting pickup and delivery from core scope, which would require
evidence from pilot data that target businesses do not rely on it. Removing proof capture, removing
courier cash reconciliation, or introducing an optimisation claim each require their own decision record.
Requires at least a **minor** version bump of [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md).

## Related Master Source sections

- §2 Vision
- §10 Pickup and delivery
- §11 Unclaimed laundry
- §16 Financial integrity
- §18 UX and design foundation
- §22 MVP
- §29 Success metrics
