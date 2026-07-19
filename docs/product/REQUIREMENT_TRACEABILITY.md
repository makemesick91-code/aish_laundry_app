# Aish Laundry App — Requirement Traceability Matrix

**Document version: 1.0.0** · **Step: 1 — Product Requirement and Domain Model**
**Implementation status of every requirement listed here: NOT IMPLEMENTED**

Canonical source: [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md). Subordinate to the Master Source.

Related: [`PRODUCT_REQUIREMENTS.md`](PRODUCT_REQUIREMENTS.md) · [`MVP_SCOPE.md`](MVP_SCOPE.md) ·
[`USE_CASE_CATALOG.md`](USE_CASE_CATALOG.md)

---

## 0. How to read this matrix

Every requirement defined in [`PRODUCT_REQUIREMENTS.md`](PRODUCT_REQUIREMENTS.md) appears exactly once
below, mapped to:

| Column | Meaning |
| --- | --- |
| ID | The requirement identifier, permanent and never reused |
| Bounded context | One of the twenty canonical bounded contexts in §1 |
| Step | The canonical roadmap Step that first delivers it |
| Acceptance criteria | The `AC-###` identifiers in [`MVP_SCOPE.md`](MVP_SCOPE.md) §5 that verify it |

Where the Acceptance criteria cell reads **`Step N DoD`**, no dedicated MVP acceptance criterion was
written in Step 1; the requirement is verified against the general Definition of Done for that Step
([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §25.1) plus the criteria in the `TEN`, `FIN`, `OFF`, `TRK`,
`DEL`, `UCL`, `NOT`, `SEC`, and `NFR` series owned by the other Step 1 documents. This is stated plainly
rather than fabricating a criterion identifier that does not exist.

**Verification status of every row: NOT STARTED.** Nothing has been verified, because nothing has been
built.

---

## 1. Canonical bounded contexts

Twenty bounded contexts. Detailed definitions belong to `docs/domain/BOUNDED_CONTEXTS.md`; this matrix
uses the names only.

| # | Bounded context | Primary Steps |
| --- | --- | --- |
| 1 | Identity and Access | 3 |
| 2 | Tenant and Organization | 3, 4 |
| 3 | Subscription and Entitlement | 12 |
| 4 | Customer Management | 4 |
| 5 | Service Catalog and Pricing | 4 |
| 6 | Order Intake and POS | 5 |
| 7 | Production Operations | 6 |
| 8 | Quality Control and Rework | 6 |
| 9 | Payment and Receivables | 5 |
| 10 | Customer Tracking | 7 |
| 11 | Pickup and Delivery | 8 |
| 12 | Courier Assignment and Settlement | 8 |
| 13 | Notification and Communication | 7 |
| 14 | Unclaimed Laundry Recovery | 9 |
| 15 | Loyalty, Membership, and Deposit | 11 |
| 16 | Reporting and Owner Portfolio | 10 |
| 17 | Audit and Compliance | 3, 5, 12 |
| 18 | Platform Administration | 12 |
| 19 | Offline Synchronization | 5, 6, 8 |
| 20 | File and Evidence Management | 6, 8 |

---

## 2. Functional requirements — FR-001 … FR-120

### 2.1 FR-001 … FR-020 — Step 3

| ID | Bounded context | Step | Acceptance criteria |
| --- | --- | --- | --- |
| `FR-001` | Identity and Access | 3 | AC-001 |
| `FR-002` | Identity and Access | 3 | Step 3 DoD |
| `FR-003` | Identity and Access | 3 | AC-001 |
| `FR-004` | Identity and Access | 3 | AC-002 |
| `FR-005` | Identity and Access | 3 | AC-002 |
| `FR-006` | Identity and Access | 3 | AC-002 |
| `FR-007` | Identity and Access | 3 | AC-003, AC-005 |
| `FR-008` | Identity and Access | 3 | AC-004 |
| `FR-009` | Identity and Access | 3 | AC-004 |
| `FR-010` | Audit and Compliance | 3 | AC-003 |
| `FR-011` | Tenant and Organization | 3 | Step 3 DoD |
| `FR-012` | Tenant and Organization | 3 | Step 3 DoD |
| `FR-013` | Tenant and Organization | 3 | Step 3 DoD |
| `FR-014` | Tenant and Organization | 3 | Step 3 DoD |
| `FR-015` | Tenant and Organization | 3 | AC-006 |
| `FR-016` | Tenant and Organization | 3 | AC-006 |
| `FR-017` | Tenant and Organization | 3 | AC-006 |
| `FR-018` | Tenant and Organization | 3 | Step 3 DoD |
| `FR-019` | Tenant and Organization | 3 | AC-005, AC-007 |
| `FR-020` | Audit and Compliance | 3 | Step 3 DoD |

### 2.2 FR-021 … FR-047 — Step 4

| ID | Bounded context | Step | Acceptance criteria |
| --- | --- | --- | --- |
| `FR-021` | Customer Management | 4 | AC-007 |
| `FR-022` | Customer Management | 4 | AC-007 |
| `FR-023` | Customer Management | 4 | Step 4 DoD |
| `FR-024` | Customer Management | 4 | Step 4 DoD |
| `FR-025` | Customer Management | 4 | AC-025 |
| `FR-026` | Customer Management | 4 | AC-025 |
| `FR-027` | Customer Management | 4 | AC-008 |
| `FR-028` | Customer Management | 4 | AC-008 |
| `FR-029` | Customer Management | 4 | AC-005 |
| `FR-030` | Customer Management | 4 | AC-025 |
| `FR-031` | Service Catalog and Pricing | 4 | Step 4 DoD |
| `FR-032` | Service Catalog and Pricing | 4 | Step 4 DoD |
| `FR-033` | Service Catalog and Pricing | 4 | Step 4 DoD |
| `FR-034` | Service Catalog and Pricing | 4 | Step 4 DoD |
| `FR-035` | Service Catalog and Pricing | 4 | AC-009 |
| `FR-036` | Service Catalog and Pricing | 4 | AC-009 |
| `FR-037` | Service Catalog and Pricing | 4 | AC-010 |
| `FR-038` | Service Catalog and Pricing | 4 | AC-010 |
| `FR-039` | Service Catalog and Pricing | 4 | AC-011 |
| `FR-040` | Service Catalog and Pricing | 4 | Step 4 DoD |
| `FR-041` | Tenant and Organization | 4 | Step 4 DoD |
| `FR-042` | Tenant and Organization | 4 | Step 4 DoD |
| `FR-043` | Tenant and Organization | 4 | Step 4 DoD |
| `FR-044` | Tenant and Organization | 4 | AC-043 |
| `FR-045` | Tenant and Organization | 4 | Step 4 DoD |
| `FR-046` | Tenant and Organization | 4 | AC-031 |
| `FR-047` | Tenant and Organization | 4 | AC-028 |

### 2.3 FR-048 … FR-070 — Step 5

| ID | Bounded context | Step | Acceptance criteria |
| --- | --- | --- | --- |
| `FR-048` | Order Intake and POS | 5 | AC-013 |
| `FR-049` | Order Intake and POS | 5 | Step 5 DoD |
| `FR-050` | Order Intake and POS | 5 | Step 5 DoD |
| `FR-051` | Order Intake and POS | 5 | AC-012 |
| `FR-052` | Order Intake and POS | 5 | AC-009 |
| `FR-053` | Order Intake and POS | 5 | AC-023 |
| `FR-054` | Order Intake and POS | 5 | Step 5 DoD |
| `FR-055` | Order Intake and POS | 5 | Step 5 DoD |
| `FR-056` | Payment and Receivables | 5 | Step 5 DoD |
| `FR-057` | Order Intake and POS | 5 | AC-005 |
| `FR-058` | Order Intake and POS | 5 | Step 5 DoD |
| `FR-059` | Offline Synchronization | 5 | AC-013, AC-019 |
| `FR-060` | Audit and Compliance | 5 | Step 5 DoD |
| `FR-061` | Payment and Receivables | 5 | Step 5 DoD |
| `FR-062` | Payment and Receivables | 5 | AC-014 |
| `FR-063` | Payment and Receivables | 5 | AC-015 |
| `FR-064` | Payment and Receivables | 5 | AC-015 |
| `FR-065` | Payment and Receivables | 5 | AC-017 |
| `FR-066` | Payment and Receivables | 5 | AC-016 |
| `FR-067` | Payment and Receivables | 5 | AC-016 |
| `FR-068` | Payment and Receivables | 5 | AC-018 |
| `FR-069` | Audit and Compliance | 5 | Step 5 DoD |
| `FR-070` | Payment and Receivables | 5 | Step 5 DoD |

### 2.4 FR-071 … FR-085 — Step 6

| ID | Bounded context | Step | Acceptance criteria |
| --- | --- | --- | --- |
| `FR-071` | Production Operations | 6 | AC-020 |
| `FR-072` | Production Operations | 6 | AC-020 |
| `FR-073` | Production Operations | 6 | Step 6 DoD |
| `FR-074` | Production Operations | 6 | Step 6 DoD |
| `FR-075` | Production Operations | 6 | Step 6 DoD |
| `FR-076` | Production Operations | 6 | AC-021 |
| `FR-077` | Production Operations | 6 | AC-021, AC-038 |
| `FR-078` | Production Operations | 6 | Step 6 DoD |
| `FR-079` | Offline Synchronization | 6 | AC-019 |
| `FR-080` | Production Operations | 6 | Step 6 DoD |
| `FR-081` | Quality Control and Rework | 6 | AC-022 |
| `FR-082` | Quality Control and Rework | 6 | Step 6 DoD |
| `FR-083` | File and Evidence Management | 6 | AC-032 |
| `FR-084` | Quality Control and Rework | 6 | Step 6 DoD |
| `FR-085` | Quality Control and Rework | 6 | Step 6 DoD |

### 2.5 FR-086 … FR-099 — Step 7

| ID | Bounded context | Step | Acceptance criteria |
| --- | --- | --- | --- |
| `FR-086` | Customer Tracking | 7 | AC-023 |
| `FR-087` | Customer Tracking | 7 | AC-023 |
| `FR-088` | Customer Tracking | 7 | AC-024 |
| `FR-089` | Customer Tracking | 7 | AC-025 |
| `FR-090` | Customer Tracking | 7 | AC-025 |
| `FR-091` | Customer Tracking | 7 | AC-025 |
| `FR-092` | Customer Tracking | 7 | AC-026 |
| `FR-093` | Notification and Communication | 7 | AC-027 |
| `FR-094` | Notification and Communication | 7 | AC-027 |
| `FR-095` | Notification and Communication | 7 | Step 7 DoD |
| `FR-096` | Notification and Communication | 7 | AC-008 |
| `FR-097` | Notification and Communication | 7 | AC-028 |
| `FR-098` | Notification and Communication | 7 | AC-029 |
| `FR-099` | Notification and Communication | 7 | AC-030 |

### 2.6 FR-100 … FR-111 — Step 8

| ID | Bounded context | Step | Acceptance criteria |
| --- | --- | --- | --- |
| `FR-100` | Pickup and Delivery | 8 | Step 8 DoD |
| `FR-101` | Pickup and Delivery | 8 | AC-033 |
| `FR-102` | Pickup and Delivery | 8 | Step 8 DoD |
| `FR-103` | Pickup and Delivery | 8 | AC-033 |
| `FR-104` | Pickup and Delivery | 8 | AC-031 |
| `FR-105` | File and Evidence Management | 8 | AC-032 |
| `FR-106` | Pickup and Delivery | 8 | AC-037 |
| `FR-107` | Offline Synchronization | 8 | AC-019, AC-035 |
| `FR-108` | Courier Assignment and Settlement | 8 | Step 8 DoD |
| `FR-109` | Courier Assignment and Settlement | 8 | AC-034 |
| `FR-110` | Courier Assignment and Settlement | 8 | AC-035 |
| `FR-111` | Courier Assignment and Settlement | 8 | AC-036 |

### 2.7 FR-112 … FR-117 — Step 9

| ID | Bounded context | Step | Acceptance criteria |
| --- | --- | --- | --- |
| `FR-112` | Unclaimed Laundry Recovery | 9 | AC-038 |
| `FR-113` | Unclaimed Laundry Recovery | 9 | AC-039 |
| `FR-114` | Unclaimed Laundry Recovery | 9 | AC-039 |
| `FR-115` | Unclaimed Laundry Recovery | 9 | AC-040 |
| `FR-116` | Unclaimed Laundry Recovery | 9 | AC-041 |
| `FR-117` | Unclaimed Laundry Recovery | 9 | AC-042 |

### 2.8 FR-118 … FR-120 — Step 11

| ID | Bounded context | Step | Acceptance criteria |
| --- | --- | --- | --- |
| `FR-118` | Loyalty, Membership, and Deposit | 11 | Step 11 DoD |
| `FR-119` | Customer Tracking | 11 | Step 11 DoD |
| `FR-120` | Loyalty, Membership, and Deposit | 11 | Step 11 DoD |

---

## 3. Reporting requirements — RPT-001 … RPT-020

| ID | Bounded context | Step | Acceptance criteria |
| --- | --- | --- | --- |
| `RPT-001` | Reporting and Owner Portfolio | 10 | AC-044, AC-045 |
| `RPT-002` | Reporting and Owner Portfolio | 10 | AC-044 |
| `RPT-003` | Reporting and Owner Portfolio | 10 | AC-044 |
| `RPT-004` | Reporting and Owner Portfolio | 10 | AC-045 |
| `RPT-005` | Reporting and Owner Portfolio | 10 | Step 10 DoD |
| `RPT-006` | Reporting and Owner Portfolio | 10 | Step 10 DoD |
| `RPT-007` | Reporting and Owner Portfolio | 10 | AC-043 |
| `RPT-008` | Courier Assignment and Settlement | 10 | AC-036 |
| `RPT-009` | Payment and Receivables | 10 | Step 10 DoD |
| `RPT-010` | Payment and Receivables | 10 | Step 10 DoD |
| `RPT-011` | Unclaimed Laundry Recovery | 10 | AC-041 |
| `RPT-012` | Unclaimed Laundry Recovery | 10 | AC-041 |
| `RPT-013` | Unclaimed Laundry Recovery | 10 | AC-041 |
| `RPT-014` | Unclaimed Laundry Recovery | 10 | Step 10 DoD |
| `RPT-015` | Pickup and Delivery | 10 | AC-033 |
| `RPT-016` | Quality Control and Rework | 10 | Step 10 DoD |
| `RPT-017` | Reporting and Owner Portfolio | 10 | Step 10 DoD |
| `RPT-018` | Reporting and Owner Portfolio | 10 | AC-046 |
| `RPT-019` | Reporting and Owner Portfolio | 10 | AC-047 |
| `RPT-020` | Subscription and Entitlement | 12 | Step 12 DoD |

---

## 4. Subscription requirements — SUB-001 … SUB-020

| ID | Bounded context | Step | Acceptance criteria |
| --- | --- | --- | --- |
| `SUB-001` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-002` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-003` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-004` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-005` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-006` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-007` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-008` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-009` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-010` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-011` | Subscription and Entitlement | 12 | AC-010 |
| `SUB-012` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-013` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-014` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-015` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-016` | Platform Administration | 12 | Step 12 DoD |
| `SUB-017` | Platform Administration | 12 | Step 12 DoD |
| `SUB-018` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-019` | Subscription and Entitlement | 12 | Step 12 DoD |
| `SUB-020` | Subscription and Entitlement | 12 | Step 12 DoD |

---

## 5. Coverage summaries

### 5.1 By series

| Series | Range | Count |
| --- | --- | --- |
| FR | FR-001 … FR-120 | 120 |
| RPT | RPT-001 … RPT-020 | 20 |
| SUB | SUB-001 … SUB-020 | 20 |
| **Total defined in Step 1 by this document set** | | **160** |

No gaps and no duplicates exist in any of the three ranges.

### 5.2 By canonical Step

| Step | Title | Requirements | Count |
| --- | --- | --- | --- |
| 3 | Runtime, Authentication, Multi-Tenancy, and RBAC | FR-001 … FR-020 | 20 |
| 4 | Laundry Master Data | FR-021 … FR-047 | 27 |
| 5 | POS, Order, and Payment Foundation | FR-048 … FR-070 | 23 |
| 6 | Production Operations | FR-071 … FR-085 | 15 |
| 7 | Customer Tracking and WhatsApp | FR-086 … FR-099 | 14 |
| 8 | Pickup and Delivery Operations | FR-100 … FR-111 | 12 |
| 9 | Unclaimed Laundry and Cashflow Recovery | FR-112 … FR-117 | 6 |
| 10 | Finance, Reports, and Owner Portfolio | RPT-001 … RPT-019 | 19 |
| 11 | Customer Android Experience | FR-118 … FR-120 | 3 |
| 12 | Subscription and Platform Administration | SUB-001 … SUB-020, RPT-020 | 21 |
| **Total** | | | **160** |

Steps 0, 1, 2, 13, and 14 define no FR, RPT, or SUB requirement. Step 0 is governance. Step 1 produces
this documentation. Step 2 delivers the design system. Step 13 is governed by the `NFR` and `SEC` series.
Step 14 is pilot and launch.

### 5.3 By bounded context

| Bounded context | Requirements | Count |
| --- | --- | --- |
| Identity and Access | FR-001 … FR-009 | 9 |
| Tenant and Organization | FR-011 … FR-019, FR-041 … FR-047 | 16 |
| Subscription and Entitlement | RPT-020, SUB-001 … SUB-015, SUB-018 … SUB-020 | 20 |
| Customer Management | FR-021 … FR-030 | 10 |
| Service Catalog and Pricing | FR-031 … FR-040 | 10 |
| Order Intake and POS | FR-048 … FR-055, FR-057, FR-058 | 10 |
| Production Operations | FR-071 … FR-078, FR-080 | 9 |
| Quality Control and Rework | FR-081, FR-082, FR-084, FR-085, RPT-016 | 5 |
| Payment and Receivables | FR-056, FR-061 … FR-068, FR-070, RPT-009, RPT-010 | 13 |
| Customer Tracking | FR-086 … FR-092, FR-119 | 8 |
| Pickup and Delivery | FR-100 … FR-104, FR-106, RPT-015 | 7 |
| Courier Assignment and Settlement | FR-108 … FR-111, RPT-008 | 5 |
| Notification and Communication | FR-093 … FR-099 | 7 |
| Unclaimed Laundry Recovery | FR-112 … FR-117, RPT-011 … RPT-014 | 10 |
| Loyalty, Membership, and Deposit | FR-118, FR-120 | 2 |
| Reporting and Owner Portfolio | RPT-001 … RPT-007, RPT-017 … RPT-019 | 10 |
| Audit and Compliance | FR-010, FR-020, FR-060, FR-069 | 4 |
| Platform Administration | SUB-016, SUB-017 | 2 |
| Offline Synchronization | FR-059, FR-079, FR-107 | 3 |
| File and Evidence Management | FR-083, FR-105 | 2 |
| **Total** | | **160** |

Every one of the twenty canonical bounded contexts carries at least one requirement. No requirement
appears in more than one context.

---

## 6. Hard-gate traceability

The two hard gates are traced explicitly because a gate that is not traceable is not a gate.

### 6.1 Tenant isolation — automatic NO-GO on failure

| Canonical hard rule | Realised by |
| --- | --- |
| Rules 1–4: multi-tenant, multi-brand, multi-outlet structure | FR-011, FR-012, FR-013, FR-014, FR-015 |
| Rule 5: tenant switcher in every authenticated client | FR-016 |
| Rule 6: subscription and billing at the tenant boundary | SUB-001 |
| Rules 7–8: `tenant_id` on every business table; every business query tenant-scoped | The `TEN` series, enforced across every FR |
| Rules 9–10: client-supplied tenant id is never proof; membership and permission verified server-side | FR-007 |
| Rule 11: never merge on matching name, email, or phone | FR-019, FR-022 |
| Rule 12: cross-tenant exposure is an automatic NO-GO | AC-005; the `TEN` series negative test suite |
| Rule 13: the portfolio dashboard must not weaken isolation | RPT-018 |
| Tenant-scoped local device storage | FR-017, the `OFF` series |
| Tenant-scoped guest links | FR-109 |
| Tenant-scoped object keys and signed URLs | FR-105 |

### 6.2 Financial integrity — automatic NO-GO on failure

| Canonical rule | Realised by |
| --- | --- |
| Integer Rupiah; no floating point in any money path | FR-037, FR-038, SUB-011 |
| Payments are idempotent | FR-062 |
| Gateway callbacks verified server-side | FR-063 |
| Never marked paid on a client claim | FR-064 |
| Refund and void require permission and reason | FR-065 |
| No hard delete of financial transactions | FR-066 |
| Corrections by reversal or adjustment | FR-067 |
| Historical prices immune to price-list changes | FR-035, FR-036 |
| Shift closing compares expected against actual and records variance | RPT-007 |
| Courier cash reconciled | FR-110, FR-111, RPT-008 |
| Serialised concurrent money operations | FR-068 |
| Financial audit trail, append-only, separate from application logs | FR-069 |
| No duplicate order or payment from an offline retry | FR-059, FR-107, AC-013, AC-014, AC-019 |

### 6.3 Absolute prohibitions traced

| Prohibition | Realised by |
| --- | --- |
| No automatic disposal, sale, auction, donation, or transfer of laundry | FR-117, AC-042; the deliberate absence of any such use case in [`USE_CASE_CATALOG.md`](USE_CASE_CATALOG.md) §7 |
| No route optimisation claim, no guaranteed arrival time | FR-103, RPT-015, AC-033 |
| No "unlimited WhatsApp" claim | SUB-020 |
| No lifetime cloud plan | SUB-012 |
| No per-nota fee on normal plans | SUB-013 |
| No security control, isolation, or backup behind a paid tier | SUB-018 |
| The Customer Android app never replaces the public tracking portal | FR-119 |
| No silent platform access to tenant data | SUB-017 |
| Export never blocked for a lapsed tenant | SUB-014 |

---

## 7. Requirement identifier rules

1. **Identifiers are permanent.** A requirement is never renumbered, even if its Step slips.
2. **Identifiers are never reused.** A withdrawn requirement keeps its identifier and gains a withdrawal
   note.
3. **A superseded requirement keeps its identifier** and gains a supersession note pointing at its
   replacement, exactly as decision records do
   ([`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §31.1).
4. **This document defines only the FR, RPT, and SUB series.** Identifiers in the `NFR`, `SEC`, `TEN`,
   `FIN`, `OFF`, `TRK`, `DEL`, `UCL`, and `NOT` series are defined by the security, quality, and domain
   documents of Step 1 and are referenced here by series name only.
5. **A new requirement is appended, never inserted.** The three ranges defined here are closed at their
   current upper bounds; a further requirement takes the next unused number in its series rather than
   displacing an existing one.

---

## 8. Status

| Item | Status |
| --- | --- |
| Requirements defined by this document set | 160 |
| Requirements implemented | **0 — every requirement is NOT IMPLEMENTED** |
| Acceptance criteria defined | AC-001 … AC-047 in [`MVP_SCOPE.md`](MVP_SCOPE.md) |
| Acceptance criteria verified | **0 — verification is NOT STARTED** |
| Backend runtime | **ABSENT** |
| Flutter workspace | **ABSENT** |
| Deployment | **ABSENT** |
| Application CI | **NOT APPLICABLE** |
| UAT | **NOT STARTED** |

This matrix is a mapping of requirements to contexts, Steps, and criteria. It is **not** evidence that
any requirement has been implemented, tested, or verified.
