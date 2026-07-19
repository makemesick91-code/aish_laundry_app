# Screen Inventory — Aish Laundry App

**Step 2 — Design System and UX Foundation. Documentation only.**

## Purpose

This document enumerates every screen surface the Aish Laundry App is obligated to provide across its
four client surfaces, and records — for each one — the persona it serves, the requirements it must
satisfy, the states it must survive, and the privacy rules it must not breach.

An entry in this inventory describes an **obligation**, never an achievement. Nothing listed here has
been built, rendered, compiled, or tested. **Documentation is not implementation.**

## Status block

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| Every screen in this inventory | **NOT IMPLEMENTED** |
| Flutter workspace | **ABSENT** |
| Backend runtime | **ABSENT** |
| Application CI | **NOT APPLICABLE** |
| UAT | **NOT STARTED** |

`GO` for Step 2 is conferred by the repository owner and is never self-declared.

## How to read this document

- Screens are grouped by platform, then listed in the order a user most plausibly meets them.
- Each screen carries exactly twenty fields, in a fixed order, so that entries can be diffed and
  validated mechanically.
- Requirement IDs cited here are the requirement baseline IDs owned by the Product Requirements
  Document. A screen that cites no requirement is a defect; a requirement invented inside a screen
  entry is a graver defect.
- Statuses use the approved vocabulary only. No screen may be described as working.

## Screen ID scheme

```
SCR-<SURFACE>-<NNN>

SURFACE ::= CUS  Customer Android (Flutter)
          | OPS  Ops Android (Flutter)
          | CON  Console Web (Flutter Web)
          | TRK  Public Tracking Portal (browser, no app install)

NNN     ::= zero-padded ordinal, permanent, never reused, never renumbered
```

A retired screen keeps its ID and gains a retirement note. Reusing an ID silently rewrites the meaning
of every citation elsewhere.

## Legend — the twenty mandatory fields

| Field | What it records |
|---|---|
| Platform | The client surface that renders the screen. |
| Persona | The persona IDs (P-01..P-14) the screen is designed for. |
| Purpose | The single job the screen exists to do. |
| Requirement IDs | The requirement baseline IDs the screen must satisfy. |
| Entry points | How a user legitimately arrives. |
| Exit points | Where the user can legitimately go next. |
| Data displayed | The fields the screen is permitted to render. |
| Data masked | What must be masked, truncated, or withheld. |
| Primary action | The one dominant action, styled as such. |
| Secondary action | Lower-emphasis actions, spatially separated from destructive ones. |
| Empty state | What is shown when there is genuinely nothing. |
| Loading state | What is shown while data is in flight. |
| Error state | What is shown on failure, including the recovery step. |
| Offline behaviour | Behaviour with no connectivity, and what is queued. |
| Permission behaviour | What a user without permission sees. Client-side menu visibility is **not** authorization; backend authorization is authoritative from Step 3. |
| Accessibility notes | Contrast, focus, target size, font scaling, semantics. |
| Responsive behaviour | Layout across compact (<600px), medium (600–1023px), expanded (1024–1439px), wide (>=1440px). |
| Privacy and security notes | Classification, masking, and exposure constraints. |
| Analytics intent | What may be measured, and what must never be measured. |
| Future implementation step | The roadmap Step that will build it. |

Accessibility for every screen in this document is **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT
YET RUNTIME-TESTED**.

## Conventions used in examples

Every example datum below is fictional. Tenant "Laundry Bersih Sejahtera"; outlets "Outlet Cempaka"
and "Outlet Melati"; customers "Budi Santoso", "Siti Rahmawati", "Dewi Anggraini"; phone always shown
as `0812-XXXX-1234`; order numbers like `AL-2026-000123`. Money is integer Rupiah rendered `Rp79.000`
or `Rp25.000`. Weight uses a comma decimal: `1,5 kg`. Times are 24-hour (`14:30`), displayed in the
outlet timezone (Asia/Jakarta) and stored in UTC.

## Related documents

- [UX State Model](./UX_STATE_MODEL.md)
- [Offline and Sync UX](./OFFLINE_AND_SYNC_UX.md)
- [Critical Journeys](./CRITICAL_JOURNEYS.md)
- [UX Acceptance Criteria](./UX_ACCEPTANCE_CRITERIA.md)
- [Wireframes](./wireframes/README.md)
- [Role Navigation Matrix](./information-architecture/ROLE_NAVIGATION_MATRIX.md)

## Summary count by platform

| Platform | ID range | Screens | Implementation status |
|---|---|---|---|
| Customer Android (Flutter) | SCR-CUS-001 .. SCR-CUS-018 | 18 | **NOT IMPLEMENTED** |
| Ops Android (Flutter) | SCR-OPS-001 .. SCR-OPS-037 | 37 | **NOT IMPLEMENTED** |
| Console Web (Flutter Web) | SCR-CON-001 .. SCR-CON-023 | 23 | **NOT IMPLEMENTED** |
| Public Tracking Portal (browser, no app install) | SCR-TRK-001 .. SCR-TRK-011 | 11 | **NOT IMPLEMENTED** |
| **Total** | — | **89** | **NOT IMPLEMENTED** |

---

# Customer Android (Flutter)

### SCR-CUS-001 — Onboarding / Welcome

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Introduce what the app does in three honest statements and route the visitor to phone login or to public tracking without an account. |
| Requirement IDs | FR-001, FR-002, TRK-001, NFR-004 |
| Entry points | First launch after install; launch while unauthenticated; a marketing deep link. |
| Exit points | SCR-CUS-002 Phone Entry; the public tracking portal in an external browser tab; system back exits the app. |
| Data displayed | Product name, three capability statements, language indicator (Bahasa Indonesia), app version string. |
| Data masked | None — this screen holds no personal data and must never pre-fill a remembered phone number. |
| Primary action | "Masuk dengan nomor HP" — proceed to phone entry. |
| Secondary action | "Lacak pesanan tanpa akun" — open the public tracking portal. |
| Empty state | Not applicable; the screen is entirely static copy. |
| Loading state | Only a brief splash while locale and theme resolve; never a spinner longer than the first frame. |
| Error state | If configuration fails to load, show a plain retry panel explaining that the app could not start and offering "Coba lagi". |
| Offline behaviour | Fully usable offline; the tracking link is disabled with the reason "Butuh koneksi internet" rather than failing silently. |
| Permission behaviour | No permission is required or implied; nothing on this screen reads tenant data. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Copy survives 200% font scaling; both buttons are at least 48dp tall. |
| Responsive behaviour | Compact: single column, buttons pinned to the bottom. Medium and above: content capped at a readable measure and centred. |
| Privacy and security notes | Classified PUBLIC. No identifiers are collected before consent to proceed; no device fingerprint is taken here. |
| Analytics intent | Count of onboarding views and which of the two routes was chosen. No device identifier, no phone number. |
| Future implementation step | Step 11 — Customer Android Experience. |

### SCR-CUS-002 — Phone Entry and OTP Request

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Collect an Indonesian mobile number and request a one-time password for it. |
| Requirement IDs | FR-001, FR-002, SEC-004, SEC-011, NOT-012 |
| Entry points | SCR-CUS-001; a session-expired redirect; explicit sign-out. |
| Exit points | SCR-CUS-003 OTP Verification on success; back to SCR-CUS-001. |
| Data displayed | Country prefix `+62`, the number as typed, a rate-limit notice when applicable, terms and privacy links. |
| Data masked | The typed number is never echoed into logs, analytics, or crash traces in any form. |
| Primary action | "Kirim kode OTP" — request an OTP for the entered number. |
| Secondary action | Change country prefix; open the privacy notice. |
| Empty state | The action stays disabled until the number is structurally valid. |
| Loading state | Button enters a busy state with the label "Mengirim…" and the field locks to prevent a second submit. |
| Error state | Invalid format, unreachable network, and provider rejection each get distinct Bahasa Indonesia copy with a stated next step. |
| Offline behaviour | OTP request is never queued — it is refused immediately with "Tidak ada koneksi", because a deferred OTP is worse than no OTP. |
| Permission behaviour | Unauthenticated by definition; the server decides whether the number may receive an OTP. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Numeric keyboard, labelled field, error text tied to the input by semantics rather than colour alone. |
| Responsive behaviour | Compact: full-width field above the fold with the keyboard visible. Medium and above: centred card, maximum 480px wide. |
| Privacy and security notes | The phone number is CONFIDENTIAL. Server-side rate limiting and progressive backoff are mandatory; the OTP itself is SECRET and never appears on this screen. |
| Analytics intent | OTP request attempt count and failure reason category only. Never the number, never the OTP. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

### SCR-CUS-003 — OTP Verification

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Verify the one-time password and establish an authenticated session bound to this device. |
| Requirement IDs | FR-002, FR-003, SEC-005, SEC-012, SEC-020 |
| Entry points | SCR-CUS-002 after a successful send; a re-authentication prompt from SCR-CUS-017. |
| Exit points | SCR-CUS-004 Home on success; back to SCR-CUS-002 to correct the number. |
| Data displayed | The masked destination `0812-XXXX-1234`, the OTP input, a resend countdown, remaining attempt guidance. |
| Data masked | The destination number is always shown masked; the OTP is never re-displayed after entry. |
| Primary action | "Verifikasi" — submit the code. |
| Secondary action | "Kirim ulang kode" after the countdown expires; "Ubah nomor". |
| Empty state | Submit stays disabled until the full code length is entered. |
| Loading state | Inline progress on the button; the field locks so a double tap cannot burn an attempt. |
| Error state | Wrong code, expired code, and too many attempts are distinct messages; lockout states the wait duration explicitly. |
| Offline behaviour | Verification is refused offline with a clear message; no code is cached for later comparison on the device. |
| Permission behaviour | No tenant data is reachable until the server issues a session; the client asserts nothing. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Autofill from SMS is supported without disabling manual entry; the countdown is announced, not only animated. |
| Responsive behaviour | Compact: code boxes fill the width. Medium and above: centred card with the same box sizing. |
| Privacy and security notes | The OTP is SECRET — never logged, never in telemetry, never in a crash report. Tokens land in platform keystore-backed storage only. |
| Analytics intent | Verification success and failure category. Never the code, never the number, never the token. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

### SCR-CUS-004 — Home

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Give a returning customer the single most useful next action and a glance at anything currently in progress. |
| Requirement IDs | FR-021, FR-086, FR-100, TRK-002, NFR-011 |
| Entry points | Successful OTP verification; app resume with a valid session; a notification tap that has no more specific target. |
| Exit points | SCR-CUS-005, SCR-CUS-006, SCR-CUS-009, SCR-CUS-012, SCR-CUS-016, SCR-CUS-017. |
| Data displayed | Greeting with the customer's own name, count of active orders, the most recent order card (`AL-2026-000123`, status, outlet "Outlet Cempaka"), outstanding balance in Rupiah. |
| Data masked | No other customer's data ever appears; staff identity is limited to an operational first name where shown at all. |
| Primary action | "Minta penjemputan" — start a pickup request. |
| Secondary action | Open active orders; open order history; open notifications. |
| Empty state | A first-time customer sees an explanation of how to place a first order at an outlet, not an empty card grid. |
| Loading state | Skeleton cards preserving final layout height so nothing shifts when data lands. |
| Error state | If the summary fails, the screen keeps working and shows an inline "Gagal memuat ringkasan — Coba lagi" band rather than a blank page. |
| Offline behaviour | Last successfully fetched summary is shown with a visible "Data terakhir 14:30" stamp; nothing is presented as live when it is not. |
| Permission behaviour | Only orders belonging to this customer within this tenant are ever fetched; the client never supplies a tenant identifier as proof. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Order status carries text and an icon, never colour alone. |
| Responsive behaviour | Compact: vertical card stack. Medium: two columns. Expanded and wide: not a target surface, but the layout must not break if the app is run on a tablet. |
| Privacy and security notes | Balances and order data are CONFIDENTIAL and tenant-scoped. Cached home data is cleared on sign-out. |
| Analytics intent | Which entry tile was used and time to first meaningful action. No order contents, no amounts. |
| Future implementation step | Step 11 — Customer Android Experience. |

### SCR-CUS-005 — Active Orders

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | List every order not yet COMPLETED or CANCELLED, ordered so the one needing attention is first. |
| Requirement IDs | FR-021, FR-086, FR-087, UCL-004, NFR-012 |
| Entry points | SCR-CUS-004; the bottom navigation; a status-change notification. |
| Exit points | SCR-CUS-006 Order Detail; pull-to-refresh stays on the screen. |
| Data displayed | Order number, canonical status label, outlet name, intake date, estimated ready time, amount due, an "aging" hint for orders sitting in READY_FOR_PICKUP. |
| Data masked | Internal production notes, operator identity, and cost or margin data are never present in the payload. |
| Primary action | Open the selected order. |
| Secondary action | Filter by outlet; refresh. |
| Empty state | "Belum ada pesanan aktif" with a route to place a pickup request — not a bare blank list. |
| Loading state | Three skeleton rows; the filter control remains interactive. |
| Error state | Load failure shows a retry row and keeps any cached list visible beneath it, clearly stamped as cached. |
| Offline behaviour | Cached list is readable and explicitly labelled stale; refresh is disabled with a stated reason. |
| Permission behaviour | Server returns only this customer's orders in this tenant; an order ID from another customer resolves to not-found, never to a partial record. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Each row is a single focusable target of at least 48dp with a full semantic label. |
| Responsive behaviour | Compact: one card per row. Medium: two-column grid. Expanded and wide: content width capped for readability. |
| Privacy and security notes | Tenant-scoped, CONFIDENTIAL. No cross-tenant aggregation exists on this surface even for a customer known in two tenants — those are two unrelated profiles. |
| Analytics intent | List length distribution and refresh frequency. No order numbers, no amounts. |
| Future implementation step | Step 11 — Customer Android Experience. |

### SCR-CUS-006 — Order Detail

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact, P-14 Authorized Order Recipient |
| Purpose | Show one order in full: what was taken in, what it costs, where it is, and what the customer can do next. |
| Requirement IDs | FR-021, FR-048, FR-062, FR-086, FR-088, FIN-005 |
| Entry points | SCR-CUS-005; SCR-CUS-012; a deep link from a WhatsApp notification. |
| Exit points | SCR-CUS-007 Timeline; SCR-CUS-008 Payment Summary; SCR-CUS-011 Delivery Schedule; SCR-CUS-015 Feedback when COMPLETED. |
| Data displayed | Order number `AL-2026-000123`, status, outlet, service lines with weight `1,5 kg` and unit counts, item-level notes the customer supplied, subtotal, discount, total `Rp79.000`, paid amount, balance. |
| Data masked | Internal QC remarks, staff full names, courier phone numbers, unit cost, and margin are never included. |
| Primary action | Contextual: "Bayar sekarang" when a balance exists, otherwise "Lacak pesanan". |
| Secondary action | View timeline; request delivery; contact the outlet. |
| Empty state | Not applicable — an order always has content; a missing line item surfaces as an explicit "Rincian belum tersedia" row. |
| Loading state | Header renders from the cached list row immediately while the detail body shows skeletons. |
| Error state | A failed load offers retry; a permission failure resolves as not-found with no hint that the order exists elsewhere. |
| Offline behaviour | Previously opened orders are readable from cache with a staleness stamp; payment actions are disabled offline. |
| Permission behaviour | Authorization is server-side; an authorized recipient sees a reduced field set, and hiding the pay button on the client is never treated as the control. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Money is announced as a full Rupiah value, not as digits. |
| Responsive behaviour | Compact: stacked sections. Medium and above: two-column split with the money summary pinned beside the line items. |
| Privacy and security notes | CONFIDENTIAL. An order is never shown as paid on a client claim; paid state is only ever rendered from server-confirmed data. |
| Analytics intent | Detail opens and which secondary route was taken. No line items, no totals. |
| Future implementation step | Step 11 — Customer Android Experience. |

### SCR-CUS-007 — Order Timeline

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Present the order's status history as a readable chronology in customer language, not internal jargon. |
| Requirement IDs | FR-086, FR-088, FR-089, TRK-004, NFR-013 |
| Entry points | SCR-CUS-006; a status-change push notification. |
| Exit points | Back to SCR-CUS-006; forward to SCR-CUS-011 when a delivery is scheduled. |
| Data displayed | Each reached status with its outlet-local timestamp (`14:30`), a plain-language description, and the current step highlighted. |
| Data masked | Which operator performed a transition is withheld; reason codes for internal ISSUE handling are summarised, never quoted verbatim. |
| Primary action | None dominant; the screen is informational and its primary affordance is returning to the order. |
| Secondary action | Refresh; contact the outlet. |
| Empty state | A DRAFT order shows only the intake step with "Menunggu konfirmasi outlet". |
| Loading state | The timeline rail renders immediately with placeholder nodes so the shape is stable. |
| Error state | On failure the last cached timeline is kept with a "Tidak dapat memperbarui" band and a retry control. |
| Offline behaviour | Fully readable from cache; the current step is stamped with the time it was last confirmed by the server. |
| Permission behaviour | Only statuses this customer is entitled to see are returned; internal-only substates are never emitted to this surface. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The current step is conveyed by text and icon in addition to colour, and the rail is not the only cue. |
| Responsive behaviour | Compact: vertical rail. Medium and above: vertical rail with wider description column; never a horizontal stepper that truncates. |
| Privacy and security notes | CONFIDENTIAL. A REWORK excursion is shown honestly but without blaming a named employee. |
| Analytics intent | Timeline views per order and depth scrolled. No timestamps tied to an identifiable customer. |
| Future implementation step | Step 7 — Customer Tracking and WhatsApp. |

### SCR-CUS-008 — Payment Summary

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Show exactly what is owed on an order, what has been paid, and how the remainder may be settled. |
| Requirement IDs | FR-062, FR-063, FR-065, FIN-005, FIN-011, FIN-018 |
| Entry points | SCR-CUS-006; a payment reminder notification; SCR-CUS-012 for an unpaid historical order. |
| Exit points | Back to SCR-CUS-006; out to a payment provider flow; to SCR-CUS-018 on an unrecoverable error. |
| Data displayed | Total `Rp79.000`, paid `Rp25.000`, balance, each recorded payment with method and outlet-local time, and the price snapshot that applied at intake. |
| Data masked | Gateway references are truncated; no card or account identifiers are ever rendered in full. |
| Primary action | "Bayar sisa tagihan" when a balance remains. |
| Secondary action | View payment history; download or share the invoice. |
| Empty state | A fully settled order shows a clear "Lunas" state with the settling timestamp rather than a hidden section. |
| Loading state | Amounts render only when confirmed; a skeleton is shown instead of a provisional number, because a wrong figure is worse than a delayed one. |
| Error state | A failed payment attempt states plainly whether the money moved, and directs the customer to the outlet if the state is genuinely unknown. |
| Offline behaviour | Balances are readable from cache with a staleness stamp; initiating a payment is disabled offline with the reason shown. |
| Permission behaviour | Only this customer's own payments are returned; the server never accepts a client assertion that an order is paid. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The balance is the highest-contrast element and is announced first. |
| Responsive behaviour | Compact: summary above the fold, history below. Medium and above: summary card beside the history list. |
| Privacy and security notes | Financial data is CONFIDENTIAL and integer Rupiah throughout; no floating-point value ever reaches this screen. |
| Analytics intent | Payment initiation and completion counts by method category. Never an amount, never a gateway reference. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-CUS-009 — Pickup Request

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Let a customer ask for their laundry to be collected at a chosen address within a chosen time window. |
| Requirement IDs | FR-100, FR-101, DEL-001, DEL-004, DEL-006 |
| Entry points | SCR-CUS-004 primary action; SCR-CUS-006 for a repeat order; a marketing deep link. |
| Exit points | Confirmation returning to SCR-CUS-004; SCR-CUS-010 to add a missing address. |
| Data displayed | Selected address label, outlet that will serve it, available date and time windows (for example `14:30`–`16:30`), service note field, coverage warning when the address sits outside a zone. |
| Data masked | Courier identity and phone are not shown at request time; only a role label is presented. |
| Primary action | "Kirim permintaan penjemputan" — submit as REQUESTED. |
| Secondary action | Change address; change time window; cancel the draft. |
| Empty state | With no saved address, the screen leads directly into SCR-CUS-010 rather than presenting an unusable form. |
| Loading state | Window availability loads inline; the submit control stays disabled until a real window is selected. |
| Error state | Out-of-coverage, no available window, and submission failure are three distinct messages, each naming the next step. |
| Offline behaviour | The draft is retained locally but is never presented as submitted; submission requires connectivity and says so. |
| Permission behaviour | Only addresses owned by this customer are selectable; the serving outlet is decided server-side, never chosen by the client. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Time windows are radio-semantics with 48dp targets and readable labels at 200% scaling. |
| Responsive behaviour | Compact: single-column wizard. Medium and above: address panel beside the window picker. |
| Privacy and security notes | The address is RESTRICTED; it is transmitted only to the serving tenant and never rendered on any public surface. |
| Analytics intent | Request submission and abandonment counts by step. No address text, no coordinates. |
| Future implementation step | Step 8 — Pickup and Delivery Operations. |

