#!/usr/bin/env bash
#
# ADVERSARIAL TEST OF THE STEP 5 VALIDATORS (the DEC-0035 guard transition).
#
# Rule 47 and Rule 33: a guard that permits new runtime must be tested against
# both the runtime it now ALLOWS and the runtime it still FORBIDS before it is
# relied upon as a gate. DEC-0035 moved seven labels — POS, order, laundry
# intake, payment, refund, QRIS, receipt/nota — from forbidden to permitted, and
# raised _common.CANONICAL_CURRENT_STEP from 4 to 5. This harness proves the
# transition in BOTH directions:
#
#   ACCEPT — the seven Step 5 labels are now authorised runtime.
#   REJECT — the twenty-three Step 6+ labels remain forbidden, plainly and renamed.
#
# Disciplines inherited from the Step 3/4 harness corrections (Rule 49):
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
    echo "        ${validator} REJECTED legitimate Step 5 input."
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
  local path="backend/database/migrations/2026_07_23_999${RANDOM_SUFFIX}_create_${table}_table.php"
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
LABEL_0030="scripts/validate-dec-0030-labels.py"
LABEL_0035="scripts/validate-dec-0035-labels.py"
RANDOM_SUFFIX=100

# Confirm the transition is actually live before asserting anything about it.
STEP="$(python3 -c 'import sys; sys.path.insert(0, "scripts"); import _common; print(_common.CANONICAL_CURRENT_STEP)')" \
  || abort_setup "could not read CANONICAL_CURRENT_STEP"
[ "$STEP" -ge 5 ] || abort_setup "CANONICAL_CURRENT_STEP is ${STEP}; this harness asserts the DEC-0035 transition and requires step >= 5"

echo "========================================================================"
echo "ADVERSARIAL TEST — STEP 5 VALIDATORS (DEC-0035 transition)"
echo "========================================================================"
echo

# ---------------------------------------------------------------------------
# CONTROL — the current tree is legitimate and must be accepted by all three.
# ---------------------------------------------------------------------------
echo "CONTROL — legitimate input must be ACCEPTED"
expect_accept "runtime scope guard accepts the current tree" "$SCOPE_GUARD"
expect_accept "DEC-0030 label audit accepts the current tree" "$LABEL_0030"
expect_accept "DEC-0035 label audit accepts the current tree" "$LABEL_0035"
echo

# ---------------------------------------------------------------------------
# ACCEPT — the seven Step 5 labels are now authorised (DEC-0035).
# ---------------------------------------------------------------------------
echo "AUTHORISED — a Step 5 table/route must now be ACCEPTED by the scope guard"

ORDERS="$(printf '%s%s' 'ord' 'ers')"
F="$(migration_fixture "$ORDERS")"
expect_accept "scope guard accepts a Step 5 order table (DEC-0035)" "$SCOPE_GUARD"
expect_accept "DEC-0035 label audit accepts a Step 5 order table" "$LABEL_0035"
rm -f "$F"

PAYMENTS="$(printf '%s%s' 'pay' 'ments')"
F="$(migration_fixture "$PAYMENTS")"
expect_accept "scope guard accepts a Step 5 payment table (DEC-0035)" "$SCOPE_GUARD"
rm -f "$F"

ROUTES="backend/routes/api.php"
[ -f "$ROUTES" ] || abort_setup "$ROUTES not found"
backup_file "$ROUTES"
printf "\n// ADVERSARIAL FIXTURE — removed by the harness.\nRoute::post('%s', [\\App\\Http\\Controllers\\HealthController::class, 'show']);\n" \
  "$PAYMENTS" >> "$ROUTES" || abort_setup "could not append the payment route"
grep -q "$PAYMENTS" "$ROUTES" || abort_setup "the payment route was not appended"
expect_accept "scope guard accepts a Step 5 payment route (DEC-0035)" "$SCOPE_GUARD"
cleanup
BACKED_UP=()
echo

# ---------------------------------------------------------------------------
# REJECT — the Step 6+ labels remain forbidden, plainly and renamed.
# ---------------------------------------------------------------------------
echo "FORWARD-LEAK GUARD — a Step 6+ feature must still be REJECTED"

PRODUKSI="$(printf '%s%s' 'produk' 'si')"
F="$(migration_fixture "$PRODUKSI")"
expect_reject "scope guard rejects a Step 6 production table" "$SCOPE_GUARD"
expect_reject "DEC-0035 label audit rejects a Step 6 production table" "$LABEL_0035"
rm -f "$F"

# Rename-evasion (Rule 36 hard rule 4): an Indonesian compound of the token.
F="$(migration_fixture "laporan_${PRODUKSI}")"
expect_reject "DEC-0035 label audit rejects a compound-renamed production table" "$LABEL_0035"
rm -f "$F"

DELIVERY="$(printf '%s%s' 'pengan' 'taran')"
F="$(migration_fixture "$DELIVERY")"
expect_reject "scope guard rejects a Step 8 delivery table" "$SCOPE_GUARD"
expect_reject "DEC-0035 label audit rejects a Step 8 delivery table" "$LABEL_0035"
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

echo "SUMMARY [step-05-validators]: ${PASSED}/${TOTAL} expectations met, ${FAILED} failed"
if [ "$FAILED" -ne 0 ] || [ "$BEFORE" != "$AFTER" ]; then
  echo "RESULT: FAIL (step-05-validators)"
  exit 1
fi
echo "RESULT: PASS (step-05-validators)"
exit 0
