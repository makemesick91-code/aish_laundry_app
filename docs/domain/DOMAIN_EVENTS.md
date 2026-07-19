# Domain Events — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** `NOT IMPLEMENTED` (documentation only; no event bus, no publisher, no subscriber)
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.3.0

This catalogue records **144 domain events** across the twenty bounded contexts, of which **138 are
fully specified here** and 6 are named but deliberately deferred. An event is a
recorded fact about something that already happened. It is named in the past tense, it is immutable
once emitted, and it never carries a command's intent.

---

## 1. Rules that govern every event

| Rule | Statement |
| --- | --- |
| **Past tense** | An event names a fact, never an intention. `PaymentCaptured`, never `CapturePayment`. |
| **Immutable** | An emitted event is never edited, re-emitted with different content, or deleted. A mistake is corrected by a new, compensating event. |
| **Tenant-carrying** | Every business event carries `TenantId` (`TEN-015`, `TEN-027`). A background subscriber never infers tenant context from "the last request". |
| **No secrets** | An event never carries a password, an OTP, a credential, or a tracking-token plaintext (`TRK-019`, `NOT-016`). |
| **No full address in a message payload** | Events consumed by Notification carry references, not raw personal data, so a template can never accidentally render a full address (`NOT-015`). |
| **Money is `Money`** | Any monetary field on an event is integer Rupiah (`FIN-001`, `FIN-002`). |
| **Idempotent consumption** | Every subscriber is idempotent. A replayed event never produces a second payment, a second order, or a second message (`FIN-003`, `NOT-002`, `OFF-017`). |
| **One-way to Notification** | Notification subscribes to business events. **No notification event is ever subscribed to by a business aggregate** (`NOT-029`). |
| **Server timestamps** | Event ordering uses server timestamps. Client clock skew is expected (`OFF-015`). |

Each event below records its **emitter**, its **key subscribers**, and the **rule** that constrains
it.

---

## 1.1 The event record — required fields

Every event, without exception, carries the following envelope. A field here is not optional and has
no permissive default; an event that cannot populate one is not emitted.

| Field | Rule |
| --- | --- |
| Event name | Past tense, PascalCase, stable for the life of the version. |
| Event version | Explicit integer. A breaking payload change is a **new version**, never a mutation of the existing one. |
| Occurrence timestamp | The server timestamp at which the fact occurred. Client clock skew is expected and never authoritative (`OFF-015`). |
| `TenantId` | The owning tenant (`TEN-015`). Carried explicitly, never inferred downstream (`TEN-027`). |
| Actor | The authenticated actor, or the named system process, responsible for the fact. |
| **Source aggregate identity** | The aggregate type and identifier that emitted the event. |
| **`CorrelationId`** | A stable identifier tying every event, command, queued job, retry, and notification arising from one originating action into a single traceable chain. |
| `CausationId` | The identifier of the immediate command or event that caused this one. |
| `ClientReference` | Present where the originating operation was client-captured, so the offline idempotency key survives into the event record (`OFF-001`). |

### The source aggregate

> **Every event has exactly one source aggregate — the owning aggregate that emitted it.**

- The **source aggregate** is the aggregate root whose state change the event records. It is named on
  the event, not derived by a consumer from the event name.
- No event is emitted by a projection, a controller, a background worker, or a consumer. Only an
  aggregate root emits, because only an aggregate root owns the invariant the fact attests to.
- Two aggregates never emit the same event. Where two contexts both care about a fact, one owns it
  and the other subscribes — see [`CONTEXT_MAP.md`](CONTEXT_MAP.md).

### The correlation identifier

- `CorrelationId` is generated once, at the edge, when the originating action enters the system, and
  is then **propagated unchanged** — into every emitted event, every queued message, every background
  job, every retry attempt, every outbound notification send record, and every audit entry.
- A background job or scheduler run **inherits** the correlation identifier of whatever caused it. A
  job that cannot state its correlation identifier is not traceable, and an untraceable financial or
  messaging chain is the condition under which a duplicate charge or a duplicate message goes
  unexplained.
- Correlation identifiers are opaque and carry no personal data, no tenant name, and no token
  material. They are recorded in structured logs; secrets never are (`TRK-019`, `NOT-016`).

---

## 1.2 The delivery and idempotency contract

> **Idempotency is a server contract, not a client convention.**

