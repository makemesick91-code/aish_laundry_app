# Aish Laundry App — Product Requirements

**Document version: 1.0.0**
**Status: PLANNED** — becomes **ACCEPTED** when Step 1 receives owner-conferred GO.
**Step: 1 — Product Requirement and Domain Model**
**Every requirement in this document has implementation status `NOT IMPLEMENTED`.**

Canonical source: [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) version 1.1.0, baseline date
19 July 2026. This document is **subordinate**: where it disagrees with the Master Source, the Master
Source wins and this document is defective and must be corrected.

---

## Section index

| § | Section |
| --- | --- |
| 1 | Document control |
| 2 | Executive summary |
| 3 | Product vision |
| 4 | Problem statements |
| 5 | Market context |
| 6 | Target segments |
| 7 | Goals |
| 8 | Non-goals |
| 9 | Success metrics |
| 10 | Personas summary |
| 11 | Jobs to be done summary |
| 12 | Customer journeys summary |
| 13 | Operational journeys summary |
| 14 | Platform requirements |
| 15 | Functional requirements — FR-001 … FR-120 |
| 16 | Non-functional requirements |
| 17 | Security requirements |
| 18 | Privacy requirements |
| 19 | Multi-tenancy requirements |
| 20 | Financial requirements |
| 21 | Offline requirements |
| 22 | Tracking requirements |
| 23 | Pickup and delivery requirements |
| 24 | Unclaimed laundry requirements |
| 25 | Reporting requirements — RPT-001 … RPT-020 |
| 26 | Subscription requirements — SUB-001 … SUB-020 |
| 27 | MVP scope |
| 28 | Release constraints |
| 29 | Dependencies |
| 30 | Risks |
| 31 | Assumptions |
| 32 | Open questions |
| 33 | Acceptance criteria linkage |
| 34 | Traceability |
| 35 | Future roadmap relationship |

---

## 1. Document control

| Field | Value |
| --- | --- |
| Document | Aish Laundry App — Product Requirements |
| Version | 1.0.0 |
| Status | PLANNED until Step 1 GO; ACCEPTED thereafter |
| Owner | Aish Tech Solution |
| Canonical source | [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) v1.3.0, baseline 19 July 2026 |
| Roadmap Step | Step 1 — Product Requirement and Domain Model |
| Primary language | Bahasa Indonesia |
| Currency | Rupiah, stored as integer |
| Timezone | Asia/Jakarta; outlet local time where outlet semantics matter |
| Repository visibility | **PUBLIC** ([DEC-0016](../decisions/DEC-0016-public-repository-visibility-accepted-deviation.md)) |

### 1.1 Requirement identifier ownership

| Series | Meaning | Defined in |
| --- | --- | --- |
| `FR-001` … `FR-120` | Functional requirements | **This document, §15** |
| `RPT-001` … `RPT-020` | Reporting requirements | **This document, §25** |
| `SUB-001` … `SUB-020` | Subscription requirements | **This document, §26** |
| `NFR-###` | Non-functional requirements | `docs/quality/NON_FUNCTIONAL_REQUIREMENTS.md` |
| `SEC-###` | Security requirements | `docs/security/SECURITY_REQUIREMENTS.md` |
| `TEN-###` | Multi-tenancy requirements | `docs/security/TENANT_ISOLATION_REQUIREMENTS.md` |
| `FIN-###` | Financial integrity requirements | `docs/quality/FINANCIAL_INTEGRITY_REQUIREMENTS.md` |
| `OFF-###` | Offline and synchronisation requirements | `docs/quality/OFFLINE_REQUIREMENTS.md` |
| `TRK-###` | Tracking portal requirements | `docs/security/TRACKING_REQUIREMENTS.md` |
| `DEL-###` | Pickup and delivery requirements | `docs/domain/DELIVERY_REQUIREMENTS.md` |
| `UCL-###` | Unclaimed laundry requirements | `docs/domain/UNCLAIMED_LAUNDRY_REQUIREMENTS.md` |
| `NOT-###` | Notification requirements | `docs/domain/NOTIFICATION_REQUIREMENTS.md` |

This document defines **only** the FR, RPT, and SUB series. It refers to the other series **by series
name** and never invents an identifier inside them.

### 1.2 Requirement record format

Every requirement carries: a stable unique **ID**, a **title**, a **statement**, a **rationale**, a
**priority** of `MUST`, `SHOULD`, or `COULD`, the canonical **Step** that delivers it, and the
implementation **status**, which is `NOT IMPLEMENTED` for every requirement in this document without
exception.

`MUST` means the product is not the product without it. `SHOULD` means it is expected but a documented
gap is survivable for one release. `COULD` means it is desirable and explicitly deferrable.

### 1.3 Change control

This document changes only through a pull request. A change that alters a **product decision** requires
an owner decision, a decision record under [`../decisions/`](../decisions/DEC-0001-official-product-name.md),
and a Master Source version bump where the Master Source itself moves
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §1.2). **No agent invents a product decision** (§27.4
rule 14). Anything genuinely unresolved goes to
[`ASSUMPTIONS_AND_OPEN_QUESTIONS.md`](ASSUMPTIONS_AND_OPEN_QUESTIONS.md) as an open question.

---

## 2. Executive summary

**Aish Laundry App** is a multi-tenant Laundry Operations, Customer Tracking, and Pickup-and-Delivery
SaaS, owned by **Aish Tech Solution**, built for laundry UMKM and laundry chains in Indonesia. Its
primary language is **Bahasa Indonesia**, its currency is **Rupiah** stored as integer, and its timezone
is **Asia/Jakarta**.

It runs on four canonical platforms: **Aish Laundry Customer Android** (Flutter), **Aish Laundry Ops
Android** (Flutter, offline-first), **Aish Laundry Console Web** (Flutter Web), and **Portal Tracking
Publik** (browser-based, no app installation required, Flutter not mandatory).

Three capabilities are the reason the product exists and are locked into the foundation: **public
tracking without app installation**, **pickup and delivery as a first-class product**, and **unclaimed
laundry recovery** through a H+1 / H+3 / H+7 / H+14 ladder.

Two hard gates govern everything: **cross-tenant data exposure is an automatic NO-GO**, and **any
financial integrity failure is an automatic NO-GO**.

This document translates the Master Source into 160 identified requirements — 120 functional, 20
reporting, 20 subscription — each bound to a canonical roadmap Step. **Nothing described here is built.**
Backend runtime is **ABSENT**. Flutter workspace is **ABSENT**. Deployment is **ABSENT**. Application CI
is **NOT APPLICABLE** until Step 3. UAT is **NOT STARTED**.

---

## 3. Product vision

Aish Laundry App makes a laundry business legible to three audiences at once
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §2.2):

- **The customer** knows where their laundry is without asking anyone.
- **The staff** know what to do next, even when the internet drops.
- **The owner** knows what every outlet earned, what is stuck, and what money is at risk.

The long-term direction is to be the operating system for Indonesian laundry businesses. Growth comes
from an owner adding a second outlet, then a second brand, then recommending the product — **not from
locking data in** (§2.4, §17.3).

Eight product values decide arguments when a design choice is contested (§3): honesty over optimism;
tenant isolation is sacred; money is never guessed; the shop floor comes first; customers own their
attention; data belongs to the tenant; fair, transparent, unglamorous pricing; boring technology,
carefully used.

---

## 4. Problem statements

Four failures recur in Indonesian laundry businesses
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §2.1):

| # | Problem | Consequence | Requirements that address it |
| --- | --- | --- | --- |
| PS-1 | **Customers cannot see their own order.** Every status question becomes a phone call or a WhatsApp message a busy kasir must answer manually. | Staff time consumed; customer anxiety; no self-service. | FR-086 … FR-099 |
| PS-2 | **Laundry piles up uncollected.** Finished orders sit for weeks; shelf space is consumed; the money is never collected; owners find out when the shelf is full. | Trapped cash and trapped space. | FR-112 … FR-117 |
| PS-3 | **Pickup and delivery is improvised.** Couriers coordinate in personal chat; cash is reconciled from memory; proof does not exist when a delivery is disputed. | Financial leakage and unresolvable disputes. | FR-100 … FR-111 |
| PS-4 | **The owner cannot see the business.** Three outlets produce three separate realities and no consolidated number that can be trusted. | Decisions made on guesswork. | RPT-001 … RPT-020 |

Existing software either targets large enterprises at enterprise prices, or is a generic POS that knows
nothing about laundry-specific realities: kiloan versus satuan, per-item production stages, garments that
must be tracked individually, and customers who genuinely forget to collect.

---

## 5. Market context

- **Primary market:** laundry UMKM dan jaringan laundry Indonesia.
- **Baseline device:** a low-end to mid-range Android phone on a congested mobile network. This is the
  normal case, not a degraded case ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §19.1).
- **Baseline channel:** WhatsApp, because it is the channel Indonesian customers actually read (§14).
  It is a third-party service with real per-message costs, real policy constraints, and real delivery
  failures.
- **Baseline currency and time:** Rupiah as integer; Asia/Jakarta for business-day logic; outlet local
  time for quiet hours.
- **Competitive frame** (§30.4): paper nota and WhatsApp offer no tracking, consolidation, aging
  visibility, or audit; generic retail POS lacks the laundry domain; enterprise laundry systems are
  over-scoped and over-priced; marketplace apps take a cut and own the customer relationship. Aish
  Laundry App is laundry-native, UMKM-priced from Rp79.000/bulan, and leaves the customer relationship
  with the tenant.

---

## 6. Target segments

