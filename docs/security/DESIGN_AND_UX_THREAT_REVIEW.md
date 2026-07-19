# Design and UX Threat Review — Step 2

**Step:** 2 — Design System and UX Foundation
**Status of this document:** `IN PROGRESS` (Step 2 is `IN PROGRESS`; `GO` is owner-conferred)
**Master Source version:** 1.3.0 · baseline 19 July 2026
**Method:** STRIDE-derived, applied to interface and interaction design rather than to runtime components.

---

## 1. What this document is, and what it is not

This is a **security and privacy review of the design and UX foundation** produced in Step 2. It examines
the ways an interface can leak data, mislead a user about money, exclude a person, or invite an attacker —
independently of whether the code that would implement it exists.

**It is not a penetration test, a runtime audit, a code review, or an accessibility audit.** No runtime
exists. The backend is `ABSENT`, the Flutter workspace is `ABSENT`, the database is `ABSENT`, and
deployment is `ABSENT`. Every mitigation recorded here is a **design obligation**, not an implemented
control. A finding marked `MITIGATED-BY-DESIGN` means the design foundation forbids the failure mode; it
does **not** mean any code enforces it, because there is no code.

Accessibility statements in this review carry the exact wording:
**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**

### 1.1 Governance mode — stated plainly

**This review is internal re-verification under single-maintainer governance. It is NOT independent peer
review, and it is not an approval.** Independent human approval is `ABSENT`, a standing accepted deviation
recorded in [`../decisions/DEC-0017-single-maintainer-approval-standing-deviation.md`](../decisions/DEC-0017-single-maintainer-approval-standing-deviation.md).
The compensating controls — the active ruleset, exact-SHA CI, deterministic validators, adversarial
validator testing — are load-bearing rather than supplementary, and they are **not equivalent** to a second
human reader. A design defect that both the maintainer and the validators miss is not caught. That residual
risk is accepted, not eliminated.

### 1.2 Public repository constraint

This repository is **PUBLIC**, an accepted deviation from a canonical desired **PRIVATE**
(AMENDMENT-0001, [`../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md`](../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)).
PUBLIC is **not** the desired end state. Every example datum below is **fictional and recognisably so**.
No real customer name, phone number, address, token, or credential appears anywhere in this document, and
none may ever be added to it.

---

## 2. Scope

**Surfaces reviewed:** Aish Laundry Customer Android · Aish Laundry Ops Android · Aish Laundry Console Web ·
Portal Tracking Publik · the external-courier guest link.

**Inputs:** [`INITIAL_THREAT_MODEL.md`](INITIAL_THREAT_MODEL.md) (THREAT-001 … THREAT-050),
[`DATA_CLASSIFICATION.md`](DATA_CLASSIFICATION.md), [`ABUSE_CASES.md`](ABUSE_CASES.md),
[`TRUST_BOUNDARIES.md`](TRUST_BOUNDARIES.md), [`PRIVACY_REQUIREMENTS.md`](PRIVACY_REQUIREMENTS.md),
[`../product/PRODUCT_REQUIREMENTS.md`](../product/PRODUCT_REQUIREMENTS.md).

**Finding identifiers** use the prefix `DUX-` and are **permanent**. A closed finding keeps its ID and
gains a closure note. An ID is never reused.

---

## 3. Severity summary

| Severity | Count | OPEN | MITIGATED-BY-DESIGN | ACCEPTED |
| --- | --- | --- | --- | --- |
| CRITICAL | 8 | **0** | 8 | 0 |
| HIGH | 13 | **0** | 13 | 0 |
| MEDIUM | 10 | 0 | 7 | 3 |
| LOW | 3 | 0 | 1 | 2 |
| INFORMATIONAL | 2 | 1 | 0 | 1 |
| **Total** | **36** | **1** | **29** | **6** |

**Closure position:** 0 CRITICAL open · 0 HIGH open · every MEDIUM either mitigated by design or accepted
with recorded rationale · every LOW documented · every INFORMATIONAL documented. Every CRITICAL and HIGH
finding traces to a concrete UX mitigation and to at least one requirement ID. The single OPEN item is
`DUX-036`, INFORMATIONAL, and is an outstanding process step rather than a design defect.

### 3.1 Severity definitions used here

| Severity | Meaning in a design review |
| --- | --- |
| CRITICAL | The design, if built as drawn, would produce a hard-gate breach — cross-tenant exposure, financial misstatement, or personal-data disclosure. |
| HIGH | The design would produce a serious privacy, financial-trust, or exclusion harm short of a hard-gate breach. |
| MEDIUM | The design would produce a real but recoverable harm, or would materially increase the chance of user error. |
| LOW | A defect of clarity or discipline with limited direct harm. |
| INFORMATIONAL | No harm identified; recorded so that a later reader can see it was considered. |

Severity is **argued below, not asserted**. A reader is expected to be able to disagree with a rating on
the reasoning given.

---

## 4. Findings

### DUX-001 — Tenant context not visible on screen

| Field | Value |
| --- | --- |
| **Area** | Tenant and outlet context |
| **Affected surface** | Ops Android, Console Web |
| **Severity** | **CRITICAL** |
| **Linked threat** | THREAT-009, THREAT-030 |
| **Requirements** | TEN-001, TEN-004, TEN-011, FR-004, SEC-002 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** A user who belongs to more than one tenant can act inside the wrong tenant if the active
tenant is not permanently visible. The failure is silent: the screen looks correct, the data looks
plausible, and the operator discovers the mistake only when a customer complains.

**Impact (argued).** An operator creating an order, taking cash, or exporting a customer list under the
wrong tenant produces records in a business that did not transact them. The correction is a reversal
entry in one tenant and a creation in another, and the customer data has already been read across the
boundary by a human. Under Rule 02 any cross-tenant exposure is an automatic `NO-GO`, so the ceiling on
this finding is the hard gate itself — hence CRITICAL rather than HIGH.

**Likelihood (argued).** High for the multi-tenant owner persona, which the product explicitly supports
(DEC-0003). The hierarchy `User Account -> Membership -> Tenant/Organization -> Laundry Brand -> Outlet`
guarantees that a single identity legitimately holds several contexts, so the confusion is a normal
operating condition, not an edge case.

**UX mitigation.** The active tenant is rendered persistently in the primary application chrome on every
authenticated screen of Ops Android and Console Web — never only on a settings page and never only inside
a collapsed menu. The tenant name is text, never a colour swatch alone. Tenant switching is an explicit,
deliberate action that requires confirmation, clears the visible working set, and announces the new
context to assistive technology. Any screen that writes data renders the tenant name within the same
visual block as the primary action.

---

### DUX-002 — Ambiguous outlet context within a tenant

| Field | Value |
| --- | --- |
| **Area** | Tenant and outlet context |
| **Affected surface** | Ops Android, Console Web |
| **Severity** | **HIGH** |
| **Linked threat** | THREAT-009 |
| **Requirements** | TEN-006, TEN-007, FR-011, FIN-018 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** A tenant may operate several brands and many outlets. A staff member who can see more
than one outlet may record an order, a shift closing, or a cash handover against the wrong outlet.

