# Domain Invariants — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** `NOT IMPLEMENTED` (documentation only; nothing here is built, tested, or verified)
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.0.1

This is the **numbered requirement register** for the domain-model half of Step 1. Every identifier
below is owned by this document; other Step 1 documents reference these identifiers rather than
restating the rule.

**Series owned here:** `TEN-001`–`TEN-030` (tenancy), `FIN-001`–`FIN-040` (financial),
`OFF-001`–`OFF-025` (offline), `TRK-001`–`TRK-030` (tracking), `DEL-001`–`DEL-035` (delivery),
`UCL-001`–`UCL-030` (unclaimed), `NOT-001`–`NOT-030` (notification). **220 identifiers in total.**

Requirement series in the `FR`, `RPT`, `SUB`, `SEC`, and `NFR` prefixes are owned elsewhere in Step 1
and are referenced only generically in prose here.

**Severity legend.**

| Severity | Meaning |
| --- | --- |
| **GATE** | A hard gate. Violation is an automatic `NO-GO` under [DEC-0012](../decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md). Work stops; the owner is notified; evidence is preserved at the exact SHA. |
| **CRITICAL** | A defect that must be fixed before the owning Step meets its Definition of Done. |
| **REQUIRED** | A binding rule; a deviation requires an accepted decision record. |

---

## 1. Tenancy invariants — `TEN-001` … `TEN-030`

| ID | Invariant | Severity |
| --- | --- | --- |
| TEN-001 | The Tenant is the isolation boundary and the commercial boundary. Every business aggregate traces to exactly one Tenant. | GATE |
| TEN-002 | A Tenant has exactly one `Subscription`. | REQUIRED |
| TEN-003 | A suspended Tenant retains its business data and its export right. | REQUIRED |
| TEN-004 | All authority derives from a `Membership`. A bare `UserId` grants nothing. | GATE |
| TEN-005 | A `Membership` belongs to exactly one Tenant. A user in three tenants holds three memberships. | GATE |
| TEN-006 | Least privilege is the default: a new membership starts with nothing and is granted what it needs. | REQUIRED |
| TEN-007 | A role grants permissions **within a tenant only**. The sole cross-tenant surface is the explicitly separated platform-administration path. | GATE |
| TEN-008 | A `LaundryBrand` belongs to exactly one Tenant; a Tenant may hold many brands. | REQUIRED |
| TEN-009 | An `Outlet` belongs to exactly one brand; a brand may operate many outlets. | REQUIRED |
| TEN-010 | Outlet local time governs quiet hours and business-day aging, not an arbitrary server UTC midnight. | REQUIRED |
| TEN-011 | A `Customer` profile belongs to exactly one Tenant. There is no global shared customer profile. | GATE |
| TEN-012 | Profiles are **never** merged, linked, or deduplicated because name, email, phone number, device, or shared ownership match. | GATE |
| TEN-013 | Consent and notification preference are recorded per customer **per tenant**. | REQUIRED |
| TEN-014 | Membership revocation takes effect immediately server-side, including on live sessions and devices. | CRITICAL |
| TEN-015 | Every business aggregate carries `tenant_id`. There is no exception for "small" or "lookup" business data. | GATE |
| TEN-016 | Condition evidence, proofs, and every uploaded artefact are tenant-scoped and private. | GATE |
| TEN-017 | Subscription and billing operate at the tenant boundary — never per user, never per outlet. | REQUIRED |
| TEN-018 | A lapsed subscription restricts features; it never holds a tenant's business records hostage. | REQUIRED |
| TEN-019 | Fair-use ceilings signal and prompt a conversation. They never silently degrade service, delete data, or stop a laundry mid-shift. | REQUIRED |
| TEN-020 | No lifetime cloud plan exists, ever. No per-nota fee applies on normal plans. | REQUIRED |
| TEN-021 | The security baseline, tenant isolation, and encrypted backup are never paid tiers or add-ons. | REQUIRED |
| TEN-022 | Audit entries carry tenant context and are append-only. | GATE |
| TEN-023 | Object-storage keys are tenant-scoped and unguessable. A sequential or predictable key is an enumeration vulnerability. | GATE |
| TEN-024 | A client-supplied tenant identifier is **never** authorisation proof. It is an untrusted hint that must be validated against the authenticated user's memberships. | GATE |
| TEN-025 | All business queries are tenant-scoped, enforced by construction at the data-access layer, and **fail closed** — a missing scope yields no rows, never another tenant's rows. | GATE |
| TEN-026 | Caches, queues, search indexes, exports, report files, and object keys all carry a tenant dimension. A tenant-less cache key is a cross-tenant leak waiting to happen. | GATE |
| TEN-027 | Background jobs carry explicit tenant context. They never infer it from "the last request". | GATE |
| TEN-028 | Tenant data remains exportable per policy when a subscription lapses. | REQUIRED |
| TEN-029 | Platform administration is an explicitly separated, audited path. **Platform support has no silent tenant access**; impersonation is time-bound, reasoned, and recorded at start and end. | GATE |
| TEN-030 | **Any cross-tenant data exposure — read, list, count, search, export, aggregate, cache hit, or file access — is an automatic `NO-GO`.** No exceptions, no "temporary" bypass, no staging exemption. | GATE |

