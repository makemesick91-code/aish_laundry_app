# Security Acceptance Criteria — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED. Backend runtime ABSENT. Flutter workspace ABSENT.
Deployment ABSENT. Application CI NOT APPLICABLE. UAT NOT STARTED.
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §4, §9, §13, §14, §15, §16, §17, §20
**Related decisions:** [DEC-0012](../decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md),
[DEC-0013](../decisions/DEC-0013-exact-sha-evidence-before-go.md),
[DEC-0016](../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)

---

## 1. Purpose and register ownership

This document is the authoritative register of **`SEC-001` … `SEC-068`**, the security requirements of
Aish Laundry App. Every one is a requirement placed on a future roadmap Step. `SEC-001` … `SEC-060` are
stated as requirements in §2 … §10; `SEC-061` … `SEC-068` are stated as Given/When/Then verification
scenarios in §11, each citing the specific threat it verifies.

**No criterion below has been implemented, tested, scanned, or verified.** Every "Verified by" entry
describes a verification that will be performed at the named Step, not one that has occurred. No
security testing of any kind has taken place, because there is no runtime to test.

Each criterion carries: the requirement, how it is to be verified, the responsible roadmap Step, the
related threat or abuse records, and the consequence of failing it.

**Consequence vocabulary.** *NO-GO* means a hard gate failed and work stops (DEC-0012). *Blocks DoD*
means the Step's Definition of Done is not met. *Reject change* means the specific change is refused in
review.

---

## 2. Authorisation and tenant isolation — SEC-001 … SEC-010

| ID | Requirement | Verified by | Step | Related | Failure consequence |
| --- | --- | --- | --- | --- | --- |
| **SEC-001** | Least privilege applies to users, service accounts, storage credentials, and database roles. No principal holds authority it does not need. | Review of granted roles against required operations, recorded at the exact SHA | 3, audited 13 | THREAT-043 | Blocks DoD |
| **SEC-002** | Server-side authorisation is enforced on every protected operation. Client-side hiding of a control is a user-experience affordance and never an access control. | Negative tests calling every protected endpoint directly without the UI | 3 | THREAT-043, ABUSE-017 | Reject change |
| **SEC-003** | Every business table carries `tenant_id`. There are no exceptions for "small" or "lookup" business tables. | Schema review; a table without `tenant_id` is rejected before code depends on it | 3 | THREAT-022 | Reject change |
| **SEC-004** | Every business query is tenant-scoped, enforced **by default at the data access layer**, so a forgotten scope yields nothing rather than another tenant's rows. Fail closed, never fail open. | Negative isolation tests proving a missing scope returns empty, not foreign data | 3 | THREAT-022, THREAT-009 | **NO-GO** |
| **SEC-005** | A client-supplied tenant identifier is never authorisation proof. It is an untrusted hint validated against the authenticated user's memberships. | Tests submitting a foreign tenant identifier from an authenticated session | 3 | THREAT-009, ABUSE-001 | **NO-GO** |
| **SEC-006** | The backend verifies **membership and permission** server-side on every request touching tenant data. Authorisation derives from Membership, never from the user account alone. | Tests with a valid session but no membership in the target tenant | 3 | THREAT-042, ABUSE-016 | **NO-GO** |
| **SEC-007** | A member of tenant A cannot read, list, count, search, filter, export, or mutate any record of tenant B — including via identifier guessing, filter parameters, report endpoints, and file URLs. | Explicit negative tenant-isolation tests across **every** access path; absence of these tests blocks the DoD | 3, then every Step | THREAT-022, 023, ABUSE-014 | **NO-GO** |
| **SEC-008** | Caches, queues, search indexes, exports, report files, uploaded files, and object-storage keys all carry a tenant dimension. A tenant-less cache key is a cross-tenant leak. | Key-construction review plus isolation tests exercising cached and queued paths specifically | 3, then every Step | THREAT-016 | **NO-GO** if exposure occurred |
| **SEC-009** | Background jobs carry explicit tenant context and never infer it from the last request. | Job-dispatch review; tests running jobs out of request context | 3 | THREAT-016 | Blocks DoD |
| **SEC-010** | Global and platform administration is a distinct, audited path. It is **never** implemented by relaxing tenant scoping for ordinary roles, and no scope-bypass parameter or mode exists. | Code-level assertion that no bypass path exists; negative tests reaching for a platform path from a tenant role | 12 | THREAT-044 | **NO-GO** |

