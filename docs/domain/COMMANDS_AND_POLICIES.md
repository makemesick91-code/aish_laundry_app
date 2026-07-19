# Commands and Policies — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** `NOT IMPLEMENTED` (documentation only)
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.0.1

A **command** is a request to change state. It may be rejected. A **policy** is a rule that reacts to
a domain event by issuing a command, subject to preconditions. Events are catalogued in
[`DOMAIN_EVENTS.md`](DOMAIN_EVENTS.md); invariant identifiers are defined in
[`DOMAIN_INVARIANTS.md`](DOMAIN_INVARIANTS.md).

---

## 1. Rules that govern every command

| Rule | Statement |
| --- | --- |
| **Server-side authorisation** | Every command is authorised on the server against a verified `Membership`. Client-side hiding of a button is a UX affordance, never an access control. |
| **Tenant scope is derived, not supplied** | The command's tenant scope comes from the verified membership. A `TenantId` in the payload is an untrusted hint (`TEN-024`). |
| **Rejection is normal** | A command that violates an invariant is rejected with a machine-readable error code and a Bahasa Indonesia message that explains the recovery step. A `ReasonCode` alone is not an acceptable user-facing message. |
| **Idempotency where retried** | Any command a client may retry carries a `ClientReference` and is idempotent on it (`OFF-001`, `OFF-017`). |
| **Reason where consequential** | Refund, void, waiver, cancellation, failed delivery, reschedule, variance acknowledgement, revocation, and non-collection all require a `ReasonCode` plus free text. |
| **Audit in the same transaction** | For financial and security-relevant commands, the audit write shares the transaction. No audit, no action (`FIN-038`). |
| **Fail closed** | An unresolvable tenant context, an unwritable audit entry, or an unverifiable permission all produce rejection, never a permissive default. |

---

## 2. Command register by context

### 2.1 Identity and Access

| Command | Actor | Preconditions | Emits |
| --- | --- | --- | --- |
| `RequestOtp` | Anyone with a phone number | Rate limit not exceeded; brute-force backoff respected | `OtpRequested` |
| `VerifyOtp` | The requester | OTP unexpired, unused, matching | `OtpVerified` |
| `IssueSession` | System | OTP verified | `SessionIssued` |
| `RevokeSession` | The user, a tenant admin, or a platform admin | Authorised | `SessionRevoked` |
| `RegisterDevice` | Authenticated user | Session valid | `DeviceRegistered` |
| `RevokeDevice` | The user or an admin | Authorised | `DeviceRevoked` |

The OTP value never appears in a command log, an event payload, or an audit entry (`NOT-016`).

### 2.2 Tenant and Organization

| Command | Actor | Preconditions | Emits |
| --- | --- | --- | --- |
| `RegisterTenant` | Platform admin | Provisioning request authorised | `TenantRegistered` |
| `SuspendTenant` / `ReactivateTenant` | Platform admin | Audited, reasoned | `TenantSuspended` / `TenantReactivated` |
| `CreateBrand` / `ArchiveBrand` | Owner, tenant admin | Within tenant | `BrandCreated` / `BrandArchived` |
| `OpenOutlet` / `CloseOutlet` | Owner, tenant admin | Brand active | `OutletOpened` / `OutletClosed` |
| `GrantMembership` | Owner, tenant admin | Granting actor's own authority covers the grant (`TEN-006`) | `MembershipGranted` |
| `ChangeMembershipRoles` | Owner, tenant admin | Version matches; no privilege escalation beyond the granter | `MembershipRoleChanged` |
| `RevokeMembership` | Owner, tenant admin | — | `MembershipRevoked` |
| `SwitchTenantContext` | Any multi-tenant member | Target membership verified server-side | `TenantContextSwitched` |

### 2.3 Customer Management

| Command | Actor | Preconditions | Emits |
| --- | --- | --- | --- |
| `CreateCustomerProfile` | Kasir, laundry admin | Tenant-scoped; **no cross-tenant lookup exists to call** (`TEN-011`) | `CustomerProfileCreated` |
| `UpdateCustomerContact` | Kasir, laundry admin | Version matches | `CustomerContactUpdated` |
| `AddCustomerAddress` / `ArchiveCustomerAddress` | Kasir, customer | — | `CustomerAddressAdded` / `CustomerAddressArchived` |
| `GrantMarketingConsent` | The customer, or staff recording an explicit customer instruction with a source | Consent is specific and informed | `CustomerConsentGranted` |
| `WithdrawMarketingConsent` | The customer, or staff on request | Always accepted, never refused | `CustomerConsentWithdrawn` |

