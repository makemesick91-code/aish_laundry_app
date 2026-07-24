# Step 6 — Production Operations: Evidence Pack

**Status:** `IN PROGRESS` — `WATCH / MERGE-READY` at the candidate SHA below; `GO` is the owner's to confer after merge and post-merge verification.
**Baseline SHA:** `9c23eca41f45963b61a04f936e69bf9b71997552` · **Scope:** [DEC-0037](../../docs/decisions/DEC-0037-step-06-runtime-scope-transition.md)

Every artefact here is captured output bound to an exact 40-character commit SHA (Rule 01, DEC-0013),
sanitised — no secrets, no real personal data (Rule 23/45). Every datum in every test fixture is
fictional and recognisably so. **No `GO` is claimed here.**

## What Step 6 delivers

The production-operations backend (Units A–E, pre-resume) **plus** the complete Flutter Ops Android
**offline-first** production surface (F1–F4) and its verifier:

- **Backend:** production persistence with composite tenant-bound constraints; the server-authoritative
  state machine; quality control and rework; the **immutable first `READY_FOR_PICKUP` anchor**; the
  RBAC-gated, idempotent, optimistic-concurrency HTTP surface (9 endpoints).
- **Flutter (F1–F4):** typed production contracts + repository; a durable, encrypted-at-rest command
  queue; a connectivity-aware sync worker with reconciliation; and the operator UI, wired into the Ops
  router — all offline-first and honest (a queued command is never rendered as committed; READY is never
  claimed before a server acknowledgement — Rule 29).

The operator-assignment table remains prepared, unexercised, Step 6-authorised infrastructure (no HTTP
surface). The two residuals FR-074 and FR-083 were **closed on the residual-closure branch** — see below.

## Residual closure (FR-074, FR-083)

Under the repository owner's decisions, the two residuals the implementation merge carried are now
implemented and `TESTED` (artefacts `batch-operations.txt`, `qc-evidence.txt`):

- **FR-074 — production batch operations (MUST).** `ProductionBatchService` + `BatchController`
  (`/api/v1/production/batches`: list, create, show, update, close, add-item, remove-item, timeline),
  gated `production.operate` (writes) / `production.view` (reads). Membership is tenant- and
  outlet-safe and stage-compatible; a closed batch is immutable (DB triggers); idempotency and the
  append-only membership timeline live in `production_batch_events`. A durable offline command path and
  the Ops Android batch list/detail surface complete it. Backend isolation/idempotency/concurrency/
  lifecycle tests (`ProductionBatchTest`) + Flutter F1/F2/F3/F5.
- **FR-083 — QC defect-photo evidence (SHOULD).** A defect photo attaches to a FAILED inspection,
  stored in a PRIVATE S3-compatible bucket (MinIO — digest-pinned, loopback-bound, **no public
  bucket**) under a random key, validated by content (MIME/dimensions/size, malformed rejected),
  SHA-256-checksummed, audited append-only, and read only through a **short-lived signed URL**. Backend
  tests (`QualityControlEvidenceTest`) run against **real MinIO**; Flutter F1/F3/F6. The photo *capture
  source* is an injected seam exercised from fixtures — no physical-camera evidence is fabricated
  (classified truthfully, owner constraint).

`GO` remains the owner's to confer after merge; nothing here self-declares it.

## Commit map (this branch, from the baseline)

| SHA prefix | Unit |
| --- | --- |
| `bd90cc6` | DEC-0037 runtime-scope transition |
| `95a3ba6` | Phase 1 design, FR-071…FR-085 matrix, contracts, threat model |
| `59f9423` | Unit A — production persistence + DB-boundary invariants |
| `5a6523c` | Unit B — production state-machine services |
| `977ac6b` | Unit C — quality control and rework services |
| `0c21362` | Unit D — immutable first `READY_FOR_PICKUP` anchor |
| `d4181db` | Unit E — production API, RBAC, HTTP contract |
| `373e27b` | Repair the stale Step-5 out-of-scope route guard → Step 7+ |
| `f577059` | F1 — typed production contracts + repository |
| `53d5232` | F2 — durable encrypted offline command queue |
| `bd9b179` | F3 — sync worker + reconciliation |
| `34acaa9` | F4 — Ops Android offline-first surface + wiring |
| `…` | F3 forged-ack hardening; schema-scope → Step 6; forward-boundary guards → Step 7; verifier + adversarial harness; this evidence pack |