---

## 3. Credentials, tokens, and sessions — SEC-011 … SEC-018

| ID | Requirement | Verified by | Step | Related | Failure consequence |
| --- | --- | --- | --- | --- | --- |
| **SEC-011** | Passwords are hashed with a modern, memory-hard, per-password-salted algorithm. No MD5, no SHA-1, no unsalted digest, no home-grown scheme. | Implementation review; a test asserting the algorithm and parameters in use | 3 | THREAT-001 | Reject change |
| **SEC-012** | Server-side tokens — sessions, devices, tracking, guest links — are stored **hashed**, never in plaintext. | Storage inspection test asserting no plaintext token is retrievable from the store | 3, 7, 8 | THREAT-002, 003 | **NO-GO** |
| **SEC-013** | On-device credentials and tokens use Android platform secure storage, never plain shared preferences or plain files. | Client implementation review; instrumentation test on target device class | 3, 11 | THREAT-004 | Blocks DoD |
| **SEC-014** | Session revocation is supported and takes effect immediately server-side. | Test: revoke, then assert the next request with that session fails | 3 | THREAT-004, 042 | Blocks DoD |
| **SEC-015** | Device revocation is supported: one device's access is revoked without forcing every other device to re-authenticate and without a password change. | Test with two registered devices; revoke one, assert the other still works | 3 | THREAT-004 | Blocks DoD |
| **SEC-016** | Membership removal revokes that tenant's sessions immediately, and permission caches are short-lived and invalidated on membership change. | Test: remove membership, assert the next request in that tenant fails | 3 | THREAT-042, ABUSE-016 | **NO-GO** |
| **SEC-017** | The tracking token is high-entropy from a cryptographically secure source, **is not the order number**, and is not derivable from it. Order numbers grant access to nothing. | Entropy review; test asserting an order number submitted as a token is rejected | 7 | THREAT-002, ABUSE-002, 003 | **NO-GO** |
| **SEC-018** | Tracking tokens and guest-link tokens are expiring and revocable, and comparison is constant-time. | Tests for expiry, for revocation taking effect immediately, and review of the comparison | 7, 8 | THREAT-002, 003 | Blocks DoD |

---

## 4. Abuse resistance — SEC-019 … SEC-025

| ID | Requirement | Verified by | Step | Related | Failure consequence |
| --- | --- | --- | --- | --- | --- |
| **SEC-019** | Rate limiting is applied to authentication, OTP issuance, tracking-token lookup, and every public endpoint, per source and globally. | Load-shaped tests asserting limits engage at the defined thresholds | 3, hardened 13 | THREAT-036, 037, ABUSE-003 | Blocks DoD |
| **SEC-020** | Brute-force protection applies progressive delay and lockout on repeated failure. | Test asserting delay growth and lockout after the defined failure count | 3, 13 | THREAT-001, ABUSE-023 | Blocks DoD |
| **SEC-021** | Rate limiting **fails closed** for abusable endpoints when the counter store is unavailable. Losing Redis degrades performance; it never removes a security control. | Test with the counter store unavailable, asserting requests are refused rather than permitted | 13 | THREAT-041 | **NO-GO** |
| **SEC-022** | OTPs are single-use, short-expiry, of adequate length, and their attempt counter is bound to the OTP record so re-issuance does not reset it. Re-issuance within a window returns the existing valid code. | Tests for reuse rejection, expiry, and counter persistence across re-issuance | 3 | THREAT-001, ABUSE-023 | **NO-GO** |
| **SEC-023** | Sensitive actions — including changing a delivery address from the tracking portal — require a fresh OTP. | Test asserting the action is refused without a fresh verification | 7 | THREAT-025, ABUSE-022 | Blocks DoD |
| **SEC-024** | Out-of-tenant records are indistinguishable from absent records. Error codes do not encode existence, and response timing is not materially different. | Response-shape assertions in the negative isolation suite | 3 | THREAT-032, ABUSE-001 | Blocks DoD |
| **SEC-025** | The public tracking portal is served with **`noindex`**, so tracking pages never enter search engines. | Header and meta assertion test; periodic external search during Step 13 | 7 | THREAT-026 | Blocks DoD |

