# Non-Functional Requirements — Aish Laundry App

**Step:** 1 — Product Requirement and Domain Model
**Status:** IN PROGRESS
**Implementation status:** NOT IMPLEMENTED. Backend runtime ABSENT. Flutter workspace ABSENT.
Deployment ABSENT. Application CI NOT APPLICABLE. UAT NOT STARTED.
**Canonical source:** [`../MASTER_SOURCE.md`](../MASTER_SOURCE.md) §13, §15, §16, §17, §18, §19, §20, §28
**Related decision:** [DEC-0013](../decisions/DEC-0013-exact-sha-evidence-before-go.md)

---

## 1. Purpose and register ownership

This document is the authoritative register of **`NFR-001` … `NFR-050`**.

Every requirement states: **metric**, **measurement method**, **environment**, **threshold**,
**responsible roadmap Step**, and **failure consequence**.

---

## 2. The honesty statement about numbers — read this before any figure below

**Every numeric target in this document is a TARGET THAT HAS NOT BEEN MEASURED.**

- Nothing has been benchmarked. No load test has been run. No device has been profiled. No availability
  has been observed. No crash rate exists, because there are no sessions.
- **No target below may be described as achieved, met, validated, or on track.** There is no system to
  achieve them against.
- A target moves to a measured status only through evidence bound to an **exact 40-character commit
  SHA**, with the exact command, captured output, timestamp, and environment (DEC-0013). Evidence
  produced at one SHA does not carry over to another.

**Relationship to Master Source §19.3.** §19.3 states that concrete numeric budgets are set in **Step 13
— Security, Performance, Backup, and Recovery**, measured against real devices, and that the Master
Source deliberately does not invent numbers that have not been measured. The figures reproduced in §3
below are therefore recorded here as **proposed Step 1 targets awaiting owner confirmation and Step 13
measurement** — they are the intent against which Step 13 will set the canonical budget, not a
substitute for it. They are not canonical, and they do not amend the Master Source. See the open
question in [`STEP_01_DEFINITION_OF_DONE.md`](STEP_01_DEFINITION_OF_DONE.md) §8.

---

## 3. Headline targets (TARGETS NOT YET MEASURED)

Reproduced exactly as specified. **None of these has been measured. None is claimed as achieved.**

| Target | Value |
| --- | --- |
| Common API p95 | **under 500 ms under defined normal load** |
| Public tracking primary content | **under 2.5 seconds on reasonable mobile connection** |
| Android cold start | **under 3.5 seconds on target device class** |
| Pilot crash-free sessions | **at least 99.5%** |
| Cross-tenant leakage | **zero** |
| Duplicate order due to retry | **zero** |
| Duplicate payment | **zero** |
| Commercial availability target | **99.9%** |
| Critical data RPO | **15 minutes maximum** |
| RTO | **4 hours maximum** |

The three **zero** targets are not aspirations that degrade gracefully. Cross-tenant leakage, duplicate
orders from retry, and duplicate payments are hard gates: any occurrence is an **automatic NO-GO**
(DEC-0012), regardless of schedule.

**Target environment for every measurement below:** a **low-end to mid-range Android phone on a
congested mobile network**. This is not a degraded case to be handled later; it is the normal case.

---

## 4. Requirement register

