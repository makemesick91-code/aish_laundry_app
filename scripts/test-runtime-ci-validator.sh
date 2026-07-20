#!/usr/bin/env bash
#
# Adversarial suite for scripts/validate-runtime-ci.py.
#
# Rule 33 item 15: a validator only ever run against correct input is an UNTESTED
# validator. This proves it turns RED for each violation class it claims to
# catch, and GREEN for the real workflows.
#
# Mutations are applied to a throwaway copy; the working tree is verified
# byte-identical before and after. A mutation whose setup fails is reported as an
# INVALID RESULT, never counted as caught.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

PASS=0; FAIL=0; TOTAL=0
green() { printf '\033[32m%s\033[0m' "$*"; }
red()   { printf '\033[31m%s\033[0m' "$*"; }

repo_payload() {
  { git ls-files -z; git ls-files -z --others --exclude-standard; } 2>/dev/null | sort -z -u
}
fingerprint() { repo_payload | xargs -0 sha256sum 2>/dev/null | sha256sum | awk '{print $1}'; }
BEFORE="$(fingerprint)"

WORK="$(mktemp -d)"
trap 'rm -rf "${WORK}"' EXIT
BASE="${WORK}/base"; mkdir -p "${BASE}"
repo_payload | tar -cf - --null -T - 2>/dev/null | ( cd "${BASE}" && tar -xf - )

# expect_red <id> <desc> <mutation>
expect_red() {
  local id="$1" desc="$2" mutation="$3"
  TOTAL=$((TOTAL + 1))
  local sb="${WORK}/m${id}"
  rm -rf "${sb}"; cp -a "${BASE}" "${sb}"
  local before after
  before="$(cd "${sb}" && sha256sum .github/workflows/*.yml | sha256sum)"
  if ! ( cd "${sb}" && eval "${mutation}" ) >/dev/null 2>&1; then
    printf '  %s  M%-2s %s — SETUP FAILED (invalid result)\n' "$(red FAIL)" "${id}" "${desc}"
    FAIL=$((FAIL + 1)); return
  fi
  after="$(cd "${sb}" && sha256sum .github/workflows/*.yml | sha256sum)"
  if [ "${before}" = "${after}" ]; then
    printf '  %s  M%-2s %s — MUTATION DID NOT LAND (invalid result)\n' "$(red FAIL)" "${id}" "${desc}"
    FAIL=$((FAIL + 1)); return
  fi
  if ( cd "${sb}" && python3 scripts/validate-runtime-ci.py ) >/dev/null 2>&1; then
    printf '  %s  M%-2s %s — validator stayed GREEN\n' "$(red FAIL)" "${id}" "${desc}"
    FAIL=$((FAIL + 1))
  else
    printf '  %s  M%-2s %s\n' "$(green 'ok  ')" "${id}" "${desc}"
    PASS=$((PASS + 1))
  fi
}

WF=".github/workflows"

echo "========================================================================"
echo "ADVERSARIAL TEST — scripts/validate-runtime-ci.py"
echo "========================================================================"
echo
echo "-- context identity --"
expect_red 1  "runtime-foundation context renamed" \
  "sed -i 's/^    name: runtime-foundation$/    name: runtime-build/' ${WF}/runtime-foundation.yml"
expect_red 2  "tenant-isolation context renamed" \
  "sed -i 's/^    name: tenant-isolation$/    name: isolation/' ${WF}/tenant-isolation.yml"
expect_red 3  "authentication-rbac context renamed" \
  "sed -i 's/^    name: authentication-rbac$/    name: auth/' ${WF}/authentication-rbac.yml"
expect_red 4  "duplicate context published twice" \
  "sed -i 's/^    name: tenant-isolation\$/    name: runtime-foundation/' ${WF}/tenant-isolation.yml"

echo
echo "-- real work removed --"
expect_red 5  "Customer Android build removed" \
  "sed -i '/cd apps\/customer_android/,+1d' ${WF}/runtime-foundation.yml"
expect_red 6  "Ops Android build removed" \
  "sed -i '/cd apps\/ops_android/,+1d' ${WF}/runtime-foundation.yml"
expect_red 7  "Admin Web build removed" \
  "sed -i '/cd apps\/admin_web/,+1d' ${WF}/runtime-foundation.yml"
expect_red 8  "flutter analyze removed" \
  "sed -i '/flutter analyze/d' ${WF}/runtime-foundation.yml"