---

## 5. Files, uploads, and object storage — SEC-026 … SEC-030

| ID | Requirement | Verified by | Step | Related | Failure consequence |
| --- | --- | --- | --- | --- | --- |
| **SEC-026** | Uploads are validated server-side by **type, size, and content** — never by file extension and never by the client-declared content type. | Tests submitting a polyglot file and a mislabelled content type, asserting rejection | 8, 13 | THREAT-015, ABUSE-013 | Blocks DoD |
| **SEC-027** | Object storage is never publicly readable or listable for tenant data. | Bucket policy verification recorded at the exact SHA | 8, 13 | THREAT-024 | **NO-GO** |
| **SEC-028** | Private files are served **only** through signed, short-expiry URLs. | Test asserting a direct unsigned fetch fails and an expired signed URL fails | 8, 13 | THREAT-024 | **NO-GO** |
| **SEC-029** | Object keys are tenant-scoped and unguessable. A sequential or predictable key is an enumeration vulnerability. | Key-generation review; enumeration attempt test | 8 | THREAT-024 | **NO-GO** |
| **SEC-030** | Uploaded images are re-encoded rather than stored verbatim, size-capped, and rate-limited per user; storage consumption is accounted per tenant. | Tests for size cap, re-encoding, and upload rate limit | 8, 13 | THREAT-015, 039 | Blocks DoD |

---

## 6. Security controls over money — SEC-031 … SEC-033

These sit alongside the financial-integrity requirements owned by the domain documentation; the three
below are the specifically security-shaped ones.

| ID | Requirement | Verified by | Step | Related | Failure consequence |
| --- | --- | --- | --- | --- | --- |
| **SEC-031** | Payment gateway callbacks are verified server-side for signature and authenticity, with amount and currency checked against the expected order and replay rejected by gateway reference. **An order is never marked paid on a client claim.** | Tests replaying a genuine callback, forging a signature, and altering the amount | 5 | THREAT-006, ABUSE-005 | **NO-GO** |
| **SEC-032** | Refund, void, and discount require permission and a recorded reason, with actor, tenant, outlet, timestamp, and amounts before and after. No delete path exists for financial records in ordinary UI; corrections are reversal or adjustment entries. | Tests asserting permission enforcement, reason requirement, and the absence of a delete path | 5 | THREAT-019, ABUSE-018 | **NO-GO** |
| **SEC-033** | Operations that must not run concurrently — payment application, shift closing, status transitions — are serialized by a lock that **fails closed**. | Concurrency test asserting exactly one payment results from simultaneous submissions | 5 | THREAT-006, 041, ABUSE-007 | **NO-GO** |

---

## 7. Privacy — SEC-034 … SEC-046

The reasoning behind these is in [`PRIVACY_REQUIREMENTS.md`](PRIVACY_REQUIREMENTS.md).

