# Aish Laundry App — Use Case Catalog

**Document version: 1.0.0** · **Step: 1 — Product Requirement and Domain Model**
**Status of every use case described here: NOT IMPLEMENTED**

Canonical source: [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md). Subordinate to the Master Source.

Related: [`PERSONAS.md`](PERSONAS.md) · [`USER_JOURNEYS.md`](USER_JOURNEYS.md) ·
[`OPERATIONAL_JOURNEYS.md`](OPERATIONAL_JOURNEYS.md) ·
[`PRODUCT_REQUIREMENTS.md`](PRODUCT_REQUIREMENTS.md) ·
[`REQUIREMENT_TRACEABILITY.md`](REQUIREMENT_TRACEABILITY.md)

---

## 0. How to read this catalog

A use case is a named interaction between an actor and the system. It is narrower than a journey and
broader than a requirement.

| Column | Meaning |
| --- | --- |
| ID | `UC-###`, stable and never reused |
| Use case | The interaction, named as an action |
| Primary actor | Persona ID from [`PERSONAS.md`](PERSONAS.md) |
| Surface | Which of the four canonical platforms |
| Requirements | The FR / RPT / SUB identifiers that govern it |
| Step | Canonical roadmap Step that first delivers it |

Every use case in this catalog has implementation status **NOT IMPLEMENTED**.

Surface abbreviations: **Ops** = Aish Laundry Ops Android; **Console** = Aish Laundry Console Web;
**Portal** = Portal Tracking Publik; **CustApp** = Aish Laundry Customer Android.

### Actor legend

The Primary actor column uses persona identifiers. Their full names, defined in
[`PERSONAS.md`](PERSONAS.md), are:

| ID | Persona | ID | Persona |
| --- | --- | --- | --- |
| P-01 | Platform Super Admin | P-08 | Quality Control |
| P-02 | Platform Support | P-09 | Courier Internal |
| P-03 | Tenant Owner | P-10 | External Local Courier |
| P-04 | Tenant Admin | P-11 | Finance |
| P-05 | Outlet Manager | P-12 | Customer |
| P-06 | Cashier | P-13 | Corporate Customer Contact |
| P-07 | Production Operator | P-14 | Authorized Order Recipient |

An actor recorded as **System** is a scheduled or event-driven action with no human initiator; it is
still tenant-scoped and still audited. The **External Local Courier** never holds a membership account
and acts only through a scoped, expiring, revocable guest job link.

---

## 1. Identity, tenancy, and access — Step 3

| ID | Use case | Primary actor | Surface | Requirements | Step |
| --- | --- | --- | --- | --- | --- |
| UC-001 | Authenticate with phone number and OTP | P-06 | Ops, Console, CustApp | FR-001, FR-003 | 3 |
| UC-002 | Establish a session bound to a device | P-06 | Ops | FR-004 | 3 |
| UC-003 | Revoke a session | P-04 | Console | FR-005 | 3 |
| UC-004 | Revoke a single device | P-04 | Console | FR-006 | 3 |
| UC-005 | Switch active tenant | P-03 | Console, Ops | FR-016, FR-017 | 3 |
| UC-006 | Create a tenant | P-01 | Console | FR-011, SUB-016 | 3 |
| UC-007 | Create a brand under a tenant | P-04 | Console | FR-012 | 3 |
| UC-008 | Create an outlet under a brand | P-04 | Console | FR-013 | 3 |
| UC-009 | Invite a staff member and create a membership | P-04 | Console | FR-014, FR-018 | 3 |
| UC-010 | Grant and revoke role permissions | P-04 | Console | FR-008, FR-009 | 3 |
| UC-011 | Review the security audit trail | P-03 | Console | FR-010, FR-020 | 3 |

**Constraint on this cluster.** Every authorisation decision is evaluated server-side against a verified
Membership. A client-supplied tenant identifier is a hint that must be validated, never a grant
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §4.2 rules 9 and 10).

---

## 2. Master data — Step 4