### SCR-CUS-010 — Address Book and Address Form

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Maintain the customer's own saved pickup and delivery addresses. |
| Requirement IDs | FR-022, FR-023, DEL-002, SEC-030, SEC-041 |
| Entry points | SCR-CUS-009 when no address exists; SCR-CUS-017 Profile; SCR-CUS-011. |
| Exit points | Back to the caller with the chosen address; delete confirmation stays in place. |
| Data displayed | Address label ("Rumah", "Kantor"), full address text, landmark note, contact name, contact phone as `0812-XXXX-1234`, default flag, coverage indicator. |
| Data masked | The contact phone is masked in the list and revealed in full only inside the edit form the owner opened. |
| Primary action | "Simpan alamat" — create or update. |
| Secondary action | Set as default; edit; delete with confirmation. |
| Empty state | "Belum ada alamat tersimpan" with a single prominent add action and a one-line explanation of why an address is needed. |
| Loading state | Coverage checking shows an inline indicator on the address card, not a blocking overlay. |
| Error state | Validation failures are field-level; a save failure retains all typed input and offers retry without re-entry. |
| Offline behaviour | Existing addresses are readable offline; edits queue with a reused `client_reference` and are shown as "Menunggu sinkronisasi", never as saved. |
| Permission behaviour | A customer may only read and mutate addresses they own; the server rejects any address ID outside the caller's ownership. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Delete is spatially separated from edit and requires explicit confirmation. |
| Responsive behaviour | Compact: list then full-screen form. Medium and above: master–detail with the list on the left. |
| Privacy and security notes | Addresses are RESTRICTED; they never appear on the public tracking portal, never in analytics, and never in a notification body. |
| Analytics intent | Address count per customer as a bucketed distribution. Never address text, never coordinates. |
| Future implementation step | Step 8 — Pickup and Delivery Operations. |

### SCR-CUS-011 — Delivery Schedule

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact, P-14 Authorized Order Recipient |
| Purpose | Let a customer choose or change when a finished order is delivered, and see the current delivery state. |
| Requirement IDs | FR-102, FR-103, DEL-011, DEL-014, DEL-027 |
| Entry points | SCR-CUS-006 when the order is READY_FOR_PICKUP; a "siap diantar" notification; SCR-CUS-007. |
| Exit points | Back to SCR-CUS-006; SCR-CUS-010 to change the delivery address. |
| Data displayed | Delivery address label, chosen window, delivery status from the canonical set (SCHEDULED, ASSIGNED, EN_ROUTE, ARRIVED, DELIVERED, FAILED, RESCHEDULED), and the recipient name the customer nominated. |
| Data masked | Courier full name and phone are withheld; only a role label and, where policy permits, a first name are shown. |
| Primary action | "Jadwalkan pengantaran" — or "Ubah jadwal" once scheduled. |
| Secondary action | Nominate an authorized recipient; switch back to self-collection at the outlet. |
| Empty state | An order not yet READY_FOR_PICKUP shows why scheduling is not open yet, with the expected readiness time. |
| Loading state | Window availability streams in per date; already-loaded dates stay selectable. |
| Error state | A FAILED delivery is presented as a first-class outcome with the recorded reason and a clear reschedule action, not as an app error. |
| Offline behaviour | Current schedule readable from cache; changing a schedule requires connectivity and states so plainly. |
| Permission behaviour | Only the order's own customer, or a nominated authorized recipient with reduced fields, may open this screen; the server enforces it. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Status is text plus icon; the selected window is announced on change. |
| Responsive behaviour | Compact: date strip above a window list. Medium and above: calendar column beside the window list. |
| Privacy and security notes | RESTRICTED address data; no route information, no other stop, and no other customer is ever visible here. |
| Analytics intent | Schedule and reschedule counts, and failed-delivery reason categories. No addresses, no names. |
| Future implementation step | Step 8 — Pickup and Delivery Operations. |

### SCR-CUS-012 — Order History

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Let a customer find a past order — by date, outlet, or order number — and reopen its detail or invoice. |
| Requirement IDs | FR-021, FR-024, FR-062, RPT-014, NFR-014 |
| Entry points | Bottom navigation; SCR-CUS-004; a search from SCR-CUS-017. |
| Exit points | SCR-CUS-006 Order Detail; SCR-CUS-008 for an unsettled historical order. |
| Data displayed | Order number, completion date, outlet, service summary, total in Rupiah, settlement state, feedback-given indicator. |
| Data masked | Historical staff assignment and internal notes are absent from the payload entirely. |
| Primary action | Open the selected historical order. |
| Secondary action | Filter by outlet and date range; search by order number. |
| Empty state | "Belum ada riwayat pesanan" with an explanation that completed orders appear here. |
| Loading state | Paged skeleton rows; the applied filter chip stays visible while the page loads. |
| Error state | A page-load failure appends an inline retry row rather than discarding pages already shown. |
| Offline behaviour | The most recent page is cached and readable; older pages state that connectivity is required to fetch further back. |
| Permission behaviour | Scoped to this customer within this tenant; a customer known to two tenants sees two unrelated histories and never a merged one. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Pagination is announced and the filter state is exposed to assistive technology. |
| Responsive behaviour | Compact: single list. Medium: two columns. Expanded and wide: capped width with the filter bar persistent. |
| Privacy and security notes | CONFIDENTIAL, tenant-scoped, and cleared from device cache on sign-out or tenant change. |
| Analytics intent | Search and filter usage rates. No order numbers, no totals. |
| Future implementation step | Step 11 — Customer Android Experience. |

### SCR-CUS-013 — Loyalty

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer |
| Purpose | Show the customer's loyalty balance, how it was earned, and what it can be redeemed against — without overstating any benefit. |
| Requirement IDs | FR-118, FR-120, FIN-030, NFR-015 |
| Entry points | SCR-CUS-004; SCR-CUS-017 Profile. |
| Exit points | Back to Home; into SCR-CUS-006 for the order that generated an entry. |
| Data displayed | Current point balance, ledger of earning and redemption entries with outlet-local dates, expiry rules as configured by the tenant, redemption options. |
| Data masked | Nothing personal beyond the customer's own record; other customers' tiers or standings are never referenced. |
| Primary action | "Tukarkan poin" where a redemption is actually available. |
| Secondary action | View the full ledger; read the programme terms. |
| Empty state | A tenant that runs no loyalty programme sees an honest "Program loyalitas belum aktif di tenant ini", not a zero-point dashboard implying a programme exists. |
| Loading state | Balance and ledger load independently; the balance never renders a provisional number. |
| Error state | A ledger failure keeps the confirmed balance visible and marks the ledger section as unavailable with a retry. |
| Offline behaviour | Last confirmed balance readable with a staleness stamp; redemption is disabled offline because it changes financial state. |
| Permission behaviour | Own record only; redemption legality is decided server-side and is never inferred from an enabled button. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Ledger entries are table-semantic with header association. |
| Responsive behaviour | Compact: balance card above the ledger. Medium and above: balance card beside a scrolling ledger. |
| Privacy and security notes | CONFIDENTIAL and tenant-scoped; loyalty value is integer Rupiah where it is expressed in money. |
| Analytics intent | Redemption attempt and completion counts. No balances, no ledger contents. |
| Future implementation step | Step 11 — Customer Android Experience. |

### SCR-CUS-014 — Membership

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Show the customer's membership or deposit standing with a tenant and the terms attached to it. |
| Requirement IDs | FR-118, FR-120, FIN-031, SEC-045 |
| Entry points | SCR-CUS-013; SCR-CUS-017 Profile; a membership expiry notification. |
| Exit points | Back to Profile; into SCR-CUS-008 when a deposit was applied to an order. |
| Data displayed | Membership tier name as the tenant defined it, validity window, deposit balance in Rupiah, the entitlements actually granted, and the movements that changed the deposit. |
| Data masked | Tenant-internal cost of the programme and margin data are never present. |
| Primary action | "Perpanjang keanggotaan" where the tenant offers renewal in-app. |
| Secondary action | View deposit movements; read membership terms. |
| Empty state | "Belum menjadi anggota" with a factual description of what membership provides at this tenant, and no invented benefit. |
| Loading state | Tier and deposit load together so a partially-rendered entitlement list can never mislead. |
| Error state | A load failure states that standing could not be confirmed and explicitly warns against assuming an entitlement is active. |
| Offline behaviour | Cached standing readable and stamped; renewal and any deposit movement require connectivity. |
| Permission behaviour | Own membership only; entitlement enforcement lives on the server and is never derived from what this screen renders. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Expiry proximity is conveyed by text and icon, not colour alone. |
| Responsive behaviour | Compact: stacked cards. Medium and above: standing card beside the movement list. |
| Privacy and security notes | CONFIDENTIAL. Deposit is a financial record — integer Rupiah, never hard-deleted, corrections by reversal only. |
| Analytics intent | Renewal funnel counts. No balances, no tier names tied to an individual. |
| Future implementation step | Step 11 — Customer Android Experience. |

### SCR-CUS-015 — Feedback

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Collect a rating and optional comment on a completed order so the outlet can act on it. |
| Requirement IDs | FR-025, FR-026, FR-084, NFR-016 |
| Entry points | SCR-CUS-006 once the order is COMPLETED; a post-completion notification. |
| Exit points | Acknowledgement then back to SCR-CUS-006; skip returns without recording anything. |
| Data displayed | Order number, outlet, completion date, rating control, optional comment field with a stated character limit. |
| Data masked | The customer is never shown other customers' feedback; staff are never named in the prompt. |
| Primary action | "Kirim penilaian" — submit the feedback. |
| Secondary action | Skip; edit an already-submitted rating within the tenant-configured window. |
| Empty state | An order with feedback already given shows the submitted rating with its timestamp instead of an empty form. |
| Loading state | Submit shows inline progress; the form locks so a double tap cannot create two entries. |
| Error state | A submission failure preserves the typed comment and offers retry; nothing is discarded on the user's behalf. |
| Offline behaviour | Feedback queues with a reused `client_reference` and is labelled "Menunggu terkirim"; it is never shown as received. |
| Permission behaviour | Only the ordering customer may submit; the server rejects feedback on an order the caller does not own. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The rating control is keyboard and switch operable, with each value labelled in words. |
| Responsive behaviour | Compact: full-screen sheet. Medium and above: modal dialog capped at 560px. |
| Privacy and security notes | Comment text is CONFIDENTIAL free text and is never surfaced on the public tracking portal. |
| Analytics intent | Response rate and rating distribution in aggregate. Never comment text, never a customer identifier. |
| Future implementation step | Step 11 — Customer Android Experience. |

### SCR-CUS-016 — Notifications

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Give the customer an in-app record of every message the tenant sent them, and control over what they receive. |
| Requirement IDs | FR-093, FR-096, NOT-003, NOT-009, NOT-016 |
| Entry points | SCR-CUS-004; a push notification tap; SCR-CUS-017 Profile. |
| Exit points | Deep link into the referenced order; into notification preferences on the same screen. |
| Data displayed | Message title, body summary, category (transaksional or pemasaran), outlet-local send time, related order number, delivery state. |
| Data masked | No tracking token, no OTP, and no full address ever appears in a stored notification body. |
| Primary action | Open the related order. |
| Secondary action | Mark all read; open preferences; opt out of marketing. |
| Empty state | "Belum ada notifikasi" with a note that transactional updates will appear here automatically. |
| Loading state | Paged skeleton rows retaining the two-line body height. |
| Error state | A load failure keeps cached notifications visible with a retry band. |
| Offline behaviour | Fully readable from cache; preference changes queue and are labelled as pending until the server acknowledges. |
| Permission behaviour | Own notifications only; marketing opt-out is enforced at send time on the server, never by hiding a row here. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Unread state is conveyed by text and icon in addition to weight. |
| Responsive behaviour | Compact: single list. Medium and above: list beside a reading pane. |
| Privacy and security notes | CONFIDENTIAL. Quiet hours default to 20.00–08.00 outlet local time and are honoured by the sender, not by this screen. |
| Analytics intent | Open rate by category and opt-out rate. Never message bodies, never recipient identity. |
| Future implementation step | Step 7 — Customer Tracking and WhatsApp. |

### SCR-CUS-017 — Profile

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Hold the customer's own identity details, linked tenants, session control, and sign-out. |
| Requirement IDs | FR-003, FR-021, SEC-020, SEC-021, TEN-005, TEN-012 |
| Entry points | Bottom navigation; SCR-CUS-004 avatar. |
| Exit points | SCR-CUS-010 Address Book; SCR-CUS-013; SCR-CUS-014; SCR-CUS-016; sign-out to SCR-CUS-001. |
| Data displayed | Display name, phone as `0812-XXXX-1234`, linked tenants such as "Laundry Bersih Sejahtera", active device sessions with last-used time, app version. |
| Data masked | The phone is masked by default and revealed only on an explicit reveal action by the account owner. |
| Primary action | "Simpan perubahan" when a profile field has been edited. |
| Secondary action | Revoke a device session; switch tenant; open preferences; sign out. |
| Empty state | A customer linked to exactly one tenant sees no tenant switcher rather than a switcher with one entry. |
| Loading state | Session list loads independently of profile fields so an edit is never blocked by a slow list. |
| Error state | A save failure keeps the edited values and names the failing field; a session-revoke failure states that the session may still be active. |
| Offline behaviour | Profile readable from cache; edits queue as pending, and session revocation is refused offline because it must take effect server-side. |
| Permission behaviour | Own account only; a tenant switch re-derives authorization from membership on the server and never from a client-supplied tenant ID. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Sign-out and revoke are separated from routine edits and both confirm. |
| Responsive behaviour | Compact: single scrolling column. Medium and above: two-column settings layout. |
| Privacy and security notes | Tokens live only in platform keystore-backed storage; switching tenant clears the previous tenant's cached data on device. |
| Analytics intent | Session revocation counts and tenant-switch frequency. No phone numbers, no tenant identifiers tied to a person. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

### SCR-CUS-018 — Error and Recovery

| Field | Value |
|---|---|
| Platform | Customer Android (Flutter) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact, P-14 Authorized Order Recipient |
| Purpose | Provide one honest destination for unrecoverable failures that names what happened and what the customer should do next. |
| Requirement IDs | NFR-017, NFR-018, SEC-034, FR-018 |
| Entry points | An unhandled failure on any Customer Android screen; a forced upgrade requirement; a server maintenance response. |
| Exit points | Retry returning to the originating screen; back to SCR-CUS-004; out to contact the outlet. |
| Data displayed | A plain-language description, a short non-sensitive correlation reference for support, and the concrete recovery step. |
| Data masked | No stack trace, no internal endpoint, no token, no server hostname, and no personal data is ever rendered. |
| Primary action | "Coba lagi" — retry the failed operation. |
| Secondary action | Return home; contact the outlet; check connection settings. |
| Empty state | Not applicable; this screen only exists in response to a failure. |
| Loading state | Retry shows inline progress and is disabled while in flight so retries cannot stack. |
| Error state | A failed retry increments a visible attempt count and, after a bounded number, recommends contacting the outlet instead of looping. |
| Offline behaviour | Distinguishes "no connectivity" from "server error" explicitly, because the recovery step differs. |
| Permission behaviour | A permission failure is reported as not-found without disclosing that a record exists elsewhere. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The message is announced on arrival and focus lands on the recovery action. |
| Responsive behaviour | Compact: full-screen. Medium and above: centred card capped at 480px. |
| Privacy and security notes | The correlation reference is opaque and carries no customer, tenant, or order identity. Personal data in crash reporting is redacted at source. |
| Analytics intent | Error category counts and retry success rate. Never a message body, never a correlation reference tied to a person. |
| Future implementation step | Step 11 — Customer Android Experience. |

---

# Ops Android (Flutter)

### SCR-OPS-001 — Startup

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-05 Outlet Manager, P-06 Cashier, P-07 Production Operator, P-08 Quality Control, P-09 Courier Internal |
| Purpose | Resolve device registration, stored session, and pending offline queue before any operational screen is shown. |
| Requirement IDs | FR-004, FR-005, OFF-001, OFF-019, SEC-020 |
| Entry points | Cold app launch; launch after device restart; launch after a crash. |
| Exit points | SCR-OPS-002 Login when no session; SCR-OPS-003 when unregistered; SCR-OPS-006 Home when a valid session exists. |
| Data displayed | Product name, outlet binding if known, a queue-restoration line such as "Memulihkan 3 operasi tertunda", app version. |
| Data masked | No account name and no tenant name is shown before the session is validated. |
| Primary action | None — the screen resolves automatically and routes. |
| Secondary action | Retry startup after a failure. |
| Empty state | A clean device with nothing queued routes straight through without lingering. |
| Loading state | A determinate progress line for queue restoration, because restoration length is knowable. |
| Error state | Corrupt local state is reported explicitly with a path to support; the queue is never silently discarded to make startup succeed. |
| Offline behaviour | Startup fully completes offline against cached session material; the queue is restored intact from persistent storage. |
| Permission behaviour | No tenant data is read until the server validates the session; a cached role is never treated as authorization. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Progress is announced rather than conveyed only by animation. |
| Responsive behaviour | Compact: full-screen. Medium and above: centred content; the screen never depends on width. |
| Privacy and security notes | Local operational data is encrypted at rest; the queue is per tenant and per user and never shared across either. |
| Analytics intent | Startup duration and queue-restoration size buckets. No user identity, no queue contents. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

### SCR-OPS-002 — Login

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-05 Outlet Manager, P-06 Cashier, P-07 Production Operator, P-08 Quality Control, P-09 Courier Internal |
| Purpose | Authenticate a staff member against the tenant's identity records. |
| Requirement IDs | FR-004, FR-006, SEC-002, SEC-011, SEC-016 |
| Entry points | SCR-OPS-001; SCR-OPS-036 Session Expired; explicit logout via SCR-OPS-035. |
| Exit points | SCR-OPS-004 Tenant Selection or SCR-OPS-006 Home; SCR-OPS-003 when the device is not activated. |
| Data displayed | Identifier field, credential field, outlet binding hint, failed-attempt guidance, lockout countdown when applied. |
| Data masked | The credential is obscured by default; the identifier is never persisted into a shared preference in plain form. |
| Primary action | "Masuk" — submit credentials. |
| Secondary action | Reveal credential; contact tenant admin for a reset. |
| Empty state | Submit stays disabled until both fields carry content. |
| Loading state | Button busy state with fields locked so a double submit cannot consume two attempts. |
| Error state | Wrong credential, locked account, revoked membership, and unreachable server are four distinct messages with distinct next steps. |
| Offline behaviour | Login requires connectivity and refuses clearly; no credential check ever happens against a local copy. |
| Permission behaviour | Role and outlet scope arrive from the server after authentication; the client renders nothing role-specific beforehand. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Errors are announced and associated with the failing field. |
| Responsive behaviour | Compact: full-width form. Medium and above: centred card at 440px, usable in landscape on a counter stand. |
| Privacy and security notes | Credentials never enter logs at any level; brute-force protection and progressive backoff are server-side. |
| Analytics intent | Login success and failure category counts. Never an identifier, never a credential. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

### SCR-OPS-003 — Device Activation

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-04 Tenant Admin, P-05 Outlet Manager |
| Purpose | Bind this physical device to one tenant and outlet so that operational data is scoped before any order is taken. |
| Requirement IDs | FR-007, FR-013, TEN-006, SEC-021, SEC-022 |
| Entry points | SCR-OPS-001 on an unregistered device; SCR-OPS-037 after a revocation; a manager-initiated re-binding. |
| Exit points | SCR-OPS-002 Login once activated; back out abandons activation. |
| Data displayed | Activation code field, resolved tenant name "Laundry Bersih Sejahtera", resolved outlet "Outlet Cempaka", device label field, activation expiry countdown. |
| Data masked | The activation code is write-only and is never re-displayed or copied to the clipboard automatically. |
| Primary action | "Aktifkan perangkat" — bind the device. |
| Secondary action | Rename the device label; cancel activation. |
| Empty state | Not applicable — the screen exists only when activation is required. |
| Loading state | Code validation shows inline progress; the tenant and outlet names appear only once the server resolves them. |
| Error state | An expired, already-used, or wrong-tenant code produces distinct copy; none of them reveals which tenant the code belonged to. |
| Offline behaviour | Activation requires connectivity and refuses clearly; a device is never activated against a locally cached code. |
| Permission behaviour | Only a role entitled to activate devices may consume a code; the server enforces this and the client never assumes success. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The code field uses grouped input with a clear character count announced. |
| Responsive behaviour | Compact: single column. Medium and above: centred card; the countdown remains visible without scrolling. |
| Privacy and security notes | The activation code is SECRET, single-use, expiring, and stored hashed server-side. Device binding is auditable. |
| Analytics intent | Activation success and failure category. Never a code, never a device identifier in clear form. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

### SCR-OPS-004 — Tenant Selection

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-03 Tenant Owner, P-04 Tenant Admin, P-05 Outlet Manager |
| Purpose | Let a staff member who holds memberships in more than one tenant choose the tenant this session operates in. |
| Requirement IDs | FR-011, FR-012, TEN-001, TEN-005, TEN-009 |
| Entry points | After login when more than one membership exists; an explicit tenant switch from SCR-OPS-034. |
| Exit points | SCR-OPS-005 Outlet Selection; back to SCR-OPS-002. |
| Data displayed | Each tenant the user is a member of, the role held there, and the number of outlets accessible under that role. |
| Data masked | No operational figure, customer count, or revenue from any tenant is previewed on this screen. |
| Primary action | Select a tenant and continue. |
| Secondary action | Refresh the membership list; sign out. |
| Empty state | A membership list that returns empty is reported as "Tidak ada keanggotaan aktif" with a route to contact the tenant admin. |
| Loading state | Skeleton rows; selection is disabled until the server-confirmed list has landed. |
| Error state | A load failure blocks progress rather than falling back to a cached membership list, because stale membership is a security risk. |
| Offline behaviour | Tenant switching requires connectivity; the previously active tenant's cached data remains isolated and untouched. |
| Permission behaviour | Memberships are derived server-side from the authenticated identity; a client-supplied tenant ID is never authorization proof. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Rows are 56dp targets with role stated in text, not only by badge colour. |
| Responsive behaviour | Compact: full-width list. Medium and above: centred list capped at 560px. |
| Privacy and security notes | Selecting a different tenant purges the previous tenant's cached data, caches, and search indexes on the device before proceeding. |
| Analytics intent | Multi-tenant switch frequency in aggregate. No tenant names, no membership identifiers. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

