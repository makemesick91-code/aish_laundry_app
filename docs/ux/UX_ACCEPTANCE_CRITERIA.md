# UX Acceptance Criteria

**Step 2 status:** IN PROGRESS
**Criteria executed:** **NONE**
**Implementation status:** NOT IMPLEMENTED · **Application CI:** NOT APPLICABLE

---

## 0. The honesty rule, stated before anything else

> **AN ACCEPTANCE CRITERION IS NOT EVIDENCE THAT IT PASSED.**

Writing a criterion proves intent. Only captured output at an exact commit SHA proves a result. At
Step 2 **every criterion below is unmet by definition**, because nothing is implemented: the Flutter
workspace is `ABSENT`, the backend runtime is `ABSENT`, and there are no unit, widget, integration, or
end-to-end tests.

A criterion is never weakened to make it pass. If reality does not meet a criterion, either the
implementation is wrong or the requirement was wrong — and changing a requirement is an owner
decision.

## 0.1 How to read a criterion

Each criterion carries an ID `UXAC-###`, the requirement IDs it verifies, its bounded context, the
roadmap step that will satisfy it, and a Given / When / Then statement. Criteria cover **both the
happy path and the negative path**; a criterion that only proves the feature works when used
correctly proves very little.

Criteria that touch the three places where silent failure is most expensive are marked explicitly:

| Marker | Meaning |
|---|---|
| **[TENANT]** | Names the tenant boundary |
| **[FINANCIAL]** | Names financial integrity |
| **[OFFLINE]** | Names offline behaviour |

No criterion assumes state it did not establish, and none depends on the order in which other
criteria ran.

---

## 1. Tenant boundary and context

### UXAC-001 — Tenant context is always visible **[TENANT]**

- **Requirements:** `FR-011`, `FR-014`, `TEN-002`
- **Bounded context:** Tenant and Organization · **Step:** 3
- **Given** an authenticated user with a membership in `Laundry Bersih Sejahtera` working at `Outlet Cempaka`,
- **When** any operational screen on Ops Android is displayed,
- **Then** the persistent context bar shows tenant, brand, outlet, role, and sync state simultaneously, each as text and not by colour alone.

### UXAC-002 — Tenant switching is never silent **[TENANT]**

- **Requirements:** `FR-015`, `FR-016`, `TEN-002`
- **Bounded context:** Tenant and Organization · **Step:** 3
- **Given** a user holding memberships in two tenants,
- **When** a deep link belonging to the non-active tenant is opened,
- **Then** the interface names the tenant the link belongs to and requires an explicit confirmation, **and** does not switch context automatically.

### UXAC-003 — Negative: no stale tenant cache survives a switch **[TENANT]**

- **Requirements:** `FR-017`, `FR-019`, `TEN-022`
- **Bounded context:** Tenant and Organization · **Step:** 3
- **Given** a user has browsed customers in tenant A,
- **When** the user switches to tenant B,
- **Then** no record, list, search result, or cached figure from tenant A is reachable or displayed at any point during or after the switch, and no frame renders tenant A data under tenant B's label.

### UXAC-004 — Negative: identical phone numbers are not merged **[TENANT]**

- **Requirements:** `FR-019`, `FR-021`, `FR-022`
- **Bounded context:** Customer Management · **Step:** 3
- **Given** the fictional phone number `0812-XXXX-1234` is registered as a customer in tenant A and separately in tenant B,
- **When** either tenant views its customer record,
- **Then** two unrelated profiles exist, no interface element suggests they are the same person, and no total, history, or loyalty balance combines them.

### UXAC-005 — Negative: a client-supplied tenant hint grants nothing **[TENANT]**

- **Requirements:** `FR-016`, `FR-018`, `SEC-012`
- **Bounded context:** Identity and Access · **Step:** 3
- **Given** a Console Web user with a membership only in tenant A,
- **When** a URL containing tenant B's slug is opened,
- **Then** the response is Permission Denied (`UXS-010`), no data or data shape from tenant B is revealed, and the denial does not confirm whether the referenced record exists.

### UXAC-006 — Portfolio Mode shows aggregates only **[TENANT]**

