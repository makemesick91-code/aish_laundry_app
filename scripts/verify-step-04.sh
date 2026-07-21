#!/usr/bin/env bash
#
# Canonical Step 4 verifier — LAUNDRY MASTER DATA.
#
# Runs the REAL gates. There is no placeholder success anywhere in this file:
# every line below either executes a command whose exit status decides the
# result, or is reported as SKIPPED with a visible reason. A verifier that
# prints PASS without running anything is worse than no verifier, because it
# launders an unverified claim into an evidence pack (Rule 01).
#
# It DELEGATES the Step 0-3 regression to `verify-step-03.sh` rather than
# restating it. A second copy of thirty gates is a second thing to drift.
#
# Requires:
#   - Flutter/Dart on PATH        (export PATH="$HOME/flutter/bin:$PATH")
#   - PHP 8.5 + composer
#   - development PostgreSQL and Redis running
#       bash scripts/start-dev-services.sh
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
echo "STEP 4 CANONICAL VERIFICATION — LAUNDRY MASTER DATA"
echo "========================================================================"
echo "  commit    : ${SHA}"
echo "  branch    : $(git rev-parse --abbrev-ref HEAD)"
echo "  timestamp : $(date -u '+%Y-%m-%dT%H:%M:%SZ') (UTC)"
echo
echo "  Step 4 is MASTER DATA. It is not the workflows that consume it."
echo "  A migration is not a tested schema and a table is not a feature."

# ---------------------------------------------------------------------------
hdr "1. Step 0-3 regression (delegated, not restated)"
gate "Step 3 verifier (carries Steps 0-2)" bash scripts/verify-step-03.sh

# ---------------------------------------------------------------------------
hdr "2. Step 4 authorization and governance"
gate "DEC-0028 present and ACCEPTED"  bash -c 'grep -qE "^\*\*Status:\*\* ACCEPTED" docs/decisions/DEC-0028-*.md'
gate "DEC-0030 present and ACCEPTED"  bash -c 'grep -qE "^\*\*Status:\*\* ACCEPTED" docs/decisions/DEC-0030-*.md'
gate "DEC-0031 present and ACCEPTED"  bash -c 'grep -qE "^\*\*Status:\*\* ACCEPTED" docs/decisions/DEC-0031-*.md'
gate "Rule 50 (Step 4 status) present" test -f .claude/rules/50-current-step-04-status.md

# ---------------------------------------------------------------------------
hdr "3. Step 4 scope — master data, not the workflow"
gate "DEC-0030 label audit"           python3 scripts/validate-dec-0030-labels.py
gate "Step 4 validator adversarial harness" bash scripts/test-step-04-validators.sh

# The absence of an order/payment/document route is asserted, not assumed.
gate "no Step 5 route registered"     bash -c '! grep -qE "Route::(get|post|patch|put|delete)\(\s*.(orders|payments|invoices|receipts|checkout|pickups|deliveries)" backend/routes/api.php'
gate "no Step 5 endpoint constant"    bash -c '! grep -qiE "String (order|payment|invoice|receipt|checkout|pickup|delivery)" packages/networking/lib/src/api_endpoints.dart'

# ---------------------------------------------------------------------------
hdr "4. Step 4 hard gates (authoritative PostgreSQL)"
if ! (cd backend && php artisan --version >/dev/null 2>&1); then
  skip "Step 4 backend gates" "Laravel could not boot; is backend/.env present?"
elif ! bash scripts/check-dev-services.sh >/dev/null 2>&1; then
  skip "Step 4 database-backed gates" "development PostgreSQL/Redis not running (scripts/start-dev-services.sh)"
