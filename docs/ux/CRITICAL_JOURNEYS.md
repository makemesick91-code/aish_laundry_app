# Critical Journeys — Aish Laundry App

Step 2 — Design System and UX Foundation. Derived artefact. Canonical source: `docs/MASTER_SOURCE.md`.

## Purpose

To record the thirty-two critical end-to-end journeys that the Aish Laundry App must support, so that
Step 2 screen, state, and component work is anchored to actual user intent rather than to screen
inventories in isolation. This document is the index and the specification table; the per-journey
diagrams live in the cluster files under `journeys/`.

A journey described here is an **obligation**, never an achievement. Nothing in this document has been
built, executed, or tested.

## Status block

| Item | Status |
|---|---|
| Step 2 — Design System and UX Foundation | **IN PROGRESS** |
| All thirty-two journeys | **NOT IMPLEMENTED** |
| Backend runtime | **ABSENT** |
| Flutter workspace | **ABSENT** |
| Application CI | **NOT APPLICABLE** |
| UAT | **NOT STARTED** |
| Accessibility conformance | **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED** |

`GO` is conferred by the repository owner and is never self-declared.

## How to read this document

- Each journey has a permanent identifier `JRN-001` … `JRN-032`. Identifiers are never reused.
- Each journey is specified by a fixed thirteen-row table: Trigger, Actor, Precondition, Happy path,
  Alternative path, Error path, Offline path, Security boundary, Recovery, Completion criteria, Linked
  requirements, Linked screens, Linked components.
- **Client-side menu visibility is not authorization.** Backend authorization is authoritative and is
  delivered from Step 3 onward. Any "the button is hidden" statement in this document is a usability
  affordance only.
- All example data is fictional: customer "Budi Santoso", staff "Siti Rahmawati", masked phone
  `0812-XXXX-1234`, order `AL-2026-000123`, outlet "Outlet Cempaka", tenant "Laundry Bersih Sejahtera".
- Money is integer Rupiah, displayed as `Rp79.000`. Weight is displayed as `1,5 kg`. Times are 24-hour,
  e.g. `14:30`, displayed in outlet timezone and stored in UTC.
- Route ordering is a **suggestion** — "usulan rute". No route optimization and no guaranteed delivery
  time is claimed anywhere.
- Aging for unclaimed laundry anchors to the **first** `READY_FOR_PICKUP` timestamp and never restarts.
  The ladder is exactly H+1, H+3, H+7 (plus a follow-up task) and H+14 (escalation to manager/owner).
  There is no automatic disposal, sale, auction, donation, or ownership transfer, ever.
- A retry always reuses the **same** `client_reference`. A retry must never create a duplicate order or a
  duplicate payment. A conflict is never silently overwritten. A notification failure never changes
  business state.

## Journey index

| Journey ID | Name | Actor | Cluster file | Primary surface |
|---|---|---|---|---|
| JRN-001 | Customer opens tracking link | Customer (P-12) | [`journeys/CUSTOMER_SELF_SERVICE_JOURNEYS.md`](journeys/CUSTOMER_SELF_SERVICE_JOURNEYS.md) | Portal Tracking Publik |
| JRN-002 | Customer requests pickup | Customer (P-12) | [`journeys/CUSTOMER_SELF_SERVICE_JOURNEYS.md`](journeys/CUSTOMER_SELF_SERVICE_JOURNEYS.md) | Customer Android |
| JRN-003 | Customer sees unpaid balance | Customer (P-12) | [`journeys/CUSTOMER_SELF_SERVICE_JOURNEYS.md`](journeys/CUSTOMER_SELF_SERVICE_JOURNEYS.md) | Customer Android |
| JRN-004 | Cashier creates kiloan order | Cashier (P-06) | [`journeys/COUNTER_AND_POS_JOURNEYS.md`](journeys/COUNTER_AND_POS_JOURNEYS.md) | Ops Android |
| JRN-005 | Cashier creates mixed order | Cashier (P-06) | [`journeys/COUNTER_AND_POS_JOURNEYS.md`](journeys/COUNTER_AND_POS_JOURNEYS.md) | Ops Android |
| JRN-006 | Cashier records condition and photo | Cashier (P-06) | [`journeys/COUNTER_AND_POS_JOURNEYS.md`](journeys/COUNTER_AND_POS_JOURNEYS.md) | Ops Android |
| JRN-007 | Cashier takes partial payment | Cashier (P-06) | [`journeys/COUNTER_AND_POS_JOURNEYS.md`](journeys/COUNTER_AND_POS_JOURNEYS.md) | Ops Android |
| JRN-008 | Cashier works offline | Cashier (P-06) | [`journeys/OFFLINE_AND_SYNC_JOURNEYS.md`](journeys/OFFLINE_AND_SYNC_JOURNEYS.md) | Ops Android |
| JRN-009 | Duplicate submission prevented | Cashier (P-06) | [`journeys/OFFLINE_AND_SYNC_JOURNEYS.md`](journeys/OFFLINE_AND_SYNC_JOURNEYS.md) | Ops Android |
| JRN-010 | Operator processes production queue | Production Operator (P-07) | [`journeys/PRODUCTION_AND_QC_JOURNEYS.md`](journeys/PRODUCTION_AND_QC_JOURNEYS.md) | Ops Android |
| JRN-011 | QC fails and creates rework | Quality Control (P-08) | [`journeys/PRODUCTION_AND_QC_JOURNEYS.md`](journeys/PRODUCTION_AND_QC_JOURNEYS.md) | Ops Android |
| JRN-012 | Order becomes ready for pickup | Production Operator (P-07) | [`journeys/PRODUCTION_AND_QC_JOURNEYS.md`](journeys/PRODUCTION_AND_QC_JOURNEYS.md) | Ops Android |
| JRN-013 | Reminder H+1 | Outlet Manager (P-05) | [`journeys/UNCLAIMED_LAUNDRY_JOURNEYS.md`](journeys/UNCLAIMED_LAUNDRY_JOURNEYS.md) | Console Web |
| JRN-014 | Reminder H+3 | Outlet Manager (P-05) | [`journeys/UNCLAIMED_LAUNDRY_JOURNEYS.md`](journeys/UNCLAIMED_LAUNDRY_JOURNEYS.md) | Console Web |
| JRN-015 | Reminder H+7 | Outlet Manager (P-05) | [`journeys/UNCLAIMED_LAUNDRY_JOURNEYS.md`](journeys/UNCLAIMED_LAUNDRY_JOURNEYS.md) | Console Web |
| JRN-016 | Cashier follows up unclaimed laundry | Cashier (P-06) | [`journeys/UNCLAIMED_LAUNDRY_JOURNEYS.md`](journeys/UNCLAIMED_LAUNDRY_JOURNEYS.md) | Ops Android |
| JRN-017 | Manager converts pickup to delivery | Outlet Manager (P-05) | [`journeys/PICKUP_AND_DELIVERY_JOURNEYS.md`](journeys/PICKUP_AND_DELIVERY_JOURNEYS.md) | Console Web |
| JRN-018 | Manager assigns courier | Outlet Manager (P-05) | [`journeys/PICKUP_AND_DELIVERY_JOURNEYS.md`](journeys/PICKUP_AND_DELIVERY_JOURNEYS.md) | Console Web |
| JRN-019 | External courier uses guest job | External Courier (P-10) | [`journeys/PICKUP_AND_DELIVERY_JOURNEYS.md`](journeys/PICKUP_AND_DELIVERY_JOURNEYS.md) | Guest job link |
| JRN-020 | Courier records failed attempt | Courier Internal (P-09) | [`journeys/PICKUP_AND_DELIVERY_JOURNEYS.md`](journeys/PICKUP_AND_DELIVERY_JOURNEYS.md) | Ops Android |
| JRN-021 | Courier records proof | Courier Internal (P-09) | [`journeys/PICKUP_AND_DELIVERY_JOURNEYS.md`](journeys/PICKUP_AND_DELIVERY_JOURNEYS.md) | Ops Android |
| JRN-022 | Courier reconciles COD | Courier Internal (P-09) | [`journeys/PICKUP_AND_DELIVERY_JOURNEYS.md`](journeys/PICKUP_AND_DELIVERY_JOURNEYS.md) | Ops Android |
| JRN-023 | Owner switches tenant | Tenant Owner (P-03) | [`journeys/TENANT_AND_PORTFOLIO_JOURNEYS.md`](journeys/TENANT_AND_PORTFOLIO_JOURNEYS.md) | Console Web |
| JRN-024 | Owner views portfolio | Tenant Owner (P-03) | [`journeys/TENANT_AND_PORTFOLIO_JOURNEYS.md`](journeys/TENANT_AND_PORTFOLIO_JOURNEYS.md) | Console Web |
| JRN-025 | Finance reconciles receivable | Finance (P-11) | [`journeys/TENANT_AND_PORTFOLIO_JOURNEYS.md`](journeys/TENANT_AND_PORTFOLIO_JOURNEYS.md) | Console Web |
| JRN-026 | Membership revoked | Tenant Admin | [`journeys/SECURITY_AND_FAILURE_JOURNEYS.md`](journeys/SECURITY_AND_FAILURE_JOURNEYS.md) | Ops Android |
| JRN-027 | Session expired | Cashier (P-06) | [`journeys/SECURITY_AND_FAILURE_JOURNEYS.md`](journeys/SECURITY_AND_FAILURE_JOURNEYS.md) | Ops Android |
| JRN-028 | Device revoked | Outlet Manager (P-05) | [`journeys/SECURITY_AND_FAILURE_JOURNEYS.md`](journeys/SECURITY_AND_FAILURE_JOURNEYS.md) | Ops Android |
| JRN-029 | Notification provider fails | Outlet Manager (P-05) | [`journeys/SECURITY_AND_FAILURE_JOURNEYS.md`](journeys/SECURITY_AND_FAILURE_JOURNEYS.md) | Console Web |
| JRN-030 | Tracking token expired | Customer (P-12) | [`journeys/SECURITY_AND_FAILURE_JOURNEYS.md`](journeys/SECURITY_AND_FAILURE_JOURNEYS.md) | Portal Tracking Publik |
| JRN-031 | Tracking token revoked | Customer (P-12) | [`journeys/SECURITY_AND_FAILURE_JOURNEYS.md`](journeys/SECURITY_AND_FAILURE_JOURNEYS.md) | Portal Tracking Publik |
| JRN-032 | Sync conflict requires user action | Cashier (P-06) | [`journeys/OFFLINE_AND_SYNC_JOURNEYS.md`](journeys/OFFLINE_AND_SYNC_JOURNEYS.md) | Ops Android |