### SCR-OPS-005 — Outlet Selection

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-05 Outlet Manager, P-06 Cashier, P-07 Production Operator, P-08 Quality Control |
| Purpose | Choose which outlet of the selected tenant this device session is operating for. |
| Requirement IDs | FR-014, FR-015, TEN-004, TEN-010, FR-041 |
| Entry points | After tenant selection; an explicit outlet change from SCR-OPS-034; a shift start that requires a different outlet. |
| Exit points | SCR-OPS-006 Home; back to SCR-OPS-004. |
| Data displayed | Outlet name ("Outlet Cempaka", "Outlet Melati"), brand it belongs to, operating hours, whether a shift is currently open. |
| Data masked | Revenue, order counts, and staff lists are not previewed here; the screen is a selector, not a dashboard. |
| Primary action | Select an outlet and continue. |
| Secondary action | Search outlets by name; change tenant. |
| Empty state | A role with no outlet assignment sees "Belum ada outlet yang ditugaskan" and a route to the outlet manager. |
| Loading state | Skeleton rows with the search field already active. |
| Error state | A load failure blocks selection and offers retry; no cached outlet list is used to grant scope. |
| Offline behaviour | If the device is already bound to a single outlet, the screen is skipped entirely offline; changing outlet needs connectivity. |
| Permission behaviour | Only outlets covered by the user's membership are returned; the server rejects any outlet ID outside that set. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Open-shift state is text plus icon, never colour alone. |
| Responsive behaviour | Compact: full-width list. Medium and above: two-column grid of outlet cards. |
| Privacy and security notes | Switching outlet within a tenant re-scopes every subsequent query server-side and clears outlet-specific local caches. |
| Analytics intent | Outlet switch frequency. No outlet names, no user identity. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

### SCR-OPS-006 — Home

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-05 Outlet Manager, P-06 Cashier, P-07 Production Operator, P-08 Quality Control, P-09 Courier Internal |
| Purpose | Put the role's most frequent action one tap away and expose sync health honestly at all times. |
| Requirement IDs | FR-048, FR-071, FR-100, OFF-002, OFF-012, NFR-011 |
| Entry points | After outlet selection; app resume with a valid session; back from any operational flow. |
| Exit points | SCR-OPS-007 POS; SCR-OPS-022 Production Queue; SCR-OPS-026 Quality Control; SCR-OPS-029 and SCR-OPS-030 jobs; SCR-OPS-020 Offline Queue; SCR-OPS-033 Shift. |
| Data displayed | Outlet name, active shift state, counts of orders awaiting each stage, pending sync operation count, last successful sync time (`14:30`). |
| Data masked | A production operator's view excludes order totals and customer balances; a courier's view excludes the customer directory. |
| Primary action | Role-dependent: "Pesanan baru" for a cashier, "Ambil pekerjaan" for a production operator, "Mulai rute" for a courier. |
| Secondary action | Open the offline queue; open shift; open settings. |
| Empty state | An outlet with no work in progress shows a factual "Tidak ada pekerjaan aktif" rather than fabricated activity. |
| Loading state | Counters show skeletons; the primary action is enabled immediately because it does not depend on the counters. |
| Error state | A counter failure marks that tile as unavailable and leaves the rest of the screen operable. |
| Offline behaviour | Fully operable offline; a persistent banner states the offline condition, the pending count, and the last sync time — never an implied "live". |
| Permission behaviour | Tiles are filtered by role, but hiding a tile is presentation only; every underlying call is authorized server-side. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The primary action is at least 56dp and reachable one-handed. |
| Responsive behaviour | Compact: two-column tile grid. Medium: three columns. Expanded and wide: capped grid; the app remains usable on a counter tablet. |
| Privacy and security notes | All counters are tenant- and outlet-scoped; no figure from another outlet or tenant is ever aggregated here. |
| Analytics intent | Tile usage by role and time-to-first-action. No counts tied to a named outlet. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-007 — POS New Order

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-05 Outlet Manager |
| Purpose | Take in a customer's laundry and create an order in the fewest steps the counter allows. |
| Requirement IDs | FR-048, FR-049, FR-050, FR-059, OFF-001, OFF-004 |
| Entry points | SCR-OPS-006 primary action; a barcode scan of an existing customer card; resuming a DRAFT order. |
| Exit points | SCR-OPS-008 Customer Search; SCR-OPS-010 Service Selection; SCR-OPS-014 Order Review; discard returns to Home. |
| Data displayed | Draft order number placeholder, selected customer summary, running line list, running total in Rupiah, sync state chip. |
| Data masked | The customer's phone is shown as `0812-XXXX-1234` on the counter screen; full disclosure requires an explicit reveal. |
| Primary action | "Lanjut ke ringkasan" — proceed to review. |
| Secondary action | Add another service line; change customer; save as DRAFT; discard. |
| Empty state | A new order with no lines shows a single prominent "Pilih layanan" and nothing else, so the next step is unambiguous. |
| Loading state | The catalogue loads once per session and is cached; line addition never blocks on the network. |
| Error state | A catalogue failure falls back to the cached price list and states clearly that prices shown are the last synced version with its timestamp. |
| Offline behaviour | Order creation works fully offline, is assigned a `client_reference` generated once, and is displayed as "Tertunda" until the server acknowledges. |
| Permission behaviour | Only a role entitled to take orders at this outlet may open the screen; the server rejects an unauthorized create regardless of what the UI allowed. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Targets are 56dp for one-handed counter use; the running total is announced on change. |
| Responsive behaviour | Compact: stacked wizard. Medium and above: line list beside a persistent total panel. |
| Privacy and security notes | Draft orders are stored encrypted on device, scoped to tenant and user, and cleared on tenant switch. |
| Analytics intent | Time to complete an intake and abandonment step. No customer identity, no totals. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-008 — Customer Search

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-05 Outlet Manager |
| Purpose | Find an existing customer of this tenant quickly, by name or phone, without exposing the directory wholesale. |
| Requirement IDs | FR-021, FR-027, FR-028, TEN-008, SEC-030 |
| Entry points | SCR-OPS-007; SCR-OPS-016 Payment when a customer must be attached; a card scan. |
| Exit points | Back to SCR-OPS-007 with a selected customer; SCR-OPS-009 Customer Creation when no match exists. |
| Data displayed | Matching customers with name ("Siti Rahmawati"), masked phone `0812-XXXX-1234`, last order date, outstanding balance indicator. |
| Data masked | The phone is masked in results; addresses are not shown at all in the result list. |
| Primary action | Select the matching customer. |
| Secondary action | Create a new customer; clear the search. |
| Empty state | With no query typed the list is deliberately empty and says "Ketik nama atau nomor HP" — the directory is never listed by default. |
| Loading state | Debounced search with an inline spinner in the field; prior results stay visible until replaced. |
| Error state | A search failure states that results may be incomplete and offers retry; it never presents a partial list as complete. |
| Offline behaviour | Searches only the locally cached subset for this outlet, and says so explicitly so the cashier knows a miss may not mean absence. |
| Permission behaviour | Results are tenant-scoped server-side; the same phone number in another tenant is a different, unreachable profile and is never merged. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Result count is announced; each row is a single labelled target. |
| Responsive behaviour | Compact: full-screen search. Medium and above: search panel beside the order draft so context is not lost. |
| Privacy and security notes | Customer identity is CONFIDENTIAL; enumeration is limited by requiring a query and by server-side rate limiting. |
| Analytics intent | Search-to-selection rate and new-customer creation rate. Never query text, never results. |
| Future implementation step | Step 4 — Laundry Master Data. |

### SCR-OPS-009 — Customer Creation

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-05 Outlet Manager |
| Purpose | Register a new customer for this tenant with the minimum data an order actually requires. |
| Requirement IDs | FR-021, FR-022, FR-029, TEN-011, SEC-030, OFF-004 |
| Entry points | SCR-OPS-008 when a search returns no match; SCR-OPS-007 directly for a walk-in. |
| Exit points | Back to SCR-OPS-007 with the new customer attached; cancel discards the draft. |
| Data displayed | Name field, phone field, optional address, optional note, consent toggle for marketing messages, duplicate-candidate warning. |
| Data masked | Once saved, the phone renders as `0812-XXXX-1234` everywhere except inside this form during entry. |
| Primary action | "Simpan pelanggan" — create and attach. |
| Secondary action | Attach an address now; skip optional fields. |
| Empty state | Not applicable; the form always renders its fields. |
| Loading state | Duplicate checking runs inline against the local cache and then the server, without blocking typing. |
| Error state | A duplicate candidate is surfaced for the cashier to choose, never auto-merged; a save failure preserves every typed field. |
| Offline behaviour | Creation succeeds offline with a `client_reference` generated once and reused on retry; the record shows "Tertunda sinkronisasi". |
| Permission behaviour | Creation is authorized server-side for this tenant only; a client-supplied tenant identifier is ignored as proof. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The consent toggle states its meaning in words, not by position. |
| Responsive behaviour | Compact: full-screen form. Medium and above: form sheet beside the order draft. |
| Privacy and security notes | Records are never merged across tenants because a name, phone, or address matches — that is an explicit prohibition, not a heuristic. |
| Analytics intent | Creation count and duplicate-warning acceptance rate. Never names, never phone numbers. |
| Future implementation step | Step 4 — Laundry Master Data. |

### SCR-OPS-010 — Service Selection

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-05 Outlet Manager |
| Purpose | Choose the services and price entries that apply to this intake, from the tenant's own catalogue. |
| Requirement IDs | FR-031, FR-032, FR-035, FR-050, FIN-009 |
| Entry points | SCR-OPS-007; editing an existing line from SCR-OPS-014. |
| Exit points | SCR-OPS-011 Weight Input for weight-based services; SCR-OPS-012 Item Intake for per-piece services; back to SCR-OPS-007. |
| Data displayed | Service categories, service name, pricing basis (per kg or per item), unit price such as `Rp25.000`, express surcharge, estimated completion. |
| Data masked | Tenant cost and margin figures are not present in the payload delivered to this device. |
| Primary action | Select a service and continue to its quantity screen. |
| Secondary action | Search the catalogue; mark the line as express; add a line note. |
| Empty state | An outlet with no active services shows "Belum ada layanan aktif" and routes the manager to the console rather than allowing a priceless line. |
| Loading state | Catalogue renders from cache instantly and refreshes in the background with a visible "diperbarui" stamp. |
| Error state | A refresh failure keeps the cached catalogue and states its last-synced time, so the cashier knows what price basis is in use. |
| Offline behaviour | Fully operable from the cached catalogue; the price snapshot captured at intake is the one that governs the order thereafter. |
| Permission behaviour | Only services enabled for this outlet are selectable, decided server-side at sync time and re-validated on submission. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Prices are announced as full Rupiah amounts; express is text plus icon. |
| Responsive behaviour | Compact: category list then service list. Medium and above: categories beside services in a two-pane layout. |
| Privacy and security notes | Pricing is tenant-scoped; no other tenant's catalogue is reachable, cacheable, or searchable from this device. |
| Analytics intent | Service selection frequency by category in aggregate. No prices tied to a named tenant. |
| Future implementation step | Step 4 — Laundry Master Data. |

