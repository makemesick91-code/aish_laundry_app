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
# STATUS HONESTY: at the DEC-0039 governance transition the Step 7 runtime did not
# exist, and the backend and UI sections SKIPPED with a visible reason rather than
# reporting an unbuilt feature as verified.
#
# THAT IS NO LONGER TRUE AND THIS FILE NO LONGER SAYS IT IS. The runtime exists, so
# both former SKIPs are now MANDATORY GATES: the modules, the portal views, the
# test suites, and the operator UI must be PRESENT (a hard fail if not — a missing
# module is a regression, not a reason to skip), and their suites must pass.
#
# The only remaining skips are genuine ENVIRONMENT preconditions — no composer
# install, no .env, no PostgreSQL/Redis, no Flutter on PATH. Those are honest on a
# developer laptop and never occur in CI. A SKIP is never folded into the pass
# count.
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
# The verifier's OWN adversarial harness (Rule 47, Rule 33). It proves that a
# missing Step 7 runtime FAILS rather than skipping — which is the entire value
# of turning the two transitional SKIPs into mandatory gates. It runs its
# removal tests in a disposable copy and asserts the canonical tree is unchanged.
gate "verify-step-07 adversarial harness" bash scripts/test-verify-step-07.sh
gate "no float in any money path"          python3 scripts/validate-money-rules.py

# ---------------------------------------------------------------------------
hdr "3. Step 7 backend runtime (customer tracking + notification)"
#
# THESE ARE NOW MANDATORY GATES. At the DEC-0039 governance transition this
# section was a visible SKIP, because the runtime did not exist and reporting an
# unbuilt feature as verified would have been a false claim. The runtime now
# exists, so the SKIP is gone and a missing or failing suite FAILS the step.
#
# THE MODULE PRESENCE CHECK IS A HARD FAIL, NOT A SKIP. This is the important
# difference from the transitional version: if the Tracking or Notification
# module disappears, that is a REGRESSION in a step that has already built them,
# and a verifier that skipped on absence would report a green run for a tree with
# the feature deleted (Rule 01).
gate "Step 7 Tracking module present"      test -d backend/app/Modules/Tracking
gate "Step 7 Notification module present"  test -d backend/app/Modules/Notification
gate "Step 7 public portal views present"  test -f backend/resources/views/tracking/show.blade.php
gate "Step 7 backend test suites present"  test -d backend/tests/Feature/Tracking

# The SERVICE preconditions remain honest skips: a developer without PostgreSQL
# running has not broken anything, and a false FAIL would train people to ignore
# this verifier. CI always has the services, so the gates always execute there.
if [ ! -d backend/vendor ]; then
  skip "Step 7 backend suites" "backend/vendor missing (composer install)"
elif [ ! -f backend/.env ]; then
  skip "Step 7 backend suites" "backend/.env missing"
elif ! bash scripts/check-dev-services.sh >/dev/null 2>&1; then
  skip "Step 7 backend suites" "development PostgreSQL/Redis not reachable (bash scripts/start-dev-services.sh)"
else
  gate "live schema within Step 7 scope" \
    bash -c 'cd backend && set -a && . ./.env && set +a && php scripts/ci/assert-schema-scope.php'
  # Deliberately NOT sourcing .env for the suites: phpunit.xml pins CACHE_STORE
  # to the array driver, and exported env vars would override it — putting the
  # rate limiter on the shared Redis and making the suite fail on leftover state
  # from a previous run rather than on anything in the tree.
  gate "Step 7 tracking backend suite (token lifecycle, portal projection, public API, OTP, isolation, RBAC)" \
    bash -c 'cd backend && php artisan test --filter=Tracking'
  gate "Step 7 notification backend suite (outbox, dedup, consent, quiet hours, provider abstraction, FR-099 decoupling)" \
    bash -c 'cd backend && php artisan test --filter=Notification'
fi

# ---------------------------------------------------------------------------
hdr "4. Step 7 public tracking portal and operator surface"
#
# ALSO MANDATORY NOW. The portal is exercised by the BACKEND suite, because it is
# server-rendered: `PublicTrackingApiTest` drives `/lacak/{token}` and asserts the
# headers, the robots meta tag, escaping of tenant-supplied markup, the absence of
# any script or remote asset, and the absence of an app-install prompt. There is
# no separate portal gate here because there is no separate portal runtime — and
# inventing one would be a gate that verifies nothing.
gate "operator tracking UI present"        test -d apps/ops_android/lib/src/tracking
gate "operator tracking UI test present"   test -f apps/ops_android/test/tracking_test.dart

if [ -n "${DART_BIN}" ]; then
  gate "operator tracking/notification UI widget tests" \
    bash -c 'cd apps/ops_android && flutter test test/tracking_test.dart'
else
  skip "operator tracking/notification UI widget tests" "Flutter/Dart not on PATH"
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