## Journey specifications

### JRN-001 — Customer opens tracking link

| Field | Specification |
|---|---|
| Trigger | Budi Santoso taps a tracking link received for order `AL-2026-000123`. |
| Actor | Customer (P-12). Unauthenticated visitor holding a token. |
| Precondition | A high-entropy tracking token was issued for the order; only its hash is stored server-side; the token is unexpired and unrevoked. |
| Happy path | 1) Visitor opens the link. 2) Backend validates the token hash and rate-limit budget. 3) Public projection for the order is assembled. 4) `SCR-TRK-001` renders order status, masked identity `0812-XXXX-1234`, and the timeline. 5) Visitor reads status `WASHING` with an estimated-ready note that is explicitly not a guarantee. |
| Alternative path | Order already at `READY_FOR_PICKUP` renders `SCR-TRK-003` with pickup instructions and outlet hours in outlet timezone. |
| Error path | Malformed or unknown token renders a generic not-found result that does not distinguish "wrong token" from "no such order". Excess attempts render `SCR-TRK-008` with `UXS-017`. |
| Offline path | Not applicable — the public portal is online-only. Loss of connectivity renders `UXS-003` with a retry affordance; no data is cached to the device. |
| Security boundary | Public internet to backend. Token is never the order number and never derivable from it. The projection never contains a full address, internal notes, cost, or margin. Page is `noindex`. Enumeration protection and rate limiting apply. |
| Recovery | Visitor retries after the rate-limit window, or requests a fresh link from Outlet Cempaka. A new link issues a new token; the old token is not resurrected. |
| Completion criteria | The correct masked public projection for exactly one order is displayed, with no personal data beyond the masked set and no cross-tenant leakage. |
| Linked requirements | TRK-001, TRK-002, TRK-004, TRK-009, TRK-014, SEC-014, SEC-022, SEC-041, NFR-006, NFR-018, TEN-004 |
| Linked screens | [`SCR-TRK-001`](SCREEN_INVENTORY.md), [`SCR-TRK-003`](SCREEN_INVENTORY.md), [`SCR-TRK-008`](SCREEN_INVENTORY.md) |
| Linked components | `StatusChip`, `TimelineStepper`, `MaskedIdentityLabel`, `PrimaryActionBar` |

### JRN-002 — Customer requests pickup

| Field | Specification |
|---|---|
| Trigger | Budi Santoso taps "Minta Penjemputan" on the Customer Android home screen. |
| Actor | Customer (P-12), authenticated by phone and OTP. |
| Precondition | Customer has at least one saved address within a serviced zone of Laundry Bersih Sejahtera; pickup is enabled for Outlet Cempaka. |
| Happy path | 1) Customer opens `SCR-CUS-009`. 2) Customer selects a saved address. 3) Customer selects a date and a time window such as `14:30`–`16:30`. 4) Customer adds an optional note. 5) Customer submits with a `client_reference` generated once. 6) Backend creates the request at `REQUESTED`. 7) Confirmation shows the request and its expected confirmation step. |
| Alternative path | Address outside the serviced zone offers the nearest serviced zone and outlet drop-off instead of failing silently. Customer may add a new address before continuing. |
| Error path | Submission failure renders `UXS-003` with the reason and a retry that reuses the same `client_reference`. Subscription entitlement limits render `UXS-015` without exposing another tenant's configuration. |
| Offline path | Request is queued locally with its `client_reference` and shown as `UXS-005`. It syncs when connectivity returns; a retry never creates a second request. |
| Security boundary | Customer identity to tenant boundary. The customer may only create requests against their own profile within one tenant. A customer-supplied tenant identifier is never authorization proof. |
| Recovery | Customer may cancel a request in `REQUESTED` or `CONFIRMED`, or reschedule. A cancelled request keeps its history and reason. |
| Completion criteria | Exactly one pickup request exists at status `REQUESTED` with a recorded address, window, and `client_reference`. |
| Linked requirements | FR-100, FR-101, FR-102, FR-021, FR-024, DEL-001, DEL-002, DEL-004, OFF-003, OFF-007, SUB-011, TEN-008 |
| Linked screens | [`SCR-CUS-004`](SCREEN_INVENTORY.md), [`SCR-CUS-009`](SCREEN_INVENTORY.md), [`SCR-CUS-011`](SCREEN_INVENTORY.md) |
| Linked components | `PrimaryActionBar`, `SyncBadge`, `ReasonCodePicker`, `StatusChip` |

### JRN-003 — Customer sees unpaid balance

| Field | Specification |
|---|---|
| Trigger | Budi Santoso opens order `AL-2026-000123` from the active orders list. |
| Actor | Customer (P-12), authenticated. |
| Precondition | The order carries a partial payment; the outstanding balance is authoritative on the server as integer Rupiah. |
| Happy path | 1) Customer opens `SCR-CUS-005`. 2) Customer selects the order. 3) `SCR-CUS-006` renders the order. 4) `SCR-CUS-008` renders the payment summary: total `Rp79.000`, paid `Rp40.000`, outstanding `Rp39.000`. 5) Customer reads the outstanding amount with its payment instruction for Outlet Cempaka. |
| Alternative path | A fully paid order renders the same summary with an outstanding value of `Rp0` and no payment call to action. |
| Error path | If the balance cannot be fetched, `UXS-003` is shown and no amount is displayed. A stale cached figure is never presented as current; if shown at all it carries `UXS-020`. |
| Offline path | The last synced summary may be shown with `UXS-020` Stale Data and an explicit "terakhir diperbarui" timestamp in outlet timezone. No payment may be initiated while stale. |
| Security boundary | Customer to tenant boundary. The customer sees only their own orders in one tenant. The same phone number in another tenant is an unrelated profile and is never merged. |
| Recovery | Customer pulls to refresh; on reconnect the server figure replaces the local copy, since the server is the source of truth. |
| Completion criteria | The displayed outstanding balance equals the server value in integer Rupiah, or is clearly labelled stale. |
| Linked requirements | FR-061, FR-064, FR-065, FR-026, FIN-001, FIN-004, FIN-011, FIN-019, TEN-009, NFR-011 |
| Linked screens | [`SCR-CUS-005`](SCREEN_INVENTORY.md), [`SCR-CUS-006`](SCREEN_INVENTORY.md), [`SCR-CUS-008`](SCREEN_INVENTORY.md) |
| Linked components | `MoneyField`, `StatusChip`, `PrimaryActionBar`, `StaleDataBanner` |

### JRN-004 — Cashier creates kiloan order

| Field | Specification |
|---|---|
| Trigger | Siti Rahmawati receives a bag of laundry at the counter of Outlet Cempaka. |
| Actor | Cashier (P-06). |
| Precondition | Cashier is authenticated, has an active membership in Laundry Bersih Sejahtera, and Outlet Cempaka is active. A kiloan service exists in the catalog with a current price. |
| Happy path | 1) Cashier opens `SCR-OPS-007`. 2) Cashier finds or creates the customer profile. 3) Cashier selects the kiloan service. 4) Cashier enters weight `1,5 kg` on `SCR-OPS-011`. 5) The server-authoritative total is computed and displayed as `Rp79.000`. 6) Cashier confirms; the order is created at `RECEIVED` with a price snapshot and a `client_reference`. 7) `SCR-OPS-018` renders the receipt. |
| Alternative path | Cashier saves the order as `DRAFT` when the customer is still deciding, and resumes it later without re-entering the weight. |
| Error path | Weight below the service minimum blocks submission with a recovery instruction, not just a code. A catalog price change mid-entry is surfaced explicitly; the order captures the price applying at creation. |
| Offline path | Order is queued with its `client_reference` and displayed as `UXS-005` in `SCR-OPS-020`. The receipt is marked as pending confirmation until synced. |
| Security boundary | Cashier to tenant and outlet boundary. Menu visibility is not authorization; the backend verifies membership and permission on the create call from Step 3 onward. |
| Recovery | A failed submission retries with the identical `client_reference`. The cashier may reprint the receipt from `SCR-OPS-018` without creating a second order. |
| Completion criteria | Exactly one order exists at `RECEIVED`, tenant-scoped, with an immutable price snapshot in integer Rupiah. |
| Linked requirements | FR-048, FR-049, FR-050, FR-052, FR-031, FR-034, FR-021, FIN-002, FIN-009, FIN-012, OFF-003, TEN-007, NFR-004 |
| Linked screens | [`SCR-OPS-006`](SCREEN_INVENTORY.md), [`SCR-OPS-007`](SCREEN_INVENTORY.md), [`SCR-OPS-011`](SCREEN_INVENTORY.md), [`SCR-OPS-018`](SCREEN_INVENTORY.md) |
| Linked components | `PrimaryActionBar`, `MoneyField`, `WeightField`, `StatusChip`, `SyncBadge` |