### SCR-OPS-011 — Weight Input

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-05 Outlet Manager |
| Purpose | Record the measured weight for a weight-based service line and show the resulting line amount before it is committed. |
| Requirement IDs | FR-050, FR-051, FIN-002, FIN-009, NFR-019 |
| Entry points | SCR-OPS-010 after choosing a per-kilogram service; editing a weight line from SCR-OPS-014. |
| Exit points | Back to SCR-OPS-007 with the line added; back to SCR-OPS-010 to change the service. |
| Data displayed | Weight entry with comma decimal (`1,5 kg`), the tenant's minimum-charge rule, unit price, computed line amount in Rupiah, rounding rule applied. |
| Data masked | Nothing personal appears on this screen; it is purely a measurement and pricing step. |
| Primary action | "Tambahkan ke pesanan" — commit the line. |
| Secondary action | Adjust the weight; add a line note; cancel the line. |
| Empty state | The commit action stays disabled until a weight above zero is entered. |
| Loading state | None expected — pricing is computed locally from the cached snapshot and is instantaneous. |
| Error state | A weight beyond the tenant's plausible bounds triggers a confirmation rather than a silent acceptance. |
| Offline behaviour | Fully offline-capable; the computation uses the captured price snapshot so a later catalogue change cannot alter this line. |
| Permission behaviour | Overriding a minimum charge requires a permitted role; the server re-validates the override on submission. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The computed amount is announced on every change; the numeric keypad is large-target. |
| Responsive behaviour | Compact: keypad below the value. Medium and above: keypad beside a persistent line preview. |
| Privacy and security notes | All money is integer Rupiah; no floating-point arithmetic exists anywhere in this path, including the display layer. |
| Analytics intent | Weight distribution buckets and override frequency. No amounts tied to an order. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-012 — Item Intake (Satuan)

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-05 Outlet Manager |
| Purpose | Record individual garments for a per-piece service, with counts, item types, and any declared condition. |
| Requirement IDs | FR-050, FR-052, FR-053, FR-083, FIN-009 |
| Entry points | SCR-OPS-010 after choosing a per-item service; editing an item line from SCR-OPS-014. |
| Exit points | SCR-OPS-013 Condition Evidence Capture; back to SCR-OPS-007 with the line added. |
| Data displayed | Item type list with per-item price, quantity steppers, per-item notes, running item count, running line amount in Rupiah. |
| Data masked | Nothing personal; the customer is referenced only by the attached order draft. |
| Primary action | "Tambahkan ke pesanan" — commit the item lines. |
| Secondary action | Capture condition evidence; adjust quantity; remove an item type. |
| Empty state | With no item selected, the screen shows the item type list only and the commit action is disabled. |
| Loading state | Item types render from cache; no network wait is acceptable at the counter. |
| Error state | An item type withdrawn from the catalogue since the last sync is flagged and must be replaced before commit. |
| Offline behaviour | Fully offline; item lines and their notes are part of the queued order under the same reused `client_reference`. |
| Permission behaviour | Price overrides on a per-item line require a permitted role and are re-validated server-side on submission. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Steppers expose current value in words and are 48dp minimum. |
| Responsive behaviour | Compact: item list with inline steppers. Medium and above: item catalogue beside the selected-items panel. |
| Privacy and security notes | Item notes may describe a customer's belongings and are therefore CONFIDENTIAL, never shown on the public tracking portal. |
| Analytics intent | Items per order distribution. No item notes, no order identity. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-013 — Condition Evidence Capture

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-07 Production Operator, P-08 Quality Control |
| Purpose | Photograph and annotate pre-existing damage or staining at intake so a dispute later has evidence behind it. |
| Requirement IDs | FR-083, FR-105, SEC-013, SEC-014, FR-054 |
| Entry points | SCR-OPS-012; SCR-OPS-014 before confirming an order; SCR-OPS-026 during quality control. |
| Exit points | Back to the calling screen with evidence attached; discard removes the unsaved capture. |
| Data displayed | Camera preview, captured thumbnails, per-photo condition note, item association, capture time (`14:30`). |
| Data masked | Photographs are never rendered on any customer-facing public surface and never leave private storage unsigned. |
| Primary action | "Ambil foto" then "Lampirkan" — attach the evidence to the item. |
| Secondary action | Retake; add a note; delete an unsaved capture. |
| Empty state | "Belum ada bukti kondisi" with a one-line explanation of when evidence is worth capturing. |
| Loading state | Upload progress is per photo and non-blocking; the cashier may continue the intake while uploads run. |
| Error state | An upload failure keeps the photo queued locally and marks it "Menunggu unggah"; it is never reported as stored. |
| Offline behaviour | Photos are captured and encrypted locally, queued with the order's `client_reference`, and uploaded on reconnect. |
| Permission behaviour | Capture requires an operational role at this outlet; viewing an existing photo requires a signed, expiring URL issued server-side. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Shutter is a large target; each thumbnail carries a descriptive label. |
| Responsive behaviour | Compact: full-screen camera. Medium and above: camera pane beside the thumbnail strip. |
| Privacy and security notes | Laundry photographs are RESTRICTED — private object storage, tenant-scoped unguessable keys, signed expiring URLs only, never indexed. |
| Analytics intent | Capture count per order in aggregate. Never an image, never a note, never a storage key. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-014 — Order Review

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-05 Outlet Manager |
| Purpose | Present the complete order for a final check before it is committed and money is discussed. |
| Requirement IDs | FR-048, FR-055, FR-056, FIN-009, FIN-013, OFF-004 |
| Entry points | SCR-OPS-007 primary action; back from SCR-OPS-015 or SCR-OPS-016. |
| Exit points | SCR-OPS-015 Discount; SCR-OPS-016 Payment; back to edit any line. |
| Data displayed | Customer summary with masked phone, every line with quantity or `1,5 kg`, unit price, line amount, subtotal, discount, total `Rp79.000`, estimated ready time, attached evidence count. |
| Data masked | The phone stays masked; the address is shown only when a pickup or delivery is part of the order. |
| Primary action | "Konfirmasi pesanan" — commit the order as RECEIVED. |
| Secondary action | Edit a line; apply a discount; save as DRAFT; discard with confirmation. |
| Empty state | An order with no lines cannot reach this screen; the confirm action is unreachable without at least one line. |
| Loading state | Confirmation shows a determinate busy state and locks the whole form so no second submit is possible. |
| Error state | A rejected confirmation names the reason — withdrawn service, invalid discount, missing customer — and returns to the exact line at fault. |
| Offline behaviour | Confirmation succeeds offline into the queue under the order's original `client_reference`; the order is shown as "Tertunda", never as synced. |
| Permission behaviour | Confirmation authority is checked server-side on submission; an offline confirmation that the server later rejects surfaces as a conflict, not a silent drop. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The total is the highest-contrast element and is announced before the confirm action. |
| Responsive behaviour | Compact: scrolling review with a sticky total bar. Medium and above: line list beside a fixed summary panel. |
| Privacy and security notes | The price snapshot is captured at confirmation and is immutable thereafter; a later catalogue edit never alters this order. |
| Analytics intent | Review-to-confirm conversion and edit-back rate. No totals, no customer identity. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-015 — Discount

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-05 Outlet Manager, P-06 Cashier |
| Purpose | Apply a permitted discount or promotion to an order, with a recorded reason and an accountable actor. |
| Requirement IDs | FR-036, FR-057, FIN-006, FIN-021, SEC-035 |
| Entry points | SCR-OPS-014; SCR-OPS-016 before payment is taken. |
| Exit points | Back to SCR-OPS-014 with the discount applied or removed. |
| Data displayed | Available discount definitions, discount basis, computed reduction in Rupiah, resulting total, reason field, the approving actor. |
| Data masked | Other customers' discount history and tenant margin impact are not shown. |
| Primary action | "Terapkan diskon" — apply and return. |
| Secondary action | Remove an applied discount; request manager approval. |
| Empty state | With no discount defined for this outlet, the screen states so plainly and offers no free-form amount to a role without override rights. |
| Loading state | Approval requests show a pending state naming who was asked; the order remains editable meanwhile. |
| Error state | An expired or ineligible discount is rejected with the reason named, and the order total reverts visibly rather than quietly. |
| Offline behaviour | Only pre-synced discount definitions are applicable offline; a discount requiring live approval is refused offline with that reason stated. |
| Permission behaviour | Override beyond the role's ceiling requires an explicit approval that is recorded; a hidden control is never treated as the enforcement. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The before-and-after total is announced as one comparative statement. |
| Responsive behaviour | Compact: full-screen sheet. Medium and above: dialog beside the persistent order summary. |
| Privacy and security notes | Every applied discount records actor, timestamp, amount, and reason as an audit entry; integer Rupiah throughout. |
| Analytics intent | Discount application rate and override frequency by role. No amounts, no actor identity. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-016 — Payment

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-05 Outlet Manager |
| Purpose | Record payment against an order without ever creating a duplicate and without ever showing money as settled before the server says so. |
| Requirement IDs | FR-061, FR-062, FR-064, FIN-003, FIN-005, OFF-005 |
| Entry points | SCR-OPS-014 after confirmation; SCR-OPS-028 at collection; SCR-OPS-032 for courier-collected cash. |
| Exit points | SCR-OPS-018 Receipt; SCR-OPS-017 Partial Payment; back to the order. |
| Data displayed | Amount due `Rp79.000`, method selection, tendered amount, change due, resulting balance, the `client_reference` state chip. |
| Data masked | No gateway credential, no full card or account identifier, and no provider secret is ever rendered. |
| Primary action | "Catat pembayaran" — record the payment. |
| Secondary action | Take a partial payment; change method; cancel before recording. |
| Empty state | A fully settled order shows "Lunas" with the settling time and offers only receipt reprint. |
| Loading state | A determinate busy state with the form locked; the button cannot be pressed twice, and a retry reuses the same `client_reference`. |
| Error state | An ambiguous outcome is reported as ambiguous — "Status pembayaran belum dipastikan" — with an explicit instruction to check before re-taking money. |
| Offline behaviour | Payment queues offline and is displayed as "Tertunda — belum dikonfirmasi server"; it is never rendered as final, and the same `client_reference` is reused on every retry. |
| Permission behaviour | Recording, voiding, and refunding are separate permissions enforced server-side; an order is never marked paid on a client claim. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Amount due and change due are announced distinctly; the numeric keypad uses large targets. |
| Responsive behaviour | Compact: keypad below the amount summary. Medium and above: keypad beside a persistent balance panel. |
| Privacy and security notes | Integer Rupiah only; idempotency is a server contract keyed on `client_reference`; a duplicate payment from a retry is unacceptable. |
| Analytics intent | Payment method mix and retry frequency. Never amounts, never references, never customer identity. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-017 — Partial Payment

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-05 Outlet Manager |
| Purpose | Record a deposit or part-payment and make the remaining balance unmistakable to everyone downstream. |
| Requirement IDs | FR-062, FR-063, FR-066, FIN-011, FIN-018, UCL-012 |
| Entry points | SCR-OPS-016 when the tendered amount is below the total; a deposit taken at intake. |
| Exit points | SCR-OPS-018 Receipt; back to the order with the balance shown. |
| Data displayed | Total, previously paid, this payment, remaining balance `Rp25.000`, agreed settlement expectation, method. |
| Data masked | No other order's balance for the same customer is shown unless the role is entitled to the receivables view. |
| Primary action | "Catat pembayaran sebagian" — record the part-payment. |
| Secondary action | Change the amount; record a settlement note; cancel. |
| Empty state | Not applicable; the screen only opens when a balance genuinely remains. |
| Loading state | Locked form with determinate progress; the remaining balance renders only from a server-confirmed figure once online. |
| Error state | A failure states plainly whether the part-payment was recorded, and never leaves the cashier guessing whether to re-take cash. |
| Offline behaviour | Queues under a single reused `client_reference`; the balance is labelled provisional until the server confirms. |
| Permission behaviour | Part-payment is a distinct permitted operation; the resulting balance is authoritative on the server, not on the device. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The remaining balance is the loudest element and is announced last, after the amount recorded. |
| Responsive behaviour | Compact: stacked. Medium and above: entry pane beside a balance breakdown. |
| Privacy and security notes | Financial records are append-only in effect; a correction is a reversal or adjustment entry, never a deletion. |
| Analytics intent | Partial payment frequency and average settlement lag in buckets. No amounts, no identities. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-018 — Receipt

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-05 Outlet Manager |
| Purpose | Produce the customer's proof of intake and payment, in print or as a shareable message, without blocking on hardware. |
| Requirement IDs | FR-058, FR-062, FR-067, TRK-003, NOT-014 |
| Entry points | SCR-OPS-016; SCR-OPS-017; a reprint from an order detail. |
| Exit points | Back to SCR-OPS-006 Home; SCR-OPS-019 Printer State on a hardware failure. |
| Data displayed | Order number `AL-2026-000123`, outlet, intake time `14:30`, line summary, total, paid, balance, estimated ready time, tracking link. |
| Data masked | The customer's phone prints masked as `0812-XXXX-1234`; the tracking token itself is embedded in the link and never printed as a readable value. |
| Primary action | "Cetak struk" — send to the connected printer. |
| Secondary action | Share via WhatsApp deep link; reprint; skip printing. |
| Empty state | Not applicable; a receipt always has an order behind it. |
| Loading state | Print job shows a determinate state; the order is already committed and is not waiting on this. |
| Error state | A printer failure is reported as a printer failure only — the order and the payment remain valid and are never rolled back. |
| Offline behaviour | Printing works offline against the cached order; the WhatsApp share falls back to a manual deep link that a staff member taps. |
| Permission behaviour | Reprint may be permission-gated by tenant policy and is recorded as an audit event when it is. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The on-screen receipt is selectable text, not an image, so it can be read by assistive technology. |
| Responsive behaviour | Compact: full-screen receipt preview. Medium and above: preview beside the action column. |
| Privacy and security notes | The printed receipt carries no full address and no internal note; the tracking link is high-entropy, expiring, and revocable. |
| Analytics intent | Print success rate and share-channel mix. Never receipt contents, never tracking tokens. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-019 — Printer State

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-05 Outlet Manager |
| Purpose | Diagnose and recover a receipt printer without ever putting the order or the payment at risk. |
| Requirement IDs | FR-058, NFR-020, NFR-021, FR-018 |
| Entry points | SCR-OPS-018 on a print failure; SCR-OPS-034 Settings; a manual connectivity check. |
| Exit points | Back to SCR-OPS-018 to retry; back to Home leaving the receipt unprinted. |
| Data displayed | Printer name, connection type, connection state, paper state, last successful print time, pending print jobs. |
| Data masked | No receipt content is echoed into diagnostic output. |
| Primary action | "Hubungkan ulang" — re-establish the printer connection. |
| Secondary action | Select a different printer; run a test print; skip printing entirely. |
| Empty state | With no printer configured, the screen offers pairing and states plainly that receipts can be shared digitally instead. |
| Loading state | Connection attempt shows a determinate state with a bounded timeout, not an indefinite spinner. |
| Error state | Each failure class — not paired, out of paper, out of range, driver rejected — has its own message and its own recovery step. |
| Offline behaviour | Entirely local; printer operation does not depend on server connectivity in any way. |
| Permission behaviour | Printer configuration may be restricted to a manager role by tenant policy; enforcement is server-side where the setting is stored. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Connection state is text plus icon; status changes are announced. |
| Responsive behaviour | Compact: single column. Medium and above: device list beside the diagnostic panel. |
| Privacy and security notes | Printer discovery is scoped to the outlet's own hardware; no order data is broadcast during discovery. |
| Analytics intent | Print failure category counts. No printer identifiers, no receipt data. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-020 — Offline Queue

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-05 Outlet Manager, P-06 Cashier, P-09 Courier Internal |
| Purpose | Make every pending operation visible, ordered, and actionable, so nothing is lost and nothing is silently duplicated. |
| Requirement IDs | OFF-002, OFF-003, OFF-012, OFF-019, OFF-021, FIN-003 |
| Entry points | SCR-OPS-006 sync banner; SCR-OPS-001 after queue restoration; a failed-operation notification. |
| Exit points | SCR-OPS-021 Sync Conflict; the originating order or job; back to Home. |
| Data displayed | Each queued operation with its type, target order number, creation time, retry count, next retry time, state (menunggu, mencoba, gagal), and its `client_reference` state. |
| Data masked | The `client_reference` value is shown truncated and is never copied into analytics or logs. |
| Primary action | "Sinkronkan sekarang" — attempt the queue immediately. |
| Secondary action | Inspect an operation; open its order; escalate a stuck operation to the manager. |
| Empty state | "Semua operasi tersinkronisasi" with the last successful sync time stated, rather than an ambiguous blank. |
| Loading state | Per-row progress with the overall count; ordering is preserved so a dependent operation never jumps ahead. |
| Error state | A permanently failing operation is kept visible with its failure reason and is never auto-dropped to make the queue look clean. |
| Offline behaviour | This is the offline screen: fully functional with no connectivity, and the manual sync action states that connectivity is required. |
| Permission behaviour | Removing a queued financial operation requires an explicit, permissioned, audited action — it is never available as a convenience button. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Each state is text plus icon; retry countdowns are announced, not only animated. |
| Responsive behaviour | Compact: queue list. Medium and above: queue list beside an operation detail pane. |
| Privacy and security notes | The queue is scoped per tenant and per user, encrypted at rest, and survives app kill, crash, and device restart. |
| Analytics intent | Queue depth, retry counts, and failure categories. Never payloads, never references, never order numbers. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-021 — Sync Conflict

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-05 Outlet Manager, P-06 Cashier |
| Purpose | Surface a disagreement between device state and server state for a human to resolve, especially where money is involved. |
| Requirement IDs | OFF-006, OFF-007, OFF-014, FIN-003, FIN-012, SEC-036 |
| Entry points | SCR-OPS-020 on a conflicting operation; a background sync that detects divergence. |
| Exit points | Back to SCR-OPS-020 once resolved; into the affected order to verify the outcome. |
| Data displayed | Side-by-side device value and server value, the field in dispute, both timestamps in outlet local time, the operation type, and the audit note field. |
| Data masked | No other order's data is pulled in for comparison; the conflict view is strictly scoped to the disputed record. |
| Primary action | "Terima versi server" — reconcile to the authoritative server state. |
| Secondary action | Escalate to the outlet manager; open the affected order; record a resolution note. |
| Empty state | Not applicable; the screen exists only when a conflict is live. |
| Loading state | Re-fetch of the server value shows a determinate state; neither side is rendered as chosen until the user chooses. |
| Error state | A failed resolution leaves the conflict open and visible; it is never closed optimistically. |
| Offline behaviour | Conflicts are readable offline but cannot be resolved offline, because the server is the source of truth and must be consulted. |
| Permission behaviour | Financial conflicts require a manager-level permission to resolve, enforced server-side, and always record actor and reason. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The two versions are labelled in text; difference is never conveyed by colour alone. |
| Responsive behaviour | Compact: stacked comparison. Medium and above: true side-by-side columns. |
| Privacy and security notes | A payment conflict is never silently overwritten; the server is final and the divergence is made visible with an audit entry. |
| Analytics intent | Conflict frequency by operation type and resolution latency. Never values, never order identity. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-022 — Production Queue

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-07 Production Operator, P-05 Outlet Manager |
| Purpose | Show the operator what to work on next, in priority order, without exposing commercial data they do not need. |
| Requirement IDs | FR-071, FR-072, FR-073, FR-077, NFR-022 |
| Entry points | SCR-OPS-006 primary action for the operator role; a label scan; back from SCR-OPS-023. |
| Exit points | SCR-OPS-023 Production Detail; SCR-OPS-024 Label Scan; back to Home. |
| Data displayed | Order number, current status from the canonical set, service type, item or weight quantity, target completion time, express flag, age in the current stage. |
| Data masked | Order totals, customer balance, discounts, and customer phone are deliberately absent from the operator payload. |
| Primary action | Open the top-priority job. |
| Secondary action | Filter by stage; scan a label; refresh. |
| Empty state | "Tidak ada pekerjaan di antrean" with the next expected intake time, rather than an unexplained blank board. |
| Loading state | Skeleton rows that preserve stage grouping so the operator's spatial memory holds. |
| Error state | A refresh failure keeps the cached queue and stamps it with its last sync time, because working from a known-stale list beats working from nothing. |
| Offline behaviour | The queue is fully readable offline and stage transitions are queued with reused `client_reference` values. |
| Permission behaviour | Only stages the operator is assigned to are actionable; the server rejects a transition the role may not perform regardless of the UI. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Express is text plus icon; stage grouping has semantic headers. |
| Responsive behaviour | Compact: grouped list. Medium: two columns per stage. Expanded and wide: a lane board that still avoids horizontal page scrolling. |
| Privacy and security notes | The operator payload is minimised by design — an operator does not need financial data to wash clothes. |
| Analytics intent | Queue depth and stage dwell time in aggregate. No order numbers, no operator identity. |
| Future implementation step | Step 6 — Production Operations. |

### SCR-OPS-023 — Production Detail

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-07 Production Operator, P-05 Outlet Manager |
| Purpose | Give the operator the instructions for one order and the enumerated transitions they are allowed to perform on it. |
| Requirement IDs | FR-073, FR-074, FR-075, FR-078, FR-080 |
| Entry points | SCR-OPS-022; SCR-OPS-024 after a successful scan. |
| Exit points | Back to SCR-OPS-022 after a transition; SCR-OPS-026 when the order reaches QUALITY_CONTROL. |
| Data displayed | Order number, items with handling notes, service instructions, intake condition evidence, current status, permitted next statuses only. |
| Data masked | No customer name beyond an initial where the tenant requires one for identification; no phone, no address, no money. |
| Primary action | Advance to the next enumerated status — for example SORTING to WASHING. |
| Secondary action | Flag ISSUE with a reason; view condition evidence; add a production note. |
| Empty state | Not applicable; a production job always has content. |
| Loading state | Evidence thumbnails load lazily; the transition controls are available immediately. |
| Error state | A rejected transition states which precondition failed and changes nothing — the failure is atomic and the screen reflects the unchanged status. |
| Offline behaviour | Transitions are queued with reused `client_reference` values and replay idempotently; a replayed transition already applied is a no-op. |
| Permission behaviour | Only enumerated transitions permitted for this role appear, and the server independently re-validates every one; there is no generic set-status control. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Status change is confirmed in text; ISSUE is spatially separated from the advance action. |
| Responsive behaviour | Compact: instructions above actions. Medium and above: instruction pane beside a persistent action column. |
| Privacy and security notes | Condition photographs are RESTRICTED and are fetched through signed expiring URLs, never cached to public device storage. |
| Analytics intent | Transition counts and ISSUE reason categories. No order identity, no note text. |
| Future implementation step | Step 6 — Production Operations. |

### SCR-OPS-024 — Label Scan

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-07 Production Operator, P-08 Quality Control, P-09 Courier Internal |
| Purpose | Resolve a physical label to the correct order in one motion, so hands stay on the laundry rather than on a search field. |
| Requirement IDs | FR-076, FR-077, FR-081, NFR-023, SEC-037 |
| Entry points | SCR-OPS-022; SCR-OPS-026; SCR-OPS-030; the Home quick action. |
| Exit points | SCR-OPS-023, SCR-OPS-026, or the relevant job screen on a successful resolve; stays on failure. |
| Data displayed | Camera viewfinder, scan guidance, last resolved order number, a short recent-scan history for this session. |
| Data masked | The scan history holds order numbers only, never customer identity. |
| Primary action | Scan — resolution is automatic on a valid read. |
| Secondary action | Enter the order number manually; toggle the torch; open the last scanned order. |
| Empty state | The viewfinder with guidance text is the resting state; there is no empty list to show. |
| Loading state | A brief resolve indicator over the viewfinder; scanning is paused during resolution to prevent a double read. |
| Error state | Unreadable code, unknown order, and wrong-outlet order are three distinct messages; the wrong-outlet case never names the other outlet's customer. |
| Offline behaviour | Resolves against the locally cached order set for this outlet and says plainly when a code cannot be resolved without connectivity. |
| Permission behaviour | Resolution is scoped to the active tenant and outlet; a label from another tenant resolves to not-found with no information disclosed. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Manual entry is always available as an equal path, never a hidden fallback. |
| Responsive behaviour | Compact: full-screen viewfinder. Medium and above: viewfinder beside the recent-scan list. |
| Privacy and security notes | Order numbers are not tracking tokens and are never usable as one; a scan grants no access beyond the active tenant scope. |
| Analytics intent | Scan success rate and manual-entry fallback rate. No order numbers. |
| Future implementation step | Step 6 — Production Operations. |

### SCR-OPS-025 — Assignment

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-05 Outlet Manager, P-07 Production Operator |
| Purpose | Assign production work or a courier job to a named person so that accountability for each stage is explicit. |
| Requirement IDs | FR-078, FR-079, FR-108, FR-109, SEC-035 |
| Entry points | SCR-OPS-022; SCR-OPS-023; a courier planning action from Home. |
| Exit points | Back to the calling queue with the assignment recorded; SCR-OPS-020 if the assignment queued offline. |
| Data displayed | Assignable staff for this outlet with role and current workload count, the job being assigned, and the current assignee if any. |
| Data masked | Staff personal contact details are not shown; only operational name and role appear. |
| Primary action | "Tugaskan" — assign the job to the selected person. |
| Secondary action | Reassign; unassign with a reason; view current workload. |
| Empty state | With no eligible staff on shift, the screen says so and routes to the shift screen rather than allowing an assignment to nobody. |
| Loading state | Workload counts load after the staff list so the list is usable immediately. |
| Error state | An assignment rejected because the assignee went off shift names that reason and returns the job to unassigned visibly. |
| Offline behaviour | Assignment queues with a reused `client_reference` and is labelled pending; concurrent assignment is resolved server-side, not on the device. |
| Permission behaviour | Only a role permitted to assign may act, and assignment is serialised server-side so two managers cannot both claim the same job. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Rows are 56dp; current assignee is stated in text, not implied by highlight. |
| Responsive behaviour | Compact: staff list sheet. Medium and above: staff list beside the job summary. |
| Privacy and security notes | Staff records are CONFIDENTIAL and tenant-scoped; workload data never crosses an outlet boundary without entitlement. |
| Analytics intent | Assignment and reassignment counts. No staff identity, no job identity. |
| Future implementation step | Step 6 — Production Operations. |

### SCR-OPS-026 — Quality Control

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-08 Quality Control, P-05 Outlet Manager |
| Purpose | Record a pass, a rework requirement, or an authorized waiver against an order, with evidence and a reason. |
| Requirement IDs | FR-081, FR-082, FR-084, FR-085, SEC-035 |
| Entry points | SCR-OPS-022 when an order reaches QUALITY_CONTROL; SCR-OPS-024 label scan; SCR-OPS-023. |
| Exit points | SCR-OPS-028 Ready for Pickup on PASSED; SCR-OPS-027 Rework on FAILED_REWORK_REQUIRED; back to the queue. |
| Data displayed | Order number, items, intake condition evidence, production notes, the four canonical QC statuses (PENDING, PASSED, FAILED_REWORK_REQUIRED, WAIVED_WITH_AUTHORIZATION), defect category list. |
| Data masked | Customer contact and financial data are absent; the QC role does not need them and therefore does not receive them. |
| Primary action | "Tandai lolos" — record PASSED. |
| Secondary action | Record FAILED_REWORK_REQUIRED with a defect category; request an authorized waiver; capture new evidence. |
| Empty state | Not applicable; a QC job always references a specific order. |
| Loading state | Evidence loads lazily; the four status actions are available immediately. |
| Error state | A rejected QC decision names the failing precondition and leaves the order in PENDING, unchanged. |
| Offline behaviour | Decisions queue with reused `client_reference` values; a waiver requiring live authorization is refused offline with that reason stated. |
| Permission behaviour | WAIVED_WITH_AUTHORIZATION requires an explicit permission, a recorded reason, and an audit entry; a silent waiver is a defect, not an option. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The three decision actions are spatially separated and each is labelled in full words. |
| Responsive behaviour | Compact: evidence above decisions. Medium and above: evidence pane beside a persistent decision column. |
| Privacy and security notes | QC photographs are RESTRICTED, served by signed expiring URLs, and never reach the public tracking portal. |
| Analytics intent | Pass, rework, and waiver rates with defect category mix. No order identity, no reviewer identity. |
| Future implementation step | Step 6 — Production Operations. |

### SCR-OPS-027 — Rework

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-07 Production Operator, P-08 Quality Control, P-05 Outlet Manager |
| Purpose | Route a failed order back into production with a clear defect record, without disturbing the order's aging anchor. |
| Requirement IDs | FR-082, FR-085, FR-080, UCL-002, NFR-024 |
| Entry points | SCR-OPS-026 after FAILED_REWORK_REQUIRED; a manager-initiated rework from SCR-OPS-023. |
| Exit points | Back to SCR-OPS-022 with the order in REWORK; SCR-OPS-025 to assign the rework. |
| Data displayed | Order number, defect category and note, the stage the order returns to, rework count for this order, original first-ready timestamp when one already exists. |
| Data masked | Financial data and customer contact remain absent from this operator-facing view. |
| Primary action | "Kirim ke pengerjaan ulang" — transition to REWORK. |
| Secondary action | Assign the rework; attach additional evidence; escalate to the manager. |
| Empty state | Not applicable; rework always concerns a specific failed order. |
| Loading state | Determinate transition state; the defect note is preserved throughout. |
| Error state | A rejected transition explains the precondition and leaves the QC decision intact so it need not be re-entered. |
| Offline behaviour | Rework transitions queue idempotently under a reused `client_reference`; a replay of an already-applied rework is a no-op. |
| Permission behaviour | Only QC and manager roles may initiate rework, enforced server-side against the enumerated transition table. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Rework count is stated in text, and the aging note is read aloud with the status change. |
| Responsive behaviour | Compact: single column. Medium and above: defect detail beside the target-stage selector. |
| Privacy and security notes | The screen states explicitly that returning to READY_FOR_PICKUP a second time does not restart aging — the first-ready timestamp is immutable. |
| Analytics intent | Rework rate by defect category and by stage. No order identity, no note text. |
| Future implementation step | Step 6 — Production Operations. |

