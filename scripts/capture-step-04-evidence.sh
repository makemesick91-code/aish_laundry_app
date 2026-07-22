#!/usr/bin/env bash
#
# Regenerate the Step 4 evidence captures at the CURRENT commit.
#
# WHY THIS IS A SCRIPT AND NOT A SEQUENCE OF PASTED TERMINAL OUTPUT
# -----------------------------------------------------------------
# Evidence produced at one SHA does not carry to another (Rule 01, DEC-0013).
# The Step 4 pack drifted 49 commits behind its binding SHA precisely because
# re-capturing was a manual ritual nobody repeated. A script makes the recapture
# one command, so the cheap thing and the correct thing are the same thing.
#
# Every file it writes states the SHA it was produced at. It refuses to run on a
# dirty tree, because a capture from a working tree that does not match any
# commit describes a state that will never exist again.
set -uo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO"

OUT="evidence/step-04"
SHA="$(git rev-parse HEAD)"
STAMP="$(TZ=Asia/Jakarta date '+%Y-%m-%d %H:%M:%S %Z')"

if [ -n "$(git status --porcelain)" ]; then
  echo "REFUSING: the working tree is dirty."
  echo "A capture from an uncommitted tree describes a state no commit holds."
  git status --porcelain | head -20
  exit 1
fi

# Absolute machine paths are normalised out of every capture.
#
# The Flutter runner prints absolute test paths, which embed the operator's home
# directory. On a PUBLIC repository that is machine-specific noise nobody needs,
# and it makes two captures from two machines differ for a reason unrelated to
# the result. It is not a secret — the same username is in every commit — which
# is why this is normalisation rather than redaction.
scrub() { sed -E 's#/home/[A-Za-z0-9_.-]+/Projects/aish_laundry#<REPO>#g'; }

header() {
  printf '%s\n%s\n\nBound to commit: %s\nTimestamp (Asia/Jakarta): %s\nEnvironment: PHP %s, PostgreSQL 18.4, Redis 8.2 (loopback-bound dev services)\n\n' \
    "$1" "$(printf '=%.0s' $(seq 1 ${#1}))" "$SHA" "$STAMP" "$(php -r 'echo PHP_VERSION;')"
}

echo "Capturing Step 4 evidence at $SHA"

# --- Backend suite ---------------------------------------------------------
{
  header "STEP 4 — BACKEND SUITE"
  echo '$ cd backend && php artisan test'
  echo ""
  (cd backend && php artisan test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | scrub | tail -25)
} > "$OUT/backend-suite.txt"

# --- Database lifecycle ----------------------------------------------------
{
  header "STEP 4 — DATABASE LIFECYCLE (Rule 43 hard rule 3)"
  echo "Fresh apply, rollback of the Step 4 migrations, and re-apply. A migration"
  echo "applied once on one database with no rollback exercised is unverified in"
  echo "that dimension however simple it looks."
  echo ""
  echo '$ php artisan migrate:fresh --force'
  (cd backend && php artisan migrate:fresh --force 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | scrub | tail -6)
  echo ""
  echo '$ php artisan migrate:rollback --step=5 --force'
  (cd backend && php artisan migrate:rollback --step=5 --force 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | scrub | grep -E 'DONE|Nothing')
  echo ""
  echo '$ php artisan migrate --force'
  (cd backend && php artisan migrate --force 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | scrub | grep -E 'DONE|Nothing')
} > "$OUT/database-lifecycle.txt"