| ID | Requirement | Verified by | Step | Related | Failure consequence |
| --- | --- | --- | --- | --- | --- |
| **SEC-034** | **The public tracking portal never shows a full address**, under any role, configuration, or tenant setting. | Portal response assertion; field-by-field review of the portal payload | 7 | THREAT-025 | **NO-GO** |
| **SEC-035** | Phone numbers and names are masked per context, applied **server-side** in the response the client receives. | Tests asserting the masked form per context | 7 | THREAT-025 | Blocks DoD |
| **SEC-036** | The portal never shows other orders belonging to the same customer, internal notes, laundry photographs, or staff identity beyond the operationally necessary. | Portal payload review and assertion tests | 7 | THREAT-049 | Blocks DoD |
| **SEC-037** | Laundry photographs and delivery proofs are private, tenant-scoped, signed-URL-only, never indexed, and **never used for marketing**. | Access tests; review of every surface that renders an image | 8 | THREAT-024 | **NO-GO** if exposed |
| **SEC-038** | Proof of pickup and proof of delivery are **mandatory for every custody transfer**. No custody transfer can be recorded without proof. | Test asserting the transition to `PICKED_UP` and `DELIVERED` is refused without proof | 8 | THREAT-014, ABUSE-008 | Blocks DoD |
| **SEC-039** | Courier access is scoped to the courier's own assignments and shows the **minimum address detail the job genuinely requires**, never in a shareable or indexable form. | Negative tests attempting to reach unassigned stops | 8 | THREAT-027, ABUSE-009 | **NO-GO** |
| **SEC-040** | The external courier guest link is single-assignment, minimum-privilege, tenant-scoped, and non-traversable. It grants no access to customer history, other orders, pricing, or any tenant data beyond the assignment; scope is re-derived from the token, never from a request parameter. | Negative tests altering identifiers within a guest-scoped session | 8 | THREAT-003, 045 | **NO-GO** |
| **SEC-041** | Marketing consent is recorded per customer per tenant with timestamp and source, held separately from transactional consent. | Consent record assertions; test that consent in one tenant does not apply in another | 7 | THREAT-020, ABUSE-011 | Blocks DoD |
| **SEC-042** | Opt-out takes effect immediately, is evaluated **at send time**, holds across all outlets of the tenant, and is **never reset by a data import**. A marketing message is never sent through a transactional path. | Tests: opt out then send; import a list then assert opt-out survives | 7 | ABUSE-011 | Blocks DoD |
| **SEC-043** | **Quiet hours default 20.00–08.00 outlet local time.** Non-urgent messages queued in the window are deferred to the next permitted window — never dropped, never silently sent. | Scheduler tests across the boundary, including a drained backlog | 7 | THREAT-040, ABUSE-010 | Blocks DoD |
| **SEC-044** | Message deduplication is keyed on recipient, event, order, and intended send window, so a retry, queue replay, or scheduler restart never produces a second identical message. | Replay and restart tests asserting exactly one send | 7, ladder 9 | THREAT-040, ABUSE-010 | Blocks DoD |
| **SEC-045** | Message content carries no full address, no token, and no OTP echoed back, and no sensitive data beyond what the recipient already owns. | Template review and content assertion tests | 7 | THREAT-035 | **NO-GO** if a token or OTP is echoed |
| **SEC-046** | **Tenant data remains exportable per policy when a subscription lapses**, and exports carry the same access rules as the underlying records. | Test exporting under a lapsed subscription; access-rule assertions on the export | 12 | ABUSE-014 | Reject change |

---

## 8. Logging, audit, and observability — SEC-047 … SEC-052

| ID | Requirement | Verified by | Step | Related | Failure consequence |
| --- | --- | --- | --- | --- | --- |
| **SEC-047** | **Logs never contain passwords, OTPs, tokens, or credentials** — not at debug level, not temporarily, not on a local branch. Redaction is enforced at the logging boundary. | Log-content assertion tests; sampled review at the exact SHA | 13, binding from 3 | THREAT-029 | **NO-GO** |
| **SEC-048** | Telemetry records tenant context as an identifier only, never as personal data, against a defined allow-list of attribute keys. | Attribute allow-list enforcement test | 13 | THREAT-031 | Blocks DoD |
| **SEC-049** | Every request carries a correlation identifier that flows through queues and background jobs, so one customer interaction is traceable end to end. | Trace-completeness sampling across a queued flow | 13 | THREAT-021 | Blocks DoD |
| **SEC-050** | Financial operations are audited **separately** from application logs, on an append-only trail not subject to log rotation. | Append-only assertion; attempted-mutation test | 13, records from 5 | THREAT-017 | **NO-GO** |
| **SEC-051** | Security events are audited: authentication failures, permission denials, session and device revocation, support impersonation start and end, and tracking-token issuance and revocation. | Event-coverage assertion per listed event | 13, records from 3 | THREAT-007, 042, 043 | Blocks DoD |
| **SEC-052** | Alerting covers symptoms customers feel — failed payments, undelivered notifications, queue backlog, error-rate spikes, authentication failure and lockout rates. An alert nobody acts on is deleted. | Alert inventory review with a named owner per alert | 13 | THREAT-036, 040 | Blocks DoD |

