#!/usr/bin/env bash
#
# Adversarial test harness for the Step 3 runtime-scope guard.
#
# Rule 33 item 15: a validator that has only ever been run against correct input
# is an UNTESTED validator, and reporting it as a passing gate overstates the
# assurance it provides. This harness proves the guard turns RED for each class
# of violation DEC-0024 says it must catch, and GREEN for legitimate Step 3
# runtime.
#
# Safety properties:
#   - every mutation is applied to a throwaway copy of the repository, never to
#     the working tree;
#   - the working tree is verified byte-identical before and after;
#   - no git history operation is performed, so the destructive-operations guard
#     is never engaged or bypassed.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

PASS=0
FAIL=0
TOTAL=0

green() { printf '\033[32m%s\033[0m' "$*"; }
red()   { printf '\033[31m%s\033[0m' "$*"; }

WORK="$(mktemp -d)"
cleanup() { rm -rf "${WORK}"; }
trap cleanup EXIT

# The set of files git would actually carry: tracked, plus untracked-but-not-ignored
# (the latter can still be swept in by `git add -A`, so they belong in scope).
#
# Git-IGNORED files are deliberately excluded. A developer's local backend/.env is
# required to run the application and can never be committed, so copying it into
# every sandbox made the guard fail closed on a file that will not exist in a fresh
# clone or in CI — turning all five legitimate-case tests red for a reason that had
# nothing to do with what they were testing.
repo_payload() {
  { git ls-files -z; git ls-files -z --others --exclude-standard; } 2>/dev/null | sort -z -u
}

# Fingerprint that payload so we can prove we did not disturb the working tree.
fingerprint() {
  repo_payload | xargs -0 sha256sum 2>/dev/null | sha256sum | awk '{print $1}'
}
BEFORE="$(fingerprint)"

# Pristine copy used as the base for every mutation — fresh-clone equivalent.
BASE="${WORK}/base"
mkdir -p "${BASE}"
repo_payload | tar -cf - --null -T - 2>/dev/null | (cd "${BASE}" && tar -xf -)

# Apply the authorised Step 3 runtime baseline inside the current directory, so
# that feature-leakage mutations are tested against a realistic tree rather than
# against an empty one. Defined as a FUNCTION, not a string: a multi-line string
# spliced in front of "; cmd" produces a shell syntax error, and an earlier
# revision of this harness did exactly that — the setup silently never ran and
# every mutation appeared to be caught when nothing had been mutated at all.
step3_base() {
  mkdir -p backend/app backend/database/migrations apps/ops_android/lib packages/core/lib
  printf '{"name":"aish/backend"}\n'   > backend/composer.json
  printf 'name: ops_android\n'         > apps/ops_android/pubspec.yaml
  printf 'void main() {}\n'            > apps/ops_android/lib/main.dart
  printf '<?php\nclass Kernel {}\n'    > backend/app/Kernel.php
  # Version-agnostic: this pinned the literal 1.3.0 and became a silent no-op once
  # the Master Source moved past it. A base fixture that quietly stops doing its job
  # is the same defect class M28 exists to catch.
  sed -i -E 's/^\*\*Document version: [0-9]+\.[0-9]+\.[0-9]+\*\*/**Document version: 1.4.1**/' docs/MASTER_SOURCE.md
  grep -q '^\*\*Document version: 1\.4\.1\*\*' docs/MASTER_SOURCE.md
  touch docs/decisions/DEC-0024-step-3-runtime-introduction.md
}

# run_setup <sandbox> <setup-string>
# Fails LOUDLY if the setup itself errors. A mutation harness whose setup silently
# fails reports false confidence, which is worse than no harness.
run_setup() {
  local sandbox="$1"; shift
  local out
  if ! out="$( cd "${sandbox}" && eval "$*" 2>&1 )"; then
    printf '  %s  setup failed: %s\n' "$(red ERROR)" "${out}" >&2
    return 1
  fi
  return 0
}

# expect_red <id> <description> <setup-commands>
expect_red() {
  local id="$1"; shift
  local desc="$1"; shift
  TOTAL=$((TOTAL + 1))
  local sandbox="${WORK}/m${id}"
  rm -rf "${sandbox}"; cp -a "${BASE}" "${sandbox}"
  if ! run_setup "${sandbox}" "$@"; then
    printf '  %s  M%-2s %s — SETUP ERROR\n' "$(red FAIL)" "${id}" "${desc}"
    FAIL=$((FAIL + 1)); return
  fi
  if ( cd "${sandbox}" && python3 scripts/validate-runtime-scope.py ) >/dev/null 2>&1; then
    printf '  %s  M%-2s %s\n' "$(red FAIL)" "${id}" "${desc} — guard stayed GREEN"
    FAIL=$((FAIL + 1))
  else
    printf '  %s  M%-2s %s\n' "$(green 'ok  ')" "${id}" "${desc}"
    PASS=$((PASS + 1))
  fi
}