- The **server contract** is that a repeated operation carrying the same stable `ClientReference`
  returns the original result and creates no second record. The client's obligation is only to reuse
  the reference unchanged on every retry; the guarantee itself is the server's, enforced server-side
  (`FIN-003`, `OFF-001`). A client that behaves badly must not be able to produce a duplicate order or
  a duplicate payment — that is what makes it a contract rather than an etiquette.
- **Message delivery is at least once.** The transport may redeliver an event after a broker restart,
  a consumer crash between handling and acknowledgement, a queue replay, a scheduler restart, or a
  retry after a timeout. Exactly-once delivery is not assumed anywhere in this model, because it is
  not achievable across a network and pretending otherwise is how duplicates ship.
- **Therefore every consumer is idempotent.** A consumer that cannot tolerate redelivery will
  eventually double-charge a customer or double-notify one — not as an edge case, but as a certainty
  once the transport redelivers. Each consumer declares its deduplication key: for payment
  application the `ClientReference`; for a notification send the recipient, event, order, and
  intended send window (`NOT-002`); for a reminder ladder stage the order and stage (`UCL-004`).
- Consumer deduplication state is **tenant-scoped** and persisted, so it survives a restart. An
  in-memory "already seen" set is not a deduplication mechanism.
- Retries use **exponential backoff** (`OFF-003`). A handler that keeps failing is not retried
  forever; it lands in a visible failed state with its correlation identifier intact.
- **A failed handler is never silently dropped.** It stays visible and actionable, and its failure
  never alters business state — a messaging failure in particular never changes or cancels an order
  (`NOT-001`, `NOT-029`).

---

## 2. Tenant and Organization — 11 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `TenantRegistered` | `Tenant` | Subscription, Audit | Creates the isolation boundary. |
| `TenantSuspended` | `Tenant` | Subscription, Audit, Reporting | Suspension retains data and the export right (`TEN-003`). |
| `TenantReactivated` | `Tenant` | Subscription, Audit | — |
| `BrandCreated` | `LaundryBrand` | Catalog, Audit | — |
| `BrandArchived` | `LaundryBrand` | Catalog, Reporting, Audit | Archiving never alters a past order or nota (`FIN-010`). |
| `OutletOpened` | `Outlet` | Order Intake, Delivery, Audit | — |
| `OutletClosed` | `Outlet` | Order Intake, Delivery, Reporting, Audit | — |
| `MembershipGranted` | `Membership` | Identity, Audit | Least privilege by default (`TEN-006`). |
| `MembershipRoleChanged` | `Membership` | Audit | — |
| `MembershipRevoked` | `Membership` | Identity, Offline Sync, Audit | Effective immediately, including on live sessions (`TEN-014`). |
| `TenantContextSwitched` | `Membership` | Offline Sync, Audit | Client caches are partitioned on switch (`OFF-006`, `OFF-020`). |

## 3. Identity and Access — 7 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `OtpRequested` | Identity | Notification, Audit | The OTP value is **never** in the event payload (`NOT-016`). |
| `OtpVerified` | Identity | Audit | — |
| `SessionIssued` | Identity | Audit | Identity confers no business authority (`TEN-004`). |
| `SessionRevoked` | Identity | Offline Sync, Audit | Revocation is immediate. |
| `DeviceRegistered` | Identity | Audit | — |
| `DeviceRevoked` | Identity | Offline Sync, Audit | A lost device is cut off without changing a password. |
| `AuthenticationFailed` | Identity | Audit | Feeds brute-force protection; never logs the attempted credential. |

## 4. Customer Management — 6 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `CustomerProfileCreated` | `Customer` | Order Intake, Audit | Tenant-scoped profile; never merged across tenants (`TEN-011`, `TEN-012`). |
| `CustomerContactUpdated` | `Customer` | Notification, Audit | — |
| `CustomerAddressAdded` | `CustomerAddress` | Delivery, Audit | Never rendered in a notification body (`NOT-015`). |
| `CustomerAddressArchived` | `CustomerAddress` | Delivery, Audit | Archive, never delete, so past jobs stay interpretable. |
| `CustomerConsentGranted` | `Customer` | Notification, Audit | Recorded with timestamp and source (`NOT-011`). |
| `CustomerConsentWithdrawn` | `Customer` | Notification, Audit | Honoured at send time, permanently, tenant-wide (`NOT-005`). |