### JRN-005 — Cashier creates mixed order

| Field | Specification |
|---|---|
| Trigger | Budi Santoso brings both kiloan laundry and two satuan items to the counter. |
| Actor | Cashier (P-06). |
| Precondition | The catalog carries both a weight-based and a per-item service with current prices. |
| Happy path | 1) Cashier opens `SCR-OPS-007`. 2) Cashier adds a kiloan line and enters `1,5 kg`. 3) Cashier adds two satuan lines with quantities. 4) Each line captures its own price snapshot. 5) The server computes the order total. 6) Cashier confirms and the order is created at `RECEIVED`. 7) `SCR-OPS-018` renders one receipt covering all lines. |
| Alternative path | Cashier applies an authorised discount, which requires a permission and a recorded reason and is shown as a distinct line rather than folded into the price. |
| Error path | A removed or inactive catalog item blocks that line only; other lines remain intact and the cashier is told which line needs attention. |
| Offline path | The complete multi-line order is queued as one unit under one `client_reference`; lines are never split across queue entries. |
| Security boundary | Cashier to tenant and outlet boundary. Discount permission is verified server-side; hiding the discount control client-side is not authorization. |
| Recovery | Cashier edits the draft before confirmation. After confirmation, corrections follow the reversal and adjustment path, never a silent edit. |
| Completion criteria | One order at `RECEIVED` containing all lines, each with its own price snapshot, and a single server-computed total. |
| Linked requirements | FR-048, FR-051, FR-053, FR-054, FR-032, FR-035, FR-037, FIN-002, FIN-009, FIN-015, OFF-003, TEN-007 |
| Linked screens | [`SCR-OPS-007`](SCREEN_INVENTORY.md), [`SCR-OPS-011`](SCREEN_INVENTORY.md), [`SCR-OPS-018`](SCREEN_INVENTORY.md) |
| Linked components | `MoneyField`, `WeightField`, `LineItemList`, `ReasonCodePicker`, `PrimaryActionBar` |

### JRN-006 — Cashier records condition and photo

| Field | Specification |
|---|---|
| Trigger | Siti Rahmawati notices a stain and a loose button while intake is in progress. |
| Actor | Cashier (P-06). |
| Precondition | An order in `DRAFT` or `RECEIVED` exists; the device camera is available; the tenant has evidence capture enabled. |
| Happy path | 1) Cashier opens `SCR-OPS-013`. 2) Cashier selects a condition reason code. 3) Cashier captures one or more photographs. 4) Cashier adds a short note. 5) Evidence is attached to the order line. 6) Customer acknowledges the recorded condition at the counter. 7) The receipt notes that condition evidence was recorded. |
| Alternative path | Cashier records a condition with a reason code but no photograph when the customer declines photography; the absence is recorded rather than implied. |
| Error path | Camera permission denied renders `UXS-010` with instructions; the order still proceeds with a text-only condition note. Upload failure keeps the evidence queued and visible, never dropped. |
| Offline path | Photographs are stored encrypted on device, queued under the order's `client_reference`, and uploaded on reconnect. Queue survives app kill. |
| Security boundary | Device to backend to private object storage. Laundry photographs are RESTRICTED. They are never public, never indexed, and are served only through signed expiring URLs. They never appear on the public tracking portal. |
| Recovery | Cashier may add further evidence before the order leaves `RECEIVED`. A failed upload is retried under bounded backoff and surfaced for human attention. |
| Completion criteria | Every recorded condition carries a reason code, an actor, and a timestamp; every attached photograph is stored privately and tenant-scoped. |
| Linked requirements | FR-055, FR-056, FR-022, SEC-024, SEC-030, SEC-033, SEC-047, TEN-012, OFF-009, NFR-021 |
| Linked screens | [`SCR-OPS-007`](SCREEN_INVENTORY.md), [`SCR-OPS-013`](SCREEN_INVENTORY.md) |
| Linked components | `ProofCaptureSheet`, `ReasonCodePicker`, `SyncBadge`, `PermissionDeniedPanel` |

### JRN-007 — Cashier takes partial payment

| Field | Specification |
|---|---|
| Trigger | Budi Santoso pays `Rp40.000` of an `Rp79.000` order at intake and will settle the rest on collection. |
| Actor | Cashier (P-06). |
| Precondition | An order exists at `RECEIVED` with a server-authoritative total; partial payment is permitted by tenant policy. |
| Happy path | 1) Cashier opens `SCR-OPS-016`. 2) Cashier chooses partial payment and continues to `SCR-OPS-017`. 3) Cashier enters `Rp40.000` and the cash method. 4) A `client_reference` is generated once for this payment. 5) The server records the payment idempotently and recomputes the outstanding balance as `Rp39.000`. 6) `SCR-OPS-018` prints a receipt showing paid and outstanding amounts. |
| Alternative path | Customer pays the full amount instead; the same flow records a single full payment and the outstanding balance becomes `Rp0`. |
| Error path | An amount exceeding the outstanding balance is rejected before submission with a plain explanation. A printer failure renders `SCR-OPS-019`; the payment is already recorded and is never re-submitted to obtain a printout. |
| Offline path | The payment is queued under its `client_reference` as `UXS-005`. The financial queue is never cleared by cache clearing, logout, or upgrade. |
| Security boundary | Cashier to tenant, outlet, and financial boundary. An order is never marked paid on a client claim. Totals are authoritative on the server; the client display is presentational. |
| Recovery | Retry reuses the same `client_reference` and returns the original payment rather than creating a second one. Corrections use reversal or adjustment entries with actor, reason, and timestamp. |
| Completion criteria | Exactly one payment of `Rp40.000` exists, the outstanding balance is `Rp39.000`, and both are integer Rupiah. |
| Linked requirements | FR-061, FR-062, FR-063, FR-066, FR-069, FIN-003, FIN-005, FIN-007, FIN-013, FIN-020, OFF-004, OFF-012, TEN-009 |
| Linked screens | [`SCR-OPS-016`](SCREEN_INVENTORY.md), [`SCR-OPS-017`](SCREEN_INVENTORY.md), [`SCR-OPS-018`](SCREEN_INVENTORY.md), [`SCR-OPS-019`](SCREEN_INVENTORY.md) |
| Linked components | `MoneyField`, `PaymentMethodPicker`, `SyncBadge`, `PrimaryActionBar`, `StatusChip` |

### JRN-008 — Cashier works offline

| Field | Specification |
|---|---|
| Trigger | Connectivity is lost at Outlet Cempaka during the morning rush. |
| Actor | Cashier (P-06). |
| Precondition | The cashier is already authenticated on the device with a valid session and a selected tenant and outlet. |
| Happy path | 1) The app detects loss of connectivity and shows `UXS-004` persistently. 2) Cashier continues creating orders and taking payments. 3) Each operation is persisted locally with its own `client_reference`. 4) `SCR-OPS-020` lists pending operations with `UXS-005`. 5) Connectivity returns and the queue drains in dependency order, showing `UXS-006`. 6) Each synced operation shows `UXS-007`. |
| Alternative path | Partial connectivity drains some operations and leaves others pending; the queue shows a mixed state rather than an aggregate "all good". |
| Error path | A permanently failing operation shows `UXS-008` with its reason and stays visible and actionable. It is never silently dropped. |
| Offline path | This journey is the offline path. Queue ordering is respected: an order is created before its payment, and a payment whose order failed does not jump ahead. |
| Security boundary | Device storage boundary. Local data is separated per tenant and per user, and is encrypted. A tenant or user switch must not expose the previous context's cached data. |
| Recovery | Retries use bounded exponential backoff and always reuse the original `client_reference`. Removing a queued financial operation requires an explicit, permissioned, audited action. |
| Completion criteria | Every queued operation reaches `UXS-007` or `UXS-008`; no operation is lost; no duplicate order or payment exists. |
| Linked requirements | FR-059, FR-079, FR-107, OFF-001, OFF-002, OFF-003, OFF-005, OFF-010, OFF-014, OFF-018, SEC-026, TEN-014, NFR-013 |
| Linked screens | [`SCR-OPS-006`](SCREEN_INVENTORY.md), [`SCR-OPS-007`](SCREEN_INVENTORY.md), [`SCR-OPS-020`](SCREEN_INVENTORY.md) |
| Linked components | `OfflineQueueList`, `SyncBadge`, `OfflineBanner`, `StatusChip`, `PrimaryActionBar` |

### JRN-009 — Duplicate submission prevented

