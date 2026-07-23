# Aish Laundry App — Master Source

**Document version: 1.4.6**
**Baseline date: 19 July 2026**

Owner: Aish Tech Solution
Category: Multi-Tenant Laundry Operations, Customer Tracking, Pickup and Delivery SaaS
Primary market: Laundry UMKM dan jaringan laundry Indonesia
Primary language: Bahasa Indonesia · Currency: Rupiah · Timezone: Asia/Jakarta
Local monorepo root: `aish_laundry` · Remote repository: `aish_laundry_app` · Default branch: `main`
Repository visibility: **PUBLIC** — an accepted deviation from a canonical desired **PRIVATE**
(see [`ASSUMPTIONS.md`](ASSUMPTIONS.md) AMENDMENT-0001, and
[DEC-0016](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md))

---

## Table of contents

| § | Section |
| --- | --- |
| 1 | Canonical rules |
| 2 | Vision |
| 3 | Product values |
| 4 | Multi-tenancy |
| 5 | Platforms |
| 6 | Architecture |
| 7 | Roles |
| 8 | Product modules |
| 9 | Public tracking portal |
| 10 | Pickup and delivery |
| 11 | Unclaimed laundry |
| 12 | Owner dashboard and portfolio |
| 13 | Offline-first |
| 14 | Notifications and WhatsApp |
| 15 | Security |
| 16 | Financial integrity |
| 17 | Privacy |
| 18 | UX and design foundation |
| 19 | Performance |
| 20 | Observability |
| 21 | Pricing |
| 22 | MVP |
| 23 | Non-goals |
| 24 | Roadmap |
| 25 | Definition of Done |
| 26 | Git and CI |
| 27 | AI development rules |
| 28 | Testing |
| 29 | Success metrics |
| 30 | Positioning |
| 31 | Decision records |
| 32 | Changelog |
| 33 | AI instructions |
| 34 | Step 1 artefacts — requirements and domain model |
| 35 | Step 2 artefacts — design system and UX foundation |

---

## 1. Canonical rules

This document is the **single source of truth** for Aish Laundry App. Every other artefact in this
repository — README, contributing guide, security policy, rule files under `.claude/rules/`, decision
records, validator scripts, evidence packs, and all future code — is subordinate to it.

### 1.1 Precedence

1. `docs/MASTER_SOURCE.md` (this document).
2. Accepted decision records in [`decisions/`](decisions/), which refine but never contradict this document.
3. Governance policies in [`governance/`](governance/).
4. Rule files in `.claude/rules/`.
5. Everything else.

Where any artefact disagrees with this document, **this document wins** and the disagreeing artefact is
defective and must be corrected in the same pull request that discovers the conflict.

### 1.2 Amendment procedure

- This document changes only through a pull request that also adds or updates a decision record when a
  product decision changes.
- The document version follows semantic versioning: **MAJOR** for a reversal of a locked decision,
  **MINOR** for new canonical scope, **PATCH** for clarification that changes no meaning.
- Every version bump adds an entry to [`CHANGELOG.md`](CHANGELOG.md).
- The baseline date is the date of the 1.0.0 baseline and does not change; subsequent versions carry
  their own dates in the changelog.

### 1.3 Honesty rules

These are absolute and apply to every human and every AI agent working in this repository:

1. **Never claim any implementation, test, deployment, or CI that does not exist.**
2. **Never present an empty folder as an implemented feature.**
3. Use only the status vocabulary defined in [`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md).
4. **`GO` is never written as the status of Step 0 in the foundation pull request.** The highest status
   Step 0 may carry before merge is `IN PROGRESS`, and after validation `TESTED` or `WATCH`.
5. Every claim of completion is backed by evidence bound to an exact commit SHA.
6. Internal markdown links must point at files that actually exist in the tree.
7. If something is unknown, it is written as an assumption in [`ASSUMPTIONS.md`](ASSUMPTIONS.md), not
   invented.

### 1.4 Naming rules

- The official product name is exactly **Aish Laundry App**. No other canonical product name may appear
  anywhere in this repository.
- Platform names are canonical: *Aish Laundry Customer Android*, *Aish Laundry Ops Android*,
  *Aish Laundry Console Web*, *Portal Tracking Publik*.
- Step numbers are canonical and are never reused or swapped without a decision record.
- Decision record identifiers are permanent; a superseded decision keeps its number and gains a
  supersession note.

### 1.5 Scope rule

Each canonical Step delivers exactly its declared scope. Work belonging to a later Step is never
performed early, and work belonging to an earlier Step is never quietly deferred. The roadmap in §24 is
locked.

### 1.6 Language rule

Bahasa Indonesia is the primary language of the product: all customer-facing copy, staff-facing copy,
notification templates, and error messages are written in Bahasa Indonesia. Technical governance terms —
`tenant`, `idempotent`, `NO-GO`, `Definition of Done`, HTTP verbs, database terminology — may remain in
English for precision.

---

## 2. Vision

### 2.1 The problem

Laundry businesses in Indonesia — from a single-outlet kiloan shop to a multi-brand chain — run on
paper nota, WhatsApp chats, and the owner's memory. Four failures recur:

1. **Customers cannot see their own order.** Every status question becomes a phone call or a WhatsApp
   message that a busy kasir has to answer manually.
2. **Laundry piles up uncollected.** Finished orders sit on shelves for weeks. The work is done, the
   shelf space is consumed, and the money is never collected. Owners discover the problem only when the
   shelf is full.
3. **Pickup and delivery is improvised.** Couriers coordinate through personal chat, cash collected on
   delivery is reconciled from memory, and proof of delivery does not exist when a customer disputes.
4. **The owner cannot see the business.** An owner with three outlets has three separate realities and
   no consolidated number that can be trusted.

Existing software either targets large enterprises at enterprise prices, or is a generic POS that knows
nothing about laundry-specific realities: kiloan versus satuan, per-item production stages, garments that
must be tracked individually, and customers who genuinely forget to collect.

### 2.2 The product

Aish Laundry App is a multi-tenant SaaS that makes a laundry business legible to three audiences at once:

- **The customer** knows where their laundry is without asking anyone.
- **The staff** know what to do next, even when the internet drops.
- **The owner** knows what every outlet earned, what is stuck, and what money is at risk.

It is built for Indonesian laundry UMKM first. Bahasa Indonesia, Rupiah as integer, Asia/Jakarta time,
WhatsApp as the notification channel that customers actually read, and Android as the device that staff
and customers actually own.

### 2.3 The three differentiators

Three capabilities are not features to be added later — they are the reason the product exists, and they
are locked into the foundation from Step 0:

1. **Public tracking without app installation** (§9, DEC-0006). A customer receives a secure link over
   WhatsApp and sees their order status in a browser. No download, no account, no password.
2. **Pickup and delivery as a first-class product** (§10, DEC-0007). Not an afterthought bolted onto a
   POS, but a scheduled, zoned, courier-assigned, proof-captured, cash-reconciled operation.
3. **Unclaimed laundry recovery** (§11, DEC-0008). A structured H+1 / H+3 / H+7 / H+14 reminder and
   escalation ladder that converts finished-but-uncollected laundry back into shelf space and cash.

### 2.4 Long-term direction

Aish Laundry App aims to be the operating system for Indonesian laundry businesses: the place where
orders, production, money, customers, and couriers all live. Growth comes from an owner adding a second
outlet, then a second brand, then recommending the product to another owner — not from locking data in.

---

## 3. Product values

These values decide arguments. When a design choice is contested, the higher value wins.

### 3.1 Honesty over optimism

The product never tells a customer that laundry is ready when it is not, never tells an owner a number
it cannot substantiate, and never tells a courier a route is optimal when it is merely ordered. The
repository never claims work it has not done. Honesty is a product property and an engineering property
and they are the same property.

### 3.2 Tenant isolation is sacred

One tenant's data is never visible to another tenant under any circumstance, for any convenience, for
any dashboard, for any support workflow. There is no "small leak". Cross-tenant exposure is an automatic
NO-GO (§4, §15, DEC-0012).

### 3.3 Money is never guessed

Financial data is exact, auditable, and never destroyed. Integer Rupiah, idempotent payments,
server-verified callbacks, corrections by reversal rather than deletion (§16, DEC-0012).

### 3.4 The shop floor comes first

A kasir during the evening rush has both hands busy and no patience. The primary action of every screen
is the shortest possible path. Interfaces degrade gracefully when the network fails. A courier on a
motorcycle gets a simple interface, not a dense dashboard.

### 3.5 Customers own their attention

Notifications are useful or they do not exist. Transactional and marketing messages are separated,
opt-out is honoured, quiet hours are respected, and duplicates are suppressed (§14).

### 3.6 Data belongs to the tenant

Tenant data remains exportable per policy when a subscription lapses. Tenant data is never used to train
AI models without explicit consent. There is no hostage-taking as a retention strategy (§17, §21).

### 3.7 Fair, transparent, unglamorous pricing

Pricing is published, predictable, and free of traps. No lifetime cloud plan, no per-nota fee on normal
plans, third-party messaging costs shown transparently, and the security baseline available on every
plan (§21, DEC-0010, DEC-0011).

### 3.8 Boring technology, carefully used

The architecture is deliberately conventional: Flutter, Laravel, PostgreSQL, Redis, S3-compatible
storage, REST. Novelty is spent on the laundry domain, not on infrastructure fashion.

---

## 4. Multi-tenancy

### 4.1 Hierarchy

```
User Account
    └── Membership
            └── Tenant / Organization
                    └── Laundry Brand
                            └── Outlet
```

- **User Account** — a person, identified by phone number, with credentials. A person has exactly one
  account across the platform.
- **Membership** — the link between a user account and a tenant, carrying the roles and permissions that
  the user holds *within that tenant*. Authorisation is always evaluated against a membership, never
  against a bare user account.
- **Tenant / Organization** — the commercial and isolation boundary. Subscription, billing, plan limits,
  and data isolation all live here.
- **Laundry Brand** — a customer-facing brand belonging to a tenant. A tenant may operate several brands
  with different names, pricing, and presentation.
- **Outlet** — a physical location belonging to a brand, where orders are taken and production happens.

### 4.2 Hard rules

These thirteen rules are canonical and non-negotiable:

1. One user may join multiple tenants.
2. One owner may own or manage multiple tenants.
3. A tenant may have multiple brands.
4. A brand may have multiple outlets.
5. A tenant switcher exists in every authenticated client.
6. Subscription and billing operate at the tenant boundary.
7. Every business table has `tenant_id`.
8. All business queries are tenant-scoped.
9. A client-supplied tenant identifier is **never** authorisation proof.
10. The backend verifies membership **and** permission, server-side, on every request.
11. Data is **never** merged merely because owner name, email, or phone number match.
12. **Cross-tenant data exposure is an automatic NO-GO.**
13. The owner portfolio dashboard must not weaken tenant isolation.

### 4.3 Consequences for design

- Every migration that creates a business table also creates its `tenant_id` column and its index.
- Every repository or query builder used for business data enforces the tenant scope by construction, so
  that forgetting the scope is a compile-time or framework-level failure rather than a silent leak.
- The tenant context is derived from the authenticated session and the verified membership, then applied
  server-side. A `tenant_id` in a request body or header is treated as a *hint that must be verified*,
  never as a grant.
- Global platform-administration surfaces are explicitly separated from tenant surfaces and are audited.

### 4.4 Tenant switching

A user with memberships in several tenants sees a tenant switcher. Switching tenants:

- issues a new server-side tenant context bound to the verified membership;
- clears or partitions client-side caches so that no data from the previous tenant is visible;
- is recorded in the audit trail.

Local data on device is separated per tenant *and* per user (§13).

Full enforcement policy: [`governance/TENANT_ISOLATION_POLICY.md`](governance/TENANT_ISOLATION_POLICY.md).
Decision records: DEC-0002, DEC-0003, DEC-0012.

---

## 5. Platforms

Four canonical platforms. No fifth platform exists without a decision record.

### 5.1 Aish Laundry Customer Android

Flutter. The customer-facing application.

- Login by phone number and OTP.
- Active orders and order history.
- Order tracking.
- Pickup request.
- Saved addresses.
- Invoices.
- Loyalty.
- Feedback.
- Notifications.

The customer app **does not replace** the public tracking portal (DEC-0014). Installation is always
optional; a customer who never installs anything still gets full tracking through the portal.

### 5.2 Aish Laundry Ops Android

Flutter. The staff-facing operational application, used on the shop floor and on the road. Roles served:

- kasir;
- manager outlet;
- operator produksi;
- quality control;
- kurir;
- laundry admin.

This is the application that must work offline (§13), because shop-floor connectivity in Indonesia is not
reliable and a kasir cannot stop taking orders because the network dropped.

### 5.3 Aish Laundry Console Web

Flutter Web. The management console. Roles served:

- owner;
- tenant admin;
- manager;
- finance;
- platform admin.

Reporting, configuration, master data, finance, subscription management, and the owner portfolio
dashboard live here.

### 5.4 Portal Tracking Publik

Browser-based, **no app installation required**. This is the customer's default tracking experience and
the product's most visible differentiator.

Flutter is **not mandatory** for this surface: if a lighter web stack delivers materially better load
performance on low-end Android browsers over poor networks, that stack is chosen. Performance for this
surface outranks stack uniformity. The choice is recorded in a decision record in the Step that builds it.

Full behaviour: §9. Decision record: DEC-0006.

---

## 6. Architecture

The architecture is **locked**. Changing any locked element requires a new architecture decision record
in a later Step.

### 6.1 Locked stack

| Layer | Technology |
| --- | --- |
| Frontend | Flutter + Dart |
| Backend | Laravel modular monolith |
| API | REST JSON, versioned at `/api/v1` |
| Database | PostgreSQL |
| Cache, queue, locks, rate limiting | Redis |
| Files | S3-compatible object storage |
| Style | API-first modular monolith |
| Repository | Monorepo |

### 6.2 Why a modular monolith

A modular monolith is the correct shape for this product at this stage (DEC-0005):

- A laundry order touches customers, pricing, production, payment, and notification in a single business
  transaction. Distributing that across services buys distributed-transaction problems and no benefit.
- One team, one deployment unit, one database — operational cost stays proportionate to a UMKM-priced
  product.
- Module boundaries are enforced in code (namespaces, module directories, explicit public interfaces) so
  that extraction into services later is a refactor rather than a rewrite.

### 6.3 API-first

Every capability is exposed through the versioned REST API before any client consumes it. Consequences:

- No client talks to the database directly, ever.
- No business logic lives only in a client.
- The API is the contract; all four platforms are consumers of the same contract.
- Breaking changes require a new API version, not a silent change to `/api/v1`.
- API responses are JSON with a stable envelope, stable error codes, and no leakage of internal detail.

### 6.4 Monorepo

One repository holds backend, all clients, shared packages, infrastructure definitions, and governance.
This keeps a cross-cutting change — an API contract plus its client consumers plus its documentation — in
a single reviewable pull request.

Layout is described in [`../README.md`](../README.md) §3.

### 6.5 Data and state principles

- PostgreSQL is the system of record. Redis is never the system of record for business data.
- Redis holds cache, queues, distributed locks, and rate-limit counters. Losing Redis degrades
  performance; it must never lose money or orders.
- Files — laundry photographs, proof-of-delivery images, signatures — live in S3-compatible object
  storage, are private by default, and are served only through signed URLs.
- All timestamps are stored in UTC and rendered in Asia/Jakarta, or in the outlet's local time where
  outlet-local semantics matter (for example quiet hours, §14).

### 6.6 Step 0 constraint

**Step 0 creates no runtime for any of the above.** No Flutter workspace, no Laravel application, no
database, no Redis, no bucket, no deployment. The stack above is a locked *decision*, not an
implementation.

---

## 7. Roles

Roles are held through a **membership**, scoped to a tenant, and may be further scoped to a brand or an
outlet. Permission checks are always server-side (§15).

### 7.1 Tenant-scoped roles

| Role | Primary surface | Core responsibility |
| --- | --- | --- |
| Owner | Console Web | Owns the tenant; sees the portfolio across brands and outlets; approves commercial decisions. |
| Tenant admin | Console Web | Configures the tenant: brands, outlets, users, roles, master data, policies. |
| Manager | Console Web + Ops Android | Runs one or more outlets; sees operational and financial performance for the scope granted. |
| Finance | Console Web | Reconciles payments, refunds, shift closings, courier cash; produces financial reports. |
| Manager outlet | Ops Android | Runs a single outlet day to day; approves exceptions; handles escalations. |
| Kasir | Ops Android | Takes orders, quotes prices, accepts payment, hands over finished laundry. |
| Operator produksi | Ops Android | Executes production stages and records progress. |
| Quality control | Ops Android | Verifies finished work before it is marked ready. |
| Kurir | Ops Android | Executes pickup and delivery, captures proof, collects cash. |
| Laundry admin | Ops Android | Administrative support at the outlet: customers, orders, reprints, corrections within permission. |
| Customer | Customer Android + Portal | Owns their own orders and personal data. |

### 7.2 Platform-scoped roles

| Role | Surface | Core responsibility |
| --- | --- | --- |
| Platform admin | Console Web | Operates the SaaS platform itself: subscriptions, plan limits, tenant lifecycle, platform health. |

### 7.3 Role rules

1. A role grants permissions **within a tenant only**. There is no cross-tenant role except the
   explicitly separated platform-admin surface.
2. Platform support has **no silent access to tenant data**. Support impersonation is explicit,
   time-bound, consented where required, and fully audited (§15, §17).
3. Least privilege is the default: a new role starts with nothing and is granted what it needs.
4. Sensitive financial operations — refund, void, price override, shift close adjustment — require an
   explicit permission and a recorded reason (§16).
5. Roles are configurable per tenant within the permission model; the permission model itself is
   canonical and not tenant-editable.

---

## 8. Product modules

The functional surface of Aish Laundry App, expressed as modules. **All modules are NOT IMPLEMENTED as of
the Step 0 baseline.** The Step column names the canonical Step that first delivers the module.

| Module | Purpose | First delivered in |
| --- | --- | --- |
| Identity and authentication | Phone + OTP login, sessions, devices, revocation | Step 3 |
| Tenancy and membership | Tenants, brands, outlets, memberships, tenant switcher | Step 3 |
| RBAC and permissions | Roles, permissions, server-side authorisation | Step 3 |
| Customer management | Customer records, contacts, addresses, consent, history | Step 4 |
| Service and price list | Kiloan, satuan, packages, add-ons, per-brand pricing | Step 4 |
| Outlet master data | Operating hours, capacity, zones, printers, shift definitions | Step 4 |
| POS and order intake | Order creation, quoting, nota, deposits | Step 5 |
| Payment | Cash, transfer, gateway, idempotency, refunds, voids | Step 5 |
| Production operations | Stages, batches, item tracking, quality control, rework | Step 6 |
| Order status lifecycle | Canonical status machine including `READY_FOR_PICKUP` | Step 6 |
| Customer tracking | Secure token issuance, public portal, status visibility | Step 7 |
| Notification and WhatsApp | Provider abstraction, templates, opt-out, quiet hours, dedupe | Step 7 |
| Pickup and delivery | Requests, scheduling, zones, courier assignment, proof, cash | Step 8 |
| Unclaimed laundry recovery | Aging, H+1/H+3/H+7/H+14 ladder, follow-up tasks, dashboard | Step 9 |
| Finance and reporting | Shift closing, reconciliation, revenue and receivable reports | Step 10 |
| Owner portfolio | Cross-brand, cross-outlet consolidated view within one tenant | Step 10 |
| Customer application | Customer Android experience, loyalty, feedback, invoices | Step 11 |
| Subscription and billing | Plans, limits, trial, upgrade, downgrade, lapse handling | Step 12 |
| Platform administration | Tenant lifecycle, platform health, audited support tooling | Step 12 |
| Security hardening | Rate limiting, backup, recovery, performance budgets | Step 13 |
| Observability | Logging, metrics, tracing, alerting | Step 13 |

Module boundaries in the backend mirror this table. A module owns its data, exposes an explicit interface,
and does not reach into another module's tables.

---

## 9. Public tracking portal

The Portal Tracking Publik lets a customer see their order without installing anything and without
creating an account. It is the product's most visible differentiator (DEC-0006) and its most exposed
attack surface.

### 9.1 Canonical behaviour

- The customer receives a **secure tracking link**, normally over WhatsApp.
- **No login is required** for safe information.
- The link is **shareable via WhatsApp** — a customer may forward it to a family member who is collecting.

### 9.2 Canonical security rules

These rules are non-negotiable and may not be relaxed by any implementation:

1. The token is **high-entropy**, generated by a cryptographically secure random source.
2. The token is **stored hashed**. The plaintext token exists only in the link.
3. The token is **not the order number**. Order numbers are sequential, guessable, and printed on nota;
   they must never grant access.
4. Tokens are **revocable** — a customer or an outlet can invalidate a link that was shared too widely.
5. Tokens are **expiring** — a link does not remain valid forever.
6. The portal is served with **`noindex`** so that tracking pages never enter search engines.
7. **Personal data is masked.** Names are partially masked; phone numbers are partially masked.
8. The portal **never shows the full address**.
9. **Sensitive actions require OTP** — for example changing a delivery address or requesting a schedule
   change from the portal.

### 9.3 Information the portal may show

Safe by default: order number, brand and outlet identity, service type, current status and status
history, estimated completion, amount due and payment state, and the actions available to the customer.

Never shown without OTP verification: full address, full phone number, other orders belonging to the
same customer, internal notes, staff identity beyond what is operationally necessary, and laundry
photographs.

### 9.4 Relationship to the Customer Android app

The Customer Android application is an **enhancement**, never a replacement (DEC-0014). Any capability
that a customer genuinely needs in order to follow their laundry must be reachable from the portal. The
portal is not degraded to push app installation.

---

## 10. Pickup and delivery

Pickup and delivery is a **core product capability**, not an optional add-on (DEC-0007). Many Indonesian
laundry businesses compete primarily on antar-jemput; the product treats it as a first-class operation.

### 10.1 Canonical capabilities

- **Pickup request** — raised by the customer (app or portal) or by staff on the customer's behalf.
- **Schedule** — a pickup or delivery is scheduled, not merely requested.
- **Time window** — the customer is given a window, not a fictitious exact minute.
- **Zone** — outlets define service zones; a request is matched to a zone.
- **Courier assignment** — a specific courier is assigned to a specific job.
- **Internal courier** — staff couriers using Ops Android.
- **External ojek lokal** — third-party riders who are not staff and do not get full application access.
- **Simple route ordering** — jobs are ordered sensibly for the courier.
- **Route suggestion with no false optimization claims** — the product suggests an order; it must never
  present a suggestion as a mathematically optimal route unless it genuinely computes one.
- **Proof of pickup** and **proof of delivery**.
- Proof mechanisms: **OTP, photo, signature, recipient name**.
- **Cash collection** at the door.
- **Courier cash reconciliation** at the end of a shift or route.
- **Secure guest link for external ojek** — an external rider receives a limited, expiring link that
  allows exactly the job at hand and nothing else.

### 10.2 Rules

1. A delivery is never marked complete without proof appropriate to the tenant's configured policy.
2. Cash collected by a courier is a financial transaction and is subject to §16 in full: it is recorded,
   it is idempotent, it is reconciled against expected amounts, and it is never silently adjusted.
3. An external ojek link grants access to **one job**, expires, and never exposes the customer's full
   address history, other orders, or any other tenant data.
4. Route suggestions are labelled as suggestions. Honest language is a hard requirement (§3.1).
5. A failed delivery is a first-class outcome with a recorded reason, not an error state — the laundry
   returns to the outlet and the order returns to a defined status.
6. Time windows are commitments shown to the customer; missing them is measurable (§29).

### 10.3 Relationship to unclaimed laundry

Delivery is the strongest remedy for unclaimed laundry: laundry that a customer will not collect can
often be delivered. The escalation ladder in §11 may propose a delivery as a recovery action.

---

## 11. Unclaimed laundry

*Cucian menumpuk* — finished laundry that the customer never collects — destroys shelf space and traps
cash. Aish Laundry App treats its recovery as a core product capability (DEC-0008).

### 11.1 Aging rule

**Aging starts when an order FIRST reaches status `READY_FOR_PICKUP`.**

"First" is literal: if an order returns to production for rework and reaches `READY_FOR_PICKUP` again,
the aging clock is **not** reset. The customer's laundry has been finished since the first time, and the
business has been carrying it since then.

### 11.2 Reminder ladder

| Age | Action |
| --- | --- |
| H+1 | Friendly reminder to the customer |
| H+3 | Second reminder |
| H+7 | Priority reminder **and** a follow-up task assigned to a staff member |
| H+14 | Escalation to the outlet manager or the owner |

Rules:

- Reminders are transactional messages, but they still respect opt-out and quiet hours (§14).
- Reminders are de-duplicated: a customer never receives the same reminder twice because of a retry.
- The H+7 follow-up task is a real, assignable, closable task with an owner, not a notification.
- The H+14 escalation surfaces in the manager and owner dashboards.

### 11.3 Dashboard minimum

The unclaimed laundry dashboard must show at least:

- order count;
- customer count;
- held invoices;
- unpaid balance;
- order age;
- outlet;
- last reminder;
- follow-up officer;
- reason not collected.

### 11.4 Absolute prohibition

**The product never automatically discards, sells, or transfers ownership of laundry.**

No configuration, no plan, no escalation level, and no automation may dispose of a customer's property.
Disposal is a legal and ethical matter between a business and its customer; the software's job ends at
surfacing the problem, reminding the customer, escalating to a human, and recording the reason it was
never collected.

---

## 12. Owner dashboard and portfolio

### 12.1 Purpose

An owner running several brands and outlets needs one consolidated, trustworthy view: what was earned,
what is stuck, what money is at risk, and which outlet needs attention today.

### 12.2 Scope of consolidation

- The portfolio consolidates **within a single tenant** across that tenant's brands and outlets.
- An owner who owns **multiple tenants** switches tenants (§4.4). Consolidation across tenants is not
  performed by weakening isolation; if a cross-tenant view is ever built, it is built as an explicitly
  consented, separately authorised construct with its own decision record.
- **Hard rule 13 applies without exception: the portfolio dashboard must not weaken tenant isolation.**

### 12.3 Minimum content

| Area | Content |
| --- | --- |
| Revenue | Revenue by day, outlet, brand, and service type |
| Orders | Orders taken, in production, ready, delivered, cancelled |
| Cash | Cash expected vs actual per shift; courier cash outstanding |
| Receivables | Unpaid balance; held invoices |
| Unclaimed | Aging buckets, oldest orders, escalations pending (§11) |
| Operations | Time-window adherence, rework rate, capacity pressure |
| Subscription | Plan, usage against fair-use limits, trial or renewal state |

### 12.4 Trust rules

1. Every number is derived from the same system of record that operations use; there is no separate,
   divergent reporting truth.
2. Any figure that is an estimate is labelled an estimate.
3. A figure that cannot be computed for a period is shown as unavailable, never as zero.
4. Drill-down from any aggregate to its underlying records is possible for a user with permission,
   within the same tenant.

---

## 13. Offline-first

Offline capability applies primarily to **Aish Laundry Ops Android** and is implemented in later Steps.
Shop-floor and on-the-road connectivity in Indonesia is unreliable; the business cannot stop.

### 13.1 Canonical rules

1. **`client_reference` on every important operation.** The client generates a stable identifier before
   sending, and the server uses it to deduplicate.
2. **Persistent queue.** Operations survive application restart and device reboot.
3. **Exponential backoff retry.** Retries are spaced and bounded, not a tight loop.
4. **The financial queue is never casually deleted.** A queued payment is money; clearing it is an
   explicit, permissioned, audited action — never a "clear cache" side effect.
5. **Payment conflicts are never silently overwritten.** A conflict surfaces to a human with both values.
6. **The server is the final source of truth.** When client and server disagree about business state, the
   server wins and the client reconciles.
7. **Local data is separated per tenant and per user.** Switching tenant or user never exposes the
   previous context's data.
8. **Sensitive local data is encrypted**, using Android secure storage for keys and credentials.
9. **A duplicate order or duplicate payment caused by a retry is unacceptable.** This is a financial
   integrity failure (§16) and therefore an automatic NO-GO.

### 13.2 Visible sync state

The user must always be able to tell whether they are working offline and whether their work has
synchronised. Offline and sync state are visible (§18). A kasir must never believe a payment was recorded
when it is still sitting in a queue.

### 13.3 What is not offline

Not everything degrades gracefully, and the product is honest about it:

- Payment gateway confirmation requires the network; an offline device may record an intent, never a
  confirmed gateway payment.
- OTP verification requires the network.
- Public tracking is server-rendered and requires the network by nature.

---

## 14. Notifications and WhatsApp

WhatsApp is the channel Indonesian customers actually read. It is therefore the primary notification
channel, and it is treated with corresponding care.

### 14.1 Canonical rules

1. **Provider abstraction.** The product integrates against an internal notification interface, not
   directly against one vendor's SDK. Providers are replaceable.
2. **The official provider is the automated path.** Automated sending goes through an official WhatsApp
   Business provider.
3. **Manual deep-link is the fallback.** When automated sending is unavailable, staff get a prepared
   deep-link to send the message themselves. The fallback is explicit and visible, never disguised as
   automation.
4. **Transactional and marketing messages are separated** — different templates, different consent,
   different opt-out handling.
5. **Opt-out is honoured.** A customer who opts out of marketing stops receiving marketing.
6. **Quiet hours default to 20.00–08.00 outlet local time.** Non-urgent messages are held until quiet
   hours end.
7. **Message deduplication is required.** A retry, a queue replay, or a double-trigger never produces a
   second identical message to the customer.
8. **WhatsApp failure never cancels an order.** Notification is a side effect; the order lifecycle is
   independent of it.
9. **Provider costs are transparent** (§21, DEC-0011).
10. **Never promise fake "unlimited WhatsApp".** Message volume has a real per-message cost and the
    product says so.

### 14.2 Notification catalogue (canonical intent)

| Event | Class | Channel |
| --- | --- | --- |
| Order received | Transactional | WhatsApp + tracking link |
| Order in production | Transactional | WhatsApp (optional per tenant) |
| Order ready for pickup | Transactional | WhatsApp + tracking link |
| Pickup scheduled / courier assigned | Transactional | WhatsApp |
| Delivery completed | Transactional | WhatsApp |
| Payment received | Transactional | WhatsApp |
| H+1 / H+3 / H+7 unclaimed reminder | Transactional | WhatsApp |
| H+14 escalation | Internal | In-product to manager/owner |
| Promotions, loyalty campaigns | Marketing | WhatsApp, consent required |

### 14.3 Content rules

- Messages are in Bahasa Indonesia.
- Messages never contain OTP values together with a tracking link in a way that enables one-message
  account takeover.
- Messages never contain a full address.
- Message logs never contain credentials or token plaintext (§15).

---

## 15. Security

Canonical security rules for Aish Laundry App. These are requirements on the eventual implementation;
Step 0 implements none of them and claims none of them.

### 15.1 Authorisation

1. **Least privilege** everywhere: users, service accounts, storage credentials, database roles.
2. **Server-side authorisation** on every request. Client-side checks are user experience, never security.
3. **Tenant-scoped access** on every business query (§4).
4. Membership and permission are verified server-side; a client-supplied tenant identifier is never proof.

### 15.2 Credentials and tokens

5. **Secure password hashing** using a modern memory-hard algorithm with per-password salt.
6. **Secure token storage** on the server: tokens stored hashed, never in plaintext.
7. **Android secure storage** for credentials and tokens on device.
8. **No secrets in the repository** — see [`../SECURITY.md`](../SECURITY.md).

### 15.3 Files and uploads

9. **Uploads are validated**: type, size, and content, not merely file extension.
10. **Private files are served via signed URL** with a short expiry. Object storage is never public.
11. Laundry photographs are private data (§17).

### 15.4 Abuse resistance

12. **Rate limiting** on authentication, OTP issuance, tracking-token lookup, and all public endpoints.
13. **Brute-force protection** with progressive delay and lockout on repeated failure.
14. **Session revocation** — a user or an administrator can end a session immediately.
15. **Device revocation** — a lost device can be cut off without changing the password.

### 15.5 Support access

16. **Support impersonation is time-bound and audited.** It has a start, an end, a reason, an actor, and
    an immutable record. **Platform support has no silent tenant access.**

### 15.6 Data protection

17. **Backups are encrypted**, at rest and in transit, and restore is tested (Step 13).
18. **Logs never contain password, OTP, token, or credential values.** Redaction happens at the logging
    boundary, not by hoping nobody logs the wrong object.
19. **Phone numbers and addresses are masked per context.** The tracking portal never shows a full
    address.

### 15.7 Hard gate

**Cross-tenant data exposure is an automatic NO-GO** (DEC-0012). It blocks merge, blocks release, blocks
a GO tag, and is not subject to schedule negotiation.

Full policy: [`governance/TENANT_ISOLATION_POLICY.md`](governance/TENANT_ISOLATION_POLICY.md).

### 15.8 Public repository authoring constraints

This repository is **PUBLIC**. That is an accepted deviation from a canonical desired **PRIVATE**,
recorded in AMENDMENT-0001 and locked by
[DEC-0016](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md). The deviation is not a
judgement that public is adequate; it is the price paid for platform-enforced branch protection on a free
plan.

Two consequences are canonical.

**First, every file in this repository is world-readable and permanently so.** Deletion is not
remediation: anything committed must be assumed mirrored, cached, and indexed. A secret is compromised at
the moment it is pushed, and rotation — not removal — is the first response ([`../SECURITY.md`](../SECURITY.md)).

**Second, the following must never be committed**, in any file type, including documentation, examples,
test fixtures, and evidence packs:

20. Customer data of any kind; real customer phone numbers, names, or addresses; photographs of customer
    laundry or premises.
21. Credentials, tokens, OTP values, private keys, `.env` files, or production configuration.
22. Database dumps, backups, sensitive server addresses, or internal network topology.
23. Internal incident data containing personal data, raw authentication output, third-party provider
    secrets, or billing credentials.

Stated positively:

- **Evidence packs are sanitised before commit**, and they state that sanitisation occurred.
- **Every example datum is fictional** and recognisably so. An example is invented, never copied from
  reality.
- **Only `PUBLIC` and sanitised `INTERNAL` material is committed.** The `CONFIDENTIAL`, `RESTRICTED`, and
  `SECRET` classes may be described and modelled, but never instantiated with real values.
- **This repository is never described as private** (§1.3, §33.4).

**Governance operates in single-maintainer mode.** Independent human approval is **ABSENT**. The
compensating controls are the active ruleset, exact-SHA CI, deterministic validators, and recorded
internal re-verification. That is stated plainly here so that no report can present internal
re-verification as independent peer review.

---

## 16. Financial integrity

Money is the most consequential data in the product after identity. These rules are canonical.

### 16.1 Representation

1. **Money is stored as integer Rupiah.**
2. **Floating point is forbidden for financial transactions.** No `float`, no `double`, no
   binary-floating-point arithmetic anywhere in a money path — storage, computation, transport, or
   display formatting that round-trips through a float.

### 16.2 Payment correctness

3. **Payments are idempotent.** The same logical payment, submitted twice — by a retry, a queue replay,
   a double tap, or a network timeout — produces exactly one payment.
4. **Gateway callbacks are verified server-side**: signature verification, amount verification, and
   status verification against the gateway, not against the callback payload alone.
5. **An order is never marked paid on a client claim.** A client may report; only the server may decide.

### 16.3 Corrections and audit

6. **Refund and void require an explicit permission and a recorded reason.**
7. **Financial transactions are never deleted through ordinary UI.**
8. **Corrections happen via reversal or adjustment entries**, preserving the original record and the full
   audit trail. The history of what happened is never rewritten.

### 16.4 Pricing history

9. **Historical order prices are immune to price-list changes.** An order captures the price that applied
   when it was created. Editing the price list changes future orders only. Reprinting an old nota shows
   the old price.

### 16.5 Cash handling

10. **Shift closing compares expected cash against actual cash**, records the variance, and requires a
    reason for a variance beyond a configured threshold.
11. **Courier cash is reconciled** — cash collected on delivery is tracked from collection to hand-over,
    with the courier accountable for the difference.

### 16.6 Hard gate

**Any financial integrity failure is an automatic NO-GO** (DEC-0012).

Examples that trigger the gate: a duplicate payment created by a retry; a float appearing in a money
calculation; an order marked paid without server verification; a deletable financial transaction; a
historical price mutating after a price-list edit; a courier cash discrepancy that the system cannot
account for.

Full policy: [`governance/FINANCIAL_INTEGRITY_POLICY.md`](governance/FINANCIAL_INTEGRITY_POLICY.md).

---

## 17. Privacy

### 17.1 Personal data held

Aish Laundry App holds customer names, phone numbers, addresses, order histories, payment records,
delivery proofs including photographs and signatures, and photographs of customers' garments. All of it
is personal data and all of it belongs to the customer and to the tenant serving them.

### 17.2 Canonical privacy rules

1. **Phone numbers and addresses are masked per context.** The masking level depends on who is looking
   and where — a kasir preparing a delivery sees more than a public portal visitor.
2. **The tracking portal never shows a full address.**
3. **Laundry photographs are private data.** They are stored privately, served only through signed URLs,
   never shown on the public portal, and never used for marketing.
4. **Tenant data is not used to train AI models without explicit consent.** Consent is specific,
   informed, recorded, and revocable. Absence of a refusal is not consent.
5. **Platform support has no silent tenant access.** Impersonation is explicit, time-bound, and audited
   (§15.5).
6. **Logs never contain passwords, OTPs, tokens, or credentials** (§15.6).
7. **Encrypted backups** protect data at rest outside the primary database (§15.6).

### 17.3 Data lifecycle

- Data is retained while the tenant relationship exists and for the period required by the tenant's legal
  and tax obligations.
- **Tenant data remains exportable per policy when a subscription lapses** (§21). A lapsed subscription
  restricts access to features; it does not hold a tenant's business records hostage.
- Deletion requests are handled at the tenant boundary with a documented process; financial records
  subject to retention obligations are handled per those obligations and the customer is told so.

### 17.4 Consent

- Marketing messaging requires consent, separately from transactional messaging (§14).
- Consent state is recorded per customer per tenant, with a timestamp and a source.
- Opt-out takes effect immediately and is never reset by a data import.

---

## 18. UX and design foundation

### 18.1 Visual language

The visual identity is: **white; soft blue; dark blue; restrained gold accent**. The tone is
**professional, light, not futuristic, and relevant to Indonesian UMKM**.

Consequences:

- White is the dominant surface. Interfaces feel clean and readable in a brightly lit shop.
- Soft blue carries interactive and informational emphasis; dark blue carries structure, headers, and
  weight.
- Gold is an **accent** — used sparingly for value, achievement, or premium moments. Gold is never a
  large surface and never the primary action colour.
- No neon, no glassmorphism, no heavy gradients, no science-fiction styling. The product should look like
  a trustworthy business tool that an owner is comfortable showing to a customer.

### 18.2 Canonical UX rules

1. **Shortest possible primary actions.** Every screen has one obvious next action reachable with minimal
   taps. Taking an order is the shortest path in the product.
2. **Status is never conveyed by colour alone.** Every status carries a label and, where useful, an icon
   and a shape. This is an accessibility requirement and a shop-floor-lighting requirement.
3. **Destructive actions are separated** from routine actions — physically separated in the layout,
   visually distinct, and confirmed. A refund is never adjacent to a print button.
4. **Errors explain recovery steps.** An error message names what failed and what the user should do
   next. "Terjadi kesalahan" alone is a defect.
5. **Offline and sync state are visible** (§13.2).
6. **Accessibility and device font scaling are supported.** Layouts survive large system font settings;
   contrast meets accessible ratios; touch targets are adequate for a user wearing gloves or standing at
   a counter.
7. **Couriers get a simple interface** — large targets, few decisions, one job at a time, usable one-handed
   and in sunlight.
8. **Avoid heavy animation.** Motion is functional — confirming a state change, showing a transition —
   never decorative. Low-end Android devices are the baseline, not the exception.

### 18.3 Copy rules

- All user-facing copy is in Bahasa Indonesia (§1.6).
- Copy is plain and respectful; it does not use jargon that a shop staff member would not use.
- Copy never overstates: no "optimal route", no "instant", no "unlimited" where those are untrue (§3.1).

### 18.4 Foundation delivery

The design system is delivered in **Step 2 — Design System and UX Foundation** as a shared package
(`packages/design_system`). No screen is built before its foundation exists.

### 18.5 The Step 2 foundation

Step 2 delivers that foundation as **specification only**. `packages/design_system` remains a
placeholder containing a `README` or a `.gitkeep`; the Flutter workspace is `ABSENT` and no runtime
consumes any of it. The canonical artefacts are listed in §35.

Four foundation decisions are locked in Step 2, each with a decision record:

- **Design tokens are layered** — primitive, semantic, and component alias. A component names a meaning,
  never a hex value (DEC-0018).
- **The light theme is the canonical MVP theme.** Dark mode is `PLANNED` and `NOT IMPLEMENTED`; no
  document may claim it is available (DEC-0019).
- **Typography is system-first and no font binary is committed** to this PUBLIC repository. Tabular
  figures are mandatory wherever money or tabular numerics are stacked in a column (DEC-0020).
- **The accessibility target is WCAG 2.2 AA-aligned by design and explicitly not runtime-tested**
  (DEC-0021). The mandated wording is used verbatim wherever the target is stated.

Two further decisions fix how the foundation is expressed: the canonical UX state taxonomy and
role-adaptive navigation (DEC-0022), and low-fidelity SVG wireframes with no final-logo fabrication
(DEC-0023).

---

## 19. Performance

### 19.1 Target environment

The baseline device is a **low-end to mid-range Android phone** on a **congested mobile network**. This
is not a degraded case to be handled later; it is the normal case.

### 19.2 Canonical performance principles

1. **The public tracking portal is the most performance-critical surface.** It is opened by customers on
   unknown devices over unknown networks, often once. It must load fast on a cold cache. This is why the
   portal is permitted a lighter stack than Flutter (§5.4).
2. **The kasir order-intake path is the most latency-critical internal surface.** It is used dozens of
   times per hour under time pressure and must remain responsive, including offline (§13).
3. **Perceived performance is designed, not hoped for.** Optimistic local state, skeletons over spinners,
   and immediate acknowledgement of user intent.
4. **The list is the product.** Order lists, production queues, and courier job lists are paginated,
   indexed, and bounded. No screen loads an unbounded result set.
5. **Every business query is tenant-scoped and index-supported.** A tenant-scoped query without a
   supporting index is a defect, not a tuning opportunity.
6. **Redis absorbs read pressure and rate limiting**, never business truth (§6.5).
7. **Images are the heaviest payload.** Laundry photographs and delivery proofs are compressed on device
   before upload, served resized, and never loaded at full resolution in a list.
8. **Background work belongs in queues.** Notification sending, report generation, and reminder
   evaluation never block a user request.

### 19.3 Performance budgets

Concrete numeric budgets — page weight, time to interactive, API percentile latency, queue lag — are set
in **Step 13 — Security, Performance, Backup, and Recovery**, measured against real devices, and recorded
with evidence. This document deliberately does not invent numbers that have not been measured (§3.1).

---

## 20. Observability

### 20.1 Purpose

Observability exists so that the team can answer three questions honestly: is the system healthy, what
exactly happened to this specific order, and where is the money.

### 20.2 Canonical rules

1. **Structured logging.** Logs are structured records with a stable schema, not free-text strings.
2. **Correlation.** Every request carries a correlation identifier that flows through queues and
   background jobs, so a single customer interaction can be traced end to end.
3. **Tenant context in telemetry** is recorded as an identifier for filtering — **never as personal data**.
   Telemetry is not a bypass around tenant isolation or privacy.
4. **Logs never contain passwords, OTPs, tokens, or credentials** (§15.6). Redaction is enforced at the
   logging boundary.
5. **Financial operations are audited separately** from application logs. The financial audit trail is
   append-only and is not subject to log rotation (§16.3).
6. **Security events are audited**: authentication failures, permission denials, session and device
   revocation, support impersonation start and end, tracking-token issuance and revocation.
7. **Alert on symptoms that customers feel** — failed payments, undelivered notifications, queue backlog,
   error-rate spikes — not on every internal fluctuation.
8. **An alert that nobody acts on is deleted.** Alert fatigue is a security risk.

### 20.3 Minimum signals

| Signal | Why |
| --- | --- |
| API error rate and latency percentiles | Health of the contract all clients depend on |
| Queue depth and job failure rate | Notifications and reminders silently stopping |
| Notification delivery outcomes per provider | WhatsApp failures and provider cost (§14, §21) |
| Offline sync backlog and conflict count | Ops app correctness (§13) |
| Payment success, retry, and duplicate-suppression counts | Financial integrity (§16) |
| Authentication failure and lockout rates | Abuse and brute force (§15.4) |
| Unclaimed laundry aging distribution | Business health (§11) |

### 20.4 Delivery

Observability tooling is delivered in **Step 13**. Nothing in this section is implemented at the Step 0
baseline.

---

## 21. Pricing

This is a **locked commercial decision** (DEC-0009). Figures are reproduced exactly and may only change
through a new decision record.

### 21.1 Trial

**Trial: 14 hari gratis**

### 21.2 Monthly plans

| Plan | Price | Limits |
| --- | --- | --- |
| Starter | **Rp79.000/bulan** | 1 outlet, 5 staff, hingga 1.000 order/bulan fair-use |
| Growth | **Rp199.000/bulan** | hingga 3 outlet, 20 staff, hingga 5.000 order/bulan |
| Scale | **Rp399.000/bulan** | hingga 10 outlet, 75 staff, hingga 20.000 order/bulan |
| Enterprise | **mulai Rp999.000/bulan** | negotiated |

### 21.3 Annual plans

| Plan | Annual price |
| --- | --- |
| Starter | **Rp790.000/tahun** |
| Growth | **Rp1.990.000/tahun** |
| Scale | **Rp3.990.000/tahun** |

### 21.4 Pricing guardrails

These guardrails are canonical and constrain every future commercial decision:

1. **No lifetime cloud plan** (DEC-0010). A cloud service has a recurring cost; a one-off payment for
   perpetual service is a promise that cannot be kept honestly.
2. **No per-nota fee on normal plans.** Charging per transaction punishes the customer for succeeding.
3. **Transparent provider costs** (DEC-0011). Third-party messaging costs are shown, not buried.
4. **The security baseline is not locked behind expensive plans.**
5. **Tenant isolation is not an add-on.** It is the architecture, on every plan.
6. **Backup is not a premium security add-on.**
7. **Pricing changes require a decision record.**
8. **WhatsApp provider fees are billed separately** and shown transparently.
9. **Tenant data remains exportable per policy when a subscription lapses** (§17.3).

### 21.5 Fair use

Order limits are stated as fair-use ceilings. Exceeding a ceiling triggers a conversation and a plan
recommendation — it does not silently degrade the service, does not delete data, and does not stop a
laundry from operating mid-shift.

### 21.6 Public visibility notice

Because the repository is PUBLIC (AMENDMENT-0001 in [`ASSUMPTIONS.md`](ASSUMPTIONS.md), locked by
[DEC-0016](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)), the pricing above and
the commercial reasoning behind it are publicly visible. This is an accepted consequence of the owner's
deliberate decision to enable branch protection.

The canonical desired visibility remains **PRIVATE**. PUBLIC is an accepted deviation, re-examinable
under the upgrade path in DEC-0016, not a settled preference. Because pricing is publicly readable, every
figure in this section must be accurate at all times; a stale price on a public repository is a
commercial risk, not a typo (§15.8).

---

## 22. MVP

**The MVP focuses on laundry operations** (DEC-0015).

### 22.1 MVP definition

The MVP is the smallest product that lets a single laundry tenant run a real working day end to end and
lets its customers track their laundry without installing anything.

### 22.2 In the MVP

| Capability | Canonical Step |
| --- | --- |
| Authentication with phone + OTP | Step 3 |
| Tenancy, brands, outlets, memberships, tenant switcher | Step 3 |
| RBAC with server-side authorisation | Step 3 |
| Customers, services, price lists, outlet master data | Step 4 |
| POS order intake, nota, payment, refund/void with audit | Step 5 |
| Production stages, status lifecycle including `READY_FOR_PICKUP` | Step 6 |
| Public tracking portal with secure tokens | Step 7 |
| WhatsApp notification with provider abstraction and fallback | Step 7 |
| Pickup and delivery with proof and courier cash | Step 8 |
| Unclaimed laundry H+1/H+3/H+7/H+14 and its dashboard | Step 9 |
| Shift closing, reconciliation, core reports, owner portfolio | Step 10 |

### 22.3 After the MVP

| Capability | Canonical Step |
| --- | --- |
| Customer Android application, loyalty, feedback, invoices | Step 11 |
| Subscription, plan limits, platform administration | Step 12 |
| Security hardening, performance budgets, backup and recovery | Step 13 |
| Pilot and commercial launch | Step 14 |

### 22.4 MVP rationale

Operations first, because a laundry that cannot take an order does not care about loyalty points. The
customer application comes after the portal because the portal already solves the customer's real
problem, and the app must not become a prerequisite for tracking (DEC-0014).

### 22.5 MVP quality bar

The MVP is small in scope but not lax in quality. Tenant isolation (§15) and financial integrity (§16)
are hard gates from Step 3 onward. There is no "we will secure it after the pilot".

---

## 23. Non-goals

Explicitly **not** part of Aish Laundry App, now or as an assumed future:

1. **A general-purpose ERP.** The product serves laundry operations, not manufacturing, not general
   inventory, not HR.
2. **A general-purpose POS for other retail verticals.** The domain model is laundry-specific by design.
3. **An accounting system.** The product produces financial records and reports and exports; it is not a
   general ledger and does not file taxes.
4. **A payroll system.** Shift and attendance data may be recorded for operations; payroll is out of
   scope.
5. **A marketplace.** The product does not aggregate laundries for consumers or take a cut of orders.
6. **A courier network.** The product coordinates a tenant's own couriers and external ojek lokal; it
   does not supply riders.
7. **A route optimisation engine.** Route *suggestion* and *simple ordering* are in scope; claiming
   mathematical optimisation is explicitly forbidden (§10.2).
8. **An AI decision-maker for money.** No automated system decides refunds, writes off balances, or
   adjusts cash.
9. **Automatic disposal of unclaimed laundry.** Never (§11.4).
10. **Offline payment gateway confirmation.** Physically impossible; the product does not pretend
    otherwise (§13.3).
11. **iOS applications** at this stage. Android and web are the canonical clients; iOS would require a
    decision record.
12. **On-premise deployment** at this stage. The product is a cloud SaaS.
13. **A lifetime plan** (DEC-0010).
14. **Multi-tenant data blending for cross-tenant analytics.** Not without explicit consent and its own
    decision record (§12.2).

---

## 24. Roadmap

The roadmap is **locked**. Step numbers are never reused or swapped without a decision record.

| Step | Title | Status |
| --- | --- | --- |
| Step 0 | Master Source and Governance | GO |
| Step 1 | Product Requirement and Domain Model | GO |
| Step 2 | Design System and UX Foundation | GO WITH ACCEPTED DEVIATION |
| Step 3 | Runtime, Authentication, Multi-Tenancy, and RBAC | GO WITH ACCEPTED DEVIATION |
| Step 4 | Laundry Master Data | GO |
| Step 5 | POS, Order, and Payment Foundation | IN PROGRESS |
| Step 6 | Production Operations | PLANNED |
| Step 7 | Customer Tracking and WhatsApp | PLANNED |
| Step 8 | Pickup and Delivery Operations | PLANNED |
| Step 9 | Unclaimed Laundry and Cashflow Recovery | PLANNED |
| Step 10 | Finance, Reports, and Owner Portfolio | PLANNED |
| Step 11 | Customer Android Experience | PLANNED |
| Step 12 | Subscription and Platform Administration | PLANNED |
| Step 13 | Security, Performance, Backup, and Recovery | PLANNED |
| Step 14 | Pilot and Commercial Launch | PLANNED |

Scope summaries for each Step: [`ROADMAP.md`](ROADMAP.md).
Current machine-validated status: [`STATUS.md`](STATUS.md).

Step 0 reached **GO** on 19 July 2026, conferred by the repository owner against exact-SHA evidence
(DEC-0013). That GO carries one recorded deviation: repository visibility is PUBLIC where the canonical
requirement was PRIVATE (§15.8, DEC-0016). Step 0 GO therefore means every technical and governance gate
passed **with the visibility requirement deliberately amended and documented** — it does not mean the
original PRIVATE requirement was met. The honesty rule in §1.3 item 4 governs what may be written *during*
a foundation pull request and is not retroactively violated by an owner-conferred GO recorded after
merge.

### 24.1 Step 0 scope guard

Step 0 is **forbidden** to create any of the following:

`flutter create`, `dart create`, `laravel new`, `composer create-project`, `npm create`, `pubspec.yaml`,
`composer.json`, `artisan`, database schema, migrations, authentication, tenant implementation, REST API
runtime, Android UI, Flutter Web UI, Docker application runtime, any deployment, and any payment,
WhatsApp, tracking, pickup-delivery, or H+1/H+3/H+7 implementation.

Runtime folders contain only a `README.md` or a `.gitkeep`. **Never claim an empty folder is an
implemented feature.**

---

## 25. Definition of Done

A Step is Done only when every applicable item below is true and evidenced.

### 25.1 General Definition of Done

1. Declared scope for the Step is delivered in full; nothing declared was quietly dropped.
2. No work belonging to a later Step was performed.
3. All canonical documents affected by the Step are updated — Master Source, `STATUS.md`, `CHANGELOG.md`,
   `ROADMAP.md`, and any decision record required by a new or changed decision.
4. Status statements use only the vocabulary in [`governance/STATUS_MODEL.md`](governance/STATUS_MODEL.md).
5. The Step's validator script passes, and its unedited output is in the evidence pack.
6. All internal markdown links resolve to files that exist.
7. No secrets, credentials, or personal data appear anywhere in the diff or the evidence pack.
8. Tenant isolation gate: no cross-tenant exposure. Verified, not assumed (§15.7).
9. Financial integrity gate: no violation of §16. Verified, not assumed.
10. Tests required by the Step exist, run, and pass — with real output, not a claim (§28).
11. An evidence pack exists under `evidence/step-NN/`, bound to the **exact commit SHA** under review,
    and sanitised per [`governance/EVIDENCE_POLICY.md`](governance/EVIDENCE_POLICY.md).
12. The pull request is reviewed and approved by someone other than the author for a Step-closing change.

    **Standing deviation — independent approval is `ABSENT`.** This project operates under
    single-maintainer governance: there is one maintainer, who is also the owner, so the second person
    this item presupposes does not exist. The item is **not** deleted — it states the correct
    requirement and becomes binding the moment a second maintainer exists — but it **cannot currently
    be satisfied**, and it is recorded as an accepted deviation rather than waived silently or
    re-reported as a fresh failure at every Step. See
    [DEC-0017](decisions/DEC-0017-single-maintainer-approval-standing-deviation.md) for the
    compensating controls (active ruleset, exact-SHA CI, deterministic validators, adversarial
    validator testing, recorded internal re-verification) and for the honest limitation: those controls
    are **not equivalent** to independent review, and a defect that both the maintainer and the
    validators miss is not caught.

    **Internal re-verification is never described as review or approval** (§1.3). The deviation excuses
    this item only; it lowers no other gate, and `GO` remains the owner's to confer.
13. Every claim in the pull request description is true.

### 25.2 Step 0 Definition of Done

Step 0 additionally requires:

1. `docs/MASTER_SOURCE.md` existed at version 1.0.0 with baseline date 19 July 2026 and covered all
   thirty-three canonical sections. (The document has since moved to 1.0.1 under §1.2; the Step 0
   Definition of Done is assessed against the 1.0.0 state that Step 0 delivered.)
2. All fifteen decision records DEC-0001 … DEC-0015 exist, each with status ACCEPTED, date 19 July 2026,
   and every required heading.
3. Governance policies exist: required files, status model, evidence policy, tenant isolation policy,
   financial integrity policy.
4. Root governance files exist: `README.md`, `CONTRIBUTING.md`, `SECURITY.md`, `CLAUDE.md`.
5. Rule files `00-canonical-source.md` … `15-current-product-status.md` exist under `.claude/rules/`.
6. `scripts/verify-step-00.sh` exists and passes.
7. `docs/STATUS.md` carries exactly the canonical statuses and **does not** state `GO` for Step 0.
8. **No runtime artefact of any kind exists** — no `pubspec.yaml`, no `composer.json`, no schema, no
   migration, no deployment.
9. Every runtime placeholder folder contains a README stating `Status: NOT IMPLEMENTED` and
   `Runtime: ABSENT`.
10. AMENDMENT-0001 records the PUBLIC repository visibility honestly; nowhere claims the repository is
    private.

Full checklist: [`DEFINITION_OF_DONE.md`](DEFINITION_OF_DONE.md).

---

## 26. Git and CI

### 26.1 Branch model

- `main` is protected; direct pushes are forbidden; all change arrives by pull request.
- Step branches are named `feature/step-NN-<slug>`.
- Branch, commit, and pull-request conventions: [`../CONTRIBUTING.md`](../CONTRIBUTING.md).

### 26.2 Exact-SHA policy

Evidence and CI results are bound to an **exact commit SHA** (DEC-0013). A green check on an earlier
commit is not evidence for a later commit. The SHA that is validated must be the SHA that is merged and,
where applicable, the SHA that is tagged.

### 26.3 Tags and releases

- Tags are **annotated and immutable**. A tag is never moved, never deleted, never re-pointed.
- GO tag naming convention: `aish-laundry-step-NN-<slug>-vX.Y.Z-go`.
- A GO tag asserts that the Step met its Definition of Done with evidence at that exact SHA.

### 26.4 Rollback

**Rollback is by revert only.** A revert commit is created through a pull request. History on `main` is
never rewritten.

### 26.5 Forbidden destructive operations

Force push to `main`, history rewriting on `main`, tag deletion, tag movement, branch deletion of a
protected branch, deleting or amending merged history, and disabling required checks to force a merge.

Full policy: [`GIT_AND_RELEASE_POLICY.md`](GIT_AND_RELEASE_POLICY.md).

### 26.6 CI at the Step 0 baseline

| CI concern | Status |
| --- | --- |
| Governance CI (documentation and validator) | Present from Step 0 |
| Application CI (build, unit test, integration test) | **NOT APPLICABLE** — there is no application to build |

Application CI becomes applicable at **Step 3**, when the first runtime exists. Claiming application CI
before then would be a false claim (§1.3).

---

## 27. AI development rules

Aish Laundry App is developed with substantial AI assistance. AI agents operate under the same honesty
standard as humans, plus these additional constraints.

### 27.1 Autonomy

1. An agent executes the declared scope of the current Step autonomously, without asking for permission
   at every routine step.
2. An agent **stops and reports** when it encounters: a hard-gate risk (§15.7, §16.6), a required
   destructive operation, a conflict with the Master Source, a missing credential, or a scope boundary.
3. An agent never expands its own scope to "finish something useful".

### 27.2 Truth

4. **No false claims.** An agent never reports a test that did not run, a file that was not created, a
   check that did not pass, or a deployment that did not happen.
5. An agent reports failure plainly. A failed Step reported honestly is a success of the process.
6. An agent never edits a validator to make it pass.
7. An agent never writes `GO` for Step 0.

### 27.3 Evidence

8. Every completion claim is backed by an evidence artefact bound to an exact commit SHA (DEC-0013).
9. Command output pasted into a report is real, unedited output.
10. Evidence is sanitised before commit (§15, [`governance/EVIDENCE_POLICY.md`](governance/EVIDENCE_POLICY.md)).

### 27.4 Boundaries

11. An agent never commits a secret, never prints a secret, and never stores a credential in the
    repository.
12. An agent never performs a forbidden destructive git operation (§26.5).
13. An agent never modifies files owned by another agent or another Step without being asked.
14. An agent never invents a product decision. New decisions come from the owner and are recorded as
    decision records.

Full policy: [`AI_EXECUTION_POLICY.md`](AI_EXECUTION_POLICY.md).
Tooling rules: [`TOOLING_POLICY.md`](TOOLING_POLICY.md).

---

## 28. Testing

### 28.1 Current state

**No tests exist at the Step 0 baseline**, because no application code exists. Application CI is
**NOT APPLICABLE**. The only executable verification in Step 0 is the governance validator
`scripts/verify-step-00.sh`.

### 28.2 Canonical testing requirements from Step 3 onward

| Layer | Requirement |
| --- | --- |
| Unit | Business rules — pricing, aging, status transitions, money arithmetic — covered by fast, deterministic tests. |
| Integration | API endpoints tested against a real PostgreSQL and a real Redis, not mocks. |
| Tenant isolation | A dedicated, always-run suite that attempts cross-tenant access and asserts it fails. |
| Financial integrity | A dedicated suite covering idempotency, retry, refund, void, reversal, and historical price immutability. |
| Offline sync | Queue persistence, retry, deduplication by `client_reference`, and conflict surfacing. |
| Contract | API responses validated against the published contract so clients cannot be silently broken. |
| End-to-end | The critical customer journey: order → production → ready → tracking link → collection or delivery → payment. |

### 28.3 Mandatory suites

Two suites are mandatory from the Step that introduces the capability, and a failure in either is an
automatic NO-GO:

1. **Tenant isolation tests** — every business endpoint is exercised with a token from tenant A against
   data belonging to tenant B and must be denied.
2. **Financial integrity tests** — duplicate submission, retry storm, and callback replay must each
   produce exactly one payment.

### 28.4 Testing rules

- A test that has never failed has never been verified; negative cases are written deliberately.
- Money is never asserted with floating-point tolerance (§16.1).
- Test data never contains real customer data (§17).
- Test output in an evidence pack is unedited (§27.3).
- A skipped or quarantined test is disclosed in the pull request, never hidden.

---

## 29. Success metrics

Metrics are stated as **what will be measured**, not as targets invented before any measurement exists
(§3.1). Baselines and targets are set at **Step 14 — Pilot and Commercial Launch** with real pilot data.

### 29.1 Product health

| Metric | Why it matters |
| --- | --- |
| Share of orders whose tracking link is opened by the customer | Whether the tracking differentiator actually lands |
| Status enquiries handled per outlet per day | Whether tracking reduces manual work for kasir |
| Median age of laundry at collection | The core unclaimed-laundry outcome (§11) |
| Volume and value of laundry older than H+7 and H+14 | Cash trapped on shelves |
| Recovery rate after H+1/H+3/H+7 reminders | Whether the ladder works |
| Pickup and delivery time-window adherence | Whether the delivery promise is kept (§10) |
| Rework rate after quality control | Production quality |

### 29.2 Operational health

| Metric | Why it matters |
| --- | --- |
| Offline queue backlog and sync failure rate | Ops app reliability (§13) |
| Duplicate payment suppression count | Financial integrity working as designed (§16) |
| Shift cash variance distribution | Cash handling discipline |
| Courier cash outstanding and reconciliation lag | Money in transit |
| Notification delivery success by provider | Channel reliability and cost (§14, §21) |
| API error rate and latency percentiles | Platform health (§19, §20) |

### 29.3 Commercial health

| Metric | Why it matters |
| --- | --- |
| Trial-to-paid conversion after the 14-day trial | Whether the product proves itself in two weeks |
| Plan distribution across Starter, Growth, Scale, Enterprise | Whether the pricing ladder matches reality (§21) |
| Tenant retention and voluntary churn reasons | Whether the product keeps earning its price |
| Outlets per tenant over time | Whether tenants grow inside the product |
| WhatsApp cost per active outlet | Whether transparent messaging cost stays sustainable |

### 29.4 Non-metrics

The product does not optimise for time-in-app, notification volume, or app installs. Installing the
customer app is never a success metric that justifies degrading the portal (DEC-0014).

---

## 30. Positioning

### 30.1 One-line positioning

**Aish Laundry App — aplikasi operasional laundry multi-tenant untuk UMKM dan jaringan laundry Indonesia:
pelanggan bisa melacak cuciannya tanpa instal aplikasi, antar-jemput terkelola rapi, dan cucian menumpuk
tidak lagi menjadi uang yang hilang.**

### 30.2 Who it is for

- Single-outlet laundry UMKM that has outgrown paper nota and WhatsApp.
- Growing laundry businesses opening their second and third outlet.
- Multi-brand laundry operators who need consolidation without losing per-brand identity.
- Owners who run pickup and delivery as a competitive advantage.

### 30.3 Who it is not for

- Large industrial or hospital linen operations with heavy compliance and asset-tracking requirements.
- Non-laundry retail seeking a generic POS.
- Businesses wanting a one-off perpetual licence (DEC-0010).

### 30.4 Competitive frame

| Alternative | Where it falls short | Aish Laundry App |
| --- | --- | --- |
| Paper nota and WhatsApp | No tracking, no consolidation, no aging visibility, no audit | Structured operations with the same WhatsApp reach |
| Generic retail POS | No laundry domain, no production stages, no unclaimed-laundry logic | Laundry-native domain model |
| Enterprise laundry systems | Enterprise pricing, long implementation, over-scoped | UMKM pricing from Rp79.000/bulan, usable on day one |
| Marketplace apps | Take a cut, own the customer relationship | The tenant owns its customers and its data (§17) |

### 30.5 Proof points

1. Tracking with **no app installation required** (§9).
2. **Pickup and delivery** with proof and cash reconciliation as a core module (§10).
3. **H+1 / H+3 / H+7 / H+14** unclaimed-laundry recovery (§11).
4. **Multi-tenant, multi-brand, multi-outlet** from the foundation, not retrofitted (§4).
5. **Transparent pricing** with no lifetime plan, no per-nota fee, and no security paywall (§21).

### 30.6 Tone of voice

Professional, plain, respectful, and Indonesian. The product never oversells. Claims made in marketing
must be claims the software can substantiate — the honesty rule (§3.1) applies to positioning too.

---

## 31. Decision records

Thirty-five decisions are locked. Fifteen were locked at the 1.0.0 baseline; DEC-0016 was added at
version 1.0.1, DEC-0017 at version 1.2.0, DEC-0018 … DEC-0023 at version 1.3.0, DEC-0024 … DEC-0027 at
version 1.4.0, DEC-0028 … DEC-0031 at version 1.4.1, DEC-0032 at version 1.4.2, DEC-0033 at version 1.4.3, DEC-0034 at version 1.4.4, and DEC-0035 at version 1.4.6. DEC-0001 … DEC-0023 carry date
**19 July 2026**; DEC-0024 … DEC-0027 carry **20 July 2026**; DEC-0028 … DEC-0031 carry
**21 July 2026**; DEC-0035 carries **23 July 2026**. All carry status **ACCEPTED**. Each has a full record in
[`decisions/`](decisions/).

**This section was stale and is corrected under
[DEC-0029](decisions/DEC-0029-canonical-status-drift-remediation-and-cross-document-validation.md).**
It read "Twenty-four decisions are locked" and listed DEC-0001 … DEC-0024 while DEC-0025, DEC-0026, and
DEC-0027 already existed as accepted records in [`decisions/`](decisions/) — the same drift class as the
§24 roadmap table, and undetected for the same reason: no validator compared this table against the
directory it describes. `scripts/validate-decisions.py` now does, in both directions, so a record added
without being listed here — or listed here without existing — fails closed.

| ID | Title | Status | Record |
| --- | --- | --- | --- |
| DEC-0001 | Official Product Name | ACCEPTED | [DEC-0001](decisions/DEC-0001-official-product-name.md) |
| DEC-0002 | Multi-Tenant Architecture | ACCEPTED | [DEC-0002](decisions/DEC-0002-multi-tenant-architecture.md) |
| DEC-0003 | Multi-Laundry Owner Model | ACCEPTED | [DEC-0003](decisions/DEC-0003-multi-laundry-owner-model.md) |
| DEC-0004 | Flutter Client and Web Console | ACCEPTED | [DEC-0004](decisions/DEC-0004-flutter-client-and-web-console.md) |
| DEC-0005 | API-First Modular Monolith Backend | ACCEPTED | [DEC-0005](decisions/DEC-0005-api-first-modular-monolith-backend.md) |
| DEC-0006 | Public Tracking Without App Installation | ACCEPTED | [DEC-0006](decisions/DEC-0006-public-tracking-without-app-installation.md) |
| DEC-0007 | Pickup and Delivery as Core Product | ACCEPTED | [DEC-0007](decisions/DEC-0007-pickup-and-delivery-as-core-product.md) |
| DEC-0008 | H+1 H+3 H+7 Reminder as Core Product | ACCEPTED | [DEC-0008](decisions/DEC-0008-h1-h3-h7-reminder-as-core-product.md) |
| DEC-0009 | Initial Commercial Pricing | ACCEPTED | [DEC-0009](decisions/DEC-0009-initial-commercial-pricing.md) |
| DEC-0010 | No Lifetime Cloud Subscription | ACCEPTED | [DEC-0010](decisions/DEC-0010-no-lifetime-cloud-subscription.md) |
| DEC-0011 | Transparent Third-Party Messaging Costs | ACCEPTED | [DEC-0011](decisions/DEC-0011-transparent-third-party-messaging-costs.md) |
| DEC-0012 | Tenant Isolation and Financial Integrity Hard Gate | ACCEPTED | [DEC-0012](decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md) |
| DEC-0013 | Exact-SHA Evidence Before GO | ACCEPTED | [DEC-0013](decisions/DEC-0013-exact-sha-evidence-before-go.md) |
| DEC-0014 | Customer Android Does Not Replace Public Tracking | ACCEPTED | [DEC-0014](decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md) |
| DEC-0015 | MVP Focuses on Laundry Operations | ACCEPTED | [DEC-0015](decisions/DEC-0015-mvp-focuses-on-laundry-operations.md) |
| DEC-0016 | Public Repository Visibility Accepted Deviation | ACCEPTED | [DEC-0016](decisions/DEC-0016-public-repository-visibility-accepted-deviation.md) |
| DEC-0017 | Single-Maintainer Approval Standing Deviation | ACCEPTED | [DEC-0017](decisions/DEC-0017-single-maintainer-approval-standing-deviation.md) |
| DEC-0018 | Two-Layer Design Token Architecture | ACCEPTED | [DEC-0018](decisions/DEC-0018-two-layer-design-token-architecture.md) |
| DEC-0019 | Light Theme is the Canonical MVP Theme; Dark Mode Deferred | ACCEPTED | [DEC-0019](decisions/DEC-0019-light-theme-canonical-mvp-dark-mode-deferred.md) |
| DEC-0020 | System-First Typography; No Font Binary Committed | ACCEPTED | [DEC-0020](decisions/DEC-0020-system-first-typography-no-font-binary.md) |
| DEC-0021 | WCAG 2.2 AA-Aligned Accessibility Target | ACCEPTED | [DEC-0021](decisions/DEC-0021-wcag-22-aa-aligned-accessibility-target.md) |
| DEC-0022 | Canonical UX State Taxonomy and Role-Adaptive Navigation | ACCEPTED | [DEC-0022](decisions/DEC-0022-canonical-ux-state-taxonomy-and-role-adaptive-navigation.md) |
| DEC-0023 | Low-Fidelity SVG Wireframes; No Final-Logo Fabrication | ACCEPTED | [DEC-0023](decisions/DEC-0023-low-fidelity-wireframes-and-no-final-logo-fabrication.md) |
| DEC-0024 | Step 3 Runtime Introduction and Runtime Scope Guard Transition | ACCEPTED | [DEC-0024](decisions/DEC-0024-step-3-runtime-introduction-and-runtime-scope-guard-transition.md) |
| DEC-0025 | Platform-Managed Role Catalogues and Tenant-Scoped Authorization | ACCEPTED | [DEC-0025](decisions/DEC-0025-platform-managed-role-catalogues-and-tenant-scoped-authorization.md) |
| DEC-0026 | Step 3 Flutter Platform Scaffolding Guard Transition | ACCEPTED | [DEC-0026](decisions/DEC-0026-step-3-flutter-platform-scaffolding-guard-transition.md) |
| DEC-0027 | Local Development Environment Bootstrap and Template Contract | ACCEPTED | [DEC-0027](decisions/DEC-0027-local-development-environment-bootstrap-and-template-contract.md) |
| DEC-0028 | Step 4 Scope Resolution and Canonical Authorization | ACCEPTED | [DEC-0028](decisions/DEC-0028-step-04-scope-resolution-and-canonical-authorization.md) |
| DEC-0029 | Canonical Status Drift Remediation and Cross-Document Validation | ACCEPTED | [DEC-0029](decisions/DEC-0029-canonical-status-drift-remediation-and-cross-document-validation.md) |
| DEC-0030 | Step 4 Runtime Scope Transition | ACCEPTED | [DEC-0030](decisions/DEC-0030-step-04-runtime-scope-transition.md) |
| DEC-0031 | Step 4 Traceability Boundaries | ACCEPTED | [DEC-0031](decisions/DEC-0031-step-04-traceability-boundaries.md) |
| DEC-0032 | Step 3 Post-GO Corrective Remediation: Runtime Authentication Wiring | ACCEPTED | [DEC-0032](decisions/DEC-0032-step-03-post-go-corrective-auth-runtime-wiring.md) |
| DEC-0033 | Step 4 Independent Review Findings SEC-01 … SEC-12, and the Conditions Under Which Each Closes | ACCEPTED | [DEC-0033](decisions/DEC-0033-step-04-independent-review-closure.md) |
| DEC-0034 | Password-Reset Token Disclosure: a Step 3 Post-GO Security Correction Co-Delivered in PR #18 | ACCEPTED | [DEC-0034](decisions/DEC-0034-step-03-token-logging-correction-carried-in-step-04.md) |
| DEC-0035 | Step 5 Runtime Scope Transition | ACCEPTED | [DEC-0035](decisions/DEC-0035-step-05-runtime-scope-transition.md) |

### 31.1 Decision record rules

- Every record contains: ID, Title, Status, Date, Context, Decision, Consequences, Positive consequences,
  Negative consequences / trade-offs, Verification, Supersession policy, Related Master Source sections.
- Identifiers are permanent and never reused.
- A decision is superseded, never edited into a different decision. The superseded record keeps its
  content and gains a supersession note pointing at its replacement.
- Any change to a locked commercial or architectural decision requires a new record and a Master Source
  version bump.

### 31.2 Traceability

Mapping from foundation area to rule file, decision record, and validator:
[`GOVERNANCE_TRACEABILITY.md`](GOVERNANCE_TRACEABILITY.md).

---

## 32. Changelog

The canonical changelog is [`CHANGELOG.md`](CHANGELOG.md), maintained in Keep a Changelog format with
semantic versioning.

### 32.00000 Version 1.4.2

**1.4.2 — 22 July 2026 — Step 3 post-GO corrective remediation recorded.**

Added DEC-0032 (Step 3 post-GO corrective remediation: runtime authentication wiring). Classified
**PATCH** under §1.2: no product decision, pricing figure, roadmap number, hierarchy level, reminder
stage, or architectural lock changes. The only edit is the §31 index entry that
`scripts/validate-decisions.py` requires for every decision record on disk, plus this changelog note.

**What DEC-0032 records.** `AuthService` had exactly one implementation in the tree — the test double
in `packages/testing` — and all three Flutter applications declared their production provider as a
throwing stub with no production override. Every real launch threw on the first frame that read it, so
no surface could authenticate against a real backend. The defect is classified as an internal
pre-existing Step 3 runtime defect at `HIGH — REAL RUNTIME PATH UNAVAILABLE`, remediated after `GO` on
a separate branch and merged through PR #19.

**The Step 3 `GO` determination and its tag are unchanged.** The immutable tag
`aish-laundry-step-03-runtime-auth-multitenancy-rbac-v1.4.0-go` was not moved, deleted, recreated, or
retargeted; it still peels to `0e2554338812b05eba8411afeb099212b05f9761` and it does **not** cover the
correction. Step 3 remains `GO WITH ACCEPTED DEVIATION`. Nothing here advances a step status,
authorises deployment, or starts Step 4.

### 32.00000 Version 1.4.1

**1.4.1 — 21 July 2026 — canonical status drift remediation and Step 4 start.**

Added DEC-0028 (Step 4 scope resolution and canonical authorization), DEC-0029 (canonical status drift
remediation and cross-document validation), DEC-0030 (Step 4 runtime scope transition), and DEC-0031
(Step 4 traceability boundaries). Classified
**PATCH** under §1.2: no product decision, pricing figure, roadmap number, hierarchy level, reminder
stage, or architectural lock changes. Statements of fact are corrected to match evidence that already
existed, and one step status advances through the ordinary canonical process.

**Three stale-truth corrections.** §24 declared Step 2 `IN PROGRESS` and Step 3 `PLANNED` while
`ROADMAP.md`, `STATUS.md`, and two immutable annotated `GO` tags all recorded both steps as
`GO WITH ACCEPTED DEVIATION`. §24 also contradicted §32's own 1.4.0 entry, which describes Step 3
runtime as delivered. Both rows are corrected. Separately, `STATUS.md` §6 declared the database and
Redis `ABSENT` while §2 of the same document declared both `PRESENT` and `verify-step-03.sh` reported
them reachable with migrations applied; §6 now states the environment each row describes rather than a
bare word that contradicts §2. **Understatement is corrected with the same seriousness as
overstatement** — a canonical document that disagrees with an immutable tag is wrong in either
direction (Rule 01).

Separately again, §31 declared "Twenty-four decisions are locked" and listed DEC-0001 … DEC-0024 while
DEC-0025, DEC-0026, and DEC-0027 already existed as accepted records. §31 now lists all thirty.

**Three validator gaps closed.** No validator had ever parsed this document's own §24 roadmap table —
`validate-roadmap.py` read only `ROADMAP.md`; `validate-status.py` had no check that two tables inside
`STATUS.md` agree; and nothing compared §31 against `decisions/`. All three gaps are now covered by
fail-closed, adversarially tested checks, each verified in both directions (DEC-0029).

**Step 4 runtime scope.** DEC-0030 moves exactly four feature labels — service catalog, price list,
customer management, and printer configuration — from forbidden to permitted in
`scripts/validate-runtime-scope.py`, effective from canonical step 4. Every other label stays
forbidden, now named `STEP5_PLUS_FEATURE_TOKENS`: orders, payments, QRIS, production, quality control,
tracking, WhatsApp, pickup, delivery, courier settlement, the reminder ladder, receivables, finance,
loyalty, and subscription billing among them. `receipt` stays forbidden while `printer` is permitted,
because FR-045 authorises printer *configuration* as outlet master data while the nota itself is FR-052
in Step 5. **A permitted label is not an implemented feature**, and `classify` still reports scope
classification only (§Rule 36 hard rule 6).

**Step 4 begins.** §24 moves Step 4 from `PLANNED` to `IN PROGRESS` under the separate canonical
authorization DEC-0028 records, as Rule 49 requires. `IN PROGRESS` is the only status this confers:
Step 4 delivers no feature by starting, and `GO` remains owner-conferred against exact-SHA evidence.
Steps 5–14 remain `PLANNED`, all Step 5+ business features remain `NOT IMPLEMENTED`, and deployment
remains `ABSENT`.

### 32.0000 Version 1.4.0

**1.4.0 — 20 July 2026 — Step 3 runtime introduction and runtime scope guard transition.**

Added DEC-0024, which authorises the first application runtime in this repository and transitions the
mechanical guard that enforced its absence.

Steps 0, 1, and 2 prohibited all application runtime, enforced by `scripts/validate-no-runtime.py` and
by the required `Runtime Detection / classify` check. Step 3 is the first canonical step authorised to
introduce runtime. Those two facts conflict, and the conflict is resolved by **versioning the guard
semantics** rather than by deleting enforcement or by rewriting history:

- current `main` and Step 3 onward are governed by an allowlist-based, fail-closed **runtime scope**
  guard, `scripts/validate-runtime-scope.py`;
- Steps 0–2 remain governed by the **runtime absence** guard, which is retained and executed against
  their immutable `GO` tags;
- neither guard is ever applied to the other's period, because doing so would either make Step 3
  impossible or retroactively invalidate a `GO` conferred under rules that did not yet exist.

The required status check context remains exactly `classify`. Its published name is unchanged; only
the states it reports are extended, and it now reports a specific classification rather than a generic
success message.

The new guard is **stricter than the guard it replaces** in every dimension except the single one the
owner authorised. In addition to placement rules it adds structural Step 4+ feature detection,
deployment-artifact detection, credential detection, personal-data detection, and status-claim honesty
checks — none of which the absence guard performed, because forbidding all runtime made them
unnecessary.

**Runtime existing is not runtime working.** A manifest is not a feature, a migration is not a tested
schema, and this version bump confers no implementation status whatsoever. All product features remain
`NOT IMPLEMENTED`, deployment remains `ABSENT`, and Step 4+ business features remain forbidden.

### 32.0000 Version 1.3.0

**1.3.0 — 19 July 2026 — Step 2 design system and UX foundation.**

Added §35, making the Step 2 artefacts canonical for their subject matter: the design token layers, the
colour and contrast system, typography, spacing and density, the responsive foundation, platform
adaptation, the accessibility foundation, content design and the Bahasa Indonesia UX copy glossary, the
component catalog and state matrix, the information architecture, the screen inventory, the critical
journeys, the UX state model, and the design and UX threat review. Added §18.5 recording the four locked
foundation decisions. Added DEC-0018 … DEC-0023. Moved Step 1 to `GO` and Step 2 to `IN PROGRESS` in the
roadmap status table.

Classified MINOR under §1.2: the change is additive. No product decision was reversed, no pricing figure
altered, no roadmap number changed, and no architectural lock touched. **Step 2 creates no runtime.**
Every product feature remains `NOT IMPLEMENTED`, the backend runtime and Flutter workspace remain
`ABSENT`, application CI remains `NOT APPLICABLE`, and UAT remains `NOT STARTED`.

### 32.000 Version 1.2.0

**1.2.0 — 19 July 2026 — Single-maintainer approval recorded as a standing deviation.**

Added DEC-0017 and a note on §25.1 item 12 recording that independent human approval is `ABSENT` under
single-maintainer governance, naming the compensating controls and stating plainly that they are **not
equivalent** to independent review. The item itself is **not** deleted and becomes binding when a second
maintainer exists. Classified MINOR under §1.2 because the note changes whether that gate blocks Step
closure, which is a change of meaning rather than a clarification. No product decision, pricing figure,
roadmap number, or architectural lock was changed.

### 32.00 Version 1.1.0

**1.1.0 — 19 July 2026 — Step 1 requirements and domain model.**

Added §34, making the Step 1 artefacts canonical for their subject matter: the requirement identifier
scheme, the fifteen order statuses, the eleven pickup/delivery statuses, the four quality-control
statuses, and the twenty bounded contexts. Moved Step 1 to `IN PROGRESS` in §24. Classified MINOR under
§1.2 because §34 is new canonical scope; **no existing product decision, pricing figure, roadmap
number, or architectural lock was changed**, so no new decision record was required.

### 32.0 Version 1.0.1

**1.0.1 — 19 July 2026 — Public repository deviation codified.**

Added §15.8 (public repository authoring constraints and single-maintainer governance), recorded the
canonical desired visibility as PRIVATE with PUBLIC as an accepted deviation (§21.6), recorded Step 0 as
GO with its deviation (§24), and added DEC-0016. No product decision, no pricing figure, no roadmap
number, and no architectural lock was changed. Classified MINOR under §1.2 because §15.8 is new canonical
scope rather than a clarification.

### 32.1 Baseline entry

**1.0.0 — 19 July 2026 — Step 0 governance foundation.**

Established the Master Source at version 1.0.0; the canonical status model; the locked roadmap Step 0 to
Step 14; the fifteen accepted decision records; the tenant isolation and financial integrity hard gates;
the git, release, evidence, AI execution, and tooling policies; the Definition of Done; and the runtime
placeholder structure with no runtime of any kind.

### 32.2 Changelog rules

1. Every pull request that changes canonical content adds a changelog entry.
2. Entries are grouped as Added, Changed, Deprecated, Removed, Fixed, Security.
3. Entries state what changed for a reader of the product or the governance, not which files moved.
4. A Master Source version bump always appears in the changelog with its date.
5. Entries never claim work that did not happen (§1.3).

---

## 33. AI instructions

Operating instructions for any AI agent working in this repository. These complement §27 and the rule
files in `.claude/rules/`.

### 33.1 Before doing anything

1. Read this document. It is the source of truth and it overrides your assumptions.
2. Read [`STATUS.md`](STATUS.md) to learn what actually exists right now.
3. Read [`ROADMAP.md`](ROADMAP.md) and identify the current Step and its boundaries.
4. Read the rule files in `.claude/rules/` and the policies in [`governance/`](governance/).
5. Read `CLAUDE.md` at the repository root for the operating contract.

### 33.2 While working

6. Deliver exactly the current Step's scope. Nothing earlier, nothing later.
7. Prefer editing an existing canonical document over creating a new one.
8. Never create a runtime manifest, schema, migration, or deployment before the authorising Step.
9. Never write `GO` as Step 0's status.
10. Use only the canonical status vocabulary.
11. Keep every internal markdown link pointing at a file that exists.
12. Reproduce locked facts — pricing, roadmap, hierarchy, reminder ladder — **exactly**. Do not paraphrase
    a number.
13. Never invent a product decision. If a decision is needed and does not exist, stop and say so.

### 33.3 When reporting

14. Report only what you actually did. Distinguish clearly between "created", "verified", and "assumed".
15. Paste real command output, unedited, sanitised of secrets.
16. Bind every completion claim to the exact commit SHA.
17. State unmet requirements plainly, in a section the reader cannot miss.
18. If a hard gate (§15.7, §16.6) is at risk, stop, do not merge, and report **NO-GO** with the reason.

### 33.4 Never

19. Never commit or print a secret.
20. Never force push to `main`, rewrite history on `main`, or move or delete a tag.
21. Never weaken, skip, or delete a validator assertion to obtain a green result.
22. Never describe an empty folder as an implemented feature.
23. Never claim a test, build, deployment, CI run, or UAT that did not happen.
24. Never claim this repository is private — it is PUBLIC by owner decision (AMENDMENT-0001).

---

## 34. Step 1 artefacts — requirements and domain model

Step 1 turns this document into precise, buildable requirements and a conceptual domain model. The
artefacts below are **canonical for their subject matter** and subordinate to this document: where any
of them disagrees with the Master Source, the Master Source wins and the artefact is defective (§1.1).

### 34.1 Status

**Step 1 produces documentation only.** It creates **no runtime**: no Flutter workspace, no Laravel
application, no schema, no migration, no API, no UI, no deployment. Every product feature remains
`NOT IMPLEMENTED`, the backend runtime and Flutter workspace remain `ABSENT`, application CI remains
`NOT APPLICABLE`, and UAT remains `NOT STARTED`.

**Documentation is not implementation.** A requirement, an invariant, a state machine, a threat, or an
acceptance criterion states an obligation, never an achievement. **A written acceptance criterion is not
a passed test.** Claiming otherwise is a false claim under §1.3.

### 34.2 Canonical artefacts

| Subject | Canonical artefact |
| --- | --- |
| Product requirements | [`product/PRODUCT_REQUIREMENTS.md`](product/PRODUCT_REQUIREMENTS.md) |
| MVP boundary | [`product/MVP_SCOPE.md`](product/MVP_SCOPE.md) |
| Personas | [`product/PERSONAS.md`](product/PERSONAS.md) |
| Requirement traceability | [`product/REQUIREMENT_TRACEABILITY.md`](product/REQUIREMENT_TRACEABILITY.md) |
| Domain vocabulary | [`domain/DOMAIN_GLOSSARY.md`](domain/DOMAIN_GLOSSARY.md) |
| Bounded contexts | [`domain/BOUNDED_CONTEXTS.md`](domain/BOUNDED_CONTEXTS.md) |
| Aggregates | [`domain/AGGREGATE_CATALOG.md`](domain/AGGREGATE_CATALOG.md) |
| Domain invariants | [`domain/DOMAIN_INVARIANTS.md`](domain/DOMAIN_INVARIANTS.md) |
| Tenant boundaries | [`domain/TENANT_BOUNDARIES.md`](domain/TENANT_BOUNDARIES.md) |
| Order lifecycle | [`state-machines/ORDER_STATE_MACHINE.md`](state-machines/ORDER_STATE_MACHINE.md) |
| Initial threat model | [`security/INITIAL_THREAT_MODEL.md`](security/INITIAL_THREAT_MODEL.md) |
| Data classification | [`security/DATA_CLASSIFICATION.md`](security/DATA_CLASSIFICATION.md) |
| Non-functional requirements | [`quality/NON_FUNCTIONAL_REQUIREMENTS.md`](quality/NON_FUNCTIONAL_REQUIREMENTS.md) |
| Acceptance criteria | [`quality/ACCEPTANCE_CRITERIA.md`](quality/ACCEPTANCE_CRITERIA.md) |

The full sets live under `docs/product/`, `docs/domain/`, `docs/state-machines/`, `docs/security/`, and
`docs/quality/`.

### 34.3 Requirement identifiers

Requirements carry stable, permanent identifiers using these canonical prefixes: `FR-` functional,
`NFR-` non-functional, `SEC-` security, `TEN-` tenancy, `FIN-` financial, `OFF-` offline, `TRK-`
tracking, `DEL-` delivery, `UCL-` unclaimed laundry, `NOT-` notification, `SUB-` subscription, `RPT-`
reporting.

An identifier is **never reused**. A withdrawn requirement keeps its identifier and gains a withdrawal
note, because every document that cited it would otherwise silently change meaning.

### 34.4 The canonical status sets

Fixed in Step 1 and binding on every later Step. Changing, adding, removing, or renaming any value
requires a decision record.

**Order status** — fifteen values: `DRAFT`, `RECEIVED`, `AWAITING_PROCESS`, `SORTING`, `WASHING`,
`DRYING`, `FINISHING`, `QUALITY_CONTROL`, `REWORK`, `READY_FOR_PICKUP`, `SCHEDULED_FOR_DELIVERY`,
`OUT_FOR_DELIVERY`, `COMPLETED`, `CANCELLED`, `ISSUE`.

**Pickup and delivery job status** — eleven values: `REQUESTED`, `CONFIRMED`, `SCHEDULED`, `ASSIGNED`,
`EN_ROUTE`, `ARRIVED`, `PICKED_UP`, `DELIVERED`, `FAILED`, `RESCHEDULED`, `CANCELLED`.

**Quality control status** — four values: `PENDING`, `PASSED`, `FAILED_REWORK_REQUIRED`,
`WAIVED_WITH_AUTHORIZATION`. A waiver requires an explicit permission, a recorded reason, and an audit
entry.

Every transition is explicitly enumerated in `docs/state-machines/`. There is no arbitrary status write
and no generic client-controlled "set status" operation.

### 34.5 The twenty bounded contexts

Identity and Access · Tenant and Organization · Subscription and Entitlement · Customer Management ·
Service Catalog and Pricing · Order Intake and POS · Production Operations · Quality Control and
Rework · Payment and Receivables · Customer Tracking · Pickup and Delivery · Courier Assignment and
Settlement · Notification and Communication · Unclaimed Laundry Recovery · Loyalty, Membership, and
Deposit · Reporting and Owner Portfolio · Audit and Compliance · Platform Administration · Offline
Synchronization · File and Evidence Management.

Every aggregate belongs to exactly one bounded context, and backend module boundaries mirror this set
(§6.2). Adding, removing, renaming, or merging a context requires a decision record.

### 34.6 Conceptual, not physical

The Step 1 domain model is **conceptual**. Any entity-relationship diagram in it carries the literal
marker `CONCEPTUAL DOMAIN MODEL — NOT DATABASE SCHEMA`. Physical schema, indexes, and migrations arrive
at **Step 3** and are forbidden before then (§24.1).

### 34.7 Verification

Step 1 is verified by `bash scripts/verify-step-01.sh`, which runs the Step 0 governance gates that
remain in force plus the Step 1 gates. Its output is bound to an exact commit SHA (DEC-0013).

These are **governance validators**, not application tests. There are no unit, widget, integration, or
end-to-end tests in Step 1, because there is no application, and none may be claimed (§28.1).

---

## 35. Step 2 artefacts — design system and UX foundation

Step 2 turns §18 into a specified, checkable design and UX foundation. The artefacts below are
**canonical for their subject matter** and subordinate to this document: where any of them disagrees
with the Master Source, the Master Source wins and the artefact is defective (§1.1).

### 35.1 Status

**Step 2 produces documentation only.** It creates **no runtime**: no Flutter workspace, no Laravel
application, no schema, no migration, no API, no screen, no theme, no deployment. Every product feature
remains `NOT IMPLEMENTED`, the backend runtime and Flutter workspace remain `ABSENT`, application CI
remains `NOT APPLICABLE`, and UAT remains `NOT STARTED`.

**Documentation is not implementation.** A design token is not a theme. A component specification is not
a component. A wireframe is not a screen. An accessibility requirement is not a passed audit. Claiming
otherwise is a false claim under §1.3.

### 35.2 Canonical artefacts

| Subject | Canonical artefact |
| --- | --- |
| Design system entry point | [`design/DESIGN_SYSTEM.md`](design/DESIGN_SYSTEM.md) |
| Design principles | [`design/DESIGN_PRINCIPLES.md`](design/DESIGN_PRINCIPLES.md) |
| Brand foundation | [`design/BRAND_FOUNDATION.md`](design/BRAND_FOUNDATION.md) |
| Design tokens | [`design/tokens/README.md`](design/tokens/README.md) |
| Colour and contrast | [`design/COLOR_AND_CONTRAST.md`](design/COLOR_AND_CONTRAST.md) |
| Typography | [`design/TYPOGRAPHY.md`](design/TYPOGRAPHY.md) |
| Spacing, sizing, density | [`design/SPACING_SIZING_DENSITY.md`](design/SPACING_SIZING_DENSITY.md) |
| Responsive foundation | [`design/RESPONSIVE_FOUNDATION.md`](design/RESPONSIVE_FOUNDATION.md) |
| Platform adaptation | [`design/PLATFORM_ADAPTATION.md`](design/PLATFORM_ADAPTATION.md) |
| Accessibility foundation | [`design/ACCESSIBILITY.md`](design/ACCESSIBILITY.md) |
| Content design | [`design/CONTENT_DESIGN.md`](design/CONTENT_DESIGN.md) |
| UX copy glossary | [`design/UX_COPY_GLOSSARY.md`](design/UX_COPY_GLOSSARY.md) |
| Component catalog | [`design/COMPONENT_CATALOG.md`](design/COMPONENT_CATALOG.md) |
| Component state matrix | [`design/COMPONENT_STATE_MATRIX.md`](design/COMPONENT_STATE_MATRIX.md) |
| Information architecture | [`ux/information-architecture/`](ux/information-architecture/) |
| Screen inventory | [`ux/SCREEN_INVENTORY.md`](ux/SCREEN_INVENTORY.md) |
| Critical journeys | [`ux/CRITICAL_JOURNEYS.md`](ux/CRITICAL_JOURNEYS.md) |
| UX state model | [`ux/UX_STATE_MODEL.md`](ux/UX_STATE_MODEL.md) |
| Offline and sync UX | [`ux/OFFLINE_AND_SYNC_UX.md`](ux/OFFLINE_AND_SYNC_UX.md) |
| Security and privacy UX | [`ux/SECURITY_AND_PRIVACY_UX.md`](ux/SECURITY_AND_PRIVACY_UX.md) |
| Design and UX threat review | [`security/DESIGN_AND_UX_THREAT_REVIEW.md`](security/DESIGN_AND_UX_THREAT_REVIEW.md) |
| UX traceability | [`quality/STEP_02_TRACEABILITY.md`](quality/STEP_02_TRACEABILITY.md) |

The full sets live under `docs/design/` and `docs/ux/`.

### 35.3 The token layers

Design tokens are layered, and the layering is machine-enforced (DEC-0018):

- **Primitive** — a literal value carrying no meaning. Never named by a component.
- **Semantic** — a meaning bound to a primitive. `color.semantic.conflict` is the single place the
  meaning of "local and server disagree" is bound to a colour.
- **Component alias** — component-scoped, referencing a semantic colour or, for dimension, motion,
  elevation and typography, the corresponding primitive.

**A component specification never names a primitive colour and never states a literal hex value.**

### 35.4 The accessibility target

The mandated wording, used verbatim and never softened or strengthened:

**DESIGNED TO MEET WCAG 2.2 AA REQUIREMENTS — NOT YET RUNTIME-TESTED**

Concretely: normal text ≥ 4.5:1, large text ≥ 3:1, interactive boundaries and the focus ring ≥ 3:1,
minimum touch target 48 × 48 logical pixels, the focus indicator never removed, and **status never
conveyed by colour alone** — every status carries a semantic colour, a semantic icon, and a Bahasa
Indonesia label. Contrast ratios are recomputed from the token values by the validators, never asserted.

**Runtime accessibility testing is `NOT STARTED` and belongs to Step 13** (DEC-0021).

### 35.5 The canonical UX state taxonomy

Twenty states are canonical (DEC-0022): `Loading`, `Empty`, `Error`, `Offline`, `Pending Sync`,
`Syncing`, `Synced`, `Failed Sync`, `Conflict`, `Permission Denied`, `Session Expired`,
`Device Revoked`, `Tenant Unavailable`, `Outlet Inactive`, `Subscription Limited`, `Provider Degraded`,
`Rate Limited`, `Maintenance`, `Partial Data`, `Stale Data`.

**Every state carries a recovery path.** Adding, removing or renaming one requires a decision record.

The Ops Android surface additionally distinguishes nine sync states so that no failure is silent:
`Saved Locally`, `Waiting to Sync`, `Syncing`, `Synced`, `Sync Failed`, `Conflict`, `Server Rejected`,
`Retry Scheduled`, `Manual Attention Required`. **`Syncing` never means the server has accepted**, and
an order is never presented as paid on client state alone (§16).

### 35.6 Navigation is not authorisation

Navigation is role-adaptive across all fourteen roles (§7). **Hiding a menu item is a usability
affordance and never an access control.** Server-side authorisation is delivered in Step 3 and is
authoritative (§15). The external courier never receives tenant-wide navigation (§10).

Tenant and outlet context is visible on every operational screen. A tenant switch is explicit, never
silent, warns on unsynced critical operations, and leaves no readable cached data behind (§4).

### 35.7 Wireframes and the logo

Wireframes are **low-fidelity SVG**, each labelled `LOW-FIDELITY — NOT IMPLEMENTED`, carrying no script,
no remote reference, no embedded binary, and no personal data (DEC-0023, §15.8). A wireframe is never a
final UI and is never described as an implemented screen.

**LOGO STATUS: NOT APPROVED.** The permitted usage is the text wordmark "Aish Laundry App" as an explicit
placeholder. **No logo is fabricated and no artefact is described as the final or official logo.**

### 35.8 Verification

The canonical Step 2 command is:

```bash
bash scripts/verify-step-02.sh
```

It runs every Step 0 and Step 1 gate still in force plus every Step 2 gate, and it fails closed. The
validators are themselves adversarially tested by `scripts/test-step-02-validators.sh`, which breaks the
repository in thirty specific ways and requires each break to be caught.

These are **governance validators**, not application tests. There are no unit, widget, integration, or
end-to-end tests in Step 2, because there is no application, and none may be claimed (§28.1). A passing
gate proves a document satisfies a rule. It never proves a screen renders or an accessibility criterion
was met.

---

*End of Master Source, version 1.4.6, baseline date 19 July 2026.*