## 5. Service Catalog and Pricing — 5 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `ServiceCatalogItemPublished` | `ServiceCatalog` | Order Intake, Audit | — |
| `ServiceCatalogItemRetired` | `ServiceCatalog` | Order Intake, Audit | Never alters an existing order (`FIN-010`). |
| `PriceListVersionPublished` | `PriceList` | Order Intake, Reporting, Audit | **Affects future orders only** (`FIN-012`). |
| `PriceRuleAdded` | `PriceRule` | Order Intake, Audit | Integer arithmetic only (`FIN-013`). |
| `PriceRuleRetired` | `PriceRule` | Order Intake, Audit | No retroactive re-evaluation. |

## 6. Order Intake and POS — 12 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `OrderDrafted` | `LaundryOrder` | Offline Sync, Audit | Idempotent on `ClientReference` (`OFF-001`). |
| `OrderLineAdded` | `OrderLine` | Audit | — |
| `OrderLineRemoved` | `OrderLine` | Audit | Permitted only before intake confirmation (`FIN-018`). |
| `OrderPriceSnapshotTaken` | `OrderLine` | Payment, Reporting, Audit | The snapshot is **immutable** (`FIN-011`). |
| `OrderConditionEvidenceCaptured` | `OrderConditionEvidence` | Files, Audit | Private artefact; never on the portal (`TRK-017`, `DEL-021`). |
| `OrderReceived` | `LaundryOrder` | Production, Tracking, Notification, Payment | Issues tracking access as a **new** secret (`TRK-003`). |
| `OrderStatusChanged` | `LaundryOrder` | Production, Notification, Tracking, Reporting, Audit | Only enumerated transitions (`DEL-001` analogue for orders). |
| `OrderReachedReadyForPickupFirstTime` | `LaundryOrder` | Unclaimed Recovery, Notification, Audit | **Emitted exactly once, ever.** Anchors aging (`UCL-001`, `UCL-002`, `UCL-017`). |
| `OrderFlaggedAsIssue` | `LaundryOrder` | Notification, Reporting, Audit | Carries a `ReasonCode` and an actor. |
| `OrderIssueResolved` | `LaundryOrder` | Reporting, Audit | — |
| `OrderCompleted` | `LaundryOrder` | Unclaimed Recovery, Tracking, Reporting, Audit | Starts the tracking expiry clock (`TRK-005`). |
| `OrderCancelled` | `LaundryOrder` | Payment, Delivery, Unclaimed, Notification, Audit | Money already taken is corrected by reversal, never by removing the order (`FIN-008`). |

## 7. Production Operations — 7 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `ProductionJobCreated` | `ProductionJob` | Order Intake, Audit | One order, one tenant, one outlet. |
| `ProductionStageStarted` | `ProductionJob` | Order Intake, Reporting, Audit | Real timestamps, real actor. |
| `ProductionStageCompleted` | `ProductionJob` | Order Intake, Reporting, Audit | Idempotent on `ClientReference` (`OFF-001`). |
| `ProductionJobBlocked` | `ProductionJob` | Order Intake, Notification, Audit | Carries a `ReasonCode`. |
| `ProductionJobResumed` | `ProductionJob` | Order Intake, Audit | — |
| `ReworkRequested` | `ProductionJob` | Order Intake, Reporting, Audit | **Does not reset unclaimed aging** (`UCL-017`). |
| `ReworkCompleted` | `ProductionJob` | Quality Control, Audit | Returns the job to inspection, never straight to ready. |

## 8. Quality Control and Rework — 4 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `QualityControlInspectionOpened` | `QualityControlInspection` | Order Intake, Audit | — |
| `QualityControlPassed` | `QualityControlInspection` | Order Intake, Audit | One of only two verdicts permitting `READY_FOR_PICKUP`. |
| `QualityControlFailedReworkRequired` | `QualityControlInspection` | Production, Order Intake, Audit | Returns the order to `REWORK`. |
| `QualityControlWaived` | `QualityControlInspection` | Order Intake, Audit | Requires **permission + reason + audit entry**, all three, always. |