**Impact (argued).** This is contained **inside** one tenant, so it is not a tenant-isolation breach and
therefore not CRITICAL. It is nevertheless HIGH because the damage lands on money: a shift closing booked
to the wrong outlet produces a false cash variance in two outlets simultaneously, and the corrective path
is a reversal entry in each. Unclaimed-laundry aging and reminder targeting also read outlet, so the error
propagates into customer-facing messaging.

**Likelihood (argued).** Moderate. Most counter staff work one outlet; managers and owners routinely move
between several, and they are precisely the roles that perform shift closing.

**UX mitigation.** Outlet is displayed alongside tenant in the persistent context strip whenever the
signed-in user has access to more than one outlet. Any financial action — payment, refund, void, shift
closing, courier cash handover — restates the outlet name inside the confirmation surface, not merely in
the page chrome. Where a user has access to exactly one outlet, the outlet is still shown but is not
presented as a control, so the interface never implies a choice that does not exist.

---

### DUX-003 — Personal data over-exposed in lists, search results, and reports

| Field | Value |
| --- | --- |
| **Area** | PII exposure |
| **Affected surface** | Ops Android, Console Web |
| **Severity** | **HIGH** |
| **Linked threat** | THREAT-023, THREAT-031 |
| **Requirements** | SEC-014, SEC-021, TRK-009, NFR-031 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** Order lists, customer search, courier manifests, and printed or exported reports tend to
accumulate personal data because each field is individually defensible. The aggregate is not: a single
screen showing full phone numbers and full addresses for every open order is a tenant's customer database
in one photograph.

**Impact (argued).** Customer phone is `CONFIDENTIAL`; customer address is `RESTRICTED`; and per
[`DATA_CLASSIFICATION.md`](DATA_CLASSIFICATION.md) **aggregation raises class** — a full list is RESTRICTED
even where each row is CONFIDENTIAL. Disclosure harms customers directly and exposes the tenant's book of
business to a competitor. It is HIGH rather than CRITICAL because the exposure stays within an authorised
tenant boundary; it becomes CRITICAL the moment it crosses one, which is `DUX-001`.

**Likelihood (argued).** High in the absence of a rule, because every individual field has a plausible
operational justification and nobody is accountable for the total.

**UX mitigation.** Lists show **masked** identifiers by default (see
[`../ux/SECURITY_AND_PRIVACY_UX.md`](../ux/SECURITY_AND_PRIVACY_UX.md)). Unmasking is a deliberate,
per-record action, is available only to roles that need it, and is recorded. Address is never rendered in
a list row; it appears only on a record detail view and only for roles with a delivery or pickup reason.
Screens that would otherwise render more than a screenful of personal data present a count and require a
filter, so a broad sweep is a decision rather than a default.

---

### DUX-004 — Tracking token leaked through the interface

| Field | Value |
| --- | --- |
| **Area** | Tracking token handling |
| **Affected surface** | Portal Tracking Publik, Customer Android, Ops Android |
| **Severity** | **CRITICAL** |
| **Linked threat** | THREAT-002, THREAT-026, THREAT-029, THREAT-031 |
| **Requirements** | TRK-003, TRK-004, TRK-011, SEC-030, SEC-041 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** The plaintext tracking token is a bearer credential. Interfaces leak bearer credentials in
predictable ways: page titles, breadcrumb text, analytics page-path fields, crash reports, screenshots
shared in a chat group, referrer headers on outbound links, and "copy link" affordances offered without
context.

**Impact (argued).** A leaked token grants a stranger the customer's order view. The token is classed
`SECRET`; per Rule 21 a `SECRET` value is never logged, never emitted in an event, never placed in
telemetry, and never committed. Any design that routes it into an analytics field is a disclosure by
construction, which is why this is CRITICAL rather than HIGH.

**Likelihood (argued).** High without an explicit prohibition, because sending the full URL to analytics
is the default behaviour of essentially every web analytics integration.

**UX mitigation.** The token value never appears in a page title, a heading, a breadcrumb, a visible label,
an analytics path or parameter, an error message, or a support form. The portal carries `noindex`, and
outbound links carry `noreferrer`. The customer-facing affordance is "Bagikan tautan lacak" with an
explicit warning that anyone holding the link can see the order; the interface never presents the raw
token as copyable text. Ops surfaces display a tracking **status** and a revoke control, never the token.

---

### DUX-005 — Full address rendered on the public tracking portal

| Field | Value |
| --- | --- |
| **Area** | Address exposure |
| **Affected surface** | Portal Tracking Publik |
| **Severity** | **CRITICAL** |
| **Linked threat** | THREAT-025, THREAT-026 |
| **Requirements** | TRK-009, TRK-010, SEC-022, DEL-019 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** The public tracking projection is reachable by anyone holding a link. If it renders the
full delivery address, the link becomes a physical-location disclosure about a named person.

**Impact (argued).** Address is `RESTRICTED` precisely because its disclosure carries a physical-safety
dimension, not merely a privacy one. A tracking link is routinely forwarded through family and building
WhatsApp groups, so the realistic audience is far wider than the customer. This is a personal-data
exposure on a public surface, an automatic `NO-GO` class under Rule 03 — CRITICAL is the only defensible
rating.

**Likelihood (argued).** High if unstated, because a delivery interface naturally wants to show the
destination, and the portal shares its data shape with the authenticated order view.

**UX mitigation.** The public tracking projection is a **separate projection**, not a filtered view of the
internal order. It is defined by an allow-list of fields; a field absent from the allow-list cannot appear
because it is never assembled. Address is rendered at area granularity only, in the form
`Kelurahan Sukamaju, Bandung` — never a house number, never a map pin at building resolution. The full
address exists only inside authenticated, tenant-scoped, role-gated surfaces.

---

### DUX-006 — Clipboard exposure of sensitive values

| Field | Value |
| --- | --- |
| **Area** | Clipboard |
| **Affected surface** | Ops Android, Customer Android, Console Web |
| **Severity** | **MEDIUM** |
| **Linked threat** | THREAT-004, THREAT-031 |
| **Requirements** | SEC-033, TRK-011, NFR-033 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** A "copy" control places a value into a system clipboard that is readable by other
applications, may synchronise across devices, and persists after the source screen closes.

**Impact (argued).** Bounded: the clipboard is device-local and requires a second malicious or careless
actor on the same device to become a disclosure. It is not CRITICAL because it does not by itself cross a
trust boundary. It is not LOW because a copied tracking link or customer phone number is exactly the kind
of value that ends up pasted into a personal chat.

**Likelihood (argued).** Moderate. Copy affordances are convenient and staff use them constantly; clipboard
history features are now default on many Android builds.