- **Requirements:** `RPT-017`, `RPT-018`, `RPT-019`
- **Bounded context:** Reporting and Owner Portfolio · **Step:** 10
- **Given** an owner holding memberships in two tenants,
- **When** Portfolio Mode is displayed,
- **Then** only aggregate figures appear, no individual customer record is reachable, and no create, edit, or delete affordance exists anywhere in the mode.

### UXAC-007 — Negative: a revoked membership leaves the portfolio immediately **[TENANT]**

- **Requirements:** `FR-015`, `FR-017`, `TEN-003`
- **Bounded context:** Tenant and Organization · **Step:** 3
- **Given** an owner whose membership in tenant B is revoked while Portfolio Mode is open,
- **When** the next interaction occurs,
- **Then** tenant B is removed from the portfolio, its contribution is removed from every aggregate rather than left stale, and `UXS-013 Tenant Unavailable` explains the change without exposing tenant B data.

### UXAC-008 — Negative: a courier cannot browse the customer database **[TENANT]**

- **Requirements:** `FR-108`, `DEL-006`, `SEC-020`
- **Bounded context:** Courier Assignment and Settlement · **Step:** 8
- **Given** an authenticated Courier Internal,
- **When** any navigation path, deep link, or search entry point is attempted,
- **Then** no destination lists customers of the tenant, and any attempt to reach one is refused server-side, not merely hidden.

---

## 2. Financial integrity

### UXAC-010 — Money is integer Rupiah everywhere **[FINANCIAL]**

- **Requirements:** `FR-061`, `FIN-013`, `NFR-021`
- **Bounded context:** Payment and Receivables · **Step:** 5
- **Given** an order totalling `Rp79.000` with a partial payment of `Rp25.000`,
- **When** any surface displays the total, the paid amount, or the balance,
- **Then** each is displayed as integer Rupiah in Indonesian format, and no sub-Rupiah value or floating-point artefact appears anywhere.

### UXAC-011 — An offline payment never looks final **[FINANCIAL] [OFFLINE]**

- **Requirements:** `FR-059`, `FR-062`, `FIN-005`, `OFF-019`
- **Bounded context:** Payment and Receivables · **Step:** 5
- **Given** a cashier records a `Rp25.000` payment while the device is offline,
- **When** the confirmation and the printed receipt are produced,
- **Then** both state that the payment is recorded on the device and **not yet confirmed by the server**, the word "berhasil" is not used, and the order is not marked paid.

### UXAC-012 — Negative: an order is never marked paid on a client claim **[FINANCIAL]**

- **Requirements:** `FR-062`, `FR-063`, `FIN-005`
- **Bounded context:** Payment and Receivables · **Step:** 5
- **Given** a client submits a payment claim that the server has not verified,
- **When** the server processes it,
- **Then** the order's payment state does not change on the basis of the claim alone, and the interface reflects the server's state rather than the device's.

### UXAC-013 — Negative: a retry never creates a duplicate payment **[FINANCIAL] [OFFLINE]**

- **Requirements:** `FR-059`, `FR-064`, `OFF-019`, `FIN-021`
- **Bounded context:** Offline Synchronization · **Step:** 5
- **Given** a payment queued with a `client_reference` that fails to send three times,
- **When** each retry is attempted, including after an application kill and a device restart,
- **Then** the original `client_reference` is reused on every attempt, exactly one payment exists on the server, and the interface presents the already-applied response as success rather than as an error.

### UXAC-014 — Negative: no interface offers a fresh-reference resubmit **[FINANCIAL] [OFFLINE]**

- **Requirements:** `FR-059`, `FR-107`, `OFF-019`
- **Bounded context:** Offline Synchronization · **Step:** 5
- **Given** a pending or failed financial operation in the queue,
- **When** every available action on that item is enumerated,
- **Then** no action resubmits the operation with a newly generated `client_reference`.

### UXAC-015 — A price-list change never alters a historical order **[FINANCIAL]**

- **Requirements:** `FR-036`, `FR-037`, `FIN-013`
- **Bounded context:** Service Catalog and Pricing · **Step:** 4
- **Given** order `AL-2026-000123` was created at a service price of `Rp79.000`,
- **When** the tenant later changes the service price,
- **Then** the order, its invoice, and any reprint continue to show `Rp79.000`, and the pricing screen states this explicitly before the change is saved.

### UXAC-016 — Negative: a financial record has no delete path **[FINANCIAL]**