**A merge command does not exist in this model.** There is no `MergeCustomerProfiles`, no
`LinkCustomerAcrossTenants`, and no deduplication job keyed on name, email, phone, device, or shared
ownership (`TEN-012`).

### 2.4 Service Catalog and Pricing

| Command | Actor | Preconditions | Emits |
| --- | --- | --- | --- |
| `PublishCatalogItem` / `RetireCatalogItem` | Tenant admin, owner | — | `ServiceCatalogItemPublished` / `ServiceCatalogItemRetired` |
| `PublishPriceListVersion` | Tenant admin, owner | Effective period does not overlap an existing published version | `PriceListVersionPublished` |
| `AddPriceRule` / `RetirePriceRule` | Tenant admin, owner | Integer arithmetic; rounding defined | `PriceRuleAdded` / `PriceRuleRetired` |
| `QuotePrice` | Order Intake (system) | Catalog item published; price list effective at the quote instant | — (returns a snapshot) |

**There is no `EditPublishedPriceList` command.** A published version is immutable; a change publishes
a new version (`FIN-012`).

### 2.5 Order Intake and POS

| Command | Actor | Preconditions | Emits |
| --- | --- | --- | --- |
| `DraftOrder` | Kasir, laundry admin | `ClientReference` present | `OrderDrafted` |
| `AddOrderLine` / `RemoveOrderLine` | Kasir | Order in `DRAFT` | `OrderLineAdded` / `OrderLineRemoved` |
| `CaptureConditionEvidence` | Kasir | Order exists | `OrderConditionEvidenceCaptured` |
| `ConfirmOrderIntake` | Kasir | At least one line; price snapshot taken; total computed server-side (`FIN-015`) | `OrderPriceSnapshotTaken`, `OrderReceived` |
| `TransitionOrderStatus` | Role varies per transition | The transition is **explicitly enumerated** in the order state machine | `OrderStatusChanged`, and on the first ready transition also `OrderReachedReadyForPickupFirstTime` |
| `FlagOrderIssue` / `ResolveOrderIssue` | Kasir, manager outlet | `ReasonCode` present | `OrderFlaggedAsIssue` / `OrderIssueResolved` |
| `CancelOrder` | Manager outlet, laundry admin with permission | `ReasonCode` and reason text; any captured money handled by reversal (`FIN-008`) | `OrderCancelled` |
| `CompleteOrder` | Kasir, kurir | Handover recorded, or delivery proof captured | `OrderCompleted` |

**There is no `SetOrderStatus` free-form command.** Every transition is named and enumerated.

### 2.6 Production and Quality Control

| Command | Actor | Preconditions | Emits |
| --- | --- | --- | --- |
| `CreateProductionJob` | System, on `OrderReceived` | Order accepted | `ProductionJobCreated` |
| `StartStage` / `CompleteStage` | Operator produksi | Preceding stage complete; `ClientReference` present | `ProductionStageStarted` / `ProductionStageCompleted` |
| `BlockProductionJob` / `ResumeProductionJob` | Operator produksi, manager outlet | `ReasonCode` present | `ProductionJobBlocked` / `ProductionJobResumed` |
| `SendToQualityControl` | Operator produksi | All stages complete | `QualityControlInspectionOpened` |
| `RecordInspectionPassed` | Quality control | Inspection open | `QualityControlPassed` |
| `RecordInspectionFailed` | Quality control | Defect recorded | `QualityControlFailedReworkRequired`, `ReworkRequested` |
| `WaiveInspectionWithAuthorization` | Manager outlet holding the waiver permission | **Permission + `ReasonCode` + reason text + audit entry — all four, always** | `QualityControlWaived`, `AuditEntryAppended` |

### 2.7 Payment and Receivables