| ID | Use case | Primary actor | Surface | Requirements | Step |
| --- | --- | --- | --- | --- | --- |
| UC-012 | Create a customer profile | P-06 | Ops | FR-021, FR-022 | 4 |
| UC-013 | Search for a customer | P-06 | Ops | FR-023 | 4 |
| UC-014 | Add a saved address to a customer | P-06 | Ops, CustApp | FR-024, FR-025 | 4 |
| UC-015 | Record or withdraw marketing consent | P-12 | Portal, CustApp | FR-027, FR-028 | 4 |
| UC-016 | View a customer's order history | P-06 | Ops | FR-029 | 4 |
| UC-017 | Record an internal note on a customer | P-06 | Ops | FR-030 | 4 |
| UC-018 | Define a kiloan service | P-04 | Console | FR-031 | 4 |
| UC-019 | Define a satuan service | P-04 | Console | FR-031 | 4 |
| UC-020 | Define a package | P-04 | Console | FR-032 | 4 |
| UC-021 | Define an add-on | P-04 | Console | FR-033 | 4 |
| UC-022 | Publish a price list version for a brand | P-04 | Console | FR-034, FR-035 | 4 |
| UC-023 | Configure outlet operating hours | P-04 | Console | FR-041 | 4 |
| UC-024 | Configure outlet capacity | P-04 | Console | FR-042 | 4 |
| UC-025 | Define a service zone | P-04 | Console | FR-043 | 4 |
| UC-026 | Define shifts for an outlet | P-04 | Console | FR-044 | 4 |
| UC-027 | Register a printer for nota output | P-04 | Console | FR-045 | 4 |
| UC-028 | Configure the proof policy for pickup and delivery | P-04 | Console | FR-046 | 4 |
| UC-029 | Configure quiet hours for an outlet | P-04 | Console | FR-047 | 4 |

**Constraint on this cluster.** Publishing a new price list version affects **future orders only**.
Historical orders and reprinted nota show the price captured at creation
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §16.4).

---

## 3. Order intake and payment — Step 5

| ID | Use case | Primary actor | Surface | Requirements | Step |
| --- | --- | --- | --- | --- | --- |
| UC-030 | Create a draft order | P-06 | Ops | FR-048, FR-049 | 5 |
| UC-031 | Add kiloan lines by weight | P-06 | Ops | FR-050, FR-031 | 5 |
| UC-032 | Add satuan lines by item | P-06 | Ops | FR-050, FR-031 | 5 |
| UC-033 | Apply a package or add-on | P-06 | Ops | FR-050, FR-032, FR-033 | 5 |
| UC-034 | Record a special handling instruction | P-06 | Ops | FR-055 | 5 |
| UC-035 | Compute the order total server-side | P-06 | Ops | FR-051, FR-037, FR-038 | 5 |
| UC-036 | Override a price with permission and reason | P-05 | Ops | FR-039 | 5 |
| UC-037 | Confirm an order into `RECEIVED` | P-06 | Ops | FR-048 | 5 |
| UC-038 | Generate and print a nota | P-06 | Ops | FR-052, FR-045 | 5 |
| UC-039 | Reprint a nota showing the originally captured prices | P-06 | Ops | FR-052, FR-036 | 5 |
| UC-040 | Take a cash payment | P-06 | Ops | FR-061, FR-062 | 5 |
| UC-041 | Take a bank transfer payment | P-06 | Ops | FR-061 | 5 |
| UC-042 | Take a payment-gateway payment | P-06 | Ops | FR-061, FR-063, FR-064 | 5 |
| UC-043 | Record a deposit and track the balance | P-06 | Ops | FR-056, FR-070 | 5 |
| UC-044 | Issue a refund with permission and reason | P-05 | Ops, Console | FR-065, FR-067 | 5 |
| UC-045 | Void a payment with permission and reason | P-05 | Ops, Console | FR-065, FR-067 | 5 |
| UC-046 | Post a reversal or adjustment entry | P-11 | Console | FR-067, FR-069 | 5 |
| UC-047 | Cancel an order with a recorded reason | P-05 | Ops | FR-058 | 5 |
| UC-048 | Search and list orders within tenant scope | P-06 | Ops, Console | FR-057 | 5 |
| UC-049 | Take an order while offline | P-06 | Ops | FR-059, `OFF` series | 5 |
| UC-050 | Inspect the offline queue and its sync state | P-06 | Ops | FR-059 | 5 |
| UC-051 | Resolve a payment conflict surfaced by sync | P-05 | Ops | FR-068, `OFF` series | 5 |
| UC-052 | Review an order's audit trail | P-05 | Console | FR-060 | 5 |
| UC-053 | Review the financial audit trail | P-11 | Console | FR-069 | 5 |

**Constraints on this cluster.** A payment retried with the same `client_reference` produces exactly one
payment (FR-062). An order is never marked paid on a client claim (FR-064). No interface path deletes a
financial transaction (FR-066). A duplicate payment produced by a retry is an automatic **NO-GO**
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §16.6).

