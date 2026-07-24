#!/usr/bin/env bash
#
# ADVERSARIAL TEST OF THE STEP 6 VALIDATORS (the DEC-0037 guard transition).
#
# Rule 47 and Rule 33: a guard that permits new runtime must be tested against
# both the runtime it now ALLOWS and the runtime it still FORBIDS before it is
# relied upon as a gate. DEC-0037 moved six labels — production, washing, drying,
# finishing, quality control, rework — from forbidden to permitted, and raised
# _common.CANONICAL_CURRENT_STEP from 5 to 6. This harness proves the transition
# in BOTH directions:
#
#   ACCEPT — the six Step 6 labels are now authorised runtime.
#   REJECT — every Step 7+ label remains forbidden, plainly and renamed:
#            tracking (Step 7), WhatsApp (Step 7), pickup/delivery (Step 8),
#            the reminder ladder / unclaimed aging (Step 9).
#
# Disciplines inherited from the Step 3/4/5 harness corrections (Rule 49):
#   1. FIXTURES ARE ASSEMBLED AT RUNTIME, never embedded as literals, so this
#      script's own text carries no forbidden identifier that could trip a guard.
#   2. SETUP FAILURE IS LOUD: a mutation that never applied has verified nothing.
#   3. THE WORKING TREE IS VERIFIED BYTE-IDENTICAL before and after.
#
# Exit 0 = every expectation met. Exit 1 = a guard mis-classified, or the tree
# was not restored.

set -uo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO"

PASSED=0
FAILED=0
TOTAL=0

tree_fingerprint() {
  git status --porcelain=v1 | sort
  git diff --no-color | sha256sum
}
BEFORE="$(tree_fingerprint)"

CREATED_FILES=()
BACKED_UP=()

cleanup() {
  for f in "${CREATED_FILES[@]:-}"; do
    [ -n "$f" ] && rm -f "$f"
  done
  for pair in "${BACKED_UP[@]:-}"; do
    [ -z "$pair" ] && continue
    original="${pair%%::*}"
    backup="${pair##*::}"
    if [ -f "$backup" ]; then
      mv -f "$backup" "$original"
    fi
  done
}
trap cleanup EXIT

backup_file() {
  local original="$1"
  local backup
  backup="$(mktemp)"
  cp "$original" "$backup"
  BACKED_UP+=("${original}::${backup}")
}

expect_reject() {
  local description="$1"; local validator="$2"
  TOTAL=$((TOTAL + 1))
  if python3 "$validator" >/dev/null 2>&1; then
    echo "  FAIL  ${description}"
    echo "        ${validator} PASSED on deliberately broken input."
    FAILED=$((FAILED + 1))
  else
    echo "  ok    ${description}"
    PASSED=$((PASSED + 1))
  fi
}

expect_accept() {
  local description="$1"; local validator="$2"
  TOTAL=$((TOTAL + 1))
  if python3 "$validator" >/dev/null 2>&1; then
    echo "  ok    ${description}"
    PASSED=$((PASSED + 1))
  else
    echo "  FAIL  ${description}"
    echo "        ${validator} REJECTED legitimate Step 6 input."
    FAILED=$((FAILED + 1))
  fi
}

abort_setup() {
  echo "SETUP FAILED: $1" >&2
  echo "A mutation that never applied would score as 'caught'. Aborting." >&2
  exit 1
}

make_fixture() {
  local path="$1"; shift
  mkdir -p "$(dirname "$path")" || abort_setup "could not create $(dirname "$path")"
  printf '%s\n' "$@" > "$path" || abort_setup "could not write $path"
  CREATED_FILES+=("$path")
  [ -s "$path" ] || abort_setup "$path was written empty"
}

migration_fixture() {  # $1 = table token
  local table="$1"
  local path="backend/database/migrations/2026_07_24_999${RANDOM_SUFFIX}_create_${table}_table.php"
  RANDOM_SUFFIX=$((RANDOM_SUFFIX + 1))
  make_fixture "$path" \
    '<?php' \
    '// ADVERSARIAL FIXTURE — deliberately assembled. Removed by the harness.' \
    'use Illuminate\Database\Migrations\Migration;' \
    'use Illuminate\Database\Schema\Blueprint;' \
    'use Illuminate\Support\Facades\Schema;' \
    'return new class extends Migration {' \
    '  public function up(): void {' \
    "    Schema::create('${table}', function (Blueprint \$table) {" \
    "      \$table->uuid('id')->primary();" \
    '    });' \
    '  }' \
    '};'
  grep -q "$table" "$path" || abort_setup "fixture for '${table}' lacks its token"
  echo "$path"
}

SCOPE_GUARD="scripts/validate-runtime-scope.py"
LABEL_0035="scripts/validate-dec-0035-labels.py"
LABEL_0037="scripts/validate-dec-0037-labels.py"
RANDOM_SUFFIX=100

# Confirm the transition is actually live before asserting anything about it.
STEP="$(python3 -c 'import sys; sys.path.insert(0, "scripts"); import _common; print(_common.CANONICAL_CURRENT_STEP)')" \
  || abort_setup "could not read CANONICAL_CURRENT_STEP"
[ "$STEP" -ge 6 ] || abort_setup "CANONICAL_CURRENT_STEP is ${STEP}; this harness asserts the DEC-0037 transition and requires step >= 6"

echo "========================================================================"
echo "ADVERSARIAL TEST — STEP 6 VALIDATORS (DEC-0037 transition)"
echo "========================================================================"
echo

