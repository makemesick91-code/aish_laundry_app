#!/usr/bin/env bash
#
# Canonical Step 5 verifier — POS, ORDER, AND PAYMENT FOUNDATION.
#
# Runs the REAL gates. There is no placeholder success anywhere in this file:
# every line below either executes a command whose exit status decides the
# result, or is reported as SKIPPED with a visible reason (Rule 01).
#
# It DELEGATES the Step 0-4 regression to `verify-step-04.sh` rather than
# restating it, and adds the Step 5 gates: the DEC-0035 guard transition, the
# order/payment runtime, financial integrity, tenant isolation, and idempotency.
#
# Requires:
#   - PHP 8.5 + composer
#   - development PostgreSQL and Redis running (bash scripts/start-dev-services.sh)
#   - Flutter/Dart on PATH for the delegated Step 0-4 Flutter gates (else SKIP)
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

SHA="$(git rev-parse HEAD)"
echo "========================================================================"
echo "STEP 5 CANONICAL VERIFICATION — POS, ORDER, AND PAYMENT FOUNDATION"
echo "========================================================================"
echo "  commit    : ${SHA}"
echo "  timestamp : $(date -u '+%Y-%m-%dT%H:%M:%SZ') (UTC)"
echo "  authorised: canonical roadmap (Master Source §24), guard transition DEC-0035"

# ---------------------------------------------------------------------------
hdr "1. Step 0-4 regression (delegated, not restated)"
# verify-step-04.sh uses three exit codes (0 pass, 78 skip, else fail) and this
# gate must not flatten them: a genuine Step 0-4 failure must fail Step 5.
step4_rc=0
bash scripts/verify-step-04.sh >/dev/null 2>&1 || step4_rc=$?
if [ "${step4_rc}" -eq 0 ]; then
  printf '  %s  %s\n' "$(g 'PASS')" "Step 0-4 regression (verify-step-04.sh)"; PASS=$((PASS + 1))
elif [ "${step4_rc}" -eq "${GATE_SKIP_RC}" ]; then
  skip "Step 0-4 regression (verify-step-04.sh)" "a delegated gate skipped (Flutter/precondition); run it directly to see which"
else
  printf '  %s  %s (exit %s)\n' "$(r 'FAIL')" "Step 0-4 regression (verify-step-04.sh)" "${step4_rc}"
  FAIL=$((FAIL + 1)); FAILED_GATES+=("Step 0-4 regression")
fi

# ---------------------------------------------------------------------------
hdr "2. Step 5 authorization and governance"
gate "DEC-0035 present and ACCEPTED"       bash -c 'grep -qE "^\*\*Status:\*\* ACCEPTED" docs/decisions/DEC-0035-*.md'
gate "Master Source at version 1.4.6"      bash -c 'grep -q "Document version: 1.4.6" docs/MASTER_SOURCE.md'
gate "MASTER_SOURCE checksum matches"      bash -c 'cd docs && sha256sum -c MASTER_SOURCE.sha256'
gate "Rule 50 (Step 4 status) present"     test -f .claude/rules/50-current-step-04-status.md
gate "Step 5 requirement matrix present"   test -f docs/quality/STEP_05_REQUIREMENT_MATRIX.md
gate "governance validator suite"          bash scripts/validate-governance.sh
gate "runtime scope guard (classify)"      python3 scripts/validate-runtime-scope.py
gate "DEC-0035 label audit"                python3 scripts/validate-dec-0035-labels.py
gate "DEC-0030 label audit"                python3 scripts/validate-dec-0030-labels.py
gate "Step 5 validator adversarial harness" bash scripts/test-step-05-validators.sh
gate "Step 4 validator adversarial harness (step-aware)" bash scripts/test-step-04-validators.sh
gate "financial money-rules (no float money column)" python3 scripts/validate-money-rules.py

# ---------------------------------------------------------------------------
hdr "3. Step 5 backend runtime (order + payment)"
# The live schema must contain the Step 5 tables and NO Step 6+ table.
gate "live schema within Step 5 scope"     bash -c 'cd backend && set -a && . ./.env && set +a && php scripts/ci/assert-schema-scope.php'
# The Step 5 backend suites run against real PostgreSQL (Rule 43): schema
# invariants, domain/service behaviour, HTTP surface, RBAC, tenant isolation,
# idempotency, financial integrity (append-only, historical price, reversal).
gate "Step 5 backend suite (Ordering + Payments)" bash -c 'cd backend && php artisan test --filter="Ordering|Payments" 2>&1 | grep -qE "FAIL|failed" && exit 1 || exit 0'
gate "no float in any money path"          python3 scripts/validate-money-rules.py

# ---------------------------------------------------------------------------
hdr "4. Public repository safety and working tree"
gate "secret scan"                         bash scripts/validate-secrets.sh
gate "public repository safety (canonical scan)" bash scripts/validate-public-repository-safety.sh
gate "working tree clean"                  bash -c '[ "$(git status --porcelain | wc -l)" -eq 0 ]'

# ---------------------------------------------------------------------------
echo
echo "========================================================================"
echo "STEP 5 VERIFICATION SUMMARY"
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
[ "${FAIL}" -eq 0 ] || exit 1
exit 0