**UX mitigation.** Copy controls are offered only where copying is the intended workflow. Copying a tracking
link or a masked customer phone shows a brief non-blocking notice stating what was copied and that the
clipboard is shared with other applications. OTP codes, session tokens, and raw tracking tokens have **no**
copy affordance at all. No screen auto-populates the clipboard.

---

### DUX-007 — Notification preview reveals sensitive content on a locked screen

| Field | Value |
| --- | --- |
| **Area** | Notification preview |
| **Affected surface** | Customer Android, Ops Android |
| **Severity** | **HIGH** |
| **Linked threat** | THREAT-035, THREAT-004 |
| **Requirements** | NOT-014, SEC-034, SEC-035, TRK-011 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** Push notifications render on a locked device to whoever is holding it. A notification body
carrying an OTP, a tracking link, an address, or an amount discloses that value without authentication.

**Impact (argued).** An OTP in a lock-screen preview defeats the second factor entirely, which turns a
usability convenience into an account-takeover path. That is why this is HIGH rather than MEDIUM despite
requiring physical proximity to the device.

**Likelihood (argued).** High if unconstrained, because notification copy is usually written for clarity by
someone who is not thinking about the lock screen.

**UX mitigation.** Push notification payloads carry the **minimum** needed to bring the user into the app:
an event type and a non-identifying reference. They never carry an OTP, a tracking token, a full address, a
full phone number, a payment amount, or a customer's full name. Sensitive detail is retrieved after
authentication, inside the app. This constraint is restated as a hard rule in
[`../ux/SECURITY_AND_PRIVACY_UX.md`](../ux/SECURITY_AND_PRIVACY_UX.md).

---

### DUX-008 — External courier guest surface exposes more than the assignment

| Field | Value |
| --- | --- |
| **Area** | External courier access |
| **Affected surface** | External-courier guest link |
| **Severity** | **CRITICAL** |
| **Linked threat** | THREAT-003, THREAT-027, THREAT-045 |
| **Requirements** | DEL-021, DEL-022, DEL-023, SEC-046, TEN-019 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** The guest link given to an external ojek is an unauthenticated bearer credential in the
hands of a party outside the tenant. Any navigation, list, search box, back-link, or "other jobs" affordance
on that surface widens it beyond the single assignment.

**Impact (argued).** A guest surface that permits traversal is a cross-tenant exposure path in the most
literal sense: an external courier working for two competing laundries could pivot from one to the other.
Rule 02 makes that an automatic `NO-GO`. Even without traversal, browsing unrelated customer addresses is a
`RESTRICTED`-class disclosure to a non-employee.

**Likelihood (argued).** High without an explicit design constraint, because a courier screen naturally
wants a "my jobs" list, and a "my jobs" list is exactly the traversal surface.

**UX mitigation.** The guest surface renders **one assignment** and nothing else. It has no navigation
chrome, no search, no history, no customer profile, no pricing, no order value, and no link to any other
record. Address is shown at the granularity the delivery genuinely requires and is not selectable as text
or shareable as a link. The surface displays its own expiry plainly, and a revoked or expired link renders
a neutral "tautan tidak berlaku" state that discloses nothing about whether the assignment ever existed.

---

### DUX-009 — Support impersonation not visually unmistakable

| Field | Value |
| --- | --- |
| **Area** | Support impersonation |
| **Affected surface** | Console Web, Ops Android |
| **Severity** | **HIGH** |
| **Linked threat** | THREAT-007, THREAT-044 |
| **Requirements** | SEC-050, SEC-051, TEN-024, NFR-040 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** When platform support acts inside a tenant, an interface that looks identical to a normal
session makes the impersonation invisible to the operator and easy for the support agent to forget.

**Impact (argued).** Rule 03 states that platform support has **no silent tenant access**, and Rule 12
makes silent or unaudited platform access to tenant data an automatic `NO-GO`. A visually indistinguishable
impersonation session is silent access by interface design, even when the server writes an audit record —
because the tenant cannot see it. HIGH rather than CRITICAL only because the audit trail still exists
server-side and the exposure is to an accountable internal actor.

**Likelihood (argued).** Certain in the absence of a rule; no interface grows a support banner by accident.

**UX mitigation.** An impersonation session renders a persistent, high-contrast, non-dismissible banner on
every screen stating that support is acting inside the tenant, naming the support actor, showing the reason
recorded at session start, and showing the remaining time. The banner is announced to assistive technology
on entry to every screen. Ending impersonation is always one action away. A reason is mandatory before the
session begins — it cannot be supplied afterwards.

---

### DUX-010 — Payment success claimed from client state

| Field | Value |
| --- | --- |
| **Area** | Payment confirmation |
| **Affected surface** | Ops Android, Customer Android |
| **Severity** | **CRITICAL** |
| **Linked threat** | THREAT-006, THREAT-010 |
| **Requirements** | FIN-006, FIN-011, FIN-012, OFF-009 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** An interface that renders "Pembayaran berhasil" when the request leaves the device — rather
than when the server confirms it — asserts a financial fact it does not possess. On a patchy connection the
optimistic state and the real state diverge routinely.

**Impact (argued).** A false success releases goods against a payment that never settled. The loss is real
money, and the corrective path is a reversal entry plus a conversation with a customer who was told they had
paid. Rule 04 makes financial integrity a hard gate and any integrity failure an automatic `NO-GO`; an
interface that manufactures the claim is the defect, not the network.

**Likelihood (argued).** High in Indonesian counter conditions, which the product explicitly targets:
patchy mobile data and cheap devices are the design assumption, not the exception.

**UX mitigation.** Three states are visually and textually distinct and never collapsed into one:
`TERKIRIM — MENUNGGU KONFIRMASI SERVER`, `BERHASIL — DIKONFIRMASI SERVER`, and `GAGAL`. Only the second is
rendered in the success style. A pending payment is never described with a success word, a success colour,
or a success icon. The order remains visibly unpaid until the server confirms. Retry reuses the original
`client_reference`, and the interface says so, so an operator does not create a second payment by pressing
the button again.

---

### DUX-011 — Refund flow shaped as a dark pattern

| Field | Value |
| --- | --- |
| **Area** | Refund and void |
| **Affected surface** | Ops Android, Console Web |
| **Severity** | **HIGH** |
| **Linked threat** | THREAT-019 |
| **Requirements** | FIN-021, FIN-022, FIN-023, SEC-052 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** A refund path that is buried, that pre-selects the smallest amount, that defaults the
reason to a bland placeholder, or that discourages completion through friction is a dark pattern operating
against the customer.

**Impact (argued).** Two harms compound. The customer is denied money owed. The tenant's audit trail fills
with meaningless reasons, which destroys the evidentiary value of the record precisely when a dispute
arises (THREAT-019 is repudiation). HIGH because it is a deliberate-looking integrity failure even though
no isolation boundary is crossed.

**Likelihood (argued).** Moderate. This is rarely designed on purpose; it emerges when the refund path is
treated as an exception flow and given no design attention.