else
  # Rule 48 hard rule 3: the direct-ID path alone does not satisfy the gate.
  gate "tenant isolation matrix (all 6 access paths)" \
    bash -c 'cd backend && php artisan test --filter Step04IsolationMatrixTest'
  gate "customer master data"        bash -c 'cd backend && php artisan test --filter CustomerMasterDataTest'
  gate "service catalogue surface"   bash -c 'cd backend && php artisan test --filter ServiceCatalogSurfaceTest'
  gate "price list integrity"        bash -c 'cd backend && php artisan test --filter PriceListIntegrityTest'
  gate "outlet master data"          bash -c 'cd backend && php artisan test --filter OutletMasterDataTest'
  gate "staff assignment"            bash -c 'cd backend && php artisan test --filter StaffAssignmentTest'
  gate "role catalogue alignment"    bash -c 'cd backend && php artisan test --filter TenantRoleCatalogueAlignmentTest'
  gate "integer Rupiah rounding"     bash -c 'cd backend && php artisan test --filter RupiahRoundingTest'

  # Rule 04 hard rule 2 — asserted against the LIVE schema, because a money
  # column declared float would be inherited by Step 5 (Rule 42).
  # Rule 04 hard rule 2 and Rule 02 hard rule 7, asserted against the LIVE
  # schema rather than against migration source: a migration can be edited,
  # superseded, or bypassed by a manual DDL, and what a query reads is what
  # actually exists. A money column declared float here would be inherited by
  # Step 5's payment paths (Rule 42).
  #
  # A SCRIPT, not inline PHP. The first version of this gate was written inline
  # and failed for an environment reason — `$_ENV` is not populated in PHP CLI
  # under the default `variables_order` — which reported a schema defect that
  # did not exist. A gate that fails for the wrong reason is worse than no gate.
  gate "Step 4 schema invariants (money type, tenant_id)" \
    bash -c 'cd backend && set -a && . ./.env && set +a && php scripts/ci/assert-step04-invariants.php'
fi

# ---------------------------------------------------------------------------
hdr "5. Step 4 client surfaces"
if ! command -v flutter >/dev/null 2>&1; then
  skip "Step 4 Flutter gates" "flutter not on PATH (export PATH=\"\$HOME/flutter/bin:\$PATH\")"
else
  gate "Ops Android master-data suite" bash -c 'cd apps/ops_android && flutter test test/master_data_test.dart'
  gate "Console master-data suite"     bash -c 'cd apps/admin_web && flutter test test/master_data_test.dart'

  # The conflict UX is the behaviour most easily lost to a refactor, so its
  # absence is asserted structurally as well as by test: a retry control on a
  # stale-write path resends the payload and destroys another edit (T-12).
  gate "no retry affordance on the stale-write path" \
    bash -c '! grep -A6 "StaleWriteNotice" apps/ops_android/lib/src/master_data/master_data_views.dart | grep -q "Coba lagi"'

  # Rule 32 hard rule 5 — unmasking is a server action, never a client control.
  #
  # `lib/` only, deliberately. The test suites assert this control's ABSENCE by
  # name, so scanning them too would flag the very tests that prove the point.
  gate "no unmask control in client source" \
    bash -c '! grep -rqiE "(buka|tampilkan|lihat) nomor" apps/customer_android/lib apps/ops_android/lib apps/admin_web/lib packages/design_system/lib --include=*.dart'
fi

# ---------------------------------------------------------------------------
hdr "6. Public repository safety for Step 4 fixtures"
# Fixture phone safety is DELEGATED to the canonical scan, which the Step 3
# verifier above already runs. A second, cruder check here flagged the
# recognisably fabricated sequential numbers Rule 45 explicitly PERMITS
# ("an obviously sequential, repeated, or placeholder pattern") — and a gate
# that fires on correct input trains a reader to ignore it.
gate "public repository safety (canonical scan)" bash scripts/validate-public-repository-safety.sh
gate "working tree clean"            bash -c '[ "$(git status --porcelain | wc -l)" -eq 0 ]'

# ---------------------------------------------------------------------------
echo
echo "========================================================================"
printf 'STEP 4 VERIFICATION: %d passed, %d failed, %d skipped\n' "${PASS}" "${FAIL}" "${SKIP}"
if [ "${#FAILED_GATES[@]}" -gt 0 ]; then
  echo "FAILED:"
  for f in "${FAILED_GATES[@]}"; do echo "  - ${f}"; done
fi
if [ "${#SKIPPED_GATES[@]}" -gt 0 ]; then
  echo "SKIPPED (NOT verified — do not report these as passing):"
  for s in "${SKIPPED_GATES[@]}"; do echo "  - ${s}"; done
fi
echo "  commit: ${SHA}"
echo "========================================================================"
echo
echo "  This verifier classified and executed gates. It is not owner acceptance."
echo "  GO is conferred by the repository owner and is never self-declared."

[ "${FAIL}" -eq 0 ] || exit 1
exit 0
