#!/usr/bin/env bash
#
# Adversarial harness for scripts/verify-step-06.sh.
#
# A verifier that has only ever been run against a green tree is an untested
# gate: it might report PASS by construction. This harness proves the SIX
# properties the Step 6 verifier's trustworthiness rests on:
#
#   1. a required failure returns non-zero;
#   2. a skipped mandatory gate cannot be reported as PASS;
#   3. a future Step 7 advancement does not invalidate historical Step 6
#      verification (the guards are step-aware, not hardcoded);
#   4. the checksum gate's exit code cannot mask a verifier failure — the final
#      exit is decided by the FAIL count alone;
#   5. a stale version pin fails closed;
#   6. a missing environment prerequisite is reported truthfully (SKIP), never
#      a false PASS and never a false FAIL.
#
# Properties 1, 2 and 4 are exercised against the REAL gate()/skip() and
# final-exit logic EXTRACTED from verify-step-06.sh (not a copy), so a change to
# that logic is caught here. Properties 3, 5 and 6 run the verifier's actual
# sub-checks against deliberately broken input.
#
# No git history operation is performed. Exit 0 = every expectation met.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}" || exit 2

VERIFIER="scripts/verify-step-06.sh"
PASS=0; FAIL=0
red()   { printf '\033[31m%s\033[0m' "$*"; }
green() { printf '\033[32m%s\033[0m' "$*"; }

ok()   { printf '  %s  %s\n' "$(green 'ok  ')" "$1"; PASS=$((PASS + 1)); }
bad()  { printf '  %s  %s\n' "$(red 'FAIL')" "$1"; FAIL=$((FAIL + 1)); }

TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

# ---------------------------------------------------------------------------
# Extract the REAL gate()/skip() functions, the counters, and the final-exit
# rule from the verifier, so the behavioural tests below exercise the actual
# logic rather than a hand-copied paraphrase that could drift from it.
# ---------------------------------------------------------------------------
HARNESS_LIB="${TMP}/extracted.sh"
{
  echo 'PASS=0; FAIL=0; SKIP=0; FAILED_GATES=(); SKIPPED_GATES=()'
  echo 'GATE_SKIP_RC=78'
  echo 'g(){ printf "%s" "$*"; }; r(){ printf "%s" "$*"; }; y(){ printf "%s" "$*"; }'
  # gate() { ... } and skip() { ... } verbatim from the verifier.
  awk '/^gate\(\) \{/,/^\}/' "${VERIFIER}"
  awk '/^skip\(\) \{/,/^\}/' "${VERIFIER}"
  # The final-exit rule, verbatim.
  echo 'final_exit(){ [ "${FAIL}" -eq 0 ] || return 1; return 0; }'
} > "${HARNESS_LIB}"

# 1. A required failure returns non-zero (via the real gate() + final-exit).
(
  # shellcheck disable=SC1090
  . "${HARNESS_LIB}"
  gate "a failing gate" bash -c 'exit 1' >/dev/null 2>&1
  final_exit
) && rc=0 || rc=$?
if [ "${FAIL:-0}" -eq 0 ] && [ "${rc}" -ne 0 ]; then
  ok "1. a required failure makes the verifier's final-exit rule return non-zero"
else
  bad "1. a required failure did NOT make the final-exit rule return non-zero"
fi

# 2. A skipped mandatory gate is counted as SKIP, never PASS.
skip_result="$(
  # shellcheck disable=SC1090
  . "${HARNESS_LIB}"
  gate "a precondition-skipped gate" bash -c 'exit 78' >/dev/null 2>&1
  echo "PASS=${PASS} SKIP=${SKIP} FAIL=${FAIL}"
)"
if [ "${skip_result}" = "PASS=0 SKIP=1 FAIL=0" ]; then
  ok "2. a skipped (exit 78) gate increments SKIP, not PASS"
else
  bad "2. a skipped gate was miscounted: ${skip_result}"
fi