| Field | Specification |
|---|---|
| Trigger | Siti Rahmawati taps confirm twice on a slow connection, then the app restarts mid-submit. |
| Actor | Cashier (P-06). |
| Precondition | An operation carrying a persisted `client_reference` is in flight or queued. |
| Happy path | 1) The first submission reaches the server and creates the record. 2) The second submission carries the identical `client_reference`. 3) The server recognises the reference and returns the original result instead of creating a second record. 4) The app reconciles to the server result. 5) `SCR-OPS-020` shows one operation at `UXS-007`. 6) One order and one payment exist. |
| Alternative path | The app is killed before any response is received; on relaunch the persisted queue entry replays with the same reference and the server responds with the original result. |
| Error path | If the server cannot be reached at all, the operation remains queued at `UXS-005`. The client never regenerates the reference to "force" a fresh attempt. |
| Offline path | Identical: the reference is generated once, persisted with the operation, and reused on every retry regardless of how long the device stays offline. |
| Security boundary | Client to server idempotency contract. Idempotency is a server obligation, not a client convention; the server is the final arbiter of what was created. |
| Recovery | The cashier sees exactly one record. Where the client and server disagree, the divergence is surfaced rather than resolved silently. |
| Completion criteria | Retry produces exactly one order and exactly one payment. A duplicate would be an automatic NO-GO. |
| Linked requirements | FR-059, FR-063, OFF-001, OFF-004, OFF-011, OFF-013, OFF-020, FIN-003, FIN-014, NFR-014 |
| Linked screens | [`SCR-OPS-007`](SCREEN_INVENTORY.md), [`SCR-OPS-016`](SCREEN_INVENTORY.md), [`SCR-OPS-020`](SCREEN_INVENTORY.md) |
| Linked components | `OfflineQueueList`, `SyncBadge`, `MoneyField`, `StatusChip` |

### JRN-010 — Operator processes production queue

| Field | Specification |
|---|---|
| Trigger | The production operator starts a shift and opens the outlet production queue. |
| Actor | Production Operator (P-07). |
| Precondition | Orders exist at `AWAITING_PROCESS` for Outlet Cempaka; the operator holds a membership permitting production transitions. |
| Happy path | 1) Operator opens `SCR-OPS-022`. 2) Operator selects order `AL-2026-000123`. 3) Operator transitions it to `SORTING`. 4) Operator advances it through `WASHING`, then `DRYING`, then `FINISHING`. 5) Each transition records actor, timestamp, and outlet. 6) The order moves to `QUALITY_CONTROL`. |
| Alternative path | Operator batches several orders through one stage together; each order still records its own transition entries rather than sharing one. |
| Error path | A transition not enumerated by the state machine is rejected and changes nothing. A damaged item is raised as `ISSUE` with a reason and an owner, which is a real state and not an error screen. |
| Offline path | Transitions are queued with their `client_reference` and replay idempotently. A replayed transition that already applied is a no-op, not a second application. |
| Security boundary | Operator to tenant and outlet boundary. Transitions are server-side and authorised; there is no generic client-controlled "set status" operation. |
| Recovery | An incorrect transition is corrected through a documented corrective path with permission, reason, and audit — never a casual edit. |
| Completion criteria | The order reaches `QUALITY_CONTROL` through enumerated transitions only, each audited. |
| Linked requirements | FR-071, FR-072, FR-073, FR-074, FR-076, FR-078, FR-080, OFF-006, TEN-010, SEC-009, NFR-016 |
| Linked screens | [`SCR-OPS-006`](SCREEN_INVENTORY.md), [`SCR-OPS-022`](SCREEN_INVENTORY.md) |
| Linked components | `ProductionQueueList`, `StatusChip`, `PrimaryActionBar`, `ReasonCodePicker`, `SyncBadge` |

### JRN-011 — QC fails and creates rework

| Field | Specification |
|---|---|
| Trigger | Quality control inspects order `AL-2026-000123` and finds a remaining stain. |
| Actor | Quality Control (P-08). |
| Precondition | The order is at `QUALITY_CONTROL` with a QC record at `PENDING`. |
| Happy path | 1) QC opens `SCR-OPS-026`. 2) QC inspects and selects `FAILED_REWORK_REQUIRED`. 3) QC selects a reason code and optionally captures evidence. 4) The order transitions to `REWORK`. 5) `SCR-OPS-027` shows the rework task with its reason. 6) Production repeats the required stage and the order returns to `QUALITY_CONTROL`. |
| Alternative path | QC records `WAIVED_WITH_AUTHORIZATION`, which requires an explicit permission, a recorded reason, and an audit entry. A silent waiver is a defect. |
| Error path | QC attempting a waiver without the permission renders `UXS-010`; the QC record stays `PENDING` and nothing is written. |
| Offline path | The QC outcome is queued under its `client_reference`; evidence photographs upload separately and remain visible until uploaded. |
| Security boundary | QC to tenant and outlet boundary. The waiver permission is verified server-side. QC evidence is RESTRICTED and private. |
| Recovery | A second QC pass may set `PASSED`. If the order later reaches `READY_FOR_PICKUP` again, the original first-ready timestamp is retained and the aging clock does not restart. |
| Completion criteria | The QC record carries one of the four canonical statuses with an actor, reason where required, and audit entry. |
| Linked requirements | FR-081, FR-082, FR-083, FR-084, FR-085, FR-077, SEC-010, SEC-033, UCL-006, TEN-010 |
| Linked screens | [`SCR-OPS-026`](SCREEN_INVENTORY.md), [`SCR-OPS-027`](SCREEN_INVENTORY.md), [`SCR-OPS-022`](SCREEN_INVENTORY.md) |
| Linked components | `StatusChip`, `ReasonCodePicker`, `ProofCaptureSheet`, `PermissionDeniedPanel`, `PrimaryActionBar` |

### JRN-012 — Order becomes ready for pickup

| Field | Specification |
|---|---|
| Trigger | QC passes order `AL-2026-000123` and it is shelved for collection. |
| Actor | Production Operator (P-07), with Outlet Manager (P-05) oversight. |
| Precondition | The QC record is `PASSED` or `WAIVED_WITH_AUTHORIZATION`. |
| Happy path | 1) Operator opens `SCR-OPS-028`. 2) Operator records the shelf location. 3) The order transitions to `READY_FOR_PICKUP`. 4) The **first** `READY_FOR_PICKUP` timestamp is written once and is immutable. 5) A readiness notification is queued subject to quiet hours and opt-out. 6) The public tracking projection updates to the ready state. |
| Alternative path | The order is instead scheduled for delivery and moves to `SCHEDULED_FOR_DELIVERY`; the first-ready timestamp still governs aging. |
| Error path | A transition attempted before QC completes is rejected and changes nothing. A notification failure is retried and surfaced; it never blocks or reverses the transition. |
| Offline path | The transition is queued with its `client_reference`; the server timestamp is authoritative for the first-ready anchor, not the device clock. |
| Security boundary | Operator to tenant and outlet boundary. The readiness projection exposed publicly contains no full address, no internal note, and no cost. |
| Recovery | If the order returns to `REWORK` and becomes ready a second time, the original first-ready timestamp is preserved. Aging never restarts. |
| Completion criteria | The order is at `READY_FOR_PICKUP` with an immutable first-ready timestamp recorded exactly once. |
| Linked requirements | FR-075, FR-080, FR-086, FR-093, UCL-001, UCL-002, UCL-003, NOT-004, NOT-011, TRK-006, SEC-022 |
| Linked screens | [`SCR-OPS-028`](SCREEN_INVENTORY.md), [`SCR-TRK-003`](SCREEN_INVENTORY.md), [`SCR-CUS-007`](SCREEN_INVENTORY.md) |
| Linked components | `StatusChip`, `ShelfLocationField`, `TimelineStepper`, `PrimaryActionBar` |

### JRN-013 — Reminder H+1

| Field | Specification |
|---|---|
| Trigger | One day has elapsed since the first `READY_FOR_PICKUP` timestamp of order `AL-2026-000123`. |
| Actor | Outlet Manager (P-05) as accountable owner; the scheduler performs the send. |
| Precondition | The order remains uncollected; the customer has not opted out of this message category; the outlet has a messaging channel configured. |
| Happy path | 1) The scheduler evaluates aging against the immutable first-ready timestamp. 2) The H+1 stage is selected. 3) Quiet hours 20:00–08:00 outlet local time are checked. 4) A friendly reminder is queued with a deduplication key. 5) The message is sent through the provider abstraction. 6) The send is recorded on the order with tenant, outlet, template, and status. |
| Alternative path | The evaluation falls inside quiet hours, so the message is deferred to the next permitted window rather than dropped or sent anyway. |
| Error path | Provider failure is retried under a bounded policy and made visible on `SCR-CON-014`. The order's state is not altered by a messaging failure. |
| Offline path | Not applicable — the reminder ladder is a server-side scheduler concern, not a device concern. |
| Security boundary | Backend to third-party messaging provider. The message carries no full address, no tracking token, and no OTP. Provider responses are untrusted input. |
| Recovery | A failed send is retried and, if still failing, flagged for the outlet manager. The H+1 stage still fires at most once for the order. |
| Completion criteria | The H+1 stage fired exactly once for this order, or is recorded as deferred or failed with a reason. |
| Linked requirements | UCL-001, UCL-004, UCL-005, UCL-009, UCL-013, NOT-001, NOT-005, NOT-008, NOT-012, NOT-018, FR-112, FR-093 |
| Linked screens | [`SCR-CON-014`](SCREEN_INVENTORY.md), [`SCR-CUS-016`](SCREEN_INVENTORY.md) |
| Linked components | `AgingBucketTable`, `StatusChip`, `NotificationLogList`, `MaskedIdentityLabel` |

### JRN-014 — Reminder H+3

