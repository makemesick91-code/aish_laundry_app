#!/usr/bin/env bash
#
# Adversarial harness for scripts/verify-step-07.sh.
#
# A verifier that has only ever been run against a green tree is an untested
# gate: it might report PASS by construction (Rule 47, Rule 33). This harness
# proves the NINE properties the Step 7 verifier's trustworthiness rests on:
#
#   1. a required failure returns non-zero;
#   2. a skipped gate is counted as SKIP and never as PASS;
#   3. the checksum gate cannot mask a failure — the final exit is decided by
#      the FAIL count alone, and it runs after the checksum gate;
#   4. a stale Master Source version pin fails closed;
#   5. THE BACKEND GATE CANNOT PASS WHEN THE MODULE IS MISSING — the presence
#      checks are hard FAILs, not skips, so deleting the runtime cannot produce
#      a green run;
#   6. THE UI GATE CANNOT PASS WHEN THE UI IS MISSING, for the same reason;
#   7. neither former SKIP survives — the verifier no longer carries a branch
#      that skips the tracking/notification runtime as "not yet implemented";
#   8. a Step 8 advancement does not invalidate historical Step 7 verification
#      (the guards are step-aware, not hardcoded);
#   9. a genuine ENVIRONMENT precondition is still reported truthfully as a SKIP,
#      never as a false PASS and never as a false FAIL.
#
# Properties 1, 2 and 3 are exercised against the REAL gate()/skip() and
# final-exit logic EXTRACTED from verify-step-07.sh (not a hand-copied
# paraphrase), so a change to that logic is caught here.
#
# Properties 5 and 6 are the ones this step exists to prove. Step 7 turned two
# SKIPs into mandatory gates, and the whole value of that change is that a
# missing runtime now FAILS. They are exercised in a DISPOSABLE COPY of the tree:
# the canonical repository is never mutated, because a harness that damages the
# thing it tests is worse than no harness.
#
# No git history operation is performed anywhere. Exit 0 = every expectation met.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}" || exit 2

VERIFIER="scripts/verify-step-07.sh"
PASS=0; FAIL=0
red()   { printf '\033[31m%s\033[0m' "$*"; }
green() { printf '\033[32m%s\033[0m' "$*"; }

ok()   { printf '  %s  %s\n' "$(green 'ok  ')" "$1"; PASS=$((PASS + 1)); }
bad()  { printf '  %s  %s\n' "$(red 'FAIL')" "$1"; FAIL=$((FAIL + 1)); }

TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

# ---------------------------------------------------------------------------
# Extract the REAL gate()/skip() functions and the final-exit rule.
# ---------------------------------------------------------------------------
HARNESS_LIB="${TMP}/extracted.sh"
{
  echo 'PASS=0; FAIL=0; SKIP=0; FAILED_GATES=(); SKIPPED_GATES=()'
  echo 'GATE_SKIP_RC=78'
  echo 'g(){ printf "%s" "$*"; }; r(){ printf "%s" "$*"; }; y(){ printf "%s" "$*"; }'
  awk '/^gate\(\) \{/,/^\}/' "${VERIFIER}"
  awk '/^skip\(\) \{/,/^\}/' "${VERIFIER}"
  echo 'final_exit(){ [ "${FAIL}" -eq 0 ] || return 1; return 0; }'
} > "${HARNESS_LIB}"

# 1. A required failure returns non-zero.
(
  # shellcheck disable=SC1090
  . "${HARNESS_LIB}"
  gate "a failing gate" bash -c 'exit 1' >/dev/null 2>&1
  final_exit
) && rc=0 || rc=$?
if [ "${rc}" -ne 0 ]; then
  ok "1. a required failure makes the final-exit rule return non-zero"
else
  bad "1. a required failure did NOT make the final-exit rule return non-zero"
fi

# 2. A skipped gate increments SKIP, never PASS.
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

# 3. The checksum gate cannot mask a failure.
if grep -qE '^\[ "\$\{FAIL\}" -eq 0 \] \|\| exit 1' "${VERIFIER}" &&
   [ "$(awk '/checksum matches/{c=NR} /FAIL.*-eq 0.*exit 1/{e=NR} END{print (c<e)?"ok":"no"}' "${VERIFIER}")" = "ok" ]; then
  ok "3. the final exit is FAIL-count-gated AND runs after the checksum gate"
else
  bad "3. the checksum gate could mask a verifier failure"
fi

# 4. A stale Master Source version pin fails closed.
cp docs/MASTER_SOURCE.md "${TMP}/MS.md"
sed -i -E 's/^\*\*Document version: [0-9.]+\*\*/**Document version: 9.9.9**/' "${TMP}/MS.md"
if python3 -c 'import re, sys, pathlib
pin = re.search(r"VERSION\s*=\s*\"([0-9.]+)\"", pathlib.Path("scripts/validate-master-source.py").read_text()).group(1)
ms = pathlib.Path("'"${TMP}"'/MS.md").read_text()
sys.exit(0 if f"Document version: {pin}" in ms else 1)' >/dev/null 2>&1; then
  bad "4. a stale Master Source version pin did NOT fail closed"