# expect_green <id> <description> <setup-commands>
expect_green() {
  local id="$1"; shift
  local desc="$1"; shift
  TOTAL=$((TOTAL + 1))
  local sandbox="${WORK}/g${id}"
  rm -rf "${sandbox}"; cp -a "${BASE}" "${sandbox}"
  if ! run_setup "${sandbox}" "$@"; then
    printf '  %s  G%-2s %s — SETUP ERROR\n' "$(red FAIL)" "${id}" "${desc}"
    FAIL=$((FAIL + 1)); return
  fi
  if ( cd "${sandbox}" && python3 scripts/validate-runtime-scope.py ) >/dev/null 2>&1; then
    printf '  %s  G%-2s %s\n' "$(green 'ok  ')" "${id}" "${desc}"
    PASS=$((PASS + 1))
  else
    printf '  %s  G%-2s %s\n' "$(red FAIL)" "${id}" "${desc} — guard wrongly turned RED"
    FAIL=$((FAIL + 1))
  fi
}

# The canonical current step. DEC-0035 permitted the seven Step 5 labels (order,
# payment, receipt/nota, POS, intake, refund, QRIS) from step 5 onward. The
# fixtures marked red_step5 below were VIOLATIONS while step < 5; from step 5 they
# are authorised Step 5 runtime, and that boundary's adversarial coverage — Step 5
# accepted, Step 6 rejected — lives in test-step-05-validators.sh. This harness
# keeps its Step-6+ leakage assertions unchanged and records the superseded Step-5
# ones as a visible skip: same strength, boundary moved forward by DEC-0035, no
# coverage lost. Mirrors reject_step5_token in test-step-04-validators.sh.
STEP="$(python3 -c 'import sys; sys.path.insert(0, "scripts"); import _common; print(_common.CANONICAL_CURRENT_STEP)')" \
  || { echo "could not read CANONICAL_CURRENT_STEP" >&2; exit 1; }

# red_step5 <id> <desc> <setup>: a real expect_red while step < 5; a visible,
# UNCOUNTED skip at/after step 5, because DEC-0035 made the token authorised
# runtime and its accept/reject coverage moved to test-step-05-validators.sh.
red_step5() {
  if [ "${STEP}" -lt 5 ]; then
    expect_red "$@"
  else
    printf '  --    M%-2s %s — superseded by DEC-0035 (covered in test-step-05-validators.sh)\n' "$1" "$2"
  fi
}

echo "========================================================================"
echo "ADVERSARIAL TEST — scripts/validate-runtime-scope.py"
echo "========================================================================"
echo
echo "-- runtime placement --"
expect_red 1  "pubspec.yaml outside an approved root"        "step3_base; printf 'name: x\n' > tools/pubspec.yaml 2>/dev/null || { mkdir -p tools && printf 'name: x\n' > tools/pubspec.yaml; }"
expect_red 2  "composer.json outside backend"                "step3_base; printf '{}\n' > composer.json"
expect_red 3  "Dart source outside an approved root"         "step3_base; mkdir -p sandbox && printf 'void main(){}\n' > sandbox/a.dart"
expect_red 4  "PHP source outside backend"                   "step3_base; mkdir -p sandbox && printf '<?php\n' > sandbox/a.php"
expect_red 5  "unauthorised executable prototype (Go)"       "step3_base; mkdir -p proto && printf 'package main\n' > proto/main.go"

echo
echo "-- Step 5+ business feature leakage (Step 5 authorised by DEC-0035; Step 6+ still forbidden) --"
red_step5   6  "POS module directory"                        "step3_base; mkdir -p backend/app/Modules/pos && printf '<?php\n' > backend/app/Modules/pos/Service.php"
red_step5   7  "payment route"                               "step3_base; printf '<?php\nRoute::post(\"api/v1/payments\", [X::class]);\n' > backend/routes_api.php && mv backend/routes_api.php backend/app/routes_api.php"
red_step5   8  "order migration filename"                    "step3_base; printf '<?php\n' > backend/database/migrations/2026_07_20_000000_create_orders_table.php"
red_step5   9  "orders table via Schema::create"             "step3_base; printf '<?php\nSchema::create(\"orders\", function(\$t){});\n' > backend/app/Mig.php"
expect_red 10 "tracking controller (tracking_tokens table)"  "step3_base; printf '<?php\nSchema::create(\"tracking_tokens\", function(\$t){});\n' > backend/app/Trk.php"
expect_red 11 "delivery module directory"                    "step3_base; mkdir -p backend/app/Modules/deliveries && printf '<?php\n' > backend/app/Modules/deliveries/D.php"
expect_red 12 "H+7 reminder worker (reminder_stages)"        "step3_base; printf '<?php\nSchema::create(\"reminder_stages\", function(\$t){});\n' > backend/app/Rem.php"
expect_red 13 "WhatsApp provider implementation"             "step3_base; mkdir -p backend/app/Modules/whatsapp && printf '<?php\n' > backend/app/Modules/whatsapp/Client.php"
red_step5  14 "QRIS provider implementation"                 "step3_base; mkdir -p backend/app/Modules/qris && printf '<?php\n' > backend/app/Modules/qris/Gateway.php"
red_step5  15 "Eloquent Payment model"                       "step3_base; printf '<?php\nclass Payment extends Model {}\n' > backend/app/Payment.php"
red_step5  16 "Flutter POS feature directory"                "step3_base; mkdir -p apps/ops_android/lib/features/pos && printf 'void main(){}\n' > apps/ops_android/lib/features/pos/screen.dart"

