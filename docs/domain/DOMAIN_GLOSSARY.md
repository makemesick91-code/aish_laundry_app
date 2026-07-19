# Domain Glossary — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** `NOT IMPLEMENTED` (documentation only; backend runtime `ABSENT`, Flutter workspace `ABSENT`)
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.2.0

This glossary fixes the vocabulary of the Aish Laundry App domain so that every later document, API
contract, and (eventually) implementation uses one word for one concept. Where a term is canonical in
the Master Source, this file restates it and never extends it. Where a term is domain modelling
detail introduced by Step 1, it is marked **(Step 1 modelling term)**.

All example data in this document is **fictional**. This repository is PUBLIC
([DEC-0016](../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)); no real
customer name, phone number, or address appears anywhere.

---

## 1. Language conventions

- Product-facing copy is Bahasa Indonesia; domain terms in this repository are English for precision
  (Master Source §1.6).
- Where the shop floor uses an Indonesian word that has no precise English equivalent — *kiloan*,
  *satuan*, *nota*, *antar-jemput*, *ojek lokal*, *cucian menumpuk* — the Indonesian word is the
  canonical domain term and is used untranslated.
- Status identifiers are `UPPER_SNAKE_CASE` and are canonical. They are never localised in data, only
  in presentation.

---

## 2. Tenancy vocabulary

The canonical hierarchy is:

```
User Account -> Membership -> Tenant/Organization -> Laundry Brand -> Outlet
```

| Term | Definition |
| --- | --- |
| **User Account** | A person's platform identity, keyed on a phone number. One person, one account across the platform. Holds no business authority by itself. |
| **Membership** | The link between a User Account and a Tenant, carrying roles and permissions **within that tenant**. Authorisation is always evaluated against a Membership, never a bare User Account. |
| **Tenant / Organization** | The isolation boundary and the commercial boundary. Subscription, plan limits, billing, and data isolation all live here. |
| **Laundry Brand** | A customer-facing brand owned by a tenant. A tenant may operate several brands with different names, pricing, and presentation. |
| **Outlet** | A physical location belonging to a brand, where orders are taken and production happens. |
| **Tenant context** | The server-derived, membership-verified scope applied to every business operation. Never taken from a client-supplied value. |
| **Tenant switcher** | The client affordance for a user holding memberships in more than one tenant. Switching re-derives tenant context server-side and partitions client caches. |
| **Platform administration** | The explicitly separated, audited surface that operates the SaaS itself. It is not a tenant role and is never implemented by relaxing tenant scoping. |
| **Cross-tenant exposure** | Any read, list, count, search, export, aggregate, cache hit, or file access that returns one tenant's data to a principal scoped to another. Automatic `NO-GO`. |

**Tenant-scoped customer identity.** A customer profile belongs to exactly one tenant. The same
phone number appearing in two tenants produces **two unrelated tenant customer profiles**. Profiles
are never merged because name, email, phone number, device, or the identity of the owner match.
There is no global shared customer profile.

---

## 3. Commercial and catalog vocabulary

| Term | Definition |
| --- | --- |
| **Kiloan** | Laundry priced by weight. The billable quantity is a `Weight`. |
| **Satuan** | Laundry priced per item. The billable quantity is a `Quantity` of garments. |
| **Package** | A bundled service sold as one catalog item with its own price. |
| **Add-on** | An optional extra applied to an order line (for example express handling, extra fragrance) with its own price contribution. |
| **Service Catalog** | The tenant's set of sellable services, scoped per brand. |
| **Price List** | A versioned set of prices for catalog items, effective over a date range, scoped to a brand and optionally an outlet. |
| **Price Rule** | A conditional modifier on a price — minimum charge, express surcharge, tiered weight band, member discount. Rules are versioned like prices. |
| **Price snapshot** | The immutable copy of the price and rule evaluation captured onto an order line at the moment the order is created. **(Step 1 modelling term)** |
| **Nota** | The customer-facing receipt/ticket for an order. A reprint of an old nota always shows the price that applied when the order was created. |
| **Human order number** | The short, human-readable, outlet-scoped, sequential order reference printed on the nota. It is **guessable by design** and therefore never grants access to anything. |

