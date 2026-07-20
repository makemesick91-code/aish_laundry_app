#!/usr/bin/env bash
#
# OWNER-RUN — add the three Step 3 runtime contexts to ruleset 19164588.
#
# Repository settings are owner territory (Rule 11, Rule 12): an agent never
# changes them. This script is prepared and dry-run-tested by the agent, and
# EXECUTED by the repository owner.
#
# It adds exactly three contexts:
#     runtime-foundation
#     tenant-isolation
#     authentication-rbac
#
# It preserves every existing context and every existing rule. It FAILS CLOSED on
# anything it does not recognise: an unexpected ruleset shape aborts rather than
# being overwritten, because a required-check set is the only thing standing
# between a red build and main.
#
# USAGE
#   cd /home/fikri/Projects/aish_laundry
#   bash scripts/owner/update-ruleset-step-03.sh --dry-run   # inspect, change nothing
#   bash scripts/owner/update-ruleset-step-03.sh             # apply

set -euo pipefail

REPO="makemesick91-code/aish_laundry_app"
RULESET_ID="19164588"
PR_NUMBER="13"

EXPECTED_EXISTING=12
NEW_CONTEXTS=("runtime-foundation" "tenant-isolation" "authentication-rbac")
EXPECTED_FINAL=$((EXPECTED_EXISTING + ${#NEW_CONTEXTS[@]}))   # 15

DRY_RUN=0
[ "${1:-}" = "--dry-run" ] && DRY_RUN=1

ok()   { printf '  \033[32mOK\033[0m    %s\n' "$*"; }
info() { printf '        %s\n' "$*"; }
die()  { printf '  \033[31mFAIL\033[0m  %s\n' "$*" >&2; exit 1; }

command -v gh >/dev/null 2>&1 || die "gh CLI not found"
command -v python3 >/dev/null 2>&1 || die "python3 not found"
gh auth status >/dev/null 2>&1 || die "gh is not authenticated"

WORK="$(mktemp -d)"
trap 'rm -rf "${WORK}"' EXIT
BEFORE="${WORK}/before.json"
AFTER="${WORK}/after.json"
PAYLOAD="${WORK}/payload.json"

echo "== Step 3 ruleset update =="
echo "  repository : ${REPO}"
echo "  ruleset    : ${RULESET_ID}"
echo "  adding     : ${NEW_CONTEXTS[*]}"
echo

# --- 1. read the live ruleset -------------------------------------------------
gh api "repos/${REPO}/rulesets/${RULESET_ID}" > "${BEFORE}" 2>/dev/null \
  || die "could not read ruleset ${RULESET_ID}"
ok "live ruleset read"

# --- 2. validate its shape and current state, failing closed -----------------
python3 - "${BEFORE}" "${EXPECTED_EXISTING}" "${NEW_CONTEXTS[@]}" <<'PY' || die "pre-update validation failed"
import json, sys

path, expected_existing, *new_contexts = sys.argv[1:]
expected_existing = int(expected_existing)
d = json.load(open(path))

errors = []

if d.get("enforcement") != "active":
    errors.append(f"enforcement is {d.get('enforcement')!r}, expected 'active'")

bypass = d.get("bypass_actors") or []
if bypass:
    errors.append(f"{len(bypass)} bypass actor(s) present, expected 0")

rules = d.get("rules") or []
types = sorted(r.get("type") for r in rules)
# Every one of these must survive the update.
for required in ("deletion", "non_fast_forward", "pull_request", "required_status_checks"):
    if required not in types:
        errors.append(f"expected rule type missing: {required}")

rsc = [r for r in rules if r.get("type") == "required_status_checks"]
if len(rsc) != 1:
    errors.append(f"expected exactly 1 required_status_checks rule, found {len(rsc)}")
else:
    p = rsc[0].get("parameters") or {}
    if p.get("strict_required_status_checks_policy") is not True:
        errors.append("strict_required_status_checks_policy is not true")
    checks = p.get("required_status_checks") or []
    contexts = [c.get("context") for c in checks]
    if len(contexts) != len(set(contexts)):
        errors.append("duplicate contexts already present")
    if len(contexts) != expected_existing:
        errors.append(f"expected {expected_existing} existing contexts, found {len(contexts)}")
    already = [c for c in new_contexts if c in contexts]
    if already:
        errors.append(f"context(s) already present, refusing to duplicate: {already}")

    print(f"        enforcement : {d.get('enforcement')}")
    print(f"        bypass      : {len(bypass)}")
    print(f"        strict      : {p.get('strict_required_status_checks_policy')}")
    print(f"        rules       : {', '.join(types)}")
    print(f"        contexts    : {len(contexts)}")
    for c in contexts:
        print(f"          - {c}")

if errors:
    for e in errors:
        print(f"  VALIDATION ERROR: {e}", file=sys.stderr)
    sys.exit(1)
PY
ok "ruleset shape, enforcement, bypass, and strict policy validated"

# --- 3. the three contexts must already be reporting on the Step 3 PR --------
# Adding a required check that no workflow publishes would block main forever.
echo
echo "== verifying the new contexts actually report on PR #${PR_NUMBER} =="
HEAD_SHA="$(gh pr view "${PR_NUMBER}" --repo "${REPO}" --json headRefOid -q .headRefOid)"
info "PR #${PR_NUMBER} head: ${HEAD_SHA}"
gh api "repos/${REPO}/commits/${HEAD_SHA}/check-runs?per_page=100" \
  -q '.check_runs[] | "\(.name)\t\(.conclusion)"' > "${WORK}/runs.txt" 2>/dev/null \
  || die "could not read check runs for ${HEAD_SHA}"

for ctx in "${NEW_CONTEXTS[@]}"; do
  line="$(grep -P "^${ctx}\t" "${WORK}/runs.txt" || true)"
  [ -n "${line}" ] || die "context '${ctx}' does not report on ${HEAD_SHA}; refusing to require it"
  concl="$(printf '%s' "${line}" | cut -f2)"
  [ "${concl}" = "success" ] || die "context '${ctx}' is '${concl}' on ${HEAD_SHA}; refusing to require a red check"
  ok "${ctx} reports success on the candidate SHA"
done

# --- 4. build the updated payload --------------------------------------------
python3 - "${BEFORE}" "${PAYLOAD}" "${NEW_CONTEXTS[@]}" <<'PY' || die "payload construction failed"
import json, sys

src, dst, *new_contexts = sys.argv[1:]
d = json.load(open(src))

for r in d.get("rules", []):
    if r.get("type") != "required_status_checks":
        continue
    p = r["parameters"]
    # APPEND only. Existing entries keep their position and their
    # integration_id, so nothing is silently re-scoped.
    for ctx in new_contexts:
        p["required_status_checks"].append({"context": ctx})

# Send back only the mutable fields. Server-managed metadata (id, node_id,
# created_at, _links, ...) is dropped rather than echoed back.
payload = {
    "name": d["name"],
    "target": d.get("target", "branch"),
    "enforcement": d["enforcement"],
    "conditions": d.get("conditions", {}),
    "rules": d["rules"],
    "bypass_actors": d.get("bypass_actors", []),
}
json.dump(payload, open(dst, "w"), indent=2)
print(f"        payload contexts: "
      f"{sum(len(r['parameters']['required_status_checks']) for r in d['rules'] if r['type']=='required_status_checks')}")
PY
ok "update payload constructed (append-only)"

if [ "${DRY_RUN}" -eq 1 ]; then
  echo
  echo "  --dry-run: nothing was sent. The payload above would add:"
  for ctx in "${NEW_CONTEXTS[@]}"; do echo "    + ${ctx}"; done
  echo "  Re-run without --dry-run to apply."
  exit 0
fi

# --- 5. apply ------------------------------------------------------------------
echo
echo "== applying =="
gh api --method PUT "repos/${REPO}/rulesets/${RULESET_ID}" \
  --input "${PAYLOAD}" >/dev/null 2>&1 \
  || die "ruleset update rejected by the API"
ok "update submitted"

# --- 6. INDEPENDENT re-read ----------------------------------------------------
# Re-read from the API rather than trusting the response we just received.
echo
echo "== independent verification =="
sleep 2
gh api "repos/${REPO}/rulesets/${RULESET_ID}" > "${AFTER}" 2>/dev/null \
  || die "could not re-read the ruleset after update"

python3 - "${AFTER}" "${EXPECTED_FINAL}" "${NEW_CONTEXTS[@]}" <<'PY' || die "post-update verification FAILED"
import json, sys

path, expected_final, *new_contexts = sys.argv[1:]
expected_final = int(expected_final)
d = json.load(open(path))
errors = []

if d.get("enforcement") != "active":
    errors.append(f"enforcement became {d.get('enforcement')!r}")
if d.get("bypass_actors"):
    errors.append(f"{len(d['bypass_actors'])} bypass actor(s) appeared")

types = sorted(r.get("type") for r in d.get("rules", []))
for required in ("deletion", "non_fast_forward", "pull_request", "required_status_checks"):
    if required not in types:
        errors.append(f"rule type lost during update: {required}")

rsc = [r for r in d["rules"] if r["type"] == "required_status_checks"]
p = rsc[0]["parameters"]
contexts = [c["context"] for c in p["required_status_checks"]]

if p.get("strict_required_status_checks_policy") is not True:
    errors.append("strict policy was lost")
if len(contexts) != len(set(contexts)):
    dupes = sorted({c for c in contexts if contexts.count(c) > 1})
    errors.append(f"duplicate contexts: {dupes}")
if len(set(contexts)) != expected_final:
    errors.append(f"expected exactly {expected_final} unique contexts, found {len(set(contexts))}")
for ctx in new_contexts:
    if ctx not in contexts:
        errors.append(f"new context missing after update: {ctx}")

print(f"        enforcement : {d.get('enforcement')}")
print(f"        bypass      : {len(d.get('bypass_actors') or [])}")
print(f"        strict      : {p.get('strict_required_status_checks_policy')}")
print(f"        rules       : {', '.join(types)}")
print(f"        contexts    : {len(set(contexts))}")
for c in contexts:
    mark = " (new)" if c in new_contexts else ""
    print(f"          - {c}{mark}")

if errors:
    for e in errors:
        print(f"  VERIFICATION ERROR: {e}", file=sys.stderr)
    sys.exit(1)
PY

ok "ruleset verified: ${EXPECTED_FINAL} unique contexts, active, 0 bypass actors, strict"
echo
echo "  Step 3 ruleset update complete."
echo "  No token or credential was printed by this script."
