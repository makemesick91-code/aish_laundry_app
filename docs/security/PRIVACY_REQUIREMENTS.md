# Privacy Requirements — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED. Backend runtime ABSENT. Flutter workspace ABSENT.
Deployment ABSENT. Application CI NOT APPLICABLE. UAT NOT STARTED.
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §9, §14, §15, §17, §20, §21
**Related decisions:** [DEC-0006](../decisions/DEC-0006-public-tracking-without-app-installation.md),
[DEC-0016](../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)

---

## 1. Purpose and posture

Aish Laundry App holds customer names, phone numbers, addresses, order histories, payment records,
delivery proofs including photographs and signatures, and photographs of customers' garments. **All of
it is personal data, and all of it belongs to the customer and to the tenant serving them** — not to the
platform.

The privacy posture follows from that ownership:

- **Collect the minimum** personal data needed to run a laundry operation.
- **Show the minimum** in each context. Masking level depends on who is looking and where.
- **Keep it inside the tenant boundary.** Privacy failures and tenant-isolation failures are usually the
  same failure seen from two angles.
- **Never monetise it.** Laundry photographs are never used for marketing. Tenant data is never used to
  train AI models without explicit consent.

Nothing in this document is implemented. Every requirement below is a requirement on a future Step.

---

## 2. Personal data inventory

| Data | Class | Held because | Retention posture |
| --- | --- | --- | --- |
| Customer name | CONFIDENTIAL | Identifying a bag of laundry to a person | While the tenant relationship exists |
| Customer phone number | CONFIDENTIAL | The primary contact channel in this market | Same |
| Customer address | RESTRICTED | Pickup and delivery cannot happen without it | Same |
| Order history | CONFIDENTIAL | Operations, disputes, and the customer's own record | Same, plus tax and legal obligation |
| Payment records | RESTRICTED | Financial truth for the tenant | Per financial retention obligation |
| Laundry photographs | RESTRICTED | Evidence of condition at intake, dispute resolution | Shortest period serving that purpose |
| Proof of pickup and delivery — photo, signature, recipient name | RESTRICTED | Custody transfer evidence | Same |
| Notification consent state | CONFIDENTIAL | Legal and ethical basis for messaging | Retained beyond the relationship where needed to honour an opt-out |
| Staff identity on audit records | CONFIDENTIAL | Accountability for financial and status actions | Per audit retention obligation |

---

## 3. Masking requirements

Masking is **contextual**. The same field renders differently depending on who is looking and where. A
kasir preparing a delivery legitimately sees more than a portal visitor.

| Context | Name | Phone | Address | Photographs |
| --- | --- | --- | --- | --- |
| **Public tracking portal** | Partial | Partial | **Never shown, in any form** | **Never shown** |
| Customer's own app | Full — their own data | Full — their own | Full — their own | Their own order's photos |
| Kasir at the counter | Full | Full | Full when needed for the order | Yes, for the order |
| Production operator | Partial | Not needed | Not needed | Yes, for the order |
| Courier — assigned job | Full name needed for handover | Contactable number for the job | **Minimum the delivery genuinely requires** | Proof they capture |
| External ojek via guest link | Recipient name for the assignment | Contactable number for the job | **Minimum the delivery genuinely requires**, never shareable or indexable | Proof they capture, for that assignment |
| Manager and owner reports | Per permission, tenant-scoped | Per permission | Per permission | Per permission |
| Platform support | Only under time-bound, audited impersonation | Same | Same | Same |
| Logs and telemetry | **Never** | **Never** | **Never** | **Never** |

**Hard rules:**

1. **The public tracking portal never shows a full address.** There is no configuration, no role, and no
   tenant setting that changes this.
2. **Full address, full phone number, other orders belonging to the same customer, internal notes, staff
   identity beyond the operationally necessary, and laundry photographs are never shown on the portal
   without OTP verification.**
3. Masking is applied **server-side**, in the response the client receives. A client that receives a full
   value and hides it has already leaked it.
4. **Personal data appearing in error reports, analytics, or crash traces is redacted at source.**

---

## 4. Consent

1. **Marketing messaging requires consent, separately from transactional messaging.** They are different
   categories with different templates, different consent, and different opt-out handling.
2. **Consent is recorded per customer per tenant, with a timestamp and a source.** Consent given to one
   tenant is not consent given to another, even where the phone number is identical — and data is never
   merged merely because contact details match.
3. **Opt-out takes effect immediately** and is **never reset by a data import**.
4. **Opt-out is evaluated at send time**, not only at campaign-build time, and holds permanently across
   all outlets of the tenant.
5. **A marketing message must never be sent through a transactional path to evade opt-out.**
6. The unclaimed-laundry reminder ladder — **H+1, H+3, H+7 with a follow-up task, and H+14 escalation** —
   is transactional, but still respects opt-out and quiet hours.