### SCR-OPS-028 — Ready for Pickup

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-05 Outlet Manager |
| Purpose | Mark an order ready, notify the customer, and hand it over correctly when they arrive. |
| Requirement IDs | FR-080, FR-093, FR-094, UCL-001, UCL-005, DEL-027 |
| Entry points | SCR-OPS-026 after PASSED; SCR-OPS-022; a customer arriving at the counter. |
| Exit points | SCR-OPS-016 Payment when a balance remains; COMPLETED handover; SCR-OPS-011 Delivery Schedule handoff on the console. |
| Data displayed | Order number, shelf or rack location, first-ready timestamp, days since ready, outstanding balance, reminder stage already sent, collection nominee. |
| Data masked | The customer phone stays masked; the address is shown only if the order converts to delivery. |
| Primary action | "Tandai siap diambil" — record READY_FOR_PICKUP, or "Serahkan pesanan" once the customer is present. |
| Secondary action | Send a manual reminder; convert to delivery; record the reason it was not collected. |
| Empty state | An outlet with nothing waiting shows "Tidak ada pesanan menunggu diambil" with the shelf count at zero. |
| Loading state | The first-ready write shows a determinate state, because it anchors aging and must not be ambiguous. |
| Error state | A failed handover leaves the order in READY_FOR_PICKUP and states clearly that custody has not transferred. |
| Offline behaviour | Both the ready transition and the handover queue idempotently; the first-ready timestamp is written once server-side and never overwritten by a replay. |
| Permission behaviour | Handover requires the counter role and, where a balance exists, the tenant's release policy is enforced server-side. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Outstanding balance is announced before the handover action can be taken. |
| Responsive behaviour | Compact: waiting list then detail. Medium and above: waiting list beside the handover panel. |
| Privacy and security notes | Aging is anchored to the FIRST READY_FOR_PICKUP timestamp and never restarts, even after a REWORK excursion. |
| Analytics intent | Time from ready to collection in buckets. No order identity, no balances. |
| Future implementation step | Step 6 — Production Operations. |

### SCR-OPS-029 — Pickup Job

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-09 Courier Internal |
| Purpose | Give a courier the ordered list of collections for their shift and everything needed to complete each one. |
| Requirement IDs | FR-100, FR-101, FR-104, DEL-008, DEL-010, DEL-018 |
| Entry points | SCR-OPS-006 courier primary action; an assignment notification; back from SCR-OPS-031. |
| Exit points | SCR-OPS-031 Proof Capture; SCR-OPS-030 Delivery Job; back to Home at shift end. |
| Data displayed | Stops in suggested order labelled "usulan rute", customer display name "Budi S.", masked phone `0812-XXXX-1234`, address for the current stop only, time window, pickup status from the canonical set. |
| Data masked | Only the current and next stop's address are revealed; the customer database is never browsable, and no order financials appear. |
| Primary action | "Mulai menuju lokasi" then "Konfirmasi penjemputan" at the stop. |
| Secondary action | Call the customer through a masked connection; mark FAILED with a reason; request RESCHEDULED. |
| Empty state | "Belum ada penjemputan hari ini" with the next scheduled window, not an empty map. |
| Loading state | Stop list renders from cache instantly; window details refresh in the background with a visible stamp. |
| Error state | A failed status update is retained in the queue and the stop stays visibly incomplete rather than appearing done. |
| Offline behaviour | The full day's stop list is cached at shift start; every status change and proof queues under a reused `client_reference`. |
| Permission behaviour | A courier sees only stops assigned to them in the active tenant; another courier's stops are not fetchable at any URL. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Large one-handed targets, high outdoor contrast, minimal steps per stop. |
| Responsive behaviour | Compact: single stop card with a collapsed list. Medium and above: list beside the active stop; this surface is optimised for compact. |
| Privacy and security notes | Stop ordering is a **suggestion** — "usulan rute". No optimal route, arrival guarantee, or ETA accuracy is claimed anywhere on this screen. |
| Analytics intent | Stops completed per shift and failure reason categories. No addresses, no names, no coordinates. |
| Future implementation step | Step 8 — Pickup and Delivery Operations. |

### SCR-OPS-030 — Delivery Job

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-09 Courier Internal |
| Purpose | Guide a courier through delivering finished orders, including any cash to be collected on arrival. |
| Requirement IDs | FR-102, FR-104, FR-106, DEL-010, DEL-027, FIN-032 |
| Entry points | SCR-OPS-006; SCR-OPS-029 after collections are done; an assignment notification. |
| Exit points | SCR-OPS-031 Proof Capture; SCR-OPS-032 Courier Cash; back to Home. |
| Data displayed | Suggested stop order labelled "usulan rute", order number, masked recipient name, address for the current stop, amount to collect such as `Rp79.000`, delivery status. |
| Data masked | Order line detail, discounts, and customer history are withheld; the courier sees the amount due and nothing more of the ledger. |
| Primary action | "Konfirmasi pengantaran" — which is unreachable until proof is captured. |
| Secondary action | Mark FAILED with a reason; request RESCHEDULED; call through a masked connection. |
| Empty state | "Belum ada pengantaran hari ini" with the next scheduled window stated. |
| Loading state | Cached stop list renders immediately; amounts are shown only from server-confirmed figures. |
| Error state | A FAILED delivery is a first-class outcome: the laundry returns to the outlet, the order returns to a defined status, and the reason is recorded. |
| Offline behaviour | Fully operable offline; proof and cash records queue idempotently and are labelled pending until acknowledged. |
| Permission behaviour | Only the assigned courier may act on a stop; the server rejects any action on an unassigned stop regardless of client state. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Cash-to-collect is the highest-contrast element and is announced on arrival at the stop. |
| Responsive behaviour | Compact: one stop at a time. Medium and above: stop list beside the active stop detail. |
| Privacy and security notes | DELIVERED is unreachable without a captured DeliveryProof. Stop ordering is a suggestion; no delivery time is guaranteed. |
| Analytics intent | Delivery success and failure reason mix. No amounts, no addresses, no recipient names. |
| Future implementation step | Step 8 — Pickup and Delivery Operations. |

### SCR-OPS-031 — Proof Capture

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-09 Courier Internal, P-10 External Local Courier |
| Purpose | Capture the evidence that custody actually changed hands, using the proof method the tenant's policy requires. |
| Requirement IDs | FR-104, FR-105, DEL-020, DEL-021, DEL-027, SEC-013 |
| Entry points | SCR-OPS-029 at a pickup stop; SCR-OPS-030 at a delivery stop; the external courier guest link. |
| Exit points | Back to the job with the stop marked PICKED_UP or DELIVERED; back without completing if proof is abandoned. |
| Data displayed | The configured proof methods (OTP, photo, signature, recipient name), capture controls, recipient name field, capture timestamp `14:30`. |
| Data masked | The delivery OTP is entered but never displayed back, never logged, and never included in any event payload. |
| Primary action | "Simpan bukti" — attach the proof and complete the custody transfer. |
| Secondary action | Retake a photo; clear a signature; switch to an alternative permitted proof method. |
| Empty state | The screen opens with the tenant's default method selected; there is no state in which zero proof is acceptable. |
| Loading state | Photo upload runs in the background with visible per-file progress; the courier is not held at the doorstep waiting. |
| Error state | An OTP mismatch is reported with remaining attempts; an upload failure keeps the proof queued and the stop visibly incomplete. |
| Offline behaviour | Proof is captured and encrypted locally, queued with the stop's reused `client_reference`, and uploaded on reconnect. |
| Permission behaviour | An external courier operating on a guest link may capture proof for their assigned stop only, and can reach nothing else. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Signature capture offers a recipient-name alternative for users who cannot sign. |
| Responsive behaviour | Compact: full-screen capture. Medium and above: capture pane beside the stop summary; compact is the design target. |
| Privacy and security notes | Proof photographs and signatures are RESTRICTED — private storage, tenant-scoped unguessable keys, signed expiring URLs, never on the public portal. |
| Analytics intent | Proof method mix and capture failure categories. Never images, never signatures, never OTP values. |
| Future implementation step | Step 8 — Pickup and Delivery Operations. |

### SCR-OPS-032 — Courier Cash

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-09 Courier Internal, P-05 Outlet Manager |
| Purpose | Track cash a courier has collected from collection through handover, and surface any variance rather than absorbing it. |
| Requirement IDs | FR-110, FR-111, FIN-032, FIN-033, FIN-034, DEL-030 |
| Entry points | SCR-OPS-030 after a cash collection; SCR-OPS-033 at shift end; a manager-initiated reconciliation. |
| Exit points | Handover confirmation returning to Home; SCR-OPS-021 if a cash figure conflicts. |
| Data displayed | Each collection with order number and amount, expected total, counted total, variance in Rupiah, handover recipient, handover time. |
| Data masked | Other couriers' cash positions are not visible to a courier; only a manager sees the cross-courier view. |
| Primary action | "Serahkan kas" — record the handover with the counted amount. |
| Secondary action | Recount; record a variance reason; escalate to the manager. |
| Empty state | A shift with no cash collections shows an explicit zero position with the shift window, not a blank card. |
| Loading state | Expected totals render only from server-confirmed payment records; a provisional expected figure is never shown. |
| Error state | A handover failure leaves the cash position open and clearly unsettled; it never closes optimistically. |
| Offline behaviour | Collections queue idempotently; the handover itself requires connectivity because it settles a financial position. |
| Permission behaviour | Recording a handover and acknowledging a variance are distinct permissions, both enforced server-side and both audited. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Variance is stated in words and sign, never conveyed by colour alone. |
| Responsive behaviour | Compact: collection list above the totals. Medium and above: collection list beside a persistent totals panel. |
| Privacy and security notes | Variance is recorded and acknowledged, never auto-adjusted, rounded away, or hidden. Integer Rupiah throughout; corrections by reversal only. |
| Analytics intent | Variance frequency and magnitude buckets. No amounts tied to a named courier. |
| Future implementation step | Step 8 — Pickup and Delivery Operations. |

### SCR-OPS-033 — Shift

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-06 Cashier, P-05 Outlet Manager |
| Purpose | Open and close a cashier shift, comparing expected cash against counted cash and recording the difference explicitly. |
| Requirement IDs | FR-068, FR-070, FIN-025, FIN-026, FIN-027, RPT-006 |
| Entry points | SCR-OPS-006; the start of a working day; an end-of-day close. |
| Exit points | Back to Home after opening; a closing summary then Home after closing. |
| Data displayed | Shift window (`08:00`–`20:00`), opening float, expected cash from recorded payments, counted cash, variance, payment method breakdown, transaction count. |
| Data masked | Individual customer identities are not listed in the shift summary; only aggregate figures and order counts appear. |
| Primary action | "Buka shift" or "Tutup shift" depending on the current state. |
| Secondary action | Recount; record a variance reason; print the shift summary. |
| Empty state | With no shift open, the screen offers only the open action and shows the previous shift's close time. |
| Loading state | Expected figures render only when server-confirmed; the counting field stays enabled throughout. |
| Error state | Closing with unsynced financial operations pending is refused, with those operations named and a route to SCR-OPS-020. |
| Offline behaviour | A shift cannot be closed while financial operations remain queued — the queue must settle first, and the screen says exactly why. |
| Permission behaviour | Closing another user's shift requires a manager permission and is audited with actor, timestamp, and reason. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Variance is announced with its sign and magnitude in words. |
| Responsive behaviour | Compact: stacked summary and count field. Medium and above: breakdown table beside the counting panel. |
| Privacy and security notes | Shift closing is serialised server-side so two devices cannot close the same shift; the variance is recorded, never suppressed. |
| Analytics intent | Variance distribution and close duration. No amounts tied to a named cashier or outlet. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-034 — Settings

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-05 Outlet Manager, P-06 Cashier, P-07 Production Operator, P-09 Courier Internal |
| Purpose | Hold device-level preferences, hardware pairing, tenant and outlet switching, and diagnostic access. |
| Requirement IDs | FR-016, FR-017, TEN-005, TEN-012, OFF-011, SEC-021 |
| Entry points | SCR-OPS-006 overflow; a support instruction; a printer problem. |
| Exit points | SCR-OPS-004 Tenant Selection; SCR-OPS-005 Outlet Selection; SCR-OPS-019 Printer State; SCR-OPS-035 Logout Guard. |
| Data displayed | Active tenant and outlet, device label, app version, printer pairing, language, sync interval, last sync time, pending queue count. |
| Data masked | No credential, no token, and no server address is rendered in any diagnostic panel. |
| Primary action | None dominant; this is a settings surface with equal-weight rows. |
| Secondary action | Switch tenant; switch outlet; pair a printer; view the offline queue; sign out. |
| Empty state | Not applicable; settings always render their full row set for the role. |
| Loading state | Rows that depend on server state show inline skeletons without blocking local preferences. |
| Error state | A failed preference save reverts the visible control to its actual stored value rather than showing the attempted one. |
| Offline behaviour | Local preferences apply immediately; tenant and outlet switching require connectivity and say so. |
| Permission behaviour | Tenant-level settings are visible only to entitled roles, and every write is authorized server-side irrespective of row visibility. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Sign-out sits at the end, visually separated from routine settings. |
| Responsive behaviour | Compact: single scrolling list. Medium and above: category list beside a detail pane. |
| Privacy and security notes | A tenant or outlet switch purges the previous scope's cached data before the new scope loads. Client-side row visibility is not authorization. |
| Analytics intent | Setting change frequency by category. No values, no device identifiers. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

### SCR-OPS-035 — Logout Guard

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-05 Outlet Manager, P-06 Cashier, P-07 Production Operator, P-09 Courier Internal |
| Purpose | Prevent a sign-out from silently discarding queued work, especially queued financial operations. |
| Requirement IDs | OFF-011, OFF-013, OFF-019, FIN-024, SEC-020 |
| Entry points | The sign-out action on SCR-OPS-034; an automatic sign-out triggered by a policy change. |
| Exit points | SCR-OPS-020 to settle the queue; SCR-OPS-002 Login after a clean sign-out; cancel returns to Settings. |
| Data displayed | Count of pending operations by type, how many of them are financial, the open shift state, and what will be retained versus cleared. |
| Data masked | Operation payloads are summarised by type only; no amounts and no customer identities are listed. |
| Primary action | "Selesaikan antrean dulu" — route to the offline queue rather than proceeding. |
| Secondary action | Sign out anyway where policy permits; cancel. |
| Empty state | With an empty queue and no open shift, the guard states that it is safe to sign out and proceeds on one confirmation. |
| Loading state | A last sync attempt runs with a determinate, bounded progress indicator before the decision is offered. |
| Error state | If the queue cannot be settled, the screen states plainly that signing out now retains the queue on this device and blocks it behind a permission. |
| Offline behaviour | Sign-out with a non-empty financial queue is blocked offline; the queue is never cleared as a side effect of signing out. |
| Permission behaviour | Overriding the guard with pending financial operations requires an explicit, permissioned, audited action by a manager. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The destructive path is separated from the safe path and never the default focus. |
| Responsive behaviour | Compact: full-screen dialog. Medium and above: modal capped at 520px. |
| Privacy and security notes | Signing out clears cached tenant data from the device while preserving the persistent operation queue under its owning user and tenant. |
| Analytics intent | Guard trigger rate and override rate. No queue contents, no user identity. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-OPS-036 — Session Expired

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-05 Outlet Manager, P-06 Cashier, P-07 Production Operator, P-08 Quality Control, P-09 Courier Internal |
| Purpose | Handle an expired or server-revoked session without losing the work in progress on the device. |
| Requirement IDs | SEC-020, SEC-021, OFF-002, OFF-019, FR-006 |
| Entry points | Any authenticated call rejected for an invalid session; an inactivity timeout; a server-side session revocation. |
| Exit points | SCR-OPS-002 Login after re-authentication; the originating screen once the session is restored. |
| Data displayed | A plain explanation, the count of preserved pending operations, the outlet the device remains bound to. |
| Data masked | No tenant business data is rendered behind this screen; the underlying surface is obscured while unauthenticated. |
| Primary action | "Masuk kembali" — re-authenticate. |
| Secondary action | View the preserved queue; sign out fully. |
| Empty state | Not applicable; the screen only appears on an expiry event. |
| Loading state | Re-authentication shows a determinate busy state and returns the user to where they were on success. |
| Error state | A repeated failure routes to full sign-in rather than looping; the queue is preserved throughout. |
| Offline behaviour | Presented when a cached session is known to be expired; queued work stays intact and untouched. |
| Permission behaviour | Re-authentication re-derives role and scope from the server; a previously cached role is never reused as authorization. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The message is announced on arrival and focus lands on the re-login action. |
| Responsive behaviour | Compact: full-screen. Medium and above: centred modal over an obscured background. |
| Privacy and security notes | Expiry invalidates tokens server-side; the device retains no usable credential, and the queue remains encrypted at rest. |
| Analytics intent | Expiry frequency and recovery rate. No user identity, no session identifiers. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

### SCR-OPS-037 — Device Revoked

| Field | Value |
|---|---|
| Platform | Ops Android (Flutter) |
| Persona | P-04 Tenant Admin, P-05 Outlet Manager, P-06 Cashier |
| Purpose | Communicate that this device's access has been withdrawn, and stop it accessing tenant data immediately. |
| Requirement IDs | SEC-021, SEC-022, SEC-046, TEN-013, OFF-013 |
| Entry points | A server response indicating the device registration was revoked; a startup check that fails registration. |
| Exit points | SCR-OPS-003 Device Activation with a new code; app exit. |
| Data displayed | A plain statement that access was revoked, the revocation time, who to contact, and the count of operations still held on the device. |
| Data masked | No tenant name, no outlet data, and no order data is rendered once revocation is known. |
| Primary action | "Aktifkan ulang perangkat" — begin a fresh activation. |
| Secondary action | Contact the tenant admin; exit the app. |
| Empty state | Not applicable; the screen is a terminal state for the current registration. |
| Loading state | The revocation check is a startup step with a bounded timeout; it never leaves the device in an ambiguous half-authorized state. |
| Error state | If revocation status cannot be confirmed, the device fails closed and stays locked rather than assuming access is still valid. |
| Offline behaviour | A known revocation is enforced offline; cached tenant data is purged and the device cannot be used until re-activated. |
| Permission behaviour | Revocation is a server decision; the device cannot appeal, override, or bypass it locally under any circumstance. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The statement is announced immediately and the contact information is selectable text. |
| Responsive behaviour | Compact: full-screen. Medium and above: centred card; layout is irrelevant to the security behaviour. |
| Privacy and security notes | On revocation the device purges cached tenant data and stored tokens; only the encrypted operation queue is retained pending an audited recovery path. |
| Analytics intent | Revocation encounter counts. No device identifiers, no tenant identifiers. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

---

# Console Web (Flutter Web)

Console Web renders in three visually distinct modes so that a user always knows the scope of what they
are reading: **Portfolio Mode** (across the tenants a user legitimately belongs to), **Tenant Mode**
(one tenant, all its brands and outlets), and **Outlet Mode** (a single outlet). Mode is a presentation
of an authorized scope, never a widening of one.

### SCR-CON-001 — Portfolio Dashboard

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-03 Tenant Owner |
| Purpose | Let an owner who holds memberships in several tenants compare them side by side in Portfolio Mode. |
| Requirement IDs | RPT-001, RPT-002, TEN-002, TEN-013, FR-041 |
| Entry points | Sign-in for a user with more than one membership; the mode switcher from Tenant Mode. |
| Exit points | SCR-CON-002 Tenant Dashboard for a chosen tenant; SCR-CON-018 Reports. |
| Data displayed | Per-tenant order volume, revenue in Rupiah, outstanding receivables, unclaimed count, active outlets, and the comparison period. |
| Data masked | No customer-level record is shown in Portfolio Mode; the view is aggregate by design. |
| Primary action | Open a tenant in Tenant Mode. |
| Secondary action | Change the comparison period; export the portfolio summary; switch mode. |
| Empty state | A user with exactly one membership never sees Portfolio Mode at all, rather than a portfolio containing one row. |
| Loading state | Per-tenant cards load independently so a slow tenant does not block the rest of the comparison. |
| Error state | A tenant whose figures fail to load is shown as unavailable with a retry; its absence is never rendered as a zero. |
| Offline behaviour | Console Web assumes connectivity; a lost connection shows a persistent banner and freezes figures with a "Data terakhir 14:30" stamp. |
| Permission behaviour | Aggregation covers only tenants the user genuinely belongs to; the query surface is never widened to produce the aggregate. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Each figure has a text label; trends are stated numerically, not only as a sparkline. |
| Responsive behaviour | Compact: single-column cards. Medium: two columns. Expanded: three. Wide: four with a comparison table below. |
| Privacy and security notes | Portfolio Mode must not weaken tenant isolation; each tenant's figures are computed within its own scope and only then combined for display. |
| Analytics intent | Mode usage and period selection. No revenue figures, no tenant names. |
| Future implementation step | Step 10 — Finance, Reports, and Owner Portfolio. |

