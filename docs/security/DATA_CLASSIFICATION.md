# Data Classification — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED. Backend runtime ABSENT. Flutter workspace ABSENT.
Deployment ABSENT. Application CI NOT APPLICABLE. UAT NOT STARTED.
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §15, §15.8, §17
**Related decision:** [DEC-0016](../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)

---

## 1. Purpose

Every later decision about storage, logging, masking, retention, and what may appear in a commit
depends on knowing what class a piece of data belongs to. This document fixes the classes and assigns
the data the product will hold.

No classification scheme is enforced by any running system, because **no system is running**. This is a
requirement document.

---

## 2. The public repository constraint — read this first

**This repository is PUBLIC.** That is an accepted deviation from a canonical desired PRIVATE, recorded
in AMENDMENT-0001 and locked by
[DEC-0016](../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md). It is not a
judgement that public is adequate; it is the price paid for platform-enforced branch protection on a
free plan. **This repository is never described as private.**

Two consequences bind every author, human or AI:

1. **Every file here is world-readable and permanently so.** Deletion is not remediation. Anything
   committed must be assumed mirrored, cached, and indexed. A secret is compromised at the moment it is
   pushed, and **rotation — not removal — is the first response**.

2. **Only `PUBLIC` and sanitised `INTERNAL` material may be committed.** The `CONFIDENTIAL`,
   `RESTRICTED`, and `SECRET` classes **may be described and modelled, but never instantiated with real
   values.**

**Every example datum in this repository is fictional** and recognisably so. An example is invented,
never copied from reality. Where a SECRET-class item must be illustrated, an obviously-fake placeholder
is used and labelled as fictional — see §6.

Evidence packs are sanitised before commit and state that sanitisation occurred.

**Governance operates in single-maintainer mode. Independent human approval is ABSENT.** The
compensating controls are the ruleset, exact-SHA CI, and deterministic validators including secret
scanning. Internal re-verification by the same maintainer is never described as independent peer review.

---

## 3. The five classes

| Class | Definition | If disclosed | May it appear in this repository? |
| --- | --- | --- | --- |
| **PUBLIC** | Intended for anyone. Published to customers or prospects anyway. | No harm. Disclosure is the point. | **Yes.** |
| **INTERNAL** | Operational material not intended for publication, but whose disclosure causes no direct harm to a customer or tenant. | Minor. Embarrassment or lost context, not injury. | **Only after sanitisation**, and only when it contains no lower-class data. |
| **CONFIDENTIAL** | Data belonging to an identified tenant or customer whose disclosure damages a commercial relationship or a person's privacy. | Real harm to a tenant or a customer. | **Never with real values.** May be described and modelled. |
| **RESTRICTED** | Data whose disclosure causes serious harm — financial, physical-safety, or competitive — and which is subject to the tightest access control the product offers. | Severe. Potentially business-ending for a tenant, or a physical-safety risk for a person. | **Never with real values.** May be described and modelled. |
| **SECRET** | Credentials and bearer tokens. Their entire value is that only the holder has them. | Immediate compromise of whatever they protect. | **Never, in any form, real or plausible.** Only obviously-fake labelled placeholders. |

**Class inheritance.** A container takes the highest class of anything inside it. A report joining
order counts (CONFIDENTIAL) with delivery addresses (RESTRICTED) is RESTRICTED. A log line embedding an
OTP is SECRET, which is precisely why OTPs must never be logged.

**Aggregation raises class.** One customer phone number is CONFIDENTIAL. A tenant's complete customer
phone list is RESTRICTED, because it is the tenant's business in a single file.

---

## 4. Classification assignments

### 4.1 PUBLIC

| Data | Notes |
| --- | --- |
| **Marketing pricing** | The canonical plan table. Published to customers; publicly visible here by accepted consequence of DEC-0016. Must match the Master Source character for character wherever it appears. |
| **Public product roadmap** | PUBLIC **unless explicitly restricted** by an owner decision. Step numbers, titles, and declared scope are readable by competitors, and that is accepted (THREAT-050). |
| Brand and outlet identity as shown on the tracking portal | The customer already knows which laundry they used. |
| Order status names and their meanings | The fifteen canonical statuses are product vocabulary, not data. |
| Governance documents, decision records, rule files | The entire point of this repository. |
| Published terms, policies, and support contact routes | Intended for publication. |

### 4.2 INTERNAL

| Data | Notes |
| --- | --- |
| **Internal operational metrics** | Aggregate platform health: error rates, latency percentiles, queue depth. Commit only when sanitised and carrying no tenant identifier that maps to a real business. |
| Architecture and design documentation | Committed freely; contains no tenant data. |
| Validator output and evidence packs | Committed **only after sanitisation**, with the sanitisation stated. |
| Non-production configuration structure | Structure and key names only. Never a value. |
| Runbooks and incident procedures | Sanitised of hostnames, identifiers, and any credential shape. |

### 4.3 CONFIDENTIAL

| Data | Notes |
| --- | --- |
| **Customer phone number** | Personal data. Masked per context: a kasir preparing a delivery sees more than a portal visitor. Portal shows partial only. |
| Customer name | Masked partially on the portal. |
| Order data — items, service, status, history, estimated completion | Belongs to the customer and the tenant serving them. |
| Notification consent state | Per customer per tenant, with timestamp and source. |
| Tenant configuration — price lists, services, zones, staff roster | Competitive intelligence about a specific business. |
| Subscription entitlement — plan, limits, billing state | Commercially sensitive to the tenant. |
| **Audit record — where it contains no personal data or amounts** | For example a status-transition record naming a staff role and a timestamp. See §4.4 for the higher case. |