| Segment | Description | Likely plan |
| --- | --- | --- |
| Single-outlet UMKM | Has outgrown paper nota and WhatsApp; one outlet, a handful of staff | Starter |
| Growing operator | Opening a second and third outlet; needs consolidation | Growth |
| Multi-brand operator | Several brands under one tenant, each with its own pricing and presentation | Scale |
| Delivery-led operator | Competes primarily on antar-jemput; courier operations dominate | Growth or Scale |
| Chain | Ten or more outlets, dedicated finance function | Scale or Enterprise |

**Not the target** ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §30.3): large industrial or hospital
linen operations with heavy compliance and asset-tracking requirements; non-laundry retail seeking a
generic POS; businesses wanting a one-off perpetual licence.

---

## 7. Goals

| ID | Goal | Measured by |
| --- | --- | --- |
| G-1 | A customer can see their order status without installing anything, without an account, and without a password. | Share of orders whose tracking link is opened |
| G-2 | A kasir can take a complete, priced, paid order in the shortest path on the screen, including offline. | Median taps and seconds to intake; offline sync success |
| G-3 | Finished laundry does not sit uncollected without a structured, human-accountable response. | Median age at collection; value older than H+7 and H+14 |
| G-4 | Every custody transfer of a customer's property is recorded with proof. | Proportion of transfers with valid proof |
| G-5 | Every Rupiah is accounted for, with variances visible rather than absorbed. | Shift variance distribution; courier cash reconciliation lag |
| G-6 | An owner can see one consolidated, drillable, trustworthy view of their tenant. | Time to answer "how did the business do yesterday" |
| G-7 | One tenant's data is never visible to another tenant. | Cross-tenant exposure incidents, which must be zero |
| G-8 | The product's claims match what the software actually does. | Absence of unsubstantiated claims in product, docs, and marketing |

G-7 and G-8 are not aspirational. G-7 is a hard gate (§19). G-8 is the honesty rule (§3.1) and applies to
this document.

---

## 8. Non-goals

Reproduced from [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §23. Explicitly **not** part of Aish Laundry
App, now or as an assumed future:

1. A general-purpose ERP.
2. A general-purpose POS for other retail verticals.
3. An accounting system — the product produces financial records, reports, and exports; it is not a
   general ledger and does not file taxes.
4. A payroll system.
5. A marketplace.
6. A courier network — the product coordinates a tenant's own couriers and external ojek lokal; it does
   not supply riders.
7. **A route optimisation engine.** Route *suggestion* and *simple ordering* are in scope; claiming
   mathematical optimisation is explicitly forbidden.
8. An AI decision-maker for money. No automated system decides refunds, writes off balances, or adjusts
   cash.
9. **Automatic disposal of unclaimed laundry. Never.**
10. Offline payment gateway confirmation — physically impossible; the product does not pretend otherwise.
11. iOS applications at this stage.
12. On-premise deployment at this stage.
13. A lifetime plan.
14. Multi-tenant data blending for cross-tenant analytics.

No requirement in this document may be interpreted as authorising any of the fourteen.

---

## 9. Success metrics

Metrics are stated as **what will be measured**. Baselines and targets are set at **Step 14 — Pilot and
Commercial Launch** with real pilot data. **No target is invented here and no target is claimed to be
met.** Full detail: [`SUCCESS_METRICS.md`](SUCCESS_METRICS.md).

Headline metrics, drawn from [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §29:

- Share of orders whose tracking link is opened by the customer.
- Status enquiries handled per outlet per day.
- Median age of laundry at collection; volume and value older than H+7 and H+14.
- Recovery rate after H+1 / H+3 / H+7 reminders.
- Pickup and delivery time-window adherence.
- Duplicate payment suppression count; shift cash variance distribution; courier cash reconciliation lag.
- Trial-to-paid conversion after the 14-day trial.

**Non-metrics** (§29.4): the product does not optimise for time-in-app, notification volume, or app
installs. Installing the Customer Android app is never a success metric that could justify degrading the
Portal Tracking Publik ([DEC-0014](../decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md)).

---

## 10. Personas summary

Fourteen canonical personas. Full detail: [`PERSONAS.md`](PERSONAS.md).

| ID | Persona | Scope | Membership account |
| --- | --- | --- | --- |
| P-01 | Platform Super Admin | Platform | Yes, platform-scoped |
| P-02 | Platform Support | Platform | Yes, platform-scoped |
| P-03 | Tenant Owner | Tenant | Yes |
| P-04 | Tenant Admin | Tenant | Yes |
| P-05 | Outlet Manager | Tenant, outlet-scoped | Yes |
| P-06 | Cashier | Tenant, outlet-scoped | Yes |
| P-07 | Production Operator | Tenant, outlet-scoped | Yes |
| P-08 | Quality Control | Tenant, outlet-scoped | Yes |
| P-09 | Courier Internal | Tenant, outlet-scoped | Yes |
| P-10 | External Local Courier | Single job only | **No — guest job link only** |
| P-11 | Finance | Tenant | Yes |
| P-12 | Customer | Own data only | Optional |
| P-13 | Corporate Customer Contact | Own organisation's orders | Optional |
| P-14 | Authorized Order Recipient | Single collection event | **No** |

Authorisation is always derived from a **Membership**, server-side, tenant-scoped. A persona label is a
design artefact and never an authorisation grant.

---

## 11. Jobs to be done summary

Forty-one jobs across five clusters. Full detail: [`JOBS_TO_BE_DONE.md`](JOBS_TO_BE_DONE.md).

| Cluster | Jobs | Steps |
| --- | --- | --- |
| Customer visibility, collection, consent | JTBD-001 … JTBD-009 | 7, 8, 9, 11 |
| Shop floor: intake, production, quality, cash | JTBD-010 … JTBD-020 | 5, 6 |
| Pickup, delivery, proof, courier cash | JTBD-021 … JTBD-027 | 8 |
| Owner portfolio, finance, master data control | JTBD-028 … JTBD-035 | 3, 4, 10 |
| Subscription, fair use, platform operation | JTBD-036 … JTBD-041 | 12 |

---

## 12. Customer journeys summary

Eight journeys. Full detail: [`USER_JOURNEYS.md`](USER_JOURNEYS.md).

| ID | Journey | Step |
| --- | --- | --- |
| UJ-001 | Track an order without installing anything | 7 |
| UJ-002 | Request a pickup | 8 |
| UJ-003 | Receive a delivery | 8 |
| UJ-004 | Collect at the outlet | 5, 6 |
| UJ-005 | Receive and act on an unclaimed-laundry reminder | 9 |
| UJ-006 | Manage notification consent | 7 |
| UJ-007 | Corporate contact tracks several orders | 7, 11 |
| UJ-008 | Use the Customer Android application | 11 |

---

## 13. Operational journeys summary

Ten journeys. Full detail: [`OPERATIONAL_JOURNEYS.md`](OPERATIONAL_JOURNEYS.md).

| ID | Journey | Step |
| --- | --- | --- |
| OJ-001 | Take an order at the counter | 5 |
| OJ-002 | Run a production shift | 6 |
| OJ-003 | Close a shift and reconcile cash | 5, 10 |
| OJ-004 | Run a courier route | 8 |
| OJ-005 | Use an external local courier | 8 |
| OJ-006 | Work the unclaimed-laundry dashboard | 9 |
| OJ-007 | Review the owner portfolio | 10 |
| OJ-008 | Configure a tenant and onboard an outlet | 3, 4 |
| OJ-009 | Platform support responds to a tenant | 12 |
| OJ-010 | Handle a subscription lifecycle event | 12 |

---

## 14. Platform requirements

Four canonical platforms. **No fifth platform exists without a decision record**
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §5).

### 14.1 Aish Laundry Customer Android

Flutter. Login by phone number and OTP; active orders; order history; order tracking; pickup request;
saved addresses; invoices; loyalty; feedback; notifications.

**Binding constraint:** it **does not replace** the Portal Tracking Publik
([DEC-0014](../decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md)). Installation is
always optional. A customer who never installs anything still gets full tracking through the portal.

### 14.2 Aish Laundry Ops Android

Flutter. The staff-facing operational application, used on the shop floor and on the road. Roles served:
kasir, manager outlet, operator produksi, quality control, kurir, laundry admin.

**Binding constraint:** this is the **offline-first** surface (§21). A kasir cannot stop taking orders
because the network dropped.

### 14.3 Aish Laundry Console Web

Flutter Web. Roles served: owner, tenant admin, manager, finance, platform admin. Reporting,
configuration, master data, finance, subscription management, and the owner portfolio dashboard live
here.

### 14.4 Portal Tracking Publik

Browser-based, **no app installation required**. The customer's default tracking experience and the
product's most visible differentiator.

**Flutter is not mandatory for this surface.** If a lighter web stack delivers materially better load
performance on low-end Android browsers over poor networks, that stack is chosen; performance for this
surface outranks stack uniformity. The choice is recorded in a decision record in the Step that builds it
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §5.4). It is the most performance-critical surface in the
product (§19.2).

### 14.5 Cross-platform requirements

- Every client surface consumes the **same versioned REST API** at `/api/v1`. No surface gets a private
  back channel or direct database access (§6.3).
- All user-facing copy is in **Bahasa Indonesia**.
- Money is displayed as formatted integer Rupiah; formatting is a view concern applied to an integer.
- **Status is never conveyed by colour alone** (§18.2 rule 2).
- Accessibility and device font scaling are supported on every surface (§18.2 rule 6).
- The design system arrives in **Step 2** as `packages/design_system`. No product screen is built before
  its foundation exists (§18.4).

---

## 15. Functional requirements

**120 requirements, FR-001 … FR-120.** Every one has status **NOT IMPLEMENTED**.

