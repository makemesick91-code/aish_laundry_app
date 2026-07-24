# Step 6 — Production Operations: Evidence Pack

**Status:** `IN PROGRESS` — this pack is populated as each Step 6 unit is implemented and verified.
**Baseline SHA:** `9c23eca41f45963b61a04f936e69bf9b71997552` · **Scope:** [DEC-0037](../../docs/decisions/DEC-0037-step-06-runtime-scope-transition.md)

Every artefact in this directory is captured output bound to an exact 40-character commit SHA (Rule 01,
DEC-0013), sanitised (no secrets, no real personal data — Rule 23/45). A requirement is only proven when
its captured evidence exists at the candidate SHA; until then the plan in
[`../../docs/quality/STEP_06_REQUIREMENT_MATRIX.md`](../../docs/quality/STEP_06_REQUIREMENT_MATRIX.md)
reads `NOT IMPLEMENTED`.

## Index (populated during Phases 2–5)

| Artefact | Requirement(s) | Status |
| --- | --- | --- |
| `governance.txt` | DEC-0037 transition | pending |
| `adversarial-step06.txt` | Rule 47 guard transition | pending |
| `migrations.txt` | FR-074, FR-076, DB constraint plan | pending |
| `production-transitions.txt` | FR-071, FR-072, FR-073, FR-078, FR-080 | pending |
| `production-item.txt` | FR-075 | pending |
| `ready-anchor.txt` | FR-076, FR-077 | pending |
| `qc-gate.txt`, `rework.txt`, `qc-evidence.txt` | FR-081 … FR-085 | pending |
| `idempotency.txt`, `offline-queue.txt` | FR-079 | pending |
| `tenant-isolation.txt` | TEN-*, Rule 48 | pending |
| `flutter.txt` | Ops Android surface + offline-first | pending |
| `verify-step-06.txt` | full Step 6 gate | pending |

No `GO` is claimed here; `GO` is the owner's to confer after merge and post-merge verification.
