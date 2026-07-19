# Abuse Cases — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED. Backend runtime ABSENT. Flutter workspace ABSENT.
Deployment ABSENT. Application CI NOT APPLICABLE. UAT NOT STARTED.
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §4, §9, §11, §13, §14, §15, §16, §17

---

## 1. Purpose

A use case describes what a user is meant to do. An **abuse case** describes what someone will actually
try. Writing them down at Step 1 means the requirements are shaped by them rather than patched after
someone succeeds.

Each abuse case names the abuser, their goal, the path they take, the harm, the **required countermeasure**
(a requirement placed on a future Step, not a description of an existing control), and the related
threat records in [`INITIAL_THREAT_MODEL.md`](INITIAL_THREAT_MODEL.md).

**Nothing here is implemented.** Every countermeasure is a requirement. No abuse case has been tested,
because there is no runtime to test against.

**Every example below is fictional.** No real number, name, address, token, or credential appears
anywhere in this document.

---

## 2. Abuse case register

### ABUSE-001 — Tenant enumeration
- **Abuser:** Competitor, or a curious authenticated user
- **Goal:** Discover which laundry businesses are on the platform and how large they are
- **Path:** Probe tenant identifiers, subdomain patterns, or login error messages to distinguish "tenant exists" from "tenant does not exist"; count responses to build a customer list of the platform itself
- **Harm:** Competitive intelligence about the platform's tenants; a target list for later attacks
- **Required countermeasure:** Tenant identifiers that appear in requests are non-sequential and non-guessable; authentication and lookup responses do not distinguish existence; a client-supplied tenant identifier is never authorisation proof and yields nothing without a validated membership; rate limiting on any endpoint accepting a tenant identifier
- **Related:** THREAT-009, THREAT-032 — **Step 3**

### ABUSE-002 — Order-number enumeration
- **Abuser:** Anyone holding one nota, or anyone at all
- **Goal:** Read other customers' orders
- **Path:** Order numbers are sequential and printed on every receipt; increment or decrement one and request the corresponding record from the portal or an API
- **Harm:** Bulk disclosure of order and partial customer data within a tenant
- **Required countermeasure:** **The order number never grants access to anything.** Portal access requires the tracking token, which is not the order number and is not derivable from it; API access requires authentication plus tenant scope plus permission; the order number remains human-friendly and sequential precisely because it carries no authority
- **Related:** THREAT-002, THREAT-022 — **Step 7**

### ABUSE-003 — Tracking token brute force
- **Abuser:** Remote attacker with a script
- **Goal:** Reach any valid tracking page without being given a link
- **Path:** Generate candidate tokens at volume against the portal lookup endpoint
- **Harm:** Disclosure of order data, masked customer data, amount due, and payment state, at whatever scale the attacker can reach
- **Required countermeasure:** High-entropy token from a cryptographically secure source, large enough that exhaustive search is infeasible; token **stored hashed** so a database read does not yield usable links; constant-time comparison; expiry; revocation; rate limiting per source and globally on the lookup path; failure-rate alerting
- **Related:** THREAT-002, THREAT-036 — **Step 7**, hardened **Step 13**

### ABUSE-004 — Price manipulation
- **Abuser:** Customer with a modified client, or a colluding staff member
- **Goal:** Pay less than the service costs
- **Path:** Submit an order whose total, unit price, or discount was computed client-side; or edit the master price list downward, complete an order, and restore it
- **Harm:** Revenue loss; a ledger that no longer reconciles against the price list
- **Required countermeasure:** Totals computed and authoritative **on the server**; client-computed totals are display only; money is **integer Rupiah** with floating point forbidden in every financial path; an order captures the price that applied at creation and **historical order prices are immune to later price-list changes**; price-list edits are permissioned and audited with actor, timestamp, before and after
- **Related:** THREAT-010 — **Step 5**

### ABUSE-005 — Payment replay
- **Abuser:** Customer, or a remote attacker who has observed one callback
- **Goal:** Have an order marked paid without paying, or paid twice to force a refund of real money
- **Path:** Replay a captured gateway callback, or resubmit a payment request repeatedly hoping for a second accepted record
- **Harm:** Direct financial loss; a duplicate payment that must then be reversed
- **Required countermeasure:** Gateway callbacks **verified server-side** for signature, amount, and currency against the expected order; replay rejected by gateway reference; payments **idempotent** on a stable `client_reference` so a retry returns the original result rather than creating a second record; an order is **never marked paid on a client claim**; concurrent operations on one order serialized by a lock
- **Related:** THREAT-006 — **Step 5**