---

## 2. Financial invariants — `FIN-001` … `FIN-040`

| ID | Invariant | Severity |
| --- | --- | --- |
| FIN-001 | Money is stored as **integer Rupiah**. The smallest representable unit is Rp1. | GATE |
| FIN-002 | **Floating point is forbidden in every financial path** — storage, computation, transport, and any display path that round-trips through a float. | GATE |
| FIN-003 | Payments are **idempotent**, keyed on a stable client-supplied `ClientReference`. The same logical payment submitted any number of times produces exactly one payment. | GATE |
| FIN-004 | Gateway callbacks are verified server-side: signature, amount, currency, and status checked against the gateway, not trusted from the payload. Replays are rejected. | GATE |
| FIN-005 | An order is **never** marked paid on a client claim. | GATE |
| FIN-006 | Refund and void require an explicit permission and a recorded reason, with actor, timestamp, and amount. | CRITICAL |
| FIN-007 | Financial transactions are never deleted through ordinary UI. There is no "delete payment" button for regular roles. | GATE |
| FIN-008 | Corrections happen via **reversal or adjustment entries** that preserve the original record. History is added to, never rewritten. | GATE |
| FIN-009 | Every catalog and price-list amount is expressed as `Money` (integer Rupiah). | CRITICAL |
| FIN-010 | Retiring a catalog item never alters an existing order, invoice, or nota. | CRITICAL |
| FIN-011 | Every `OrderLine` carries an **immutable price snapshot** written once at intake confirmation. | GATE |
| FIN-012 | **Editing a price list never changes a past order, invoice, or nota.** A price-list edit affects future orders only. | GATE |
| FIN-013 | Price-rule evaluation uses integer arithmetic only. | GATE |
| FIN-014 | Rounding is explicit: a defined mode applied at a defined point, never a language default and never implicit at a display boundary. | CRITICAL |
| FIN-015 | The order total is computed and authoritative **on the server**. A client-computed total is display only. | CRITICAL |
| FIN-016 | Concurrent operations on the same order or payment are serialized by transaction or distributed lock, so double submission cannot create double payment. | GATE |
| FIN-017 | A line total is derived from the line's own price snapshot, never re-derived from the live catalog. | GATE |
| FIN-018 | A confirmed order line is not silently editable. A correction is an authorised amendment that records actor and reason and emits an adjustment entry. | CRITICAL |
| FIN-019 | An offline device may record a payment **intent**. It may never record a confirmed gateway payment. | GATE |
| FIN-020 | A refund never exceeds the captured amount of the payment it reverses, net of prior refunds. | GATE |
| FIN-021 | A refund is never a silent operation. | CRITICAL |
| FIN-022 | A `Receivable` balance is derived only from posted financial entries. | CRITICAL |
| FIN-023 | Unpaid balance and held-invoice figures are **read from the authoritative financial records**, never recomputed independently by a dashboard or report. | CRITICAL |
| FIN-024 | Shift closing compares expected cash against actual cash and records the variance explicitly. | CRITICAL |
| FIN-025 | A variance beyond the tenant's configured threshold requires a recorded reason and an acknowledgement. | CRITICAL |
| FIN-026 | A variance is **never** masked, auto-rounded away, absorbed, or suppressed from a report. A visible discrepancy is a feature; a hidden one is fraud-shaped. | GATE |
| FIN-027 | Cash collected at the door is a financial transaction and inherits every rule in this section. | GATE |
| FIN-028 | Courier cash is tracked per courier, per shift or route, from collection to hand-over. | CRITICAL |
| FIN-029 | Courier expected versus actual is compared explicitly; any variance is recorded and acknowledged, never written off silently. | GATE |
| FIN-030 | Subscription amounts are `Money`. Canonical pricing figures live in Master Source §21 and are never rounded, reformatted, translated, or restated from memory. | REQUIRED |
| FIN-031 | Every financial audit entry records actor, tenant, outlet, timestamp, before and after amounts, and reason. | GATE |
| FIN-032 | The financial audit trail is append-only and is **not** subject to log rotation. | GATE |
| FIN-033 | The rounding mode and rounding point applied by a price rule are recorded in the price snapshot. | CRITICAL |
| FIN-034 | Money is never inferred from a display string. Rupiah formatting is a view concern applied to an integer. | GATE |
| FIN-035 | Money arithmetic is total and checked. Division is permitted only through an explicit allocation rule whose parts always sum to the whole. | CRITICAL |
| FIN-036 | Reprinting an old nota shows the old price. Historical prices are immutable. | GATE |
| FIN-037 | Customer deposit and loyalty balances are **customer money** and are subject to every rule in this section. | GATE |
| FIN-038 | If the audit entry for a financial action cannot be written, **the action does not proceed**. Fail closed. | GATE |
| FIN-039 | A duplicate payment or duplicate order produced by a retry is an automatic `NO-GO`. | GATE |
| FIN-040 | **Any financial integrity failure — discrepancy, duplicate payment, lost payment, unexplained balance change, or a float in a money path — is an automatic `NO-GO`.** | GATE |