### 4.1 Performance — NFR-001 … NFR-008

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-001** | Common API endpoints respond within the latency target under normal load. | Server-side response latency, 95th percentile | Load generation at a defined normal-load profile; latency histogram per endpoint | Production-equivalent environment, defined normal load | **p95 under 500 ms** — target, not measured | 13 | Blocks Step 13 DoD |
| **NFR-002** | The public tracking portal renders its primary content quickly on a cold cache. | Time to primary content | Synthetic page-load measurement on a throttled connection profile, cold cache | Low-end to mid-range Android, congested mobile network | **under 2.5 seconds** — target, not measured | 13 | Blocks Step 13 DoD; the portal is the most performance-critical surface |
| **NFR-003** | Android applications start acceptably from cold. | Cold start to first interactive frame | On-device instrumentation across a defined device sample | Target device class, cold start after reboot | **under 3.5 seconds** — target, not measured | 13 | Blocks Step 13 DoD |
| **NFR-004** | The kasir order-intake path stays responsive under counter conditions. | Interaction-to-acknowledgement latency on the intake path | On-device instrumentation during a scripted intake sequence, including offline | Ops Android at an outlet counter, intermittent connectivity | Set with evidence in Step 13; qualitatively, the common path is the fastest path | 13 | Blocks Step 13 DoD |
| **NFR-005** | No screen loads an unbounded result set. Lists are paginated, indexed, and bounded. | Maximum rows returned per request per endpoint | Endpoint review plus tests asserting a bound is enforced server-side | All surfaces | A hard server-side bound exists on every list endpoint | 5 onward | Reject change |
| **NFR-006** | Every tenant-scoped query is index-supported. A tenant-scoped query without a supporting index is a **defect, not a tuning opportunity**. | Presence of a supporting index per query shape | Query plan review; slow-query capture | PostgreSQL | No unindexed tenant-scoped query in a user-facing path | 3 onward | Reject change |
| **NFR-007** | Images are compressed on device before upload, served resized, and never loaded at full resolution in a list. | Bytes transferred per image per context | Payload measurement per surface | Congested mobile network | Set with evidence in Step 13; the principle is binding from Step 8 | 8, budget 13 | Blocks DoD |
| **NFR-008** | Background work belongs in queues. Notification sending, report generation, and reminder evaluation never block a user request. | Presence of synchronous heavy work in request paths | Endpoint review; queue-depth metric | Backend | Zero user requests performing send, report generation, or ladder evaluation inline | 7 onward | Reject change |

### 4.2 Availability — NFR-009 … NFR-011

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-009** | The service meets its commercial availability target. | Availability over a defined measurement window | External synthetic checks against defined critical paths | Production, once it exists | **99.9%** — target, not measured; there is no production and no observed availability | 13, baselined 14 | Blocks commercial launch readiness |
| **NFR-010** | Loss of Redis degrades performance but never loses money or orders. | Order and payment loss during a cache/queue outage | Fault-injection exercise with Redis unavailable | Production-equivalent | Zero orders lost, zero payments lost | 13 | **NO-GO** on any loss |
| **NFR-011** | Portal load cannot starve the kasir path. The two surfaces are isolated in capacity terms. | Kasir path latency under portal load | Load test driving portal traffic while measuring the intake path | Production-equivalent | Kasir path stays within its Step 13 budget under defined portal load | 13 | Blocks Step 13 DoD |

### 4.3 Reliability — NFR-012 … NFR-016

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-012** | Client applications are stable in pilot use. | Crash-free sessions | Crash reporting with personal data redacted at source | Pilot, target device class | **at least 99.5%** — target, not measured; **UAT is NOT STARTED and no session exists** | 14 | Blocks pilot readiness |
| **NFR-013** | A retry never produces a duplicate order. | Count of duplicate orders attributable to retry | Offline replay tests: network loss mid-submit, app kill mid-submit, long-offline replay | Ops Android, induced connectivity failure | **zero** | 5 | **Automatic NO-GO** |
| **NFR-014** | A retry, replay, or duplicate callback never produces a duplicate payment. | Count of duplicate payments | Idempotency tests on `client_reference`; duplicate gateway callback replay; concurrent submission | Backend and Ops Android | **zero** | 5 | **Automatic NO-GO** |
| **NFR-015** | A messaging failure never changes order state. | Count of orders whose state changed due to a notification outcome | Fault injection with the provider unavailable | Backend | zero | 7 | Reject design |
| **NFR-016** | A failed operation is never silently dropped. It stays visible and actionable to the user. | Count of silently discarded queued operations | Queue inspection after induced failures | Ops Android | zero | 5 | Blocks DoD |

### 4.4 Durability — NFR-017 … NFR-019

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-017** | Critical data loss on failure is bounded. | Recovery Point Objective | Backup cadence verification plus a restore drill measuring actual data loss | Production-equivalent | **RPO 15 minutes maximum** — target, not measured | 13 | Blocks Step 13 DoD |
| **NFR-018** | The persistent offline queue survives application restart and device reboot. | Queued operations surviving restart | Kill-and-reboot tests with a populated queue | Ops Android | 100% of queued operations survive; an in-memory queue is not acceptable | 5 | Blocks DoD |
| **NFR-019** | The financial queue is never casually deleted. Removal requires an explicit, permissioned, audited action. | Count of queued financial operations lost to cache clear, upgrade, or logout | Tests performing each of those actions with a populated financial queue | Ops Android | zero | 5 | **NO-GO** |