**Price snapshot immutability is canonical** (Master Source §16.4). Editing a price list changes
future orders only. No price-list edit, rule change, discount change, or catalog retirement ever
alters a past order, a past invoice, or a past nota.

---

## 4. Order and production vocabulary

| Term | Definition |
| --- | --- |
| **Laundry Order** | The central business record: a customer's laundry accepted at an outlet, priced, produced, and returned. |
| **Order Line** | One priced element of an order — a catalog item with quantity or weight, its price snapshot, and its add-ons. |
| **Order Condition Evidence** | Photographs and notes recording the condition of items at intake and at handover — stains, damage, colour bleed risk, missing buttons. Private data. |
| **Production Job** | The work of moving one order (or one batch of items within it) through the production stages at an outlet. |
| **Production stage** | A named step of physical work: sorting, washing, drying, finishing. Stages map onto order statuses but are recorded with their own start and completion timestamps. |
| **Batch** | A grouping of items from one or more orders processed together in a machine load. **(Step 1 modelling term)** |
| **Quality Control Inspection** | The verification of finished work before the order may be declared ready. |
| **Rework** | Production work repeated because quality control failed. Rework never resets unclaimed aging (§6). |
| **Waiver** | An authorised decision to release an order despite a failed or incomplete inspection. Requires permission, a reason, and an audit entry. |
| **First-ready timestamp** | The instant an order **first** reached `READY_FOR_PICKUP`. Recorded once, immutable thereafter, and the anchor for all unclaimed aging. |

### 4.1 Canonical order statuses (exactly fifteen)

`DRAFT`, `RECEIVED`, `AWAITING_PROCESS`, `SORTING`, `WASHING`, `DRYING`, `FINISHING`,
`QUALITY_CONTROL`, `REWORK`, `READY_FOR_PICKUP`, `SCHEDULED_FOR_DELIVERY`, `OUT_FOR_DELIVERY`,
`COMPLETED`, `CANCELLED`, `ISSUE`.

No sixteenth status exists. Transitions are enumerated in
[`../state-machines/ORDER_STATE_MACHINE.md`](../state-machines/ORDER_STATE_MACHINE.md); anything not
enumerated is forbidden.

### 4.2 Canonical quality control statuses (exactly four)

`PENDING`, `PASSED`, `FAILED_REWORK_REQUIRED`, `WAIVED_WITH_AUTHORIZATION`.

---

## 5. Money vocabulary

| Term | Definition |
| --- | --- |
| **Money** | A value object over an **integer number of Rupiah**. The smallest representable unit is Rp1. Floating point is forbidden in every money path — storage, computation, transport, and display round-trip. |
| **Payment** | A recorded transfer of money from customer to tenant, idempotent on a `ClientReference`. |
| **Payment intent** | An offline-recorded intention to pay that has not yet been confirmed server-side. An intent is never a payment and is never shown as "paid". |
| **Refund** | Money returned to the customer. Requires permission, a `ReasonCode`, and a recorded reason text. |
| **Void** | Cancellation of a payment before settlement, subject to the same permission and reason rules as a refund. |
| **Reversal entry** | A correcting financial entry that offsets an earlier entry while preserving it. The only permitted correction mechanism alongside adjustment. |
| **Adjustment entry** | A correcting financial entry that changes a balance forward without deleting history. |
| **Receivable** | The unpaid balance owed on an order. |
| **Held invoice** | An invoice that remains unsettled while the laundry is uncollected — a headline figure on the unclaimed dashboard. |
| **Cashier Shift** | A bounded period of counter operation by a cashier, closed by comparing expected cash against actual cash. |
| **Variance** | Expected minus actual, recorded explicitly, never absorbed, never auto-rounded away, never suppressed from a report. |
| **Courier cash** | Cash collected at the door by a courier, tracked from collection to hand-over. |
| **Courier Settlement** | The reconciliation of a courier's collected cash for a shift or route. |
| **Idempotency** | The guarantee that the same logical financial operation, submitted any number of times, produces exactly one financial record. |

**Financial records are never hard-deleted through ordinary UI.** Corrections are reversal or
adjustment entries only (Master Source §16.3).

---

## 6. Unclaimed laundry vocabulary