---

## 3. Offline and synchronization invariants — `OFF-001` … `OFF-025`

| ID | Invariant | Severity |
| --- | --- | --- |
| OFF-001 | Every critical operation carries a stable `ClientReference`, generated once before the first attempt, persisted with the queued operation, and **reused unchanged on every retry**. | GATE |
| OFF-002 | The queue is **persistent**: it survives application restart, device reboot, and crash. An in-memory queue is not acceptable. | CRITICAL |
| OFF-003 | Retries use exponential backoff — spaced and bounded, never a tight loop. | REQUIRED |
| OFF-004 | The financial queue is **never casually deleted**. It is not cleared by a cache wipe, a logout, a version upgrade, or a developer convenience button. | GATE |
| OFF-005 | The server is the final source of truth. On divergence, server state prevails and the client reconciles. | CRITICAL |
| OFF-006 | Local data is partitioned **per tenant AND per user**. A tenant or user switch never exposes the previous context's cached data. | GATE |
| OFF-007 | A duplicate order or duplicate payment produced by a retry is unacceptable and is an automatic `NO-GO`. | GATE |
| OFF-008 | A failed operation is never silently dropped. It remains visible and actionable. | CRITICAL |
| OFF-009 | Dependency ordering is respected. An operation whose predecessor failed does not jump ahead. | CRITICAL |
| OFF-010 | A payment conflict is **never silently overwritten**. Both values are surfaced. | GATE |
| OFF-011 | Conflicts affecting money escalate to a human. Non-financial conflicts may use a last-write rule **only if that rule is written down** in advance. | GATE |
| OFF-012 | Conflict resolution records actor, timestamp, chosen value, and reason. | CRITICAL |
| OFF-013 | Offline and sync state are visible at all times. A kasir must never believe a payment was recorded while it sits in a queue. | CRITICAL |
| OFF-014 | Sensitive local data is encrypted on device using platform secure storage for credentials and tokens. | CRITICAL |
| OFF-015 | Server timestamps are authoritative for ordering and reporting. Client clock skew is expected. | REQUIRED |
| OFF-016 | A queued operation carries the tenant and user it was captured under and is rejected if replayed under a different context. | GATE |
| OFF-017 | Idempotency is a **server contract**, not a client trick: the server recognises a repeated `ClientReference` and returns the original result. | GATE |
| OFF-018 | Replay after a long offline period reconciles correctly rather than producing a burst of duplicates. | CRITICAL |
| OFF-019 | Application kill mid-submit does not lose the queued operation. | CRITICAL |
| OFF-020 | A tenant switch leaks no cached data — a tenant-isolation defect if violated. | GATE |
| OFF-021 | Financial operations are pruned from the queue only after confirmed server acceptance, never on a timer. | GATE |
| OFF-022 | Proof capture and courier cash recording work offline, so a courier in a dead zone is never forced to skip proof. | CRITICAL |
| OFF-023 | Gateway confirmation, OTP verification, and public tracking require the network. The product states this honestly rather than pretending to degrade gracefully. | REQUIRED |
| OFF-024 | Purging a queued financial operation requires an explicit, permissioned, audited action. | GATE |
| OFF-025 | An operation retried with a **regenerated** reference is rejected outright. This is the highest-risk bug class in the entire offline design. | GATE |

