#!/usr/bin/env bash
#
# Canonical Step 7 verifier — CUSTOMER TRACKING AND WHATSAPP.
#
# Runs the REAL gates. There is no placeholder success anywhere in this file:
# every line below either executes a command whose exit status decides the
# result, or is reported as SKIPPED with a visible reason (Rule 01). The final
# exit is decided by the FAIL count alone — never by the last command run (a
# checksum, a Flutter gate), so a green checksum can never mask a red gate.
#
# It DELEGATES the Step 0-6 regression to `verify-step-06.sh` (which delegates
# onward to 05 … 00) rather than restating it, and adds the Step 7 gates: the
# DEC-0039 guard transition, and — once the tracking/notification runtime exists —
# the public tracking-token lifecycle, the portal projection and headers, OTP, the
# notification outbox with consent/quiet-hours/dedup, the provider abstraction, and
# the public-web + operator surfaces.
#
# STATUS HONESTY: at the DEC-0039 governance transition the Step 7 runtime does not
# yet exist. The backend and Flutter sections below therefore SKIP with a visible
# reason until a `backend/app/Modules/Tracking` (and the Notification module and
# their test suites) are present. A SKIP is never folded into the pass count, and a
# feature that has not been built is never reported as verified.
#
# Requires (once the runtime exists):
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
echo "STEP 7 CANONICAL VERIFICATION — CUSTOMER TRACKING AND WHATSAPP"
echo "========================================================================"
echo "  commit    : ${SHA}"
echo "  timestamp : $(date -u '+%Y-%m-%dT%H:%M:%SZ') (UTC)"
echo "  authorised: canonical roadmap (Master Source §24), guard transition DEC-0039"

# ---------------------------------------------------------------------------
hdr "1. Step 0-6 regression (delegated, not restated)"
# verify-step-06.sh uses three exit codes (0 pass, 78 skip, else fail) and this
# gate must not flatten them: a genuine Step 0-6 failure must fail Step 7.
step6_rc=0
bash scripts/verify-step-06.sh >/dev/null 2>&1 || step6_rc=$?
if [ "${step6_rc}" -eq 0 ]; then
  printf '  %s  %s\n' "$(g 'PASS')" "Step 0-6 regression (verify-step-06.sh)"; PASS=$((PASS + 1))
elif [ "${step6_rc}" -eq "${GATE_SKIP_RC}" ]; then
  skip "Step 0-6 regression (verify-step-06.sh)" "a delegated gate skipped (Flutter/DB precondition); run it directly to see which"
else
  printf '  %s  %s (exit %s)\n' "$(r 'FAIL')" "Step 0-6 regression (verify-step-06.sh)" "${step6_rc}"
  FAIL=$((FAIL + 1)); FAILED_GATES+=("Step 0-6 regression")
fi

# ---------------------------------------------------------------------------
hdr "2. Step 7 authorization and governance"
gate "DEC-0039 present and ACCEPTED"       bash -c 'grep -qE "^\*\*Status:\*\* ACCEPTED" docs/decisions/DEC-0039-*.md'
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
gate "Step 7 requirement matrix present"   test -f docs/quality/STEP_07_REQUIREMENT_MATRIX.md
gate "Step 7 evidence pack present"        test -f evidence/step-07/README.md
gate "governance validator suite"          bash scripts/validate-governance.sh
gate "runtime scope guard (classify)"      python3 scripts/validate-runtime-scope.py
gate "DEC-0039 label audit"                python3 scripts/validate-dec-0039-labels.py
gate "DEC-0037 label audit (step-aware)"   python3 scripts/validate-dec-0037-labels.py
gate "DEC-0035 label audit (step-aware)"   python3 scripts/validate-dec-0035-labels.py
gate "Step 7 validator adversarial harness" bash scripts/test-step-07-validators.sh
gate "no float in any money path"          python3 scripts/validate-money-rules.py

# ---------------------------------------------------------------------------
hdr "3. Step 7 backend runtime (customer tracking + notification)"
# The Step 7 backend is the Tracking module (public tracking-token lifecycle,
# portal projection, OTP) and the Notification module (provider abstraction,
# outbox, consent/quiet-hours/dedup). Until they exist this section is a visible
# SKIP — honest at the DEC-0039 governance transition, and it flips to real,
# service-gated gates as the runtime lands (Rule 01, Rule 36 hard rule 6).
if [ ! -d backend/app/Modules/Tracking ] && [ ! -d backend/app/Modules/Notification ]; then
  skip "Step 7 tracking backend suite" "Tracking/Notification modules not yet implemented (DEC-0039 governance transition only)"
elif [ ! -d backend/vendor ]; then
  skip "Step 7 tracking backend suite" "backend/vendor missing (composer install)"
elif [ ! -f backend/.env ]; then
  skip "Step 7 tracking backend suite" "backend/.env missing"
elif ! bash scripts/check-dev-services.sh >/dev/null 2>&1; then
  skip "Step 7 tracking backend suite" "development PostgreSQL/Redis not reachable (bash scripts/start-dev-services.sh)"
else
  gate "live schema within Step 7 scope" \
    bash -c 'cd backend && set -a && . ./.env && set +a && php scripts/ci/assert-schema-scope.php'
  gate "Step 7 tracking backend suite (token lifecycle, portal projection, OTP, tenant isolation, RBAC, idempotency)" \
    bash -c 'cd backend && php artisan test --filter=Tracking'
  gate "Step 7 notification backend suite (provider abstraction, outbox, consent, quiet hours, dedup, order-state decoupling)" \
    bash -c 'cd backend && php artisan test --filter=Notification'
fi

# ---------------------------------------------------------------------------
hdr "4. Step 7 public tracking portal and operator surface"
# The public tracking portal and the operator tracking/notification UI are
# first-class Step 7 deliverables. Until they exist this section is a visible SKIP.
if [ -n "${DART_BIN}" ] && [ -d apps/ops_android/lib/src/tracking ]; then
  gate "operator tracking/notification UI widget tests" \
    bash -c 'cd apps/ops_android && flutter test test/tracking_test.dart'
else
  skip "Step 7 public portal + operator UI gates" "tracking/notification UI not yet implemented, or Flutter/Dart not on PATH"
fi

# ---------------------------------------------------------------------------
hdr "5. Public repository safety and working tree"
gate "secret scan"                         bash scripts/validate-secrets.sh
gate "public repository safety (canonical scan)" bash scripts/validate-public-repository-safety.sh
gate "working tree clean"                  bash -c '[ "$(git status --porcelain | wc -l)" -eq 0 ]'

# ---------------------------------------------------------------------------
echo
echo "========================================================================"
echo "STEP 7 VERIFICATION SUMMARY"
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
