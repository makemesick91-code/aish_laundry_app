#!/usr/bin/env bash
#
# Canonical Step 6 verifier — PRODUCTION OPERATIONS.
#
# Runs the REAL gates. There is no placeholder success anywhere in this file:
# every line below either executes a command whose exit status decides the
# result, or is reported as SKIPPED with a visible reason (Rule 01). The final
# exit is decided by the FAIL count alone — never by the last command run (a
# checksum, a Flutter gate), so a green checksum can never mask a red gate.
#
# It DELEGATES the Step 0-5 regression to `verify-step-05.sh` (which delegates
# onward to 04 … 00) rather than restating it, and adds the Step 6 gates: the
# DEC-0037 guard transition, the production runtime, tenant isolation, RBAC, the
# state machine, quality control and rework, the immutable first-READY anchor,
# idempotency and concurrency, and the Flutter offline-first surface (F1-F4).
#
# Requires:
#   - PHP + composer install (backend/vendor) for the backend gates
#   - backend/.env and development PostgreSQL + Redis for the DB gates
#   - Flutter/Dart on PATH for the Flutter gates
# A precondition that is not met is a visible SKIP with its reason, never a
# false FAIL and never a silent pass.
#
# Exit 0 = every executed gate passed. Skips are counted and named separately
# and are never folded into the pass count.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}" || exit 2

PASS=0; FAIL=0; SKIP=0
FAILED_GATES=()
SKIPPED_GATES=()

g()  { printf '\033[32m%s\033[0m' "$*"; }
r()  { printf '\033[31m%s\033[0m' "$*"; }
y()  { printf '\033[33m%s\033[0m' "$*"; }
hdr(){ printf '\n\033[1m== %s ==\033[0m\n' "$*"; }

GATE_SKIP_RC=78

gate() {
  local label="$1"; shift
  local rc=0
  "$@" >/dev/null 2>&1 || rc=$?
  if [ "${rc}" -eq 0 ]; then
    printf '  %s  %s\n' "$(g 'PASS')" "${label}"; PASS=$((PASS + 1))
  elif [ "${rc}" -eq "${GATE_SKIP_RC}" ]; then
    skip "${label}" "precondition not met in this environment (exit ${GATE_SKIP_RC})"
  else
    printf '  %s  %s\n' "$(r 'FAIL')" "${label}"; FAIL=$((FAIL + 1)); FAILED_GATES+=("${label}")
  fi
}

skip() {
  printf '  %s  %s — %s\n' "$(y 'SKIP')" "$1" "$2"
  SKIP=$((SKIP + 1)); SKIPPED_GATES+=("$1 ($2)")
}

# Resolve Flutter/Dart once so every Flutter gate agrees on availability.
DART_BIN=""
if command -v dart >/dev/null 2>&1; then
  DART_BIN="dart"
elif [ -x "${HOME}/flutter/bin/dart" ]; then
  DART_BIN="${HOME}/flutter/bin/dart"
  export PATH="${HOME}/flutter/bin:${PATH}"
fi

SHA="$(git rev-parse HEAD)"
echo "========================================================================"
echo "STEP 6 CANONICAL VERIFICATION — PRODUCTION OPERATIONS"
echo "========================================================================"
echo "  commit    : ${SHA}"
echo "  timestamp : $(date -u '+%Y-%m-%dT%H:%M:%SZ') (UTC)"
echo "  authorised: canonical roadmap (Master Source §24), guard transition DEC-0037"

# ---------------------------------------------------------------------------
hdr "1. Step 0-5 regression (delegated, not restated)"
# verify-step-05.sh uses three exit codes (0 pass, 78 skip, else fail) and this
# gate must not flatten them: a genuine Step 0-5 failure must fail Step 6.
step5_rc=0
bash scripts/verify-step-05.sh >/dev/null 2>&1 || step5_rc=$?
if [ "${step5_rc}" -eq 0 ]; then
  printf '  %s  %s\n' "$(g 'PASS')" "Step 0-5 regression (verify-step-05.sh)"; PASS=$((PASS + 1))
elif [ "${step5_rc}" -eq "${GATE_SKIP_RC}" ]; then
  skip "Step 0-5 regression (verify-step-05.sh)" "a delegated gate skipped (Flutter/DB precondition); run it directly to see which"