### 4.5 Security — NFR-020 … NFR-022

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-020** | No cross-tenant data exposure occurs through any access path. | Count of cross-tenant exposures | Negative tenant-isolation tests across direct identifier, list, filter, search, count, export, report, and file URL | All surfaces | **zero** | 3, then every Step | **Automatic NO-GO** (DEC-0012) |
| **NFR-021** | The full `SEC-001` … `SEC-060` register is satisfied at the Step that owns each criterion. | Count of unsatisfied SEC criteria owned by the Step | Per-criterion verification recorded at the exact SHA | Per criterion | zero unsatisfied at the owning Step | per criterion | Blocks DoD; NO-GO where the criterion says so |
| **NFR-022** | Rate limiting and abuse protections fail closed rather than open when their counter store is unavailable. | Requests permitted during counter-store outage | Fault injection with the store unavailable | Backend | Zero abusable requests permitted | 13 | **NO-GO** |

### 4.6 Privacy — NFR-023 … NFR-024

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-023** | No personal data appears in logs or telemetry. | Occurrences of personal data, credentials, OTPs, or tokens in log and telemetry stores | Automated log-content assertions plus sampled review at the exact SHA | Backend and clients | **zero** | 13, binding from 3 | **NO-GO** |
| **NFR-024** | Masking is applied server-side per context, and the public tracking portal never returns a full address. | Occurrences of a full address in a portal response | Portal response assertions on every field | Portal | **zero** | 7 | **NO-GO** |

### 4.7 Accessibility — NFR-025 … NFR-028

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-025** | Layouts survive large system font sizes without truncating critical information. | Screens failing at the largest supported font scale | Device font-scaling sweep across every screen | Target device class | zero critical truncations | 2, enforced per surface | Blocks DoD |
| **NFR-026** | **Status is never conveyed by colour alone.** Every status carries text and/or an icon in addition to colour. | Status indicators relying on colour alone | Design-system review plus per-screen audit | All surfaces | zero | 2 | Blocks DoD for the Step that introduced it |
| **NFR-027** | Contrast meets accessible ratios and tap targets are adequately sized. | Contrast ratio per token pair; tap target dimensions | Design-system token audit; measured against the design system's stated ratio | All surfaces | Ratio and size thresholds fixed in Step 2 | 2 | Blocks DoD |
| **NFR-028** | Courier interfaces are usable one-handed, outdoors, on a cheap phone: large targets, minimal steps. | Steps and taps to complete a proof capture | Task walkthrough against the defined courier flow | Ops Android, outdoor conditions | Minimised and fixed in Step 8; complexity here produces skipped proofs and lost cash | 8 | Blocks DoD |

### 4.8 Offline usability — NFR-029 … NFR-032

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-029** | `client_reference` is generated once, persisted with the queued operation, and reused unchanged on every retry. | Operations retried with a regenerated reference | Retry tests inspecting the reference across attempts | Ops Android | **zero** — regenerating on retry defeats the entire mechanism | 5 | **NO-GO** |
| **NFR-030** | Offline and sync state are visible to the user at all times: what is pending, what failed, what needs attention. | Presence of pending, failed, and attention states in the UI | Per-screen review during induced offline conditions | Ops Android | All three states visible on every relevant screen | 5 | Blocks DoD |
| **NFR-031** | A tenant or user switch exposes nothing cached from the previous context. | Records from the previous context readable after a switch | Tenant-switch leak test with two memberships on one device | Ops Android | **zero** | 5 | **NO-GO** — treated as a tenant-isolation defect |
| **NFR-032** | Payment conflicts surface to a human with both values rather than resolving silently. | Conflicts resolved without human input | Conflict-injection test | Ops Android and backend | zero silent resolutions | 5 | Reject implementation |