- **Requirements:** `FR-065`, `FR-069`, `FIN-021`, `FIN-022`
- **Bounded context:** Payment and Receivables · **Step:** 5
- **Given** any role on any surface,
- **When** the available actions on a posted financial record are enumerated,
- **Then** no delete action exists, and any correction affordance is labelled as creating a **reversal** or an **adjustment** entry by name.

### UXAC-017 — A cash variance is surfaced, never absorbed **[FINANCIAL]**

- **Requirements:** `FR-070`, `RPT-009`, `FIN-022`
- **Bounded context:** Payment and Receivables · **Step:** 5
- **Given** a shift close where counted cash differs from expected cash,
- **When** the close is attempted,
- **Then** the variance is displayed explicitly, an acknowledgement is required, and no affordance exists to round, clear, or suppress it from the report.

### UXAC-018 — Courier cash variance is recorded **[FINANCIAL]**

- **Requirements:** `FR-110`, `FR-111`, `DEL-030`, `RPT-008`
- **Bounded context:** Courier Assignment and Settlement · **Step:** 8
- **Given** a courier hands over less cash than the jobs recorded as collected,
- **When** settlement is completed,
- **Then** expected, counted, and variance are all shown, the variance is acknowledged and recorded with actor, timestamp, and reason, and nothing is auto-adjusted.

### UXAC-019 — Negative: unsynced financial work survives every context change **[FINANCIAL] [OFFLINE]**

- **Requirements:** `FR-059`, `FR-107`, `OFF-019`
- **Bounded context:** Offline Synchronization · **Step:** 5
- **Given** three unsynced financial operations on a device,
- **When** the user signs out, switches tenant, expires their session, upgrades the application, or clears the cache,
- **Then** all three remain in the queue, remain visible, and are not removed — removal requiring an explicit permission, a recorded reason, and an audit entry.

---

## 3. Offline and sync

### UXAC-020 — The user always knows whether the server acknowledged **[OFFLINE]**

- **Requirements:** `FR-059`, `FR-079`, `OFF-019`
- **Bounded context:** Offline Synchronization · **Step:** 5
- **Given** any operation with financial or custody significance,
- **When** its state is displayed on the item, in the queue, and in the home summary,
- **Then** exactly one of the nine sync states is shown as text plus icon, and `TERSINKRON` appears only after a server acknowledgement.

### UXAC-021 — Negative: no silent sync failure **[OFFLINE]**

- **Requirements:** `FR-059`, `FR-107`
- **Bounded context:** Offline Synchronization · **Step:** 5
- **Given** an operation whose bounded retry budget is exhausted,
- **When** the failure occurs,
- **Then** the item remains in the queue with the server's stated reason, the queue badge and home summary reflect it, and the item is never dropped, hidden, aged out, or collapsed into a generic offline indicator.

### UXAC-022 — Negative: a conflict is never silently overwritten **[FINANCIAL] [OFFLINE]**

- **Requirements:** `FR-059`, `FR-064`, `OFF-019`
- **Bounded context:** Offline Synchronization · **Step:** 5
- **Given** the server records `Rp79.000` and the device records `Rp75.000` for the same payment,
- **When** synchronisation occurs,
- **Then** `UXS-009 Conflict` presents both values with their timestamps, no option is preselected, the panel cannot be dismissed by navigating back, and no value is applied without an explicit human decision.

### UXAC-023 — App kill mid-submit loses nothing **[OFFLINE]**

- **Requirements:** `FR-059`, `OFF-019`
- **Bounded context:** Offline Synchronization · **Step:** 5
- **Given** a cashier submits an order and the application is killed during the submission,
- **When** the application is reopened,
- **Then** the operation is present in the queue with its original `client_reference`, and the POS draft — including captured photographs — is offered for resume.

### UXAC-024 — Dependent operations keep their order **[OFFLINE]**

- **Requirements:** `FR-059`, `FR-107`
- **Bounded context:** Offline Synchronization · **Step:** 5
- **Given** a queued `CreateOrder` followed by a queued `RecordPayment` for the same order,
- **When** the queue drains,
- **Then** the order is applied first, the payment does not jump ahead, and the interface shows the dependency where the predecessor has failed.

### UXAC-025 — Negative: offline search states its limits **[OFFLINE] [TENANT]**