**UX mitigation.** Refund and void are first-class, discoverable actions on the order record, at the same
level of prominence as payment. No amount is pre-selected. The reason field is mandatory, free of
pre-filled text, and rejects an empty or whitespace-only value. The confirmation restates amount, order,
customer, actor, and reason before commit. The interface states plainly that the correction is recorded as
a reversal or adjustment entry and that the original record is preserved — never as a deletion.

---

### DUX-012 — Destructive actions adjacent to routine actions

| Field | Value |
| --- | --- |
| **Area** | Destructive action placement |
| **Affected surface** | All authenticated surfaces |
| **Severity** | **HIGH** |
| **Linked threat** | THREAT-011, THREAT-017 |
| **Requirements** | FR-046, FIN-024, NFR-036 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** Cancel, void, refund, revoke, and remove placed next to save, confirm, or print produce
mis-taps — especially on a phone, one-handed, at a busy counter, in bright light.

**Impact (argued).** A mis-tapped cancel on a real order interrupts production and requires a documented
corrective path out of a terminal state (Rule 19). It is HIGH rather than CRITICAL because every such action
is recoverable through an audited path and none of them delete a financial record.

**Likelihood (argued).** High. The Ops app is explicitly designed for time pressure, and adjacency plus
haste is the classic mis-tap condition.

**UX mitigation.** Destructive actions are spatially separated from routine actions, never placed in the
primary action position, and never the visual default. They are styled distinctly and are never conveyed by
colour alone — an icon and a text label always accompany the treatment. Confirmation is required, and the
confirmation dialogue's default focus is the safe choice.

---

### DUX-013 — Confirmations too weak for the consequence

| Field | Value |
| --- | --- |
| **Area** | Confirmation strength |
| **Affected surface** | All authenticated surfaces |
| **Severity** | **HIGH** |
| **Linked threat** | THREAT-011, THREAT-019 |
| **Requirements** | FIN-021, FIN-025, FR-047 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** A generic "Yakin?" dialogue with a `Batal` / `OK` pair conveys no information about what
is about to happen and trains users to dismiss it reflexively.

**Impact (argued).** A confirmation that is always dismissed is not a control. Its presence creates false
assurance in the design record — the reviewer sees a confirmation step and stops asking — which is why this
is HIGH rather than MEDIUM.

**Likelihood (argued).** High. Generic confirmations are the default output of every component library.

**UX mitigation.** Confirmation strength scales with consequence. Routine reversible actions need none.
Destructive actions restate the specific object and effect in the dialogue body. Financial actions restate
amount, order, customer, outlet, and tenant, and require a reason where Rule 04 requires one. Irreversible
or high-value actions require a distinct deliberate gesture — typing the order number, or step-up
authentication — never merely a second tap in the same position.

---

### DUX-014 — Offline state presented as confirmed state

| Field | Value |
| --- | --- |
| **Area** | Offline and sync honesty |
| **Affected surface** | Ops Android |
| **Severity** | **CRITICAL** |
| **Linked threat** | THREAT-013 |
| **Requirements** | OFF-002, OFF-005, OFF-009, OFF-014, FIN-012 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** An offline-first interface that renders a queued operation identically to a committed one
tells the operator that the server holds a record it does not hold.

**Impact (argued).** This is `DUX-010` generalised to every operation, and it is worse in aggregate: a
queue of "completed" work that has not reached the server produces order duplication on retry, mis-stated
cash at shift closing, and a reconciliation the owner cannot explain. Rule 07 names duplicate order or
payment from a retry as the defining failure of the offline design, and Rule 12 makes it an automatic
`NO-GO`.

**Likelihood (argued).** Very high without an explicit rule. Optimistic UI is the standard technique for
making an offline app feel fast, and it is correct — but only when the optimism is labelled.

**UX mitigation.** Connectivity and sync state are visible at all times in the Ops app. Every record
carries an honest state: `TERSIMPAN DI PERANGKAT`, `MENUNGGU SINKRONISASI`, `TERSINKRON`, or
`GAGAL — PERLU TINDAKAN`. Pending items are never styled as confirmed. A pending-operations view lists
what is queued, what failed, and what needs attention, and nothing is ever silently dropped from it.
Financial items in the queue cannot be cleared by a routine cache-clear, a logout, or an upgrade — removal
requires an explicit, permissioned, audited action. A payment conflict is surfaced for human resolution and
never auto-resolved.

---

### DUX-015 — Stale data indistinguishable from current data

| Field | Value |
| --- | --- |
| **Area** | Data freshness |
| **Affected surface** | Ops Android, Console Web, Customer Android |
| **Severity** | **MEDIUM** |
| **Linked threat** | THREAT-030 |
| **Requirements** | OFF-011, OFF-016, NFR-018 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** Cached content rendered without a freshness indicator leads a user to act on a status,
balance, or assignment that has since changed server-side.

**Impact (argued).** Bounded and usually self-correcting, because the server is the source of truth and
the next write is rejected or reconciled. It rises above LOW because a stale unpaid balance shown to a
customer at collection is an argument at the counter.

**Likelihood (argued).** High in ordinary use — the app is designed to work offline, so stale data is a
normal state rather than a fault.

**UX mitigation.** Any view that may render cached content shows the time it was last synchronised in
outlet local time. Financial figures and order status carry the freshness marker adjacent to the value,
not in a page footer. A refresh affordance is always available, and its failure is reported honestly rather
than leaving the previous timestamp in place.

---

### DUX-016 — Design excludes users with disabilities

| Field | Value |
| --- | --- |
| **Area** | Accessibility |
| **Affected surface** | All surfaces |
| **Severity** | **HIGH** |
| **Linked threat** | — (no threat-model entry; harm is exclusion, not adversarial) |
| **Requirements** | NFR-041, NFR-042, NFR-043, FR-097 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** A design foundation that does not fix contrast, target size, font scaling, focus order, and
assistive-technology semantics at the token and component level cannot have them retrofitted later without
rework of every screen.

**Impact (argued).** Exclusion of staff and customers with low vision, motor impairment, or colour vision
deficiency. Accessibility is a **hard gate** in this product's rules, so a foundation that omits it blocks
the Definition of Done for every subsequent Step that builds a screen — the cost compounds rather than
staying local, which is the argument for HIGH.

**Likelihood (argued).** Certain if not fixed in the foundation. Retrofitted accessibility is reliably
partial.

**UX mitigation.** The design system fixes: minimum touch target 48×48 dp; contrast ratios meeting WCAG 2.2
AA for text and for non-text UI components; layouts that survive large system font scaling without
truncating critical information; a defined focus order per screen; and an accessibility contract per
component covering role, name, state, and announcement.
**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**

---

### DUX-017 — Status conveyed by colour alone