---

## 4. Production and quality — Step 6

| ID | Use case | Primary actor | Surface | Requirements | Step |
| --- | --- | --- | --- | --- | --- |
| UC-054 | View the outlet production queue | P-07 | Ops | FR-057, FR-073 | 6 |
| UC-055 | Advance an order through a production stage | P-07 | Ops | FR-072, FR-073 | 6 |
| UC-056 | Process a batch through a stage | P-07 | Ops | FR-074 | 6 |
| UC-057 | Flag an item as damaged, missing, or special-handling | P-07 | Ops | FR-075 | 6 |
| UC-058 | Move an order to `ISSUE` with a reason | P-05 | Ops | FR-078 | 6 |
| UC-059 | Record production progress while offline | P-07 | Ops | FR-079 | 6 |
| UC-060 | Inspect an order at quality control | P-08 | Ops | FR-081 | 6 |
| UC-061 | Reject an order to `REWORK` with a defect reason | P-08 | Ops | FR-082, FR-084 | 6 |
| UC-062 | Attach defect evidence to a rework | P-08 | Ops | FR-083 | 6 |
| UC-063 | Pass an order to `READY_FOR_PICKUP` | P-08 | Ops | FR-076, FR-081 | 6 |
| UC-064 | Review an order's rework history | P-05 | Console | FR-084, FR-085 | 6 |