- **Requirements:** `FR-023`, `FR-059`
- **Bounded context:** Customer Management · **Step:** 5
- **Given** a device is offline,
- **When** a customer search is performed,
- **Then** results come only from the local tenant-scoped cache, the interface states that results may be incomplete with the last sync time, and no result from any other tenant appears.

---

## 4. Order lifecycle, production, and quality control

### UXAC-030 — Only enumerated transitions are offered

- **Requirements:** `FR-071`, `FR-072`, `FR-080`
- **Bounded context:** Production Operations · **Step:** 6
- **Given** an order in status `WASHING`,
- **When** the transition actions are displayed,
- **Then** only transitions enumerated by the state machine from `WASHING` appear, and no generic "set status" control or free-text status field exists on any surface.

### UXAC-031 — Negative: a rejected transition changes nothing

- **Requirements:** `FR-072`, `FR-080`
- **Bounded context:** Production Operations · **Step:** 6
- **Given** a client requests a transition the server refuses,
- **When** the response is received,
- **Then** the order's status is unchanged, the interface states why, and no partial effect is applied.

### UXAC-032 — A QC waiver requires permission, reason, and audit

- **Requirements:** `FR-081`, `FR-084`, `FR-085`
- **Bounded context:** Quality Control and Rework · **Step:** 6
- **Given** a quality control result of `WAIVED_WITH_AUTHORIZATION`,
- **When** the waiver is recorded,
- **Then** the interface required an explicit permission, captured a reason, and produced an audit entry — and no path exists that waives silently.

### UXAC-033 — Negative: rework never restarts the aging clock

- **Requirements:** `FR-081`, `FR-112`, `UCL-001`
- **Bounded context:** Unclaimed Laundry Recovery · **Step:** 9
- **Given** an order that first reached `READY_FOR_PICKUP` on 12 July 2026, returned to `REWORK`, and reached `READY_FOR_PICKUP` again on 17 July 2026,
- **When** the unclaimed-laundry dashboard displays its age,
- **Then** the age is computed from 12 July 2026, the displayed first-ready timestamp is 12 July 2026, and no interface offers a way to edit it.

---

## 5. Tracking portal

### UXAC-040 — The primary status is above the fold

- **Requirements:** `FR-086`, `FR-087`, `TRK-013`, `NFR-011`
- **Bounded context:** Customer Tracking · **Step:** 7
- **Given** a valid tracking link opened on a 360 × 640 viewport,
- **When** the page renders,
- **Then** the primary status, the order reference `AL-2026-000123`, the masked name `Budi S.`, and the outlet are all readable without scrolling, and status is conveyed by text and icon rather than colour alone.

### UXAC-041 — Negative: the portal never shows a full address

- **Requirements:** `FR-090`, `TRK-010`, `TRK-011`
- **Bounded context:** Customer Tracking · **Step:** 7
- **Given** any order state and any portal screen,
- **When** the page content is enumerated,
- **Then** no full address, no full phone number, no unmasked customer name, no internal note, no cost, no margin, no audit record, and no photograph appears.

### UXAC-042 — Negative: the portal never shows another order

- **Requirements:** `FR-089`, `TRK-015`
- **Bounded context:** Customer Tracking · **Step:** 7
- **Given** a customer with three orders at the same outlet,
- **When** one order's tracking link is opened,
- **Then** only that order is shown, and no navigation, link, or hint reveals the existence of the others.

### UXAC-043 — Negative: an invalid token is indistinguishable from an unknown one

- **Requirements:** `FR-088`, `TRK-007`
- **Bounded context:** Customer Tracking · **Step:** 7
- **Given** a malformed token and a well-formed token for an order that does not exist,
- **When** each is opened,
- **Then** both produce the same page shape and message, neither confirms whether any order exists, and both offer *Hubungi Outlet*.

### UXAC-044 — Token revocation takes effect immediately

- **Requirements:** `FR-091`, `TRK-004`, `TRK-022`, `TRK-023`
- **Bounded context:** Customer Tracking · **Step:** 7
- **Given** a valid tracking link,
- **When** the outlet revokes it,
- **Then** the next request produces `SCR-TRK-006`, the revocation is recorded with actor, timestamp, and reason, and any re-issued access revokes the prior token.

### UXAC-045 — Negative: a token never appears in analytics or logs

