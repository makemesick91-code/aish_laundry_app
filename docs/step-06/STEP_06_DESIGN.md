# Step 6 — Production Operations: Runtime Design and Contracts

**Step:** 6 — Production Operations · **Status:** `IN PROGRESS` · **Master Source:** 1.4.10 ·
**Scope opened by:** [DEC-0037](../decisions/DEC-0037-step-06-runtime-scope-transition.md) · **Private
object storage (FR-083):** [DEC-0038](../decisions/DEC-0038-step-06-private-object-storage-introduction.md) ·
**Baseline:** `9c23eca41f45963b61a04f936e69bf9b71997552`

This document fixes the runtime contracts the Step 6 backend and the Ops Android surface implement. The
conceptual lifecycles are already canonical:
[`PRODUCTION_STATE_MACHINE.md`](../state-machines/PRODUCTION_STATE_MACHINE.md),
[`QUALITY_CONTROL_STATE_MACHINE.md`](../state-machines/QUALITY_CONTROL_STATE_MACHINE.md), and
[`ORDER_STATE_MACHINE.md`](../state-machines/ORDER_STATE_MACHINE.md). This document adds only the runtime
contracts those conceptual specs do not carry: persistence constraints, the API surface, the offline
command protocol, idempotency, the conflict taxonomy, and the Step 6 threat model.

Nothing here is evidence. Only captured output at an exact SHA proves a result (Rule 01, DEC-0013).

---

## 1. Order-level vs item-level state

- **Order status** (Ordering module, Step 5) is the canonical customer-facing lifecycle (FR-071). Production
  never writes it directly except by the guarded transition to `READY_FOR_PICKUP`, and only through the
  Ordering module's interface (Rule 06 hard rule 6 — no cross-module table reach).
- **Production job state** (`PRODUCTION_STATE_MACHINE.md`: `CREATED`…`CLOSED`/`ABANDONED`) is the internal
  work lifecycle. It is derived from, and must not silently diverge from, the order status.
