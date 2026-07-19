# Bounded Contexts — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** `NOT IMPLEMENTED` (documentation only; backend runtime `ABSENT`)
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.0.1

The domain is divided into **twenty bounded contexts**. The names below are canonical for Step 1 and
are not renamed, merged, or split without a decision record. Each context maps onto a module boundary
in the eventual Laravel modular monolith (Master Source §6.2): a module owns its data, exposes an
explicit interface, and never reaches into another module's tables.

The contexts and their interactions are drawn in [`CONTEXT_MAP.md`](CONTEXT_MAP.md).

**Every context below is `NOT IMPLEMENTED`.** The "implementation step target" column records the
canonical Step that will first deliver it, per Master Source §8 and §22.

---

## Index

| # | Bounded context | First delivered in |
| --- | --- | --- |
| 1 | Identity and Access | Step 3 |
| 2 | Tenant and Organization | Step 3 |
| 3 | Subscription and Entitlement | Step 12 |
| 4 | Customer Management | Step 4 |
| 5 | Service Catalog and Pricing | Step 4 |
| 6 | Order Intake and POS | Step 5 |
| 7 | Production Operations | Step 6 |
| 8 | Quality Control and Rework | Step 6 |
| 9 | Payment and Receivables | Step 5 |
| 10 | Customer Tracking | Step 7 |
| 11 | Pickup and Delivery | Step 8 |
| 12 | Courier Assignment and Settlement | Step 8 |
| 13 | Notification and Communication | Step 7 |
| 14 | Unclaimed Laundry Recovery | Step 9 |
| 15 | Loyalty, Membership, and Deposit | Step 11 |
| 16 | Reporting and Owner Portfolio | Step 10 |
| 17 | Audit and Compliance | Step 3 (extended each Step) |
| 18 | Platform Administration | Step 12 |
| 19 | Offline Synchronization | Step 5 |
| 20 | File and Evidence Management | Step 6 |

---

## 1. Identity and Access

- **Purpose.** Establish who a person is, and issue authenticated sessions and devices. Identity is
  platform-wide; authority is not.
- **Owner.** Platform engineering, under the tenancy hard gate.
- **Primary actors.** Any human user; the OTP delivery pathway; session and device revocation
  operators.
- **Aggregates.** `MembershipRegistration` view over `Membership` (owned by context 2); the User
  Account identity record; session and device records.
- **Commands.** `RequestOtp`, `VerifyOtp`, `IssueSession`, `RevokeSession`, `RegisterDevice`,
  `RevokeDevice`.
- **Events.** `OtpRequested`, `OtpVerified`, `SessionIssued`, `SessionRevoked`, `DeviceRegistered`,
  `DeviceRevoked`, `AuthenticationFailed`.
- **Upstream contexts.** None. This context is the root of trust.
- **Downstream contexts.** All authenticated contexts.
- **Synchronous dependencies.** None internal; OTP delivery is asynchronous and failure-tolerant.
- **Asynchronous dependencies.** Notification and Communication (OTP delivery), Audit and Compliance.
- **Tenant boundary.** A User Account is **platform-scoped**, deliberately. It grants **no** business
  authority. Every business capability is derived from a `Membership` in context 2. A session carries
  an identity, not a tenant grant.
- **Sensitive data.** Phone numbers, OTP values, session tokens, device identifiers. OTP values and
  tokens are never logged and never stored in plaintext.
- **Failure impact.** Nobody can authenticate. No data is exposed and no money moves. Degraded, not
  dangerous.
- **Implementation step target.** Step 3. Status `NOT IMPLEMENTED`.

## 2. Tenant and Organization

- **Purpose.** Own the isolation boundary: tenants, brands, outlets, memberships, roles, permissions,
  and tenant context derivation.
- **Owner.** Platform engineering; the hard-gate owner for tenant isolation.
- **Primary actors.** Owner, tenant admin, platform admin.
- **Aggregates.** `Tenant`, `Membership`, `LaundryBrand`, `Outlet`.
- **Commands.** `RegisterTenant`, `SuspendTenant`, `ReactivateTenant`, `CreateBrand`, `ArchiveBrand`,
  `OpenOutlet`, `CloseOutlet`, `GrantMembership`, `ChangeMembershipRoles`, `RevokeMembership`,
  `SwitchTenantContext`.
- **Events.** `TenantRegistered`, `TenantSuspended`, `TenantReactivated`, `BrandCreated`,
  `BrandArchived`, `OutletOpened`, `OutletClosed`, `MembershipGranted`, `MembershipRoleChanged`,
  `MembershipRevoked`, `TenantContextSwitched`.