- **Requirements:** `FR-091`, `SEC-041`, `TRK-007`
- **Bounded context:** Customer Tracking · **Step:** 7
- **Given** a portal session,
- **When** analytics events, telemetry, server logs, and outbound referrer headers are inspected,
- **Then** no plaintext token value appears in any of them.

### UXAC-046 — Every portal response is `noindex`

- **Requirements:** `FR-086`, `TRK-007`, `TRK-025`
- **Bounded context:** Customer Tracking · **Step:** 7
- **Given** any portal response, including every failure page,
- **When** its headers and markup are inspected,
- **Then** `noindex, nofollow` is present, and no path requires an application install to read the status.

### UXAC-047 — Rate limiting always offers a human path

- **Requirements:** `FR-088`, `TRK-007`, `SEC-030`
- **Bounded context:** Customer Tracking · **Step:** 7
- **Given** repeated token lookups exceeding the limit,
- **When** `SCR-TRK-008` is shown,
- **Then** it states when to retry in plain Bahasa Indonesia, offers *Hubungi Outlet*, and does not reveal whether the attempted token corresponds to a real order.

---

## 6. Pickup, delivery, and custody

### UXAC-050 — Route ordering is labelled a suggestion

- **Requirements:** `FR-103`, `DEL-010`, `RPT-015`
- **Bounded context:** Pickup and Delivery · **Step:** 8
- **Given** any surface that presents stop ordering — Console Web, Ops Android, the guest link, an export, or a printed sheet,
- **When** the stop list is displayed,
- **Then** it is labelled **USULAN RUTE — BUKAN RUTE OPTIMAL**, no optimal-route claim appears, and no arrival time is presented as guaranteed.

### UXAC-051 — Negative: `DELIVERED` is unreachable without proof

- **Requirements:** `FR-104`, `FR-105`, `DEL-027`
- **Bounded context:** Pickup and Delivery · **Step:** 8
- **Given** a delivery job in status `ARRIVED` with no captured proof,
- **When** every available action is enumerated,
- **Then** no path reaches `DELIVERED`, and a server-side attempt to set it is refused.

### UXAC-052 — Proof capture works offline **[OFFLINE]**

- **Requirements:** `FR-105`, `FR-107`, `DEL-013`, `DEL-034`
- **Bounded context:** Pickup and Delivery · **Step:** 8
- **Given** a courier with no connectivity,
- **When** proof is captured and the transition submitted,
- **Then** the proof is stored locally, shown with its sync state, uploaded on reconnect, and a retry using the original `ClientReference` records exactly one handover.

### UXAC-053 — A failed delivery is a first-class outcome

- **Requirements:** `FR-106`, `DEL-023`
- **Bounded context:** Pickup and Delivery · **Step:** 8
- **Given** a courier who cannot complete a delivery,
- **When** `FAILED` is recorded,
- **Then** a reason code and free text are required, the actor is recorded, the order returns to a defined status, and nothing is marked complete or paid.

### UXAC-054 — Negative: a guest link reaches exactly one job **[TENANT]**

- **Requirements:** `FR-108`, `FR-109`, `DEL-018`, `DEL-033`
- **Bounded context:** Courier Assignment and Settlement · **Step:** 8
- **Given** an external courier holding guest links for jobs in tenant A and tenant B,
- **When** either link is used,
- **Then** only the one assigned job is reachable, no customer history, other order, pricing, or tenant data is exposed, no full address appears in a shareable form, and no traversal between the two links is possible.

### UXAC-055 — Guest-link revocation is immediate

- **Requirements:** `FR-109`, `DEL-033`
- **Bounded context:** Courier Assignment and Settlement · **Step:** 8
- **Given** an active guest link,
- **When** it is revoked from Console Web,
- **Then** the next request from that link is refused, and no cached page continues to serve job detail.

---

## 7. Unclaimed laundry

### UXAC-060 — The dashboard exposes every required field

- **Requirements:** `FR-112`, `FR-113`, `FR-115`, `RPT-011`, `RPT-012`, `UCL-019`
- **Bounded context:** Unclaimed Laundry Recovery · **Step:** 9
- **Given** the unclaimed-laundry dashboard for `Outlet Cempaka`,
- **When** it is displayed,
- **Then** aging bucket, order count, customer count, outstanding balance, paid amount, reminder state, last response, assigned follow-up owner, promise to collect, dispute, and converted-to-delivery are all present, and every figure is tenant-scoped.