### SCR-CON-002 — Tenant Dashboard

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-03 Tenant Owner, P-04 Tenant Admin |
| Purpose | Present one tenant's operational and financial health across all its brands and outlets in Tenant Mode. |
| Requirement IDs | RPT-003, RPT-004, RPT-007, FR-042, FR-043 |
| Entry points | SCR-CON-001; sign-in for a single-tenant user; the mode switcher from Outlet Mode. |
| Exit points | SCR-CON-003 Outlet Dashboard; SCR-CON-004 Orders; SCR-CON-014 Unclaimed Laundry; SCR-CON-018 Reports. |
| Data displayed | Orders by status, revenue and receivables in Rupiah, outlet comparison, unclaimed ladder counts, courier settlement position, subscription usage against plan limits. |
| Data masked | Customer phone appears masked in any drill-down preview; addresses are not shown at dashboard level at all. |
| Primary action | Open the outlet or metric that most needs attention. |
| Secondary action | Change period; switch to Outlet Mode; export; open reports. |
| Empty state | A newly created tenant with no orders sees a setup checklist rather than a grid of zeroes presented as performance. |
| Loading state | Tiles load independently with skeletons that preserve height so the layout does not reflow. |
| Error state | A failed tile is marked unavailable with a retry; a partial dashboard is never presented as complete. |
| Offline behaviour | Figures freeze with an explicit staleness stamp and a persistent offline banner; nothing is presented as live. |
| Permission behaviour | Tile visibility follows role, but every underlying query is authorized server-side against membership and permission. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Charts carry accessible data tables; no status is colour-only. |
| Responsive behaviour | Compact: stacked tiles. Medium: two columns. Expanded: three-column grid. Wide: three columns with a persistent filter rail. |
| Privacy and security notes | All figures are tenant-scoped; no figure is ever computed across a tenant boundary. Money is integer Rupiah. |
| Analytics intent | Tile engagement and drill-down paths. No figures, no tenant identity. |
| Future implementation step | Step 10 — Finance, Reports, and Owner Portfolio. |

### SCR-CON-003 — Outlet Dashboard

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-05 Outlet Manager, P-04 Tenant Admin |
| Purpose | Give an outlet manager the day's operational picture for one outlet in Outlet Mode. |
| Requirement IDs | RPT-005, RPT-006, FR-044, FR-071, UCL-006 |
| Entry points | SCR-CON-002; direct sign-in for an outlet-scoped role; the mode switcher. |
| Exit points | SCR-CON-005 Production Board; SCR-CON-016 Cashier Shifts; SCR-CON-014 Unclaimed Laundry; SCR-CON-004 Orders. |
| Data displayed | Orders taken today, orders by production stage, shift state and cash position, ready-but-uncollected count, delivery stops outstanding, staff on shift. |
| Data masked | Individual customer records are summarised; phone appears masked in previews and addresses are not shown here. |
| Primary action | Open whichever stage is furthest behind its target. |
| Secondary action | Open shifts; open the production board; change the day. |
| Empty state | An outlet before opening hours shows the day's schedule and yesterday's close, not an empty dashboard. |
| Loading state | Independent tile skeletons; the date selector is usable immediately. |
| Error state | Failed tiles are marked unavailable individually with retries; the rest of the dashboard stays usable. |
| Offline behaviour | Frozen figures with a staleness stamp and an offline banner; no write action is offered while disconnected. |
| Permission behaviour | Outlet Mode is scoped by membership; an outlet ID outside the user's entitlement resolves to not-found, never to partial data. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Stage counts are announced with their labels; no reliance on colour. |
| Responsive behaviour | Compact: stacked. Medium: two columns. Expanded and wide: three columns with the stage board given the widest track. |
| Privacy and security notes | Outlet-scoped and tenant-scoped throughout; the mode indicator is always visible so scope is never ambiguous. |
| Analytics intent | Manager drill-down paths and dwell time. No counts tied to a named outlet. |
| Future implementation step | Step 10 — Finance, Reports, and Owner Portfolio. |

### SCR-CON-004 — Orders

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-04 Tenant Admin, P-05 Outlet Manager, P-11 Finance |
| Purpose | Search, filter, and inspect orders across the authorized scope, and act on them where permitted. |
| Requirement IDs | FR-048, FR-055, FR-060, RPT-008, TEN-008, SEC-030 |
| Entry points | SCR-CON-002; SCR-CON-003; a link from a report or an audit entry. |
| Exit points | An order detail view; SCR-CON-015 Receivables; SCR-CON-011 Planning for a delivery conversion. |
| Data displayed | Order number, customer name with masked phone, outlet, status, intake and ready times, total and balance in Rupiah, assigned staff, aging where READY_FOR_PICKUP. |
| Data masked | Phone is masked by default; revealing it is a permissioned, audited action, not a hover affordance. |
| Primary action | Open the selected order. |
| Secondary action | Filter by status, outlet, and date; export the filtered set; bulk-act where permitted. |
| Empty state | An unmatched filter says so and offers to clear the filter, distinctly from "this outlet has no orders". |
| Loading state | Virtualised table with skeleton rows; filters stay interactive during load. |
| Error state | A failed page load appends a retry row and never silently truncates the result set. |
| Offline behaviour | Read-only from the last loaded page with a staleness stamp; all write actions are disabled with the reason shown. |
| Permission behaviour | Results are tenant- and scope-filtered server-side; a bulk action shows item count, scope, a confirmation, the required permission, a reason field, and its audit effect. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Table headers are associated, sort state is announced, and rows are keyboard navigable. |
| Responsive behaviour | Compact: card list. Medium: reduced column set. Expanded and wide: full table with the filter rail pinned; the table scrolls inside its own container. |
| Privacy and security notes | Exports carry the same access rules as the underlying records and are tenant-scoped, private, and delivered by signed expiring URL. |
| Analytics intent | Filter and export usage. Never order numbers, never customer data, never exported contents. |
| Future implementation step | Step 5 — POS, Order, and Payment Foundation. |

### SCR-CON-005 — Production Board

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-05 Outlet Manager, P-04 Tenant Admin |
| Purpose | Show work in progress by production stage so a manager can spot a bottleneck before it becomes a backlog. |
| Requirement IDs | FR-071, FR-072, FR-077, FR-078, RPT-009 |
| Entry points | SCR-CON-003; SCR-CON-002; a stage-count drill-down. |
| Exit points | An order detail; SCR-CON-012 Courier Assignment; SCR-CON-009 Employees for capacity. |
| Data displayed | Lanes for SORTING, WASHING, DRYING, FINISHING, QUALITY_CONTROL and REWORK, each with order cards showing number, age in stage, express flag, and assignee. |
| Data masked | Order totals and customer contact are absent from the board; this is an operations view, not a commercial one. |
| Primary action | Open the oldest card in the most congested lane. |
| Secondary action | Reassign; filter by express; change outlet; refresh. |
| Empty state | Empty lanes are labelled explicitly as empty rather than merely rendering as blank columns. |
| Loading state | Lane headers with counts render first, then card skeletons within each lane. |
| Error state | A lane that fails to load is marked unavailable so its emptiness is never mistaken for zero work. |
| Offline behaviour | Read-only with a staleness stamp; transitions and reassignments are disabled with the reason stated. |
| Permission behaviour | Reassignment requires an assigning role; the board never permits an arbitrary status write, only enumerated transitions the server accepts. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Lanes have semantic headings and cards are reachable without a drag interaction. |
| Responsive behaviour | Compact: one lane at a time with a lane selector. Medium: two lanes. Expanded and wide: all lanes with the board scrolling inside its own container. |
| Privacy and security notes | Outlet- and tenant-scoped; a REWORK card never restarts the order's aging anchor and the board states the original first-ready time where one exists. |
| Analytics intent | Stage dwell time and bottleneck frequency. No order identity, no assignee identity. |
| Future implementation step | Step 6 — Production Operations. |

### SCR-CON-006 — Customers

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-04 Tenant Admin, P-05 Outlet Manager |
| Purpose | Maintain the tenant's customer records and inspect one customer's history without exposing the directory wholesale. |
| Requirement IDs | FR-021, FR-024, FR-027, FR-030, TEN-011, SEC-030 |
| Entry points | SCR-CON-002; a customer link from an order; a search from the global bar. |
| Exit points | A customer detail view; SCR-CON-004 filtered to that customer; SCR-CON-015 Receivables. |
| Data displayed | Name "Dewi Anggraini", masked phone `0812-XXXX-1234`, outlet of first order, order count, lifetime value in Rupiah, outstanding balance, marketing consent state. |
| Data masked | Phone is masked by default; addresses are hidden behind a permissioned reveal that is audited. |
| Primary action | Open the selected customer. |
| Secondary action | Create a customer; merge duplicates within this tenant only; export with permission; edit consent. |
| Empty state | "Belum ada pelanggan" for a new tenant, with a route to the intake flow rather than an unexplained blank table. |
| Loading state | Virtualised rows with skeletons; the search field is active from the first frame. |
| Error state | A search failure states that results may be incomplete and never presents a partial list as authoritative. |
| Offline behaviour | Read-only from the loaded page with a staleness stamp; all mutations are disabled with the reason shown. |
| Permission behaviour | Merging is permitted only within a tenant; records are never merged across tenants because name, email, phone, or device match. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Consent state is stated in words; reveal actions announce that they are audited. |
| Responsive behaviour | Compact: card list. Medium: reduced columns. Expanded and wide: full table with a detail drawer. |
| Privacy and security notes | Customer identity is CONFIDENTIAL and addresses are RESTRICTED; both a reveal and an export are audited events with actor, time, and reason. |
| Analytics intent | Search, reveal, and export counts. Never names, phones, addresses, or exported contents. |
| Future implementation step | Step 4 — Laundry Master Data. |

### SCR-CON-007 — Services

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-04 Tenant Admin, P-03 Tenant Owner |
| Purpose | Define the tenant's service catalogue and control which services each outlet offers. |
| Requirement IDs | FR-031, FR-032, FR-033, FR-034, FR-045 |
| Entry points | SCR-CON-002; SCR-CON-008 Pricing; an empty-catalogue prompt from the Ops app. |
| Exit points | SCR-CON-008 Pricing for the selected service; back to the tenant dashboard. |
| Data displayed | Service name, category, pricing basis (per kg or per item), turnaround, express option, outlets where it is enabled, active state. |
| Data masked | No customer or order data appears; this is a master-data surface. |
| Primary action | "Simpan layanan" — create or update a service. |
| Secondary action | Enable or disable per outlet; duplicate a service; deactivate with a reason. |
| Empty state | A new tenant sees a guided prompt to define its first service, since no order can be taken without one. |
| Loading state | The list renders with skeletons; the editor opens immediately for a new service. |
| Error state | A save failure names the failing field and retains every entered value. |
| Offline behaviour | Read-only with a staleness stamp; catalogue edits require connectivity because devices sync from this record. |
| Permission behaviour | Only tenant-level roles may edit the catalogue; deactivation shows scope, item count, confirmation, required permission, reason, and audit effect. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Active state is text plus icon; the outlet matrix has associated row and column headers. |
| Responsive behaviour | Compact: list then full-page editor. Medium and above: list beside the editor with the outlet matrix scrolling in its own container. |
| Privacy and security notes | Catalogue changes are audited and never retroactively alter a historical order's captured price. |
| Analytics intent | Catalogue size and edit frequency. No service names, no prices. |
| Future implementation step | Step 4 — Laundry Master Data. |

### SCR-CON-008 — Pricing

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-04 Tenant Admin, P-03 Tenant Owner, P-11 Finance |
| Purpose | Set and schedule the prices attached to services, with the guarantee that history is never rewritten. |
| Requirement IDs | FR-035, FR-036, FR-037, FIN-009, FIN-013, FIN-019 |
| Entry points | SCR-CON-007; SCR-CON-002; a pricing review task. |
| Exit points | Back to SCR-CON-007; SCR-CON-020 Audit to review a price change. |
| Data displayed | Service, unit price such as `Rp25.000`, effective-from date, per-outlet overrides, express surcharge, minimum charge, the currently active version. |
| Data masked | No cost or margin figure is exposed to roles without the finance entitlement. |
| Primary action | "Terbitkan harga" — publish a price version with an effective date. |
| Secondary action | Schedule a future price; add an outlet override; view version history. |
| Empty state | A service with no price cannot be sold; the screen states that plainly and blocks activation until a price exists. |
| Loading state | Version history loads after the active price so the active figure is never delayed. |
| Error state | An overlapping effective-date range is rejected with both conflicting versions named. |
| Offline behaviour | Read-only with a staleness stamp; publishing requires connectivity because it changes what every device will sync. |
| Permission behaviour | Publishing requires a finance or tenant-admin permission and is audited with actor, timestamp, and reason. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Amounts are announced as full Rupiah values; effective dates are stated in full. |
| Responsive behaviour | Compact: single column. Medium and above: price editor beside the version history timeline. |
| Privacy and security notes | Integer Rupiah only; publishing a new price never alters a historical order, invoice, or reprint. |
| Analytics intent | Publish frequency and scheduling lead time. Never prices, never service names. |
| Future implementation step | Step 4 — Laundry Master Data. |

### SCR-CON-009 — Employees

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-04 Tenant Admin, P-05 Outlet Manager |
| Purpose | Manage the people who work for the tenant, their outlet assignments, and their access state. |
| Requirement IDs | FR-008, FR-009, FR-046, TEN-003, SEC-020, SEC-021 |
| Entry points | SCR-CON-002; SCR-CON-010 Roles; an onboarding task. |
| Exit points | An employee detail view; SCR-CON-010 to change a role; SCR-CON-020 Audit. |
| Data displayed | Name, role, assigned outlets, membership state, last active time, active device count, invitation state. |
| Data masked | Personal contact details are masked by default and revealed only through a permissioned, audited action. |
| Primary action | "Undang karyawan" — issue an invitation. |
| Secondary action | Assign outlets; suspend membership; revoke a device; resend an invitation. |
| Empty state | A new tenant sees only the owner and a prompt to invite the first staff member. |
| Loading state | Table skeletons; the invite action is available immediately. |
| Error state | A failed invitation names the reason — an address already invited, or a plan staff limit reached — rather than failing generically. |
| Offline behaviour | Read-only with a staleness stamp; every membership mutation requires connectivity and says so. |
| Permission behaviour | Suspension and device revocation require distinct permissions; both show scope, count, confirmation, reason, and audit effect before they apply. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Membership state is text plus icon; destructive actions are separated from routine edits. |
| Responsive behaviour | Compact: card list. Medium: reduced columns. Expanded and wide: full table with a detail drawer. |
| Privacy and security notes | Suspending a membership revokes access at the next server check; a stale cached role is never treated as authorization. |
| Analytics intent | Invitation and suspension counts. No names, no contact details. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

### SCR-CON-010 — Roles

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-04 Tenant Admin, P-03 Tenant Owner |
| Purpose | Define what each of the fourteen roles may do within the tenant, and make the effect of a change legible before it is saved. |
| Requirement IDs | FR-008, FR-010, SEC-001, SEC-003, SEC-035, TEN-010 |
| Entry points | SCR-CON-009; SCR-CON-002; a permission review task. |
| Exit points | Back to SCR-CON-009; SCR-CON-020 Audit for the change record. |
| Data displayed | Role name, permission matrix by bounded context, count of members holding the role, a preview of what changes for those members. |
| Data masked | No member's personal data appears in the matrix; only counts are shown. |
| Primary action | "Simpan peran" — persist the permission change. |
| Secondary action | Duplicate a role; compare two roles; revert to the last saved version. |
| Empty state | A tenant using only the default roles sees them listed as defaults rather than an empty custom-role table. |
| Loading state | The matrix loads as a whole so a partially-rendered permission set can never be misread as the real one. |
| Error state | A save that would leave the tenant with no administrator is rejected with that reason named explicitly. |
| Offline behaviour | Read-only with a staleness stamp; permission changes require connectivity because they take effect server-side. |
| Permission behaviour | Only a role entitled to manage roles may edit; the matrix is a description of server-side policy, and hiding a control in a client is never the enforcement. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The matrix has associated row and column headers and is fully keyboard navigable. |
| Responsive behaviour | Compact: permissions grouped in an accordion. Medium and above: full matrix scrolling inside its own horizontal container. |
| Privacy and security notes | Every permission change is audited with actor, timestamp, before and after, and reason. Least privilege is the default for a new role. |
| Analytics intent | Role edit frequency and custom-role count. No permission contents, no tenant identity. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

### SCR-CON-011 — Pickup and Delivery Planning

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-05 Outlet Manager, P-04 Tenant Admin |
| Purpose | Plan the day's collections and deliveries by zone and time window before couriers are assigned. |
| Requirement IDs | FR-100, FR-101, FR-102, DEL-003, DEL-005, DEL-010 |
| Entry points | SCR-CON-003; SCR-CON-002; an incoming pickup request notification. |
| Exit points | SCR-CON-012 Courier Assignment; SCR-CON-013 Route Ordering; an order detail. |
| Data displayed | Requests by status (REQUESTED, CONFIRMED, SCHEDULED), zone, time window, address area rather than full address, order count per window, capacity against couriers available. |
| Data masked | Full addresses are collapsed to an area label at planning level and revealed only where the task genuinely requires them. |
| Primary action | "Jadwalkan" — confirm requests into scheduled windows. |
| Secondary action | Reassign a zone; split a window; decline a request with a reason. |
| Empty state | A day with no requests shows the window grid with zero counts and states that plainly. |
| Loading state | The window grid renders first; request cards populate into it so the structure never jumps. |
| Error state | An over-capacity window is flagged before scheduling rather than after, and the flag names the shortfall. |
| Offline behaviour | Read-only with a staleness stamp; scheduling requires connectivity because it creates commitments to customers. |
| Permission behaviour | Scheduling requires a manager permission at the owning outlet; a request from another outlet is not reachable. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Capacity warnings are text plus icon and reachable without drag interaction. |
| Responsive behaviour | Compact: window list. Medium: two-column grid. Expanded and wide: full day grid scrolling inside its own container. |
| Privacy and security notes | Addresses are RESTRICTED; planning shows an area, never a full address, and never a customer phone. No delivery time is guaranteed to a customer. |
| Analytics intent | Scheduling lead time and capacity-warning frequency. No addresses, no customer identity. |
| Future implementation step | Step 8 — Pickup and Delivery Operations. |

### SCR-CON-012 — Courier Assignment

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-05 Outlet Manager, P-04 Tenant Admin |
| Purpose | Assign scheduled stops to an internal courier or an external local courier working from a guest link. |
| Requirement IDs | FR-108, FR-109, DEL-007, DEL-022, DEL-024, SEC-047 |
| Entry points | SCR-CON-011; SCR-CON-005; a courier availability change. |
| Exit points | SCR-CON-013 Route Ordering; SCR-CON-017 Courier Settlement; back to planning. |
| Data displayed | Couriers with type (internal or external ojek), current load, zone coverage, assigned stops, guest-link state and expiry for external couriers. |
| Data masked | A guest link's token is never displayed after issuance; only its state, scope, and expiry are shown. |
| Primary action | "Tugaskan kurir" — assign the selected stops. |
| Secondary action | Issue a guest link; revoke a guest link; rebalance load; unassign with a reason. |
| Empty state | With no courier available, the screen says so and offers to issue an external guest link rather than assigning to nobody. |
| Loading state | Courier list loads with skeletons; stop counts follow so the list is usable immediately. |
| Error state | A conflicting assignment is resolved server-side and the losing assignment is surfaced explicitly to the manager. |
| Offline behaviour | Read-only with a staleness stamp; assignment and link issuance both require connectivity. |
| Permission behaviour | Issuing and revoking a guest link are distinct permissions, both audited; a link is minimum-privilege, expiring, and revocable by design. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Courier type is text plus icon; guest-link expiry is stated in full, not relative only. |
| Responsive behaviour | Compact: courier list then stop picker. Medium and above: courier list beside the stop list. |
| Privacy and security notes | The guest link is a high-entropy token stored hashed, scoped to a single assignment and tenant, and never derivable from an order number. |
| Analytics intent | Assignment counts and guest-link issuance and revocation rates. Never tokens, never courier identity. |
| Future implementation step | Step 8 — Pickup and Delivery Operations. |

### SCR-CON-013 — Route Ordering (Usulan Rute)

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-05 Outlet Manager, P-04 Tenant Admin |
| Purpose | Produce a **suggested** stop order for a courier's assigned stops — an "usulan rute" that a human accepts, edits, or ignores. |
| Requirement IDs | DEL-009, DEL-010, DEL-012, FR-104, FR-108 |
| Entry points | SCR-CON-012 after assignment; SCR-CON-011 planning; a courier requesting a re-order. |
| Exit points | Back to SCR-CON-012 with the ordering saved; an individual stop detail. |
| Data displayed | The ordered stop list with sequence number, area label, time window, order number, and a persistent literal label reading "usulan rute". |
| Data masked | Full addresses are shown only at the stop level to a role that needs them; the list view uses area labels. |
| Primary action | "Simpan usulan rute" — persist the suggested ordering for the courier. |
| Secondary action | Reorder a stop manually; lock a stop's position; regenerate the suggestion. |
| Empty state | With no assigned stops, the screen states that ordering requires an assignment first and links back to SCR-CON-012. |
| Loading state | Suggestion generation shows a determinate state; the manual list stays editable throughout. |
| Error state | If a suggestion cannot be produced, the manual ordering remains fully usable — the feature degrades to a plain ordered list, never to a blocked screen. |
| Offline behaviour | Read-only with a staleness stamp; saving an ordering requires connectivity because couriers sync from it. |
| Permission behaviour | Only a role permitted to plan may save an ordering; a courier may not silently rewrite the saved suggestion. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Reordering is available by keyboard with explicit move controls, never drag-only. |
| Responsive behaviour | Compact: ordered list. Medium and above: ordered list beside a stop detail pane. |
| Privacy and security notes | **No route optimization, no guaranteed arrival time, and no ETA accuracy is claimed anywhere.** The ordering is a suggestion, labelled as such in the interface and in every export. |
| Analytics intent | How often a suggestion is edited before saving. No addresses, no coordinates, no courier identity. |
| Future implementation step | Step 8 — Pickup and Delivery Operations. |