Column key: **Pri** is priority (`MUST` / `SHOULD` / `COULD`); **Step** is the canonical roadmap Step that
first delivers the requirement.

### 15.1 Identity and Access — Step 3

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-001 | Phone and OTP authentication | A user shall authenticate with a phone number and a one-time password delivered to that number. | Phone-first identity matches how Indonesian staff and customers actually identify themselves. | MUST | 3 |
| FR-002 | One user account per person | A person shall have exactly one user account across the platform, identified by phone number. | Prevents duplicate identities and makes membership the single authorisation join. | MUST | 3 |
| FR-003 | OTP issuance controls | OTP issuance shall be rate limited and protected against brute force with progressive delay and lockout. | OTP endpoints are the most abusable surface in a phone-first product. | MUST | 3 |
| FR-004 | Session establishment | A successful authentication shall establish a server-side session bound to the authenticated user and the device used. | Sessions must be revocable individually, which requires them to be server-side records. | MUST | 3 |
| FR-005 | Session revocation | A user or an authorised administrator shall be able to terminate a session immediately, after which it stops working. | A compromised session must be closable without waiting for expiry. | MUST | 3 |
| FR-006 | Device registration and revocation | A device shall be registered on first authentication and revocable individually without forcing other devices to re-authenticate. | A lost phone at an outlet must be cut off without disrupting the shift. | MUST | 3 |
| FR-007 | Membership-derived authorisation | Every authorisation decision shall be evaluated against a verified Membership, never against a bare user account and never against a client-supplied tenant identifier. | Membership is the canonical authorisation join; a client hint is not proof. | MUST | 3 |
| FR-008 | Role and permission model | The system shall provide a canonical permission model; roles shall be configurable per tenant within that model, and the model itself shall not be tenant-editable. | Tenants need flexibility; the safety envelope must remain canonical. | MUST | 3 |
| FR-009 | Least-privilege default | A newly created role shall hold no permissions until permissions are explicitly granted. | Least privilege by default rather than by discipline. | MUST | 3 |
| FR-010 | Authentication and authorisation audit | Authentication failures, permission denials, and session and device revocations shall be recorded in a security audit trail. | Security events must be reconstructable after the fact. | MUST | 3 |

### 15.2 Tenant and Organization — Step 3

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-011 | Tenant entity | The system shall provide a Tenant as the commercial and isolation boundary, carrying subscription, plan limits, billing, and data isolation. | The tenant is the boundary everything else hangs from. | MUST | 3 |
| FR-012 | Brand entity | A tenant shall be able to operate multiple Laundry Brands with different names, pricing, and presentation. | Multi-brand operators must not need multiple tenants. | MUST | 3 |
| FR-013 | Outlet entity | A brand shall be able to have multiple Outlets, each a physical location where orders are taken and production happens. | The outlet is the operational unit of the business. | MUST | 3 |
| FR-014 | Membership entity | The system shall provide Membership as the join between a user account and a tenant, carrying roles and permissions within that tenant. | Roles are tenant-relative, never global. | MUST | 3 |
| FR-015 | Multi-tenant membership | One user shall be able to hold memberships in multiple tenants, and one owner shall be able to own or manage multiple tenants. | Owners genuinely run several businesses. | MUST | 3 |
| FR-016 | Tenant switcher | Every authenticated client shall provide a tenant switcher wherever a user holds more than one membership. | Without an explicit switch, users would demand cross-tenant views. | MUST | 3 |
| FR-017 | Tenant switch isolation | Switching tenants shall issue a new server-side tenant context bound to the verified membership, clear or partition client caches, and be recorded in the audit trail. | A switch that leaves stale cache is a cross-tenant leak. | MUST | 3 |
| FR-018 | Brand and outlet scoping of roles | A role granted through a membership shall be further scopable to a specific brand or outlet. | An outlet manager must not implicitly manage every outlet. | MUST | 3 |
| FR-019 | No cross-tenant merge | The system shall never merge, deduplicate, or cross-reference records across tenants on the basis of matching owner name, email, or phone number. | Identical contact details across tenants are expected and must not cause merging. | MUST | 3 |
| FR-020 | Tenant configuration audit | Changes to tenants, brands, outlets, memberships, roles, and tenant policies shall be recorded with actor, timestamp, and before/after values. | Configuration changes break shop floors; they must be traceable. | MUST | 3 |

### 15.3 Customer Management — Step 4

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-021 | Customer profile | The system shall hold a customer profile with name, phone number, and optional email, scoped to exactly one tenant. | The customer record is the anchor of order history and consent. | MUST | 4 |
| FR-022 | Tenant-scoped customer identity | The same phone number appearing in two tenants shall produce two separate, unrelated customer profiles that are never merged or cross-referenced. | Direct application of the no-merge rule; competing laundries share customers. | MUST | 4 |
| FR-023 | Customer search | Staff shall be able to find a customer by phone number, name, or order number within their tenant and permitted scope. | Counter speed depends on lookup speed. | MUST | 4 |
| FR-024 | Customer addresses | A customer shall be able to hold multiple saved addresses, each usable for pickup and delivery. | Home and office pickup are both normal. | MUST | 4 |
| FR-025 | Address masking by context | Customer addresses shall be masked according to the viewing context, and the full address shall never be exposed on the public tracking portal. | Addresses are the most sensitive routine field the product holds. | MUST | 4 |
| FR-026 | Phone masking by context | Customer phone numbers shall be masked according to the viewing context. | A kasir preparing a delivery needs more than a portal visitor. | MUST | 4 |
| FR-027 | Consent state | The system shall record marketing consent per customer per tenant, with a timestamp and a source, and shall record opt-out immediately. | Consent is a legal and trust obligation, and it is tenant-relative. | MUST | 4 |
| FR-028 | Opt-out durability | A recorded opt-out shall never be reset by a data import, a bulk update, or a migration. | Opt-out that resets is worse than no opt-out. | MUST | 4 |
| FR-029 | Customer order history | Staff with permission shall see a customer's order history within the tenant, and never any order from another tenant. | History drives service quality; isolation is absolute. | MUST | 4 |
| FR-030 | Customer notes | Staff shall be able to record internal notes against a customer, and those notes shall never appear on the public tracking portal. | Internal notes support service; exposure would be a privacy failure. | SHOULD | 4 |

### 15.4 Service Catalog and Pricing — Step 4

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-031 | Service types | The system shall support kiloan services priced by weight and satuan services priced per item. | These are the two fundamental Indonesian laundry service shapes. | MUST | 4 |
| FR-032 | Packages | The system shall support packaged services combining multiple services at a defined price. | Packages are a standard commercial offering. | SHOULD | 4 |
| FR-033 | Add-ons | The system shall support add-ons applied to an order or an order line, such as express handling or special treatment. | Add-ons are how tenants differentiate without new services. | SHOULD | 4 |
| FR-034 | Per-brand price lists | A price list shall belong to a brand, so that different brands within a tenant may price differently. | Multi-brand operators price by brand, not by tenant. | MUST | 4 |
| FR-035 | Price list versioning | A price list shall be versioned with an effective period, and publishing a new version shall not alter any previously published version. | Price history must be reconstructable. | MUST | 4 |
| FR-036 | Historical price capture | An order shall capture the price that applied when the order was created, and shall be immune to any later price-list change. | Editing a price list must never retroactively change a past order or a reprinted nota. | MUST | 4 |
| FR-037 | Integer Rupiah pricing | Every price, total, discount, and adjustment shall be represented as integer Rupiah; floating point shall not appear in any money path. | The financial integrity hard gate. | MUST | 4 |
| FR-038 | Explicit rounding | Where a computation could produce a fractional Rupiah, the rounding rule shall be explicit and applied at a defined point, not left to language defaults. | Implicit rounding is how money quietly disappears. | MUST | 4 |
| FR-039 | Price override permission | Overriding a price on an order shall require an explicit permission and a recorded reason. | Price overrides are a financial control point. | MUST | 4 |
| FR-040 | Single canonical price source | Prices presented in any surface shall be read from one canonical tenant configuration, never hard-coded in scattered client strings. | Scattered pricing strings drift, and drift on pricing is a commercial risk. | MUST | 4 |

### 15.5 Outlet master data — Step 4

Bounded context: Tenant and Organization.

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-041 | Operating hours | An outlet shall define its operating hours in outlet local time. | Business-day logic and quiet hours depend on outlet local time. | MUST | 4 |
| FR-042 | Capacity definition | An outlet shall define its production capacity so that capacity pressure can be surfaced. | Owners need to see when an outlet is saturated. | SHOULD | 4 |
| FR-043 | Service zones | An outlet shall define service zones used to determine pickup and delivery coverage. | Coverage must be explicit rather than improvised per request. | MUST | 4 |
| FR-044 | Shift definitions | An outlet shall define shifts, which anchor shift closing and cash reconciliation. | Cash is reconciled per shift. | MUST | 4 |
| FR-045 | Printer configuration | An outlet shall be able to register printer configuration for nota output. | Nota printing is part of the counter workflow. | SHOULD | 4 |
| FR-046 | Proof policy configuration | A tenant shall configure which proof mechanisms — OTP, photo, signature, recipient name — are required for pickup and for delivery. | Proof requirements vary by tenant, but some proof is always required. | MUST | 4 |
| FR-047 | Quiet hours configuration | A tenant shall configure quiet hours per outlet, defaulting to 20.00–08.00 outlet local time. | The default is canonical; the configuration point must exist. | MUST | 4 |