- **Production item state** (per order line): for **kiloan** services the item carries *quantity progress*
  (e.g. weight recorded, stage reached); for **satuan** services the item carries *discrete per-unit flags*
  (each piece washed/dried/finished/QC'd). FR-075. An order is not order-level "complete" until every item
  has reached the terminal stage its service type defines.

## 2. Production transition registry (runtime)

The registry is the single server-side authority. A transition executes only if it is enumerated in
`PRODUCTION_STATE_MACHINE.md` §3 (P-01…P-11), the actor holds the permission, the job is in the `From`
state, the guards hold, and the optimistic `version` matches. Otherwise it fails **closed and atomically**
(nothing partially applies) with a stable conflict code (§6).

- Client sends `{command, expected_version, client_reference, payload}`. The client **never** sends a raw
  next-state; the server derives the target from the command + current state.
- `kiloan`/`satuan` reconciliation: `AdvanceStage`/`CompleteStage` reconcile item progress → job state →
  (via QC verdict) order status. Partial item completion is deterministic: the job advances to `AWAITING_QC`
  only when every item's stages are recorded complete (P-06 guard).
- Cancelled orders: `CreateProductionJob` is refused for a `CANCELLED` order; an in-flight job whose order
  is cancelled goes to `ABANDONED` (P-11), never to a completed-looking terminal.

## 3. First `READY_FOR_PICKUP` anchor (FR-076, FR-077) — the hard invariant

- The order carries `first_ready_at timestamptz NULL`. It is set **once**, on the first transition to
  `READY_FOR_PICKUP`, and is thereafter immutable.
- **Database boundary:** a `BEFORE UPDATE` trigger rejects any statement that would change `first_ready_at`
  from a non-NULL value to a different value (fail closed at the engine, not only the app). Setting it from
  NULL is allowed exactly once; NULL→value is the only permitted write.
- **Application boundary:** the transition service sets `first_ready_at = COALESCE(first_ready_at, now())`
  inside the same transaction as the status write, under the row lock, so concurrent transitions produce
  **one** canonical timestamp.
- **Idempotency/replay:** a replayed `MarkReadyForPickup` with the same `client_reference` returns the
  original result and does not rewrite the timestamp.
- Leaving `READY_FOR_PICKUP` (e.g. back to `REWORK`) does **not** clear `first_ready_at`; re-entering does
  **not** replace it (FR-077). **Step 9 is not implemented:** no aging computation, no reminder, no ladder.

## 4. Persistence and database-constraint plan

All tables live in the Production module's migration(s). Every business table carries `tenant_id` and, where
canonical, `outlet_id`, from its introducing migration.

| Table | Key columns | Constraints |
| --- | --- | --- |
| `production_jobs` | `id uuid pk`, `tenant_id`, `outlet_id`, `order_id`, `state`, `version int`, `first_ready_at` mirror is on `orders` | FK `(tenant_id, order_id)` → orders composite; **partial unique** `(order_id)` where state not terminal (at most one open job per order); `state` check-constrained to the enum |
| `production_items` | `id`, `tenant_id`, `job_id`, `order_line_id`, `service_type`, `stage`, `quantity_done`, `flags jsonb` | FK `(tenant_id, job_id)` composite; FK to order line; no cross-tenant/job membership |
| `production_batches` | `id`, `tenant_id`, `outlet_id`, `code`, `stage`, `status`, `version`, `closed_at` | unique `(tenant_id, code)`; closed batch immutable (trigger) |
| `production_batch_items` | `batch_id`, `production_item_id`, `tenant_id` | composite FKs bind batch and item to the **same** tenant/outlet; unique `(batch_id, production_item_id)` (no duplicate membership); insert refused when batch `closed` |
| `production_operator_assignments` | `id`, `tenant_id`, `job_id`, `membership_id`, `role`, `assigned_at` | composite FK; assignment actor must be an active membership of the tenant |
| `quality_control_inspections` | `id`, `tenant_id`, `job_id`, `verdict`, `defect_reason`, `inspector_membership_id`, `evidence_path`, `created_at` | verdict check-constrained; append-only (no update of a recorded verdict — a re-inspection is a new row) |
| `rework_cycles` | `id`, `tenant_id`, `job_id`, `source_inspection_id`, `cycle_no`, `reason`, `started_at`, `completed_at` | FK to source inspection; unique `(job_id, cycle_no)`; monotonic cycle_no |
| `production_events` | `id`, `tenant_id`, `job_id`, `type`, `actor_membership_id`, `payload jsonb`, `client_reference`, `occurred_at` | **append-only** (no update/delete trigger); unique `(tenant_id, client_reference)` where not null → server-side idempotency key |

Migrations are tested **fresh apply → rollback → re-apply** against PostgreSQL (Rule 43). Money is never
introduced here; the historical price snapshot on the order is never written by any production statement.

## 5. Offline command protocol and idempotency contract

- Every mutating production action is an **offline command** carrying a client-generated
  `client_reference` (UUID), generated **once** and persisted with the queued command, reused verbatim on
  every retry (Rule 07, Rule 20). Regenerating on retry is forbidden.
- **Server contract:** on receiving a command the server looks up `(tenant_id, client_reference)` in
  `production_events`. If present, it returns the original canonical result (no second effect). If absent,
  it applies the transition inside a transaction that also writes the idempotency row — so the effect and
  its dedup key commit atomically. This makes a retried command exactly-once.
- **Dependency ordering:** `CreateProductionJob` precedes stage/QC/rework commands for the same order; a
  command whose predecessor has not synced does not jump ahead (queue preserves per-job order).
- **Optimistic concurrency:** the command carries `expected_version`; a mismatch returns
  `PRODUCTION_VERSION_CONFLICT` and applies nothing.

## 6. Conflict taxonomy (stable codes)

| Code | Meaning | Client behaviour |
| --- | --- | --- |
| `PRODUCTION_VERSION_CONFLICT` | `expected_version` != current | Surface; refetch canonical state; operator decides |
| `PRODUCTION_INVALID_TRANSITION` | Command not enumerated from current state | Surface; not retryable as-is |
| `PRODUCTION_ITEM_ALREADY_MOVED` | Item already advanced past requested stage | Surface canonical item state |
| `PRODUCTION_BATCH_CLOSED` | Membership/mutation on a closed batch | Surface; not retryable |
| `PRODUCTION_QC_ALREADY_RECORDED` | Verdict already exists for the open inspection | Surface canonical verdict |
| `PRODUCTION_REFERENCE_REUSED_DIFFERENT_PAYLOAD` | Same `client_reference`, different payload | **Reject**; never a second effect (integrity) |
| `PRODUCTION_FORBIDDEN` | Actor lacks permission / cross-tenant / cross-outlet | Surface; fail closed, discloses nothing about other tenants |
| `PRODUCTION_ORDER_NOT_ELIGIBLE` | Order cancelled / not `RECEIVED` | Surface |

A `PRODUCTION_FORBIDDEN` / not-found response never distinguishes "belongs to another tenant" from "does
not exist" (Rule 48 hard rule 5). Conflicts are **surfaced, never auto-resolved** (Rule 07 hard rule 5).

## 7. Retry / backoff policy (Ops Android)

Exponential backoff with jitter, bounded attempts, connectivity-aware scheduling; manual retry allowed for
safe (retryable) codes only; permanent errors (`INVALID_TRANSITION`, `REFERENCE_REUSED_DIFFERENT_PAYLOAD`,
`FORBIDDEN`) are classified terminal and surfaced to the conflict centre, never looped. A `SYNCED`
acknowledgement is terminal locally; a later `FAILED`/`CONFLICT` may **never** overwrite it. Session expiry
triggers step-up without discarding the queue; a revoked session/device fails closed.

## 8. Local data separation and encryption (Ops Android)

Queued commands and cached production data are partitioned by `tenant_id` + `user_id` (+ `outlet_id` where
applicable). A tenant switch clears the visible working set and never reveals the prior tenant's data; logout
with unacknowledged commands is guarded (no silent drop of a queued command). Sensitive local fields are
encrypted at rest using the repository's existing Android keystore-backed secure-storage abstraction — **no
custom cryptography** (Rule 03 hard rule 7, Rule 07 hard rule 8).

## 9. RBAC (server-side, Rule 40)

| Action | Permitted roles |
| --- | --- |
| Advance stage / block / resume / assign | `operator_produksi`, `manager_outlet` |
| Record QC verdict | `quality_control`, `manager_outlet` (distinct from production where policy requires) |
| Start/complete rework | `operator_produksi`, `manager_outlet` |
| Batch operations (create/update/add-item/remove-item/close) | `production.operate` — `operator_produksi`, `manager_outlet` (batch reads: `production.view`) |
| Mark `READY_FOR_PICKUP` | reached only via a `PASSED`/`WAIVED` QC verdict, never a direct client write |
| View production queue | outlet-scoped operational roles |

`kasir` gains no production-mutation permission; `kurir` and any customer actor gain none; `platform_admin`
cannot silently mutate tenant production data outside the audited support path. Negative RBAC tests prove a
member lacking a permission cannot perform the gated action, and a removed role stops working on the next
request.

## 10. Step 6 threat model (STRIDE, deltas only)

| ID | Threat | Control | Test |
| --- | --- | --- | --- |
| T6-01 | Cross-tenant production read/write (any path) | Composite FKs + tenant scope default; fail closed | Isolation matrix over ID/list/filter/search/export |
| T6-02 | Cross-outlet access within a tenant | `outlet_id` scoping on queue/assignment | Cross-outlet negative test |
| T6-03 | BOLA/IDOR on job/item/batch/QC id | Ownership check server-side; 404 == denied | Direct-ID negative test per actor |
| T6-04 | Client forges next-state / status | Command→state derivation server-side; no client status | Mass-assignment + invalid-transition tests |
| T6-05 | Duplicate `client_reference` (replay) | `(tenant_id, client_reference)` unique + returns original | Replay test → one effect |
| T6-06 | Same reference, different payload | `REFERENCE_REUSED_DIFFERENT_PAYLOAD` reject | Adversarial payload-swap test |
| T6-07 | Concurrent transitions / first-ready race | Row lock + optimistic version + DB trigger | Concurrency test → one timestamp |
| T6-08 | Batch membership race / duplicate | unique `(batch_id, item_id)` + closed-batch trigger | Race + duplicate insert tests |
| T6-09 | QC tampering (rewrite fail→pass) | Verdict append-only; new inspection, not update | Tamper test rejected |
| T6-10 | Rework history deletion | `production_events` append-only trigger | Delete attempt rejected |
| T6-11 | First-ready reset | DB trigger rejects non-NULL rewrite | Reset attempt rejected at engine |
| T6-12 | Local queue tampering / PII at rest | Keystore-backed encryption; tenant/user partition | Encryption + tenant-switch tests |
| T6-13 | Logout drops unsynced financial-adjacent command | Guarded logout | Logout-with-queue test |
| T6-14 | Forged server acknowledgement | Ack bound to server response, terminal SYNCED protected | Ack-integrity test |
| T6-15 | Token/PII in logs | Tenant/actor-aware logs, no secrets (Rule 46) | Log-scan test |

Every `HIGH`/`CRITICAL` threat above maps to a control and an automated test in the evidence pack.

## 11. API surface (REST `/api/v1`, JSON envelope, Bahasa error text)

`GET /api/v1/production/queue` · `GET /production/jobs/{job}` · `GET /production/items/{item}` ·
`GET /production/jobs/{job}/timeline` · `POST /production/jobs/{job}/advance` ·
`POST /production/jobs/{job}/block` · `POST /production/jobs/{job}/resume` ·
`POST /production/jobs/{job}/assign` · `POST /production/batches` · `PATCH /production/batches/{batch}` ·
`POST /production/batches/{batch}/close` · `POST /production/batches/{batch}/items` ·
`DELETE /production/batches/{batch}/items/{item}` · `POST /production/jobs/{job}/qc` ·
`POST /production/jobs/{job}/rework` · `POST /production/jobs/{job}/rework/{cycle}/complete` ·
`POST /production/jobs/{job}/ready` · `POST /production/commands` (offline replay envelope).

Every write endpoint: tenant/outlet-authorized, RBAC-gated, idempotent on `client_reference`,
optimistic-concurrency on `expected_version`, no mass assignment, minimal response exposure, stable
validation + conflict contract, paginated + allowlisted sorting on list endpoints.