## 9. Payment and Receivables — 17 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `PaymentIntentRecorded` | `Payment` | Offline Sync, Audit | An intent is **not** a payment and is never shown as "paid" (`FIN-019`). |
| `PaymentCaptured` | `Payment` | Order Intake, Receivable, Notification, Reporting, Audit | Server-verified or authorised in-person only (`FIN-005`). |
| `PaymentDuplicateSuppressed` | `Payment` | Reporting, Audit | Suppression is **observable**, never silent (`FIN-003`). |
| `PaymentFailed` | `Payment` | Order Intake, Notification, Audit | Failure never cancels the order (`NOT-001`). |
| `PaymentGatewayCallbackVerified` | `Payment` | Audit | Signature, amount, currency, status all checked (`FIN-004`). |
| `PaymentGatewayCallbackRejected` | `Payment` | Audit | Replays are rejected and recorded. |
| `RefundRequested` | `Refund` | Audit | Requires permission and a recorded reason (`FIN-006`). |
| `RefundApproved` | `Refund` | Audit | — |
| `RefundRejected` | `Refund` | Audit | Carries a `ReasonCode`. |
| `RefundSettled` | `Refund` | Receivable, Reporting, Notification, Audit | Never exceeds the captured amount net of prior refunds (`FIN-020`). |
| `AdjustmentEntryPosted` | `Receivable` | Reporting, Audit | The **only** correction mechanism alongside reversal (`FIN-008`). |
| `ReceivableOpened` | `Receivable` | Unclaimed Recovery, Reporting | — |
| `ReceivableSettled` | `Receivable` | Unclaimed Recovery, Reporting, Audit | — |
| `CashierShiftOpened` | `CashierShift` | Audit | — |
| `CashierShiftClosed` | `CashierShift` | Reporting, Audit | Requires a completed count. |
| `CashierShiftVarianceRecorded` | `CashierShift` | Reporting, Audit | Never masked, auto-rounded, or suppressed (`FIN-026`). |
| `ReceivableWrittenDownWithAuthorization` | `Receivable` | Reporting, Audit | Only as an authorised, reasoned, audited adjustment entry — never a silent write-off. |

## 10. Customer Tracking — 7 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `TrackingAccessIssued` | `TrackingAccess` | Notification, Audit | Carries the **hash**, never the plaintext (`TRK-002`, `TRK-019`). |
| `TrackingAccessViewed` | `TrackingAccess` | Audit | A security event (`TRK-024`). |
| `TrackingAccessOtpChallenged` | `TrackingAccess` | Notification, Audit | Sensitive portal actions require OTP (`TRK-012`). |
| `TrackingAccessOtpVerified` | `TrackingAccess` | Delivery, Audit | — |
| `TrackingAccessRevoked` | `TrackingAccess` | Audit | Immediate; records actor and reason (`TRK-022`). |
| `TrackingAccessExpired` | `TrackingAccess` | Audit | Default expiry 30 days after order completion (`TRK-005`). |
| `TrackingAccessThrottled` | `TrackingAccess` | Audit | Feeds enumeration protection (`TRK-007`). |

## 11. Pickup and Delivery — 11 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `PickupRequested` | `PickupDeliveryJob` | Order Intake, Notification, Audit | May originate from the customer or from staff. |
| `PickupConfirmed` | `PickupDeliveryJob` | Notification, Audit | — |
| `JobScheduled` | `PickupDeliveryJob` | Courier Assignment, Notification, Audit | Communicates a `TimeWindow`, never an exact minute (`DEL-004`). |
| `JobEnRoute` | `PickupDeliveryJob` | Notification, Audit | Never accompanied by a computed ETA claim (`DEL-026`). |
| `CourierArrived` | `PickupDeliveryJob` | Notification, Audit | Must precede delivery (`DEL-028`). |
| `ParcelPickedUp` | `PickupDeliveryJob` | Order Intake, Audit | Requires proof (`DEL-011`). |
| `ParcelDelivered` | `PickupDeliveryJob` | Order Intake, Payment, Notification, Reporting, Audit | Unreachable without captured proof (`DEL-027`). Terminal (`DEL-029`). |
| `DeliveryProofCaptured` | `DeliveryProof` | Files, Audit | Private artefact, signed-URL only (`DEL-012`). |
| `JobFailed` | `PickupDeliveryJob` | Order Intake, Notification, Reporting, Audit | A first-class outcome with a reason (`DEL-003`). |
| `JobRescheduled` | `PickupDeliveryJob` | Notification, Audit | The original schedule is preserved in the chain (`DEL-022`). |
| `JobCancelled` | `PickupDeliveryJob` | Order Intake, Notification, Audit | Records reason and actor (`DEL-023`). |