- **Upstream contexts.** Identity and Access.
- **Downstream contexts.** Every other context without exception.
- **Synchronous dependencies.** Identity and Access (who is asking).
- **Asynchronous dependencies.** Audit and Compliance, Subscription and Entitlement.
- **Tenant boundary.** **This context defines the boundary.** It is the only place where the mapping
  from an authenticated identity to a permitted tenant scope is computed, and that computation is
  always server-side. A client-supplied tenant identifier is an untrusted hint.
- **Sensitive data.** Membership graphs, role assignments, outlet addresses.
- **Failure impact.** Total. Every business query depends on tenant context resolution; the system
  fails **closed** — no context, no data.
- **Implementation step target.** Step 3. Status `NOT IMPLEMENTED`.

## 3. Subscription and Entitlement

- **Purpose.** Hold the tenant's plan, trial state, fair-use usage, entitlement evaluation, and lapse
  behaviour.
- **Owner.** Commercial and platform engineering.
- **Primary actors.** Owner, tenant admin, platform admin.
- **Aggregates.** `Subscription`.
- **Commands.** `StartTrial`, `ActivateSubscription`, `ChangePlan`, `RecordSubscriptionPayment`,
  `MarkSubscriptionLapsed`, `SuspendSubscription`, `ReactivateSubscription`, `CancelSubscription`,
  `RequestTenantDataExport`.
- **Events.** `SubscriptionTrialStarted`, `SubscriptionActivated`, `SubscriptionPlanChanged`,
  `SubscriptionPaymentFailed`, `SubscriptionLapsed`, `SubscriptionSuspended`,
  `SubscriptionReactivated`, `SubscriptionCancelled`, `PlanLimitApproached`,
  `PlanLimitExceededFairUse`, `TenantDataExportRequested`.
- **Upstream contexts.** Tenant and Organization.
- **Downstream contexts.** Order Intake and POS (fair-use signalling), Reporting and Owner Portfolio,
  Platform Administration.
- **Synchronous dependencies.** Tenant and Organization.
- **Asynchronous dependencies.** Notification and Communication, Audit and Compliance.
- **Tenant boundary.** One `Subscription` per tenant. Subscription and billing operate **at the
  tenant boundary** — never per user, never per outlet (Master Source §4.2 rule 6).
- **Sensitive data.** Commercial terms and billing identifiers. Pricing itself is public
  (Master Source §21.6).
- **Failure impact.** Entitlement evaluation degrades. **A laundry is never stopped mid-shift** by an
  entitlement failure; fair-use ceilings trigger a conversation, not a service cut (Master Source
  §21.5). Tenant data remains exportable when a subscription lapses.
- **Implementation step target.** Step 12. Status `NOT IMPLEMENTED`.

## 4. Customer Management

- **Purpose.** Own the tenant's customer profiles, contacts, addresses, consent state, and per-tenant
  customer history.
- **Owner.** Product engineering.
- **Primary actors.** Kasir, laundry admin, manager outlet, the customer themselves.
- **Aggregates.** `Customer`, `CustomerAddress`.
- **Commands.** `CreateCustomerProfile`, `UpdateCustomerContact`, `AddCustomerAddress`,
  `ArchiveCustomerAddress`, `GrantMarketingConsent`, `WithdrawMarketingConsent`.
- **Events.** `CustomerProfileCreated`, `CustomerContactUpdated`, `CustomerAddressAdded`,
  `CustomerAddressArchived`, `CustomerConsentGranted`, `CustomerConsentWithdrawn`.
- **Upstream contexts.** Tenant and Organization.
- **Downstream contexts.** Order Intake and POS, Notification and Communication, Pickup and Delivery,
  Unclaimed Laundry Recovery, Loyalty.
- **Synchronous dependencies.** Tenant and Organization.
- **Asynchronous dependencies.** Audit and Compliance.
- **Tenant boundary.** **Customer profiles are tenant-scoped, absolutely.** The same phone number in
  two tenants produces two unrelated profiles. Profiles are **never** merged, deduplicated, or linked
  because name, email, phone, device fingerprint, or shared ownership match. There is no global
  shared customer profile by default, and creating one would require an owner decision record.
- **Sensitive data.** Names, phone numbers, addresses, consent history, order history. Masked per
  context.
- **Failure impact.** Order intake cannot identify a returning customer. Orders can still be taken;
  no money and no isolation property is at risk.
- **Implementation step target.** Step 4. Status `NOT IMPLEMENTED`.

## 5. Service Catalog and Pricing

- **Purpose.** Own what a brand sells and what it costs, versioned over time, and produce the
  **price snapshot** consumed by order intake.