| Field | Specification |
|---|---|
| Trigger | Three days have elapsed since the first `READY_FOR_PICKUP` timestamp and the order is still uncollected. |
| Actor | Outlet Manager (P-05) as accountable owner; the scheduler performs the send. |
| Precondition | The H+1 stage has already fired, deferred, or failed; the customer has not opted out; the order has not moved to `COMPLETED`. |
| Happy path | 1) The scheduler recomputes aging from the same immutable anchor. 2) The H+3 stage is selected. 3) Quiet hours and opt-out are checked. 4) A second reminder, distinct in template from H+1, is queued with its own deduplication key. 5) The message is sent. 6) The send and its outcome are recorded against the order. |
| Alternative path | The customer has since collected the laundry, so the order is no longer eligible and the stage is skipped rather than sent late. |
| Error path | A duplicate scheduler run does not resend, because deduplication is keyed on recipient, event, order, and intended send window. |
| Offline path | Not applicable — server-side scheduler. |
| Security boundary | Backend to provider. Message content is masked; the outstanding amount may be stated as integer Rupiah but no address, token, or internal note is included. |
| Recovery | If H+1 never sent, H+3 still fires on its own schedule; stages are independent and each fires at most once. |
| Completion criteria | The H+3 stage fired at most once, and its outcome is recorded. |
| Linked requirements | UCL-001, UCL-004, UCL-006, UCL-010, UCL-014, NOT-005, NOT-008, NOT-012, NOT-016, FR-113, FR-094 |
| Linked screens | [`SCR-CON-014`](SCREEN_INVENTORY.md), [`SCR-CUS-016`](SCREEN_INVENTORY.md) |
| Linked components | `AgingBucketTable`, `NotificationLogList`, `StatusChip`, `MoneyField` |

### JRN-015 — Reminder H+7

| Field | Specification |
|---|---|
| Trigger | Seven days have elapsed since the first `READY_FOR_PICKUP` timestamp and the order is still uncollected. |
| Actor | Outlet Manager (P-05); a follow-up officer is assigned. |
| Precondition | The order remains uncollected and is not `CANCELLED`; the tenant has a follow-up officer role available at Outlet Cempaka. |
| Happy path | 1) The scheduler selects the H+7 stage. 2) A priority reminder is queued subject to quiet hours and opt-out. 3) In addition, a real assignable follow-up task is created with an owner and a due date. 4) The task appears on `SCR-CON-014` with the assigned officer. 5) The officer contacts the customer and records the outcome. 6) The reason not collected is captured as a first-class field. |
| Alternative path | If no officer is available, the task is assigned to the outlet manager rather than left unowned. A task is never merely a report flag. |
| Error path | Reminder send failure does not cancel the follow-up task; the task exists independently of message delivery. |
| Offline path | Not applicable for scheduling. Follow-up outcomes recorded on a device follow the standard offline queue rules. |
| Security boundary | Backend to provider, plus internal task assignment inside the tenant boundary. Follow-up notes are INTERNAL and never surface on the public portal. |
| Recovery | An unactioned task escalates at H+14 to a manager or owner — a human accountable for the outcome. |
| Completion criteria | The H+7 reminder fired at most once **and** a real assignable follow-up task exists with a named owner. |
| Linked requirements | UCL-001, UCL-004, UCL-007, UCL-011, UCL-015, UCL-019, NOT-005, NOT-012, FR-114, FR-115, RPT-009 |
| Linked screens | [`SCR-CON-014`](SCREEN_INVENTORY.md), [`SCR-OPS-028`](SCREEN_INVENTORY.md) |
| Linked components | `AgingBucketTable`, `FollowUpTaskCard`, `ReasonCodePicker`, `StatusChip` |

### JRN-016 — Cashier follows up unclaimed laundry

| Field | Specification |
|---|---|
| Trigger | The cashier works the unclaimed list at Outlet Cempaka during a quiet period. |
| Actor | Cashier (P-06), acting on tasks raised by the ladder. |
| Precondition | Orders sit at `READY_FOR_PICKUP` beyond their expected window; follow-up tasks exist. |
| Happy path | 1) Cashier opens the unclaimed view. 2) Cashier sees order count, customer count, held invoices, unpaid balance, order age, outlet, last reminder, follow-up officer, and reason not collected. 3) Cashier contacts Budi Santoso on `0812-XXXX-1234`. 4) Cashier records the reason not collected. 5) Cashier records an agreed collection date. 6) The task is closed with an outcome. |
| Alternative path | The customer asks for delivery instead; the cashier raises a delivery request and the order moves toward `SCHEDULED_FOR_DELIVERY`. |
| Error path | The customer cannot be reached; the cashier records a "tidak dapat dihubungi" reason and the task remains open for the next attempt rather than being closed. |
| Offline path | Follow-up outcomes are queued with a `client_reference` and shown as `UXS-005` until synced. |
| Security boundary | Cashier to tenant and outlet boundary. Unpaid balance and held-invoice figures are read from the authoritative financial records and never recomputed independently. |
| Recovery | At H+14 the matter escalates to a manager or owner. **There is no automatic disposal, sale, auction, donation, or ownership transfer of a customer's laundry — ever.** |
| Completion criteria | Every worked item carries a recorded reason not collected, an officer, and an outcome; all nine dashboard fields are present. |
| Linked requirements | UCL-008, UCL-012, UCL-016, UCL-017, UCL-020, UCL-022, UCL-025, FR-116, FR-117, FIN-011, RPT-010, TEN-011 |
| Linked screens | [`SCR-CON-014`](SCREEN_INVENTORY.md), [`SCR-OPS-028`](SCREEN_INVENTORY.md), [`SCR-OPS-006`](SCREEN_INVENTORY.md) |
| Linked components | `AgingBucketTable`, `ReasonCodePicker`, `MoneyField`, `MaskedIdentityLabel`, `FollowUpTaskCard` |

### JRN-017 — Manager converts pickup to delivery

| Field | Specification |
|---|---|
| Trigger | Budi Santoso asks for the finished laundry to be delivered rather than collected. |
| Actor | Outlet Manager (P-05). |
| Precondition | The order is at `READY_FOR_PICKUP`; the delivery address is within a serviced zone; delivery is enabled for the tenant. |
| Happy path | 1) Manager opens `SCR-CON-011`. 2) Manager selects order `AL-2026-000123`. 3) Manager creates a delivery job at `REQUESTED`. 4) Manager confirms the address and time window `14:30`–`16:30`. 5) The job moves to `CONFIRMED` then `SCHEDULED`. 6) The order moves to `SCHEDULED_FOR_DELIVERY`. |
| Alternative path | The address falls outside a serviced zone; the manager records the exception and keeps the order at `READY_FOR_PICKUP` for counter collection. |
| Error path | An attempt to schedule an order not yet ready is rejected. Any delivery fee is a distinct financial line in integer Rupiah, never folded into the service price. |
| Offline path | Console Web is an online surface; loss of connectivity renders `UXS-004` and blocks scheduling rather than queueing a half-formed job. |
| Security boundary | Manager to tenant and outlet boundary. Customer address is RESTRICTED and is shown to the manager only as far as scheduling requires. |
| Recovery | A scheduled job may be moved to `RESCHEDULED` or `CANCELLED` with a recorded reason. Aging on the underlying order continues from its unchanged first-ready anchor. |
| Completion criteria | One delivery job exists at `SCHEDULED` and the order is at `SCHEDULED_FOR_DELIVERY`, both tenant-scoped. |
| Linked requirements | FR-100, FR-103, FR-104, DEL-003, DEL-005, DEL-008, DEL-012, FIN-006, UCL-002, TEN-010, SEC-021 |
| Linked screens | [`SCR-CON-011`](SCREEN_INVENTORY.md), [`SCR-CUS-011`](SCREEN_INVENTORY.md) |
| Linked components | `SchedulePlanner`, `StatusChip`, `MoneyField`, `ReasonCodePicker`, `PrimaryActionBar` |

### JRN-018 — Manager assigns courier

| Field | Specification |
|---|---|
| Trigger | Delivery jobs for the afternoon window need a courier at Outlet Cempaka. |
| Actor | Outlet Manager (P-05). |
| Precondition | Jobs exist at `SCHEDULED`; at least one internal courier or external local courier is available. |
| Happy path | 1) Manager opens `SCR-CON-012`. 2) Manager selects the jobs for the window. 3) Manager assigns an internal courier. 4) Jobs move to `ASSIGNED`. 5) Manager opens `SCR-CON-013` and arranges the stops into a **usulan rute** — a suggested ordering. 6) The courier sees the assigned jobs on `SCR-OPS-030`. |
| Alternative path | An external local courier is assigned instead and receives a scoped, expiring, revocable guest job link rather than an account. |
| Error path | Assigning a job already assigned surfaces the conflict rather than overwriting it. Assignment failure leaves the job at `SCHEDULED`. |
| Offline path | Not applicable on Console Web. The courier device receives its jobs and works them under the standard offline rules. |
| Security boundary | Manager to tenant and outlet boundary. A guest link is tenant-scoped, high-entropy, stored hashed, and exposes only the assigned job. |
| Recovery | Assignment may be changed with a recorded reason before the job reaches `EN_ROUTE`. |
| Completion criteria | Every selected job is at `ASSIGNED` with a named courier and a suggested stop ordering. **No route optimization and no guaranteed arrival time is claimed.** |
| Linked requirements | FR-105, FR-106, FR-108, DEL-006, DEL-009, DEL-013, DEL-018, DEL-024, SEC-035, SEC-038, TEN-013 |
| Linked screens | [`SCR-CON-012`](SCREEN_INVENTORY.md), [`SCR-CON-013`](SCREEN_INVENTORY.md), [`SCR-OPS-030`](SCREEN_INVENTORY.md) |
| Linked components | `CourierAssignmentPanel`, `RouteSuggestionList`, `StatusChip`, `ConflictResolutionPanel` |

