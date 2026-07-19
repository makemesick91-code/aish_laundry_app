#!/usr/bin/env bash
# OWNER-ONLY: add the three Step 2 required status checks to ruleset 19164588.
#
# This script is run by the repository owner. An agent never runs it: repository
# settings, rulesets and branch protection are owner territory (Rule 11 item 21,
# Rule 12).
#
# What it does
#   1. Reads the CURRENT ruleset from the API — it never assumes a shape.
#   2. Adds ONLY the missing contexts. Every other field is preserved verbatim.
#   3. Refuses to run if the ruleset is not in the state it expects.
#   4. Re-reads the ruleset afterwards and verifies the result independently.
#
# What it deliberately does NOT do
#   - It does not remove or reorder any existing context.
#   - It does not touch enforcement, bypass actors, deletion protection,
#     non-fast-forward protection, or the pull-request rule.
#   - It does not merge anything, tag anything, or change visibility.
#
# Fail closed: any unexpected state aborts before the write.

set -euo pipefail

REPO="makemesick91-code/aish_laundry_app"
RULESET_ID="19164588"
EXPECTED_EXISTING=9
EXPECTED_FINAL=12
NEW_CONTEXTS=("design-system" "ux-foundation" "accessibility-privacy")

echo "=============================================================="
echo " Step 2 ruleset update — repository: $REPO"
echo " Ruleset: $RULESET_ID"
echo "=============================================================="

CURRENT="$(mktemp)"
trap 'rm -f "$CURRENT" "${PATCH:-}"' EXIT

echo "→ Reading the current ruleset..."
gh api "repos/$REPO/rulesets/$RULESET_ID" > "$CURRENT"

# ---- preflight: refuse to proceed unless the ruleset is as expected --------
python3 - "$CURRENT" "$EXPECTED_EXISTING" "${NEW_CONTEXTS[@]}" <<'PY'
import json, sys
path, expected = sys.argv[1], int(sys.argv[2])
new = sys.argv[3:]
d = json.load(open(path))

problems = []
if d.get("enforcement") != "active":
    problems.append(f"enforcement is {d.get('enforcement')!r}, expected 'active'")
if len(d.get("bypass_actors") or []) != 0:
    problems.append(f"{len(d['bypass_actors'])} bypass actors present, expected 0")

types = {r["type"] for r in d.get("rules", [])}
for required in ("deletion", "non_fast_forward", "pull_request",
                 "required_status_checks"):
    if required not in types:
        problems.append(f"rule '{required}' is missing")

rsc = next((r for r in d["rules"] if r["type"] == "required_status_checks"), None)
if rsc is None:
    problems.append("no required_status_checks rule")
else:
    p = rsc["parameters"]
    if p.get("strict_required_status_checks_policy") is not True:
        problems.append("strict policy is not true")
    ctx = [c["context"] for c in p["required_status_checks"]]
    if len(ctx) != expected:
        problems.append(f"{len(ctx)} existing contexts, expected {expected}")
    already = [c for c in new if c in ctx]
    if already:
        problems.append(f"already present, nothing to do: {already}")

if problems:
    print("ABORT — the ruleset is not in the expected state:", file=sys.stderr)
    for p_ in problems:
        print(f"  - {p_}", file=sys.stderr)
    sys.exit(1)
print("  preflight OK: active, 0 bypass actors, strict, "
      f"{expected} contexts, all rules present")
PY

# ---- build the patch: add only what is missing, preserve everything else ---
PATCH="$(mktemp)"
python3 - "$CURRENT" "$PATCH" "${NEW_CONTEXTS[@]}" <<'PY'
import json, sys
src, dst = sys.argv[1], sys.argv[2]
new = sys.argv[3:]
d = json.load(open(src))

for r in d["rules"]:
    if r["type"] != "required_status_checks":
        continue
    checks = r["parameters"]["required_status_checks"]
    have = {c["context"] for c in checks}
    for c in new:
        if c not in have:
            checks.append({"context": c})

# Send back only the fields the update endpoint accepts, unchanged except for
# the added contexts.
payload = {
    "name": d["name"],
    "target": d.get("target", "branch"),
    "enforcement": d["enforcement"],
    "bypass_actors": d.get("bypass_actors", []),
    "conditions": d.get("conditions", {}),
    "rules": d["rules"],
}
json.dump(payload, open(dst, "w"), indent=2)
total = sum(len(r["parameters"]["required_status_checks"])
            for r in d["rules"] if r["type"] == "required_status_checks")
print(f"  patch prepared: {total} contexts after update")
PY

echo "→ Applying the update..."
gh api --method PUT "repos/$REPO/rulesets/$RULESET_ID" --input "$PATCH" > /dev/null

# ---- independent re-read: never trust the write, verify it -----------------
echo "→ Re-reading the ruleset to verify independently..."
VERIFY="$(mktemp)"
gh api "repos/$REPO/rulesets/$RULESET_ID" > "$VERIFY"

python3 - "$VERIFY" "$EXPECTED_FINAL" "${NEW_CONTEXTS[@]}" <<'PY'
import json, sys
path, expected = sys.argv[1], int(sys.argv[2])
new = sys.argv[3:]
d = json.load(open(path))
rsc = next(r for r in d["rules"] if r["type"] == "required_status_checks")
ctx = [c["context"] for c in rsc["parameters"]["required_status_checks"]]

failures = []
if d.get("enforcement") != "active":
    failures.append("enforcement is no longer active")
if len(d.get("bypass_actors") or []) != 0:
    failures.append("bypass actors are no longer 0")
if rsc["parameters"].get("strict_required_status_checks_policy") is not True:
    failures.append("strict policy is no longer true")
types = {r["type"] for r in d["rules"]}
for required in ("deletion", "non_fast_forward", "pull_request"):
    if required not in types:
        failures.append(f"rule '{required}' was lost")
if len(set(ctx)) != expected:
    failures.append(f"{len(set(ctx))} unique contexts, expected {expected}")
for c in new:
    if c not in ctx:
        failures.append(f"context '{c}' was not added")

print()
print(f"  enforcement      : {d['enforcement']}")
print(f"  bypass actors    : {len(d.get('bypass_actors') or [])}")
print(f"  strict policy    : {rsc['parameters'].get('strict_required_status_checks_policy')}")
print(f"  unique contexts  : {len(set(ctx))}")
for c in sorted(set(ctx)):
    print(f"    - {c}")

if failures:
    print()
    print("VERIFICATION FAILED:", file=sys.stderr)
    for f in failures:
        print(f"  - {f}", file=sys.stderr)
    sys.exit(1)
print()
print("VERIFICATION PASSED — 12 unique required contexts, enforcement active,")
print("0 bypass actors, strict policy true, deletion / non-fast-forward /")
print("pull-request rules all retained.")
PY

rm -f "$VERIFY"
echo "=============================================================="
echo " Done."
echo "=============================================================="