- **Owner.** Product engineering, under the financial hard gate.
- **Primary actors.** Tenant admin, owner, manager.
- **Aggregates.** `ServiceCatalog`, `PriceList`, `PriceRule`.
- **Commands.** `PublishCatalogItem`, `RetireCatalogItem`, `PublishPriceListVersion`, `AddPriceRule`,
  `RetirePriceRule`, `QuotePrice`.
- **Events.** `ServiceCatalogItemPublished`, `ServiceCatalogItemRetired`, `PriceListVersionPublished`,
  `PriceRuleAdded`, `PriceRuleRetired`.
- **Upstream contexts.** Tenant and Organization.
- **Downstream contexts.** Order Intake and POS, Payment and Receivables, Reporting.
- **Synchronous dependencies.** Tenant and Organization. Order intake calls `QuotePrice`
  synchronously and copies the result.
- **Asynchronous dependencies.** Audit and Compliance.
- **Tenant boundary.** Catalogs and price lists belong to a brand, which belongs to a tenant.
- **Sensitive data.** Commercial pricing per tenant. Competitive; cross-tenant exposure of a price
  list is a business-ending event for the tenant exposed.
- **Failure impact.** New orders cannot be quoted. **Existing orders are unaffected** — they carry
  their own price snapshot and never re-read the catalog.
- **Implementation step target.** Step 4. Status `NOT IMPLEMENTED`.

## 6. Order Intake and POS

- **Purpose.** Accept laundry, price it against an immutable snapshot, produce the nota, and own the
  order lifecycle.
- **Owner.** Product engineering.
- **Primary actors.** Kasir, laundry admin, manager outlet, and (for pickup-originated orders) the
  customer.
- **Aggregates.** `LaundryOrder`, `OrderLine`, `OrderConditionEvidence`.
- **Commands.** `DraftOrder`, `AddOrderLine`, `RemoveOrderLine`, `CaptureConditionEvidence`,
  `ConfirmOrderIntake`, `TransitionOrderStatus`, `CancelOrder`, `FlagOrderIssue`, `ResolveOrderIssue`,
  `CompleteOrder`.
- **Events.** `OrderDrafted`, `OrderLineAdded`, `OrderLineRemoved`, `OrderPriceSnapshotTaken`,
  `OrderConditionEvidenceCaptured`, `OrderReceived`, `OrderStatusChanged`,
  `OrderReachedReadyForPickupFirstTime`, `OrderCompleted`, `OrderCancelled`, `OrderFlaggedAsIssue`,
  `OrderIssueResolved`.
- **Upstream contexts.** Customer Management, Service Catalog and Pricing, Tenant and Organization,
  Offline Synchronization.
- **Downstream contexts.** Production Operations, Payment and Receivables, Customer Tracking,
  Notification and Communication, Pickup and Delivery, Unclaimed Laundry Recovery, Reporting.
- **Synchronous dependencies.** Service Catalog and Pricing (quote), Customer Management (identify),
  Payment and Receivables (in-person payment within the same business transaction).
- **Asynchronous dependencies.** Notification, Tracking issuance, Production job creation, Audit.
- **Tenant boundary.** Every order carries `tenant_id`, brand, and outlet. Human order numbers are
  outlet-scoped and are **never** an access credential.
- **Sensitive data.** Customer identity, garment photographs, amounts due.
- **Failure impact.** The counter stops taking orders online. Offline intake continues via context 19
  and reconciles later; this is exactly the case the offline design exists for.
- **Implementation step target.** Step 5. Status `NOT IMPLEMENTED`.

## 7. Production Operations

- **Purpose.** Move laundry through sorting, washing, drying, and finishing, and record stage
  progress honestly.
- **Owner.** Product engineering.
- **Primary actors.** Operator produksi, manager outlet.
- **Aggregates.** `ProductionJob`.
- **Commands.** `CreateProductionJob`, `StartStage`, `CompleteStage`, `BlockProductionJob`,
  `ResumeProductionJob`, `SendToQualityControl`.
- **Events.** `ProductionJobCreated`, `ProductionStageStarted`, `ProductionStageCompleted`,
  `ProductionJobBlocked`, `ProductionJobResumed`, `ReworkRequested`, `ReworkCompleted`.
- **Upstream contexts.** Order Intake and POS.
- **Downstream contexts.** Quality Control and Rework, Order Intake (status reflection), Notification,
  Reporting.
- **Synchronous dependencies.** Order Intake and POS (status transition authority remains with the
  order aggregate).
- **Asynchronous dependencies.** Notification, Audit, File and Evidence Management.
- **Tenant boundary.** Jobs are tenant- and outlet-scoped. Batching never groups items across
  tenants; a shared machine load across tenants is not representable in the model.
