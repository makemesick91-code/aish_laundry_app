#!/usr/bin/env bash
#
# ADVERSARIAL TEST OF THE STEP 4 VALIDATORS.
#
# Rule 47 and Rule 33: a validator that has only ever been run against CORRECT
# input is an untested validator, and reporting it as a passing gate overstates
# the assurance it actually provides. This harness runs the Step 4 validators
# against deliberately broken input and requires each one to FAIL.
#
# TWO DISCIPLINES INHERITED FROM THE STEP 3 HARNESS CORRECTION (Rule 49)
# ----------------------------------------------------------------------
#  1. FIXTURES ARE ASSEMBLED AT RUNTIME, never embedded as literals. The earlier
#     Step 3 harness embedded literal secret fixtures that tripped the guard
#     inside every sandbox regardless of whether the intended mutation ran — so
#     mutations appeared "caught" when the mutation setup had in fact failed and
#     never executed. That produced the SUPERSEDED "31/31" figure.
#  2. SETUP FAILURE IS LOUD. If a mutation cannot be applied, the harness aborts
#     rather than counting a pass. A gate that could not run has verified
#     nothing.
#
# THE WORKING TREE IS VERIFIED BYTE-IDENTICAL before and after. A harness that
# left a mutation behind would be worse than no harness.
#
# Exit 0 = every expectation met. Exit 1 = at least one validator failed to
# catch a violation, or the tree was not restored.

set -uo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO"

PASSED=0
FAILED=0
TOTAL=0

# ---------------------------------------------------------------------------
# Tree integrity
# ---------------------------------------------------------------------------
tree_fingerprint() {
  git status --porcelain=v1 | sort
  git diff --no-color | sha256sum
}

BEFORE="$(tree_fingerprint)"

# Restore everything this harness touched, whatever happens.
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

# ---------------------------------------------------------------------------
# Expectation helpers
# ---------------------------------------------------------------------------

