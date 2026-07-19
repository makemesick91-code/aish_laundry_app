# Data Ownership — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** `NOT IMPLEMENTED` (documentation only)
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.3.0

This document answers three questions for every class of data in the model: **who owns it**, **who
may read it**, and **what happens to it over time**. Ownership here means *authority to write* — the
one context that may change a fact. Every other context reads a published interface or a projection.

Invariant identifiers are defined in [`DOMAIN_INVARIANTS.md`](DOMAIN_INVARIANTS.md).

---

## 1. Ownership principles

| Principle | Statement |
| --- | --- |
| **One writer** | Exactly one bounded context may write each fact. A second writer is a second truth. |
| **No cross-module table access** | A module owns its data and exposes an interface. Reaching into another module's tables is what turns a modular monolith into a mud ball (Master Source §6.2). |
| **Projections are never truth** | Reporting owns projections and never writes a business fact. A figure that cannot be computed is shown as unavailable, never as zero. |
| **Money has exactly one system of record** | Payment and Receivables. The unclaimed dashboard, the owner portfolio, and every report **read** money from it and never recompute it (`FIN-023`, `UCL-014`, `UCL-024`). |
| **Tenant data belongs to the tenant** | Not to the platform. It remains exportable per policy when a subscription lapses (`TEN-018`, `TEN-028`), and it is never used to train AI models without explicit, specific, informed, recorded, revocable consent. |
| **Snapshots are owned by the copier** | Once an order line holds a price snapshot, Order Intake owns that copy. Service Catalog cannot reach back and change it (`FIN-012`). |

---

## 2. Ownership register

| Data | Owning context | Readers | Written by anyone else? |
| --- | --- | --- | --- |
| Tenant, brand, outlet, membership, roles | Tenant and Organization | All | Never |
| User account, session, device | Identity and Access | Tenant and Organization | Never |
| Subscription, plan, trial, fair-use usage | Subscription and Entitlement | Order Intake (signal), Reporting, Platform Admin | Never |
| Customer profile, contact, consent | Customer Management | Order Intake, Notification, Delivery, Unclaimed, Loyalty | Never |
| Customer address | Customer Management | Delivery (operational use) | Never |
| Catalog item, price list version, price rule | Service Catalog and Pricing | Order Intake (quote only) | Never |
| **Order price snapshot** | **Order Intake and POS** (as copier) | Payment, Reporting | **Never — not even by Service Catalog** (`FIN-011`, `FIN-012`) |
| Order, order line, status history, first-ready timestamp | Order Intake and POS | Production, QC, Payment, Tracking, Delivery, Unclaimed, Reporting | Never |
| Condition evidence | Order Intake and POS (content in File and Evidence Management) | Production, QC, Delivery | Never |
| Production job, stage records | Production Operations | Order Intake, Reporting | Never |
| Inspection verdicts, waivers | Quality Control and Rework | Order Intake, Reporting, Audit | Never |
| **Payment, refund, receivable, cashier shift** | **Payment and Receivables** | Order Intake, Unclaimed, Courier Settlement, Reporting | **Never** (`FIN-007`, `FIN-008`) |
| Tracking access, public projection | Customer Tracking | The portal (read-only) | Never |
| Pickup/delivery job, schedule, failure record | Pickup and Delivery | Courier Settlement, Order Intake, Reporting | Never |
| Delivery proof | Pickup and Delivery (content in File and Evidence Management) | Order Intake, Reporting, dispute handling | Never |
| Courier assignment, guest link hash | Courier Assignment and Settlement | Delivery | Never |
| Courier settlement, cash collection records | Courier Assignment and Settlement | Payment, Reporting | Never |
| Notification records, delivery outcomes | Notification and Communication | Reporting | Never |
| Unclaimed case, reminder schedule, follow-up task, reason not collected | Unclaimed Laundry Recovery | Reporting, Notification | Never |
| Loyalty and deposit balances | Loyalty, Membership, and Deposit | Payment, Reporting | Never |
| Audit entries | Audit and Compliance | Reporting, Platform Admin, the tenant concerned | **Never — append-only** (`TEN-022`, `FIN-032`) |
| Attachments, object keys, signed-URL log | File and Evidence Management | The context that registered the artefact | Never |
| Offline queue entries, sync conflicts | Offline Synchronization | The owning business context on acceptance | Never |
| Report projections | Reporting and Owner Portfolio | Console Web | Never |

---

## 3. The money boundary

Money is the most consequential data after identity, so its ownership is stated separately and
without qualification.

1. **Payment and Receivables is the sole writer of money.** No other context creates, alters, or
   removes a financial record.
2. **Courier cash is money.** `CourierSettlement` records collection and reconciliation, and posts
   through Payment and Receivables. Cash collected at the door inherits every financial rule
   (`FIN-027`).
3. **Deposits and loyalty balances are customer money** and are subject to every financial rule
   (`FIN-037`).
4. **The unclaimed dashboard reads money**; it never computes it. Unpaid balance and held invoices
   come from `Receivable` (`FIN-023`, `UCL-014`).
5. **The owner portfolio reads money**; it never computes it. Every number derives from the same
   system of record that operations use — there is no separate reporting truth.
6. **Corrections are reversal or adjustment entries only.** There is no delete path, no in-place
   amount edit, and no variance-suppression path anywhere in the model (`FIN-007`, `FIN-008`,
   `FIN-026`).
7. **The financial audit trail is append-only and exempt from log rotation** (`FIN-032`).