### UXAC-061 — Ladder stages differ and fire once

- **Requirements:** `FR-114`, `FR-116`, `UCL-006`, `UCL-030`, `NOT-003`
- **Bounded context:** Unclaimed Laundry Recovery · **Step:** 9
- **Given** an order aging past H+1, H+3, and H+7,
- **When** the reminder history is displayed,
- **Then** each stage appears at most once, each stage's message copy differs from the others, none was sent inside quiet hours `20.00–08.00` outlet local time, and H+7 created an assignable follow-up task with a named owner.

### UXAC-062 — Negative: no disposal capability exists

- **Requirements:** `FR-112`, `FR-117`, `UCL-022`, `UCL-028`
- **Bounded context:** Unclaimed Laundry Recovery · **Step:** 9
- **Given** an order at H+14 with an outstanding balance,
- **When** every available action on the case is enumerated across every surface and every role,
- **Then** no action discards, sells, auctions, donates, or transfers ownership of the laundry, and H+14 produces only an escalation to a named manager or owner.

### UXAC-063 — Negative: a reminder failure never changes order state

- **Requirements:** `FR-096`, `FR-099`, `NOT-027`, `DEL-035`
- **Bounded context:** Notification and Communication · **Step:** 7
- **Given** the messaging provider is unavailable when an H+3 reminder is due,
- **When** the send fails,
- **Then** the order's status and payment state are unchanged, the failure is visible and retried under a bounded policy, and `UXS-016 Provider Degraded` states that the order is unaffected.

### UXAC-064 — Negative: an opted-out customer receives no marketing message

- **Requirements:** `FR-097`, `FR-098`, `NOT-013`
- **Bounded context:** Notification and Communication · **Step:** 7
- **Given** a customer who opted out of marketing,
- **When** a marketing campaign runs and a data import is performed,
- **Then** no marketing message is delivered to that customer at any outlet of the tenant, the opt-out is not reset by the import, and no transactional path is used to deliver marketing content.

### UXAC-065 — Storage fee is not assumed active

- **Requirements:** `FR-117`, `UCL-022`
- **Bounded context:** Unclaimed Laundry Recovery · **Step:** 9
- **Given** a tenant that has not configured a storage fee,
- **When** the unclaimed-laundry dashboard is displayed,
- **Then** no storage-fee column, figure, or zero value appears, and no fee accrues to any order.

---

## 8. Authorisation, session, and states

### UXAC-070 — Negative: hiding a menu is not authorization **[TENANT]**

- **Requirements:** `FR-004`, `FR-018`, `SEC-012`
- **Bounded context:** Identity and Access · **Step:** 3
- **Given** a destination marked `HIDDEN` for a role in the role navigation matrix,
- **When** that destination is reached by a deep link, a stale bookmark, or a crafted request,
- **Then** the backend refuses the operation independently of the client, and the refusal does not depend on any client-side check.

### UXAC-071 — Session expiry preserves work **[OFFLINE]**

- **Requirements:** `FR-006`, `FR-059`, `SEC-018`
- **Bounded context:** Identity and Access · **Step:** 3
- **Given** a device with three unsynced operations and an in-progress POS draft,
- **When** the session expires,
- **Then** `UXS-011` states the unsynced count, re-authentication returns the user to the intended destination, and neither the queue nor the draft is cleared.

### UXAC-072 — Device revocation surfaces unsynced work **[FINANCIAL]**

- **Requirements:** `FR-007`, `FR-008`, `SEC-018`
- **Bounded context:** Identity and Access · **Step:** 3
- **Given** a device holding two unsynced payments,
- **When** the device is revoked,
- **Then** access stops immediately, the unsynced operations are listed by reference and amount for handover, none is silently discarded or synced under the revoked credential, and no other device is forced to re-authenticate.

### UXAC-073 — Every UX state has a recovery path

- **Requirements:** `NFR-011`, `NFR-012`
- **Bounded context:** Applies across all contexts · **Step:** 2 for definition, per-surface step for delivery
- **Given** any of the twenty states `UXS-001` … `UXS-020`,
- **When** the state is displayed on any surface,
- **Then** at least one recovery action is offered, the message states what happened and what to do, and no state leaves the user with no forward and no backward path.