### JRN-019 — External courier uses guest job

| Field | Specification |
|---|---|
| Trigger | An external local courier opens the guest job link sent for one assigned delivery. |
| Actor | External Courier (P-10). Not an account holder. |
| Precondition | A guest job link was issued for exactly one job; its token is high-entropy, hashed server-side, unexpired, and unrevoked. |
| Happy path | 1) Courier opens the link. 2) The token is validated and rate-limited. 3) The single assigned job is shown with only the information the delivery genuinely requires. 4) Courier marks `EN_ROUTE`, then `ARRIVED`. 5) Courier captures proof and records `DELIVERED`. 6) The link's usefulness ends with the job. |
| Alternative path | The courier records `FAILED` with a reason; the laundry returns to Outlet Cempaka and the order returns to a defined status with that reason recorded. |
| Error path | An expired or revoked token shows a generic denial that does not reveal whether the job exists. Excess attempts render a rate-limited state. |
| Offline path | Proof capture tolerates signal loss; the capture is queued under its `client_reference` and uploaded on reconnect without creating a second record. |
| Security boundary | Public internet to backend, most exposed non-portal surface. The link is not the order number and is not derivable from it; it grants no access to customer history, other orders, pricing, or any other tenant data. A courier working for two tenants gets two unrelated links and can never traverse between them. |
| Recovery | The manager revokes the link at any time; revocation takes effect immediately. A replacement job requires a new link, never the old token. |
| Completion criteria | Exactly one job is progressed by exactly one scoped token, with proof recorded and no data beyond the assignment exposed. |
| Linked requirements | DEL-014, DEL-019, DEL-021, DEL-025, DEL-028, SEC-034, SEC-036, SEC-039, SEC-044, TEN-013, FR-106, FR-109 |
| Linked screens | [`SCR-OPS-030`](SCREEN_INVENTORY.md), [`SCR-OPS-031`](SCREEN_INVENTORY.md), [`SCR-CON-012`](SCREEN_INVENTORY.md) |
| Linked components | `GuestJobCard`, `ProofCaptureSheet`, `StatusChip`, `PrimaryActionBar`, `MaskedIdentityLabel` |

### JRN-020 — Courier records failed attempt

| Field | Specification |
|---|---|
| Trigger | The courier arrives at the delivery address and nobody is there to receive the laundry. |
| Actor | Courier Internal (P-09). |
| Precondition | The job is at `ARRIVED`; the courier holds the assignment. |
| Happy path | 1) Courier opens `SCR-OPS-030`. 2) Courier selects the failed-attempt action. 3) Courier picks a reason code such as "penerima tidak di tempat". 4) Courier captures supporting evidence where policy requires it. 5) The job moves to `FAILED`. 6) The laundry returns to Outlet Cempaka and the order returns to a defined status with the reason recorded. |
| Alternative path | The customer answers and asks for another window; the job moves to `RESCHEDULED` with a new window instead of `FAILED`. |
| Error path | A failed attempt without a reason code is blocked; a failure is a first-class outcome and must be explained, not merely marked. |
| Offline path | The outcome is queued under its `client_reference` and shows `UXS-005` until synced; replay is a no-op if already applied. |
| Security boundary | Courier to tenant and outlet boundary. The courier sees only their assigned jobs; addresses of unrelated jobs are never visible. |
| Recovery | The outlet reschedules or converts the order back to counter collection. Aging on the order continues from its unchanged first-ready anchor. |
| Completion criteria | The job is at `FAILED` or `RESCHEDULED` with a recorded reason, actor, and timestamp, and the physical custody outcome is recorded. |
| Linked requirements | DEL-010, DEL-015, DEL-020, DEL-026, DEL-030, FR-104, FR-106, OFF-006, UCL-002, TEN-013, SEC-021 |
| Linked screens | [`SCR-OPS-030`](SCREEN_INVENTORY.md), [`SCR-OPS-031`](SCREEN_INVENTORY.md), [`SCR-CON-011`](SCREEN_INVENTORY.md) |
| Linked components | `ReasonCodePicker`, `ProofCaptureSheet`, `StatusChip`, `SyncBadge`, `PrimaryActionBar` |

### JRN-021 — Courier records proof

| Field | Specification |
|---|---|
| Trigger | The courier hands the laundry to an authorised recipient at the delivery address. |
| Actor | Courier Internal (P-09); Authorized Recipient present. |
| Precondition | The job is at `ARRIVED`; the tenant's proof policy defines which proof methods are acceptable. |
| Happy path | 1) Courier opens `SCR-OPS-031`. 2) Courier records the recipient name. 3) Courier captures the configured proof — OTP, photograph, or signature. 4) Courier confirms handover. 5) The job moves to `DELIVERED`. 6) The order moves toward `COMPLETED` subject to its payment state. |
| Alternative path | The recipient is a neighbour designated by the customer; the courier records the recipient name and relationship as part of the proof. |
| Error path | Missing proof blocks completion. **No custody transfer is recorded without proof appropriate to the tenant's configured policy.** Upload failure keeps the proof queued and visible. |
| Offline path | Proof artifacts are stored encrypted on device, queued under the job's `client_reference`, and uploaded on reconnect; the queue survives app kill. |
| Security boundary | Device to backend to private object storage. Proof photographs and signatures are RESTRICTED, served only via signed expiring URLs, tenant-scoped, and never shown on the public tracking portal. |
| Recovery | A failed upload retries under bounded backoff and is surfaced for human attention rather than dropped. |
| Completion criteria | The job is at `DELIVERED` with at least one recorded proof artifact and a recipient identity, both private and tenant-scoped. |
| Linked requirements | DEL-011, DEL-016, DEL-022, DEL-027, DEL-031, FR-106, SEC-024, SEC-030, SEC-047, OFF-009, TEN-012 |
| Linked screens | [`SCR-OPS-030`](SCREEN_INVENTORY.md), [`SCR-OPS-031`](SCREEN_INVENTORY.md) |
| Linked components | `ProofCaptureSheet`, `SignaturePad`, `RecipientField`, `StatusChip`, `SyncBadge` |

### JRN-022 — Courier reconciles COD

| Field | Specification |
|---|---|
| Trigger | The courier returns to Outlet Cempaka at the end of the shift holding collected cash. |
| Actor | Courier Internal (P-09), with Outlet Manager (P-05) or Finance (P-11) receiving. |
| Precondition | Cash was collected on one or more deliveries during the shift; each collection was recorded as a financial transaction. |
| Happy path | 1) Courier opens `SCR-OPS-032`. 2) The expected total for the shift is shown in integer Rupiah. 3) Courier counts and enters the actual amount handed over. 4) The variance is computed and displayed explicitly. 5) A non-zero variance requires a reason and an acknowledgement. 6) The manager confirms receipt on `SCR-CON-017` and the settlement is recorded. |
| Alternative path | Cash is handed over in two tranches during a long shift; each handover is its own recorded settlement rather than one merged figure. |
| Error path | An entered amount that does not reconcile is never auto-rounded, absorbed, or written off. The variance is recorded and acknowledged, never hidden. |
| Offline path | Collections recorded offline queue under their `client_reference`; settlement cannot be finalised until the collection queue has drained, and the interface says so. |
| Security boundary | Courier to tenant, outlet, and financial boundary. Cash collection is a financial transaction: integer Rupiah, idempotent, never hard-deleted, corrections by reversal or adjustment with actor, timestamp, and reason. |
| Recovery | A settlement error is corrected by an adjustment entry, never by deleting the original. Repeated variances are visible in reporting. |
| Completion criteria | Expected and actual are compared explicitly, any variance is recorded with a reason and an acknowledgement, and the settlement is auditable. |
| Linked requirements | FR-108, FR-109, FR-110, FR-111, FIN-008, FIN-016, FIN-021, FIN-025, DEL-023, DEL-029, RPT-012, TEN-009 |
| Linked screens | [`SCR-OPS-032`](SCREEN_INVENTORY.md), [`SCR-CON-017`](SCREEN_INVENTORY.md) |
| Linked components | `MoneyField`, `VarianceSummary`, `ReasonCodePicker`, `StatusChip`, `PrimaryActionBar` |

### JRN-023 — Owner switches tenant

| Field | Specification |
|---|---|
| Trigger | The owner finishes reviewing one tenant and switches to another they legitimately belong to. |
| Actor | Tenant Owner (P-03) holding memberships in more than one tenant. |
| Precondition | The user account has at least two active memberships; a tenant switcher is available wherever multi-tenant membership exists. |
| Happy path | 1) Owner opens the tenant switcher. 2) The list shows only tenants where an active membership exists. 3) Owner selects Laundry Bersih Sejahtera. 4) The session context changes and the backend re-derives permissions from the membership. 5) All views reload against the new tenant. 6) Any cached data from the previous tenant is cleared. |
| Alternative path | On the Ops app the equivalent selection happens on `SCR-OPS-004` at sign-in when more than one membership exists. |
| Error path | A tenant that is suspended or unavailable renders `UXS-013` and the switch does not complete. A stale membership no longer appears in the list. |
| Offline path | On the Ops app a tenant switch while offline is restricted, because local data is separated per tenant and per user and must not leak across the switch. |
| Security boundary | The tenant boundary is a trust boundary. **A client-supplied tenant identifier is never authorization proof**; the backend validates it against the authenticated user's memberships on every request. |
| Recovery | If the switch fails, the session remains on the previous tenant rather than entering an undefined state. |
| Completion criteria | The active tenant context matches a verified membership, and no data from the previous tenant remains visible or cached. |
| Linked requirements | FR-011, FR-012, FR-013, FR-041, FR-042, TEN-001, TEN-002, TEN-005, TEN-015, TEN-018, SEC-003, OFF-016 |
| Linked screens | [`SCR-OPS-004`](SCREEN_INVENTORY.md), [`SCR-CON-001`](SCREEN_INVENTORY.md) |
| Linked components | `TenantSwitcher`, `StatusChip`, `PermissionDeniedPanel`, `PrimaryActionBar` |