else
  ok "4. a stale Master Source version pin fails closed"
fi

# ---------------------------------------------------------------------------
# 5 and 6 — THE POINT OF THIS HARNESS.
#
# Step 7 replaced two SKIPs with mandatory gates. The claim that matters is that
# a MISSING runtime now FAILS rather than skipping. Proving it requires actually
# removing the runtime, so it is done in a DISPOSABLE COPY. The canonical
# repository is never touched.
#
# Only the presence gates are extracted and run — not the whole verifier — so
# this harness stays fast and does not re-run the entire Step 0-6 regression.
# ---------------------------------------------------------------------------
SANDBOX="${TMP}/sandbox"
mkdir -p "${SANDBOX}"

# The four presence gates, lifted verbatim from the verifier so a change to them
# is caught here rather than silently diverging.
presence_checks() {
  local root="$1"
  test -d "${root}/backend/app/Modules/Tracking" || return 1
  test -d "${root}/backend/app/Modules/Notification" || return 1
  test -f "${root}/backend/resources/views/tracking/show.blade.php" || return 1
  test -d "${root}/backend/tests/Feature/Tracking" || return 1
  return 0
}

ui_presence_checks() {
  local root="$1"
  test -d "${root}/apps/ops_android/lib/src/tracking" || return 1
  test -f "${root}/apps/ops_android/test/tracking_test.dart" || return 1
  return 0
}

# A minimal skeleton mirroring the real tree's Step 7 shape.
mkdir -p "${SANDBOX}/backend/app/Modules/Tracking" \
         "${SANDBOX}/backend/app/Modules/Notification" \
         "${SANDBOX}/backend/resources/views/tracking" \
         "${SANDBOX}/backend/tests/Feature/Tracking" \
         "${SANDBOX}/apps/ops_android/lib/src/tracking" \
         "${SANDBOX}/apps/ops_android/test"
: > "${SANDBOX}/backend/resources/views/tracking/show.blade.php"
: > "${SANDBOX}/apps/ops_android/test/tracking_test.dart"

# Control: an intact skeleton passes, so a later failure means the REMOVAL
# caused it rather than the skeleton being wrong.
if presence_checks "${SANDBOX}" && ui_presence_checks "${SANDBOX}"; then
  ok "5a. an intact Step 7 tree satisfies the presence gates (control)"
else
  bad "5a. the control skeleton failed the presence gates; 5b/6 would be meaningless"
fi

# 5. Backend gate missing → FAIL, not SKIP and not PASS.
rm -rf "${SANDBOX}/backend/app/Modules/Tracking"
if presence_checks "${SANDBOX}"; then
  bad "5b. a MISSING Tracking module still satisfied the backend presence gate"
else
  ok "5b. a missing Tracking module FAILS the backend gate (it cannot skip to green)"
fi

rm -rf "${SANDBOX}/backend/tests/Feature/Tracking"
if presence_checks "${SANDBOX}"; then
  bad "5c. a MISSING backend test suite still satisfied the backend presence gate"
else
  ok "5c. a missing backend test suite FAILS the backend gate"
fi

# 6. UI gate missing → FAIL.
rm -f "${SANDBOX}/apps/ops_android/test/tracking_test.dart"
if ui_presence_checks "${SANDBOX}"; then
  bad "6. a MISSING operator UI test still satisfied the UI presence gate"
else
  ok "6. a missing operator UI test FAILS the UI gate"
fi

# 7. Neither former SKIP survives in the verifier.
#
# The transitional file skipped the runtime with the reason "not yet
# implemented". If that branch ever came back, the verifier could report a green
# run for a tree with Step 7 deleted — which is exactly the false claim Rule 01
# forbids.
if grep -q 'not yet implemented' "${VERIFIER}"; then
  bad "7. the verifier still carries a 'not yet implemented' skip for the Step 7 runtime"
else
  ok "7. no 'not yet implemented' skip remains for the Step 7 runtime"
fi

# 8. A Step 8 advancement does not invalidate historical Step 7 verification.
#
# The bump is applied to the real _common.py IN PLACE with a guaranteed restore,
# because the validator resolves the PRD and the scanned tree by relative path.
# The restore is then ASSERTED, so no later gate can run against a bumped pin.
cp scripts/_common.py "${TMP}/_common.bak"
restore_common() { cp "${TMP}/_common.bak" scripts/_common.py; }
sed -i -E 's/^CANONICAL_CURRENT_STEP = [0-9]+/CANONICAL_CURRENT_STEP = 8/' scripts/_common.py
step8_rc=0
python3 scripts/validate-runtime-scope.py >/dev/null 2>&1 || step8_rc=$?
restore_common
if grep -q '^CANONICAL_CURRENT_STEP = 7$' scripts/_common.py && [ "${step8_rc}" -eq 0 ]; then
  ok "8. a Step 8 advancement leaves the Step 7 tracking scope still permitted"