| Field | Value |
| --- | --- |
| **Area** | Status legibility |
| **Affected surface** | All surfaces |
| **Severity** | **HIGH** |
| **Linked threat** | — |
| **Requirements** | NFR-042, FR-097, FR-041 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** Order status, payment status, sync state, and quality-control outcome expressed only as a
coloured chip are unreadable to a user with colour vision deficiency, and unreliable on a cheap screen in
direct sunlight — which is the stated operating environment.

**Impact (argued).** Misreading `SIAP DIAMBIL` as `SELESAI`, or an unpaid order as paid, produces an
operational or financial error. HIGH because status is the spine of the product: production, tracking,
aging, notifications, and reporting all read it, so a misread propagates.

**Likelihood (argued).** High. Colour-coded chips are the default idiom for status in every design system.

**UX mitigation.** Every status is rendered with a text label in Bahasa Indonesia drawn from the canonical
glossary, and with an icon or shape distinct from its neighbours. Colour is reinforcement only, never the
sole carrier. Statuses adjacent in a workflow are given distinguishable shapes as well as distinguishable
hues, and no status is distinguished from another by hue alone.

---

### DUX-018 — Focus lost on state change

| Field | Value |
| --- | --- |
| **Area** | Keyboard and focus management |
| **Affected surface** | Console Web, Ops Android |
| **Severity** | **MEDIUM** |
| **Linked threat** | — |
| **Requirements** | NFR-043, FR-097 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** When a dialogue opens, content loads, an error appears, or a route changes, focus that is
not explicitly managed returns to the document root, stranding keyboard and switch-control users.

**Impact (argued).** Loss of task completion for the affected users, and silent loss of an error message
that was never announced. MEDIUM rather than HIGH because it degrades a path rather than exposing data or
money, and because it is fully recoverable within the session.

**Likelihood (argued).** High without a rule; correct focus management is never the framework default.

**UX mitigation.** Each component's accessibility contract states where focus moves on open, on close, on
error, and on asynchronous completion. Dialogues trap focus and return it to the invoking control on
dismissal. Validation errors move focus to the first invalid field and announce the error text. Route
changes move focus to the new page heading.

---

### DUX-019 — Screen-reader ambiguity

| Field | Value |
| --- | --- |
| **Area** | Assistive technology semantics |
| **Affected surface** | All surfaces |
| **Severity** | **MEDIUM** |
| **Linked threat** | — |
| **Requirements** | NFR-043, FR-097 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** Icon-only controls, unlabelled inputs, decorative images with meaningful alternative text,
and status chips announced only as their colour name produce an interface that is technically operable and
practically unusable.

**Impact (argued).** A screen reader announcing "button" five times on a row of order actions gives the
user no basis for choosing. MEDIUM for the same reason as `DUX-018` — it degrades a path rather than
breaching a gate — but it is the difference between an accessible product and a compliant-looking one.

**Likelihood (argued).** High. Icon-only toolbars are ubiquitous and their labels are usually an
afterthought.

**UX mitigation.** Every interactive element declares role, accessible name, and state. Icon-only controls
carry a text alternative naming the action and its object, for example "Batalkan pesanan ALS-2026-000042"
rather than "Batal". Decorative imagery is marked decorative. Status is announced as its text label, never
as a colour. Live regions announce sync-state and payment-state changes.

---

### DUX-020 — OTP entry hostile under real conditions

| Field | Value |
| --- | --- |
| **Area** | OTP usability |
| **Affected surface** | Customer Android, Ops Android |
| **Severity** | **MEDIUM** |
| **Linked threat** | THREAT-001, THREAT-037, THREAT-008 |
| **Requirements** | SEC-005, SEC-006, FR-002, NFR-044 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** Split single-character OTP boxes that reject paste, lose entered digits on resend, do not
say how long the code is valid, and give an unclear message after lockout drive users toward insecure
workarounds and generate avoidable support load.

**Impact (argued).** Not a direct disclosure. The security consequence is indirect: a hostile OTP flow
pushes staff to share accounts or keep sessions permanently open, which weakens revocation. MEDIUM is the
honest rating — the harm is real but mediated by user behaviour rather than by the interface itself.

**Likelihood (argued).** Moderate to high; split-box OTP inputs with paste disabled remain a common
pattern.

**UX mitigation.** A single input accepting the full code, paste permitted, numeric keyboard, platform
autofill supported. Validity is stated in plain language. Resend is disabled with a visible countdown
rather than silently rate-limited. Rate limiting and lockout are explained in Bahasa Indonesia with a clear
recovery step. The OTP is never echoed back, never shown in a notification preview, never copied to the
clipboard automatically, and never logged.

---

### DUX-021 — Session expiry destroys unsaved work

| Field | Value |
| --- | --- |
| **Area** | Session expiry |
| **Affected surface** | Ops Android, Console Web |
| **Severity** | **HIGH** |
| **Linked threat** | THREAT-004, THREAT-042 |
| **Requirements** | SEC-011, SEC-012, OFF-004, NFR-045 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** A session that expires mid-transaction and discards the in-progress order or payment
teaches staff to defeat expiry — long sessions, shared logins, a device left permanently unlocked.

**Impact (argued).** The second-order effect is what makes this HIGH: every workaround it provokes directly
weakens the controls that answer THREAT-004 (lost or stolen device with a live session) and THREAT-042
(stale membership retaining access). A security control that users are motivated to circumvent is a weaker
control than one they tolerate.

**Likelihood (argued).** High. Counter work is interrupt-driven; sessions will expire mid-task routinely.

**UX mitigation.** Expiry is warned before it happens, with time remaining and a re-authenticate action
that does not leave the current screen. In-progress work is preserved locally through re-authentication and
restored afterwards. Re-authentication is step-up only — it never becomes a full logout that clears the
device queue. An expired session never silently discards a queued financial operation.

---

### DUX-022 — Device revocation leaves no recovery path

| Field | Value |
| --- | --- |
| **Area** | Device revocation |
| **Affected surface** | Ops Android, Console Web |
| **Severity** | **MEDIUM** |
| **Linked threat** | THREAT-004, THREAT-013 |
| **Requirements** | SEC-012, SEC-013, OFF-004 |
| **Status** | ACCEPTED |

**Description.** Revoking a device is the correct response to loss or theft. If that device held queued
offline financial operations, the interface must say what happened to them — otherwise the operator does
not know whether the money was recorded.

**Impact (argued).** Ambiguity rather than loss: the server holds whatever synchronised, and unsynchronised
operations never existed server-side. The harm is that the manager cannot tell which is which, so
reconciliation becomes guesswork. MEDIUM.

**Likelihood (argued).** Low in frequency — device loss is uncommon — but certain in consequence when it
happens, and it happens to couriers.

**Rationale for ACCEPTED.** The full resolution depends on server-side queue introspection, which belongs to
Step 3 and later and cannot be designed to completion at Step 2 without inventing runtime behaviour. What
Step 2 fixes is the honesty of the interface.