### UXAC-074 — Negative: an error is never presented as an empty result

- **Requirements:** `NFR-011`
- **Bounded context:** Applies across all contexts · **Step:** per-surface
- **Given** a request that failed,
- **When** the result is rendered,
- **Then** `UXS-003 Error` is shown rather than `UXS-002 Empty`, because an empty list reads as "nothing exists" and a failure does not.

### UXAC-075 — Subscription limits never block data export

- **Requirements:** `SUB-005`, `SUB-011`, `TEN-028`
- **Bounded context:** Subscription and Entitlement · **Step:** 12
- **Given** a tenant whose subscription has lapsed,
- **When** the tenant attempts to export its own business data,
- **Then** the export is permitted per policy, `UXS-015` describes Starter order volume as fair-use rather than a hard cutoff, and no security control, tenant isolation, or backup capability is presented as a paid tier.

### UXAC-076 — Negative: support access is never silent **[TENANT]**

- **Requirements:** `SUB-016`, `SUB-017`, `TEN-022`, `SEC-055`
- **Bounded context:** Platform Administration · **Step:** 12
- **Given** a platform support session against a tenant,
- **When** the tenant's Support Access screen is displayed,
- **Then** the session appears with who, when, for how long, and why, and no path exists for platform access that does not produce such a record.

---

## 9. Accessibility and responsiveness

### UXAC-080 — Status is never colour alone

- **Requirements:** `NFR-013`, `NFR-014`
- **Bounded context:** Applies across all contexts · **Step:** per-surface
- **Given** any status indicator on any surface — order status, sync state, aging bucket, mode badge,
- **When** it is rendered,
- **Then** it carries a text label, and where a chip is used, an icon as well.

### UXAC-081 — Layouts survive the largest supported font scale

- **Requirements:** `NFR-013`, `NFR-015`
- **Bounded context:** Applies across all contexts · **Step:** per-surface
- **Given** the largest supported system font scale,
- **When** any operational screen is displayed,
- **Then** the total, the balance, the sync count, the tenant name, and the primary action all remain visible and legible, with no critical information truncated.

### UXAC-082 — The page body never scrolls horizontally

- **Requirements:** `NFR-013`, `NFR-016`
- **Bounded context:** Applies across all contexts · **Step:** per-surface
- **Given** wide content such as a table, a production board, or a report,
- **When** it is displayed at the compact breakpoint `<600px`,
- **Then** horizontal scrolling occurs inside the content container only, and the page body does not scroll horizontally.

### UXAC-083 — Accessibility claims are stated honestly

- **Requirements:** `NFR-013`
- **Bounded context:** Applies across all contexts · **Step:** 13 for verification
- **Given** any Step 2 document, wireframe, or design artefact,
- **When** it makes an accessibility statement,
- **Then** it reads exactly **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**, and no artefact claims a passed accessibility audit.

---

## 10. Traceability obligation

Every criterion above cites at least one requirement ID. When requirements change, the criteria that
cite them are reviewed **in the same pull request** — criteria that silently outlive the requirement
they described create false confidence and are worse than no criteria.

Bidirectional traceability — requirement to criterion to bounded context to roadmap step, and back —
is maintained in the Step 1 traceability matrix and extended by this document. An orphan in either
direction is a traceability defect.

---

## 11. Related documents

- [`./SCREEN_INVENTORY.md`](./SCREEN_INVENTORY.md)
- [`./CRITICAL_JOURNEYS.md`](./CRITICAL_JOURNEYS.md)
- [`./UX_STATE_MODEL.md`](./UX_STATE_MODEL.md)
- [`./OFFLINE_AND_SYNC_UX.md`](./OFFLINE_AND_SYNC_UX.md)
- [`./USABILITY_TEST_PLAN.md`](./USABILITY_TEST_PLAN.md)
- [`./UX_OPEN_QUESTIONS.md`](./UX_OPEN_QUESTIONS.md)

## 12. Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| Criteria defined | **IN PROGRESS** |
| Criteria executed | **NONE** |
| All criteria | **unmet by definition — nothing is implemented** |
| Application CI | **NOT APPLICABLE** |
| UAT | **NOT STARTED** |

`GO` is conferred by the repository owner and is never self-declared.