- **Sensitive data.** Item-level notes and photographs.
- **Failure impact.** Production progress is not recorded. Physical work continues; the record is
  reconstructed via the offline queue. No money is at risk.
- **Implementation step target.** Step 6. Status `NOT IMPLEMENTED`.

## 8. Quality Control and Rework

- **Purpose.** Verify finished work before an order may be declared ready, and route failures back
  into production.
- **Owner.** Product engineering.
- **Primary actors.** Quality control, manager outlet (for waivers).
- **Aggregates.** `QualityControlInspection`.
- **Commands.** `OpenInspection`, `RecordInspectionPassed`, `RecordInspectionFailed`,
  `WaiveInspectionWithAuthorization`.
- **Events.** `QualityControlInspectionOpened`, `QualityControlPassed`,
  `QualityControlFailedReworkRequired`, `QualityControlWaived`.
- **Upstream contexts.** Production Operations.
- **Downstream contexts.** Order Intake and POS, Production Operations (rework loop), Reporting.
- **Synchronous dependencies.** Production Operations.
- **Asynchronous dependencies.** Audit and Compliance (waivers are always audited), Notification.
- **Tenant boundary.** Inspections are tenant- and outlet-scoped.
- **Sensitive data.** Defect photographs; staff performance data derivable from inspection outcomes.
- **Failure impact.** Orders cannot progress to `READY_FOR_PICKUP`. This is a **safe** failure: it
  delays, it never releases unverified work.
- **Implementation step target.** Step 6. Status `NOT IMPLEMENTED`.

## 9. Payment and Receivables

- **Purpose.** Own money: payments, refunds, receivables, cashier shifts, and the append-only
  financial audit trail.
- **Owner.** Product engineering, under the financial-integrity hard gate
  ([DEC-0012](../decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md)).
- **Primary actors.** Kasir, finance, manager outlet, owner; the payment gateway as an external
  system.
- **Aggregates.** `Payment`, `Refund`, `Receivable`, `CashierShift`.
- **Commands.** `RecordPaymentIntent`, `CapturePayment`, `VerifyGatewayCallback`, `RequestRefund`,
  `ApproveRefund`, `RejectRefund`, `SettleRefund`, `PostAdjustmentEntry`, `OpenCashierShift`,
  `CloseCashierShift`, `AcknowledgeVariance`.
- **Events.** `PaymentIntentRecorded`, `PaymentCaptured`, `PaymentDuplicateSuppressed`,
  `PaymentFailed`, `PaymentGatewayCallbackVerified`, `PaymentGatewayCallbackRejected`,
  `RefundRequested`, `RefundApproved`, `RefundRejected`, `RefundSettled`, `AdjustmentEntryPosted`,
  `ReceivableOpened`, `ReceivableSettled`, `CashierShiftOpened`, `CashierShiftClosed`,
  `CashierShiftVarianceRecorded`.
- **Upstream contexts.** Order Intake and POS, Offline Synchronization.
- **Downstream contexts.** Reporting and Owner Portfolio, Unclaimed Laundry Recovery (held invoices,
  unpaid balance), Courier Assignment and Settlement, Notification.
- **Synchronous dependencies.** Order Intake and POS; the gateway for verification (never trusted
  from the callback payload alone).
- **Asynchronous dependencies.** Notification, Audit and Compliance.
- **Tenant boundary.** Every financial record carries `tenant_id` and outlet. A financial query that
  is not tenant-scoped is simultaneously a security defect and a financial defect.
- **Sensitive data.** Amounts, gateway references, reconciliation variances.
- **Failure impact.** **Highest severity.** Payments may not be capturable; offline intents queue and
  reconcile. A duplicate payment, a lost payment, or an unexplained balance change is an automatic
  `NO-GO`.
- **Implementation step target.** Step 5. Status `NOT IMPLEMENTED`.

## 10. Customer Tracking

- **Purpose.** Issue, serve, expire, and revoke public tracking access, and maintain the **separate
  masked public projection** of an order.
- **Owner.** Product engineering, under the privacy baseline.
- **Primary actors.** The customer (unauthenticated), the kasir who issues or revokes a link.
- **Aggregates.** `TrackingAccess`.
- **Commands.** `IssueTrackingAccess`, `ResolveTrackingToken`, `ChallengeWithOtp`, `VerifyPortalOtp`,
  `RevokeTrackingAccess`, `ExpireTrackingAccess`.
- **Events.** `TrackingAccessIssued`, `TrackingAccessViewed`, `TrackingAccessOtpChallenged`,
  `TrackingAccessOtpVerified`, `TrackingAccessRevoked`, `TrackingAccessExpired`,
  `TrackingAccessThrottled`.