| Command | Actor | Preconditions | Emits |
| --- | --- | --- | --- |
| `RecordPaymentIntent` | Kasir (may be offline) | `ClientReference` present | `PaymentIntentRecorded` |
| `CapturePayment` | Kasir (in person) or system (gateway verified) | Server-side authorisation; lock held on the order (`FIN-016`); **never on a client claim** (`FIN-005`) | `PaymentCaptured`, or `PaymentDuplicateSuppressed` on a repeated reference |
| `VerifyGatewayCallback` | System | Signature, amount, currency, status all verified against the gateway; replay rejected | `PaymentGatewayCallbackVerified` / `PaymentGatewayCallbackRejected` |
| `RequestRefund` | Kasir, finance with permission | `ReasonCode` + reason text | `RefundRequested` |
| `ApproveRefund` / `RejectRefund` | Manager outlet, finance | Separation of duties where tenant policy requires it | `RefundApproved` / `RefundRejected` |
| `SettleRefund` | Finance, system | Approved; amount within captured net of prior refunds (`FIN-020`) | `RefundSettled` |
| `PostAdjustmentEntry` | Finance with permission | Reason recorded; original preserved | `AdjustmentEntryPosted` |
| `OpenCashierShift` / `CloseCashierShift` | Kasir, manager outlet | Count completed before close | `CashierShiftOpened` / `CashierShiftClosed` |
| `AcknowledgeVariance` | Manager outlet, finance | Reason recorded where beyond threshold | `CashierShiftVarianceRecorded` |

**There is no `DeletePayment` command, no `EditCapturedAmount` command, and no `AdjustVarianceToZero`
command.** Their absence is the invariant (`FIN-007`, `FIN-026`).

### 2.8 Customer Tracking

| Command | Actor | Preconditions | Emits |
| --- | --- | --- | --- |
| `IssueTrackingAccess` | System on `OrderReceived`, or kasir on request | A **new** high-entropy token generated; never derived from the order number (`TRK-003`) | `TrackingAccessIssued` |
| `ResolveTrackingToken` | Unauthenticated portal visitor | Rate limit not exceeded; token active and unexpired | `TrackingAccessViewed` or `TrackingAccessThrottled` |
| `ChallengeWithOtp` | Portal visitor attempting a sensitive action | Action is on the sensitive list (`TRK-012`) | `TrackingAccessOtpChallenged` |
| `VerifyPortalOtp` | Portal visitor | OTP valid | `TrackingAccessOtpVerified` |
| `RevokeTrackingAccess` | Customer, kasir, manager outlet | `ReasonCode` recorded | `TrackingAccessRevoked` |
| `ExpireTrackingAccess` | System | Expiry reached — default 30 days after order completion (`TRK-005`) | `TrackingAccessExpired` |

### 2.9 Pickup, Delivery, and Courier

| Command | Actor | Preconditions | Emits |
| --- | --- | --- | --- |
| `RequestPickup` | Customer (app or portal) or staff | Address in a covered zone | `PickupRequested` |
| `ConfirmJob` / `ScheduleJob` | Kasir, manager outlet | `TimeWindow` set, never an exact minute (`DEL-004`) | `PickupConfirmed` / `JobScheduled` |
| `AssignInternalCourier` | Manager outlet | Courier holds a `Membership` | `CourierAssigned` |
| `AssignExternalCourier` | Manager outlet | **No membership created**; a guest job link is issued instead (`DEL-007`) | `CourierAssigned`, `GuestJobLinkIssued` |
| `IssueGuestJobLink` | Manager outlet | High-entropy, hashed, expiring, revocable, minimal scope (`DEL-008`) | `GuestJobLinkIssued` |
| `RevokeGuestJobLink` | Manager outlet | Immediate effect (`DEL-033`) | `GuestJobLinkRevoked` |
| `OrderStopsAsSuggestion` | Manager outlet, system | Presented as *usulan rute*; **never as an optimal route** (`DEL-005`) | — |
| `StartJob` / `RecordArrival` | Kurir or external courier | `ClientReference` present; offline-capable | `JobEnRoute` / `CourierArrived` |
| `CaptureProof` | Kurir or external courier | Proof method per tenant policy | `DeliveryProofCaptured` |
| `RecordPickup` / `RecordDelivery` | Kurir or external courier | **Proof captured** (`DEL-002`, `DEL-027`); arrival recorded (`DEL-028`) | `ParcelPickedUp` / `ParcelDelivered` |
| `RecordJobFailure` | Kurir or external courier | `ReasonCode` + reason text (`DEL-003`) | `JobFailed` |
| `RescheduleJob` / `CancelJob` | Kasir, manager outlet | Reason recorded; chain preserved (`DEL-022`) | `JobRescheduled` / `JobCancelled` |
| `RecordCashCollected` | Kurir | `ClientReference` present; integer Rupiah (`FIN-027`) | `CourierCashCollected` |
| `SubmitCourierSettlement` | Kurir | All collections recorded | `CourierSettlementSubmitted` |
| `RecordSettlementVariance` | Manager outlet, finance | Reason recorded | `CourierSettlementVarianceRecorded` |
| `AcceptCourierSettlement` | Manager outlet, finance | **No unacknowledged variance** (`DEL-030`) | `CourierSettlementAccepted` |