---

## 4. Tracking invariants — `TRK-001` … `TRK-030`

| ID | Invariant | Severity |
| --- | --- | --- |
| TRK-001 | The tracking token is high-entropy, from a cryptographically secure random source. | GATE |
| TRK-002 | The token is stored **hashed** (`TrackingTokenHash`). The plaintext exists only inside the link. | GATE |
| TRK-003 | The token **is not the order number** and is not derivable from it. Order numbers are sequential, guessable, and printed on the nota; they never grant access. | GATE |
| TRK-004 | Tokens are revocable, and revocation takes effect immediately. | CRITICAL |
| TRK-005 | Tokens expire. The canonical default is **expiry 30 days after order completion**, and expiry is always bounded regardless of order state. | CRITICAL |
| TRK-006 | The portal is served with `noindex`. Tracking pages never enter search engines. | CRITICAL |
| TRK-007 | Token lookup is rate-limited and enumeration-protected. | GATE |
| TRK-008 | The portal serves a **separate public projection read model**, not the internal order representation. | GATE |
| TRK-009 | The projection carries a `MaskedPhoneNumber` only — never a full `PhoneNumber`. | GATE |
| TRK-010 | **The portal never shows the full address.** | GATE |
| TRK-011 | Customer names are partially masked on the portal. | CRITICAL |
| TRK-012 | Sensitive portal actions — changing a delivery address, requesting a schedule change — require OTP verification. | CRITICAL |
| TRK-013 | No login is required for safe information. | REQUIRED |
| TRK-014 | The link is shareable via WhatsApp by design; a customer may forward it to a family member who is collecting. | REQUIRED |
| TRK-015 | The portal never shows other orders belonging to the same customer. | GATE |
| TRK-016 | The portal never shows internal notes or staff identity beyond what is operationally necessary. | CRITICAL |
| TRK-017 | The portal never shows laundry photographs or delivery proof artefacts. | GATE |
| TRK-018 | Masking is applied when the projection is **built**, not when it is rendered, so a rendering bug cannot leak a full value. | GATE |
| TRK-019 | Token plaintext is never logged, never written to an audit entry, and never returned by any API after issuance. | GATE |
| TRK-020 | A `TrackingAccess` is scoped to exactly one order in exactly one tenant. | GATE |
| TRK-021 | Tenant context for a token lookup is derived server-side from the stored record, never from the request. | GATE |
| TRK-022 | Revocation is recorded with actor, timestamp, and reason. | CRITICAL |
| TRK-023 | Re-issuing access revokes the prior token and records why. | CRITICAL |
| TRK-024 | Portal views, OTP challenges, throttling events, issuance, and revocation are recorded as security events. | CRITICAL |
| TRK-025 | The portal is never degraded into "install the app first". | REQUIRED |
| TRK-026 | The Customer Android application is an enhancement to the portal, never a replacement ([DEC-0014](../decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md)). | REQUIRED |
| TRK-027 | Public tracking is server-rendered and requires the network by nature. The product states this honestly. | REQUIRED |
| TRK-028 | The safe field set is enumerated. **A field not enumerated is not served** — the projection is an allow-list, never a deny-list. | GATE |
| TRK-029 | A message never echoes an OTP value alongside a tracking link in a way that enables one-message account takeover. | GATE |
| TRK-030 | A tracking failure, throttle, or revocation never changes order state. | CRITICAL |

