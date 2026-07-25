#!/usr/bin/env bash
#
# Adversarial harness for the Step 7 (DEC-0039) runtime-scope guard transition.
#
# It proves the guard transition is CORRECT and NOT a permanent hole, without
# mutating the working tree: it drives the guard's own pure data (`forbidden_feature_map`,
# the DEC-0039 label sets and matcher) with synthetic inputs, and re-imports the guard
# with `CANONICAL_CURRENT_STEP` monkeypatched below 7 to prove the four Step 7 labels
# were genuinely gated on the canonical step and not simply deleted.
#
# A validator that has only ever been run against correct input is an untested
# validator (Rule 33, Rule 47). This harness is what lets verify-step-07.sh rely on
# the guard transition as a gate.
#
# Exit 0 = every expectation met. Exit 1 = at least one expectation failed.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}" || exit 2

# Assert the working tree is byte-identical before and after — this harness must
# never leave a fixture behind.
before="$(git status --porcelain)"

python3 - <<'PY'
import importlib.util
import sys
from pathlib import Path

SCRIPTS = Path("scripts").resolve()
sys.path.insert(0, str(SCRIPTS))

PASS = 0
FAIL = 0
def check(cond, msg):
    global PASS, FAIL
    if cond:
        print(f"  PASS  {msg}"); PASS += 1
    else:
        print(f"  FAIL  {msg}"); FAIL += 1

def load(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    m = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(m)
    return m

import _common

print("== 1. forbidden_feature_map at the real canonical current step ==")
check(_common.CANONICAL_CURRENT_STEP == 7,
      f"CANONICAL_CURRENT_STEP is 7 (found {_common.CANONICAL_CURRENT_STEP})")

vrs = load("vrs", SCRIPTS / "validate-runtime-scope.py")
fm = vrs.forbidden_feature_map()
# The four Step 7 labels are now PERMITTED (absent from the forbidden map).
for permitted in ("tracking portal", "tracking token", "WhatsApp", "notification provider"):
    check(permitted not in fm, f"Step 7 label '{permitted}' is PERMITTED at step 7 (not forbidden)")
# Every Step 8+ label stays FORBIDDEN.
for forbidden in ("pickup", "delivery", "courier routing", "unclaimed laundry",
                  "reminder ladder", "receivables", "finance reports", "loyalty",
                  "subscription billing"):
    check(forbidden in fm, f"Step 8+ label '{forbidden}' stays FORBIDDEN at step 7")
check(vrs.FORBIDDEN_LABEL == "Step 8+",
      f"forbidden band is named 'Step 8+' (found {vrs.FORBIDDEN_LABEL!r})")

print("== 2. the gating is REAL — below step 7 the four labels are forbidden ==")
# Re-import the guard with CANONICAL_CURRENT_STEP patched to 6, proving the four
# Step 7 labels were split into a STEP-GATED set (permitted only from step 7), not
# deleted. If they had merely been removed, they would be permitted at step 6 too.
_common.CANONICAL_CURRENT_STEP = 6
vrs6 = load("vrs6", SCRIPTS / "validate-runtime-scope.py")
fm6 = vrs6.forbidden_feature_map()
for gated in ("tracking portal", "tracking token", "WhatsApp", "notification provider"):
    check(gated in fm6, f"'{gated}' WOULD be forbidden at step 6 (gating is real, not a hole)")
_common.CANONICAL_CURRENT_STEP = 7  # restore

print("== 3. DEC-0039 label matcher catches Step 8+ tokens, not Step 7 tokens ==")
dec39 = load("dec39", SCRIPTS / "validate-dec-0039-labels.py")
# Structural identifiers carrying a Step 8+ token must match a STILL_FORBIDDEN entry.
def any_forbidden(identifier):
    return any(dec39.matches_forbidden(identifier, tok)
               for toks in dec39.STILL_FORBIDDEN.values() for tok in toks)
for step8 in ("pickups", "pickup_requests", "deliveries", "courier_routes",
              "unclaimed_laundry", "reminder_schedules", "storage_fees",
              "finance_reports", "loyalty_points", "subscription_invoices"):
    check(any_forbidden(step8), f"DEC-0039 audit flags Step 8+ token '{step8}'")
# The permitted Step 7 tokens must NOT be flagged by the DEC-0039 residual audit.
for step7 in ("tracking_tokens", "public_tracking", "whatsapp_messages",
              "notification_providers"):
    check(not any_forbidden(step7),
          f"DEC-0039 audit does NOT flag permitted Step 7 token '{step7}'")
# Every permitted label still traces to a PRD requirement.
trace = dec39.check_permitted_labels_trace_to_requirements()
check(trace == [], f"every DEC-0039 permitted label traces to a PRD requirement (problems: {trace})")

print("-" * 72)
print(f"SUMMARY [test-step-07-validators]: {PASS} passed, {FAIL} failed")
sys.exit(1 if FAIL else 0)
PY
rc=$?

after="$(git status --porcelain)"
if [ "${before}" != "${after}" ]; then
  echo "  FAIL  working tree changed during the harness run"
  rc=1
fi

if [ "${rc}" -eq 0 ]; then
  echo "RESULT: PASS (test-step-07-validators)"
else
  echo "RESULT: FAIL (test-step-07-validators)"
fi
exit "${rc}"
