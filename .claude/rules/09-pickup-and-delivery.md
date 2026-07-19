# Rule 09 — Pickup and Delivery

## Purpose

Pickup and delivery is a **core product differentiator**, not an add-on module. It also introduces
the two riskiest operational surfaces in the system: physical custody of a customer's belongings, and
cash held by a courier. This rule fixes both.

Backed by **DEC-0007 — Pickup and Delivery as Core Product**. Delivered in Step 8.

## Foundation capabilities

The foundation must accommodate, without redesign:

- **Pickup request** raised by the customer or by staff.
- **Schedule** and **time window** for pickup and delivery.
- **Zone** definition for coverage and assignment.
- **Courier assignment**, covering both **internal courier** and **external ojek lokal**.
- **Simple route ordering** — an ordered list of stops a courier works through.
- **Route suggestion with no false optimization claims.**
- **Proof of pickup** and **proof of delivery**.
- Proof mechanisms: **OTP, photo, signature, recipient name**.
- **Cash collection** at delivery.
- **Courier cash reconciliation.**
- **Secure guest link for external ojek.**

## Hard rules

1. **No false optimization claims.** Route *suggestion* is exactly that — a suggestion. The product
   must never claim optimal routing, guaranteed arrival times, or algorithmic delivery guarantees it
   does not implement. Copy and UI must say "usulan rute" semantics, not "rute optimal". This is a
   direct application of the no-false-claims rule (Rule 01).
2. **Proof of pickup and proof of delivery are mandatory** for every custody transfer. A parcel does
   not silently change hands. The proof method (OTP, photo, signature, recipient name) may vary by
   tenant policy, but *some* recorded proof is always required.
3. **Proof artifacts are private data.** Photographs and signatures may show a customer's home,
   belongings, or handwriting. They are stored in private object storage, served only through signed
   expiring URLs, tenant-scoped, and never exposed on the public tracking portal (Rule 03).
4. **Courier cash is reconciled.** Cash collected on delivery is tracked per courier, per shift, from
   collection through handover. Expected versus actual is compared explicitly and any variance is
   recorded and acknowledged, never absorbed silently (Rule 04).
5. **Cash collection is a financial transaction** and inherits every rule in Rule 04: integer Rupiah,
   idempotency, no deletion via ordinary UI, corrections by reversal or adjustment, and audited
   actor/timestamp/reason.
6. **The external ojek guest link is a secure, minimal-privilege, temporary credential.** It must:
   - use a **high-entropy token**, stored **hashed** server-side;
   - be **revocable** and **expiring**;
   - expose only the minimum needed to complete the assigned job;
   - **not** be the order number, and not be derivable from it;
   - **not** grant access to customer history, other orders, pricing, or any tenant data beyond the
     assignment;
   - **not** show a full customer address beyond what the delivery genuinely requires, and never in a
     shareable or indexable form.
7. **The guest link is tenant-scoped.** An external courier working for two tenants gets two
   unrelated links and can never traverse from one to the other (Rule 02).
8. **Courier UX is deliberately simple** — large tap targets, few steps, one-handed operation,
   usable outdoors on a cheap phone (Rule 05). Couriers operate under time pressure; complexity here
   produces skipped proofs and lost cash.
9. **Offline capability applies.** Couriers lose signal. Proof capture and cash recording follow the
   offline-first rules, including `client_reference` and no-duplicate guarantees (Rule 07).
10. **Delivery notifications** follow Rule 08, including quiet hours, deduplication, and the rule
    that a messaging failure never changes order state.

## Step 0 note

No pickup, delivery, routing, proof, courier, or guest-link implementation exists. In Step 0 it is
forbidden to create any pickup-delivery implementation. This rule records the constraints only.

## Violation handling

- **A custody transfer recorded without proof** — treat as a product defect; the flow must not permit
  it. Retrofit the proof requirement before the step's Definition of Done.
- **A proof photo or signature reachable without authentication, or exposed on the public portal** —
  automatic **NO-GO** under Rule 03; revoke the exposure path immediately and notify the owner.
- **A guest link that is guessable, non-expiring, non-revocable, stored in plaintext, or that grants
  access beyond its assignment** — security defect of the highest severity; fix before any external
  courier feature ships.
- **Courier cash variance hidden, auto-adjusted, or written off silently** — financial integrity
  violation, automatic **NO-GO** under Rule 04.
- **Any claim of route optimization, guaranteed delivery time, or ETA accuracy the system does not
  actually provide** — remove the claim immediately as a false claim under Rule 01.