expect_red 9  "design-token drift check removed" \
  "sed -i '/generate-design-tokens.py --check/d' ${WF}/runtime-foundation.yml"
expect_red 10 "Flutter checksum verification removed" \
  "sed -i '/FLUTTER_SHA256/d' ${WF}/runtime-foundation.yml"

echo
echo "-- authoritative services --"
expect_red 11 "PostgreSQL service removed" \
  "sed -i '/image: \"postgres:/d' ${WF}/tenant-isolation.yml"
expect_red 12 "Redis service removed" \
  "sed -i '/image: \"redis:/d' ${WF}/tenant-isolation.yml"
expect_red 13 "SQLite substituted for PostgreSQL" \
  "sed -i 's/^      DB_CONNECTION: pgsql\$/      DB_CONNECTION: sqlite/' ${WF}/tenant-isolation.yml"
expect_red 14 "isolation matrix no longer run" \
  "sed -i \"/StructuralIsolation|TenantIsolation/d\" ${WF}/tenant-isolation.yml"
expect_red 15 "Redis partitioning no longer run" \
  "sed -i '/RedisTenantPartitioning/d' ${WF}/tenant-isolation.yml"

echo
echo "-- security suites --"
expect_red 16 "authentication tests disabled" \
  "sed -i \"/AuthenticationTest|PasswordResetTest|SessionManagementTest/d\" ${WF}/authentication-rbac.yml"
expect_red 17 "RBAC matrix disabled" \
  "sed -i \"/Rbac|AuthorizationRegistry/d\" ${WF}/authentication-rbac.yml"
expect_red 18 "adversarial matrix disabled" \
  "sed -i '/AuthenticationAdversarialMatrix/d' ${WF}/authentication-rbac.yml"
expect_red 19 "log-redaction tests disabled" \
  "sed -i '/LogRedaction/d' ${WF}/authentication-rbac.yml"

echo
echo "-- supply chain and hygiene --"
expect_red 20 "mutable action tag instead of a SHA" \
  "sed -i 's#actions/checkout@11bd71901bbe5b1630ceea73d27597364c9af683#actions/checkout@v4#' ${WF}/runtime-foundation.yml"
expect_red 21 "pull_request_target introduced" \
  "sed -i 's/^on:\$/on:\\n  pull_request_target:/' ${WF}/tenant-isolation.yml"
expect_red 22 "continue-on-error escape hatch" \
  "sed -i 's/^      - name: Flutter analyze\$/      - name: Flutter analyze\\n        continue-on-error: true/' ${WF}/runtime-foundation.yml"
expect_red 23 "timeout removed" \
  "sed -i '/timeout-minutes:/d' ${WF}/authentication-rbac.yml"
expect_red 24 "concurrency cancellation removed" \
  "sed -i '/cancel-in-progress: true/d' ${WF}/tenant-isolation.yml"
expect_red 25 "excessive write permissions" \
  "sed -i 's/^      contents: read\$/      contents: write/' ${WF}/authentication-rbac.yml"
expect_red 26 "production secret referenced" \
  "sed -i 's/^      DB_PASSWORD: CHANGEME_ci_only_not_secret\$/      DB_PASSWORD: \${{ secrets.PROD_DB_PASSWORD }}/' ${WF}/tenant-isolation.yml"
expect_red 27 "deployment command introduced" \
  "sed -i 's#^          composer install --no-interaction --prefer-dist --no-progress\$#          kubectl apply -f k8s/#' ${WF}/tenant-isolation.yml"

echo
echo "-- legitimate workflows must PASS --"
TOTAL=$((TOTAL + 1))
if ( cd "${BASE}" && python3 scripts/validate-runtime-ci.py ) >/dev/null 2>&1; then
  printf '  %s  G1  unmutated workflows validate clean\n' "$(green 'ok  ')"; PASS=$((PASS + 1))
else
  printf '  %s  G1  unmutated workflows FAILED validation\n' "$(red FAIL)"; FAIL=$((FAIL + 1))
fi

AFTER="$(fingerprint)"
echo
echo "========================================================================"
if [ "${BEFORE}" != "${AFTER}" ]; then
  echo "$(red 'WORKING TREE WAS MODIFIED') — refusing to report success"
  exit 1
fi
echo "working tree byte-identical before and after: ${BEFORE:0:16}…"
printf 'RESULT: %d/%d expectations met, %d failed\n' "${PASS}" "${TOTAL}" "${FAIL}"
echo "========================================================================"
[ "${FAIL}" -eq 0 ] || exit 1