### ABUSE-006 — Duplicate offline order
- **Abuser:** Nobody — a busy kasir plus a bad network. Abuse of the system by circumstance
- **Goal:** None. The harm is accidental and therefore constant
- **Path:** The counter loses signal mid-submit; the kasir taps submit again; the app retries with a fresh identifier; two orders exist for one bag of laundry
- **Harm:** Duplicate order, duplicate charge, duplicate laundry tag, and a customer argument at collection
- **Required countermeasure:** A `client_reference` is generated **once**, persisted with the queued operation, and **reused unchanged on every retry** — regenerating it on retry defeats the entire mechanism and must be rejected in review; the server treats it as the idempotency key; the queue is persistent across app restart and device reboot; retries use bounded exponential backoff; queue ordering respects dependencies so a payment never precedes its order
- **Related:** THREAT-013 — **Step 5**, with the offline model of §13

### ABUSE-007 — Duplicate payment
- **Abuser:** Circumstance, or an attacker exploiting the same weakness deliberately
- **Goal:** For the attacker, to force a refundable overpayment; for circumstance, nothing
- **Path:** Replay of a queued payment after a long offline period, or a double submit at the counter
- **Harm:** **Automatic NO-GO.** Money that does not add up for a business owner who reconciles by hand
- **Required countermeasure:** Server-side idempotency on `client_reference`; the financial queue is **never casually deleted** — no cache clear, version upgrade, logout, or developer convenience button removes a queued payment, and doing so requires an explicit, permissioned, audited action; **payment conflicts are never silently overwritten** and surface to a human with both values; the server is the final source of truth on divergence
- **Related:** THREAT-006, THREAT-013 — **Step 5**

### ABUSE-008 — Fake delivery completion
- **Abuser:** Courier under time pressure, or a dishonest courier
- **Goal:** Close a job without delivering, or without handing over collected cash
- **Path:** Mark the job `DELIVERED` using a reused photograph, a self-drawn signature, or an invented recipient name
- **Harm:** A customer's belongings unaccounted for while the system asserts delivery; cash marked collected that was never handed over
- **Required countermeasure:** **Proof is mandatory for every custody transfer** — no parcel silently changes hands; recipient OTP is the strongest method and is preferred where the tenant enables it; proof carries a server-side capture timestamp and courier identity; duplicate-image detection within an assignment set; `FAILED` is a first-class outcome with its own reason, so the honest path is never harder than the dishonest one; courier cash reconciled per courier per shift with variance recorded and acknowledged
- **Related:** THREAT-014, THREAT-018 — **Step 8**

### ABUSE-009 — Courier viewing unrelated addresses
- **Abuser:** Internal courier or external ojek
- **Goal:** Harvest customer addresses for personal use or resale
- **Path:** Alter a filter or an identifier in the courier surface, or reuse a guest link after the job window, to see stops beyond the assignment
- **Harm:** Bulk address disclosure to someone who travels for a living — a physical-safety concern, not merely a data concern
- **Required countermeasure:** Courier access scoped to the courier's own assignments; **the minimum address detail the job genuinely requires**, never in a shareable or indexable form; no access to customer history, other orders, or pricing; the external guest link is single-assignment, expiring, revocable, tenant-scoped, and non-traversable between tenants; access volume beyond assigned stops alerts
- **Related:** THREAT-027, THREAT-045 — **Step 8**

### ABUSE-010 — Notification spam
- **Abuser:** A tenant over-messaging its own customers, or an attacker triggering sends
- **Goal:** For the tenant, attention; for the attacker, cost and nuisance
- **Path:** Repeatedly trigger status changes that emit messages; run a marketing campaign through the transactional path; or replay a queue so the same reminder sends repeatedly
- **Harm:** Customers annoyed into blocking the tenant; real third-party cost per message; provider-side account risk
- **Required countermeasure:** **Message deduplication keyed on recipient, event, order, and intended send window** so a retry, replay, or double-trigger never produces a second identical message; **quiet hours 20.00–08.00 outlet local time** with deferral rather than dropping or silently sending anyway; transactional and marketing paths separated with separate templates and consent; bounded retries; **provider costs shown transparently to the tenant** and billed separately from the plan; **never any promise of "unlimited WhatsApp"**, because message volume has a real per-message cost
- **Related:** THREAT-037, THREAT-040 — **Step 7**