---

## 4. Data classification

Classification governs where data may appear. **Only `PUBLIC` and sanitised `INTERNAL` material is
ever committed to this repository** (Master Source §15.8).

| Class | Examples in this model | Handling |
| --- | --- | --- |
| **PUBLIC** | Plan names and prices; brand and outlet identity as shown on a nota; the canonical order-status vocabulary. | May appear in documentation. Pricing figures must match the Master Source exactly. |
| **INTERNAL** | Aggregate operational metrics; anonymised aging distributions; module design. | May appear in sanitised form. |
| **CONFIDENTIAL** | Customer names, phone numbers, order histories, tenant revenue, price lists. | Modelled and described here; **never instantiated with real values** in this repository. |
| **RESTRICTED** | Full addresses, laundry photographs, delivery proof images and signatures, geolocation. | Private object storage; signed expiring URLs only; never on the public portal; never in a notification body. |
| **SECRET** | Credentials, OTP values, session tokens, tracking token plaintext, guest link plaintext, gateway keys. | **Never stored in plaintext, never logged, never audited, never committed, never returned after issuance.** |

Every example datum in Step 1 documentation is **fictional and recognisably so**. This repository is
PUBLIC by deliberate owner decision
([DEC-0016](../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)); a committed
secret is compromised at the moment it is pushed, and rotation — not removal — is the first response.

---

## 5. Masking by context

The same underlying datum is exposed at different fidelity depending on who is looking. Masking is
applied when a projection is **built**, not when it is rendered, so a rendering bug cannot leak a
full value (`TRK-018`).

| Viewer | Customer name | Phone number | Address | Laundry photos | Proof artefacts |
| --- | --- | --- | --- | --- | --- |
| Public tracking portal visitor | Partially masked | `MaskedPhoneNumber` | **Never shown** | **Never shown** | **Never shown** |
| Customer in their own app | Full (their own) | Full (their own) | Full (their own) | Their own only | Their own only |
| Kasir at the outlet | Full | Full | Full | Yes | Yes |
| Kurir (internal) | Full for the assigned job | Full for the assigned job | Full for the assigned job | No | Capture and view own |
| **External ojek lokal (guest link)** | Recipient name only | Contact for the assigned job only | **Only what the assigned delivery genuinely requires**, never shareable or indexable | **No** | Capture only for the assigned job |
| Owner / finance | Per role and permission | Per role and permission | Per role and permission | Per permission | Per permission |
| Platform admin | Only under time-bound, audited impersonation | Same | Same | Same | Same |

**The external ojek guest link never grants access to customer history, other orders, pricing, or any
tenant data beyond the assignment** (`DEL-024`).

---

## 6. Retention

| Data | Retention |
| --- | --- |
| Financial records — payments, refunds, receivables, shifts, settlements, financial audit | **Permanent**, subject to the tenant's legal and tax obligations. Never deleted through ordinary UI. |
| Orders, lines, price snapshots, status history | Retained while the financial record referencing them is retained. |
| Condition evidence and delivery proofs | Retained for at least the life of the order, and for the tenant's dispute window. |
| Notification send records | Retained for cost transparency and dispute resolution; bodies per tenant policy. |
| Tracking access records | Retained as security audit material after expiry. **Token plaintext is never retained at all.** |
| Audit entries | Permanent for financial and security-relevant entries; never rotated (`FIN-032`). |
| Customer personal data | Retained while the tenant relationship exists and for the period required by the tenant's obligations. |
| Offline queue entries | Pruned after confirmed server acceptance. **Financial operations are pruned only after confirmed acceptance, never on a timer** (`OFF-021`). |
| Report projections | Rebuildable; never archived with personal data. |

---

## 7. Deletion, anonymisation, and export

| Request | Handling |
| --- | --- |
| **Customer deletion request** | Handled at the tenant boundary through a documented process. Personal fields are anonymised; **financial records subject to retention obligations are preserved**, and the customer is told so plainly. |
| **Tenant closure** | A lifecycle state, not a data wipe. Business data is retained per obligation and remains exportable. |
| **Subscription lapse** | Restricts features. **Never blocks export** (`TEN-018`, `TEN-028`). A lapsed subscription does not hold a tenant's business records hostage. |
| **Tenant export** | Tenant-scoped, carrying the same access rules as the underlying records. Exports are files and are therefore private, tenant-keyed, and signed-URL only. |
| **AI training** | Tenant data is **not** used to train AI models without explicit consent that is specific, informed, recorded, and revocable. **Absence of a refusal is not consent.** |

**There is no deletion path anywhere in this model for:** a financial transaction, an audit entry, a
captured delivery proof, a captured condition evidence item referenced by a custody record, or a
first-ready timestamp. Their immutability is the point.

---

## 8. Status

No storage, schema, bucket, retention job, export path, or masking implementation exists. Everything
above is `NOT IMPLEMENTED`. Backend runtime is `ABSENT`; Flutter workspace is `ABSENT`. This document
claims no test, build, deployment, CI run, or UAT.

---

## Related documents

- [`TENANT_BOUNDARIES.md`](TENANT_BOUNDARIES.md)
- [`AGGREGATE_CATALOG.md`](AGGREGATE_CATALOG.md)
- [`DOMAIN_INVARIANTS.md`](DOMAIN_INVARIANTS.md)
- [`PAYMENT_DOMAIN.md`](PAYMENT_DOMAIN.md)