- **Upstream contexts.** Order Intake and POS.
- **Downstream contexts.** Notification and Communication (the link is delivered in a message),
  Pickup and Delivery (portal-originated schedule changes).
- **Synchronous dependencies.** Order Intake and POS (projection source).
- **Asynchronous dependencies.** Notification, Audit and Compliance.
- **Tenant boundary.** A `TrackingAccess` grants visibility of **exactly one order** in **exactly one
  tenant**. It never lists other orders, never reveals the customer's history, and never traverses to
  another tenant. Token resolution yields a tenant context derived server-side from the stored
  record, never from the request.
- **Sensitive data.** The most exposed surface in the product. Token plaintext (never stored, never
  logged), masked names and phone numbers. **The full address is never shown.** Laundry photographs
  are never shown.
- **Failure impact.** Customers lose self-service visibility and phone the outlet. Annoying, not
  dangerous. A *leak* here, by contrast, is an automatic `NO-GO`.
- **Implementation step target.** Step 7. Status `NOT IMPLEMENTED`.

## 11. Pickup and Delivery

- **Purpose.** Own the job: request, confirmation, zone matching, scheduling, time windows, custody
  transfer, and proof.
- **Owner.** Product engineering.
- **Primary actors.** Customer, kasir, manager outlet, kurir, external ojek lokal.
- **Aggregates.** `PickupDeliveryJob`, `DeliveryProof`.
- **Commands.** `RequestPickup`, `ConfirmJob`, `ScheduleJob`, `StartJob`, `RecordArrival`,
  `RecordPickup`, `RecordDelivery`, `CaptureProof`, `RecordJobFailure`, `RescheduleJob`, `CancelJob`.
- **Events.** `PickupRequested`, `PickupConfirmed`, `JobScheduled`, `JobEnRoute`, `CourierArrived`,
  `ParcelPickedUp`, `ParcelDelivered`, `DeliveryProofCaptured`, `JobFailed`, `JobRescheduled`,
  `JobCancelled`.
- **Upstream contexts.** Order Intake and POS, Customer Management (addresses), Tenant and
  Organization (zones per outlet).
- **Downstream contexts.** Courier Assignment and Settlement, Payment and Receivables (cash at the
  door), Notification, Order Intake (status reflection), Unclaimed Laundry Recovery (delivery as a
  recovery action).
- **Synchronous dependencies.** Order Intake and POS.
- **Asynchronous dependencies.** Notification, File and Evidence Management, Audit.
- **Tenant boundary.** Jobs, zones, and proofs are tenant-scoped. A courier working for two tenants
  holds two unrelated assignments and can never traverse from one to the other.
- **Sensitive data.** Full customer addresses (staff-only), geolocation, proof photographs and
  signatures. All private, signed-URL only, never on the public portal.
- **Failure impact.** Scheduling stops; couriers continue with queued offline capture. A proof lost
  is a dispute the tenant cannot win — proof capture is therefore mandatory and offline-capable.
- **Implementation step target.** Step 8. Status `NOT IMPLEMENTED`.

## 12. Courier Assignment and Settlement

- **Purpose.** Assign couriers (internal and external), issue and revoke guest job links, order stops
  as a **suggestion**, and reconcile courier cash.
- **Owner.** Product engineering, under the financial-integrity hard gate.
- **Primary actors.** Manager outlet, kurir, external ojek lokal, finance.
- **Aggregates.** `CourierAssignment`, `CourierSettlement`.
- **Commands.** `AssignInternalCourier`, `AssignExternalCourier`, `IssueGuestJobLink`,
  `RevokeGuestJobLink`, `OrderStopsAsSuggestion`, `OpenCourierSettlement`, `RecordCashCollected`,
  `SubmitCourierSettlement`, `AcceptCourierSettlement`, `RecordSettlementVariance`.
- **Events.** `CourierAssigned`, `GuestJobLinkIssued`, `GuestJobLinkRevoked`, `CourierCashCollected`,
  `CourierSettlementOpened`, `CourierSettlementSubmitted`, `CourierSettlementVarianceRecorded`,
  `CourierSettlementAccepted`.
- **Upstream contexts.** Pickup and Delivery.
- **Downstream contexts.** Payment and Receivables, Reporting and Owner Portfolio, Notification.
- **Synchronous dependencies.** Pickup and Delivery, Payment and Receivables.
- **Asynchronous dependencies.** Notification, Audit.
- **Tenant boundary.** **An external local courier receives no membership.** They hold a scoped,
  expiring, revocable, high-entropy guest job link exposing exactly one assigned job and the minimum
  data required to complete it — never customer history, never other orders, never pricing, never any
  other tenant's data. Two tenants engaging the same rider issue two unrelated links.