echo
echo "-- deployment --"
expect_red 17 "production docker-compose"                    "step3_base; printf 'services: {}\n' > infrastructure/docker-compose.prod.yml"
expect_red 18 "staging compose file"                         "step3_base; printf 'services: {}\n' > infrastructure/compose.staging.yml"
expect_red 19 "terraform directory"                          "step3_base; mkdir -p terraform && printf 'resource {}\n' > terraform/main.tf"
expect_red 20 "kubernetes manifests"                         "step3_base; mkdir -p k8s && printf 'kind: Deployment\n' > k8s/app.yaml"
expect_red 21 "deploy action in a workflow"                  "step3_base; printf 'jobs:\n  d:\n    steps:\n      - run: kubectl apply -f k8s/\n' > .github/workflows/deploy.yml"

echo
echo "-- secrets and PII --"
# Fixtures are ASSEMBLED AT RUNTIME from fragments so that this harness file
# contains no literal a secret scanner could match. Exempting this file from the
# scanner instead would create a blind spot in which a real secret could hide —
# the fixture is split, the guard is not weakened.
TOK="gh${_p:-}p"; TOK="${TOK}_ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
PK_OPEN="-----BEGIN RSA PRIVATE"; PK_OPEN="${PK_OPEN} KEY-----"
PHONE="0818"; PHONE="${PHONE}47362519"

expect_red 22 "committed .env"                               "step3_base; printf 'DB_PASSWORD=hunter2\n' > backend/.env"
expect_red 23 "private key file"                             "step3_base; printf -- '%s\nAAAA\n' '${PK_OPEN}' > infrastructure/server.pem"
expect_red 24 "database dump"                                "step3_base; printf 'INSERT INTO x;\n' > backend/dump.sql"
expect_red 25 "GitHub token in a file"                       "step3_base; printf 'token: %s\n' '${TOK}' > backend/notes.md"
expect_red 26 "customer PII fixture (real-looking phone)"    "step3_base; printf '| Budi | %s |\n' '${PHONE}' > backend/fixtures.md"

echo
echo "-- governance transition integrity --"
expect_red 27 "runtime present but DEC-0024 removed"         "step3_base; rm -f docs/decisions/DEC-0024-*.md"
# M28 is version-agnostic and VERIFIES its own mutation applied. It previously
# pinned the literal '1.4.0'; when the Master Source moved to 1.4.1 the sed matched
# nothing, exited 0, and the fixture reported a catch for a mutation that never
# ran — the same silent-no-op class that invalidated an earlier 31/31 figure
# (Rule 49). The trailing grep makes a failed mutation a loud SETUP ERROR.
expect_red 28 "runtime present but Master Source rolled back below 1.4.0" \
  "step3_base; sed -i -E 's/^\*\*Document version: [0-9]+\.[0-9]+\.[0-9]+\*\*/**Document version: 1.3.0**/' docs/MASTER_SOURCE.md; grep -q '^\*\*Document version: 1\.3\.0\*\*' docs/MASTER_SOURCE.md"
# The forward-leak boundary follows _common.CANONICAL_CURRENT_STEP: any step
# BEYOND the current one carrying a non-PLANNED status is a leak the guard rejects.
# These were pinned to Steps 4/5 at Step 3, moved to 5/6 at DEC-0028, and move to
# Step 6 now that DEC-0035 made Step 5 the current step and its GO the reality.
# The next transition moves them forward again. Same strength, boundary +1.
expect_red 29 "Step 6 claimed IN PROGRESS"                   "step3_base; printf '\n| Step 6 | Production Operations | IN PROGRESS |\n' >> docs/STATUS.md"
expect_red 30 "Step 6 claimed GO"                            "step3_base; printf '\n| Step 6 | Production Operations | GO |\n' >> docs/STATUS.md"
expect_red 31 "symlink escaping the repository"              "step3_base; ln -s /etc backend/escape"