# ---------------------------------------------------------------------------
# CONTROL — the current tree is legitimate and must be accepted by all.
# ---------------------------------------------------------------------------
echo "CONTROL — legitimate input must be ACCEPTED"
expect_accept "runtime scope guard accepts the current tree" "$SCOPE_GUARD"
expect_accept "DEC-0035 label audit accepts the current tree" "$LABEL_0035"
expect_accept "DEC-0037 label audit accepts the current tree" "$LABEL_0037"
echo

# ---------------------------------------------------------------------------
# ACCEPT — the six Step 6 labels are now authorised (DEC-0037).
# ---------------------------------------------------------------------------
echo "AUTHORISED — a Step 6 table/route must now be ACCEPTED by the scope guard"

PRODUKSI="$(printf '%s%s' 'produk' 'si')"
F="$(migration_fixture "$PRODUKSI")"
expect_accept "scope guard accepts a Step 6 production table (DEC-0037)" "$SCOPE_GUARD"
expect_accept "DEC-0037 label audit accepts a Step 6 production table" "$LABEL_0037"
expect_accept "DEC-0035 label audit (step-aware) accepts a Step 6 production table" "$LABEL_0035"
rm -f "$F"

QC="$(printf '%s_%s' 'quality' 'controls')"
F="$(migration_fixture "$QC")"
expect_accept "scope guard accepts a Step 6 quality-control table (DEC-0037)" "$SCOPE_GUARD"
rm -f "$F"

REWORKS="$(printf '%s%s' 're' 'works')"
F="$(migration_fixture "$REWORKS")"
expect_accept "scope guard accepts a Step 6 rework table (DEC-0037)" "$SCOPE_GUARD"
rm -f "$F"

PRODUCTION_ROUTE="$(printf '%s' 'produksi')"
ROUTES="backend/routes/api.php"
[ -f "$ROUTES" ] || abort_setup "$ROUTES not found"
backup_file "$ROUTES"
printf "\n// ADVERSARIAL FIXTURE — removed by the harness.\nRoute::post('%s', [\\App\\Http\\Controllers\\HealthController::class, 'show']);\n" \
  "$PRODUCTION_ROUTE" >> "$ROUTES" || abort_setup "could not append the production route"
grep -q "$PRODUCTION_ROUTE" "$ROUTES" || abort_setup "the production route was not appended"
expect_accept "scope guard accepts a Step 6 production route (DEC-0037)" "$SCOPE_GUARD"
cleanup
BACKED_UP=()
echo

# ---------------------------------------------------------------------------
# REJECT — every Step 7+ label remains forbidden, plainly and renamed.
# ---------------------------------------------------------------------------
echo "FORWARD-LEAK GUARD — a Step 7+ feature must still be REJECTED"

# Step 7 — customer tracking portal / token.
TRACKING="$(printf '%s_%s' 'public' 'tracking')"
F="$(migration_fixture "$TRACKING")"
expect_reject "scope guard rejects a Step 7 tracking-portal table" "$SCOPE_GUARD"
expect_reject "DEC-0037 label audit rejects a Step 7 tracking-portal table" "$LABEL_0037"
rm -f "$F"

# Step 7 — WhatsApp / notification provider.
WA="$(printf '%s' 'whatsapp')"
F="$(migration_fixture "${WA}_messages")"
expect_reject "scope guard rejects a Step 7 WhatsApp table" "$SCOPE_GUARD"
expect_reject "DEC-0037 label audit rejects a Step 7 WhatsApp table" "$LABEL_0037"
rm -f "$F"

# Rename-evasion (Rule 36 hard rule 4): an Indonesian/compound of the token.
F="$(migration_fixture "laporan_${WA}")"
expect_reject "DEC-0037 label audit rejects a compound-renamed WhatsApp table" "$LABEL_0037"
rm -f "$F"

# Step 8 — pickup / delivery.
PICKUP="$(printf '%s' 'penjemputan')"
F="$(migration_fixture "$PICKUP")"
expect_reject "scope guard rejects a Step 8 pickup table" "$SCOPE_GUARD"
expect_reject "DEC-0037 label audit rejects a Step 8 pickup table" "$LABEL_0037"
rm -f "$F"

DELIVERY="$(printf '%s' 'pengantaran')"
F="$(migration_fixture "$DELIVERY")"
expect_reject "scope guard rejects a Step 8 delivery table" "$SCOPE_GUARD"
rm -f "$F"

# Step 9 — reminder ladder / unclaimed aging.
REMINDERS="$(printf '%s' 'reminders')"
F="$(migration_fixture "$REMINDERS")"
expect_reject "scope guard rejects a Step 9 reminder-ladder table" "$SCOPE_GUARD"
expect_reject "DEC-0037 label audit rejects a Step 9 reminder-ladder table" "$LABEL_0037"
rm -f "$F"

UNCLAIMED="$(printf '%s_%s' 'unclaimed' 'laundry')"
F="$(migration_fixture "$UNCLAIMED")"
expect_reject "scope guard rejects a Step 9 unclaimed-laundry table" "$SCOPE_GUARD"
rm -f "$F"
echo

# ---------------------------------------------------------------------------
# Tree integrity + summary.
# ---------------------------------------------------------------------------
AFTER="$(tree_fingerprint)"
echo "========================================================================"
if [ "$BEFORE" = "$AFTER" ]; then
  echo "working tree verified byte-identical before and after"
else
  echo "  FAIL  working tree changed — a fixture was left behind"
  FAILED=$((FAILED + 1))
fi

echo "SUMMARY [step-06-validators]: ${PASSED}/${TOTAL} expectations met, ${FAILED} failed"
if [ "$FAILED" -ne 0 ] || [ "$BEFORE" != "$AFTER" ]; then
  echo "RESULT: FAIL (step-06-validators)"
  exit 1
fi
echo "RESULT: PASS (step-06-validators)"