| Term | Definition |
| --- | --- |
| **Cucian menumpuk** | Finished laundry the customer has not collected. |
| **Aging** | Elapsed business time since the **first-ready timestamp**. |
| **Aging bucket** | One of the canonical reporting bands: 1–2 days; 3–6 days; 7–13 days; 14–30 days; More than 30 days. |
| **Reminder ladder** | The canonical H+1 / H+3 / H+7 / H+14 sequence. |
| **Follow-up task** | The real, assignable, closable task created at H+7 with a named owner. Not a flag and not a notification. |
| **Escalation** | The H+14 hand-off to an outlet manager or owner, surfacing in their dashboards. |
| **Reason not collected** | A first-class recorded field capturing why the customer has not collected. |
| **Unclaimed Laundry Case** | The aggregate that tracks one order's unclaimed lifecycle: aging, ladder progress, follow-up task, escalation, and resolution. |

**Absolute prohibition.** The product **never** automatically discards, sells, auctions, donates, or
transfers ownership of a customer's laundry — not behind a configuration flag, not as a future
option, not as a backlog item. The product's role ends at reminding, escalating, and reporting
(Master Source §11.4).

---

## 7. Tracking vocabulary

| Term | Definition |
| --- | --- |
| **Portal Tracking Publik** | The browser-based public tracking surface. No app installation, no account, no password. |
| **Tracking Access** | The aggregate representing one issued tracking capability for one order. |
| **Tracking token** | The high-entropy secret embedded in the tracking link. It is **not** the order number and is not derivable from it. |
| **TrackingTokenHash** | The hashed form stored server-side. Plaintext exists only inside the link. |
| **Public tracking projection** | The **separate read model** built for the portal, containing only masked, safe fields. It is not the internal order representation and is never derived by "hiding" fields at render time. **(Step 1 modelling term)** |
| **Masking** | Context-dependent partial redaction of names and phone numbers. The portal never shows a full address. |
| **Sensitive portal action** | An action such as changing a delivery address or requesting a schedule change, which requires OTP verification before it takes effect. |

The Customer Android application is an **enhancement to** the portal, never a replacement
([DEC-0014](../decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md)).

---

## 8. Pickup and delivery vocabulary

| Term | Definition |
| --- | --- |
| **Antar-jemput** | Pickup and delivery, a core product capability, not an add-on ([DEC-0007](../decisions/DEC-0007-pickup-and-delivery-as-core-product.md)). |
| **Pickup Delivery Job** | One scheduled movement of laundry between a customer location and an outlet, in either direction. |
| **Zone** | An outlet-defined service area used to match a request to coverage and to group jobs. |
| **Time window** | The window communicated to the customer. A window, never a fictitious exact minute. |
| **Route ordering** | A simple ordered list of stops for a courier. |
| **Usulan rute** | Route **suggestion**. The canonical phrase. The product never says "rute optimal", never claims optimisation it does not compute, and never promises an arrival time it does not calculate. |
| **Internal courier** | A staff courier holding a membership and using Aish Laundry Ops Android. |
| **External ojek lokal** | A third-party rider who holds **no membership**, no account, and no application access. |
| **Guest job link** | The scoped, expiring, revocable, high-entropy link issued to an external ojek. Exposes exactly one assigned job and the minimum data needed to complete it — never customer history, other orders, pricing, or any other tenant data. |
| **Delivery Proof** | The recorded evidence of a custody transfer: OTP, photo, signature, recipient name, per tenant policy. Private data, signed-URL only. |
| **Failed delivery** | A first-class outcome with a recorded reason. The laundry returns to the outlet and the order returns to a defined status. It is not an error state. |

### 8.1 Canonical pickup/delivery job statuses (exactly eleven)

`REQUESTED`, `CONFIRMED`, `SCHEDULED`, `ASSIGNED`, `EN_ROUTE`, `ARRIVED`, `PICKED_UP`, `DELIVERED`,
`FAILED`, `RESCHEDULED`, `CANCELLED`.

---

## 9. Notification vocabulary