- **Sensitive data.** Guest link tokens (hashed at rest), cash amounts, courier accountability
  records.
- **Failure impact.** Assignment stops. Cash reconciliation gaps are financial-integrity events: a
  variance that the system cannot account for is a `NO-GO` trigger.
- **Implementation step target.** Step 8. Status `NOT IMPLEMENTED`.

## 13. Notification and Communication

- **Purpose.** Turn domain events into messages, behind a provider abstraction, honouring consent,
  quiet hours, and deduplication.
- **Owner.** Product engineering.
- **Primary actors.** The system; staff using the manual deep-link fallback; the customer as
  recipient.
- **Aggregates.** `Notification`.
- **Commands.** `RequestNotification`, `EvaluateSendPolicy`, `DeferForQuietHours`, `DispatchMessage`,
  `RecordDeliveryOutcome`, `OfferManualDeepLinkFallback`.
- **Events.** `NotificationRequested`, `NotificationDeduplicated`, `NotificationDeferredForQuietHours`,
  `NotificationSuppressedByOptOut`, `NotificationDispatched`, `NotificationDelivered`,
  `NotificationFailed`, `ManualDeepLinkFallbackOffered`.
- **Upstream contexts.** Order Intake and POS, Payment, Pickup and Delivery, Unclaimed Laundry
  Recovery, Customer Tracking, Identity and Access, Subscription.
- **Downstream contexts.** Reporting (delivery outcomes and provider cost).
- **Synchronous dependencies.** None. **Sending is always asynchronous by design**, so that no
  business transaction can be blocked by a provider.
- **Asynchronous dependencies.** The external WhatsApp Business provider; Audit.
- **Tenant boundary.** Every send records tenant, outlet, order, recipient, template, category,
  status, timestamp, and provider reference. Templates are tenant-scoped.
- **Sensitive data.** Recipient phone numbers, message bodies, tracking links. Messages never contain
  a full address, never echo an OTP alongside a tracking link, and message logs never contain token
  plaintext.
- **Failure impact.** **Deliberately bounded.** A provider failure never cancels, blocks, or alters an
  order, a payment, or a delivery. Failures are retried under a bounded policy, made visible, and
  never silently discarded. The product never promises "unlimited WhatsApp".
- **Implementation step target.** Step 7. Status `NOT IMPLEMENTED`.

## 14. Unclaimed Laundry Recovery

- **Purpose.** Detect finished-but-uncollected laundry, run the H+1 / H+3 / H+7 / H+14 ladder, create
  the follow-up task, escalate, and record why laundry was not collected.
- **Owner.** Product engineering.
- **Primary actors.** Kasir, laundry admin, manager outlet, owner.
- **Aggregates.** `UnclaimedLaundryCase`, `ReminderSchedule`.
- **Commands.** `OpenUnclaimedCase`, `ScheduleReminderLadder`, `FireReminderStage`,
  `CreateFollowUpTask`, `AssignFollowUpTask`, `CloseFollowUpTask`, `EscalateCase`,
  `RecordReasonNotCollected`, `CloseUnclaimedCase`.
- **Events.** `UnclaimedCaseOpened`, `ReminderScheduled`, `ReminderStageFired`, `FollowUpTaskCreated`,
  `FollowUpTaskAssigned`, `FollowUpTaskClosed`, `UnclaimedCaseEscalated`, `ReasonNotCollectedRecorded`,
  `UnclaimedCaseClosed`.
- **Upstream contexts.** Order Intake and POS (the first-ready timestamp), Payment and Receivables
  (held invoices, unpaid balance).
- **Downstream contexts.** Notification and Communication, Pickup and Delivery (delivery proposed as
  a recovery action), Reporting and Owner Portfolio.
- **Synchronous dependencies.** None. Aging evaluation is a scheduled background activity.
- **Asynchronous dependencies.** Notification, Audit.
- **Tenant boundary.** Cases, schedules, tasks, and dashboard aggregates are tenant-scoped. Aging
  statistics never cross a tenant boundary.
- **Sensitive data.** Customer contact details, unpaid balances, non-collection reasons.
- **Failure impact.** Reminders stop firing; laundry keeps piling up and money stays uncollected.
  Commercially serious, operationally safe.
- **Absolute prohibition.** This context **never** models automatic disposal, sale, auction,
  donation, or ownership transfer of customer laundry, in any form, at any age, under any escalation
  level. Its responsibility ends at reminding, escalating, and reporting.
- **Implementation step target.** Step 9. Status `NOT IMPLEMENTED`.

## 15. Loyalty, Membership, and Deposit

- **Purpose.** Hold customer loyalty balances, tenant-defined membership tiers, and prepaid deposit
  balances.