7. **Quiet hours default to 20.00–08.00 outlet local time.** Non-urgent messages queued during quiet
   hours are **deferred to the next permitted window** — never dropped, never silently sent anyway.
8. **Absence of a refusal is not consent.** Consent for AI model training is specific, informed,
   recorded, and revocable, and **tenant data is not used to train AI models without it**.

---

## 5. Data lifecycle

1. Data is retained while the tenant relationship exists and for the period required by the tenant's
   legal and tax obligations.
2. **Tenant data remains exportable per policy when a subscription lapses.** A lapsed subscription
   restricts access to features; it does not hold a tenant's business records hostage. Blocking export
   for a lapsed tenant breaches a pricing guardrail and is rejected.
3. Deletion requests are handled at the tenant boundary with a documented process. **Financial records
   subject to retention obligations are handled per those obligations, and the customer is told so** —
   the honest answer is given rather than a deletion that did not happen.
4. **Exports carry the same access rules as the underlying records**, are tenant-scoped, use unguessable
   object keys, and are delivered by signed expiring URL.
5. Laundry photographs and delivery proofs are retained for the shortest period that serves their
   evidential purpose, because they are the most intrusive data the product holds.
6. **Backups are encrypted** at rest and in transit, and **restore is tested rather than assumed**.

---

## 6. Privacy in observability

Observability exists to answer whether the system is healthy, what happened to a specific order, and
where the money is. None of those questions requires personal data in telemetry.

1. **Tenant context in telemetry is recorded as an identifier for filtering, never as personal data.**
   Telemetry is not a bypass around tenant isolation or privacy.
2. **Logs never contain passwords, OTPs, tokens, or credentials** — not at debug level, not temporarily,
   not on a local branch. **Redaction is enforced at the logging boundary**, not by hoping nobody logs
   the wrong object.
3. Correlation identifiers link a request through queues and background jobs, which is how a specific
   order is traced **without** embedding the customer in the trace.
4. Structured logs have a stable schema with a defined set of permitted attribute keys, rather than an
   open field into which any object can be serialised.

---

## 7. Privacy of proof artefacts

Delivery proofs deserve their own section because they are the least obvious privacy exposure in the
product.

1. A proof photograph may show a customer's doorway, their home interior, or their belongings. A
   signature is biometric-adjacent. Both are **RESTRICTED**.
2. Proof artefacts are stored in **private object storage**, served only through **signed expiring
   URLs**, tenant-scoped with unguessable keys, and **never exposed on the public tracking portal**.
3. **A proof photo or signature reachable without authentication, or exposed on the public portal, is an
   automatic NO-GO.** The exposure path is revoked immediately and the owner is notified.
4. **Proof is mandatory for every custody transfer** — the privacy cost is accepted because the
   alternative is a customer's belongings changing hands with no record at all. The mitigation is strict
   handling, not omission of the proof.

---

## 8. Privacy and this PUBLIC repository

**This repository is PUBLIC** by deliberate owner decision (AMENDMENT-0001, DEC-0016), and is never
described as private.

1. **No customer personal data may ever appear here** — not in documentation, not in an example, not in
   a test fixture, not in an evidence pack, not in a commit message, not in an issue.
2. **Every example datum is fictional** and recognisably so, following the placeholder convention in
   [`DATA_CLASSIFICATION.md`](DATA_CLASSIFICATION.md) §6.
3. Evidence packs are **sanitised before commit** and state that sanitisation occurred.
4. **Deletion is not remediation.** Anything committed must be assumed mirrored, cached, and indexed.
5. Governance runs in **single-maintainer mode; independent human approval is ABSENT**. The secret and
   personal-data scanners are therefore doing work a second reader would otherwise do, and their
   coverage is a real dependency rather than a formality.

---

## 9. Privacy requirements summary

The enforceable `SEC-###` identifiers for the requirements above are registered in
[`SECURITY_ACCEPTANCE_CRITERIA.md`](SECURITY_ACCEPTANCE_CRITERIA.md) §4 — specifically SEC-034 through
SEC-046. This document states the reasoning; that document states the testable criteria and the
responsible Step.

---

## 10. Related documents

- [`DATA_CLASSIFICATION.md`](DATA_CLASSIFICATION.md)
- [`INITIAL_THREAT_MODEL.md`](INITIAL_THREAT_MODEL.md)
- [`ABUSE_CASES.md`](ABUSE_CASES.md)
- [`TRUST_BOUNDARIES.md`](TRUST_BOUNDARIES.md)
- [`SECURITY_ACCEPTANCE_CRITERIA.md`](SECURITY_ACCEPTANCE_CRITERIA.md)
- [`../quality/NON_FUNCTIONAL_REQUIREMENTS.md`](../quality/NON_FUNCTIONAL_REQUIREMENTS.md)
- [`../quality/ACCEPTANCE_CRITERIA.md`](../quality/ACCEPTANCE_CRITERIA.md)