---

## 9. Repository, supply chain, and evidence — SEC-053 … SEC-057

| ID | Requirement | Verified by | Step | Related | Failure consequence |
| --- | --- | --- | --- | --- | --- |
| **SEC-053** | **No secrets, credentials, tokens, keys, connection strings, or customer data appear anywhere in this repository** — not in code, docs, examples, fixtures, evidence packs, commit messages, or issues. | Secret scanning in CI at the exact SHA under review | binding now | THREAT-028, ABUSE-015 | **NO-GO**; rotate first, remove second |
| **SEC-054** | Only `PUBLIC` and sanitised `INTERNAL` material is committed. `CONFIDENTIAL`, `RESTRICTED`, and `SECRET` may be described and modelled but **never instantiated with real values**. Every example datum is fictional and recognisably so. | Authoring review against [`DATA_CLASSIFICATION.md`](DATA_CLASSIFICATION.md) §6; scanner support | binding now | ABUSE-015 | **NO-GO** |
| **SEC-055** | Evidence packs are sanitised before commit and state that sanitisation occurred. Evidence is bound to the **exact 40-character commit SHA**, with the exact command, captured output, timestamp, and environment. Evidence at one SHA never carries over to another. | Evidence policy validation at the reviewed SHA (DEC-0013) | binding now | THREAT-017 | **NO-GO** if fabricated |
| **SEC-056** | GitHub Actions are pinned to a full commit SHA, never a floating tag. Workflow `permissions:` are set explicitly, defaulting to `contents: read`. Workflows never echo secrets, never push to `main`, never create or move tags, and never change repository settings or visibility. | Workflow review at every change; an unpinned action is rejected | binding now | THREAT-048 | Reject change |
| **SEC-057** | Configuration and secrets come from the environment, never from a committed file. A leaked secret is **rotated first and discussed second**; removal alone is not remediation on a public repository. | Configuration review; incident procedure documented and rehearsed in Step 13 | binding now | THREAT-028 | **NO-GO** |

---

## 10. Support access, backup, and recovery — SEC-058 … SEC-060

| ID | Requirement | Verified by | Step | Related | Failure consequence |
| --- | --- | --- | --- | --- | --- |
| **SEC-058** | **Platform support has no silent tenant access.** Impersonation is explicit, reason-required, time-bound, and audited with actor, tenant, start, end, and reason, on a record the impersonator cannot suppress. | Tests asserting an impersonation session cannot begin without a reason and always writes start and end events | 12 | THREAT-007, ABUSE-012 | **NO-GO** |
| **SEC-059** | Backups are encrypted at rest and in transit, with keys held separately from the backup store, and restore targets are access-controlled to the production standard. | Encryption and access-policy verification at the exact SHA | 13 | THREAT-033 | **NO-GO** if exposed |
| **SEC-060** | **Restore is exercised, not assumed.** A restore drill is performed and its unedited output recorded as evidence bound to the exact SHA. | Restore drill evidence pack | 13 | THREAT-033 | Blocks DoD |

---

## 11. Threat-linked verification scenarios — SEC-061 … SEC-068

The criteria in §2 … §10 are stated as requirements. The eight below are stated as **scenarios**, because
each verifies a threat whose failure mode is behavioural rather than structural — the control either
holds under a specific sequence of events or it does not, and a table row cannot express the sequence.