### JRN-024 — Owner views portfolio

| Field | Specification |
|---|---|
| Trigger | The owner opens the portfolio dashboard to compare performance across the tenants they own. |
| Actor | Tenant Owner (P-03). |
| Precondition | The owner holds active memberships in each tenant aggregated; reporting data exists for the selected period. |
| Happy path | 1) Owner opens `SCR-CON-001`. 2) The dashboard aggregates only tenants where the owner has an active membership. 3) Owner selects a period. 4) Revenue, order counts, outstanding receivables, and unclaimed volume are shown per tenant in integer Rupiah. 5) Owner drills into one tenant. 6) The drill-down opens inside that tenant's scope, not a widened query. |
| Alternative path | Owner exports the portfolio summary; the export carries the same access rules as the underlying records and is tenant-scoped. |
| Error path | If one tenant's figures cannot be fetched, `UXS-019` Partial Data is shown for that tenant only, and totals state that they are incomplete rather than silently under-reporting. |
| Offline path | Not applicable — Console Web is an online surface. `UXS-004` blocks the dashboard rather than showing stale aggregates as current. |
| Security boundary | Aggregating across tenants the owner legitimately belongs to is permitted; **widening the query surface to achieve it is not**. The portfolio dashboard must not weaken tenant isolation. |
| Recovery | Owner reloads or narrows the period. A membership removed since the last load disappears from the aggregate on the next request. |
| Completion criteria | Every figure shown traces to a tenant where the owner holds an active membership, and no other tenant's data is reachable. |
| Linked requirements | FR-043, FR-044, FR-045, RPT-001, RPT-003, RPT-005, RPT-008, RPT-015, TEN-019, TEN-022, FIN-018, SEC-004 |
| Linked screens | [`SCR-CON-001`](SCREEN_INVENTORY.md), [`SCR-CON-015`](SCREEN_INVENTORY.md) |
| Linked components | `PortfolioSummaryGrid`, `MoneyField`, `TenantSwitcher`, `PartialDataBanner`, `StatusChip` |

### JRN-025 — Finance reconciles receivable

| Field | Specification |
|---|---|
| Trigger | Finance works the outstanding receivables list at month end. |
| Actor | Finance (P-11). |
| Precondition | Orders exist with outstanding balances; the finance role holds the permission to view and adjust receivables. |
| Happy path | 1) Finance opens `SCR-CON-015`. 2) The list shows outstanding balances per customer and per outlet in integer Rupiah. 3) Finance selects order `AL-2026-000123` with `Rp39.000` outstanding. 4) Finance matches a received transfer against it. 5) The payment is recorded with an actor, timestamp, and reference. 6) The outstanding balance becomes `Rp0`. |
| Alternative path | An overpayment is recorded and the surplus is handled as a documented adjustment or deposit entry rather than being discarded. |
| Error path | An amount mismatch is surfaced and blocked. **A financial record is never hard-deleted**; a wrong entry is corrected by a reversal or adjustment entry that preserves the original. |
| Offline path | Not applicable — Console Web is an online surface. |
| Security boundary | Finance to tenant boundary. Every financial query is tenant-scoped; a query missing its scope yields nothing rather than another tenant's rows. Refund and void require permission and a recorded reason. |
| Recovery | Corrections are audited with actor, tenant, outlet, timestamp, before and after amounts, and reason. Historical order prices are immune to later price-list changes. |
| Completion criteria | The receivable is settled or explicitly adjusted, the ledger is append-only in effect, and every change is auditable. |
| Linked requirements | FR-064, FR-067, FR-068, FR-070, FIN-004, FIN-010, FIN-013, FIN-017, FIN-022, FIN-026, RPT-006, RPT-013, TEN-020 |
| Linked screens | [`SCR-CON-015`](SCREEN_INVENTORY.md), [`SCR-CON-001`](SCREEN_INVENTORY.md) |
| Linked components | `ReceivablesTable`, `MoneyField`, `ReasonCodePicker`, `AdjustmentEntryForm`, `StatusChip` |

### JRN-026 — Membership revoked

| Field | Specification |
|---|---|
| Trigger | A tenant admin removes a staff member's membership in Laundry Bersih Sejahtera. |
| Actor | Tenant Admin performs the revocation; the affected staff member experiences it. |
| Precondition | The staff member holds an active membership and may be signed in on one or more devices. |
| Happy path | 1) The admin revokes the membership. 2) The change takes effect server-side immediately. 3) The affected user's next request is refused at the backend. 4) The app renders `UXS-010` Permission Denied with a clear explanation. 5) The tenant disappears from the user's tenant switcher. 6) Cached tenant data on the device is cleared. |
| Alternative path | Only a permission within the membership is reduced rather than the membership removed; affected actions are refused while others continue. |
| Error path | If the device is mid-operation, the queued operation fails with a permission reason and stays visible rather than being silently discarded, so the outlet can reassign the work. |
| Offline path | An offline device may still hold a valid-looking local session. Revocation is authoritative on the server; the device cannot self-authorise, and queued operations are refused on reconnect. |
| Security boundary | Membership is the source of authorization, never the user account alone. **Client-side menu visibility is not authorization**; the backend verifies membership and permission on every request. |
| Recovery | Work in progress is surfaced to the outlet manager for reassignment. The user may still hold valid memberships in other tenants and continues to use those. |
| Completion criteria | The revoked user can no longer read, list, search, export, or mutate any record of that tenant through any path. |
| Linked requirements | FR-014, FR-015, FR-046, FR-047, TEN-003, TEN-006, TEN-016, TEN-021, TEN-024, SEC-002, SEC-006, SEC-018, OFF-016 |
| Linked screens | [`SCR-OPS-004`](SCREEN_INVENTORY.md), [`SCR-OPS-006`](SCREEN_INVENTORY.md), [`SCR-OPS-020`](SCREEN_INVENTORY.md) |
| Linked components | `PermissionDeniedPanel`, `TenantSwitcher`, `OfflineQueueList`, `StatusChip` |

### JRN-027 — Session expired

| Field | Specification |
|---|---|
| Trigger | The cashier returns to the app after a long break and the session has expired. |
| Actor | Cashier (P-06). |
| Precondition | A session existed on the device; it has expired or was terminated server-side. |
| Happy path | 1) The app makes a request and receives a session-expired response. 2) `SCR-OPS-036` renders with `UXS-011`. 3) The pending queue is preserved and remains visible. 4) Cashier re-authenticates. 5) The session is re-established and the tenant context restored. 6) The queue resumes draining with the original `client_reference` values intact. |
| Alternative path | The session was revoked deliberately by an administrator; the same screen is shown with a message that does not disclose administrative detail. |
| Error path | Repeated authentication failures trigger progressive backoff and lockout, rendering `UXS-017`. Credentials, OTPs, and tokens never appear in logs. |
| Offline path | While offline the app cannot re-authenticate and shows `UXS-004` alongside `UXS-011`. **The financial queue is never cleared by session expiry.** |
| Security boundary | Session boundary. Session revocation is supported and takes effect immediately server-side. Tokens are stored hashed server-side and in platform secure storage on device. |
| Recovery | After successful re-authentication, no queued work is lost and no operation is re-created; each retry reuses its original reference. |
| Completion criteria | The user is re-authenticated, the queue is intact, and no duplicate order or payment resulted from the interruption. |
| Linked requirements | FR-004, FR-005, FR-006, SEC-008, SEC-011, SEC-016, SEC-027, SEC-050, OFF-010, OFF-015, NFR-024 |
| Linked screens | [`SCR-OPS-036`](SCREEN_INVENTORY.md), [`SCR-OPS-020`](SCREEN_INVENTORY.md) |
| Linked components | `SessionExpiredPanel`, `OfflineQueueList`, `SyncBadge`, `PrimaryActionBar` |

### JRN-028 — Device revoked