### 4.4 RESTRICTED

| Data | Notes |
| --- | --- |
| **Customer address** | Where a person lives. Physical-safety relevant. **The public tracking portal never shows a full address**, under any condition. A courier sees the minimum the assigned job requires. |
| **Laundry photograph** | May show the inside of a customer's home and their personal garments. Stored privately, served only by signed expiring URL, never on the public portal, never indexed, **never used for marketing**. |
| Proof-of-pickup and proof-of-delivery artefacts — photos, signatures | Same handling as laundry photographs. A signature is biometric-adjacent. |
| Payment records and the financial ledger | Integer Rupiah. Append-only in effect. Corrections by reversal or adjustment. |
| Membership records | Authorisation derives from them entirely; tampering is elevation of privilege. |
| Courier address access set | The addresses one courier may see for one assignment. |
| Local offline queue on an Ops device | Contains pending orders and payments; encrypted on device. |
| A tenant's complete customer list or bulk export | Aggregation raises the class. Business-ending if it reaches a competitor. |
| **Audit record — where it contains personal data, amounts, or reasons** | A financial audit entry carries actor, amounts before and after, and a reason; a security audit entry may carry an impersonation reason. These are RESTRICTED. |

### 4.5 SECRET

| Data | Notes |
| --- | --- |
| **Tracking token** | The bearer credential for the portal. High-entropy, **stored hashed**, expiring, revocable, **not the order number** and not derivable from it. The plaintext exists only in the link. |
| **OTP** | Single use, short expiry. **Never logged**, never echoed back in a message, never stored in plaintext beyond what verification requires. |
| **Payment provider credential** | API keys, webhook signing secrets, merchant identifiers with authority. |
| **Private key** | Signing keys, TLS keys, backup encryption keys. |
| External courier guest-link token | Same handling as the tracking token; single assignment, expiring, revocable, tenant-scoped. |
| WhatsApp provider credential and webhook secret | Third-party messaging authority, with real per-message cost attached. |
| Session and device tokens | Stored hashed server-side; in Android secure storage on device. |
| Database connection strings and object-storage credentials | From the environment, never from a committed file. |

---

## 5. Handling rules by class

| Requirement | PUBLIC | INTERNAL | CONFIDENTIAL | RESTRICTED | SECRET |
| --- | --- | --- | --- | --- | --- |
| Committable to this repository | Yes | Sanitised only | Never with real values | Never with real values | Never in any form |
| Tenant-scoped in storage | n/a | n/a | Required | Required | Required |
| Encrypted at rest beyond the database | n/a | n/a | Backups encrypted | Backups encrypted | Always; hashed where it is a credential |
| May appear in a log line | Yes | Yes | Only as an identifier, never as a value | Never | **Never, at any level, not temporarily, not on a local branch** |
| May appear in telemetry | Yes | Yes | Identifier only, never personal data | Never | Never |
| Served over a public URL | Yes | No | No | Signed expiring URL only | Never |
| Shown on the public tracking portal | Yes | No | Masked only | **Never** — address never, photographs never | Never |
| Included in an export | Yes | Sanitised | Same access rules as the underlying record | Same access rules; signed URL; unguessable key | Never |
| Retention | Indefinite | Per operational need | While the tenant relationship exists, plus legal and tax obligation | Same, with financial retention obligations honoured | Shortest possible; rotate on suspicion |

---

## 6. Fictional placeholder convention

When a document must illustrate a value in a class that may never be instantiated, it uses an
obviously-fake placeholder and labels it. The following are the only forms permitted, and **all of them
are fictional**:

| Class | Fictional placeholder | Never write |
| --- | --- | --- |
| Customer name | `Contoh Pelanggan A` (fictional) | Any real person's name |
| Customer phone | `+62-8XX-CONTOH-0001` (fictional, not a dialable number) | Any real or realistic number |
| Customer address | `Alamat Contoh 1, Kota Contoh` (fictional) | Any real address |
| Tenant | `Tenant Contoh A`, `Tenant Contoh B` (fictional) | Any real business |
| Order number | `ORD-CONTOH-0001` (fictional) | Any real order reference |
| Tracking token | `<FICTIONAL-TRACKING-TOKEN-PLACEHOLDER>` (fictional; never a realistic-looking random string) | Anything resembling real token entropy |
| OTP | `<FICTIONAL-OTP-PLACEHOLDER>` (fictional) | Any six-digit-looking value |
| Any credential or key | `<FICTIONAL-CREDENTIAL-PLACEHOLDER>` (fictional) | Anything with a real key prefix, structure, or length |

The rule behind the convention: a placeholder must be impossible to mistake for a real value, including
by an automated scanner and by a future reader skimming quickly. A realistic-looking fake is worse than
an obviously-fake one, because it trains readers to accept realistic-looking strings in commits.

---

## 7. Related documents

- [`INITIAL_THREAT_MODEL.md`](INITIAL_THREAT_MODEL.md) — the asset table uses these classes
- [`PRIVACY_REQUIREMENTS.md`](PRIVACY_REQUIREMENTS.md) — masking, consent, retention
- [`TRUST_BOUNDARIES.md`](TRUST_BOUNDARIES.md) — where each class may cross
- [`SECURITY_ACCEPTANCE_CRITERIA.md`](SECURITY_ACCEPTANCE_CRITERIA.md)
- [`ABUSE_CASES.md`](ABUSE_CASES.md) — ABUSE-015 covers leakage into this repository
- [`../quality/NON_FUNCTIONAL_REQUIREMENTS.md`](../quality/NON_FUNCTIONAL_REQUIREMENTS.md)