### SCR-CON-014 — Unclaimed Laundry

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-05 Outlet Manager, P-03 Tenant Owner, P-11 Finance |
| Purpose | Turn the pile of finished-but-uncollected orders into a worked list with an owner, a next action, and a recorded outcome. |
| Requirement IDs | UCL-001, UCL-004, UCL-006, UCL-011, UCL-012, UCL-018, NOT-003 |
| Entry points | SCR-CON-002; SCR-CON-003; an H+7 follow-up task; an H+14 escalation notification. |
| Exit points | An order detail; SCR-CON-015 Receivables; SCR-CON-011 to convert an order to delivery. |
| Data displayed | Aging bucket, order count, customer count, outstanding balance in Rupiah, paid amount, reminder state, last response, assigned follow-up owner, promise to collect, dispute, converted to delivery — all tenant- and outlet-scoped. |
| Data masked | Customer phone is masked as `0812-XXXX-1234`; addresses are hidden unless the row has been converted to a delivery and the role is entitled. |
| Primary action | "Tugaskan tindak lanjut" — assign a follow-up owner to the selected orders. |
| Secondary action | Record a promise to collect; record a dispute; convert to delivery; send a manual reminder. |
| Empty state | "Tidak ada cucian menumpuk" with the count at zero for each bucket, which is a genuine and welcome result, not a failure. |
| Loading state | Bucket headers with counts render first; row skeletons populate beneath them. |
| Error state | A failed bucket is marked unavailable rather than rendered as zero, because a false zero here hides money. |
| Offline behaviour | Read-only with a staleness stamp; assignment and reminder actions require connectivity. |
| Permission behaviour | Bulk reminder sends show item count, scope, confirmation, the required permission, a reason field, and the audit effect before anything is dispatched. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Aging buckets are semantic headings; reminder state is text plus icon. |
| Responsive behaviour | Compact: bucket accordion. Medium: two columns. Expanded and wide: full table with the bucket rail pinned and the table scrolling in its own container. |
| Privacy and security notes | Aging starts when an order FIRST reaches READY_FOR_PICKUP and never restarts. The ladder is exactly H+1, H+3, H+7 (plus a real assignable follow-up task) and H+14 (escalation to manager or owner); each stage fires once, respects opt-out, and respects quiet hours defaulting to 20.00–08.00 outlet local time. A storage fee is **OPTIONAL / TENANT-CONFIGURED / SUBJECT TO POLICY / NOT ASSUMED ACTIVE**. The product never discards, sells, auctions, donates, or transfers ownership of a customer's laundry, and offers no control that would. |
| Analytics intent | Bucket sizes, reminder-stage completion, and recovery rate. No balances, no customer identity. |
| Future implementation step | Step 9 — Unclaimed Laundry and Cashflow Recovery. |

### SCR-CON-015 — Receivables

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-11 Finance, P-03 Tenant Owner, P-05 Outlet Manager |
| Purpose | Show what customers owe the tenant, by age and by outlet, and support corrections without ever deleting a record. |
| Requirement IDs | FR-062, FR-065, FR-066, FIN-011, FIN-013, RPT-010 |
| Entry points | SCR-CON-002; SCR-CON-014; SCR-CON-004 filtered to unpaid orders. |
| Exit points | An order detail; SCR-CON-020 Audit for a correction; an export. |
| Data displayed | Outstanding balance by age band, per-customer totals with masked phone, per-outlet subtotals, paid versus unpaid amounts, the reversal and adjustment history. |
| Data masked | Phone is masked; a customer's non-financial history is not pulled into this view. |
| Primary action | "Catat penyesuaian" — record an adjustment or reversal entry. |
| Secondary action | Export the receivables list; filter by age band and outlet; open an order. |
| Empty state | "Tidak ada piutang" with the period stated, so a zero is legible as a real result. |
| Loading state | Age-band totals load first; per-customer rows follow, so the headline figure is never provisional. |
| Error state | A failed correction leaves the ledger untouched and states plainly that nothing was recorded. |
| Offline behaviour | Read-only with a staleness stamp; every financial write requires connectivity and says so. |
| Permission behaviour | Adjustments and reversals require a finance permission, a reason, and an audit entry. **There is no delete action for a financial record anywhere on this screen** — corrections are reversal or adjustment entries only. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Amounts are announced as full Rupiah values; age bands have associated headers. |
| Responsive behaviour | Compact: age-band cards. Medium: two columns. Expanded and wide: full table with a pinned totals row and its own scroll container. |
| Privacy and security notes | Integer Rupiah throughout; the ledger is append-only in effect; exports are tenant-scoped and delivered by signed expiring URL. |
| Analytics intent | Adjustment frequency and age-band distribution. Never amounts, never customer identity. |
| Future implementation step | Step 10 — Finance, Reports, and Owner Portfolio. |

### SCR-CON-016 — Cashier Shifts

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-05 Outlet Manager, P-11 Finance |
| Purpose | Review closed and open shifts, their cash variance, and the acknowledgement attached to each variance. |
| Requirement IDs | FR-068, FR-070, FIN-025, FIN-026, FIN-027, RPT-006 |
| Entry points | SCR-CON-003 Outlet Mode; SCR-CON-002; a variance alert. |
| Exit points | A shift detail with its transaction list; SCR-CON-015 Receivables; SCR-CON-020 Audit. |
| Data displayed | Shift window, cashier, opening float, expected cash, counted cash, variance with sign, method breakdown, transaction count, acknowledgement state and reason. |
| Data masked | Individual customer identities are not listed in the shift view; only order counts and amounts appear. |
| Primary action | "Setujui selisih" — acknowledge a recorded variance with a reason. |
| Secondary action | Open a shift's transactions; export the shift summary; filter by outlet and date. |
| Empty state | An outlet with no shift history shows the period and a zero count, clearly labelled as no shifts recorded. |
| Loading state | Shift rows load with skeletons; variance figures render only from confirmed data. |
| Error state | A failed acknowledgement leaves the variance open and unacknowledged, visibly so. |
| Offline behaviour | Read-only with a staleness stamp; acknowledgement requires connectivity. |
| Permission behaviour | Acknowledging a variance is a manager or finance permission and is audited with actor, timestamp, amount, and reason. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Variance sign and magnitude are stated in words, never by colour alone. |
| Responsive behaviour | Compact: shift cards. Medium: two columns. Expanded and wide: table with a detail drawer. |
| Privacy and security notes | A variance is never masked, auto-rounded away, or suppressed from a report; a visible discrepancy is the feature. |
| Analytics intent | Variance frequency and acknowledgement latency. Never amounts, never cashier identity. |
| Future implementation step | Step 10 — Finance, Reports, and Owner Portfolio. |

### SCR-CON-017 — Courier Settlement

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-11 Finance, P-05 Outlet Manager |
| Purpose | Reconcile cash collected by each courier, per shift, from collection through handover. |
| Requirement IDs | FR-110, FR-111, FIN-032, FIN-033, FIN-034, DEL-030 |
| Entry points | SCR-CON-012; SCR-CON-003; an end-of-day settlement task. |
| Exit points | A courier's collection detail; SCR-CON-015 Receivables; SCR-CON-020 Audit. |
| Data displayed | Courier, shift window, stops with cash collected, expected total, handed-over total, variance in Rupiah, handover recipient and time, settlement state. |
| Data masked | Courier personal contact details are not shown; only operational identity appears. |
| Primary action | "Selesaikan penyelesaian" — close the settlement for the shift. |
| Secondary action | Record a variance reason; record an adjustment entry; export the settlement. |
| Empty state | A day with no cash-on-delivery collections shows a clear zero position per courier. |
| Loading state | Courier rows load first; per-stop detail expands on demand rather than loading eagerly. |
| Error state | A settlement that cannot be closed states which collection is unresolved and leaves the position open. |
| Offline behaviour | Read-only with a staleness stamp; closing a settlement requires connectivity because it settles money. |
| Permission behaviour | Closing a settlement and acknowledging a variance are distinct finance permissions, both audited. No hard delete exists — a correction is an adjustment entry. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Variance is announced with sign and magnitude in words. |
| Responsive behaviour | Compact: courier cards. Medium: two columns. Expanded and wide: table with an expandable stop-level detail. |
| Privacy and security notes | Cash collection is a financial transaction under the same integer-Rupiah, idempotency, and reversal-only rules as any other. Variance is recorded and acknowledged, never absorbed silently. |
| Analytics intent | Settlement closure latency and variance rate. Never amounts, never courier identity. |
| Future implementation step | Step 8 — Pickup and Delivery Operations. |

### SCR-CON-018 — Reports

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-03 Tenant Owner, P-11 Finance, P-04 Tenant Admin, P-05 Outlet Manager |
| Purpose | Generate and export the tenant's operational and financial reports within the mode the user is currently in. |
| Requirement IDs | RPT-011, RPT-012, RPT-015, RPT-018, SEC-031, FR-020 |
| Entry points | SCR-CON-001, SCR-CON-002, or SCR-CON-003 depending on mode; a scheduled report link. |
| Exit points | A generated report view; a download via signed URL; back to the originating dashboard. |
| Data displayed | Report catalogue by category, parameters (period, outlet, service), the active mode indicator, generation history with timestamps and the requesting actor. |
| Data masked | Report previews mask customer phone; a report containing addresses requires an explicit entitlement and is audited on generation. |
| Primary action | "Buat laporan" — generate with the chosen parameters. |
| Secondary action | Schedule a recurring report; re-download a previous generation; change mode. |
| Empty state | A tenant with no data in the chosen period gets an explicit "Tidak ada data pada periode ini" rather than an empty file that looks like a zero result. |
| Loading state | Generation runs asynchronously with a determinate progress state; the user may leave and return. |
| Error state | A failed generation names the cause and does not leave a partial file downloadable. |
| Offline behaviour | Previously downloaded files remain on the user's own machine; generation requires connectivity. |
| Permission behaviour | Report scope follows the mode and the user's entitlement; Portfolio Mode reports combine only tenants the user belongs to and never widen a query to achieve it. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Every chart in a rendered report carries an accessible data table. |
| Responsive behaviour | Compact: catalogue then parameters. Medium and above: catalogue beside parameters with the preview below, scrolling in its own container. |
| Privacy and security notes | Exports carry the same access rules as the underlying records, are tenant-scoped, are stored privately, and are served only by signed expiring URLs. Every export is audited. |
| Analytics intent | Report type popularity and export counts. Never report contents, never parameters tied to a tenant. |
| Future implementation step | Step 10 — Finance, Reports, and Owner Portfolio. |

### SCR-CON-019 — Subscription

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-03 Tenant Owner, P-04 Tenant Admin |
| Purpose | Show the tenant's plan, its usage against plan limits, and the separately billed third-party messaging costs. |
| Requirement IDs | SUB-001, SUB-004, SUB-008, SUB-012, SUB-016, FIN-035 |
| Entry points | SCR-CON-002; a limit-approaching notification; a lapsed-subscription banner. |
| Exit points | A plan change flow; an invoice view; SCR-CON-023 Settings. |
| Data displayed | Current plan and price, billing period, outlets used against the limit, staff used against the limit, orders this month against the plan allowance, messaging cost shown separately, renewal date. |
| Data masked | Payment instrument details are never rendered in full; only a masked reference and the provider name appear. |
| Primary action | "Ubah paket" — begin a plan change. |
| Secondary action | View invoices; export tenant data; update billing contact. |
| Empty state | A tenant in its trial sees the remaining trial days stated plainly, with no implication that a paid entitlement is already active. |
| Loading state | Usage counters load after the plan card so the plan is never rendered against provisional usage. |
| Error state | A failed plan change states clearly whether billing was affected and directs the owner to the invoice record. |
| Offline behaviour | Read-only with a staleness stamp; every billing action requires connectivity. |
| Permission behaviour | Only an owner or tenant admin may change a plan; a plan limit is enforced server-side and never by hiding a control here. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Usage meters state their numeric value in text alongside the bar. |
| Responsive behaviour | Compact: plan card then usage list. Medium and above: plan card beside the usage panel. |
| Privacy and security notes | Pricing is reproduced exactly from the canonical source and never paraphrased. The Starter order allowance is described as fair-use, not a hard cutoff. There is no lifetime cloud plan and no per-nota fee; no "unlimited WhatsApp" claim appears anywhere. Tenant data remains exportable per policy when a subscription lapses. |
| Analytics intent | Plan change funnel and limit-warning frequency. No prices, no tenant identity. |
| Future implementation step | Step 12 — Subscription and Platform Administration. |

### SCR-CON-020 — Audit

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-04 Tenant Admin, P-03 Tenant Owner, P-11 Finance |
| Purpose | Let an accountable person read the record of who did what, when, in which scope, and why. |
| Requirement IDs | FR-010, FR-020, FR-060, FR-069, SEC-050, SEC-055 |
| Entry points | SCR-CON-002; a link from any screen that produced an audited change; a compliance review. |
| Exit points | The affected record; an export of the filtered audit range. |
| Data displayed | Actor, role, tenant, outlet, timestamp in outlet local time, action, affected record identity, before and after values where recorded, reason text, correlation identifier. |
| Data masked | Personal data inside an audit entry is masked to the reader's entitlement; a SECRET value never appears because it is never recorded in the first place. |
| Primary action | Open the affected record from the selected entry. |
| Secondary action | Filter by actor, action, and period; export the filtered range with permission. |
| Empty state | An unmatched filter says so explicitly and distinguishes that from a scope in which nothing auditable has yet occurred. |
| Loading state | Virtualised rows with skeletons; filters remain interactive during load. |
| Error state | A failed page keeps prior pages and adds a retry; the record is never presented as complete when it is not. |
| Offline behaviour | Read-only from the loaded page with a staleness stamp; no audit entry is ever authored from this screen. |
| Permission behaviour | Audit access is itself permissioned, and reading the audit log is itself an audited event. **Audit entries are immutable — there is no edit and no delete on this screen.** |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Before-and-after values are labelled in text, not distinguished by colour. |
| Responsive behaviour | Compact: entry cards. Medium: reduced columns. Expanded and wide: full table with a detail drawer and its own scroll container. |
| Privacy and security notes | Audit records are CONFIDENTIAL or RESTRICTED by content, tenant-scoped, and never exposed on any public surface. Passwords, OTPs, tokens, and credentials are never present. |
| Analytics intent | Audit view frequency. Never entry contents, never actor identity. |
| Future implementation step | Step 3 — Runtime, Authentication, Multi-Tenancy, and RBAC. |

### SCR-CON-021 — Support Access

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-02 Platform Support, P-01 Platform Super Admin, P-04 Tenant Admin |
| Purpose | Make platform support access to a tenant explicit, time-bound, reasoned, and visible to the tenant itself. |
| Requirement IDs | SEC-056, SEC-057, SEC-058, FR-019, FR-069, SUB-018 |
| Entry points | A support ticket workflow; SCR-CON-002 for a tenant admin reviewing past access; a platform administration console. |
| Exit points | The impersonated session with a persistent banner; back to the platform console; an audit entry. |
| Data displayed | Requested tenant, requesting support user, stated reason, requested duration, approval state, and the full history of previous access sessions with their durations. |
| Data masked | While a request is pending, no tenant business data is rendered at all. |
| Primary action | "Mulai sesi dukungan" — begin a time-bound, audited access session. |
| Secondary action | End the session early; extend with a new reason; review past sessions. |
| Empty state | A tenant that has never been accessed by support sees an explicit "Belum pernah ada akses dukungan", which is a meaningful assurance. |
| Loading state | Approval state polls with a determinate indicator; no tenant data is prefetched while pending. |
| Error state | A denied or expired request states so plainly and grants nothing; failure is closed, not degraded. |
| Offline behaviour | Read-only with a staleness stamp; starting or ending a support session requires connectivity. |
| Permission behaviour | Support access is never silent: it requires an explicit request, records who, which tenant, when, for how long, and why, and is visible to the tenant. There is no invisible back door. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The active-session banner is announced on entry to every screen it covers. |
| Responsive behaviour | Compact: request form then history. Medium and above: request panel beside the session history. |
| Privacy and security notes | Every impersonated session carries a persistent, unmissable banner in the interface and a corresponding audit trail. Access expires automatically; it is never open-ended. |
| Analytics intent | Session count and duration buckets. Never reasons, never tenant identity, never accessed records. |
| Future implementation step | Step 12 — Subscription and Platform Administration. |

### SCR-CON-022 — Integrations

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-04 Tenant Admin, P-03 Tenant Owner |
| Purpose | Configure the tenant's third-party connections — messaging provider, payment provider, map provider — and show their real state and cost. |
| Requirement IDs | FR-095, FR-097, FR-098, NOT-010, NOT-021, SEC-060 |
| Entry points | SCR-CON-023 Settings; SCR-CON-002; a provider failure alert. |
| Exit points | A provider configuration form; SCR-CON-019 Subscription for cost detail; SCR-CON-020 Audit. |
| Data displayed | Each provider with connection state, last successful call time, recent failure count, message volume for the period, and the separately billed provider cost. |
| Data masked | Provider credentials are write-only: entered once, stored hashed or encrypted server-side, and never rendered back, not even partially. |
| Primary action | "Simpan konfigurasi" — persist the provider configuration. |
| Secondary action | Run a connection test; rotate a credential; disable a provider; review the failure log. |
| Empty state | A tenant with no messaging provider is told plainly that automated sending is unavailable and that the manual deep-link fallback is what remains. |
| Loading state | Connection tests show a determinate state with a bounded timeout; the form stays editable. |
| Error state | A provider failure is reported with its category and the fact that business state was unaffected — messaging failures never change an order. |
| Offline behaviour | Read-only with a staleness stamp; configuration changes and connection tests require connectivity. |
| Permission behaviour | Only a tenant admin or owner may configure providers; every credential change is audited with actor, timestamp, and reason, but never with the value. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Connection state is text plus icon; test results are announced on completion. |
| Responsive behaviour | Compact: provider list then form. Medium and above: provider list beside the configuration form. |
| Privacy and security notes | Credentials are SECRET — never logged, never echoed, never placed in telemetry, never committed. The manual `wa.me` deep link is a fallback a staff member taps, and is never described as automation. |
| Analytics intent | Provider configuration counts and failure category rates. Never credentials, never message contents. |
| Future implementation step | Step 7 — Customer Tracking and WhatsApp. |

### SCR-CON-023 — Settings

| Field | Value |
|---|---|
| Platform | Console Web (Flutter Web) |
| Persona | P-04 Tenant Admin, P-03 Tenant Owner, P-05 Outlet Manager |
| Purpose | Hold tenant, brand, and outlet configuration, including the operational policies other screens depend on. |
| Requirement IDs | FR-011, FR-016, FR-017, FR-047, NOT-003, UCL-018 |
| Entry points | SCR-CON-002; SCR-CON-003 in Outlet Mode; a configuration prompt from another screen. |
| Exit points | SCR-CON-022 Integrations; SCR-CON-010 Roles; SCR-CON-019 Subscription; back to the dashboard. |
| Data displayed | Tenant profile, brands, outlets with operating hours and timezone, quiet-hours window, proof-of-custody policy, reminder ladder configuration, storage-fee policy state, receipt template. |
| Data masked | No customer or financial data appears on a configuration surface. |
| Primary action | "Simpan pengaturan" — persist the configuration change. |
| Secondary action | Add an outlet; add a brand; deactivate an outlet with a reason; preview the receipt template. |
| Empty state | A tenant with one brand and one outlet sees a simplified layout rather than empty hierarchy scaffolding. |
| Loading state | Sections load independently; a slow section never blocks an unrelated setting. |
| Error state | A save failure names the failing field, retains input, and states which dependent behaviour is unchanged as a result. |
| Offline behaviour | Read-only with a staleness stamp; configuration writes require connectivity because devices and schedulers sync from them. |
| Permission behaviour | Deactivating an outlet is a bulk-consequence action and shows affected item count, scope, confirmation, the required permission, a reason field, and its audit effect. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Policy toggles state their meaning in words; destructive rows are separated from routine ones. |
| Responsive behaviour | Compact: section accordion. Medium and above: section rail beside the settings pane; Portfolio Mode is not offered here because settings are tenant-scoped. |
| Privacy and security notes | Quiet hours default to 20.00–08.00 outlet local time. The storage-fee policy is **OPTIONAL / TENANT-CONFIGURED / SUBJECT TO POLICY / NOT ASSUMED ACTIVE**, and no setting anywhere permits disposal, sale, auction, donation, or ownership transfer of a customer's laundry. |
| Analytics intent | Setting change frequency by category. No values, no tenant identity. |
| Future implementation step | Step 4 — Laundry Master Data. |

---

# Public Tracking Portal (browser, no app install)

The public tracking portal is the most exposed surface in the product. It is reachable in a browser
with no application install, and it must never become "install the app first". Every screen below is
served with a `noindex` directive (both a `robots` meta tag and an `X-Robots-Tag` response header) so
that no tracking page is ever indexed by a search engine. No tracking token value is ever written to
analytics, telemetry, logs, or a referrer.

### SCR-TRK-001 — Valid Tracking Landing