| Field | Specification |
|---|---|
| Trigger | A device is lost and the outlet manager revokes that specific device's access. |
| Actor | Outlet Manager (P-05) performs the revocation; the device holder experiences it. |
| Precondition | The device is registered against the user and tenant; other devices remain trusted. |
| Happy path | 1) Manager revokes the device. 2) The revocation takes effect server-side immediately. 3) The revoked device's next request is refused. 4) `SCR-OPS-037` renders with `UXS-012`. 5) Locally cached tenant data on that device is cleared. 6) Other devices continue working without re-authenticating. |
| Alternative path | The user revokes their own device from another trusted device; the outcome is identical for the revoked device. |
| Error path | If the revoked device holds unsynced financial operations, they cannot be recovered from that device. This is surfaced to the manager so the outlet can reconstruct the work rather than assume it synced. |
| Offline path | An offline revoked device continues to appear signed in locally until it reaches the network. It cannot self-authorise; the server refuses it on reconnect and the local cache is cleared. |
| Security boundary | Device boundary. **Device revocation is supported without forcing every other device to re-authenticate.** Credentials and tokens on device use platform keystore-backed secure storage. |
| Recovery | The manager reissues access on a replacement device. Any work known to be unsynced is reconstructed through an audited path. |
| Completion criteria | The revoked device can perform no authenticated action and holds no readable tenant data; other devices are unaffected. |
| Linked requirements | FR-007, FR-008, FR-009, SEC-012, SEC-017, SEC-026, SEC-028, SEC-052, OFF-016, OFF-021, TEN-017 |
| Linked screens | [`SCR-OPS-037`](SCREEN_INVENTORY.md), [`SCR-OPS-036`](SCREEN_INVENTORY.md) |
| Linked components | `DeviceRevokedPanel`, `PermissionDeniedPanel`, `OfflineQueueList`, `StatusChip` |

### JRN-029 — Notification provider fails

| Field | Specification |
|---|---|
| Trigger | The messaging provider returns errors for a sustained period during the afternoon. |
| Actor | Outlet Manager (P-05) observes and acts; the system detects and retries. |
| Precondition | Notifications are queued for readiness messages and ladder reminders; the provider sits behind the internal abstraction. |
| Happy path | 1) Sends begin failing. 2) The abstraction retries under a bounded policy with exponential backoff. 3) The degraded state is surfaced as `UXS-016` Provider Degraded. 4) Manager sees which messages failed and for which orders. 5) Manager uses the manual deep-link fallback for the most urgent customers. 6) When the provider recovers, the remaining queue drains without duplicating already-delivered messages. |
| Alternative path | Only one template or category is affected; transactional and marketing categories are handled separately and are not conflated. |
| Error path | Retries are bounded, never infinite. A message that exhausts its retries is flagged for human attention and is never silently discarded. |
| Offline path | Not applicable — this is a server-to-provider concern. |
| Security boundary | Backend to third-party provider. **A provider is untrusted input**; its responses are verified server-side. Messages never contain a plaintext tracking token, an OTP echo, a credential, or a full address. |
| Recovery | Deduplication keyed on recipient, event, order, and intended send window prevents a recovery drain from resending. Quiet hours still apply to deferred messages. |
| Completion criteria | **No order, payment, or delivery state changed because a notification failed.** Failures are visible, bounded, and deduplicated. |
| Linked requirements | NOT-002, NOT-006, NOT-009, NOT-013, NOT-017, NOT-021, NOT-025, FR-095, FR-096, FR-097, SEC-045, NFR-027 |
| Linked screens | [`SCR-CON-014`](SCREEN_INVENTORY.md), [`SCR-CON-001`](SCREEN_INVENTORY.md), [`SCR-CUS-016`](SCREEN_INVENTORY.md) |
| Linked components | `NotificationLogList`, `ProviderStatusBanner`, `StatusChip`, `ReasonCodePicker` |

### JRN-030 — Tracking token expired

| Field | Specification |
|---|---|
| Trigger | Budi Santoso opens an old tracking link several weeks after collection. |
| Actor | Customer (P-12), unauthenticated visitor. |
| Precondition | A token was issued for order `AL-2026-000123` and its validity window has elapsed. |
| Happy path | 1) Visitor opens the link. 2) The backend hashes the presented token and finds an expired record. 3) `SCR-TRK-005` renders an expired-token state. 4) The message explains what happened and what to do next. 5) The visitor is offered a way to request a fresh link from Outlet Cempaka. 6) No order data is rendered. |
| Alternative path | The visitor requests a fresh link and a new high-entropy token is issued; the expired token is not reactivated. |
| Error path | Repeated attempts with expired or guessed tokens trigger rate limiting and render `SCR-TRK-008` with `UXS-017`. |
| Offline path | Not applicable — the public portal is online-only. |
| Security boundary | Public internet to backend. Expiry is enforced server-side. The response reveals no order existence, no customer identity, and no tenant identity. Only the token hash is stored; the plaintext exists only in the link. |
| Recovery | The outlet issues a fresh link. Sensitive actions on the portal may additionally require an OTP step-up via `SCR-TRK-009`. |
| Completion criteria | An expired token yields no order data and a recoverable, plainly worded next step. |
| Linked requirements | TRK-003, TRK-007, TRK-011, TRK-015, TRK-019, TRK-023, SEC-014, SEC-020, SEC-041, SEC-055, NFR-019 |
| Linked screens | [`SCR-TRK-005`](SCREEN_INVENTORY.md), [`SCR-TRK-008`](SCREEN_INVENTORY.md), [`SCR-TRK-009`](SCREEN_INVENTORY.md) |
| Linked components | `TokenStatePanel`, `PrimaryActionBar`, `StatusChip`, `MaskedIdentityLabel` |

### JRN-031 — Tracking token revoked

| Field | Specification |
|---|---|
| Trigger | The outlet manager revokes a tracking link that was forwarded to the wrong recipient. |
| Actor | Outlet Manager (P-05) revokes; Customer (P-12) or the wrong recipient experiences the denial. |
| Precondition | A live token exists for the order; revocation is available to the outlet manager. |
| Happy path | 1) Manager revokes the token. 2) Revocation takes effect immediately server-side. 3) Anyone opening the link reaches `SCR-TRK-006`. 4) No order data is rendered. 5) Manager issues a fresh link to the correct recipient. 6) The revocation is recorded with actor, timestamp, and reason. |
| Alternative path | All tokens for the order are revoked at once when the manager is unsure how widely the link was forwarded. |
| Error path | Attempts to reuse the revoked token repeatedly are rate limited and render `UXS-017`. The denial does not distinguish revoked from never-existed. |
| Offline path | Not applicable — the public portal is online-only. |
| Security boundary | Public internet to backend. Tokens are revocable and expiring by design, stored hashed, and never derivable from the order number. Revocation is authoritative and immediate. |
| Recovery | A new token is issued for the correct recipient. The revoked token is never reactivated. |
| Completion criteria | The revoked token yields no order data through any path, and the revocation is audited. |
| Linked requirements | TRK-005, TRK-008, TRK-012, TRK-016, TRK-020, TRK-024, SEC-015, SEC-020, SEC-042, SEC-058, TEN-004 |
| Linked screens | [`SCR-TRK-006`](SCREEN_INVENTORY.md), [`SCR-TRK-008`](SCREEN_INVENTORY.md), [`SCR-CON-014`](SCREEN_INVENTORY.md) |
| Linked components | `TokenStatePanel`, `ReasonCodePicker`, `StatusChip`, `PrimaryActionBar` |

### JRN-032 — Sync conflict requires user action

| Field | Specification |
|---|---|
| Trigger | A queued payment for order `AL-2026-000123` reaches the server, which already holds a different payment state for that order. |
| Actor | Cashier (P-06); escalates to Outlet Manager (P-05) where policy requires. |
| Precondition | The device was offline; another device or the counter recorded a payment against the same order in the meantime. |
| Happy path | 1) The queue drains and the server reports divergence. 2) `SCR-OPS-021` renders with `UXS-009` Conflict. 3) Both the server state and the local state are shown side by side with amounts in integer Rupiah and timestamps in outlet timezone. 4) Cashier reviews the difference. 5) Cashier chooses the documented resolution, recording a reason. 6) The resolution is applied as a new audited entry. |
| Alternative path | The conflict concerns non-financial metadata only; a documented last-write rule may apply, and that rule is stated in the interface rather than assumed. |
| Error path | If the cashier lacks the permission to resolve a financial conflict, `UXS-010` is shown and the conflict remains open and visible for the manager. |
| Offline path | The conflict is detected only on reconnect. Until then the operation sits at `UXS-005` and no local resolution is possible. |
| Security boundary | Client to server reconciliation boundary. **The server is the final source of truth.** Resolution requires permission and is audited with actor, tenant, outlet, timestamp, before and after amounts, and reason. |
| Recovery | Corrections are made by reversal or adjustment entries; the original records are preserved. **A conflict affecting money is a human decision and is never resolved silently.** |
| Completion criteria | The conflict is explicitly resolved by an authorised human with a recorded reason, and no payment was overwritten or lost. |
| Linked requirements | OFF-005, OFF-008, OFF-017, OFF-019, OFF-022, OFF-025, FIN-005, FIN-013, FIN-023, FR-059, FR-069, SEC-019, NFR-015 |
| Linked screens | [`SCR-OPS-020`](SCREEN_INVENTORY.md), [`SCR-OPS-021`](SCREEN_INVENTORY.md), [`SCR-OPS-017`](SCREEN_INVENTORY.md) |
| Linked components | `ConflictResolutionPanel`, `MoneyField`, `ReasonCodePicker`, `OfflineQueueList`, `PermissionDeniedPanel` |

## Status

| Item | Status |
|---|---|
| This document | **IN PROGRESS** (Step 2 artefact) |
| JRN-001 … JRN-032 | **NOT IMPLEMENTED** |
| Journey execution | **NOT STARTED** |
| Journey test results | **NOT APPLICABLE** — no runtime exists |
| Accessibility verification | **DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED** |

**No journey in this document has been executed, tested, or verified.** Every journey describes an
obligation on future Steps, never an achievement of this one. There is no application, no backend, no
database, no screen, and no test suite. `GO` is conferred by the repository owner and is never
self-declared by an agent.