### 15.6 Order Intake and POS — Step 5

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-048 | Order creation | A kasir shall be able to create an order, beginning in status `DRAFT` and moving to `RECEIVED` on confirmation. | The order is the central domain object. | MUST | 5 |
| FR-049 | Shortest-path intake | Order intake shall be the shortest primary action path in the product. | The counter is the highest-frequency, highest-pressure surface. | MUST | 5 |
| FR-050 | Order lines | An order shall carry lines for kiloan weight, satuan items, packages, and add-ons. | Laundry orders mix service shapes routinely. | MUST | 5 |
| FR-051 | Server-authoritative totals | Order totals shall be computed and authoritative on the server; any client-computed total is display only. | A client total is an input, never a decision. | MUST | 5 |
| FR-052 | Nota generation | The system shall generate a nota for an order, reprintable at any later time showing the original captured prices. | Reprints must not show a price the customer never agreed. | MUST | 5 |
| FR-053 | Order identifier | Every order shall carry a human-usable order number, which shall never grant access to any resource. | Order numbers are printed and guessable; they are identifiers, not credentials. | MUST | 5 |
| FR-054 | Per-item tracking readiness | The order model shall support identifying individual garments or items within an order. | Laundry loses single items; the model must be able to name them. | SHOULD | 5 |
| FR-055 | Special handling instructions | An order shall carry special handling instructions visible to production staff before a stage begins. | Unread instructions are a leading cause of rework. | MUST | 5 |
| FR-056 | Deposits | The system shall support recording a deposit against an order, with the balance tracked to settlement. | Deposits are standard practice for large or express orders. | MUST | 5 |
| FR-057 | Order search and listing | Staff shall be able to find and list orders by number, customer, status, outlet, and date, always tenant-scoped, paginated, and bounded. | No screen may load an unbounded result set. | MUST | 5 |
| FR-058 | Order cancellation | An order shall be cancellable to status `CANCELLED` with a recorded reason and a recorded actor. | Cancellation is an outcome, not a deletion. | MUST | 5 |
| FR-059 | Offline order intake | Order intake shall function with no network connection, queueing the operation with a stable `client_reference` reused on every retry. | Shop-floor connectivity is unreliable by assumption. | MUST | 5 |
| FR-060 | Order audit trail | Every order state change shall record actor, tenant, outlet, timestamp, and where applicable a reason. | An order's history must be reconstructable. | MUST | 5 |

### 15.7 Payment and Receivables — Step 5

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-061 | Payment methods | The system shall support cash, bank transfer, and payment-gateway payments against an order. | These are the payment channels Indonesian laundry customers use. | MUST | 5 |
| FR-062 | Payment idempotency | A payment submitted more than once with the same `client_reference` shall produce exactly one payment, returning the original result on repeat. | A duplicate payment created by a retry is an automatic NO-GO. | MUST | 5 |
| FR-063 | Server-verified gateway callbacks | Gateway callbacks shall be verified server-side for signature, amount, and status against the gateway, and replays shall be rejected. | A callback payload is a claim, not a fact. | MUST | 5 |
| FR-064 | No client-claimed payment | An order shall never be marked paid on a client claim; payment state shall originate from a verified server-side event or an authorised in-person action by an authenticated staff member. | Only the server may decide that money arrived. | MUST | 5 |
| FR-065 | Refund and void controls | Refund and void shall each require an explicit permission and a recorded reason, and shall record actor, timestamp, and amount. | A refund is never a silent operation. | MUST | 5 |
| FR-066 | No hard delete of financial records | The system shall provide no path through ordinary interfaces to delete a financial transaction. | The ledger is append-only in effect. | MUST | 5 |
| FR-067 | Corrections by reversal | Financial corrections shall be made through reversal or adjustment entries that preserve the original record. | History is added to, never rewritten. | MUST | 5 |
| FR-068 | Serialised money operations | Concurrent operations against the same order or payment shall be serialised so double submission cannot create double payment. | Concurrency is the second-largest source of duplicate payments after retries. | MUST | 5 |
| FR-069 | Financial audit trail | Every financial operation shall record actor, tenant, outlet, timestamp, before and after amounts, and reason, in an append-only trail separate from application logs. | Financial audit must survive log rotation. | MUST | 5 |
| FR-070 | Receivable tracking | The system shall track unpaid balance per order and per customer, in integer Rupiah, read from the authoritative financial records. | Receivables feed the unclaimed dashboard and the owner portfolio. | MUST | 5 |

### 15.8 Production Operations — Step 6

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-071 | Canonical status set | The system shall implement exactly the fifteen canonical order statuses: `DRAFT`, `RECEIVED`, `AWAITING_PROCESS`, `SORTING`, `WASHING`, `DRYING`, `FINISHING`, `QUALITY_CONTROL`, `REWORK`, `READY_FOR_PICKUP`, `SCHEDULED_FOR_DELIVERY`, `OUT_FOR_DELIVERY`, `COMPLETED`, `CANCELLED`, `ISSUE`. | One canonical status set; no synonyms and no tenant-invented statuses. | MUST | 6 |
| FR-072 | Transition validity | Only transitions defined in the canonical status machine shall be permitted, enforced server-side. | An invalid transition is a data-integrity failure. | MUST | 6 |
| FR-073 | Stage progress recording | A production operator shall record stage progress in a single action from the production floor. | Recording must not cost more than doing. | MUST | 6 |
| FR-074 | Batch handling | Orders shall be processable in batches through a stage while remaining individually identifiable. | Machines process batches; customers own individual orders. | MUST | 6 |
| FR-075 | Item-level flags | An operator shall be able to flag an item as damaged, missing, or requiring special handling, against the specific order. | Problems must attach to the order, not to memory. | MUST | 6 |
| FR-076 | First ready timestamp | The system shall record the timestamp of the **first** transition to `READY_FOR_PICKUP` exactly once, and that timestamp shall be immutable thereafter. | It anchors unclaimed-laundry aging, which must never restart. | MUST | 6 |
| FR-077 | Aging anchor immunity to rework | A return to `REWORK` and a subsequent second arrival at `READY_FOR_PICKUP` shall not alter the first ready timestamp. | "First" is literal; the business has carried the laundry since then. | MUST | 6 |
| FR-078 | Issue status | An order shall be movable to `ISSUE` with a recorded reason when a problem prevents normal progression. | Problems need a state, not a silent stall. | MUST | 6 |
| FR-079 | Offline production recording | Stage progress and item flags shall be recordable with no network connection and shall synchronise without duplication. | Production areas have poor connectivity. | MUST | 6 |
| FR-080 | Server-authoritative timestamps | Server timestamps shall be authoritative for ordering and reporting; device clock skew shall not corrupt the record. | Cheap devices have unreliable clocks. | MUST | 6 |

### 15.9 Quality Control and Rework — Step 6

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-081 | Quality control gate | An order shall pass through `QUALITY_CONTROL` before reaching `READY_FOR_PICKUP` where the tenant's policy requires it, enforced server-side. | Quality control that can be skipped is not a gate. | MUST | 6 |
| FR-082 | Rework with reason | Failing inspection shall move the order to `REWORK` with a recorded defect reason. | The reason is the data that reduces future rework. | MUST | 6 |
| FR-083 | Defect evidence | Quality control shall be able to attach a photograph as defect evidence, stored privately and served only through signed expiring URLs. | Evidence resolves disputes; exposure creates them. | SHOULD | 6 |
| FR-084 | Rework history | Every rework cycle shall be recorded with actor, timestamp, and reason, and shall remain visible in the order's history. | Repeated rework on one order is a signal. | MUST | 6 |
| FR-085 | Rework reporting input | Rework events shall be recorded in a form that supports rework-rate reporting by outlet, stage, and defect reason. | Rework rate is a canonical success metric. | MUST | 6 |

### 15.10 Customer Tracking — Step 7

Detailed security properties of the tracking token belong to the `TRK` and `SEC` series.

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-086 | Tracking token issuance | The system shall issue a high-entropy tracking token for an order from a cryptographically secure random source, stored hashed server-side. | The token is the only credential protecting the order view. | MUST | 7 |
| FR-087 | Token independence from order number | The tracking token shall not be the order number and shall not be derivable from it. | Order numbers are sequential, printed, and guessable. | MUST | 7 |
| FR-088 | Token revocation and expiry | A tracking token shall be revocable by the customer or the outlet, and shall expire. | A link shared too widely must be closable. | MUST | 7 |
| FR-089 | Portal content set | The portal shall show order number, brand and outlet identity, service type, current status and status history, estimated completion, amount due, payment state, and available actions. | This is the canonical safe-by-default content set. | MUST | 7 |
| FR-090 | Portal exclusions | The portal shall never show a full address, a full phone number, other orders belonging to the same customer, internal notes, or laundry photographs without OTP verification. | Sharing is expected; over-disclosure is not. | MUST | 7 |
| FR-091 | Portal sensitive actions | Sensitive portal actions, including changing a delivery address and requesting a schedule change, shall require OTP verification. | A forwarded link must not permit account-level changes. | MUST | 7 |
| FR-092 | Portal indexing prevention | The portal shall be served with `noindex` so tracking pages never enter search engines. | An indexed tracking page is a permanent public leak. | MUST | 7 |

### 15.11 Notification and Communication — Step 7