**UX mitigation.** The revocation confirmation states plainly that operations already synchronised are
retained and that operations still queued on the revoked device are **not** recoverable, and it directs the
manager to reconcile against server records. The interface never implies recovery it cannot deliver. The
full recovery design is carried into Step 3 as an explicit obligation.

---

### DUX-023 — Consent obtained through interface manipulation

| Field | Value |
| --- | --- |
| **Area** | Marketing consent |
| **Affected surface** | Customer Android, Ops Android, Portal Tracking Publik |
| **Severity** | **HIGH** |
| **Linked threat** | THREAT-020 |
| **Requirements** | NOT-018, NOT-019, NOT-020, SEC-057 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** Pre-ticked marketing consent, consent bundled with terms acceptance, opt-out hidden behind
several taps, or a double negative in the label produces consent that is not consent.

**Impact (argued).** THREAT-020 is repudiation: the customer disputes having consented and the tenant
cannot demonstrate otherwise, because the interface that captured it was designed to produce agreement
rather than to record a decision. HIGH — it is a compliance and trust failure, and it undermines the
opt-out guarantee the product commits to.

**Likelihood (argued).** High without a rule. Bundled consent is the commercial default everywhere.

**UX mitigation.** Marketing consent is a separate, unbundled, explicitly opt-in control, defaulted to off,
labelled positively in Bahasa Indonesia, and never combined with acceptance of terms or with a
transactional notification setting. Withdrawal is at least as easy as granting and is reachable from the
notification itself. The interface distinguishes transactional messages — which follow the order and are
not consent-gated — from marketing messages, and never presents one as the other.

---

### DUX-024 — Reminder ladder perceived as spam

| Field | Value |
| --- | --- |
| **Area** | Unclaimed-laundry reminders |
| **Affected surface** | Customer WhatsApp, Customer Android |
| **Severity** | **MEDIUM** |
| **Linked threat** | THREAT-040 |
| **Requirements** | UCL-011, UCL-012, NOT-007, NOT-011 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** The H+1 / H+3 / H+7 / H+14 ladder is a core differentiator, but a customer who receives
duplicated stages, messages inside quiet hours, or reminders after collection experiences it as harassment
from the tenant's brand.

**Impact (argued).** Reputational damage to the tenant and pressure to disable the feature that recovers
their cash. MEDIUM: no data is exposed and no money is misstated, but the product's own value proposition
is eroded.

**Likelihood (argued).** Moderate. Deduplication and quiet hours are already binding rules; the residual
risk is a scheduler restart replaying a stage, which is a Step 9 implementation concern.

**UX mitigation.** Each ladder stage renders once per order with distinct, escalating-but-courteous copy.
The message states why it was sent and how to stop it. Quiet hours 20.00–08.00 outlet local time are
honoured; a message queued inside the window is deferred to the next permitted window, never dropped and
never sent anyway. The tenant-facing dashboard shows the last reminder sent per order so staff do not send
a manual duplicate on top of an automated one.

---

### DUX-025 — Storage-fee presentation as coercion

| Field | Value |
| --- | --- |
| **Area** | Unclaimed-laundry commercial copy |
| **Affected surface** | Customer WhatsApp, Customer Android, Portal Tracking Publik |
| **Severity** | **MEDIUM** |
| **Linked threat** | — |
| **Requirements** | UCL-018, UCL-019, FIN-030 |
| **Status** | ACCEPTED |

**Description.** Where a tenant charges a storage fee for uncollected laundry, escalating reminder copy can
drift into threatening language, or imply a consequence — disposal, sale, forfeiture — that the product
must never automate and that the tenant may have no legal right to impose.

**Impact (argued).** Consumer harm and legal exposure for the tenant, carried on templates the platform
supplies. MEDIUM because the fee itself is a legitimate tenant policy; the defect is only in how it is
expressed.

**Likelihood (argued).** Moderate. Escalation copy naturally hardens in tone at H+7 and H+14.

**Rationale for ACCEPTED.** The platform supplies templates but tenants may customise messaging. The
platform can constrain its own copy and cannot fully constrain a tenant's. The residual risk is accepted
and made visible rather than claimed as eliminated.

**UX mitigation.** Platform-supplied templates state the fee factually — amount, the basis on which it
accrues, and how to stop it accruing — in neutral Bahasa Indonesia. No platform template threatens
disposal, sale, donation, transfer of ownership, legal action, or credit consequences. The template
authoring interface warns that such claims are prohibited. Fee amounts are integer Rupiah read from the
authoritative financial record, never recomputed for display.

---

### DUX-026 — An automatic disposal path appearing in the interface

| Field | Value |
| --- | --- |
| **Area** | Unclaimed-laundry disposal |
| **Affected surface** | Ops Android, Console Web |
| **Severity** | **CRITICAL** |
| **Linked threat** | — (prohibited by product rule, not by an adversary) |
| **Requirements** | UCL-020, UCL-021 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** A dashboard that surfaces very old unclaimed orders invites an obvious-looking next
control: dispose, sell, donate, write off, transfer. Rule 10 prohibits this **absolutely**.

**Impact (argued).** The product would be automating the transfer of ownership of a customer's belongings
— a legal question belonging to the tenant and its customer, never to a SaaS platform. The rating is
CRITICAL not because of data exposure but because it is an outright prohibition whose breach cannot be
remediated after the fact: the belongings are gone.

**Likelihood (argued).** Real. The affordance is a natural design conclusion from an aging dashboard, and
tenants will request it.

**UX mitigation.** No screen, control, bulk action, menu item, backlog placeholder, feature flag, or
tooltip offers automated disposal, sale, auction, donation, write-off, or ownership transfer of customer
laundry. The dashboard's terminal action is **escalation to a named human** — the H+14 stage — plus a
recorded reason. The design documentation states the prohibition explicitly so that a later designer
encounters it before proposing the control. A request for this capability is escalated to the repository
owner, never implemented behind a flag or left as a TODO.

---

### DUX-027 — Personal data entering this PUBLIC repository through design artefacts

| Field | Value |
| --- | --- |
| **Area** | Public repository safety |
| **Affected surface** | Repository |
| **Severity** | **CRITICAL** |
| **Linked threat** | THREAT-028 |
| **Requirements** | SEC-060, SEC-061, NFR-048 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** Design work is the highest-risk authoring activity for this constraint. Wireframes, copy
decks, component examples, empty-state illustrations, and screenshots all want realistic data, and
"realistic" is one careless step from "real".

**Impact (argued).** Every file here is world-readable and permanently so. **Deletion is not remediation** —
content must be assumed mirrored, cached, and indexed from the moment it is pushed. A real customer phone
number in a wireframe is a disclosure that cannot be withdrawn. Rule 03 makes personal data exposed
publicly an automatic `NO-GO`.

**Likelihood (argued).** High without a rule. Copying a plausible example from a real message or screenshot
is the path of least resistance.

