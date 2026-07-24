# Step 6 — Production Operations: Requirement Matrix and Acceptance Criteria

**Step:** 6 — Production Operations
**Status:** `IN PROGRESS`
**Authorized by:** the canonical roadmap (Master Source §24; [`ROADMAP.md`](../ROADMAP.md))
**Runtime scope opened by:** [DEC-0037](../decisions/DEC-0037-step-06-runtime-scope-transition.md)
**Master Source version:** 1.4.9
**Baseline SHA:** `9c23eca41f45963b61a04f936e69bf9b71997552` (post-Step-5 canonical `main`)
**Depends on (Step 5, delivered):** orders, order lines, server-authoritative pricing with the FR-036
immutable snapshot, payments, the append-only ledger, receipt/nota. Depends on (Step 4): outlets, staff
and role assignment, service catalogue.

---

## 1. How to read this document

This is the Phase 0/1 requirement matrix for Step 6. It maps every canonical Step 6 requirement to the
mechanism that will satisfy it, the verification that will prove it, and the evidence that must exist
before the claim may be made.

**Nothing in this document is evidence.** A row saying a requirement will be verified by a test is a
plan, not a result. Only captured output bound to an exact 40-character commit SHA proves anything
(Rule 01, DEC-0013). The authoritative requirement → evidence traceability lives in
[`evidence/step-06/README.md`](../../evidence/step-06/), bound to the candidate SHA, and supersedes the
`NOT IMPLEMENTED` labels below once it exists.

**No requirement is invented here.** Step 6's requirement set is **FR-071 … FR-085**, fixed in
[`PRODUCT_REQUIREMENTS.md`](../product/PRODUCT_REQUIREMENTS.md). Step 6 also carries the runtime
enforcement of the canonical order and production lifecycles ([`ORDER_STATE_MACHINE.md`](../state-machines/ORDER_STATE_MACHINE.md),
[`PRODUCTION_STATE_MACHINE.md`](../state-machines/PRODUCTION_STATE_MACHINE.md),
[`QUALITY_CONTROL_STATE_MACHINE.md`](../state-machines/QUALITY_CONTROL_STATE_MACHINE.md)) and the
offline-first Ops Android obligations (Rule 07).

## 2. Hard gates that do not relax for Step 6

- **Tenant isolation (Rule 02/39/48).** Every production table carries `tenant_id` from its introducing
  migration; every query is tenant-scoped server-side; negative tests prove a member of tenant A cannot
  reach a tenant B production job/item/batch/QC/rework record by direct ID, list, filter, search, export,
  or file URL. Evidence is produced against PostgreSQL.
- **Financial integrity (Rule 04/18).** Production records no money of its own and **must not mutate** the
  Step 5 integer-Rupiah price snapshot (FR-036, invariant 11). Any production write touching an order's
  captured total is a defect.
- **First-ready immutability (Rule 10, invariant 17).** The first `READY_FOR_PICKUP` timestamp is written
  once and is immutable; rework never restarts it. Enforced at the database boundary and the application.
- **Server-side authorization (Rule 03/40).** A client-supplied status, role, or tenant is never
  authorization proof; every transition is decided server-side.
- **Offline honesty (Rule 07/29).** A queued production command is never rendered as committed; the server
  is the source of truth; a retry reuses its original `client_reference` and produces exactly one effect.

## 3. Ownership model (summary; full model in [`docs/step-06/STEP_06_DESIGN.md`](../step-06/STEP_06_DESIGN.md))

| Aggregate | Owner module | Tenant/outlet binding | Consistency notes |
| --- | --- | --- | --- |
| `ProductionJob` (one per order) | Production | `tenant_id` + `outlet_id`, both from the order | State machine `PRODUCTION_STATE_MACHINE.md`; optimistic `version` |
| `ProductionItem` (per order line) | Production | inherits job's tenant/outlet | `kiloan` = quantity progress; `satuan` = discrete item flags (FR-075) |
| `ProductionBatch` | Production | `tenant_id` + `outlet_id` | Membership never crosses tenant/outlet; closed batch immutable |
| `QualityControlInspection` | Production (QC context) | inherits job | `QUALITY_CONTROL_STATE_MACHINE.md`; verdict is the authorisation |
| `ReworkCycle` | Production | inherits job | Immutable linkage to source QC; cycle number monotonic |
| `ProductionEvent` (timeline/audit) | Production | inherits job | Append-only; never hard-deleted |
| `Order` status | Ordering (Step 5) | — | Production *requests* `READY_FOR_PICKUP`; Ordering owns the write |

## 4. Requirement → mechanism → verification → evidence (PLAN — frozen at Phase 1, every row `NOT IMPLEMENTED`)