The candidate SHA is recorded in `verify-step-06.txt` (the verifier was captured against a **clean**
working tree; this evidence commit is the final candidate — re-run `bash scripts/verify-step-06.sh` at
the merge SHA to reconfirm `25 PASS / 0 FAIL / 0 SKIP`).

## Index

| Artefact | Requirement(s) / property |
| --- | --- |
| `verify-step-06.txt` | The full Step 6 gate — **25 PASS / 0 FAIL / 0 SKIP** at a clean tree |
| `verifier-adversarial.txt` | `test-verify-step-06.sh` — the verifier's own 6/6 adversarial proof |
| `regression-totals.txt` | Backend 586 (5195 assertions); Production suite 51; networking 66; offline_sync 37; domain 12; ops_android widgets 125; analyze + format clean |
| `flutter-offline.txt` | F1 19 · F2 18 · F3 19 · F4 12 — the offline-first surface |
| `production-transitions.txt` | FR-071, FR-072, FR-073, FR-078, FR-080 (state machine) |
| `migrations.txt` | FR-074, FR-076 + DB-boundary constraints |
| `ready-anchor.txt` | FR-076, FR-077 — immutable first `READY_FOR_PICKUP` |
| `rework.txt` | FR-081 … FR-085 — QC + rework |
| `api-rbac.txt` | Production HTTP surface + RBAC, cross-tenant 404 |

Requirement-by-requirement disposition is in
[`../../docs/quality/STEP_06_REQUIREMENT_MATRIX.md`](../../docs/quality/STEP_06_REQUIREMENT_MATRIX.md).

## How the hard gates are evidenced

- **Tenant isolation (Rule 48):** the production suite proves a member of tenant A cannot read, list,
  advance, QC, or mark-ready a tenant-B job by any path — a foreign job 404s exactly like an absent one
  (`api-rbac.txt`). On the client, the queue is namespaced per (user, tenant) so a tenant/user switch
  cannot expose the previous context's commands (`flutter-offline.txt`, F2).
- **Immutable first `READY_FOR_PICKUP` (FR-076/077, Rule 10):** the anchor is written exactly once at the
  DB boundary (`UNIQUE(tenant_id, order_id)`); a return to `REWORK` and a second ready never mutate it
  (`ready-anchor.txt`).
- **Idempotency (FR-079):** a replay with the same `client_reference` returns the original result; a
  changed payload under the same reference is rejected (`production-transitions.txt`, and F1/F3
  client-side).
- **Concurrency:** optimistic `expected_version`; a stale version changes nothing
  (`production-transitions.txt`).
- **Append-only history:** production, QC, and rework events refuse UPDATE/DELETE at the engine
  (`migrations.txt`, `rework.txt`).
- **Offline honesty (Rule 29):** F2/F3/F4 prove encrypted-at-rest persistence, survival across a fresh
  queue instance (process death), the terminal-immutable `SYNCED` guard (worker/manual race), timeout-
  after-commit reconciliation via idempotent replay, and that no queued command — and no `READY` — is
  ever rendered as success before a canonical acknowledgement, including a forged/malformed 200.

## Residual risks (stated, not hidden)

1. **Single-maintainer governance (DEC-0017).** Independent human review is `ABSENT`; the compensating
   controls are the ruleset, exact-SHA CI, deterministic + adversarially-tested validators, and recorded
   internal re-verification. These are load-bearing, not equivalent to a second human reader.
2. **Encryption-at-rest is delegated to the platform keystore** (`SecureCredentialStore` /
   `flutter_secure_storage`); the queue contract is tested against an in-memory double. The device-level
   cryptography is the platform's and is not re-implemented or re-proven here — no new crypto dependency
   was added (Rule 37). This is a bounded claim, not a full on-device crypto audit.
3. **Background sync runs while the production surface is open** (periodic + reconnect + manual). Durable
   work survives the surface being closed and resumes on reopen; a headless background service is not
   claimed.
4. **Connectivity detection** uses an optimistic monitor (no connectivity plugin added, Rule 37); offline
   is discovered from a failed send and backed off. No false claim of OS-level connectivity detection.
5. **Debug-only runtime, no deployment.** Deployment remains `ABSENT`; nothing here authorises it.
6. **Batch / operator-assignment tables are prepared, unexercised infrastructure** — no HTTP surface, no
   UI, no test beyond their schema presence.

`GO` is the owner's to confer after merge, post-merge local verification, and authoritative `main` CI on
the exact merge SHA. **No Step 6 `GO` tag exists or may be created on this branch.**