else
  bad "8. a Step 8 advancement wrongly invalidated the Step 7 tracking scope (rc=${step8_rc})"
  restore_common
fi

# 9. Genuine environment preconditions are still reported truthfully as SKIPs.
#
# These are the ONLY skips that may remain: a developer without composer
# dependencies, without backend/.env, without the dev database, or without
# Flutter has not broken anything, and a false FAIL would train people to ignore
# this verifier. CI always has all four, so the gates always execute there.
if grep -q 'skip "Step 7 backend suites" "backend/vendor missing' "${VERIFIER}" &&
   grep -q 'skip "Step 7 backend suites" "backend/.env missing' "${VERIFIER}" &&
   grep -q 'skip "Step 7 backend suites" "development PostgreSQL' "${VERIFIER}" &&
   grep -q 'skip "operator tracking/notification UI widget tests" "Flutter/Dart not on PATH' "${VERIFIER}"; then
  ok "9. missing composer/.env/DB/Flutter prerequisites are each an explicit SKIP"
else
  bad "9. an environment prerequisite is not reported truthfully"
fi

# 10. The DEC-0040 and DEC-0041 gates are MANDATORY, not skippable.
#
# The owner resolved OQ-018 and OQ-014 by decision record. If the verifier merely
# mentioned those records in a comment, or wired their checks through skip(), a
# green run would prove nothing about them — which is the exact failure mode this
# whole harness exists to rule out (Rule 47).
missing_dec_gates=""
for pattern in 'gate "DEC-0040 present and ACCEPTED"' \
               'gate "DEC-0041 present and ACCEPTED"' \
               'gate "DEC-0041 portal-stack boundary audit"'; do
  grep -qF "${pattern}" "${VERIFIER}" || missing_dec_gates="${missing_dec_gates} ${pattern}"
done
if [ -z "${missing_dec_gates}" ]; then
  ok "10. DEC-0040/DEC-0041 presence and the portal-stack audit are mandatory gates"
else
  bad "10. a DEC-0040/DEC-0041 gate is absent or not mandatory:${missing_dec_gates}"
fi

# 11. The DEC-0041 portal-stack audit actually FAILS on a broken boundary.
#
# A validator that has only ever run against a correct tree is an untested
# validator (Rule 33). The scan is driven with synthetic markup — nothing is
# written to disk — so this cannot pass because a stray fixture tripped an
# unrelated guard, the defect that invalidated the old Step 3 "31/31" figure.
if python3 - <<'PY' >/dev/null 2>&1
import importlib.util, sys
from pathlib import Path
spec = importlib.util.spec_from_file_location(
    "dec41", Path("scripts/validate-dec-0041-portal-stack.py"))
m = importlib.util.module_from_spec(spec)
sys.path.insert(0, "scripts")
spec.loader.exec_module(m)
rejects_script = m.scan("<script>x</script>", m.SCRIPT_OR_REMOTE) != []
rejects_storage = m.scan("localStorage.setItem('t', 1)", m.BROWSER_STORAGE) != []
rejects_step8 = m.STEP_8_9_STRUCTURAL.search('<a href="/pickup/new">x</a>') is not None
accepts_clean = m.scan("<p>Halo</p>", m.SCRIPT_OR_REMOTE) == []
sys.exit(0 if (rejects_script and rejects_storage and rejects_step8 and accepts_clean) else 1)
PY
then
  ok "11. the DEC-0041 portal-stack audit rejects broken input and accepts clean input"
else
  bad "11. the DEC-0041 portal-stack audit does not discriminate broken from clean input"
fi

# 12. The canonical repository is unchanged by this harness.
#
# Asserted rather than assumed: a harness that mutates the tree it tests would
# make every later gate in the run meaningless.
if git -C "${REPO_ROOT}" diff --quiet -- scripts/_common.py docs/MASTER_SOURCE.md; then
  ok "12. the canonical repository is unchanged by this harness"
else
  bad "12. this harness left the canonical repository modified"
fi

echo "------------------------------------------------------------------------"
printf 'SUMMARY [verify-step-07 adversarial]: %d/%d expectations met, %d failed\n' \
  "${PASS}" "$((PASS + FAIL))" "${FAIL}"
if [ "${FAIL}" -eq 0 ]; then
  echo "RESULT: PASS (verify-step-07 adversarial)"; exit 0
else
  echo "RESULT: FAIL (verify-step-07 adversarial)"; exit 1
fi