| FR | Title | Priority | Mechanism (implementation) | Verification | Evidence artefact | Status |
| --- | --- | --- | --- | --- | --- | --- |
| FR-071 | Canonical status set | MUST | `Order` status enum enforces exactly the 15 canonical statuses; production drives SORTING…QUALITY_CONTROL/REWORK/READY_FOR_PICKUP via the transition registry | Unit test rejects any non-canonical status; feature test drives the lifecycle | `production-lifecycle.txt` | NOT IMPLEMENTED |
| FR-072 | Transition validity | MUST | Server-side transition registry (P-01…P-11); invalid transitions fail closed atomically | Positive + exhaustive negative transition tests | `production-transitions.txt` | NOT IMPLEMENTED |
| FR-073 | Stage progress recording | MUST | `AdvanceStage` command, single action from the floor; server records actor+time | Feature + widget test (one-tap advance) | `production-stage.txt` | NOT IMPLEMENTED |
| FR-074 | Batch handling | MUST | `ProductionBatch` + membership; tenant/outlet-bound; closed batch immutable | DB constraint + service + isolation tests | `production-batch.txt` | NOT IMPLEMENTED |
| FR-075 | Item-level flags | MUST | `ProductionItem`: kiloan quantity vs satuan discrete flags | Unit + feature tests per service type | `production-item.txt` | NOT IMPLEMENTED |
| FR-076 | First ready timestamp | MUST | `first_ready_at` written once; DB trigger + app guard; idempotent | DB-level + app-level regression, replay, concurrency | `ready-anchor.txt` | NOT IMPLEMENTED |
| FR-077 | Aging anchor immunity to rework | MUST | Return to REWORK then READY again never rewrites `first_ready_at` | Regression test: rework-after-ready keeps first timestamp | `ready-anchor.txt` | NOT IMPLEMENTED |
| FR-078 | Issue status | MUST | `ISSUE`/`BLOCKED` with mandatory `ReasonCode` and documented exits | Transition tests for block/resume | `production-issue.txt` | NOT IMPLEMENTED |
| FR-079 | Offline production recording | MUST | Ops Android durable encrypted queue + `client_reference` idempotency; server exactly-once | Offline DAO/restart/race tests + backend idempotency test | `offline-queue.txt`, `idempotency.txt` | NOT IMPLEMENTED |
| FR-080 | Server-authoritative timestamps | MUST | All production timestamps set server-side (UTC), client clocks untrusted | Test: client-supplied time ignored | `production-timestamps.txt` | NOT IMPLEMENTED |
| FR-081 | Quality control gate | MUST | `QUALITY_CONTROL` enforced before READY where tenant policy requires, server-side | Feature test: cannot reach READY bypassing QC | `qc-gate.txt` | NOT IMPLEMENTED |
| FR-082 | Rework with reason | MUST | Failed QC → REWORK with mandatory defect reason | Feature + RBAC + negative (empty reason) tests | `rework.txt` | NOT IMPLEMENTED |
| FR-083 | Defect evidence | SHOULD | QC photo stored privately; signed expiring URL only | Test: no public URL; signed-URL access only | `qc-evidence.txt` | NOT IMPLEMENTED |
| FR-084 | Rework history | MUST | Every rework cycle recorded (actor, time, reason), visible in timeline, immutable | Test: repeated rework, immutable linkage | `rework.txt` | NOT IMPLEMENTED |
| FR-085 | Rework reporting input | MUST | Rework events recorded to support rate reporting by outlet/stage/defect | Test: rework event carries outlet+stage+reason | `rework.txt` | NOT IMPLEMENTED |

## 5. Inherited obligations proven end-to-end in Step 6

| Requirement | Source | Step 6 obligation |
| --- | --- | --- |
| FR-036 | Step 4/5 | Production must not mutate the historical integer-Rupiah price snapshot |
| TEN-* | Rule 02/48 | Full-path tenant isolation over every production access path |
| OFF-* | Rule 07 | `client_reference` idempotency, persistent queue, no duplicate effect |
| UCL-002/017 | Rule 10 | First-ready timestamp immutable; aging never restarts (anchor only; the ladder is Step 9) |

## 6. Non-goals (Step 7+ — must remain `NOT IMPLEMENTED`)

Public tracking portal/token, WhatsApp/notification sending, quiet hours, marketing consent, pickup
requests, delivery scheduling, courier assignment/routing, proof of pickup/delivery, external ojek guest
links, courier cash reconciliation, the H+1/H+3/H+7/H+14 reminder ladder, unclaimed-laundry dashboard,
aging jobs, finance reports, shift closing, subscription billing, platform administration, deployment,
scanner/printer hardware integration, AI optimisation. Step 6 records the first-ready **fact**; computing
aging from it is Step 9.

## 7. Definition of Done (Step 6)

All FR-071 … FR-085 delivered and evidenced at an exact SHA; all hard gates proven (tenant isolation over
every access path against PostgreSQL, financial-snapshot immutability, first-ready immutability, offline
no-duplicate); `scripts/verify-step-06.sh` returns FAIL 0 with no mandatory SKIP; governance suite green;
authoritative CI green on the exact candidate SHA; documentation and evidence complete; `GO` conferred by
the owner after merge (never self-declared).