---

## 5. Pickup and delivery invariants — `DEL-001` … `DEL-035`

| ID | Invariant | Severity |
| --- | --- | --- |
| DEL-001 | Job statuses are exactly the eleven canonical values. Nothing outside the enumerated transitions is permitted. | CRITICAL |
| DEL-002 | **Every custody transfer requires proof.** A parcel never silently changes hands. | GATE |
| DEL-003 | A failed delivery is a **first-class outcome with a recorded reason**, not an error state. | CRITICAL |
| DEL-004 | The customer is given a `TimeWindow`, never a fictitious exact minute. | REQUIRED |
| DEL-005 | **No route optimization claims.** Stop ordering is a *usulan rute* — a suggestion. The product never says "rute optimal". | CRITICAL |
| DEL-006 | An internal courier acts through a `Membership`. | REQUIRED |
| DEL-007 | **An external local courier receives no membership, no account, and no application access.** | GATE |
| DEL-008 | The guest job link uses a high-entropy token stored hashed, is revocable and expiring, is not the order number and not derivable from it, and exposes only the assigned job and the minimum data needed. | GATE |
| DEL-009 | The guest link is tenant-scoped. Two tenants engaging the same rider issue two unrelated links with no traversal between them. | GATE |
| DEL-010 | Stop ordering is labelled a suggestion wherever it is presented. | CRITICAL |
| DEL-011 | Proof of pickup and proof of delivery are both mandatory. The method may vary by tenant policy; *some* recorded proof is always required. | GATE |
| DEL-012 | Proof artefacts are private: private object storage, signed expiring URLs, tenant-scoped unguessable keys, **never on the public portal**. | GATE |
| DEL-013 | Proof capture works offline and syncs later. | CRITICAL |
| DEL-014 | Cash collected at the door is a financial transaction (see `FIN-027`). | GATE |
| DEL-015 | Courier UX is deliberately simple: large tap targets, few steps, one-handed operation, usable outdoors on a cheap phone. | REQUIRED |
| DEL-016 | Delivery notifications follow every notification rule, including quiet hours and deduplication. | REQUIRED |
| DEL-017 | Zones are defined per outlet; a request is matched to a zone. | REQUIRED |
| DEL-018 | A job references exactly one order in the same tenant. | GATE |
| DEL-019 | A job has at most one active `CourierAssignment` at a time. | CRITICAL |
| DEL-020 | An external courier sees only the minimum address the delivery genuinely requires, and never in a shareable or indexable form. | GATE |
| DEL-021 | Condition evidence and proof artefacts are never exposed on the public tracking portal. | GATE |
| DEL-022 | A reschedule chain is recorded. The original schedule is never overwritten. | CRITICAL |
| DEL-023 | Cancellation records a `ReasonCode`, free-text reason, and actor. | CRITICAL |
| DEL-024 | A guest link never grants access to customer history, other orders, pricing, or any tenant data beyond the assignment. | GATE |
| DEL-025 | Delivery may be proposed as a recovery action for unclaimed laundry. | REQUIRED |
| DEL-026 | The product never promises a guaranteed arrival time or an ETA it does not actually compute. | CRITICAL |
| DEL-027 | `DELIVERED` is unreachable without a captured `DeliveryProof`. | GATE |
| DEL-028 | `ARRIVED` must be recorded before `DELIVERED`. A job never jumps from `EN_ROUTE` to `DELIVERED`. | CRITICAL |
| DEL-029 | `DELIVERED` is terminal. A dispute is recorded as an order-level `ISSUE`, never by mutating the job. | CRITICAL |
| DEL-030 | Courier cash is reconciled before a settlement may be accepted. | GATE |
| DEL-031 | A failed delivery returns the laundry to the outlet and the order to a defined status. | CRITICAL |
| DEL-032 | Time-window adherence is measurable after the fact. | REQUIRED |
| DEL-033 | Guest-link revocation takes effect immediately. | GATE |
| DEL-034 | Every courier-captured transition is idempotent on `ClientReference`. | GATE |
| DEL-035 | No notification outcome ever changes job state. | GATE |

---

## 6. Unclaimed laundry invariants — `UCL-001` … `UCL-030`