### 4.9 Observability — NFR-033 … NFR-036

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-033** | Logs are structured records with a stable schema, not free-text strings. | Proportion of log output conforming to the schema | Schema validation over sampled output | Backend | Full conformance in production paths | 13 | Blocks Step 13 DoD |
| **NFR-034** | A single customer interaction is traceable end to end through queues and background jobs. | Trace completeness across a queued flow | Sampled trace reconstruction | Backend | Correlation identifier present at every hop | 13 | Blocks Step 13 DoD |
| **NFR-035** | The minimum signal set is collected: API error rate and latency percentiles; queue depth and job failure rate; notification delivery outcomes per provider; offline sync backlog and conflict count; payment success, retry, and duplicate-suppression counts; authentication failure and lockout rates; unclaimed-laundry aging distribution. | Signals present out of the seven required | Signal inventory review | Backend | All seven present | 13 | Blocks Step 13 DoD |
| **NFR-036** | Alerts fire on symptoms customers feel, and every alert has a named owner. An alert nobody acts on is deleted. | Alerts without an owner; alerts never actioned | Alert inventory review | Operations | zero ownerless alerts | 13 | Blocks Step 13 DoD |

### 4.10 Maintainability — NFR-037 … NFR-039

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-037** | The backend is one deployable, internally divided into modules aligned with the roadmap domains, communicating through defined interfaces rather than reaching into each other's tables. | Cross-module direct table accesses | Module-boundary review | Backend | zero | 3 onward | Reject change |
| **NFR-038** | Breaking API changes ship as a new version and are never applied in place to `/api/v1` semantics under existing clients. | Breaking changes made in place | API contract diff review per release | Backend | zero | 3 onward | Revert; ship as a new version |
| **NFR-039** | Pricing displayed anywhere is read from a single canonical configuration derived from the Master Source, never hard-coded in scattered UI strings, and matches the Master Source character for character. | Divergent pricing occurrences | Pricing consistency validation across the repository and, later, the product | All surfaces | **zero** divergences | 12, validated now for documentation | Correct immediately and report the discrepancy |

### 4.11 Scalability — NFR-040 … NFR-042

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-040** | The system supports the plan limits it sells: 1 outlet / 5 staff / up to 1.000 order per month fair-use on Starter; up to 3 outlets / 20 staff / up to 5.000 orders on Growth; up to 10 outlets / 75 staff / up to 20.000 orders on Scale. | Sustained throughput per tenant at each plan's stated ceiling | Load test at each plan ceiling | Production-equivalent | Each ceiling sustained within the Step 13 latency budget | 13, limits in 12 | Blocks commercial launch readiness |
| **NFR-041** | One tenant's load cannot degrade another tenant's service beyond a defined bound. | Cross-tenant latency impact under single-tenant load spike | Load test driving one tenant hard while measuring another | Production-equivalent | Bound fixed with evidence in Step 13 | 13 | Blocks Step 13 DoD |
| **NFR-042** | Plan limits are enforced server-side and tenant-scoped, presented honestly, with Starter's order limit described as **fair-use** rather than a hard cutoff, and **fair-use handling never stops a laundry operating mid-shift**. | Limit enforcement bypasses; mid-shift stoppages caused by a limit | Entitlement tests via direct API calls; fair-use behaviour walkthrough | Backend | zero bypasses; zero mid-shift stoppages | 12 | Reject change |

### 4.12 Recovery — NFR-043 … NFR-044

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-043** | Service is restored within the recovery time objective after a declared incident. | Recovery Time Objective | Timed restore drill from a declared failure to service restored | Production-equivalent | **RTO 4 hours maximum** — target, not measured | 13 | Blocks Step 13 DoD |
| **NFR-044** | **Restore is exercised, not assumed.** Backups are encrypted and a restore drill produces unedited evidence at the exact SHA. | Restore drills completed with evidence | Drill execution and evidence capture | Production-equivalent | At least one successful drill with recorded output before Step 13 closes | 13 | Blocks Step 13 DoD; a backup never restored is not a backup |

### 4.13 Localization — NFR-045 … NFR-046

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-045** | UI copy and user-facing error messages are in **Bahasa Indonesia**; currency is **Rupiah** formatted for Indonesian conventions and backed by **integer** values; business-day logic uses **Asia/Jakarta**, and quiet hours use **outlet local time**. | Non-conforming strings, formats, or timezone usages | Copy review; formatting tests; timezone tests across the quiet-hours boundary | All surfaces | zero non-conformances | 2 onward | Blocks DoD |
| **NFR-046** | Timestamps are stored in UTC and rendered in Asia/Jakarta or outlet local time where outlet-local semantics matter. Server timestamps are authoritative for ordering and reporting; clock skew on devices is expected. | Ordering errors attributable to device clock skew | Skew-injection test on the offline queue | Backend and Ops Android | zero | 5 | Blocks DoD |