# Assert a validator FAILS. Used for every deliberate violation.
expect_reject() {
  local description="$1"
  local validator="$2"
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

# Assert a validator PASSES. Used for the legitimate control cases, without
# which a validator that rejected EVERYTHING would score a perfect result.
expect_accept() {
  local description="$1"
  local validator="$2"
  TOTAL=$((TOTAL + 1))

  if python3 "$validator" >/dev/null 2>&1; then
    echo "  ok    ${description}"
    PASSED=$((PASSED + 1))
  else
    echo "  FAIL  ${description}"
    echo "        ${validator} REJECTED legitimate Step 4 input."
    FAILED=$((FAILED + 1))
  fi
}

abort_setup() {
  echo "SETUP FAILED: $1" >&2
  echo "A mutation that never applied would score as 'caught'. Aborting." >&2
  exit 1
}

# Write a file and record it for cleanup. Content is assembled from arguments at
# runtime, never stored in this file as a complete literal.
make_fixture() {
  local path="$1"
  shift
  mkdir -p "$(dirname "$path")" || abort_setup "could not create $(dirname "$path")"
  printf '%s\n' "$@" > "$path" || abort_setup "could not write $path"
  CREATED_FILES+=("$path")
  [ -s "$path" ] || abort_setup "$path was written empty"
}

SCOPE_GUARD="scripts/validate-runtime-scope.py"
LABEL_AUDIT="scripts/validate-dec-0030-labels.py"

# The canonical current step. DEC-0035 permitted the seven Step 5 labels
# (order, payment, receipt/nota, POS, intake, refund, QRIS) from step 5 onward.
# The order/payment/receipt fixtures below were VIOLATIONS while step < 5; from
# step 5 they are authorised runtime, and that boundary's adversarial coverage —
# Step 5 accepted, Step 6 rejected — moves to test-step-05-validators.sh. This
# harness keeps its Step-4-boundary assertions and skips the superseded ones.
STEP="$(python3 -c 'import sys; sys.path.insert(0, "scripts"); import _common; print(_common.CANONICAL_CURRENT_STEP)')" \
  || abort_setup "could not read CANONICAL_CURRENT_STEP"

# expect_reject for a token that DEC-0035 later permitted: a real reject below
# step 5, a recorded skip at/after it (coverage lives in test-step-05).
reject_step5_token() {
  if [ "$STEP" -lt 5 ]; then
    expect_reject "$1" "$2"
  else
    echo "  --    ${1} — superseded by DEC-0035 (covered in test-step-05-validators.sh)"
  fi
}

echo "========================================================================"
echo "ADVERSARIAL TEST — STEP 4 VALIDATORS"
echo "========================================================================"
echo

# ---------------------------------------------------------------------------
# CONTROL CASES — the current tree is legitimate Step 4 and must be accepted.
# ---------------------------------------------------------------------------
echo "CONTROL — legitimate Step 4 input must be ACCEPTED"
expect_accept "runtime scope guard accepts the current tree" "$SCOPE_GUARD"
expect_accept "DEC-0030 label audit accepts the current tree" "$LABEL_AUDIT"
echo

# ---------------------------------------------------------------------------
# VIOLATIONS — a Step 5+ feature, under several names.
# ---------------------------------------------------------------------------
echo "VIOLATION — a Step 5+ table, under its plain name and renamed"

# Token assembled from parts so this script's own text does not contain the
# forbidden identifier as a literal.
ORDERS="$(printf '%s%s' 'ord' 'ers')"
RECEIPTS="$(printf '%s%s' 'receip' 'ts')"
NOTA="$(printf '%s%s' 'no' 'ta')"

MIGRATION="backend/database/migrations/2026_07_21_999001_create_${ORDERS}_table.php"
make_fixture "$MIGRATION" \
  '<?php' \
  '// ADVERSARIAL FIXTURE — deliberately out of scope. Removed by the harness.' \
  'return new class extends Migration {' \
  '  public function up(): void {' \
  "    Schema::create('${ORDERS}', function (Blueprint \$table) {" \
  "      \$table->uuid('id')->primary();" \
  '    });' \
  '  }' \
  '};'
grep -q "$ORDERS" "$MIGRATION" || abort_setup "the orders fixture does not contain its token"
reject_step5_token "scope guard rejects a Step 5 order table" "$SCOPE_GUARD"
reject_step5_token "label audit rejects a Step 5 order table" "$LABEL_AUDIT"
rm -f "$MIGRATION"

# The rename-evasion case. Rule 36 hard rule 4 treats renaming to evade
# structural detection as the same violation as building it plainly.
MIGRATION2="backend/database/migrations/2026_07_21_999002_create_sales_${RECEIPTS}_table.php"
make_fixture "$MIGRATION2" \
  '<?php' \
  '// ADVERSARIAL FIXTURE — deliberately out of scope. Removed by the harness.' \
  'return new class extends Migration {' \
  '  public function up(): void {' \
  "    Schema::create('sales_${RECEIPTS}', function (Blueprint \$table) {" \
  "      \$table->uuid('id')->primary();" \
  '    });' \
  '  }' \
  '};'
grep -q "$RECEIPTS" "$MIGRATION2" || abort_setup "the receipts fixture does not contain its token"
reject_step5_token "label audit rejects a compound-renamed receipt table" "$LABEL_AUDIT"
rm -f "$MIGRATION2"

# The Indonesian-rename case: a POS document named in Bahasa Indonesia.
MIGRATION3="backend/database/migrations/2026_07_21_999003_create_${NOTA}_penjualan_table.php"
make_fixture "$MIGRATION3" \
  '<?php' \
  '// ADVERSARIAL FIXTURE — deliberately out of scope. Removed by the harness.' \
  'return new class extends Migration {' \
  '  public function up(): void {' \
  "    Schema::create('${NOTA}_penjualan', function (Blueprint \$table) {" \
  "      \$table->uuid('id')->primary();" \
  '    });' \
  '  }' \
  '};'
grep -q "$NOTA" "$MIGRATION3" || abort_setup "the nota fixture does not contain its token"
reject_step5_token "label audit rejects an Indonesian-renamed document table" "$LABEL_AUDIT"
rm -f "$MIGRATION3"
echo

# ---------------------------------------------------------------------------
# VIOLATION — a Step 5 route on an existing, permitted module.
# ---------------------------------------------------------------------------
echo "VIOLATION — a Step 5 route added to a permitted Step 4 module"

ROUTES="backend/routes/api.php"
[ -f "$ROUTES" ] || abort_setup "$ROUTES not found"
backup_file "$ROUTES"

PAYMENT="$(printf '%s%s' 'pay' 'ments')"
printf "\n// ADVERSARIAL FIXTURE — removed by the harness.\nRoute::post('%s', [PriceListController::class, 'store']);\n" \
  "$PAYMENT" >> "$ROUTES" || abort_setup "could not append the payment route"
grep -q "$PAYMENT" "$ROUTES" || abort_setup "the payment route was not appended"
reject_step5_token "scope guard rejects a Step 5 payment route" "$SCOPE_GUARD"
reject_step5_token "label audit rejects a Step 5 payment route" "$LABEL_AUDIT"
cleanup
BACKED_UP=()
echo

# ---------------------------------------------------------------------------
# VIOLATION — printer configuration growing into a printed document.
# ---------------------------------------------------------------------------
echo "VIOLATION — printer configuration becoming a document (FR-045 vs FR-052)"

PRINTER_MODEL="backend/app/Modules/Organization/Models/OutletPrinter${RECEIPTS^}Template.php"
make_fixture "$PRINTER_MODEL" \
  '<?php' \
  '// ADVERSARIAL FIXTURE — deliberately out of scope. Removed by the harness.' \
  'namespace App\Modules\Organization\Models;' \
  "class OutletPrinter${RECEIPTS^}Template extends Model {" \
  "  protected \$table = 'outlet_printer_${RECEIPTS}';" \
  '}'
grep -q "$RECEIPTS" "$PRINTER_MODEL" || abort_setup "the printer-document fixture lacks its token"
# The FR-045-vs-FR-052 boundary (printer config must not become the nota) only
# holds below step 5; DEC-0035 authorises the nota from step 5, so this is
# superseded exactly like the order/payment/receipt fixtures above.
reject_step5_token "label audit rejects a document template beside printer config" "$LABEL_AUDIT"
rm -f "$PRINTER_MODEL"
echo

# ---------------------------------------------------------------------------
# VIOLATION — a permitted label citing a requirement the PRD does not carry.
# ---------------------------------------------------------------------------
echo "VIOLATION — a permitted label traced to a non-existent requirement"

backup_file "$LABEL_AUDIT"
# FR-999 does not exist in the PRD. A label permitted on the strength of a
# requirement that does not exist is permitted on nothing.
sed -i 's/"requirements": \["FR-045"\]/"requirements": ["FR-045", "FR-999"]/' "$LABEL_AUDIT" \
  || abort_setup "could not mutate the label audit's requirement list"
grep -q 'FR-999' "$LABEL_AUDIT" || abort_setup "the FR-999 mutation was not applied"
expect_reject "label audit rejects a label citing an absent requirement" "$LABEL_AUDIT"
cleanup
BACKED_UP=()
echo

# ---------------------------------------------------------------------------
# VIOLATION — a future-step route pointed at a real screen.
# ---------------------------------------------------------------------------
echo "VIOLATION — a future-step route no longer resolving to a placeholder"

ROUTER="apps/ops_android/lib/src/routing/ops_router.dart"
[ -f "$ROUTER" ] || abort_setup "$ROUTER not found"
backup_file "$ROUTER"

# Removing the placeholder widget is what makes the `future*` exemption unsafe:
# the label audit skips those route segments only while they reach nothing.
sed -i 's/_FuturePage/_RealCounterScreen/g' "$ROUTER" \
  || abort_setup "could not mutate the router"
grep -q '_FuturePage' "$ROUTER" && abort_setup "the placeholder mutation did not apply"
expect_reject "label audit rejects a future route with no placeholder" "$LABEL_AUDIT"
cleanup
BACKED_UP=()
echo

# ---------------------------------------------------------------------------
# Restore verification
# ---------------------------------------------------------------------------
cleanup
CREATED_FILES=()
BACKED_UP=()

AFTER="$(tree_fingerprint)"

echo "========================================================================"
if [ "$BEFORE" != "$AFTER" ]; then
  echo "RESULT: FAIL — the working tree was NOT restored."
  echo "A harness that leaves a mutation behind has corrupted the thing it tested."
  diff <(echo "$BEFORE") <(echo "$AFTER") || true
  exit 1
fi
echo "working tree verified byte-identical before and after"
echo "SUMMARY [step-04-validators]: ${PASSED}/${TOTAL} expectations met, ${FAILED} failed"

if [ "$FAILED" -gt 0 ]; then
  echo "RESULT: FAIL (step-04-validators)"
  exit 1
fi

echo "RESULT: PASS (step-04-validators)"
exit 0