### 2.10 Unclaimed Laundry Recovery

| Command | Actor | Preconditions | Emits |
| --- | --- | --- | --- |
| `OpenUnclaimedCase` | System | Triggered **only** by `OrderReachedReadyForPickupFirstTime`; idempotent per order (`UCL-015`) | `UnclaimedCaseOpened` |
| `ScheduleReminderLadder` | System | Anchored to the immutable first-ready timestamp (`UCL-005`) | `ReminderScheduled` |
| `FireReminderStage` | System (scheduler) | Stage due; not already fired; quiet hours and opt-out evaluated (`UCL-004`, `UCL-006`) | `ReminderStageFired` |
| `CreateFollowUpTask` | System at H+7 | — | `FollowUpTaskCreated` |
| `AssignFollowUpTask` / `CloseFollowUpTask` | Manager outlet, laundry admin | Named owner (`UCL-029`) | `FollowUpTaskAssigned` / `FollowUpTaskClosed` |
| `EscalateCase` | System at H+14 | Reaches a manager or owner (`UCL-010`) | `UnclaimedCaseEscalated` |
| `RecordReasonNotCollected` | Kasir, laundry admin | Free-text plus `ReasonCode` (`UCL-011`) | `ReasonNotCollectedRecorded` |
| `ProposeDeliveryAsRecovery` | Manager outlet | Customer contactable | — (creates a `PickupDeliveryJob`) |
| `CloseUnclaimedCase` | System on collection, or staff with a reason | Outcome recorded (`UCL-022`) | `UnclaimedCaseClosed` |

**Commands that must never exist in this context.** `DiscardLaundry`, `SellUnclaimedLaundry`,
`AuctionUnclaimedLaundry`, `DonateUnclaimedLaundry`, `TransferLaundryOwnership`, or any equivalent
under any name, behind any flag, at any age, at any unpaid balance, or on any tenant request. A
proposal to add one is refused outright and escalated to the repository owner (`UCL-013`, `UCL-026`,
`UCL-027`).

### 2.11 Notification

| Command | Actor | Preconditions | Emits |
| --- | --- | --- | --- |
| `RequestNotification` | Any business context, asynchronously | Event occurred | `NotificationRequested` |
| `EvaluateSendPolicy` | System | Deduplication key computed; consent read at send time | `NotificationDeduplicated` or `NotificationSuppressedByOptOut` |
| `DeferForQuietHours` | System | Outlet local time within 20.00–08.00 and the message is non-urgent (`NOT-003`, `NOT-004`) | `NotificationDeferredForQuietHours` |
| `DispatchMessage` | System | Outside quiet hours; not a duplicate; consent satisfied | `NotificationDispatched` |
| `RecordDeliveryOutcome` | System | Provider responded | `NotificationDelivered` / `NotificationFailed` |
| `OfferManualDeepLinkFallback` | System, surfaced to staff | Automated path unavailable; presented explicitly, never as automation (`NOT-007`) | `ManualDeepLinkFallbackOffered` |

### 2.12 Offline Synchronization