| ID | Invariant | Severity |
| --- | --- | --- |
| UCL-001 | **Aging starts when an order FIRST reaches `READY_FOR_PICKUP`.** | GATE |
| UCL-002 | The first-ready timestamp is recorded exactly once and is **immutable thereafter**. | GATE |
| UCL-003 | The canonical ladder is H+1 friendly reminder; H+3 second reminder; H+7 priority reminder plus an assignable follow-up task; H+14 escalation to manager or owner. | CRITICAL |
| UCL-004 | Each ladder stage fires **exactly once** per order. Deduplication is mandatory. | GATE |
| UCL-005 | Stages are computed against the immutable first-ready timestamp in outlet local time and Asia/Jakarta business-day semantics — never against an arbitrary UTC midnight. | CRITICAL |
| UCL-006 | Reminders respect quiet hours and customer opt-out. | CRITICAL |
| UCL-007 | A reminder that fails to send is retried and made visible. It is never silently dropped, and **its failure never alters the order's state**. | GATE |
| UCL-008 | Canonical aging buckets are **1–2 days; 3–6 days; 7–13 days; 14–30 days; More than 30 days.** | REQUIRED |
| UCL-009 | The H+7 follow-up task is a real, assignable, closable task with a named owner — not a flag on a report and not a notification. | CRITICAL |
| UCL-010 | The H+14 escalation reaches a manager or owner: a human accountable for the outcome. | CRITICAL |
| UCL-011 | "Reason not collected" is a first-class recorded field captured from staff follow-up. | CRITICAL |
| UCL-012 | The dashboard exposes at minimum all nine fields: order count, customer count, held invoices, unpaid balance, order age, outlet, last reminder, follow-up officer, reason not collected. | CRITICAL |
| UCL-013 | **ABSOLUTE PROHIBITION. The product never automatically discards, sells, auctions, donates, or transfers ownership of a customer's laundry.** | GATE |
| UCL-014 | Unpaid balance and held-invoice figures are read from the authoritative financial records (see `FIN-023`). | CRITICAL |
| UCL-015 | A case is opened at most once per order. Opening is idempotent on the order identifier. | CRITICAL |
| UCL-016 | Adding, removing, or renumbering a ladder stage requires an accepted decision record. | REQUIRED |
| UCL-017 | **Aging never restarts** — not after a `REWORK` cycle, not after any status change that returns the order to `READY_FOR_PICKUP`. | GATE |
| UCL-018 | Delivery may be proposed as a recovery action within a case. | REQUIRED |
| UCL-019 | Every dashboard figure is tenant-scoped. | GATE |
| UCL-020 | Escalations surface in the manager and owner dashboards. | CRITICAL |
| UCL-021 | Skipping a ladder stage records a reason (for example, the order was collected first). | CRITICAL |
| UCL-022 | Case closure records the outcome and its reason. | CRITICAL |
| UCL-023 | Reminders are transactional messages but still respect opt-out and quiet hours. | CRITICAL |
| UCL-024 | Cashflow recovery reporting reads from financial records and never recomputes money independently. | CRITICAL |
| UCL-025 | The aging distribution is a monitored operational signal. | REQUIRED |
| UCL-026 | No automation may dispose of a customer's property at **any** age, at **any** unpaid balance, at **any** escalation level, or on **any** tenant request. | GATE |
| UCL-027 | No disposal capability may exist as a configuration flag, a prototype, a backlog item, or a `TODO`. Such a proposal is refused outright and escalated to the repository owner. | GATE |
| UCL-028 | Collection of the order closes the case with a recorded outcome. | CRITICAL |
| UCL-029 | The assigned follow-up officer is recorded and shown on the dashboard. | CRITICAL |
| UCL-030 | The last reminder sent is recorded and shown on the dashboard. | CRITICAL |

---

## 7. Notification invariants — `NOT-001` … `NOT-030`