## 12. Courier Assignment and Settlement — 8 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `CourierAssigned` | `CourierAssignment` | Delivery, Notification, Audit | At most one active assignment per job (`DEL-019`). |
| `GuestJobLinkIssued` | `CourierAssignment` | Audit | High-entropy, hashed, expiring, revocable, minimal scope (`DEL-008`). |
| `GuestJobLinkRevoked` | `CourierAssignment` | Audit | Immediate (`DEL-033`). |
| `CourierCashCollected` | `CourierSettlement` | Payment, Audit | A financial transaction in full (`FIN-027`). |
| `CourierSettlementOpened` | `CourierSettlement` | Audit | — |
| `CourierSettlementSubmitted` | `CourierSettlement` | Payment, Audit | — |
| `CourierSettlementVarianceRecorded` | `CourierSettlement` | Reporting, Audit | Never hidden or written off silently (`FIN-029`). |
| `CourierSettlementAccepted` | `CourierSettlement` | Payment, Reporting, Audit | Blocked while an unacknowledged variance exists (`DEL-030`). |

## 13. Notification and Communication — 8 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `NotificationRequested` | `Notification` | — | — |
| `NotificationDeduplicated` | `Notification` | Reporting, Audit | Deduplication is mandatory (`NOT-002`). |
| `NotificationDeferredForQuietHours` | `Notification` | Reporting | Deferred to the next permitted window, never dropped (`NOT-021`). |
| `NotificationSuppressedByOptOut` | `Notification` | Reporting, Audit | Opt-out honoured at send time (`NOT-005`). |
| `NotificationDispatched` | `Notification` | Reporting | — |
| `NotificationDelivered` | `Notification` | Reporting | — |
| `NotificationFailed` | `Notification` | Reporting | **No business aggregate subscribes to this event** (`NOT-001`, `NOT-029`). |
| `ManualDeepLinkFallbackOffered` | `Notification` | Reporting, Audit | Explicit and visible; never described as automation (`NOT-007`). |

**Structural note.** The "Key subscribers" column for this context contains no business aggregate,
anywhere. That absence is the invariant. A future subscriber wiring `NotificationFailed` into an
order, payment, job, or case is a design rejection under `NOT-001`.

## 14. Unclaimed Laundry Recovery — 9 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `UnclaimedCaseOpened` | `UnclaimedLaundryCase` | Reminder Schedule, Reporting, Audit | Opened at most once per order (`UCL-015`). |
| `ReminderScheduled` | `ReminderSchedule` | Audit | Computed from the immutable first-ready timestamp (`UCL-005`). |
| `ReminderStageFired` | `ReminderSchedule` | Notification, Reporting, Audit | Each stage fires exactly once (`UCL-004`). |
| `FollowUpTaskCreated` | `UnclaimedLaundryCase` | Reporting, Audit | A real, closable task at H+7 (`UCL-009`). |
| `FollowUpTaskAssigned` | `UnclaimedLaundryCase` | Notification, Reporting, Audit | Named owner recorded (`UCL-029`). |
| `FollowUpTaskClosed` | `UnclaimedLaundryCase` | Reporting, Audit | — |
| `UnclaimedCaseEscalated` | `UnclaimedLaundryCase` | Notification, Reporting, Audit | Reaches a manager or owner at H+14 (`UCL-010`); internal, not customer WhatsApp (`NOT-025`). |
| `ReasonNotCollectedRecorded` | `UnclaimedLaundryCase` | Reporting, Audit | First-class field (`UCL-011`). |
| `UnclaimedCaseClosed` | `UnclaimedLaundryCase` | Reporting, Audit | Records outcome and reason (`UCL-022`). |

**Structural note.** There is **no** event in this catalogue — and there must never be one —
representing disposal, sale, auction, donation, or transfer of customer laundry (`UCL-013`,
`UCL-026`, `UCL-027`).

## 15. Subscription and Entitlement — 11 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `SubscriptionTrialStarted` | `Subscription` | Notification, Audit | Trial is 14 hari gratis per Master Source §21.1. |
| `SubscriptionActivated` | `Subscription` | Reporting, Audit | — |
| `SubscriptionPlanChanged` | `Subscription` | Reporting, Audit | Only plans present in the canonical pricing table. |
| `SubscriptionPaymentFailed` | `Subscription` | Notification, Audit | Never stops a laundry mid-shift (`TEN-019`). |
| `SubscriptionLapsed` | `Subscription` | Reporting, Audit | Export right survives (`TEN-018`, `TEN-028`). |
| `SubscriptionSuspended` | `Subscription` | Reporting, Audit | Data retained (`TEN-003`). |
| `SubscriptionReactivated` | `Subscription` | Reporting, Audit | — |
| `SubscriptionCancelled` | `Subscription` | Reporting, Audit | — |
| `PlanLimitApproached` | `Subscription` | Notification, Reporting | A signal, presented honestly. |
| `PlanLimitExceededFairUse` | `Subscription` | Notification, Reporting, Audit | Triggers a conversation, never a silent cut-off (`TEN-019`). |
| `TenantDataExportRequested` | `Subscription` | Files, Audit | Honoured for a lapsed tenant (`TEN-028`). |