Detailed messaging behaviour belongs to the `NOT` series.

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-093 | Provider abstraction | WhatsApp sending shall sit behind an internal notification interface; no vendor SDK, payload, or identifier shall leak into business logic. | Providers must be replaceable by adapter and configuration. | MUST | 7 |
| FR-094 | Official provider as automated path | Automated, unattended sending shall go through an official WhatsApp Business API provider. | Automation must not depend on unofficial channels. | MUST | 7 |
| FR-095 | Manual deep-link fallback | A prepared deep link that a staff member sends manually shall be available as an explicit, visible fallback, and shall never be presented or sold as automation. | Honesty about what is automated. | MUST | 7 |
| FR-096 | Transactional and marketing separation | Transactional and marketing messages shall use separate categories, templates, consent handling, and reporting, and a marketing message shall never be routed through a transactional path. | Category separation is what makes opt-out meaningful. | MUST | 7 |
| FR-097 | Quiet hours enforcement | Non-critical messages shall not be sent inside quiet hours, defaulting to 20.00–08.00 outlet local time; messages due inside the window shall be deferred to the next permitted window, not dropped and not sent anyway. | Customers own their attention. | MUST | 7 |
| FR-098 | Message deduplication | The same notification for the same recipient, event, order, and intended send window shall be sent exactly once, including across retries, queue replays, and scheduler restarts. | Duplicate messages are the messaging equivalent of duplicate payments. | MUST | 7 |
| FR-099 | Messaging decoupled from order state | A messaging failure shall never cancel, block, or alter an order's state; failures shall be visible and retried under a bounded policy. | Notification is a side effect, never a dependency. | MUST | 7 |

### 15.12 Pickup and Delivery — Step 8

Detailed delivery behaviour belongs to the `DEL` series.

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-100 | Pickup request | A pickup shall be requestable by the customer through the portal or the Customer Android app, or by staff on the customer's behalf. | Both origins are normal. | MUST | 8 |
| FR-101 | Scheduling and time windows | A pickup or delivery shall be scheduled with a time window presented to the customer, and shall never present a fictitious exact arrival minute. | The product promises what it can keep. | MUST | 8 |
| FR-102 | Zone matching | A pickup or delivery request shall be matched against the outlet's defined service zones, and an address outside every zone shall be refused plainly with an alternative offered. | Coverage is stated, never improvised. | MUST | 8 |
| FR-103 | Route ordering without optimisation claims | Jobs shall be presented to a courier as an ordered list described as a suggestion — *usulan rute* — and the product shall never claim an optimal route or a guaranteed arrival time. | Direct application of the honesty rule and non-goal 7. | MUST | 8 |
| FR-104 | Mandatory proof of custody transfer | Every custody transfer, at pickup and at delivery, shall require recorded proof by at least one configured mechanism: OTP, photo, signature, or recipient name. | A parcel never silently changes hands. | MUST | 8 |
| FR-105 | Private proof artefacts | Proof photographs and signatures shall be stored in private object storage under tenant-scoped, unguessable keys, and served only through signed expiring URLs, never on the public portal. | Proof artefacts may show a customer's home and belongings. | MUST | 8 |
| FR-106 | Failed delivery as an outcome | A failed delivery shall be recorded with a reason, the laundry shall return to the outlet, and the order shall return to a defined status. | Failure is a first-class outcome, not an error state. | MUST | 8 |
| FR-107 | Offline courier operation | Proof capture and cash recording shall work with no network connection, queue persistently with a stable `client_reference`, and synchronise without creating duplicates. | Couriers lose signal as a matter of course. | MUST | 8 |

### 15.13 Courier Assignment and Settlement — Step 8

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-108 | Courier assignment | A specific job shall be assignable to a specific internal courier or to an external local courier. | Accountability requires a named courier per job. | MUST | 8 |
| FR-109 | External courier guest job link | An external local courier shall receive a guest job link that uses a high-entropy token stored hashed, is not the order number and not derivable from it, expires, is revocable, is tenant-scoped, and grants exactly one job with no access to customer history, other orders, pricing, or any other tenant data. | The external rider is not a platform user and must never become one by accident. | MUST | 8 |
| FR-110 | Cash collection at the door | Cash collected on delivery shall be recorded as a financial transaction in integer Rupiah, idempotent, never deletable, and correctable only by reversal or adjustment. | Courier cash inherits every financial rule. | MUST | 8 |
| FR-111 | Courier cash reconciliation | Cash collected shall be tracked per courier per shift or route from collection to handover, with expected compared explicitly against actual and any variance recorded and acknowledged rather than absorbed. | Hidden variance is worse than visible variance. | MUST | 8 |

### 15.14 Unclaimed Laundry Recovery — Step 9

Detailed ladder behaviour belongs to the `UCL` series.

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-112 | Aging computation | Order aging shall be computed from the first `READY_FOR_PICKUP` timestamp against outlet local time and Asia/Jakarta business-day semantics, and shall never restart. | The canonical aging rule. | MUST | 9 |
| FR-113 | Reminder ladder | The system shall implement exactly the canonical ladder: H+1 friendly reminder; H+3 second reminder; H+7 priority reminder **and** an assignable follow-up task; H+14 escalation to the outlet manager or the owner. | Adding, removing, or renumbering a stage requires a decision record. | MUST | 9 |
| FR-114 | One firing per stage | Each ladder stage shall fire exactly once per order, deduplicated across retries, queue replays, and scheduler restarts. | A reminder sent twice damages trust. | MUST | 9 |
| FR-115 | Follow-up task | The H+7 follow-up task shall be a real, assignable, closable task with a named owner, not a flag on a report. | A flag nobody owns changes nothing. | MUST | 9 |
| FR-116 | Unclaimed dashboard fields | The unclaimed-laundry dashboard shall present at minimum: order count, customer count, held invoices, unpaid balance, order age, outlet, last reminder, follow-up officer, and reason not collected. | These nine fields are a canonical minimum, not a maximum. | MUST | 9 |
| FR-117 | Recovery actions without disposal | Recovery actions shall be limited to reminding, escalating, offering delivery, and recording the reason not collected; the system shall provide no capability, configuration, flag, or scheduled job that discards, sells, auctions, donates, or transfers ownership of a customer's laundry. | Absolute prohibition; disposal is a legal matter between a business and its customer. | MUST | 9 |

### 15.15 Customer application, loyalty, and feedback — Step 11

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| FR-118 | Customer application scope | The Aish Laundry Customer Android application shall provide phone and OTP login, active orders, order history, tracking, pickup request, saved addresses, invoices, loyalty, feedback, and notifications. | The canonical customer surface scope. | SHOULD | 11 |
| FR-119 | Portal parity guarantee | Any capability a customer genuinely needs in order to follow their laundry shall remain reachable from the Portal Tracking Publik without installing the application. | The application enhances and never replaces the portal. | MUST | 11 |
| FR-120 | Loyalty and feedback | The system shall support tenant-scoped loyalty and customer feedback capture, with any loyalty value expressed in integer Rupiah where it represents money. | Loyalty is a retention tool, and any monetary component is money. | COULD | 11 |

---

## 16. Non-functional requirements

Non-functional requirements are defined in the **`NFR` series**, owned by
`docs/quality/NON_FUNCTIONAL_REQUIREMENTS.md`. This document does not define `NFR` identifiers.

The canonical constraints those requirements must express
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §19, §20):

- The baseline device is a **low-end to mid-range Android phone on a congested mobile network** — the
  normal case, not a degraded case.
- The **Portal Tracking Publik is the most performance-critical surface** and must load fast on a cold
  cache. This is why it is permitted a lighter stack than Flutter.
- The **kasir order-intake path is the most latency-critical internal surface**.
- **No screen loads an unbounded result set.** Lists are paginated, indexed, and bounded.
- **Every business query is tenant-scoped and index-supported.** A tenant-scoped query without a
  supporting index is a defect, not a tuning opportunity.
- Images are compressed on device before upload, served resized, and never loaded at full resolution in a
  list.
- Background work — notification sending, report generation, reminder evaluation — belongs in queues and
  never blocks a user request.
- Observability: structured logging with a stable schema; correlation identifiers flowing through queues;
  tenant context in telemetry as an identifier and **never as personal data**; separate append-only
  financial and security audit trails; alerting on symptoms customers feel.

**Numeric performance budgets are deliberately not invented here.** They are set in **Step 13**, measured
against real devices, and recorded with evidence ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §19.3).

---

## 17. Security requirements

Security requirements are defined in the **`SEC` series**, owned by
`docs/security/SECURITY_REQUIREMENTS.md`. This document does not define `SEC` identifiers.

The canonical baseline those requirements must express
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §15):

- Least privilege everywhere; **server-side authorisation on every request**; client-side checks are
  user experience, never security.
- Secure password hashing with a modern memory-hard algorithm and per-password salt; **tokens stored
  hashed, never in plaintext**; Android secure storage for device credentials.
- **No secrets in the repository**, which is PUBLIC and therefore makes any committed secret compromised
  at the moment it is pushed. Rotation, not deletion, is the first response.
- Uploads validated for type, size, and content, not merely extension; **private files served via signed
  expiring URLs**; object storage never publicly readable or listable for tenant data; object keys
  tenant-scoped and unguessable.
- Rate limiting and brute-force protection on authentication, OTP issuance, tracking-token lookup, and
  all public endpoints.
- Session revocation and device revocation, both effective immediately.
- **Support impersonation is time-bound and audited. Platform support has no silent tenant access.**
- Logs never contain passwords, OTPs, tokens, or credentials; redaction is enforced at the logging
  boundary.
- Encrypted backups with a **tested**, not assumed, restore (Step 13).

**Hard gate:** cross-tenant data exposure is an automatic **NO-GO**
([DEC-0012](../decisions/DEC-0012-tenant-isolation-and-financial-integrity-hard-gate.md)).

---

## 18. Privacy requirements

Aish Laundry App holds customer names, phone numbers, addresses, order histories, payment records,
delivery proofs including photographs and signatures, and photographs of customers' garments. All of it
is personal data ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §17.1).