# --- Live schema invariants ------------------------------------------------
{
  header "STEP 4 — LIVE SCHEMA INVARIANTS"
  echo "Queried from the RUNNING database, not read from migration source. A"
  echo "migration can say one thing and the column end up another — through a"
  echo "later ALTER, a half-applied rollback, or an engine substituting a type."
  echo ""
  (cd backend && php -r '
require "vendor/autoload.php"; $a=require "bootstrap/app.php";
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

echo "--- money columns (Rule 04 hard rule 2: integer Rupiah, never floating point)\n";
foreach (DB::select("SELECT table_name, column_name, data_type FROM information_schema.columns WHERE table_schema=current_schema() AND (column_name LIKE ? OR column_name LIKE ?) ORDER BY 1,2", ["%amount%","%rupiah%"]) as $r)
  printf("  %-22s %-18s %s\n", $r->table_name, $r->column_name, $r->data_type);

echo "\n--- consent append-only triggers (SEC-12; A = ENABLE ALWAYS, fires under replica mode)\n";
foreach (DB::select("SELECT tgname, tgenabled FROM pg_trigger t JOIN pg_class c ON c.oid=t.tgrelid WHERE c.relname=? AND NOT t.tgisinternal ORDER BY 1", ["customer_consents"]) as $r)
  printf("  %-40s tgenabled=%s\n", $r->tgname, $r->tgenabled);

echo "\n--- price-list removal guard (NEW-01; engine-level, not a model event)\n";
foreach (DB::select("SELECT tgname, tgenabled FROM pg_trigger t JOIN pg_class c ON c.oid=t.tgrelid WHERE c.relname=? AND NOT t.tgisinternal ORDER BY 1", ["price_lists"]) as $r)
  printf("  %-40s tgenabled=%s\n", $r->tgname, $r->tgenabled);

echo "\n--- price_lists unique constraints (NEW-05; no equivalent duplicate)\n";
foreach (DB::select("SELECT conname, pg_get_constraintdef(oid) d FROM pg_constraint WHERE conrelid=to_regclass(?) AND contype=? ORDER BY 1", ["price_lists","u"]) as $r)
  printf("  %-40s %s\n", $r->conname, $r->d);

echo "\n--- supersession composite foreign key (NEW/N3; carries tenant_id)\n";
foreach (DB::select("SELECT conname, pg_get_constraintdef(oid) d FROM pg_constraint WHERE conname=?", ["price_lists_supersedes_same_tenant_foreign"]) as $r)
  printf("  %-40s %s\n", $r->conname, $r->d);

echo "\n--- every business table carries tenant_id (Rule 02 hard rule 7)\n";
$missing = DB::select("SELECT t.table_name FROM information_schema.tables t WHERE t.table_schema=current_schema() AND t.table_type=? AND t.table_name NOT IN (?,?,?,?,?,?,?,?) AND NOT EXISTS (SELECT 1 FROM information_schema.columns c WHERE c.table_schema=current_schema() AND c.table_name=t.table_name AND c.column_name=?) ORDER BY 1",
  ["BASE TABLE","migrations","users","password_reset_tokens","personal_access_tokens","jobs","cache","cache_locks","tenants","tenant_id"]);
if ($missing === []) { echo "  none missing\n"; }
else { foreach ($missing as $r) printf("  MISSING tenant_id: %s\n", $r->table_name); }
' 2>&1)
} > "$OUT/schema-invariants.txt"

# --- Consent protection, behavioural ---------------------------------------
{
  header "SEC-12 — CONSENT PROTECTION, BEHAVIOURAL PROOF"
  echo "Normal AND replica mode. The fixture is asserted to exist BEFORE any"
  echo "refusal is trusted: a closure reviewer's fixture once failed a check"
  echo "constraint and the row-level refusals then passed vacuously on an empty"
  echo "table."
  echo ""
  echo '$ php artisan test --filter="append_only|replication_role_bypass|truncated_away|reset_by_raw_sql"'
  echo ""
  (cd backend && php artisan test --filter='append_only|replication_role_bypass|truncated_away|reset_by_raw_sql' 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | scrub | tail -12)
} > "$OUT/consent-protection.txt"

# --- Isolation matrix ------------------------------------------------------
{
  header "STEP 4 — TENANT ISOLATION MATRIX (Rule 48 hard rule 3)"
  echo "Every access path independently: direct id, list, filter, free-text"
  echo "search, export, and file URL. A test proving only the direct-id path does"
  echo "not satisfy the gate."
  echo ""
  echo '$ php artisan test --filter="Isolation"'
  echo ""
  (cd backend && php artisan test --filter='Isolation' 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | scrub | tail -20)
} > "$OUT/isolation-matrix.txt"

# --- Flutter ---------------------------------------------------------------
{
  header "STEP 4 — FLUTTER SUITES"
  echo '$ dart analyze'
  dart analyze 2>&1 | tail -3
  echo ""
  echo '$ flutter test apps/ops_android/test packages'
  flutter test apps/ops_android/test packages 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | scrub | tail -3
  echo ""
  echo '$ (cd apps/admin_web && flutter test)   # run from the app dir: its web-storage scan reads ./lib'
  (cd apps/admin_web && flutter test 2>&1 | sed 's/\x1b\[[0-9;]*m//g' | scrub | tail -3)
} > "$OUT/flutter-suites.txt"

# --- Governance validators -------------------------------------------------
{
  header "STEP 4 — GOVERNANCE VALIDATORS"
  for v in validate-master-source validate-required-files validate-decisions \
           validate-runtime-scope validate-dec-0030-labels validate-production-composition \
           validate-auth-runtime-truth; do
    printf '$ python3 scripts/%s.py\n' "$v"
    python3 "scripts/$v.py" 2>&1 | grep -E 'SUMMARY|RESULT'
    echo ""
  done
} > "$OUT/governance-validators.txt"

# --- Adversarial harnesses -------------------------------------------------
{
  header "STEP 4 — ADVERSARIAL VALIDATOR HARNESSES (Rule 47)"
  echo "A validator that has only run against correct input is an untested"
  echo "validator. Each harness mutates a DISPOSABLE COPY and verifies the working"
  echo "tree is byte-identical afterwards."
  echo ""
  for h in test-step-04-validators test-production-composition-guard test-auth-runtime-truth; do
    printf '$ bash scripts/%s.sh\n' "$h"
    bash "scripts/$h.sh" 2>&1 | grep -E 'SUMMARY|RESULT|working tree'
    echo ""
  done
} > "$OUT/adversarial.txt"

# --- Step verifier chain ---------------------------------------------------
{
  header "STEP 4 — VERIFIER CHAIN, FRESH AT THIS SHA"
  echo "Counts captured at THIS commit. A count from an earlier SHA is not"
  echo "carried forward — that error (N1) is what this line exists to prevent."
  echo ""
  for v in 00 01 02 03 04; do
    printf '$ bash scripts/verify-step-%s.sh\n' "$v"
    out="$(bash "scripts/verify-step-$v.sh" 2>&1)"; rc=$?
    echo "$out" | sed 's/\x1b\[[0-9;]*m//g' | scrub | grep -iE 'VERIFICATION:|SKIP ' | head -3
    echo "  exit=$rc"
    echo ""
  done
  echo "NOTE ON exit=2 FROM verify-step-03: the DEC-0026 scaffold-authorization"
  echo "suite reports a visible exit-78 SKIP off a Step 3 feature branch, by"
  echo "owner-approved branch/path pin. It is never represented as PASS, and no"
  echo "Step 4 gate is hidden behind it — the Step 4 verifier runs its own gates"
  echo "independently and reports this as a named skip."
} > "$OUT/verify-chain.txt"

echo "Captured to $OUT at $SHA"