| Command | Actor | Preconditions | Emits |
| --- | --- | --- | --- |
| `QueueOfflineOperation` | Ops Android client | `ClientReference` generated **once**, before the first attempt (`OFF-001`) | `OfflineOperationQueued` |
| `RetryOfflineOperation` | Client | **Same** reference; exponential backoff (`OFF-003`, `OFF-025`) | `OfflineOperationRetried` |
| `SubmitOfflineOperation` | Client | Predecessor operations succeeded (`OFF-009`) | — |
| `AcceptOfflineOperation` / `RejectOfflineOperation` | Server | Tenant and user context matches capture context (`OFF-016`) | `OfflineOperationAccepted` / `OfflineOperationRejected` |
| `RaiseSyncConflict` | Server or client | Divergence detected | `SyncConflictDetected` |
| `ResolveSyncConflictByHuman` | Kasir, manager outlet | **Mandatory for any money conflict** (`OFF-011`) | `SyncConflictResolvedByHuman` |
| `AuthorizeFinancialQueuePurge` | Manager outlet or owner with an explicit permission | Audited; never triggered by cache clear, logout, or upgrade (`OFF-004`, `OFF-024`) | `OfflineQueuePurgeAuthorized` |

---

## 3. Policy register

A policy is written as **`ON <event> WHEN <precondition> DO <command>`**. Every policy is idempotent;
a replayed event does not fire it twice.

### 3.1 Order and production policies

| # | Policy |
| --- | --- |
| P-01 | `ON OrderReceived` `DO CreateProductionJob` and `DO IssueTrackingAccess` and `DO RequestNotification(order_received)`. |
| P-02 | `ON ProductionStageCompleted` `WHEN` it is the final stage `DO SendToQualityControl`. |
| P-03 | `ON QualityControlFailedReworkRequired` `DO TransitionOrderStatus(REWORK)` and `DO ReworkRequested`. Aging is **not** touched (`UCL-017`). |
| P-04 | `ON QualityControlPassed` **or** `QualityControlWaived` `DO TransitionOrderStatus(READY_FOR_PICKUP)`. No other path reaches ready. |
| P-05 | `ON OrderStatusChanged(READY_FOR_PICKUP)` `WHEN` no first-ready timestamp exists `DO` record it once, immutably, and emit `OrderReachedReadyForPickupFirstTime`. `WHEN` one already exists `DO` nothing to aging (`UCL-002`). |

### 3.2 Unclaimed laundry policies

| # | Policy |
| --- | --- |
| P-06 | `ON OrderReachedReadyForPickupFirstTime` `DO OpenUnclaimedCase` and `DO ScheduleReminderLadder`. |
| P-07 | `ON` scheduler tick `WHEN` a stage is due and unfired `DO FireReminderStage`. Each stage fires exactly once (`UCL-004`). |
| P-08 | `ON ReminderStageFired(H+7)` `DO CreateFollowUpTask` — a real assignable task, not a flag (`UCL-009`). |
| P-09 | `ON ReminderStageFired(H+14)` `DO EscalateCase` to a manager or owner, as an internal in-product notification (`NOT-025`). |
| P-10 | `ON OrderCompleted` `DO CloseUnclaimedCase` with the collection outcome. |
| P-11 | `ON NotificationFailed` for a reminder `DO` retry under a bounded policy and surface the failure. **`DO NOT` alter the order or the case state** (`UCL-007`, `NOT-001`). |

### 3.3 Payment policies

| # | Policy |
| --- | --- |
| P-12 | `ON OrderReceived` `WHEN` a balance remains `DO OpenReceivable`. |
| P-13 | `ON PaymentCaptured` `DO AllocatePaymentToOrder` and `DO RequestNotification(payment_received)`. |
| P-14 | `ON` a repeated `ClientReference` `DO` return the original result and emit `PaymentDuplicateSuppressed`. Never create a second payment (`FIN-003`). |
| P-15 | `ON PaymentGatewayCallbackRejected` `DO` record and alert. **`DO NOT` change the order's payment state.** |
| P-16 | `ON ParcelDelivered` `WHEN` cash was collected `DO RecordCashCollected` against the courier settlement (`FIN-027`). |
| P-17 | `ON CashierShiftClosed` `WHEN` a variance exists `DO RecordVariance` and require acknowledgement. Never auto-adjust (`FIN-026`). |

### 3.4 Delivery policies