Each cites the threat it verifies **literally by identifier**, builds on an existing criterion from the
register above, and states the negative path — the attack being denied — rather than only the happy
path. None has been executed; all are requirements on the named Step.

### SEC-061 — Server-authoritative totals defeat client price manipulation
- **Verifies:** THREAT-010 (CRITICAL)
- **Builds on:** SEC-002, SEC-032
- **Step:** 5

**Given** an order is being created in `Tenant Contoh A` against a server-side price list,
**When** a modified client submits the order with an altered line price, an altered total, and an
unauthorised discount,
**Then** the server recomputes every figure from its own price list, stores only its own computed
integer Rupiah values, and records the order at the price that applied at creation.

**Negative path — the attack is denied.** The client-supplied total must **not** be stored, echoed back
as authoritative, or used in any downstream calculation. The unauthorised discount must be **refused**,
not silently applied or clamped. No floating-point value may appear anywhere in the pricing, total,
discount, or tax path. A stored total that differs from the server recomputation is a financial
integrity failure and an **automatic NO-GO**.

### SEC-062 — Invalid status transitions are refused server-side
- **Verifies:** THREAT-011 (HIGH)
- **Builds on:** SEC-002, SEC-051
- **Step:** 6

**Given** an order at status `RECEIVED` in `Tenant Contoh A`,
**When** a client submits a transition directly to `COMPLETED`, and separately a transition to a status
outside the canonical fifteen, and separately a valid transition by a role lacking the permission,
**Then** all three are refused at the API boundary with a stable machine-readable error code, and each
refusal is recorded as a permission-denial or validation audit event.

**Negative path — the attack is denied.** The order must **not** reach `COMPLETED` without passing
through `READY_FOR_PICKUP`, so no order can exist in a completed state with no first-ready timestamp.
The transition must **not** be accepted because the client asserted its own permission, and must
**not** be enforceable only by hiding the control in the UI.

### SEC-063 — The first-ready timestamp cannot be reset to hide aging
- **Verifies:** THREAT-012 (HIGH)
- **Builds on:** SEC-002, SEC-050, SEC-051
- **Step:** 6, aging consumed in 9

**Given** an order whose first arrival at `READY_FOR_PICKUP` recorded a first-ready timestamp,
**When** the order is returned to `REWORK` and again reaches `READY_FOR_PICKUP`, and separately when a
client attempts to write the first-ready field directly,
**Then** the original timestamp is unchanged in both cases, the aging clock continues from it, and the
direct write is refused.

**Negative path — the attack is denied.** The aging clock must **not** restart, so the H+7 and H+14
stages must still fire on schedule for an order that was bounced through rework. No ordinary-UI edit
path to the field may exist. Any correction must be an audited, permissioned adjustment recorded with
actor, timestamp, before and after — never a silent rewrite. An order whose ladder stages were skipped
because of a reset anchor is a defect requiring a regression test.

### SEC-064 — Courier cash variance is surfaced, never absorbed
- **Verifies:** THREAT-018 (HIGH)
- **Builds on:** SEC-032, SEC-050
- **Step:** 8, reported in 10

**Given** a courier collected cash at the door across several deliveries in one shift, recorded as
integer Rupiah against the courier, the shift, and each order,
**When** the courier hands over an amount less than the recorded total,
**Then** the reconciliation compares expected against actual, computes the variance explicitly, records
it against the courier and the shift, and requires an acknowledgement before the shift can close.

**Negative path — the attack is denied.** The variance must **not** be auto-rounded away, written off
silently, absorbed into another figure, or suppressed from any report. The courier must **not** be able
to close a shift with an unacknowledged variance. No collection or handover record may be deleted; a
correction is a reversal or adjustment entry preserving the original. A hidden variance is treated as a
financial integrity failure.

### SEC-065 — Report and export paths cannot leak across tenants
- **Verifies:** THREAT-023 (CRITICAL)
- **Builds on:** SEC-004, SEC-007, SEC-029, SEC-046
- **Step:** 10