Canonical privacy rules that constrain every requirement in §15:

1. **Phone numbers and addresses are masked per context** (FR-025, FR-026).
2. **The tracking portal never shows a full address** (FR-090).
3. **Laundry photographs are private data** — stored privately, served only through signed URLs, never
   shown on the public portal, never used for marketing (FR-105).
4. **Tenant data is not used to train AI models without explicit consent.** Consent is specific,
   informed, recorded, and revocable. Absence of a refusal is not consent.
5. **Platform support has no silent tenant access.**
6. **Logs never contain passwords, OTPs, tokens, or credentials.**
7. **Backups are encrypted.**
8. **Tenant data remains exportable per policy when a subscription lapses** (SUB-014). A lapsed
   subscription restricts features; it never holds a tenant's business records hostage.
9. Collect the minimum personal data needed to run a laundry operation. Exports carry the same access
   rules as the underlying records. Personal data in error reports, analytics, or crash traces is
   redacted at source.

**Public-repository constraint.** Every example datum in this repository is fictional and recognisably
so. No real phone number, name, address, credential, token, or customer record appears anywhere
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §15.8).

---

## 19. Multi-tenancy requirements

Multi-tenancy requirements are defined in the **`TEN` series**, owned by
`docs/security/TENANT_ISOLATION_REQUIREMENTS.md`. This document does not define `TEN` identifiers.

The canonical hierarchy is:

```
User Account
    └── Membership
            └── Tenant / Organization
                    └── Laundry Brand
                            └── Outlet
```

The thirteen canonical hard rules ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §4.2), each of which the
`TEN` series must enforce and which FR-011 … FR-020 realise structurally:

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

Design consequences that bind §15 and §25: isolation is enforced at the data-access layer so a missing
scope yields nothing rather than another tenant's rows; caches, queues, search indexes, exports, report
files, uploaded files, and object-storage keys are all tenant-scoped; local device storage is separated
per tenant **and** per user; background jobs carry explicit tenant context and never infer it from the
last request; platform administration is a distinct audited path, never a relaxation of tenant scoping.

---

## 20. Financial requirements

Financial integrity requirements are defined in the **`FIN` series**, owned by
`docs/quality/FINANCIAL_INTEGRITY_REQUIREMENTS.md`. This document does not define `FIN` identifiers.

The canonical rules, realised in FR-037, FR-038, FR-039, FR-061 … FR-070, FR-110, FR-111
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §16):

1. **Money is stored as integer Rupiah.**
2. **Floating point is forbidden for financial transactions** — no `float`, no `double`, no
   binary-floating-point arithmetic anywhere in a money path, including display formatting that
   round-trips through a float.
3. **Payments are idempotent.**
4. **Gateway callbacks are verified server-side.**
5. **An order is never marked paid on a client claim.**
6. **Refund and void require an explicit permission and a recorded reason.**
7. **Financial transactions are never deleted through ordinary UI.**
8. **Corrections happen via reversal or adjustment entries.**
9. **Historical order prices are immune to price-list changes.**
10. **Shift closing compares expected cash against actual cash**, records the variance, and requires a
    reason beyond a configured threshold.
11. **Courier cash is reconciled** from collection to handover.
12. **Any financial integrity failure is an automatic NO-GO.**

---

## 21. Offline requirements

Offline and synchronisation requirements are defined in the **`OFF` series**, owned by
`docs/quality/OFFLINE_REQUIREMENTS.md`. This document does not define `OFF` identifiers.

Offline capability applies primarily to **Aish Laundry Ops Android**. The nine canonical rules
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §13.1), realised in FR-059, FR-079, FR-107:

1. **`client_reference` on every important operation**, generated once and reused on every retry.
2. **Persistent queue** surviving application restart and device reboot.
3. **Exponential backoff retry**, spaced and bounded.
4. **The financial queue is never casually deleted** — removing a queued financial operation is an
   explicit, permissioned, audited action.
5. **Payment conflicts are never silently overwritten** — a conflict surfaces to a human with both
   values.
6. **The server is the final source of truth.**
7. **Local data is separated per tenant and per user.**
8. **Sensitive local data is encrypted** using Android secure storage.
9. **A duplicate order or duplicate payment caused by a retry is unacceptable** and is an automatic
   NO-GO.

**Honestly excluded from offline capability** (§13.3): payment gateway confirmation, OTP verification,
and public tracking. An offline device may record a payment **intent**, never a confirmed gateway
payment. Offline and sync state are visible to the user at all times; a kasir must never believe a
payment was recorded when it is still sitting in a queue.

---

## 22. Tracking requirements

Tracking portal requirements are defined in the **`TRK` series**, owned by
`docs/security/TRACKING_REQUIREMENTS.md`. This document does not define `TRK` identifiers.

The nine canonical, non-negotiable security rules for the portal
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §9.2), realised in FR-086 … FR-092:

1. The token is **high-entropy**, from a cryptographically secure random source.
2. The token is **stored hashed**; the plaintext exists only in the link.
3. The token is **not the order number**.
4. Tokens are **revocable**.
5. Tokens are **expiring**.
6. The portal is served with **`noindex`**.
7. **Personal data is masked.**
8. The portal **never shows the full address**.
9. **Sensitive actions require OTP.**

The portal is the customer's default tracking experience
([DEC-0006](../decisions/DEC-0006-public-tracking-without-app-installation.md)) and is never degraded
into an app-install prompt
([DEC-0014](../decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md)).

---

## 23. Pickup and delivery requirements

Pickup and delivery requirements are defined in the **`DEL` series**, owned by
`docs/domain/DELIVERY_REQUIREMENTS.md`. This document does not define `DEL` identifiers.

The canonical rules ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §10.2), realised in
FR-100 … FR-111:

1. A delivery is never marked complete without proof appropriate to the tenant's configured policy.
2. Cash collected by a courier is a financial transaction subject to §20 in full.
3. An external ojek link grants access to **one job**, expires, and never exposes the customer's full
   address history, other orders, or any other tenant data.
4. **Route suggestions are labelled as suggestions.** *Usulan rute*, never *rute optimal*. No guaranteed
   arrival time. No claim of optimisation the product does not compute.
5. A failed delivery is a first-class outcome with a recorded reason.
6. Time windows are commitments shown to the customer; missing them is measurable.

Courier UX is deliberately simple: large targets, few decisions, one job at a time, one-handed, usable
outdoors on a cheap phone.

---

## 24. Unclaimed laundry requirements

Unclaimed-laundry requirements are defined in the **`UCL` series**, owned by
`docs/domain/UNCLAIMED_LAUNDRY_REQUIREMENTS.md`. This document does not define `UCL` identifiers.

Realised in FR-112 … FR-117. The canonical ladder:

| Age | Action |
| --- | --- |
| H+1 | Friendly reminder to the customer |
| H+3 | Second reminder |
| H+7 | Priority reminder **and** a follow-up task assigned to a staff member |
| H+14 | Escalation to the outlet manager or the owner |

**Aging starts when an order FIRST reaches status `READY_FOR_PICKUP`** and never restarts. The nine
minimum dashboard fields are listed in FR-116.

**Absolute prohibition.** The product never automatically discards, sells, auctions, donates, or
transfers ownership of laundry. No configuration, no plan, no escalation level, no automation, no flag,
and no TODO. Disposal is a legal and ethical matter between a business and its customer; the software's
job ends at surfacing the problem, reminding the customer, escalating to a human, and recording the
reason it was never collected ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §11.4).

---

## 25. Reporting requirements

**20 requirements, RPT-001 … RPT-020.** Every one has status **NOT IMPLEMENTED**.

All reporting figures are tenant-scoped, derived from the same system of record that operations use,
expressed in integer Rupiah where they represent money, and read from the authoritative financial records
rather than recomputed independently.

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| RPT-001 | Single reporting truth | Every reported figure shall be derived from the same system of record that operations use; no separate reporting truth shall exist. | Divergent reporting destroys trust in every number. | MUST | 10 |
| RPT-002 | Estimates labelled | Any figure that is an estimate shall be labelled an estimate. | An unlabelled estimate is a false claim. | MUST | 10 |
| RPT-003 | Unavailable is not zero | A figure that cannot be computed for a period shall be shown as unavailable, never as zero. | Zero and unknown are different facts. | MUST | 10 |
| RPT-004 | Drill-down | A user with permission shall be able to drill from any aggregate to its underlying records within the same tenant. | Verification beats trust. | MUST | 10 |
| RPT-005 | Revenue report | The system shall report revenue by day, outlet, brand, and service type. | The owner's first question every morning. | MUST | 10 |
| RPT-006 | Order volume report | The system shall report orders taken, in production, ready, delivered, and cancelled. | Operational throughput at a glance. | MUST | 10 |
| RPT-007 | Shift closing report | The system shall report expected cash against actual cash per shift, with the variance and its recorded reason. | Variance must be visible, never absorbed. | MUST | 10 |
| RPT-008 | Courier cash report | The system shall report courier cash outstanding and reconciliation lag per courier and per shift or route. | Money in transit is the least visible money. | MUST | 10 |
| RPT-009 | Receivables report | The system shall report unpaid balance and held invoices, read from the authoritative financial records. | Receivables are the cash the business has already earned. | MUST | 10 |
| RPT-010 | Payment channel reconciliation | The system shall report payments by channel — cash, transfer, gateway, courier cash — reconciled against orders. | Finance closes the period across channels, not within one. | MUST | 10 |
| RPT-011 | Unclaimed aging buckets | The system shall report unclaimed laundry in aging buckets anchored to the first `READY_FOR_PICKUP` timestamp. | The core unclaimed-laundry view. | MUST | 10 |
| RPT-012 | Oldest unclaimed orders | The system shall report the oldest unclaimed orders with their outlet, age, and unpaid balance. | Attention goes to the worst cases first. | MUST | 10 |
| RPT-013 | Escalations pending | The system shall report H+14 escalations that remain unresolved, with the accountable person named. | An escalation nobody owns is not an escalation. | MUST | 10 |
| RPT-014 | Recovery outcome report | The system shall report recovery outcomes after H+1, H+3, and H+7 reminders, and the distribution of recorded reasons not collected. | Whether the ladder actually works. | MUST | 10 |
| RPT-015 | Time-window adherence report | The system shall report pickup and delivery time-window adherence, without any claim of route optimisation or guaranteed arrival. | The delivery promise must be measurable and honest. | MUST | 10 |
| RPT-016 | Rework rate report | The system shall report rework rate by outlet, stage, and defect reason. | Production quality signal. | MUST | 10 |
| RPT-017 | Capacity pressure report | The system shall report capacity pressure per outlet against the outlet's declared capacity. | Owners need to see saturation before customers do. | SHOULD | 10 |
| RPT-018 | Owner portfolio consolidation | The system shall consolidate reporting across brands and outlets **within a single tenant**, and shall never widen the query surface across tenants to achieve consolidation. | Hard rule 13 applies without exception. | MUST | 10 |
| RPT-019 | Report export | The system shall allow reports to be exported, with exports carrying the same access rules, tenant scoping, and masking as the underlying records. | An export is not an escape hatch from access control. | MUST | 10 |
| RPT-020 | Messaging cost report | The system shall report third-party messaging volume and cost per outlet and per tenant, separately from the subscription plan. | Provider costs are disclosed, not buried. | MUST | 12 |