else
  printf '  %s  %s (exit %s)\n' "$(r 'FAIL')" "Step 0-5 regression (verify-step-05.sh)" "${step5_rc}"
  FAIL=$((FAIL + 1)); FAILED_GATES+=("Step 0-5 regression")
fi

# ---------------------------------------------------------------------------
hdr "2. Step 6 authorization and governance"
gate "DEC-0037 present and ACCEPTED"       bash -c 'grep -qE "^\*\*Status:\*\* ACCEPTED" docs/decisions/DEC-0037-*.md'
# Version-agnostic: derive the expected version from the single authoritative pin
# and assert the Master Source header matches it. A stale literal pin is exactly
# the drift the governance suite exists to catch, so the verifier carries none.
gate "Master Source header matches the pinned canonical version" \
  python3 -c 'import re, sys, pathlib
pin = re.search(r"VERSION\s*=\s*\"([0-9.]+)\"", pathlib.Path("scripts/validate-master-source.py").read_text()).group(1)
ms = pathlib.Path("docs/MASTER_SOURCE.md").read_text()
sys.exit(0 if f"Document version: {pin}" in ms else 1)'
gate "MASTER_SOURCE checksum matches"      bash -c 'cd docs && sha256sum -c MASTER_SOURCE.sha256'
gate "Rule 50 (Step 4 status) present"     test -f .claude/rules/50-current-step-04-status.md
gate "Step 6 design doc present"           test -f docs/step-06/STEP_06_DESIGN.md
gate "Step 6 requirement matrix present"   test -f docs/quality/STEP_06_REQUIREMENT_MATRIX.md
gate "governance validator suite"          bash scripts/validate-governance.sh
gate "runtime scope guard (classify)"      python3 scripts/validate-runtime-scope.py
gate "DEC-0037 label audit"                python3 scripts/validate-dec-0037-labels.py
gate "DEC-0035 label audit"                python3 scripts/validate-dec-0035-labels.py
gate "Step 6 validator adversarial harness" bash scripts/test-step-06-validators.sh
gate "Step 5 validator adversarial harness (step-aware)" bash scripts/test-step-05-validators.sh
gate "no float in any money path"          python3 scripts/validate-money-rules.py

# ---------------------------------------------------------------------------
hdr "3. Step 6 backend runtime (production operations)"
# Backend gates need composer dependencies, a backend/.env, and a reachable
# development PostgreSQL (Rule 43). Each missing precondition is a visible SKIP,
# never a false FAIL that reads like a schema or test violation. When the
# preconditions ARE met (CI, a proper local run) they execute and their exit
# status decides the result. A gate that could not run has verified nothing.
if [ ! -d backend/vendor ]; then
  skip "live schema within Step 6 scope" "backend/vendor missing (composer install)"
  skip "Step 6 production backend suite" "backend/vendor missing (composer install)"
elif [ ! -f backend/.env ]; then
  skip "live schema within Step 6 scope" "backend/.env missing"
  skip "Step 6 production backend suite" "backend/.env missing"
elif ! bash scripts/check-dev-services.sh >/dev/null 2>&1; then
  skip "live schema within Step 6 scope" "development PostgreSQL/Redis not reachable (bash scripts/start-dev-services.sh)"
  skip "Step 6 production backend suite" "development PostgreSQL/Redis not reachable (bash scripts/start-dev-services.sh)"
else
  # The live schema must now contain the Step 6 production tables and NO Step 7+
  # table (assert-schema-scope.php advanced to Step 6 scope under DEC-0037).
  gate "live schema within Step 6 scope" \
    bash -c 'cd backend && set -a && . ./.env && set +a && php scripts/ci/assert-schema-scope.php'
  # The Step 6 production suite runs against real PostgreSQL (Rule 43) and — for
  # the FR-083 QC defect-photo evidence — the real private MinIO bucket: the
  # composite tenant-bound persistence, the server-authoritative state machine,
  # quality control and rework, the IMMUTABLE first-READY_FOR_PICKUP anchor,
  # append-only production/QC/rework/batch/evidence history, RBAC, cross-tenant
  # 404, exact idempotency, changed-payload replay rejection, optimistic
  # concurrency, the FR-074 batch lifecycle/isolation/membership, and the FR-083
  # upload/validation/private-signed-URL retrieval — and that no Step 5 financial
  # snapshot is mutated. The --filter matches the Tests\Feature\Production
  # namespace, which includes ProductionBatchTest and QualityControlEvidenceTest.
  gate "Step 6 production backend suite (state machine, QC, rework, ready, batch FR-074, evidence FR-083, RBAC, idempotency, concurrency)" \
    bash -c 'cd backend && php artisan test --filter=Production'