**UX mitigation.** Every datum in every Step 2 artefact is **fictional and recognisably so**, drawn from a
fixed placeholder convention: fictional customer names, `+62 8xx-xxxx-xxxx` patterns that are structurally
valid but not allocated, invented outlet names, and invented order numbers. No screenshot of a real system,
real message thread, or real device is committed. Only `PUBLIC` and sanitised `INTERNAL` material is
committed; `CONFIDENTIAL`, `RESTRICTED`, and `SECRET` are described and modelled but never instantiated.
Pricing text reproduces the Master Source character for character.

---

### DUX-028 — SVG assets as an injection surface

| Field | Value |
| --- | --- |
| **Area** | Asset handling |
| **Affected surface** | Console Web, Portal Tracking Publik |
| **Severity** | **MEDIUM** |
| **Linked threat** | THREAT-015 |
| **Requirements** | SEC-036, SEC-037 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** SVG is executable markup. An SVG carrying `<script>`, an event handler, a foreign object,
or an external reference becomes script execution when inlined — and tenant-uploaded brand logos are
exactly the case where an untrusted SVG reaches a trusted origin.

**Impact (argued).** Script execution on the Console Web origin would run with the operator's session.
MEDIUM at Step 2 because no runtime exists and the mitigation is largely a Step 3 implementation control;
the design decision that makes it tractable — where logos may be rendered and how — is made now.

**Likelihood (argued).** Moderate. Tenants will upload logos, and SVG is the obvious format for a logo.

**UX mitigation.** Iconography in the design system ships as a curated, reviewed, first-party set — never
fetched at runtime from a third party and never assembled from user content. Tenant-uploaded brand assets
are treated as untrusted: raster formats only for tenant upload, or SVG sanitised server-side and rendered
in a context that cannot execute script. The upload interface states the accepted formats and the reason,
so the constraint reads as a product decision rather than an arbitrary rejection.

---

### DUX-029 — Remote embedded content in product surfaces

| Field | Value |
| --- | --- |
| **Area** | Third-party embedding |
| **Affected surface** | Portal Tracking Publik, Console Web |
| **Severity** | **HIGH** |
| **Linked threat** | THREAT-031, THREAT-034 |
| **Requirements** | SEC-038, SEC-039, TRK-011, NFR-032 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** Remote fonts, remote icon sets, analytics scripts, embedded maps, and marketing pixels
each open a channel from the most exposed surface in the product to a third party — carrying the referring
URL, which on the tracking portal contains the token.

**Impact (argued).** A remote asset on the tracking portal leaks the token-bearing URL to a party the
customer never chose, and a map embed leaks the customer's address to a map provider (THREAT-034). This is
a `SECRET`-class and `RESTRICTED`-class disclosure through an ordinary-looking front-end decision — HIGH.

**Likelihood (argued).** High. Remote fonts and analytics are the default in essentially every web build.

**UX mitigation.** The tracking portal is self-contained: fonts, icons, and styles are first-party. No
analytics script, marketing pixel, session recorder, or third-party embed is placed on any surface that
can carry a token. Where a map is genuinely required for an operational surface, it is authenticated,
address precision is minimised before it leaves the product, and the design records the disclosure
explicitly rather than treating the provider as internal.

---

### DUX-030 — Untrusted links rendered as trusted interface

| Field | Value |
| --- | --- |
| **Area** | Link handling |
| **Affected surface** | Ops Android, Console Web, Customer Android |
| **Severity** | **HIGH** |
| **Linked threat** | THREAT-015, THREAT-005 |
| **Requirements** | SEC-040, SEC-041, NOT-016 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** User-supplied text — customer notes, courier remarks, tenant profile fields, provider
callback content — rendered with automatic link detection lets an attacker place a clickable link inside
a trusted surface, next to legitimate controls.

**Impact (argued).** Phishing that inherits the product's credibility. A staff member sees a link inside
the Ops app and reasonably assumes the app put it there. HIGH because it converts a data field into a
delivery channel for credential theft against the operator.

**Likelihood (argued).** Moderate and rising once tenants have many staff and open text fields exist.

**UX mitigation.** User-supplied content is rendered as **plain text**. Automatic link detection is off in
customer notes, courier remarks, and any field an untrusted party can populate. Where a link must be
actionable, its full destination host is shown, it is visually marked as external and user-supplied, and
following it presents an interstitial naming the destination. Outbound navigation carries `noreferrer`.
Content originating from a provider callback is never rendered as interface without server-side
verification.

---

### DUX-031 — Design token and terminology inconsistency

| Field | Value |
| --- | --- |
| **Area** | Token and terminology consistency |
| **Affected surface** | All surfaces |
| **Severity** | **MEDIUM** |
| **Linked threat** | — |
| **Requirements** | FR-097, NFR-046 |
| **Status** | ACCEPTED |

**Description.** Two names for one status, two token names for one semantic colour, or a raw hex value used
where a semantic token exists cause the same state to look and read differently across surfaces.

**Impact (argued).** Users learn a surface rather than a product, and a security-relevant state — offline,
impersonation, pending payment — that looks different in two places is a state that will be misread in at
least one. MEDIUM: the harm is indirect but it undermines several of the mitigations above.

**Likelihood (argued).** High over time. Divergence is the default outcome of parallel design work.

**Rationale for ACCEPTED.** Full enforcement requires a token linter running against an implemented theme,
which is Step 3 or later. Step 2 fixes the naming discipline and the review obligation; it cannot fix
mechanical enforcement without a runtime.

**UX mitigation.** One semantic token layer sits between raw values and components; components reference
semantic tokens only, and a raw value is never used where a semantic token exists. Status names come from
the Bahasa Indonesia glossary, and a new term requires a glossary entry in the same pull request. The
component inventory is the single place a component is defined, and a second component with the same
purpose is a review rejection.

---

### DUX-032 — Screen defined without requirement traceability

| Field | Value |
| --- | --- |
| **Area** | Traceability |
| **Affected surface** | Design documentation |
| **Severity** | **LOW** |
| **Linked threat** | — |
| **Requirements** | FR-001, NFR-047 |
| **Status** | MITIGATED-BY-DESIGN |

**Description.** A screen or component documented without requirement references is a feature invented in a
design file. It cannot be traced, tested, or removed with confidence.

**Impact (argued).** Scope creep and orphaned work rather than a security harm — hence LOW. It does have a
security tail: an untraceable screen has no stated tenant behaviour and no stated permission behaviour,
which is where isolation defects hide.

**Likelihood (argued).** Moderate. Design work naturally runs ahead of the requirement baseline.

**UX mitigation.** Every screen and component carries at least one requirement ID from
[`../product/PRODUCT_REQUIREMENTS.md`](../product/PRODUCT_REQUIREMENTS.md), a stated tenant behaviour, a
stated permission behaviour, and a UX classification. A design artefact citing no requirement is resolved
before the Step closes — either the requirement is added to the PRD properly, or the artefact is removed.

---

### DUX-033 — Dark mode deferral leaves contrast unverified for a later theme