**Given** a user authenticated in `Tenant Contoh A`, and report and export data existing for
`Tenant Contoh B`,
**When** the user invokes every reporting and export endpoint — including aggregate, drill-down, and
file-generating paths — with parameters naming or ranging over `Tenant Contoh B`,
**Then** every response contains only `Tenant Contoh A` data, and every generated file is written under
a tenant-scoped unguessable key and delivered only by signed expiring URL.

**Negative path — the attack is denied.** No aggregate may include a single figure derived from
`Tenant Contoh B`. No generated export file may be fetched by a user of another tenant, by key guessing,
or by an unsigned URL. Reporting queries must **not** be built outside the tenant-scoped data access
layer, and a hand-written analytics query is treated as production code subject to the same rule. Bulk
cross-tenant disclosure is an **automatic NO-GO** under DEC-0012.

**Extension covering THREAT-047 (HIGH).** The same scenario is run a second time with the user holding
memberships in **both** `Tenant Contoh A` and `Tenant Contoh B`, and a third tenant `Tenant Contoh C`
existing in which they hold none. The consolidated portfolio figures must then equal the sum of the two
individually tenant-scoped queries, and must contain nothing from `Tenant Contoh C`. The dashboard must
**not** be implemented as one broad query filtered afterwards — a defect in such a filter exposes every
tenant at once, which is why the union approach is required despite its performance cost. Convenience,
performance, reporting need, and demo pressure are never sufficient reason to widen the query surface.

### SEC-066 — A tenant or user switch leaves no readable cached data
- **Verifies:** THREAT-030 (HIGH)
- **Builds on:** SEC-007, SEC-013, SEC-016
- **Step:** 5

**Given** a staff member holds memberships in `Tenant Contoh A` and `Tenant Contoh B` on one Ops device,
with cached orders, cached customers, and a pending financial queue accumulated in `Tenant Contoh A`,
**When** they switch to `Tenant Contoh B`, browse and search every screen, and then return online so the
queue drains,
**Then** no record originating in `Tenant Contoh A` is reachable while `Tenant Contoh B` is active, and
every queued operation syncs into `Tenant Contoh A`, carrying the tenant context it was created with.

**Negative path — the attack is denied.** Cached records must **not** survive the switch in readable
form, including in search indexes and list caches on device. A queued operation must **not** be
re-attributed to whichever tenant is active when the queue drains. Local data separation is per tenant
**and** per user, so a second user on the same device sees nothing from the first. Any leak is treated
as a tenant-isolation defect and is an **automatic NO-GO**.

### SEC-067 — Upload volume cannot exhaust storage or evade accounting
- **Verifies:** THREAT-039 (MEDIUM), and the upload-validation path shared with THREAT-015 (HIGH)
- **Builds on:** SEC-026, SEC-030
- **Step:** 8, limits enforced in 12

**Given** an authenticated operational user with upload access in `Tenant Contoh A`,
**When** they submit files exceeding the size cap, submit valid files at a rate far above operational
need, and submit a file whose declared content type does not match its actual content,
**Then** oversize files are refused, the upload rate limit engages, the mismatched file is refused on
content inspection rather than on extension or declared type, and all accepted storage is accounted
against `Tenant Contoh A`.

**Negative path — the attack is denied.** An oversize or mislabelled file must **not** be persisted even
transiently in a location that is servable. Storage consumed must **not** be unattributed to a tenant.
The rate limit must **not** fail open when its counter store is unavailable. Accepted images must be
re-encoded rather than stored verbatim, so a payload targeting an image parser is not preserved for a
later viewer.

### SEC-068 — A guest courier session cannot traverse beyond its assignment
- **Verifies:** THREAT-045 (HIGH)
- **Builds on:** SEC-018, SEC-039, SEC-040
- **Step:** 8