fi

# ---------------------------------------------------------------------------
hdr "4. Step 6 Flutter offline-first surface (F1-F4)"
# The offline-first production surface is a first-class Step 6 deliverable, so a
# backend-only verifier is insufficient. These need Flutter/Dart on PATH; where
# it is absent they are a visible SKIP (the runtime-foundation CI runs them).
if [ -n "${DART_BIN}" ]; then
  gate "Flutter analyze (domain, networking, offline_sync, ops_android)" \
    dart analyze packages/domain packages/networking packages/offline_sync apps/ops_android
  gate "F1 — production contract + repository tests" \
    dart test packages/networking/test/production_repository_test.dart
  gate "F2 — durable offline queue tests" \
    dart test packages/offline_sync/test/production_command_queue_test.dart
  gate "F3 — sync worker + reconciliation tests" \
    dart test packages/offline_sync/test/production_sync_worker_test.dart
  # F4 widget tests are CWD-sensitive (a guard test resolves Directory('lib')),
  # so they run from the app directory.
  gate "F4 — production operator UI widget tests" \
    bash -c 'cd apps/ops_android && flutter test test/production_test.dart'
  # F5 — FR-074 batch operator UI (list/detail/create/add-remove/close, offline
  # honesty, RBAC-gated controls).
  gate "F5 — FR-074 batch operator UI widget tests" \
    bash -c 'cd apps/ops_android && flutter test test/production_batch_test.dart'
  # F6 — FR-083 QC defect-photo flow (durable evidence upload from an injected
  # photo seam; the photo is optional).
  gate "F6 — FR-083 QC defect-photo UI widget tests" \
    bash -c 'cd apps/ops_android && flutter test test/production_qc_evidence_test.dart'
  # The production and batch surfaces are actually wired into the real Ops entry
  # point, not merely present as files (Rule 01 — a file is not a wired feature).
  gate "production surface wired into the Ops router" \
    bash -c 'grep -q "ProductionQueueScreen" apps/ops_android/lib/src/routing/ops_router.dart'
  gate "batch surface wired into the Ops router (FR-074)" \
    bash -c 'grep -q "ProductionBatchListScreen" apps/ops_android/lib/src/routing/ops_router.dart'
else
  skip "Step 6 Flutter offline-first gates (F1-F4)" "Flutter/Dart not on PATH in this environment"
fi

# ---------------------------------------------------------------------------
hdr "5. Public repository safety and working tree"
gate "secret scan"                         bash scripts/validate-secrets.sh
gate "public repository safety (canonical scan)" bash scripts/validate-public-repository-safety.sh
gate "working tree clean"                  bash -c '[ "$(git status --porcelain | wc -l)" -eq 0 ]'

# ---------------------------------------------------------------------------
echo
echo "========================================================================"
echo "STEP 6 VERIFICATION SUMMARY"
echo "========================================================================"
echo "  commit : ${SHA}"
printf '  %s %d   %s %d   %s %d\n' "$(g 'PASS')" "${PASS}" "$(r 'FAIL')" "${FAIL}" "$(y 'SKIP')" "${SKIP}"
if [ "${#FAILED_GATES[@]}" -gt 0 ]; then
  echo "  failed gates:"; for gt in "${FAILED_GATES[@]}"; do echo "    - ${gt}"; done
fi
if [ "${#SKIPPED_GATES[@]}" -gt 0 ]; then
  echo "  skipped gates:"; for gt in "${SKIPPED_GATES[@]}"; do echo "    - ${gt}"; done
fi
echo "------------------------------------------------------------------------"
# The exit is decided by the FAIL count ALONE. Not by the checksum gate above,
# not by the last Flutter gate — a mandatory failure anywhere returns non-zero.
[ "${FAIL}" -eq 0 ] || exit 1
exit 0