- **Owner.** Product engineering, under the financial-integrity hard gate for deposits.
- **Primary actors.** Customer, kasir, owner.
- **Aggregates.** Loyalty and deposit balances are modelled as ledger-backed balances owned by this
  context and are **subject to Money rules in full**. Named aggregates are deliberately deferred to
  the Step that delivers them.
- **Commands.** `AccrueLoyalty`, `RedeemLoyalty`, `TopUpDeposit`, `ConsumeDeposit`,
  `ReverseLoyaltyEntry`, `ReverseDepositEntry`.
- **Events.** `LoyaltyAccrued`, `LoyaltyRedeemed`, `DepositToppedUp`, `DepositConsumed`,
  `LoyaltyEntryReversed`, `DepositEntryReversed`.
- **Upstream contexts.** Customer Management, Order Intake and POS, Payment and Receivables.
- **Downstream contexts.** Reporting, Notification (marketing, consent-gated).
- **Synchronous dependencies.** Payment and Receivables.
- **Asynchronous dependencies.** Notification, Audit.
- **Tenant boundary.** Balances belong to a tenant customer profile and are **never** portable across
  tenants — a deposit with one tenant is not spendable with another.
- **Sensitive data.** Customer balances (money held on behalf of a customer).
- **Failure impact.** A **deposit is customer money**. Any deposit discrepancy is a financial
  integrity failure and an automatic `NO-GO`.
- **Implementation step target.** Step 11. Status `NOT IMPLEMENTED`.

## 16. Reporting and Owner Portfolio

- **Purpose.** Present consolidated, trustworthy figures across a tenant's brands and outlets.
- **Owner.** Product engineering.
- **Primary actors.** Owner, manager, finance.
- **Aggregates.** None. This context owns **projections only** and is never a system of record.
- **Commands.** `BuildReportProjection`, `RefreshPortfolioView`, `DrillDownToRecords`.
- **Events.** Consumes events; emits `ReportProjectionRefreshed`.
- **Upstream contexts.** All record-owning contexts.
- **Downstream contexts.** None.
- **Synchronous dependencies.** None for reads served from projections.
- **Asynchronous dependencies.** Every upstream context's event stream.
- **Tenant boundary.** Consolidation happens **within a single tenant** across its brands and
  outlets. An owner holding several tenants switches tenants; consolidation is never achieved by
  widening the query surface. **Hard rule 13 applies without exception.**
- **Sensitive data.** Revenue, receivables, and staff performance in aggregate.
- **Failure impact.** Owners lose visibility. **A figure that cannot be computed is shown as
  unavailable, never as zero**, and any estimate is labelled an estimate.
- **Implementation step target.** Step 10. Status `NOT IMPLEMENTED`.

## 17. Audit and Compliance

- **Purpose.** Hold the append-only record of who did what, in which tenant, when, and why — including
  every financial correction, waiver, revocation, and impersonation.
- **Owner.** Platform engineering.
- **Primary actors.** Every actor in the system, implicitly; auditors and the owner, explicitly.
- **Aggregates.** `AuditEntry`.
- **Commands.** `AppendAuditEntry`, `QueryAuditTrail`.
- **Events.** `AuditEntryAppended`.
- **Upstream contexts.** All.
- **Downstream contexts.** Reporting, Platform Administration.
- **Synchronous dependencies.** Written within the same transaction as the action it records where
  the action is financial or security-relevant, so that an audit gap is impossible.
- **Asynchronous dependencies.** None; audit is never queued behind an unreliable path.
- **Tenant boundary.** Entries carry tenant context. Platform-scoped entries (impersonation start and
  end, tenant lifecycle) are separated and are readable by the tenant they concern.
- **Sensitive data.** Actor identity, reasons, before/after amounts. **Never** passwords, OTPs,
  tokens, or credential values.
- **Failure impact.** If audit cannot be written for a financial or security-relevant action, **the
  action does not proceed**. Fail closed.
- **Implementation step target.** Step 3, extended in every subsequent Step. Status `NOT IMPLEMENTED`.

## 18. Platform Administration

- **Purpose.** Operate the SaaS itself: tenant lifecycle, plan administration, platform health, and
  audited support tooling.
- **Owner.** Platform engineering.
- **Primary actors.** Platform admin.
- **Aggregates.** None of its own; it commands aggregates in other contexts through explicitly
  separated, audited paths.
- **Commands.** `ProvisionTenant`, `SuspendTenantForNonPayment`, `BeginAuditedImpersonation`,
  `EndAuditedImpersonation`, `InspectPlatformHealth`.