**Given** an external ojek holds a valid guest link for exactly one delivery assignment in
`Tenant Contoh A`, and separately holds an unrelated guest link in `Tenant Contoh B`,
**When** within the first guest session they alter order identifiers, assignment identifiers, customer
identifiers, and file references in requests, and attempt to reach pricing, customer history, and the
second tenant's assignment,
**Then** every altered request returns nothing, because the session's scope is re-derived from the token
on every request rather than read from any request parameter.

**Negative path — the attack is denied.** No request within the guest session may return another order,
another assignment, any customer history, any pricing, or any data from `Tenant Contoh B`. The link must
expire, must be revocable with immediate effect, must be stored hashed, and must not be the order number
or derivable from it. Confirmed access beyond the assignment is an **automatic NO-GO**.

---

## 12. Register summary

| Group | Range | Count |
| --- | --- | --- |
| Authorisation and tenant isolation | SEC-001 … SEC-010 | 10 |
| Credentials, tokens, sessions | SEC-011 … SEC-018 | 8 |
| Abuse resistance | SEC-019 … SEC-025 | 7 |
| Files, uploads, object storage | SEC-026 … SEC-030 | 5 |
| Security controls over money | SEC-031 … SEC-033 | 3 |
| Privacy | SEC-034 … SEC-046 | 13 |
| Logging, audit, observability | SEC-047 … SEC-052 | 6 |
| Repository, supply chain, evidence | SEC-053 … SEC-057 | 5 |
| Support access, backup, recovery | SEC-058 … SEC-060 | 3 |
| Threat-linked verification scenarios | SEC-061 … SEC-068 | 8 |
| **Total** | **SEC-001 … SEC-068** | **68** |

Criteria whose failure is an **automatic NO-GO**: SEC-004, 005, 006, 007, 008, 010, 012, 016, 017, 021,
022, 027, 028, 029, 031, 032, 033, 034, 037, 039, 040, 045, 047, 050, 053, 054, 055, 057, 058, 059,
061, 065, 066, 068.

### 12.1 Threat coverage of the CRITICAL and HIGH register

Every CRITICAL and HIGH threat in
[`INITIAL_THREAT_MODEL.md`](INITIAL_THREAT_MODEL.md) is cited by at least one criterion here or by a
scenario in [`../quality/ACCEPTANCE_CRITERIA.md`](../quality/ACCEPTANCE_CRITERIA.md). The eight added in
§11 close the gaps: THREAT-010, THREAT-011, THREAT-012, THREAT-018, THREAT-023, THREAT-030, THREAT-039,
and THREAT-045.

No severity was lowered and no threat was restated to make a criterion easier to write.

---

## 13. Standing exclusions

- **No criterion authorises, models, or prepares for automatic disposal, sale, auction, donation, or
  ownership transfer of customer laundry.** That is an absolute prohibition (ABUSE-021).
- **No criterion asserts route optimisation, guaranteed delivery time, or ETA accuracy.** The product
  provides route *suggestion* only.
- **No criterion may be placed behind a paid tier.** The security baseline, tenant isolation, and backup
  are available on every plan including Starter; placing any of them behind a tier breaches a pricing
  guardrail and is rejected outright.

---

## 14. Related documents

- [`INITIAL_THREAT_MODEL.md`](INITIAL_THREAT_MODEL.md)
- [`ABUSE_CASES.md`](ABUSE_CASES.md)
- [`DATA_CLASSIFICATION.md`](DATA_CLASSIFICATION.md)
- [`TRUST_BOUNDARIES.md`](TRUST_BOUNDARIES.md)
- [`PRIVACY_REQUIREMENTS.md`](PRIVACY_REQUIREMENTS.md)
- [`../quality/NON_FUNCTIONAL_REQUIREMENTS.md`](../quality/NON_FUNCTIONAL_REQUIREMENTS.md)
- [`../quality/ACCEPTANCE_CRITERIA.md`](../quality/ACCEPTANCE_CRITERIA.md)
- [`../quality/STEP_01_DEFINITION_OF_DONE.md`](../quality/STEP_01_DEFINITION_OF_DONE.md)