---

## 26. Subscription requirements

**20 requirements, SUB-001 … SUB-020.** Every one has status **NOT IMPLEMENTED**.

### 26.1 Canonical pricing — reproduced exactly

```
Trial: 14 hari gratis
Starter: Rp79.000/bulan — 1 outlet, 5 staff, hingga 1.000 order/bulan fair-use
Growth: Rp199.000/bulan — hingga 3 outlet, 20 staff, hingga 5.000 order/bulan
Scale: Rp399.000/bulan — hingga 10 outlet, 75 staff, hingga 20.000 order/bulan
Enterprise: mulai Rp999.000/bulan
Annual: Starter Rp790.000/tahun; Growth Rp1.990.000/tahun; Scale Rp3.990.000/tahun
```

These figures are canonical and locked
([DEC-0009](../decisions/DEC-0009-initial-commercial-pricing.md)). They are never rounded, reformatted,
converted, simplified, translated, or restated from memory. A pricing change requires a decision record
and a Master Source version bump.

### 26.2 Requirements

| ID | Title | Statement | Rationale | Pri | Step |
| --- | --- | --- | --- | --- | --- |
| SUB-001 | Tenant-boundary subscription | Subscription and billing shall operate at the tenant boundary — not per user, not per outlet. | Hard rule 6 of the tenancy model. | MUST | 12 |
| SUB-002 | Trial | A new tenant shall be able to start a trial of 14 hari gratis. | The canonical trial offer. | MUST | 12 |
| SUB-003 | Plan catalogue | The system shall offer exactly the canonical plans Starter, Growth, Scale, and Enterprise, with the canonical monthly and annual prices. | Pricing is owner territory and is locked. | MUST | 12 |
| SUB-004 | Plan limits | Plan limits for outlets, staff, and orders per month shall match the canonical figures and be enforced server-side, tenant-scoped. | Client-enforced limits are not limits. | MUST | 12 |
| SUB-005 | Fair-use semantics | The Starter order limit shall be presented and enforced as **fair-use**, not as a hard cutoff, unless a decision record changes that. | The canonical wording is fair-use. | MUST | 12 |
| SUB-006 | Fair-use handling | Exceeding a fair-use ceiling shall trigger an honest notice and a plan recommendation, and shall not silently degrade the service, delete data, or stop a laundry operating mid-shift. | A laundry must never be cut off mid-shift. | MUST | 12 |
| SUB-007 | Usage metering | The system shall meter outlets, staff, and orders per month per tenant and present the usage honestly to the tenant. | Tenants must see what they are being measured on. | MUST | 12 |
| SUB-008 | Upgrade | A tenant shall be able to upgrade plan, with the effect on limits stated before confirmation. | Upgrades must be deliberate. | MUST | 12 |
| SUB-009 | Downgrade | A tenant shall be able to downgrade plan, with the consequences for outlets, staff, and features stated before confirmation. | A surprise downgrade breaks an operating business. | MUST | 12 |
| SUB-010 | Annual billing | The system shall support annual billing at exactly the canonical annual prices. | Locked commercial figures. | MUST | 12 |
| SUB-011 | Integer Rupiah billing | Every subscription amount shall be represented as integer Rupiah and shall follow every rule in §20. | Billing is money. | MUST | 12 |
| SUB-012 | No lifetime plan | The system shall provide no lifetime or perpetual cloud plan, and none shall be constructible as a custom Enterprise arrangement. | A one-off payment for perpetual service is a promise that cannot be kept honestly. | MUST | 12 |
| SUB-013 | No per-nota fee | The system shall charge no per-nota or per-receipt fee on normal plans. | Charging per transaction punishes success. | MUST | 12 |
| SUB-014 | Export on lapse | A tenant whose subscription lapses shall retain the ability to export its own business data per policy. | Data belongs to the tenant; there is no hostage-taking. | MUST | 12 |
| SUB-015 | Lapse and grace behaviour | Downgrade, lapse, and grace behaviour shall be defined and presented to the tenant before billing ships, and shall honour SUB-014. | Undefined lapse behaviour becomes an incident. | MUST | 12 |
| SUB-016 | Tenant lifecycle administration | A platform administrator shall be able to create, suspend, and restore a tenant through an audited platform surface. | Platform operation must not require touching tenant data. | MUST | 12 |
| SUB-017 | Audited support impersonation | Support impersonation shall be explicit, time-bound, reason-recorded, unmistakably indicated in the interface, and fully audited from start to end. | Platform support has no silent tenant access. | MUST | 12 |
| SUB-018 | Security baseline on every plan | Authentication, authorisation, secure storage, rate limiting, audit logging, tenant isolation, and encrypted backup shall be available on every plan including Starter. | Security is never an upsell and isolation is never an add-on. | MUST | 12 |
| SUB-019 | Separate messaging fees | WhatsApp provider fees shall be billed separately from the subscription plan and shown transparently. | Third-party costs are disclosed, not buried. | MUST | 12 |
| SUB-020 | No unlimited messaging claim | No surface, document, or marketing copy shall claim unlimited WhatsApp or unlimited messaging of any kind. | Message volume has a real per-message cost and the product says so. | MUST | 12 |

---

## 27. MVP scope

**The MVP focuses on laundry operations**
([DEC-0015](../decisions/DEC-0015-mvp-focuses-on-laundry-operations.md)). It is the smallest product that
lets a single laundry tenant run a real working day end to end and lets its customers track their laundry
without installing anything. Full detail: [`MVP_SCOPE.md`](MVP_SCOPE.md).

| Capability | Canonical Step | Requirements |
| --- | --- | --- |
| Authentication with phone + OTP | Step 3 | FR-001 … FR-010 |
| Tenancy, brands, outlets, memberships, tenant switcher | Step 3 | FR-011 … FR-020 |
| RBAC with server-side authorisation | Step 3 | FR-007 … FR-010 |
| Customers, services, price lists, outlet master data | Step 4 | FR-021 … FR-047 |
| POS order intake, nota, payment, refund/void with audit | Step 5 | FR-048 … FR-070 |
| Production stages, status lifecycle including `READY_FOR_PICKUP` | Step 6 | FR-071 … FR-085 |
| Public tracking portal with secure tokens | Step 7 | FR-086 … FR-092 |
| WhatsApp notification with provider abstraction and fallback | Step 7 | FR-093 … FR-099 |
| Pickup and delivery with proof and courier cash | Step 8 | FR-100 … FR-111 |
| Unclaimed laundry H+1/H+3/H+7/H+14 and its dashboard | Step 9 | FR-112 … FR-117 |
| Shift closing, reconciliation, core reports, owner portfolio | Step 10 | RPT-001 … RPT-019 |

**After the MVP:** Customer Android application, loyalty, feedback, invoices (Step 11, FR-118 … FR-120);
subscription, plan limits, platform administration (Step 12, SUB-001 … SUB-020, RPT-020); security
hardening, performance budgets, backup and recovery (Step 13); pilot and commercial launch (Step 14).

**MVP quality bar.** The MVP is small in scope but not lax in quality. Tenant isolation and financial
integrity are hard gates from Step 3 onward. There is no "we will secure it after the pilot"
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §22.5).

---

## 28. Release constraints

1. **The roadmap is locked.** Steps 0–14 are canonical. Step numbers are never reused, renumbered,
   swapped, merged, or split without a decision record.
2. **No step leakage.** Work belonging to a later Step is never performed early; work belonging to an
   earlier Step is never quietly deferred.
3. **Step 3 is the first Step permitted to create a runtime.** Before it, there is no Flutter workspace,
   no Laravel application, no schema, no migration, no API, no UI, no deployment.
4. **Step 1 creates documentation only** — no code, no schema, no migration, no manifest.
5. **Application CI is NOT APPLICABLE** until Step 3, when the first runtime exists. Claiming an
   application build or test pipeline before then is a false claim.