- **Events.** `ImpersonationStarted`, `ImpersonationEnded`, `PlatformActionRecorded`.
- **Upstream contexts.** Tenant and Organization, Subscription and Entitlement.
- **Downstream contexts.** Audit and Compliance.
- **Synchronous dependencies.** Audit and Compliance (an impersonation that cannot be audited does
  not start).
- **Asynchronous dependencies.** None.
- **Tenant boundary.** **This context is the one place that legitimately spans tenants, and it does so
  through an explicitly separated, fully audited surface — never by relaxing tenant scoping for an
  ordinary role.** Platform support has **no silent tenant access**. Impersonation is time-bound,
  reasoned, and recorded at start and end.
- **Sensitive data.** Everything it can reach. Therefore: least privilege, time bounds, and an
  immutable record.
- **Failure impact.** Support cannot assist. **This is the correct failure**: no audit, no access.
- **Implementation step target.** Step 12. Status `NOT IMPLEMENTED`.

## 19. Offline Synchronization

- **Purpose.** Guarantee that work captured without a network reaches the server exactly once, and
  that disagreements are resolved safely.
- **Owner.** Client and platform engineering, under the financial-integrity hard gate.
- **Primary actors.** Kasir and kurir on Aish Laundry Ops Android; the server as final arbiter.
- **Aggregates.** `OfflineOperation`, `SyncConflict`.
- **Commands.** `QueueOfflineOperation`, `RetryOfflineOperation`, `SubmitOfflineOperation`,
  `AcceptOfflineOperation`, `RejectOfflineOperation`, `RaiseSyncConflict`,
  `ResolveSyncConflictByHuman`, `AuthorizeFinancialQueuePurge`.
- **Events.** `OfflineOperationQueued`, `OfflineOperationRetried`, `OfflineOperationAccepted`,
  `OfflineOperationRejected`, `SyncConflictDetected`, `SyncConflictResolvedByHuman`,
  `OfflineQueuePurgeAuthorized`.
- **Upstream contexts.** Order Intake and POS, Payment and Receivables, Production Operations,
  Pickup and Delivery.
- **Downstream contexts.** The same contexts, on acceptance.
- **Synchronous dependencies.** None by definition.
- **Asynchronous dependencies.** Every write-side context; Audit.
- **Tenant boundary.** **Local storage is partitioned per tenant and per user.** A tenant switch or a
  user switch never exposes the previous context's cached data. A queued operation carries the tenant
  it was captured in and is rejected if replayed under a different context.
- **Sensitive data.** Queued payloads on device, including money and customer data. Encrypted at
  rest using platform secure storage.
- **Failure impact.** **The defining risk of the product.** A duplicate order or duplicate payment
  produced by a retry is an automatic `NO-GO`. Payment conflicts surface to a human and are never
  silently overwritten. The financial queue is never casually deleted.
- **Implementation step target.** Step 5 onward. Status `NOT IMPLEMENTED`.

## 20. File and Evidence Management

- **Purpose.** Store and serve every uploaded artefact — condition photographs, proof of pickup and
  delivery, signatures, exports — privately and verifiably.
- **Owner.** Platform engineering.
- **Primary actors.** Kasir, operator produksi, quality control, kurir, finance.
- **Aggregates.** `Attachment`.
- **Commands.** `RequestUploadSlot`, `RegisterAttachment`, `IssueSignedUrl`, `QuarantineAttachment`.
- **Events.** `AttachmentUploaded`, `AttachmentSignedUrlIssued`, `AttachmentQuarantined`.
- **Upstream contexts.** Order Intake, Production, Quality Control, Pickup and Delivery, Reporting
  (exports).
- **Downstream contexts.** Every context that displays an artefact.
- **Synchronous dependencies.** None.
- **Asynchronous dependencies.** Object storage; Audit.
- **Tenant boundary.** Object keys are tenant-scoped and unguessable. A sequential or predictable key
  is an enumeration vulnerability. Buckets are never publicly readable or listable for tenant data.
- **Sensitive data.** Photographs of customers' homes, belongings, and handwriting. Among the most
  sensitive data the product holds.
- **Failure impact.** Proof cannot be captured or shown. **A custody transfer without proof is a
  product defect**, so the flow must not permit it; offline capture buffers on device instead.
- **Implementation step target.** Step 6 onward. Status `NOT IMPLEMENTED`.

---

## Related documents

- [`CONTEXT_MAP.md`](CONTEXT_MAP.md) — relationships and integration patterns between these contexts
- [`AGGREGATE_CATALOG.md`](AGGREGATE_CATALOG.md) — the aggregates named above, in full
- [`DOMAIN_EVENTS.md`](DOMAIN_EVENTS.md) — the event catalogue
- [`TENANT_BOUNDARIES.md`](TENANT_BOUNDARIES.md) — how isolation is enforced across contexts