### ABUSE-011 — Consent bypass
- **Abuser:** Tenant marketer
- **Goal:** Reach customers who opted out of marketing
- **Path:** Send a marketing message through the transactional template; re-import a customer list so opt-out flags are reset; evaluate consent only when the campaign is built rather than when it sends
- **Harm:** Trust and compliance failure; the customer's explicit refusal overridden by a technicality
- **Required countermeasure:** **Opt-out is evaluated at send time, not at campaign-build time**; opt-out is permanent across all outlets of the tenant and **is never reset by a data import**; transactional and marketing categories are structurally separate with separate consent, and a marketing message must never be sent through a transactional path to evade opt-out; consent recorded per customer per tenant with timestamp and source; the unclaimed-laundry ladder is transactional but still respects opt-out and quiet hours
- **Related:** THREAT-020 — **Step 7**, ladder in **Step 9**

### ABUSE-012 — Support impersonation abuse
- **Abuser:** Platform support staff, or an attacker holding a support account
- **Goal:** Browse tenant data without the tenant knowing
- **Path:** Start an impersonation session without a reason, leave it open indefinitely, or use a path that writes no audit record
- **Harm:** Silent access to a tenant's customers, pricing, and revenue by the platform they are paying to trust
- **Required countermeasure:** **Platform support has no silent tenant access.** Impersonation is explicit, reason-required, **time-bound**, and audited with actor, tenant, start, end, and reason; the audit record is append-only and cannot be suppressed by the impersonator; start and end are mandatory security audit events; long or frequent sessions alert; database-level access is governed separately because it bypasses the application audit entirely
- **Related:** THREAT-007 — **Step 12**

### ABUSE-013 — Malicious upload
- **Abuser:** Any user with upload access
- **Goal:** Execute code server-side, exhaust storage, or store content that harms a later viewer
- **Path:** Upload a polyglot file declaring an image content type, an archive bomb, or a payload targeting an image-processing library
- **Harm:** Server compromise, storage exhaustion, or downstream harm to whoever opens the file
- **Required countermeasure:** Validation of **type, size, and content** server-side, never by extension and never by the client-declared content type; re-encoding rather than storing original bytes verbatim; size caps and per-user upload rate limits; user content served from storage that shares no origin with an application surface; tenant-scoped, unguessable object keys
- **Related:** THREAT-015, THREAT-039 — **Step 8**, hardened **Step 13**

### ABUSE-014 — Cross-tenant export
- **Abuser:** Authenticated user of one tenant, often an owner
- **Goal:** Obtain a competitor's customer list, price list, or revenue figures in bulk
- **Path:** Call a report or export endpoint whose query is built outside the tenant-scoped data access layer; or fetch another tenant's generated export file from a predictable object key
- **Harm:** **Complete** disclosure rather than a single record. Business-ending for the victim tenant
- **Required countermeasure:** Reporting and export use the **same tenant-scoped data access layer** as ordinary reads; export files are tenant-scoped with unguessable keys and delivered by signed expiring URL; **exports carry the same access rules as the underlying records**; the owner portfolio dashboard aggregates as a union of individually scoped queries over held memberships and **never widens the query surface**; negative isolation tests target report and export paths specifically
- **Related:** THREAT-023, THREAT-047 — **Step 10**

### ABUSE-015 — Leaked public evidence
- **Abuser:** A maintainer or an AI agent, unintentionally; then anyone on the internet
- **Goal:** None. The harm is accidental and permanent
- **Path:** An evidence pack, example, fixture, or documentation snippet committed to this **PUBLIC** repository contains a real credential, a real phone number, a real address, or a real token
- **Harm:** **Maximal and immediate.** Every file is world-readable and must be assumed mirrored, cached, and indexed. Deletion is not remediation
- **Required countermeasure:** **Every example datum is fictional and recognisably so** — invented, never copied from reality; **only PUBLIC and sanitised INTERNAL material is committed**, while CONFIDENTIAL, RESTRICTED, and SECRET may be described and modelled but never instantiated with real values; evidence packs sanitised before commit, stating that sanitisation occurred; secret scanning in CI at the exact SHA; **rotation first, removal second**; this repository is never described as private
- **Related:** THREAT-028 — **binding now**

### ABUSE-016 — Stale membership
- **Abuser:** A former staff member
- **Goal:** Keep reading a former employer's orders, customers, and pricing
- **Path:** Continue using an unexpired session, or benefit from a cached permission set, after the membership was removed
- **Harm:** Access by exactly the person the tenant took deliberate action to remove
- **Required countermeasure:** **Membership and permission verified server-side on every request** rather than trusted from a token claim; permission caches short-lived and invalidated on membership change; membership removal revokes that tenant's sessions immediately; session and device revocation effective immediately; on the Ops app, the local tenant context is cleared on the next connection; any access by an identity with no current membership alerts, because the correct count is zero
- **Related:** THREAT-042 — **Step 3**