**Constraint on this cluster.** UC-063 records the **first** `READY_FOR_PICKUP` timestamp exactly once,
and that timestamp is immutable thereafter. A `REWORK` cycle followed by a second arrival at
`READY_FOR_PICKUP` does **not** alter it (FR-076, FR-077,
[`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §11.1).

---

## 5. Tracking and notification — Step 7

| ID | Use case | Primary actor | Surface | Requirements | Step |
| --- | --- | --- | --- | --- | --- |
| UC-065 | Issue a tracking token for an order | System | — | FR-086, FR-087 | 7 |
| UC-066 | Send a tracking link over WhatsApp | System | — | FR-093, FR-094 | 7 |
| UC-067 | Open the tracking portal from a link | P-12 | Portal | FR-089, FR-092 | 7 |
| UC-068 | Forward a tracking link to a family member | P-12 | Portal | FR-089, FR-090 | 7 |
| UC-069 | Request a change from the portal, gated by OTP | P-12 | Portal | FR-091 | 7 |
| UC-070 | Revoke a tracking link | P-05 | Ops, Console | FR-088 | 7 |
| UC-071 | Send a transactional order notification | System | — | FR-096, FR-098 | 7 |
| UC-072 | Defer a message due inside quiet hours | System | — | FR-097 | 7 |
| UC-073 | Send a marketing message subject to consent | P-04 | Console | FR-096, FR-027 | 7 |
| UC-074 | Send a message manually via the deep-link fallback | P-06 | Ops | FR-095 | 7 |
| UC-075 | Review notification delivery outcomes and failures | P-05 | Console | FR-099, RPT-020 | 7 |

**Constraints on this cluster.** A messaging failure never cancels, blocks, or alters an order (FR-099).
The same notification for the same recipient, event, order, and window is sent exactly once, including
across retries, queue replays, and scheduler restarts (FR-098). The portal never shows a full address
(FR-090).

---

## 6. Pickup, delivery, and courier settlement — Step 8

| ID | Use case | Primary actor | Surface | Requirements | Step |
| --- | --- | --- | --- | --- | --- |
| UC-076 | Request a pickup as a customer | P-12 | Portal, CustApp | FR-100 | 8 |
| UC-077 | Raise a pickup request on a customer's behalf | P-06 | Ops | FR-100 | 8 |
| UC-078 | Match an address to a service zone | System | — | FR-102, FR-043 | 8 |
| UC-079 | Schedule a pickup or delivery with a time window | P-05 | Ops, Console | FR-101 | 8 |
| UC-080 | Assign a job to an internal courier | P-05 | Ops | FR-108 | 8 |
| UC-081 | Issue a guest job link to an external local courier | P-05 | Ops | FR-109 | 8 |
| UC-082 | View the day's ordered job list as *usulan rute* | P-09 | Ops | FR-103 | 8 |
| UC-083 | Capture proof of pickup | P-09 | Ops | FR-104, FR-105 | 8 |
| UC-084 | Capture proof of delivery | P-09 | Ops | FR-104, FR-105 | 8 |
| UC-085 | Capture proof from a guest job link | P-10 | Browser, guest link | FR-104, FR-109 | 8 |
| UC-086 | Collect cash at the door | P-09 | Ops | FR-110 | 8 |
| UC-087 | Record a failed delivery with a reason | P-09 | Ops | FR-106 | 8 |
| UC-088 | Capture proof and cash while offline | P-09 | Ops | FR-107 | 8 |
| UC-089 | Hand over courier cash at end of route | P-09 | Ops | FR-111 | 8 |
| UC-090 | Reconcile courier cash and record a variance | P-05 | Ops, Console | FR-111, RPT-008 | 8 |
| UC-091 | Retrieve a proof artefact through a signed expiring URL | P-05 | Console | FR-105 | 8 |
| UC-092 | Revoke a guest job link | P-05 | Ops | FR-109 | 8 |

**Constraints on this cluster.** Every custody transfer requires recorded proof (FR-104). Route order is
**usulan rute** — a suggestion; no optimisation claim and no guaranteed arrival time (FR-103). The guest
job link grants exactly one job, expires, is revocable, and never permits traversal to another tenant
(FR-109). Courier cash inherits every financial rule (FR-110, FR-111).

---

## 7. Unclaimed laundry recovery — Step 9

| ID | Use case | Primary actor | Surface | Requirements | Step |
| --- | --- | --- | --- | --- | --- |
| UC-093 | Compute order aging from the first `READY_FOR_PICKUP` | System | — | FR-112 | 9 |
| UC-094 | Send the H+1 friendly reminder | System | — | FR-113, FR-114 | 9 |
| UC-095 | Send the H+3 second reminder | System | — | FR-113, FR-114 | 9 |
| UC-096 | Send the H+7 priority reminder and create the follow-up task | System | — | FR-113, FR-115 | 9 |
| UC-097 | Work and close an H+7 follow-up task | P-05 | Ops, Console | FR-115 | 9 |
| UC-098 | Escalate at H+14 to the outlet manager or owner | System | — | FR-113 | 9 |
| UC-099 | View the unclaimed-laundry dashboard | P-05 | Console, Ops | FR-116, RPT-011 | 9 |
| UC-100 | Record the reason an order was not collected | P-05 | Ops | FR-116, FR-117 | 9 |
| UC-101 | Offer delivery as a recovery action | P-05 | Ops | FR-117, FR-100 | 9 |
| UC-102 | Review recovery outcomes after each ladder stage | P-03 | Console | RPT-014 | 9 |

**Absolute constraint on this cluster.** There is **no** use case for discarding, selling, auctioning,
donating, or transferring ownership of a customer's laundry, at any age, for any unpaid balance, under
any configuration, behind any flag. This is an absolute prohibition
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §11.4, §23 non-goal 9) and its absence from this catalog is
deliberate, not an omission.

---

## 8. Finance, reporting, and owner portfolio — Step 10

| ID | Use case | Primary actor | Surface | Requirements | Step |
| --- | --- | --- | --- | --- | --- |
| UC-103 | Close a shift comparing expected against actual cash | P-05 | Ops | RPT-007 | 10 |
| UC-104 | Record and acknowledge a shift cash variance with a reason | P-05 | Ops | RPT-007 | 10 |
| UC-105 | Reconcile payments across channels | P-11 | Console | RPT-010 | 10 |
| UC-106 | View the revenue report by day, outlet, brand, and service type | P-03 | Console | RPT-005 | 10 |
| UC-107 | View the order volume report | P-03 | Console | RPT-006 | 10 |
| UC-108 | View the receivables report | P-11 | Console | RPT-009 | 10 |
| UC-109 | View the courier cash report | P-11 | Console | RPT-008 | 10 |
| UC-110 | View unclaimed aging buckets and oldest orders | P-03 | Console | RPT-011, RPT-012 | 10 |
| UC-111 | View pending H+14 escalations | P-03 | Console | RPT-013 | 10 |
| UC-112 | View time-window adherence | P-03 | Console | RPT-015 | 10 |
| UC-113 | View the rework rate report | P-03 | Console | RPT-016 | 10 |
| UC-114 | View capacity pressure per outlet | P-03 | Console | RPT-017 | 10 |
| UC-115 | Open the owner portfolio consolidated within one tenant | P-03 | Console | RPT-018 | 10 |
| UC-116 | Drill from an aggregate to its underlying records | P-03 | Console | RPT-004 | 10 |
| UC-117 | Export a report | P-11 | Console | RPT-019 | 10 |

**Constraints on this cluster.** Every figure derives from the same system of record operations use
(RPT-001). Estimates are labelled (RPT-002). A figure that cannot be computed is shown as unavailable,
never as zero (RPT-003). Consolidation happens **within one tenant**; the query surface is never widened
across tenants (RPT-018).

---

## 9. Customer application — Step 11

| ID | Use case | Primary actor | Surface | Requirements | Step |
| --- | --- | --- | --- | --- | --- |
| UC-118 | Install and log into the Customer Android application | P-12 | CustApp | FR-118, FR-001 | 11 |
| UC-119 | View active orders and order history in the application | P-12 | CustApp | FR-118 | 11 |
| UC-120 | View invoices in the application | P-12 | CustApp | FR-118 | 11 |
| UC-121 | Submit feedback | P-12 | CustApp | FR-120 | 11 |
| UC-122 | View loyalty state | P-12 | CustApp | FR-120 | 11 |
| UC-123 | Verify portal parity for every tracking capability | P-04 | Portal, CustApp | FR-119 | 11 |

**Constraint on this cluster.** UC-123 exists because the application must be verified as an
**enhancement**, never a replacement. Any capability a customer genuinely needs in order to follow their
laundry must remain reachable from the Portal Tracking Publik without installing anything
([DEC-0014](../decisions/DEC-0014-customer-android-does-not-replace-public-tracking.md)).

---

## 10. Subscription and platform administration — Step 12

| ID | Use case | Primary actor | Surface | Requirements | Step |
| --- | --- | --- | --- | --- | --- |
| UC-124 | Start a trial of 14 hari gratis | P-03 | Console | SUB-002 | 12 |
| UC-125 | Choose a plan from the canonical catalogue | P-03 | Console | SUB-003 | 12 |
| UC-126 | View metered usage against plan limits | P-03 | Console | SUB-004, SUB-007 | 12 |
| UC-127 | Receive a fair-use notice and a plan recommendation | P-03 | Console | SUB-005, SUB-006 | 12 |
| UC-128 | Upgrade a plan | P-03 | Console | SUB-008 | 12 |
| UC-129 | Downgrade a plan with consequences stated first | P-03 | Console | SUB-009 | 12 |
| UC-130 | Switch to annual billing | P-03 | Console | SUB-010 | 12 |
| UC-131 | Export tenant business data after a lapse | P-03 | Console | SUB-014, SUB-015 | 12 |
| UC-132 | View third-party messaging cost separately from the plan | P-03 | Console | SUB-019, RPT-020 | 12 |
| UC-133 | Suspend and restore a tenant | P-01 | Console | SUB-016 | 12 |
| UC-134 | Start a time-bound audited support impersonation | P-02 | Console | SUB-017 | 12 |
| UC-135 | End an impersonation and record findings | P-02 | Console | SUB-017 | 12 |
| UC-136 | Review the impersonation audit trail | P-01 | Console | SUB-017 | 12 |

**Constraints on this cluster.** There is **no** use case for a lifetime plan (SUB-012), a per-nota fee
on a normal plan (SUB-013), or an "unlimited WhatsApp" offer (SUB-020). Their absence is deliberate.
**Platform support has no silent tenant access**; UC-134 and UC-135 are the only paths, and both are
audited ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §15.5).

---

## 11. Catalog summary

| Cluster | Use cases | Step |
| --- | --- | --- |
| Identity, tenancy, and access | UC-001 … UC-011 | 3 |
| Master data | UC-012 … UC-029 | 4 |
| Order intake and payment | UC-030 … UC-053 | 5 |
| Production and quality | UC-054 … UC-064 | 6 |
| Tracking and notification | UC-065 … UC-075 | 7 |
| Pickup, delivery, and courier settlement | UC-076 … UC-092 | 8 |
| Unclaimed laundry recovery | UC-093 … UC-102 | 9 |
| Finance, reporting, and owner portfolio | UC-103 … UC-117 | 10 |
| Customer application | UC-118 … UC-123 | 11 |
| Subscription and platform administration | UC-124 … UC-136 | 12 |
| **Total** | **136 use cases** | |

---

## 12. Status

Every use case in this catalog has status **NOT IMPLEMENTED**. Backend runtime is **ABSENT**. Flutter
workspace is **ABSENT**. No use case has been built, tested, deployed, or exercised by a real user.