| Field | Value |
| --- | --- |
| **Area** | Theming |
| **Affected surface** | All surfaces |
| **Severity** | **LOW** |
| **Linked threat** | — |
| **Requirements** | NFR-042 |
| **Status** | ACCEPTED |

**Description.** The light theme is the MVP and dark mode is deferred. Contrast decisions are therefore
verified against one theme only.

**Impact (argued).** None today; the deferred theme does not exist. The risk is future rework if the token
layer assumes a light background.

**Rationale for ACCEPTED.** Deferring dark mode is a deliberate scope decision. Recording the consequence
is the correct treatment; building a second theme now would be forward-leaked Step work.

**UX mitigation.** Semantic tokens are named by role — surface, on-surface, accent, danger — not by
appearance, so a second theme can be introduced by remapping rather than by rewriting components. No
component hard-codes a light-theme assumption. Dark mode remains `PLANNED` and is never described as
available.

---

### DUX-034 — Fabricated final logo or brand asset

| Field | Value |
| --- | --- |
| **Area** | Brand assets |
| **Affected surface** | Design documentation |
| **Severity** | **LOW** |
| **Linked threat** | — |
| **Requirements** | FR-098 |
| **Status** | ACCEPTED |

**Description.** A generated or improvised logo placed in design documentation is read by later readers as
the approved mark.

**Impact (argued).** Brand and ownership confusion, and a false claim in the Rule 01 sense — presenting an
invention as an owner decision. LOW because it is caught easily and harms nothing operationally.

**Rationale for ACCEPTED.** The final mark is an owner decision that has not been made. The honest
treatment is a labelled placeholder, not an invention.

**UX mitigation.** No final logo is fabricated, generated, or presented as approved. Brand slots in design
artefacts carry an explicitly labelled placeholder. The visual language is fixed as white, soft blue, dark
blue, and a restrained gold accent per the Master Source; the mark itself remains an owner decision.

---

### DUX-035 — Token naming revealing unreleased commercial intent

| Field | Value |
| --- | --- |
| **Area** | Public repository safety |
| **Affected surface** | Design documentation |
| **Severity** | **INFORMATIONAL** |
| **Linked threat** | THREAT-050 |
| **Requirements** | SEC-062 |
| **Status** | ACCEPTED |

**Description.** Token, component, and screen names are published. A name such as `tier-enterprise-only`
would disclose commercial structure ahead of an owner decision.

**Impact (argued).** No security impact. Commercial visibility is already an **accepted consequence** of
PUBLIC visibility (DEC-0016), and pricing is published deliberately.

**Rationale for ACCEPTED.** Recorded so that a later reader sees it was considered rather than missed.

**UX mitigation.** Names describe function, not commercial packaging. No token or component name encodes an
unreleased plan, an unannounced feature, or a customer name.

---

### DUX-036 — Graphify relationship review not yet performed at this SHA

| Field | Value |
| --- | --- |
| **Area** | Governance process |
| **Affected surface** | Design documentation |
| **Severity** | **INFORMATIONAL** |
| **Linked threat** | — |
| **Requirements** | NFR-047 |
| **Status** | **OPEN** |

**Description.** The Step 2 governance rules require a Graphify relationship review over the design
documentation set, checking that every screen, component, requirement, and rule reference resolves and that
no orphan exists in either direction. That review has **not** been performed at this commit.

**Impact (argued).** None to users. The consequence is that traceability completeness is currently
**unverified** rather than verified, and it must not be reported as verified.

**Likelihood (argued).** Not applicable — this is a stated outstanding process step, not a probabilistic
risk.

**Required action.** Run the relationship review before Step 2 closes and capture its output at the exact
commit SHA per DEC-0013. Until then, traceability completeness for Step 2 is an **unverified claim**. Any
diagram tooling used may render only documentation already approved in the Master Source; it must not
introduce new product facts, screens, or flows.

---

## 5. Coverage check

Every review area required of this document has a finding: hidden tenant context (`DUX-001`); ambiguous
outlet context (`DUX-002`); PII exposure (`DUX-003`); tracking token leakage (`DUX-004`); full address
exposure (`DUX-005`); clipboard exposure (`DUX-006`); notification preview exposure (`DUX-007`); external
courier over-access (`DUX-008`); support impersonation ambiguity (`DUX-009`); false payment success
(`DUX-010`); refund dark pattern (`DUX-011`); destructive action adjacency (`DUX-012`); weak confirmations
(`DUX-013`); offline false assurance (`DUX-014`); stale-data confusion (`DUX-015`); accessibility exclusion
(`DUX-016`); colour-only status (`DUX-017`); focus loss (`DUX-018`); screen-reader ambiguity (`DUX-019`);
OTP usability (`DUX-020`); session-expiry data loss (`DUX-021`); revoked-device recovery (`DUX-022`);
consent manipulation (`DUX-023`); reminder spam (`DUX-024`); storage-fee coercion (`DUX-025`); automatic
disposal path (`DUX-026`); public repository PII (`DUX-027`); SVG injection (`DUX-028`); remote embedded
content (`DUX-029`); malicious links (`DUX-030`); token inconsistency (`DUX-031`); screen traceability
omission (`DUX-032`).

---

## 6. What this review does not establish

- **No control is implemented.** Every mitigation is a design obligation. The backend is `ABSENT`, the
  Flutter workspace is `ABSENT`, the database is `ABSENT`, deployment is `ABSENT`, and application CI is
  `NOT APPLICABLE`.
- **No accessibility audit has been executed.** The design is
  **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED.**
- **No acceptance criterion has been executed.** A written criterion is not a passed test.
- **This is internal re-verification under single-maintainer governance, not independent peer review and
  not an approval.**
- **Step 2 is `IN PROGRESS`.** `GO` is conferred by the repository owner and is never self-declared.

---

## 7. Related documents

- [`INITIAL_THREAT_MODEL.md`](INITIAL_THREAT_MODEL.md) · [`ABUSE_CASES.md`](ABUSE_CASES.md)
- [`DATA_CLASSIFICATION.md`](DATA_CLASSIFICATION.md) · [`TRUST_BOUNDARIES.md`](TRUST_BOUNDARIES.md)
- [`PRIVACY_REQUIREMENTS.md`](PRIVACY_REQUIREMENTS.md) · [`SECURITY_ACCEPTANCE_CRITERIA.md`](SECURITY_ACCEPTANCE_CRITERIA.md)
- [`../ux/SECURITY_AND_PRIVACY_UX.md`](../ux/SECURITY_AND_PRIVACY_UX.md)
- [`../quality/STEP_02_DEFINITION_OF_DONE.md`](../quality/STEP_02_DEFINITION_OF_DONE.md)
- [`../product/PRODUCT_REQUIREMENTS.md`](../product/PRODUCT_REQUIREMENTS.md)
- [`../STATUS.md`](../STATUS.md) · [`../ROADMAP.md`](../ROADMAP.md) · [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md)