| ID | Invariant | Severity |
| --- | --- | --- |
| NOT-001 | **A provider failure never changes business state.** No order, payment, job, or case is ever cancelled, blocked, or altered because a message failed. | GATE |
| NOT-002 | Message deduplication is mandatory, keyed on recipient + event + order + intended send window. | CRITICAL |
| NOT-003 | Quiet hours default to **20.00–08.00 outlet local time**. | CRITICAL |
| NOT-004 | Quiet hours are evaluated in outlet local time, not server time. | CRITICAL |
| NOT-005 | Opt-out is honoured **at send time**, permanently, across every outlet of the tenant. | GATE |
| NOT-006 | Transactional and marketing messages are separated: categories, templates, consent handling, and reporting. | CRITICAL |
| NOT-007 | The manual deep-link fallback is explicit and visible and is **never described or sold as automation**. | CRITICAL |
| NOT-008 | The product never promises "unlimited WhatsApp" — not in the app, not in pricing, not in documentation, not in marketing copy. | CRITICAL |
| NOT-009 | Provider abstraction is mandatory. No vendor SDK type, payload shape, error code, or identifier leaks into business logic. | CRITICAL |
| NOT-010 | The official WhatsApp Business API provider is the automated path. | REQUIRED |
| NOT-011 | Consent state is recorded per customer per tenant, with a timestamp and a source. Absence of a refusal is not consent. | GATE |
| NOT-012 | Message copy is written in Bahasa Indonesia. | REQUIRED |
| NOT-013 | Opt-out is **never reset by a data import**. | GATE |
| NOT-014 | A message never echoes an OTP value alongside a tracking link. | GATE |
| NOT-015 | A message never contains a full address. | GATE |
| NOT-016 | Message logs never contain token plaintext, credentials, or OTP values. | GATE |
| NOT-017 | Delivery failures are visible to the tenant and retried under a bounded policy. | CRITICAL |
| NOT-018 | Retries are never unbounded and failures are never silently discarded. | CRITICAL |
| NOT-019 | Every send records tenant, outlet, order, recipient, template, category, status, timestamp, and provider reference. | CRITICAL |
| NOT-020 | Provider costs are transparent to the tenant and are billed separately from the subscription plan. | REQUIRED |
| NOT-021 | Messages deferred by quiet hours are sent in the next permitted window — never dropped, never sent anyway. | CRITICAL |
| NOT-022 | A quiet-hours exception path exists only where the Master Source or an accepted decision record explicitly grants it. Absent such a record, quiet hours apply. | CRITICAL |
| NOT-023 | Templates are tenant-scoped. | CRITICAL |
| NOT-024 | A marketing message is never routed through a transactional path to evade opt-out. | GATE |
| NOT-025 | The H+14 escalation is an internal in-product notification to a manager or owner, not a customer WhatsApp message. | REQUIRED |
| NOT-026 | Notification sending is asynchronous by design. | CRITICAL |
| NOT-027 | Sending never blocks a business transaction. | GATE |
| NOT-028 | A duplicate message reaching a customer is treated as a defect of the same class as a duplicate payment: investigate the key, fix, add a regression test. | CRITICAL |
| NOT-029 | The `Notification` aggregate has **no write path** into any business aggregate. This is a structural guarantee, not a convention. | GATE |
| NOT-030 | Message volume has a real third-party cost, and the product says so plainly. | REQUIRED |

---

## 8. Violation handling

- A **GATE** violation is an automatic `NO-GO`. Work stops immediately. The repository owner is
  notified. Evidence is preserved at the exact commit SHA. No unrelated work continues on the branch
  in the meantime.
- A **CRITICAL** violation blocks the Definition of Done for the Step that owns it.
- A **REQUIRED** deviation needs an accepted decision record under `../decisions/`.
- Concealing or downplaying any violation is a graver violation than the original fault.

## 9. Status

Every invariant in this register is **`NOT IMPLEMENTED`** and **unverified**. There is no code to
enforce them, no test to prove them, and no runtime to run them against. Backend runtime is `ABSENT`;
Flutter workspace is `ABSENT`. This document claims no test, build, deployment, CI run, or UAT.

---

## Related documents

- [`AGGREGATE_CATALOG.md`](AGGREGATE_CATALOG.md)
- [`ENTITY_AND_VALUE_OBJECT_CATALOG.md`](ENTITY_AND_VALUE_OBJECT_CATALOG.md)
- [`TENANT_BOUNDARIES.md`](TENANT_BOUNDARIES.md)
- [`COMMANDS_AND_POLICIES.md`](COMMANDS_AND_POLICIES.md)