| Field | Value |
|---|---|
| Platform | Public Tracking Portal (browser, no app install) |
| Persona | P-12 Customer, P-14 Authorized Order Recipient |
| Purpose | Resolve a valid tracking link into a minimal, honest view of one order's progress. |
| Requirement IDs | TRK-001, TRK-002, TRK-005, TRK-010, TRK-011, FR-086 |
| Entry points | The link printed on a receipt; the link sent in a WhatsApp message; a saved bookmark. |
| Exit points | SCR-TRK-002, 003, or 004 depending on status; SCR-TRK-010 Contact Outlet; SCR-TRK-011 Payment Balance. |
| Data displayed | Order number `AL-2026-000123`, masked customer name "Budi S.", outlet name "Outlet Cempaka", current status in customer language, last update time `14:30`. |
| Data masked | Never a full phone number, never a full address, never an internal note, never a cost or margin figure, never employee data beyond operational necessity, never audit data, never a laundry photograph. |
| Primary action | View the status detail for the current stage. |
| Secondary action | Contact the outlet; view the balance if one exists. |
| Empty state | Not applicable; a valid token always resolves to exactly one order. |
| Loading state | A single skeleton card; the page renders server-side quickly enough that a spinner is not the primary experience. |
| Error state | A resolution failure routes to SCR-TRK-007 with a stated recovery path — request a new link from the outlet. |
| Offline behaviour | The browser's own offline page applies; the portal caches nothing sensitive and stores no token in local storage. |
| Permission behaviour | The token is the only credential; it grants read access to exactly one order's public projection and nothing else. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Status is text plus icon; the page is fully usable at 200% zoom on a low-end phone. |
| Responsive behaviour | Compact: single column, no horizontal scroll. Medium and above: centred card capped at 640px. |
| Privacy and security notes | Served `noindex` via meta tag and header. The token is high-entropy, stored hashed, expiring, and revocable; it is never the order number and is never derivable from it. Rate limiting and enumeration protection are server-side. |
| Analytics intent | Page view counts and status-stage distribution only. **Never a token value**, never a customer identifier, never an order number. |
| Future implementation step | Step 7 — Customer Tracking and WhatsApp. |

### SCR-TRK-002 — In-Process Status

| Field | Value |
|---|---|
| Platform | Public Tracking Portal (browser, no app install) |
| Persona | P-12 Customer, P-14 Authorized Order Recipient |
| Purpose | Show that an order is being worked on, at what stage, and roughly when it is expected to be ready. |
| Requirement IDs | TRK-002, TRK-004, TRK-012, FR-087, FR-088 |
| Entry points | SCR-TRK-001 when the order is between RECEIVED and QUALITY_CONTROL; a status-change message link. |
| Exit points | SCR-TRK-003 once ready; SCR-TRK-010 Contact Outlet; refresh stays in place. |
| Data displayed | Stage in customer language, the stages already passed with their times, the expected ready time, and the outlet name. |
| Data masked | Internal stage names, operator identity, production notes, rework reasons, and any financial figure are all withheld. |
| Primary action | Refresh the status. |
| Secondary action | Contact the outlet. |
| Empty state | Not applicable; the screen always describes a live stage. |
| Loading state | The already-known stage stays rendered while a refresh runs, so the page never blanks. |
| Error state | A refresh failure keeps the last known state with a stamp and offers a retry — the recovery path is always visible. |
| Offline behaviour | The last rendered page remains readable in the browser; no sensitive data is written to any client-side store. |
| Permission behaviour | The token scopes the view to one order; a REWORK excursion is summarised as "sedang diperiksa ulang" without exposing the defect record. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The stage rail is a semantic list; current stage is announced. |
| Responsive behaviour | Compact: vertical stage list. Medium and above: same list, centred, never a truncating horizontal stepper. |
| Privacy and security notes | Served `noindex`. The projection contains no full address, no internal note, no staff identity beyond necessity, no cost and no margin. |
| Analytics intent | Stage distribution and refresh frequency. Never a token, never an order number. |
| Future implementation step | Step 7 — Customer Tracking and WhatsApp. |

### SCR-TRK-003 — Ready Status

| Field | Value |
|---|---|
| Platform | Public Tracking Portal (browser, no app install) |
| Persona | P-12 Customer, P-14 Authorized Order Recipient |
| Purpose | Tell the customer their laundry is ready, where to collect it, and what remains to be paid. |
| Requirement IDs | TRK-002, TRK-013, UCL-001, UCL-005, FR-086 |
| Entry points | SCR-TRK-001 when the order is READY_FOR_PICKUP; an H+1, H+3, H+7, or H+14 reminder link. |
| Exit points | SCR-TRK-011 Payment Balance; SCR-TRK-010 Contact Outlet; SCR-TRK-004 if a delivery is arranged. |
| Data displayed | "Siap diambil", the first-ready date, outlet name and its public opening hours, the outstanding balance in Rupiah if one exists. |
| Data masked | No shelf location, no internal note, no staff identity, no photograph of the laundry, and no full customer contact detail. |
| Primary action | View collection details for the outlet. |
| Secondary action | Contact the outlet; view the balance. |
| Empty state | Not applicable. |
| Loading state | Ready state renders immediately; the balance figure appears only when server-confirmed. |
| Error state | A balance that cannot be loaded is marked unavailable rather than shown as zero, with a route to contact the outlet. |
| Offline behaviour | The last rendered page remains readable; no balance is cached client-side. |
| Permission behaviour | Token-scoped read only; the portal offers no action that changes order or payment state. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The ready state is announced first and is text plus icon. |
| Responsive behaviour | Compact: single column. Medium and above: centred card with the balance in a distinct block. |
| Privacy and security notes | Served `noindex`. Aging is anchored to the FIRST READY_FOR_PICKUP timestamp and never restarts; the reminder ladder is exactly H+1, H+3, H+7 and H+14 and respects quiet hours and opt-out. Nothing on this surface disposes of, sells, or transfers anything. |
| Analytics intent | Ready-page views and time from ready to first view. Never a token, never a balance, never a name. |
| Future implementation step | Step 7 — Customer Tracking and WhatsApp. |

### SCR-TRK-004 — Delivery Status

| Field | Value |
|---|---|
| Platform | Public Tracking Portal (browser, no app install) |
| Persona | P-12 Customer, P-14 Authorized Order Recipient |
| Purpose | Show the state of a scheduled or in-progress delivery without promising a time the product cannot guarantee. |
| Requirement IDs | TRK-014, DEL-010, DEL-013, DEL-027, FR-102 |
| Entry points | SCR-TRK-001 when the order is SCHEDULED_FOR_DELIVERY or OUT_FOR_DELIVERY; a delivery notification link. |
| Exit points | SCR-TRK-010 Contact Outlet; SCR-TRK-011 Payment Balance; refresh stays in place. |
| Data displayed | Delivery status from the canonical set, the scheduled window, the area of delivery rather than the full address, and the amount to be collected on delivery if any. |
| Data masked | Never the full address, never the courier's phone or full name, never live courier location, and never another stop on the same route. |
| Primary action | Refresh the delivery status. |
| Secondary action | Contact the outlet to reschedule. |
| Empty state | Not applicable; the screen exists only for an order with a delivery. |
| Loading state | The known status stays visible during refresh so the page never blanks. |
| Error state | A FAILED delivery is presented honestly with its recorded reason and a clear instruction to contact the outlet to arrange a new attempt. |
| Offline behaviour | The last rendered page remains readable; nothing about the delivery is cached client-side. |
| Permission behaviour | Token-scoped read only; the portal cannot confirm, reschedule, or cancel a delivery. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Status is text plus icon and is announced on refresh. |
| Responsive behaviour | Compact: single column. Medium and above: centred card capped at 640px. |
| Privacy and security notes | Served `noindex`. **No guaranteed delivery time, no ETA accuracy, and no route optimization is claimed** — the window is a scheduled window and stop ordering is only ever an "usulan rute" internally. |
| Analytics intent | Delivery status distribution and failure-reason categories. Never a token, never an address, never a courier identity. |
| Future implementation step | Step 8 — Pickup and Delivery Operations. |

### SCR-TRK-005 — Expired Token

| Field | Value |
|---|---|
| Platform | Public Tracking Portal (browser, no app install) |
| Persona | P-12 Customer, P-14 Authorized Order Recipient |
| Purpose | Explain that a tracking link has passed its validity window and give a concrete way to get a working one. |
| Requirement IDs | TRK-006, TRK-016, SEC-017, SEC-062, FR-090 |
| Entry points | Any tracking URL whose token has expired. |
| Exit points | SCR-TRK-010 Contact Outlet; no other route is offered. |
| Data displayed | A plain statement that the link is no longer valid, and the instruction to request a new link from the outlet. |
| Data masked | No order number, no customer name, no status, and no outlet-specific detail is revealed to an expired token. |
| Primary action | "Hubungi outlet" — open the contact route. |
| Secondary action | None; there is deliberately nothing else to try. |
| Empty state | Not applicable; this is itself a terminal explanatory state. |
| Loading state | None; the page is rendered directly from the token check with no data fetch behind it. |
| Error state | This screen *is* an error state, and it always carries its recovery path rather than ending in a dead end. |
| Offline behaviour | Static content; readable from the browser cache with no sensitive data behind it. |
| Permission behaviour | An expired token grants nothing and reveals nothing about whether the underlying order exists. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The message is announced on load and focus lands on the contact action. |
| Responsive behaviour | Compact: full-width message. Medium and above: centred card capped at 480px. |
| Privacy and security notes | Served `noindex`. The response is indistinguishable in timing and shape from a revoked or invalid token so that expiry cannot be used to probe which tokens once existed. |
| Analytics intent | Expired-token encounter counts only. **Never a token value**, never an order identity. |
| Future implementation step | Step 7 — Customer Tracking and WhatsApp. |

### SCR-TRK-006 — Revoked Token

| Field | Value |
|---|---|
| Platform | Public Tracking Portal (browser, no app install) |
| Persona | P-12 Customer, P-14 Authorized Order Recipient |
| Purpose | Explain that a link has been withdrawn by the outlet, and route the visitor to a human. |
| Requirement IDs | TRK-007, TRK-017, SEC-017, SEC-063, FR-091 |
| Entry points | Any tracking URL whose token has been revoked server-side. |
| Exit points | SCR-TRK-010 Contact Outlet. |
| Data displayed | A plain statement that the link is no longer active, and the instruction to contact the outlet. |
| Data masked | No order number, no name, no status, and no revocation reason is disclosed to the holder of a revoked link. |
| Primary action | "Hubungi outlet" — open the contact route. |
| Secondary action | None. |
| Empty state | Not applicable. |
| Loading state | None; rendered directly from the token check. |
| Error state | This screen is the error state and always carries a recovery path. |
| Offline behaviour | Static content with nothing sensitive behind it. |
| Permission behaviour | A revoked token grants nothing; revocation takes effect immediately and is not recoverable client-side. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The message is announced on load with focus on the contact action. |
| Responsive behaviour | Compact: full-width message. Medium and above: centred card capped at 480px. |
| Privacy and security notes | Served `noindex`. The response is deliberately identical in shape to expired and invalid so that revocation status cannot be inferred by an outsider. |
| Analytics intent | Revoked-token encounter counts only. Never a token, never an order identity. |
| Future implementation step | Step 7 — Customer Tracking and WhatsApp. |

### SCR-TRK-007 — Invalid Token

| Field | Value |
|---|---|
| Platform | Public Tracking Portal (browser, no app install) |
| Persona | P-12 Customer, P-14 Authorized Order Recipient |
| Purpose | Handle a malformed, mistyped, or fabricated tracking link without confirming or denying that any order exists. |
| Requirement IDs | TRK-008, TRK-018, SEC-017, SEC-064, FR-092 |
| Entry points | A mistyped URL; a truncated link from a copy-paste; an enumeration attempt. |
| Exit points | SCR-TRK-010 Contact Outlet. |
| Data displayed | A plain statement that the link is not recognised, and the instruction to check the link or contact the outlet. |
| Data masked | Everything — this response reveals no information whatsoever about any order, customer, or outlet. |
| Primary action | "Hubungi outlet" — open the contact route. |
| Secondary action | None. |
| Empty state | Not applicable. |
| Loading state | None; the check is a constant-shape response. |
| Error state | This screen is the error state and always carries its recovery path. |
| Offline behaviour | Static content only. |
| Permission behaviour | No credential is present and none is inferred; nothing is granted. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The message is announced and focus lands on the recovery action. |
| Responsive behaviour | Compact: full-width message. Medium and above: centred card capped at 480px. |
| Privacy and security notes | Served `noindex`. Response timing and content are uniform across invalid, expired, and revoked to defeat enumeration; server-side rate limiting applies to every attempt. |
| Analytics intent | Invalid-token attempt rate as an abuse signal. **Never the submitted token value**, never a client identifier beyond what rate limiting requires. |
| Future implementation step | Step 7 — Customer Tracking and WhatsApp. |

### SCR-TRK-008 — Rate Limited

| Field | Value |
|---|---|
| Platform | Public Tracking Portal (browser, no app install) |
| Persona | P-12 Customer, P-14 Authorized Order Recipient |
| Purpose | Tell a legitimate visitor that they have been temporarily slowed down, and tell them exactly when to try again. |
| Requirement IDs | TRK-019, SEC-015, SEC-016, SEC-065, NFR-030 |
| Entry points | Repeated tracking lookups from one client; a detected enumeration pattern; excessive OTP requests. |
| Exit points | Retry after the stated interval; SCR-TRK-010 Contact Outlet. |
| Data displayed | A plain statement that too many attempts were made, the wait duration, and the alternative of contacting the outlet. |
| Data masked | No detail of what was being attempted, and no order or customer information of any kind. |
| Primary action | "Hubungi outlet" — the immediate alternative while the limit applies. |
| Secondary action | Retry once the countdown completes. |
| Empty state | Not applicable. |
| Loading state | None; the response is immediate by design. |
| Error state | This screen is the throttle response and always states a concrete recovery path with a time. |
| Offline behaviour | Static content only. |
| Permission behaviour | Rate limiting is applied server-side and cannot be bypassed by a client; it applies equally to token lookup and OTP issuance. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The wait duration is stated in words and announced, not only rendered as a ticking number. |
| Responsive behaviour | Compact: full-width message. Medium and above: centred card capped at 480px. |
| Privacy and security notes | Served `noindex`. The portal is the most exposed surface in the product and is modelled as such: high-entropy hashed tokens, enumeration protection, and progressive backoff. |
| Analytics intent | Throttle trigger rate as an abuse signal. Never tokens, never full client identifiers. |
| Future implementation step | Step 13 — Security, Performance, Backup, and Recovery. |

### SCR-TRK-009 — OTP Step-Up

| Field | Value |
|---|---|
| Platform | Public Tracking Portal (browser, no app install) |
| Persona | P-12 Customer, P-14 Authorized Order Recipient |
| Purpose | Require a one-time password before revealing anything more sensitive than the baseline public projection. |
| Requirement IDs | TRK-020, TRK-021, SEC-011, SEC-015, SEC-066, FR-089 |
| Entry points | A request on SCR-TRK-011 to see payment detail; a request to nominate an authorized recipient; any tenant-configured sensitive action. |
| Exit points | The requested view on success; SCR-TRK-008 on too many attempts; back to SCR-TRK-001. |
| Data displayed | The masked destination `0812-XXXX-1234`, the OTP field, a resend countdown, and remaining attempt guidance. |
| Data masked | The destination number is only ever shown masked; the OTP is never echoed back after entry. |
| Primary action | "Verifikasi" — submit the code. |
| Secondary action | Resend after the countdown; return to the basic tracking view. |
| Empty state | Submit is disabled until a complete code is entered. |
| Loading state | Inline progress on the button with the field locked so a double submit cannot consume two attempts. |
| Error state | Wrong, expired, and exhausted attempts are three distinct messages, each stating what to do next. |
| Offline behaviour | Step-up requires connectivity and says so; no code is ever validated client-side. |
| Permission behaviour | A successful step-up widens the view only within the same order's scope; it never grants access to another order or to any tenant data. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The countdown is announced; the field is labelled and errors are associated with it. |
| Responsive behaviour | Compact: full-width code entry. Medium and above: centred card capped at 480px. |
| Privacy and security notes | Served `noindex`. The OTP is SECRET — never logged, never in telemetry, never in an event payload, never echoed in a message body. Rate limiting and brute-force protection are server-side. |
| Analytics intent | Step-up success and failure category counts. **Never the OTP, never the token, never the phone number.** |
| Future implementation step | Step 7 — Customer Tracking and WhatsApp. |

### SCR-TRK-010 — Contact Outlet

| Field | Value |
|---|---|
| Platform | Public Tracking Portal (browser, no app install) |
| Persona | P-12 Customer, P-14 Authorized Order Recipient |
| Purpose | Give every portal state a human recovery path by routing the visitor to the outlet's own published contact channel. |
| Requirement IDs | TRK-022, NOT-018, FR-095, NFR-017, FR-018 |
| Entry points | Any tracking screen, including every failure state; a reminder message link. |
| Exit points | An outward WhatsApp or telephone link; back to the originating tracking screen. |
| Data displayed | Outlet name "Outlet Melati", its published public contact channel, its public opening hours, and the order number if the visitor holds a valid token. |
| Data masked | No employee's personal contact detail, no internal extension, no staff roster, and no customer data from any other order. |
| Primary action | Open the outlet's published contact channel. |
| Secondary action | Return to the tracking view. |
| Empty state | An outlet with no published channel shows its opening hours and its public address only, with an honest statement that no direct channel is published. |
| Loading state | Contact details render server-side with the page; no separate fetch is needed. |
| Error state | If outlet details cannot be resolved, the page states so and does not fabricate a fallback number. |
| Offline behaviour | Static content; the outward link fails to the browser's own handling. |
| Permission behaviour | Outlet contact information is PUBLIC by the tenant's own choice; nothing here is token-gated beyond the order number. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. Contact details are selectable text, and the outward link states where it leads. |
| Responsive behaviour | Compact: full-width. Medium and above: centred card capped at 480px. |
| Privacy and security notes | Served `noindex`. Only outlet-published PUBLIC contact data appears; no employee identity beyond operational necessity is ever exposed. |
| Analytics intent | Contact-route usage by originating screen. Never tokens, never order numbers, never contact detail. |
| Future implementation step | Step 7 — Customer Tracking and WhatsApp. |

### SCR-TRK-011 — Payment Balance

| Field | Value |
|---|---|
| Platform | Public Tracking Portal (browser, no app install) |
| Persona | P-12 Customer, P-13 Corporate Customer Contact |
| Purpose | Show the outstanding balance on one order so the customer knows what to bring, without exposing the ledger. |
| Requirement IDs | TRK-023, FR-062, FR-063, FIN-005, FIN-011 |
| Entry points | SCR-TRK-003 Ready Status; SCR-TRK-004 Delivery Status; a payment reminder link. |
| Exit points | SCR-TRK-009 OTP Step-Up for payment detail; SCR-TRK-010 Contact Outlet; back to the tracking view. |
| Data displayed | Order number `AL-2026-000123`, total `Rp79.000`, amount already paid `Rp25.000`, and the remaining balance. |
| Data masked | No line-item breakdown, no discount detail, no cost, no margin, no payment method reference, and no other order's balance. |
| Primary action | "Lihat rincian pembayaran" — which requires an OTP step-up before anything further is revealed. |
| Secondary action | Contact the outlet to arrange payment. |
| Empty state | A fully settled order shows "Lunas" with the settling date, which is a complete and honest answer. |
| Loading state | Figures are rendered only from server-confirmed values; a provisional amount is never displayed. |
| Error state | If the balance cannot be resolved, the screen says so and routes to the outlet rather than displaying a zero. |
| Offline behaviour | The last rendered page remains in the browser; no balance is stored client-side. |
| Permission behaviour | Read-only. **The portal offers no action that marks an order paid** — an order is never marked paid on a client claim, and this surface cannot change payment state at all. |
| Accessibility notes | DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED. The remaining balance is announced as a full Rupiah value and is the highest-contrast element. |
| Responsive behaviour | Compact: single column. Medium and above: centred card capped at 640px. |
| Privacy and security notes | Served `noindex`. Integer Rupiah only. The projection carries no full phone, no full address, no internal note, no employee data, and no audit data. |
| Analytics intent | Balance-view counts and step-up request rate. **Never amounts, never tokens, never order numbers.** |
| Future implementation step | Step 7 — Customer Tracking and WhatsApp. |

---

## Status

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| Every screen listed in this inventory (89 screens) | **NOT IMPLEMENTED** |
| Flutter workspace | **ABSENT** |
| Backend runtime | **ABSENT** |
| Application CI | **NOT APPLICABLE** |
| UAT | **NOT STARTED** |

**No screen in this document has been built, rendered, compiled, run, or tested.** This inventory is
documentation. Every entry describes an obligation that a future roadmap Step must satisfy; not one of
them describes an achievement. A screen entry is not a screen, an accessibility note is not an
accessibility test, and an acceptance-shaped sentence in this file is not a passed test.

Accessibility statements throughout read **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET
RUNTIME-TESTED**, and that wording is exact and deliberate.

`GO` for Step 2 is conferred by the repository owner against exact-SHA evidence. It is never
self-declared by an agent, and it is not claimed anywhere in this document.

Related Step 2 documents: [UX State Model](./UX_STATE_MODEL.md) ·
[Offline and Sync UX](./OFFLINE_AND_SYNC_UX.md) · [Critical Journeys](./CRITICAL_JOURNEYS.md) ·
[UX Acceptance Criteria](./UX_ACCEPTANCE_CRITERIA.md) · [Wireframes](./wireframes/README.md) ·
[Role Navigation Matrix](./information-architecture/ROLE_NAVIGATION_MATRIX.md)