6. **Evidence is bound to an exact commit SHA** ([DEC-0013](../decisions/DEC-0013-exact-sha-evidence-before-go.md)).
   Evidence produced at one SHA does not carry over to another.
7. **All change reaches `main` through a pull request.** History on shared branches is never rewritten;
   tags are annotated and immutable.
8. **`GO` is conferred by the repository owner**, never self-declared by an agent.
9. **Two mandatory test suites** from the Step that introduces the capability: tenant isolation and
   financial integrity. A failure in either is an automatic NO-GO.
10. **The design system (Step 2) precedes any product screen.** No screen is built before its foundation
    exists.

---

## 29. Dependencies

| Dependency | Nature | Constraint | First needed |
| --- | --- | --- | --- |
| Laravel modular monolith | Locked backend framework | Changing it requires a new ADR | Step 3 |
| Flutter + Dart | Locked client framework | Changing it requires a new ADR | Step 3 |
| PostgreSQL | System of record | Every business table carries `tenant_id` | Step 3 |
| Redis | Cache, queue, locks, rate limiting | **Never** the system of record for business data; every cache key carries a tenant dimension | Step 3 |
| S3-compatible object storage | Private files, proofs, exports | Never publicly readable or listable for tenant data; signed expiring URLs only | Step 6 |
| Official WhatsApp Business API provider | Automated messaging | Behind a provider abstraction; fees billed separately and disclosed | Step 7 |
| Payment gateway | Digital payments | Callbacks verified server-side; confirmation requires the network | Step 5 |
| SMS or WhatsApp OTP delivery | Authentication | Rate limited; OTP values never logged | Step 3 |
| Real laundry pilot tenants | Metric baselines and UAT | Baselines are set from real data only | Step 14 |

**No third-party service, dependency, SDK, or paid provider is introduced without owner approval.** None
is introduced in Step 1.

---

## 30. Risks

| ID | Risk | Impact | Mitigation | Owner-decision needed |
| --- | --- | --- | --- | --- |
| R-1 | A cross-tenant leak through a forgotten query scope, an untenanted cache key, a report endpoint, or a file URL | Business-ending for the affected tenant; automatic NO-GO | Isolation enforced at the data-access layer so a missing scope yields nothing; mandatory negative test suite from Step 3 | No |
| R-2 | A duplicate payment created by an offline retry | Financial integrity failure; automatic NO-GO | `client_reference` idempotency as a server contract; serialised money operations; mandatory financial test suite from Step 5 | No |
| R-3 | The tracking portal being slow on a low-end browser, defeating the main differentiator | The product's most visible promise fails on first contact | Portal permitted a lighter stack than Flutter; performance budgets measured on real devices in Step 13 | Stack choice needs an ADR in the Step that builds it |
| R-4 | Messaging costs surprising a tenant, or a provider policy change blocking automated sends | Trust and unit-economics damage | Provider abstraction; transparent cost reporting (RPT-020, SUB-019); manual deep-link fallback that is never sold as automation | No |
| R-5 | Duplicate or quiet-hours reminders damaging customer trust at scale | Compliance and trust failure | Deduplication keyed on recipient, event, order, and intended window; quiet-hours deferral; opt-out evaluated at send time | No |
| R-6 | Proof artefacts leaking through an unsigned URL or a predictable object key | Automatic NO-GO under the privacy rules | Private buckets, tenant-scoped unguessable keys, signed expiring URLs only | No |
| R-7 | A guest job link being over-scoped, guessable, or non-expiring | Highest-severity security defect | High-entropy hashed token, single job, expiring, revocable, tenant-scoped | No |
| R-8 | Pricing text drifting from the canonical figures on a public repository | Commercial risk, not a typo | Single canonical price source (FR-040); pricing changes require a decision record | Any change is owner territory |
| R-9 | Scope leaking between locked Steps | Roadmap integrity failure | Step scope guards; Definition of Done item 2 | No |
| R-10 | Governance operating in **single-maintainer** mode with independent human approval **ABSENT** | Reduced review assurance | Active ruleset, exact-SHA CI, deterministic validators, recorded internal re-verification — stated plainly, never presented as independent peer review | Already recorded in DEC-0016 |
| R-11 | Pressure to add an automatic disposal feature for unclaimed laundry | Would breach an absolute prohibition | Refuse outright and escalate; not implementable behind a flag, a prototype, or a TODO | Refusal is canonical |

---

## 31. Assumptions

Full register: [`ASSUMPTIONS_AND_OPEN_QUESTIONS.md`](ASSUMPTIONS_AND_OPEN_QUESTIONS.md). Assumptions
recorded there are explicitly **not** decisions and must not be treated as such. Summary of the
assumptions this document relies on:

- The baseline device and network are as stated in §5 and will be validated in Step 14.
- WhatsApp remains the channel Indonesian laundry customers actually read.
- Tenants are willing to configure proof requirements rather than have them imposed uniformly.
- Bahasa Indonesia is sufficient as a single UI language for the current market; no multilingual
  requirement is assumed.
- Existing repository facts hold: remote repository `aish_laundry_app`, default branch `main`, local
  monorepo root `aish_laundry`, visibility **PUBLIC**.

---

## 32. Open questions

Recorded in full in [`ASSUMPTIONS_AND_OPEN_QUESTIONS.md`](ASSUMPTIONS_AND_OPEN_QUESTIONS.md) as
`OQ-001` onward. Each is an **owner decision**, and no requirement in this document assumes an answer to
any of them. Inventing an answer to close a gap is forbidden
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §27.4 rule 14).

---

## 33. Acceptance criteria linkage

Acceptance criteria for the MVP scope are recorded in [`MVP_SCOPE.md`](MVP_SCOPE.md) §5, identified as
`AC-###` and mapped to the requirements they verify. The linkage rules:

1. Every `MUST` requirement in the MVP scope has at least one acceptance criterion.
2. An acceptance criterion is written as an observable, verifiable statement — something a person or a
   test can check — never as an opinion.
3. An acceptance criterion is satisfied only by **exact-SHA evidence**: the full forty-character commit
   SHA, the exact command, the captured output, an Asia/Jakarta timestamp, and the environment
   ([DEC-0013](../decisions/DEC-0013-exact-sha-evidence-before-go.md)).
4. Tenant isolation and financial integrity criteria are proven by **negative tests** — an attempt that
   must fail — not by the absence of a reported failure.
5. **No acceptance criterion is satisfied today.** Every one is `NOT STARTED` for verification purposes,
   because there is nothing to verify.

---

## 34. Traceability

The full matrix — every FR, RPT, and SUB identifier mapped to a bounded context, a roadmap Step, and an
acceptance-criteria reference — is [`REQUIREMENT_TRACEABILITY.md`](REQUIREMENT_TRACEABILITY.md).

Counts defined by this document:

| Series | Range | Count |
| --- | --- | --- |
| FR | FR-001 … FR-120 | 120 |
| RPT | RPT-001 … RPT-020 | 20 |
| SUB | SUB-001 … SUB-020 | 20 |
| **Total** | | **160** |

Requirements in the `NFR`, `SEC`, `TEN`, `FIN`, `OFF`, `TRK`, `DEL`, `UCL`, and `NOT` series are defined
elsewhere in Step 1 and are referenced here by series name only.

---

## 35. Future roadmap relationship

| Step | Title | Relationship to this document |
| --- | --- | --- |
| Step 1 | Product Requirement and Domain Model | **Produces this document.** IN PROGRESS on this branch. |
| Step 2 | Design System and UX Foundation | Realises the UX constraints in §14.5 as `packages/design_system`. |
| Step 3 | Runtime, Authentication, Multi-Tenancy, and RBAC | Delivers FR-001 … FR-020. First runtime. Application CI stops being NOT APPLICABLE. |
| Step 4 | Laundry Master Data | Delivers FR-021 … FR-047. |
| Step 5 | POS, Order, and Payment Foundation | Delivers FR-048 … FR-070. |
| Step 6 | Production Operations | Delivers FR-071 … FR-085. |
| Step 7 | Customer Tracking and WhatsApp | Delivers FR-086 … FR-099. |
| Step 8 | Pickup and Delivery Operations | Delivers FR-100 … FR-111. |
| Step 9 | Unclaimed Laundry and Cashflow Recovery | Delivers FR-112 … FR-117. |
| Step 10 | Finance, Reports, and Owner Portfolio | Delivers RPT-001 … RPT-019. |
| Step 11 | Customer Android Experience | Delivers FR-118 … FR-120. |
| Step 12 | Subscription and Platform Administration | Delivers SUB-001 … SUB-020 and RPT-020. |
| Step 13 | Security, Performance, Backup, and Recovery | Sets the numeric performance budgets §16 deliberately leaves unset. |
| Step 14 | Pilot and Commercial Launch | Sets metric baselines and targets from real pilot data; moves UAT from NOT STARTED. |

Requirements are **not** renumbered when a Step slips. Identifiers are permanent. A requirement that is
superseded keeps its identifier and gains a supersession note, exactly as decision records do.

---

## Status statement

| Item | Status |
| --- | --- |
| This document | Version 1.0.0, Step 1, PLANNED until owner GO |
| Every FR, RPT, and SUB requirement | **NOT IMPLEMENTED** |
| Backend runtime | **ABSENT** |
| Flutter workspace | **ABSENT** |
| Deployment | **ABSENT** |
| Application CI | **NOT APPLICABLE** |
| UAT | **NOT STARTED** |
| Steps 2–14 | **PLANNED** |
| Step 1 | **IN PROGRESS** |
| Step 0 | **GO** |

Nothing in this document is a claim that any feature, test, build, deployment, CI run, or user acceptance
test exists.