| Term | Definition |
| --- | --- |
| **Notification** | One intended message to one recipient about one event, with a class, a template, a channel, and a delivery outcome. |
| **Provider abstraction** | The internal interface all sending passes through. No vendor SDK, payload shape, or identifier leaks into business logic. |
| **Transactional message** | A message about the customer's own order. |
| **Marketing message** | A promotional or campaign message. Requires consent, separately from transactional messaging. |
| **Quiet hours** | Default **20.00–08.00 outlet local time**. Non-urgent messages are deferred to the next permitted window — never dropped, never sent anyway. |
| **Deduplication key** | The stable identity (recipient + event + order + intended send window) that guarantees a retry, replay, or double-trigger never produces a second identical message. |
| **Opt-out** | The customer's withdrawal of marketing consent. Honoured at send time, across all outlets of the tenant, permanently, and never reset by a data import. |
| **Manual deep-link fallback** | A prepared link a staff member taps to send a message themselves when automated sending is unavailable. Explicit, visible, and **never described as automation**. |

**A provider failure never changes business state.** A WhatsApp or gateway failure never cancels,
blocks, or fails an order. The product never promises "unlimited WhatsApp".

---

## 10. Offline vocabulary

| Term | Definition |
| --- | --- |
| **ClientReference** | The stable identifier the client generates **before** attempting an important operation, persists with the queued operation, and **reuses unchanged on every retry**. The server's idempotency key. |
| **Offline Operation** | A queued intent held on device until the server accepts or rejects it. |
| **Persistent queue** | A queue that survives application restart, device reboot, and crash. An in-memory queue is not acceptable. |
| **Financial queue** | The subset of queued operations that move money. Never cleared by a cache wipe, a logout, an upgrade, or a developer convenience action. Clearing requires an explicit, permissioned, audited action. |
| **Sync Conflict** | A detected disagreement between local and server state. Conflicts affecting money surface to a human and are **never** silently overwritten. |
| **Reconciliation** | The client adjusting its working copy to match the server, which is the final source of truth. |

Local data is partitioned **per tenant and per user**. A tenant switch or user switch never exposes
the previous context's cached data.

---

## 11. Cross-cutting vocabulary

| Term | Definition |
| --- | --- |
| **Aggregate** | A consistency boundary with a single root; all invariants inside it hold at the end of every command. |
| **Domain event** | A recorded fact about something that happened, named in the past tense, immutable once emitted. |
| **Command** | A request to change state, which may be rejected. |
| **Policy** | A rule that reacts to an event by issuing a command, subject to preconditions. |
| **Projection / read model** | A derived, query-shaped view built from events or records. Never the system of record. |
| **Audit Entry** | An append-only record of who did what, in which tenant and outlet, when, and why. |
| **AuditActor** | The identified principal behind an action: a membership, a platform admin under audited impersonation, or a named system process. Never anonymous. |
| **Attachment** | A stored file — photograph, signature, export. Private by default, tenant-scoped key, unguessable, signed-URL only. |
| **ReasonCode** | A machine-readable enumerated reason accompanying refunds, voids, waivers, failed deliveries, variances, and non-collection. Paired with free-text where a human explanation is required. |
| **Version** | The optimistic-concurrency counter on an aggregate, used to detect concurrent modification. |

---

## 12. Status vocabulary (governance)

Only the approved statuses may be used anywhere in this repository: `PLANNED`, `IN PROGRESS`,
`TESTED`, `WATCH`, `NOT IMPLEMENTED`, `ABSENT`, `NOT APPLICABLE`, `NOT STARTED`, `NO-GO`, `GO`.
`GO` is conferred by the repository owner and is never self-declared.

Everything described in this glossary is `NOT IMPLEMENTED`. Backend runtime is `ABSENT`. Flutter
workspace is `ABSENT`. No test, build, deployment, CI run, or UAT is claimed by this document.

---

## 13. Related documents

- [`BOUNDED_CONTEXTS.md`](BOUNDED_CONTEXTS.md) — the twenty contexts and their responsibilities
- [`AGGREGATE_CATALOG.md`](AGGREGATE_CATALOG.md) — every aggregate in the model
- [`ENTITY_AND_VALUE_OBJECT_CATALOG.md`](ENTITY_AND_VALUE_OBJECT_CATALOG.md) — value object definitions
- [`DOMAIN_INVARIANTS.md`](DOMAIN_INVARIANTS.md) — the numbered requirement register
- [`TENANT_BOUNDARIES.md`](TENANT_BOUNDARIES.md) — isolation rules in the domain model