## 16. Offline Synchronization — 7 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `OfflineOperationQueued` | `OfflineOperation` | — | Carries a stable `ClientReference` (`OFF-001`). |
| `OfflineOperationRetried` | `OfflineOperation` | — | **Same** reference, exponential backoff (`OFF-003`, `OFF-025`). |
| `OfflineOperationAccepted` | `OfflineOperation` | Owning business context, Audit | Server is final truth (`OFF-005`). |
| `OfflineOperationRejected` | `OfflineOperation` | Client UI, Audit | Never silently dropped (`OFF-008`). |
| `SyncConflictDetected` | `SyncConflict` | Client UI, Audit | Money conflicts escalate to a human (`OFF-011`). |
| `SyncConflictResolvedByHuman` | `SyncConflict` | Owning business context, Audit | Records actor, value, reason (`OFF-012`). |
| `OfflineQueuePurgeAuthorized` | `OfflineOperation` | Audit | Explicit, permissioned, audited (`OFF-024`). |

## 17. Audit, Files, and Platform Administration — 8 events

| Event | Emitter | Key subscribers | Constraining rule |
| --- | --- | --- | --- |
| `AuditEntryAppended` | `AuditEntry` | Reporting | Append-only; never contains credentials (`TEN-022`, `FIN-031`). |
| `AttachmentUploaded` | `Attachment` | Owning context, Audit | Server-side validation of type, size, and content. |
| `AttachmentSignedUrlIssued` | `Attachment` | Audit | Signed and expiring; never a public URL (`DEL-012`). |
| `AttachmentQuarantined` | `Attachment` | Audit | Quarantine rather than delete where a financial or custody record references it. |
| `ImpersonationStarted` | Platform Administration | Audit, the affected tenant | Time-bound, reasoned, recorded. No silent access (`TEN-029`). |
| `ImpersonationEnded` | Platform Administration | Audit, the affected tenant | — |
| `PlatformActionRecorded` | Platform Administration | Audit | — |
| `ReportProjectionRefreshed` | Reporting | — | A projection is never a system of record. |

---

## 18. Event count

| Context group | Events |
| --- | --- |
| Tenant and Organization | 11 |
| Identity and Access | 7 |
| Customer Management | 6 |
| Service Catalog and Pricing | 5 |
| Order Intake and POS | 12 |
| Production Operations | 7 |
| Quality Control and Rework | 4 |
| Payment and Receivables | 17 |
| Customer Tracking | 7 |
| Pickup and Delivery | 11 |
| Courier Assignment and Settlement | 8 |
| Notification and Communication | 8 |
| Unclaimed Laundry Recovery | 9 |
| Subscription and Entitlement | 11 |
| Offline Synchronization | 7 |
| Audit, Files, and Platform Administration | 8 |
| **Subtotal — fully specified above** | **138** |
| Loyalty, Membership, and Deposit | 6 (named only; see [`BOUNDED_CONTEXTS.md`](BOUNDED_CONTEXTS.md) §15) |
| **Total** | **144** |

The six Loyalty, Membership, and Deposit events are named but deliberately left unspecified until
Step 11 delivers that context. Specifying them now would require inventing product decisions about
accrual rates, tier rules, and deposit forfeiture that the owner has not made. Deposit and loyalty
balances are nonetheless already bound by every financial invariant (`FIN-037`).

---

## 19. Status

No event, publisher, subscriber, event bus, or outbox exists. Every event above is `NOT IMPLEMENTED`.
Backend runtime is `ABSENT`. This document claims no test, build, deployment, CI run, or UAT.

---

## Related documents

- [`COMMANDS_AND_POLICIES.md`](COMMANDS_AND_POLICIES.md) — the commands that emit these events and the policies that react to them
- [`AGGREGATE_CATALOG.md`](AGGREGATE_CATALOG.md)
- [`DOMAIN_INVARIANTS.md`](DOMAIN_INVARIANTS.md)