### 4.14 Compatibility — NFR-047 … NFR-048

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-047** | The product functions on the baseline device class — a low-end to mid-range Android phone on a congested mobile network — as the normal case, not a degraded one. | Functional failures on the baseline device sample | Full flow walkthrough on the defined device sample | Target device class | zero functional failures | 13, honoured from 2 | Blocks Step 13 DoD |
| **NFR-048** | The public tracking portal requires **no application install** and works in a plain mobile browser. A change making public tracking require an install is rejected. | Install-gated portal paths | Portal walkthrough in a stock mobile browser | Portal | zero install-gated paths | 7 | Reject — contradicts DEC-0006 and DEC-0014 |

### 4.15 Data retention — NFR-049

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-049** | Retention honours the documented lifecycle: data retained while the tenant relationship exists plus legal and tax obligations; **tenant data remains exportable per policy when a subscription lapses**; deletion handled at the tenant boundary with financial retention obligations honoured and explained to the customer. | Export failures under a lapsed subscription; retention deviations | Export test under lapse; retention policy audit | Backend | zero export failures; zero undocumented deviations | 12 | Reject change — breaches a pricing guardrail |

### 4.16 Auditability — NFR-050

| ID | Requirement | Metric | Measurement method | Environment | Threshold | Step | Failure consequence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| **NFR-050** | Financial and security audit trails are append-only, separate from application logs, not subject to log rotation, and record actor, tenant, outlet, timestamp, amounts before and after, and reason. Every completion claim is bound to an **exact 40-character commit SHA**. | Mutable audit records; claims without SHA-bound evidence | Attempted-mutation test on the audit trail; evidence policy validation at the reviewed SHA | Backend and repository | **zero** mutable records; **zero** unevidenced claims | 13, evidence binding now | **NO-GO**; fabricated output voids every claim from the same session |

---

## 5. Register summary

| Area | Range | Count |
| --- | --- | --- |
| Performance | NFR-001 … NFR-008 | 8 |
| Availability | NFR-009 … NFR-011 | 3 |
| Reliability | NFR-012 … NFR-016 | 5 |
| Durability | NFR-017 … NFR-019 | 3 |
| Security | NFR-020 … NFR-022 | 3 |
| Privacy | NFR-023 … NFR-024 | 2 |
| Accessibility | NFR-025 … NFR-028 | 4 |
| Offline usability | NFR-029 … NFR-032 | 4 |
| Observability | NFR-033 … NFR-036 | 4 |
| Maintainability | NFR-037 … NFR-039 | 3 |
| Scalability | NFR-040 … NFR-042 | 3 |
| Recovery | NFR-043 … NFR-044 | 2 |
| Localization | NFR-045 … NFR-046 | 2 |
| Compatibility | NFR-047 … NFR-048 | 2 |
| Data retention | NFR-049 | 1 |
| Auditability | NFR-050 | 1 |
| **Total** | **NFR-001 … NFR-050** | **50** |

---

## 6. Restatement of the honesty position

Every figure in this document is a **target that has not been measured**. There is no backend, no
Flutter workspace, no deployment, no application CI, and no UAT. Nothing here may be quoted as a
capability, a benchmark, a service-level commitment, or evidence of anything. It is a statement of what
the product must eventually be measured against.

---

## 7. Related documents

- [`ACCEPTANCE_CRITERIA.md`](ACCEPTANCE_CRITERIA.md)
- [`STEP_01_DEFINITION_OF_DONE.md`](STEP_01_DEFINITION_OF_DONE.md)
- [`../security/SECURITY_ACCEPTANCE_CRITERIA.md`](../security/SECURITY_ACCEPTANCE_CRITERIA.md)
- [`../security/INITIAL_THREAT_MODEL.md`](../security/INITIAL_THREAT_MODEL.md)
- [`../security/PRIVACY_REQUIREMENTS.md`](../security/PRIVACY_REQUIREMENTS.md)
- [`../security/DATA_CLASSIFICATION.md`](../security/DATA_CLASSIFICATION.md)
- [`../STATUS.md`](../STATUS.md) · [`../ROADMAP.md`](../ROADMAP.md)