### ABUSE-017 — Role escalation
- **Abuser:** Authenticated staff member
- **Goal:** Acquire refund, void, discount, or price-list permissions
- **Path:** Edit their own membership role, or call a permission-granting endpoint that fails to check the caller's own authority
- **Harm:** Full internal compromise of a tenant, with financial capability attached
- **Required countermeasure:** Role and permission fields are not writable by the subject of the record; **no user may grant themselves a capability they do not already hold**; granting requires strictly higher authority; permission changes audited with actor, target, before, after, and timestamp; authorisation enforced at the API boundary, never only in the UI, because hiding a button is a user-experience affordance and never an access control
- **Related:** THREAT-043 — **Step 3**

### ABUSE-018 — Refund abuse
- **Abuser:** Kasir or manager, possibly with an outside accomplice
- **Goal:** Extract cash through refunds against orders that were never paid, or paid by someone else
- **Path:** Issue refunds at the end of a shift; void completed orders; or delete the payment record so the refund appears to balance
- **Harm:** Direct, repeatable cash loss that a small owner reconciling by hand may not notice for weeks
- **Required countermeasure:** Refund and void **require permission and a recorded reason**, with actor, timestamp, and amount; **financial transactions are never deleted through ordinary UI** — there is no delete-payment button for regular roles; **corrections are reversal or adjustment entries preserving the original**, so the ledger is append-only in effect; a refund can never exceed what was actually received against that order; refund volume per actor is reported and outliers alert
- **Related:** THREAT-019 — **Step 5**, reporting in **Step 10**

### ABUSE-019 — Cash settlement fraud
- **Abuser:** Kasir at shift close, or courier at handover
- **Goal:** Retain collected cash and make the books balance anyway
- **Path:** Under-report collected cash; adjust the expected figure; or record a variance and quietly write it off
- **Harm:** Cash loss concealed inside a reconciliation that looks clean
- **Required countermeasure:** **Shift closing compares expected cash against actual cash, records the variance explicitly, and requires it to be acknowledged rather than hidden**; courier cash tracked per courier per shift from collection through handover; **variance is never masked, auto-rounded away, or suppressed from a report** — a visible discrepancy is a feature and a hidden one is fraud-shaped; expected figures are computed from authoritative financial records, never re-derived independently; adjustments require permission and a reason
- **Related:** THREAT-018 — **Step 8**, reporting in **Step 10**

### ABUSE-020 — Storage-fee abuse
- **Abuser:** A tenant applying a storage or late-collection fee to unclaimed laundry
- **Goal:** Recover holding costs on laundry that has aged past H+14
- **Path:** Fees accrue automatically, are applied retroactively, are not disclosed to the customer, or grow without limit while the reminder ladder runs
- **Harm:** A customer returning after two weeks faces a charge they never agreed to; the ladder becomes a revenue mechanism rather than a recovery mechanism
- **Required countermeasure:** Any storage or late fee is **tenant-configured, disclosed in advance, and applied only under a policy the tenant has set** — the product never invents a fee, a rate, or an escalation; a fee is a financial transaction inheriting **integer Rupiah**, permission, reason, audit, and reversal-only correction; **historical price immunity applies**, so a fee schedule change never re-prices a past order; fees are visible in the amount due on the tracking portal; **the product never automatically discards, sells, auctions, donates, or transfers ownership of laundry regardless of age or unpaid balance** — the escalation ladder ends at reminding, escalating, and reporting
- **Related:** THREAT-012, ABUSE-021 — **Step 9**

### ABUSE-021 — Unclaimed-laundry unlawful disposal
- **Abuser:** A tenant, or a feature request asking the product to help
- **Goal:** Clear shelf space by disposing of laundry nobody collected
- **Path:** A request for the system to auto-flag items for disposal after H+14, schedule an auction, donate items, or transfer ownership after a set period
- **Harm:** The product would automate a decision about someone else's property that is a legal question between the tenant and its customer, not a decision a SaaS product may make
- **Required countermeasure:** **Absolute prohibition.** The system must not implement, schedule, prototype, flag-guard, or suggest any automated disposal, sale, auction, donation, resale, or ownership transfer of a customer's belongings — regardless of age, unpaid balance, or tenant request. Any such implementation, script, backlog item, or TODO is **refused outright and escalated to the repository owner**. The product's role ends at reminding (H+1, H+3), assigning a follow-up task (H+7), escalating to a manager or owner (H+14), and reporting — including the reason not collected. Any future policy here would require an accepted decision record and explicit owner approval and is out of scope for the entire current roadmap
- **Related:** §11.4 — **not applicable to any Step; never to be built**

