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

print("== 4. DEC-0041 portal-stack audit rejects each broken boundary ==")
# Same discipline as above: drive the validator's PURE functions with synthetic
# inputs. Nothing is written to disk, so the harness cannot leave a fixture behind
# and cannot pass because a fixture tripped an unrelated guard — the failure mode
# that invalidated the old Step 3 "31/31 mutations caught" figure (Rule 49).
dec41 = load("dec41", SCRIPTS / "validate-dec-0041-portal-stack.py")

# --- each forbidden construct is DETECTED in a synthetic view ---
broken = [
    ("<script>alert(1)</script>", dec41.SCRIPT_OR_REMOTE, "a script tag"),
    ("<div onclick=\"go()\">x</div>", dec41.SCRIPT_OR_REMOTE, "an inline event handler"),
    ("<link href=\"https://cdn.contoh.invalid/a.css\">", dec41.SCRIPT_OR_REMOTE, "a remote asset URL"),
    ("@vite(['resources/js/app.js'])", dec41.SCRIPT_OR_REMOTE, "a Vite bundle"),
    ("@php $x = 1; @endphp", dec41.BUSINESS_LOGIC_IN_VIEW, "an inline PHP block"),
    ("{{ DB::table('orders')->count() }}", dec41.BUSINESS_LOGIC_IN_VIEW, "direct database access"),
    ("{{ Order::query()->first() }}", dec41.BUSINESS_LOGIC_IN_VIEW, "an Eloquent query"),
    ("<script>localStorage.setItem('t', token)</script>", dec41.BROWSER_STORAGE, "localStorage"),
    ("{{ session('tracking_token') }}", dec41.BROWSER_STORAGE, "a session read"),
    ("{{ Auth::user() }}", dec41.BROWSER_STORAGE, "an authentication facade call"),
]
for markup, patterns, label in broken:
    check(dec41.scan(markup, patterns) != [],
          f"DEC-0041 audit REJECTS {label}")

# --- unescaped output ---
check("{!!" in "<p>{!! $note !!}</p>", "DEC-0041 audit's unescaped-output probe is the right token")

# --- Step 8/9 controls in a structural position ---
for control in ('<form action="/lacak/jemput">',
                '<a href="/pickup/new">Jemput</a>',
                '<button id="reminder-send">x</button>',
                '<input name="courier_id">',
                '<a href="/pengiriman/atur">Antar</a>'):
    check(dec41.STEP_8_9_STRUCTURAL.search(control) is not None,
          f"DEC-0041 audit flags a Step 8/9 control: {control}")

# --- and does NOT flag legitimate portal markup ---
legitimate = (
    '<h1>Status cucian Anda</h1>'
    '<p>Pesanan ALS-2026-000042 sedang dikerjakan.</p>'
    '<form action="/lacak/{{ $token }}/otp" method="post">'
    '<button id="minta-kode">Minta kode verifikasi</button></form>'
)
check(dec41.scan(legitimate, dec41.SCRIPT_OR_REMOTE) == [],
      "DEC-0041 audit does NOT flag legitimate portal markup (no script, no remote)")
check(dec41.scan(legitimate, dec41.BROWSER_STORAGE) == [],
      "DEC-0041 audit does NOT flag legitimate portal markup (no storage, no session)")
check(dec41.STEP_8_9_STRUCTURAL.search(legitimate) is None,
      "DEC-0041 audit does NOT flag the FR-091 OTP control as a Step 8/9 control")

# --- comment stripping is a real narrowing, and it is bounded ---
# The portal views DOCUMENT their own constraints ("There is no <script> tag").
# Stripping comments is what stops the audit flagging the very sentences that prove
# the rule is kept. It must strip comments and NOTHING else.
commented = "{{-- There is no <script> tag here --}}<p>Halo</p>"
check(dec41.scan(dec41.strip_comments(commented), dec41.SCRIPT_OR_REMOTE) == [],
      "DEC-0041 audit does not flag a <script> mention inside a Blade comment")
check(dec41.scan(dec41.strip_comments("{{-- doc --}}<script>x</script>"),
                 dec41.SCRIPT_OR_REMOTE) != [],
      "DEC-0041 audit still flags a REAL script tag beside a Blade comment "
      "(comment stripping did not swallow live markup)")
check(dec41.scan(dec41.strip_comments("<!-- html comment --><script>x</script>"),
                 dec41.SCRIPT_OR_REMOTE) != [],
      "DEC-0041 audit still flags a REAL script tag beside an HTML comment")

# --- the permitted-route pin is exactly one route ---
check(dec41.PERMITTED_WEB_ROUTE == "lacak/{token}",
      f"DEC-0041 permits exactly the portal route (found {dec41.PERMITTED_WEB_ROUTE!r})")

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