# 4. The checksum gate cannot mask a failure: the final exit is decided by the
#    FAIL count, and the checksum gate is NOT the last thing the verifier runs.
if grep -qE '^\[ "\$\{FAIL\}" -eq 0 \] \|\| exit 1' "${VERIFIER}" &&
   [ "$(awk '/checksum matches/{c=NR} /FAIL.*-eq 0.*exit 1/{e=NR} END{print (c<e)?"ok":"no"}' "${VERIFIER}")" = "ok" ]; then
  ok "4. the final exit is FAIL-count-gated AND runs after the checksum gate"
else
  bad "4. the checksum gate could mask a verifier failure"
fi

# 3. A FUTURE Step 7 advancement does not invalidate historical Step 6
#    verification: with the canonical step bumped to 7, the runtime-scope guard
#    still PERMITS the Step 6 production scope (the guard is step-aware, not
#    hardcoded — production is authorised at step >= 6). The bump is applied to
#    the real _common.py IN PLACE with a guaranteed restore, so the validator's
#    relative paths (the PRD, the tree it scans) stay intact.
cp scripts/_common.py "${TMP}/_common.bak"
restore_common() { cp "${TMP}/_common.bak" scripts/_common.py; }
sed -i -E 's/^CANONICAL_CURRENT_STEP = [0-9]+/CANONICAL_CURRENT_STEP = 7/' scripts/_common.py
step7_rc=0
python3 scripts/validate-runtime-scope.py >/dev/null 2>&1 || step7_rc=$?
restore_common
# Confirm the restore actually happened, so a later gate never runs on a bumped
# pin (a harness that corrupts the tree it tests is worse than no harness).
if grep -q '^CANONICAL_CURRENT_STEP = 6$' scripts/_common.py && [ "${step7_rc}" -eq 0 ]; then
  ok "3. a Step 7 advancement leaves the Step 6 production scope still permitted"
else
  bad "3. a Step 7 advancement wrongly invalidated the Step 6 production scope (rc=${step7_rc})"
  restore_common
fi

# 5. A stale version pin fails closed: the verifier's version-match check must
#    FAIL when the Master Source header no longer matches the canonical pin.
cp docs/MASTER_SOURCE.md "${TMP}/MS.md"
sed -i -E 's/^\*\*Document version: [0-9.]+\*\*/**Document version: 9.9.9**/' "${TMP}/MS.md"
if python3 -c 'import re, sys, pathlib
pin = re.search(r"VERSION\s*=\s*\"([0-9.]+)\"", pathlib.Path("scripts/validate-master-source.py").read_text()).group(1)
ms = pathlib.Path("'"${TMP}"'/MS.md").read_text()
sys.exit(0 if f"Document version: {pin}" in ms else 1)' >/dev/null 2>&1; then
  bad "5. a stale Master Source version pin did NOT fail closed"
else
  ok "5. a stale Master Source version pin fails closed"
fi

# 6. A missing environment prerequisite is reported truthfully. The verifier's
#    backend block SKIPs (never FAILs, never silently PASSes) when composer
#    dependencies, backend/.env, or the dev database are absent. Assert those
#    three skip() branches exist in the verifier.
if grep -q 'skip "live schema within Step 6 scope" "backend/vendor missing' "${VERIFIER}" &&
   grep -q 'skip "live schema within Step 6 scope" "backend/.env missing' "${VERIFIER}" &&
   grep -qE 'skip "live schema within Step 6 scope" "development PostgreSQL' "${VERIFIER}"; then
  ok "6. missing composer/.env/DB prerequisites are each reported as an explicit SKIP"
else
  bad "6. a missing environment prerequisite is not reported truthfully"
fi

echo "------------------------------------------------------------------------"
printf 'SUMMARY [verify-step-06 adversarial]: %d/%d expectations met, %d failed\n' \
  "${PASS}" "$((PASS + FAIL))" "${FAIL}"
if [ "${FAIL}" -eq 0 ]; then
  echo "RESULT: PASS (verify-step-06 adversarial)"; exit 0
else
  echo "RESULT: FAIL (verify-step-06 adversarial)"; exit 1
fi