### ABUSE-022 — Customer account takeover
- **Abuser:** Remote attacker, or whoever now holds a recycled number
- **Goal:** Read a customer's order history and saved addresses, or redirect a delivery
- **Path:** Brute-force the OTP; exploit a SIM swap or number recycling; or reuse a session that outlived a number change
- **Harm:** Disclosure of home address and order history; a delivery redirected to an attacker's address
- **Required countermeasure:** OTP entropy, short expiry, single use, and an attempt counter bound to the OTP record; rate limiting per number and per source with progressive delay and lockout; **sensitive actions such as changing a delivery address require a fresh OTP**, including from the tracking portal; a number change invalidates existing sessions; dormant accounts re-verified; session and device revocation available to the customer
- **Related:** THREAT-001, THREAT-008 — **Step 3**, portal actions in **Step 7**

### ABUSE-023 — OTP brute force
- **Abuser:** Remote attacker with a script
- **Goal:** Guess a valid code before it expires
- **Path:** Request an OTP for a target number, then submit codes at volume; or request many OTPs so that a long-lived code accumulates guessing attempts
- **Harm:** Account takeover; and, as a side effect, real third-party messaging cost and lockout of the legitimate customer
- **Required countermeasure:** Sufficient code length; short expiry; **single use**; the attempt counter bound to the OTP record so re-issuance does not reset it; re-issuance within a window returns the existing valid code rather than minting a new one; **rate limiting fails closed** if the counter store is unavailable, rather than fail open at exactly the moment the system is least healthy; progressive delay and lockout; **the OTP never appears in any log**; failure-rate and lockout-rate metrics with alerting
- **Related:** THREAT-001, THREAT-037, THREAT-041 — **Step 3**, hardened **Step 13**

---

## 3. Coverage summary

| # | Abuse case | Highest related severity | Responsible Step |
| --- | --- | --- | --- |
| ABUSE-001 | Tenant enumeration | CRITICAL | 3 |
| ABUSE-002 | Order-number enumeration | CRITICAL | 7 |
| ABUSE-003 | Tracking token brute force | CRITICAL | 7 |
| ABUSE-004 | Price manipulation | CRITICAL | 5 |
| ABUSE-005 | Payment replay | CRITICAL | 5 |
| ABUSE-006 | Duplicate offline order | MEDIUM | 5 |
| ABUSE-007 | Duplicate payment | CRITICAL | 5 |
| ABUSE-008 | Fake delivery completion | HIGH | 8 |
| ABUSE-009 | Courier viewing unrelated addresses | HIGH | 8 |
| ABUSE-010 | Notification spam | MEDIUM | 7 |
| ABUSE-011 | Consent bypass | MEDIUM | 7 |
| ABUSE-012 | Support impersonation abuse | HIGH | 12 |
| ABUSE-013 | Malicious upload | HIGH | 8 |
| ABUSE-014 | Cross-tenant export | CRITICAL | 10 |
| ABUSE-015 | Leaked public evidence | CRITICAL | binding now |
| ABUSE-016 | Stale membership | HIGH | 3 |
| ABUSE-017 | Role escalation | CRITICAL | 3 |
| ABUSE-018 | Refund abuse | HIGH | 5 |
| ABUSE-019 | Cash settlement fraud | HIGH | 8 / 10 |
| ABUSE-020 | Storage-fee abuse | HIGH | 9 |
| ABUSE-021 | Unclaimed-laundry unlawful disposal | prohibited outright | never |
| ABUSE-022 | Customer account takeover | CRITICAL | 3 / 7 |
| ABUSE-023 | OTP brute force | CRITICAL | 3 |

**Total: 23 abuse cases.**

---

## 4. Related documents

- [`INITIAL_THREAT_MODEL.md`](INITIAL_THREAT_MODEL.md)
- [`TRUST_BOUNDARIES.md`](TRUST_BOUNDARIES.md)
- [`DATA_CLASSIFICATION.md`](DATA_CLASSIFICATION.md)
- [`PRIVACY_REQUIREMENTS.md`](PRIVACY_REQUIREMENTS.md)
- [`SECURITY_ACCEPTANCE_CRITERIA.md`](SECURITY_ACCEPTANCE_CRITERIA.md)
- [`../quality/ACCEPTANCE_CRITERIA.md`](../quality/ACCEPTANCE_CRITERIA.md)