| # | Policy |
| --- | --- |
| P-18 | `ON JobScheduled` `DO RequestNotification(pickup_scheduled)` including the `TimeWindow` — never an ETA (`DEL-026`). |
| P-19 | `ON CourierAssigned` `WHEN` the courier is external `DO IssueGuestJobLink` scoped to the single job (`DEL-008`). |
| P-20 | `ON JobCancelled` or `JobFailed` `DO RevokeGuestJobLink` immediately (`DEL-033`). |
| P-21 | `ON JobFailed` `DO TransitionOrderStatus` back to the defined return status and `DO RequestNotification(delivery_failed)` (`DEL-031`). |
| P-22 | `ON ParcelDelivered` `DO CompleteOrder` **only if** proof was captured (`DEL-027`). |

### 3.5 Tracking policies

| # | Policy |
| --- | --- |
| P-23 | `ON TrackingAccessIssued` `DO RequestNotification(tracking_link)`. The message contains the link and **never** an OTP alongside it (`TRK-029`, `NOT-014`). |
| P-24 | `ON OrderCompleted` `DO` schedule `ExpireTrackingAccess` for 30 days later (`TRK-005`). |
| P-25 | `ON` repeated failed token resolutions from one source `DO` throttle and emit `TrackingAccessThrottled` (`TRK-007`). |
| P-26 | `ON OrderStatusChanged` `DO` rebuild the public projection from the **allow-listed safe field set** (`TRK-028`). |

### 3.6 Tenancy and offline policies

| # | Policy |
| --- | --- |
| P-27 | `ON MembershipRevoked` `DO RevokeSession` for that membership and `DO` partition or clear the device's cache for that tenant (`TEN-014`, `OFF-006`). |
| P-28 | `ON TenantContextSwitched` `DO` clear or partition client caches so that no previous-tenant data remains visible (`OFF-020`). |
| P-29 | `ON OfflineOperationAccepted` `DO` reconcile local state to the server response (`OFF-005`). |
| P-30 | `ON SyncConflictDetected` `WHEN` the conflict involves money `DO PresentConflictToHuman`. **Never auto-resolve** (`OFF-010`, `OFF-011`). |
| P-31 | `ON SubscriptionLapsed` `DO` restrict features and `DO` keep `RequestTenantDataExport` available (`TEN-018`, `TEN-028`). |
| P-32 | `ON PlanLimitExceededFairUse` `DO RequestNotification` and surface a plan recommendation. **`DO NOT` stop order intake** (`TEN-019`). |

---

## 4. Policies that must never be written

These are recorded explicitly so that no future contributor mistakes their absence for an oversight.

| Forbidden policy | Rule violated |
| --- | --- |
| `ON NotificationFailed DO CancelOrder` (or block, or fail, any business aggregate) | `NOT-001`, `NOT-029` |
| `ON PaymentGatewayCallbackReceived DO MarkOrderPaid` without server-side verification | `FIN-004`, `FIN-005` |
| `ON PriceListVersionPublished DO RecalculateExistingOrders` | `FIN-012`, `FIN-036` |
| `ON OrderStatusChanged(READY_FOR_PICKUP) DO ResetAging` | `UCL-002`, `UCL-017` |
| `ON UnclaimedCaseAged(N) DO DiscardLaundry` / `DO SellLaundry` / `DO TransferOwnership` | `UCL-013`, `UCL-026`, `UCL-027` |
| `ON CustomerProfileCreated WHEN phone matches another tenant DO MergeProfiles` | `TEN-012` |
| `ON SyncConflictDetected DO ResolveByLastWrite` for a money conflict | `OFF-010`, `OFF-011` |
| `ON CacheCleared DO PurgeFinancialQueue` | `OFF-004`, `OFF-024` |
| `ON SubscriptionLapsed DO BlockDataExport` | `TEN-018`, `TEN-028` |
| `ON QuietHours DO SendAnyway` without an explicitly recorded exception | `NOT-003`, `NOT-021`, `NOT-022` |

---

## 5. Status

No command handler, policy, saga, scheduler, or process manager exists. Everything above is
`NOT IMPLEMENTED`. Backend runtime is `ABSENT`. This document claims no test, build, deployment, CI
run, or UAT.

---

## Related documents

- [`DOMAIN_EVENTS.md`](DOMAIN_EVENTS.md)
- [`AGGREGATE_CATALOG.md`](AGGREGATE_CATALOG.md)
- [`DOMAIN_INVARIANTS.md`](DOMAIN_INVARIANTS.md)