echo
echo "-- DEC-0035: Step 6+ features stay forbidden after Step 5 opened --"
# Permitting the seven Step 5 labels (DEC-0035) must not have loosened anything
# else. The Step 5 fixtures below (nota, orders, payments, kasir) are red_step5:
# real rejections before step 5, superseded skips at step 5 (accept-coverage in
# test-step-05-validators.sh). The Step 6+ set (tracking_tokens, subscriptions)
# stays a hard rejection — this is the retained boundary, including the
# printer(Step 4)/nota(Step 5 FR-052) sharp edge.
red_step5  32 "receipt/nota table (printer is Step 4, nota is Step 5 FR-052)" \
  "step3_base; printf '<?php\nSchema::create(\"nota\", function(\$t){});\n' > backend/app/Nota.php"
red_step5  33 "orders table"                                 "step3_base; printf '<?php\nSchema::create(\"orders\", function(\$t){});\n' > backend/app/Ord.php"
red_step5  34 "payments table"                               "step3_base; printf '<?php\nSchema::create(\"payments\", function(\$t){});\n' > backend/app/Pay.php"
red_step5  35 "POS renamed to 'kasir' (evasion by renaming)" "step3_base; mkdir -p backend/app/Modules/kasir; printf '<?php\nclass X {}\n' > backend/app/Modules/kasir/X.php"
expect_red 36 "tracking_tokens table"                        "step3_base; printf '<?php\nSchema::create(\"tracking_tokens\", function(\$t){});\n' > backend/app/Trk.php"
expect_red 37 "subscription billing table"                   "step3_base; printf '<?php\nSchema::create(\"subscriptions\", function(\$t){});\n' > backend/app/Sub.php"

echo
echo "-- legitimate Step 3 runtime must PASS --"
expect_green 1 "authorised backend + Flutter foundation"     "step3_base"
expect_green 2 "tenancy tables (memberships, outlets, roles)" "step3_base; printf '<?php\nSchema::create(\"memberships\", function(\$t){});\nSchema::create(\"outlets\", function(\$t){});\nSchema::create(\"roles\", function(\$t){});\n' > backend/app/Tenancy.php"
expect_green 3 "Eloquent orderBy() must not trip 'order'"    "step3_base; printf '<?php\nclass Tenant extends Model { public function s(){ return \$this->q()->orderBy(\"name\")->get(); } }\n' > backend/app/Tenant.php"
expect_green 4 "prose mentioning payment/POS in docs"        "step3_base; printf '# Notes\nPOS and payment are owned by Step 5 and are NOT IMPLEMENTED.\n' > docs/runtime/NOTE.md"
expect_green 5 "documented fictional phone in docs"          "step3_base; printf 'Contoh: 081234567890\n' > docs/runtime/EXAMPLE.md"

echo
echo "-- DEC-0030: authorised Step 4 master data must PASS --"
# The four labels DEC-0030 permits, exercised through the same structural signals
# the guard uses for rejection: migration filename, Schema::create, Eloquent model
# class name, and module directory.
expect_green 6 "customers table + Customer model (FR-021…FR-030)" \
  "step3_base; printf '<?php\nSchema::create(\"customers\", function(\$t){});\nclass Customer extends Model {}\n' > backend/app/Cust.php"
expect_green 7 "price_lists and service_catalog tables (FR-031…FR-040)" \
  "step3_base; printf '<?php\nSchema::create(\"price_lists\", function(\$t){});\nSchema::create(\"service_catalog\", function(\$t){});\n' > backend/app/Cat.php"
expect_green 8 "printer configuration table (FR-045)" \
  "step3_base; printf '<?php\nSchema::create(\"printers\", function(\$t){});\n' > backend/app/Prn.php"
expect_green 9 "Step 4 migration filenames" \
  "step3_base; touch backend/database/migrations/2026_07_21_000100_create_customers_table.php backend/database/migrations/2026_07_21_000200_create_price_lists_table.php"
expect_green 10 "Step 4 module directory" \
  "step3_base; mkdir -p backend/app/Modules/customers; printf '<?php\nclass X {}\n' > backend/app/Modules/customers/X.php"

# ---------------------------------------------------------------------------
AFTER="$(fingerprint)"
echo
echo "========================================================================"
if [ "${BEFORE}" != "${AFTER}" ]; then
  echo "$(red 'WORKING TREE WAS MODIFIED') — harness is unsafe, refusing to report success"
  echo "  before: ${BEFORE}"
  echo "  after : ${AFTER}"
  exit 1
fi
echo "working tree byte-identical before and after: ${BEFORE:0:16}…"
printf 'RESULT: %d/%d expectations met, %d failed\n' "${PASS}" "${TOTAL}" "${FAIL}"
echo "========================================================================"
[ "${FAIL}" -eq 0 ] || exit 1
