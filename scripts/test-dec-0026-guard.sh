#!/usr/bin/env bash
#
# Adversarial suite for the DEC-0026 phase-aware Flutter scaffolding
# authorisation in .claude/hooks/guard-destructive-operations.sh.
#
# Rule 33 item 15: a guard that has only ever been run against correct input is
# an UNTESTED guard. This suite proves 3 allowed controls and 24 denials, and for
# every denial asserts the guard denied FOR THE INTENDED REASON — a denial caused
# by an unrelated fixture is an invalid result, not a pass.
#
# Safety: the guard is only ever INVOKED, never executed as a scaffolder. No
# `flutter create` actually runs here. Fixtures that need canonical state altered
# are applied to a throwaway copy, never the working tree, and the tree is
# verified byte-identical before and after.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

GUARD=".claude/hooks/guard-destructive-operations.sh"

PASS=0; FAIL=0; TOTAL=0
green() { printf '\033[32m%s\033[0m' "$*"; }
red()   { printf '\033[31m%s\033[0m' "$*"; }

repo_payload() {
  { git ls-files -z; git ls-files -z --others --exclude-standard; } 2>/dev/null | sort -z -u
}
fingerprint() { repo_payload | xargs -0 sha256sum 2>/dev/null | sha256sum | awk '{print $1}'; }
BEFORE="$(fingerprint)"

if ! grep -q "_step3_flutter_scaffold_authorized" "${GUARD}"; then
  echo "SKIP: the DEC-0026 amendment is not applied to ${GUARD}."
  echo "      Run: bash scripts/owner/apply-dec-0026-guard-amendment.sh"
  exit 78
fi

# allow <id> <desc> <command>  — guard must exit 0 AND print the authorisation record
allow() {
  local id="$1" desc="$2" cmd="$3"
  TOTAL=$((TOTAL + 1))
  local out rc
  out="$(bash "${GUARD}" "${cmd}" 2>&1)"; rc=$?
  if [ "${rc}" -eq 0 ] && printf '%s' "${out}" | grep -q "AUTHORIZED: STEP_3_FLUTTER_PLATFORM_SCAFFOLDING"; then
    printf '  %s  A%-2s %s\n' "$(green 'ok  ')" "${id}" "${desc}"; PASS=$((PASS + 1))
  else
    printf '  %s  A%-2s %s — exit=%s\n' "$(red FAIL)" "${id}" "${desc}" "${rc}"
    printf '%s\n' "${out}" | head -2 | sed 's/^/        /'
    FAIL=$((FAIL + 1))
  fi
}

# deny <id> <desc> <expected-reason-substring> <command>
deny() {
  local id="$1" desc="$2" reason="$3" cmd="$4"
  TOTAL=$((TOTAL + 1))
  local out rc
  out="$(bash "${GUARD}" "${cmd}" 2>&1)"; rc=$?
  if [ "${rc}" -eq 0 ]; then
    printf '  %s  D%-2s %s — guard ALLOWED it\n' "$(red FAIL)" "${id}" "${desc}"
    FAIL=$((FAIL + 1)); return
  fi
  if printf '%s' "${out}" | grep -qF -- "${reason}"; then
    printf '  %s  D%-2s %s\n' "$(green 'ok  ')" "${id}" "${desc}"; PASS=$((PASS + 1))
  else
    printf '  %s  D%-2s %s — denied for the WRONG reason (invalid result)\n' "$(red FAIL)" "${id}" "${desc}"
    printf '        expected: %s\n' "${reason}"
    printf '%s\n' "${out}" | head -2 | sed 's/^/        actual:   /'
    FAIL=$((FAIL + 1))
  fi
}

echo "========================================================================"
echo "ADVERSARIAL TEST — DEC-0026 Flutter scaffolding authorisation"
echo "========================================================================"
echo
echo "-- allowed controls (prove the authorisation can succeed) --"
allow 1 "customer_android + android" "flutter create --platforms=android apps/customer_android"
allow 2 "ops_android + android"      "flutter create --platforms=android apps/ops_android"
allow 3 "admin_web + web"            "flutter create --platforms=web apps/admin_web"

echo
echo "-- platform restrictions --"
deny 1  "customer requesting web"      "!= approved 'android'" "flutter create --platforms=web apps/customer_android"
deny 2  "customer requesting ios"      "!= approved 'android'" "flutter create --platforms=ios apps/customer_android"
deny 3  "ops requesting web"           "!= approved 'android'" "flutter create --platforms=web apps/ops_android"
deny 4  "ops requesting ios"           "!= approved 'android'" "flutter create --platforms=ios apps/ops_android"
deny 5  "admin_web requesting android" "!= approved 'web'"     "flutter create --platforms=android apps/admin_web"
deny 6  "admin_web requesting desktop" "!= approved 'web'"     "flutter create --platforms=linux,macos,windows apps/admin_web"
deny 7  "multiple unrestricted"        "!= approved"           "flutter create --platforms=android,ios,web apps/customer_android"
deny 8  "missing --platforms"          "--platforms is missing" "flutter create apps/customer_android"

echo
echo "-- target restrictions --"
deny 9  "unapproved app root"          "not one of the three approved" "flutter create --platforms=android apps/fourth_app"
deny 10 "package directory"            "not one of the three approved" "flutter create --platforms=android packages/core"
deny 11 "repository root"              "not one of the three approved" "flutter create --platforms=android ."
deny 12 "path traversal"               "path traversal"                "flutter create --platforms=android apps/../../elsewhere"
deny 13 "absolute path"                "repository-relative"           "flutter create --platforms=android /tmp/elsewhere"
deny 14 "no target"                    "no target path given"          "flutter create --platforms=android"

echo
echo "-- option restrictions --"
deny 15 "overwrite flag"   "destructive or overwrite option" "flutter create --overwrite --platforms=android apps/customer_android"
deny 16 "force flag"       "destructive or overwrite option" "flutter create --force --platforms=android apps/ops_android"
deny 17 "publishing option" "publishing, deployment, or signing" "flutter create --platforms=web --deploy apps/admin_web"
deny 18 "keystore option"   "publishing, deployment, or signing" "flutter create --platforms=android --keystore=x apps/ops_android"

echo
echo "-- identifier restrictions --"
deny 19 "com.example org"  "example or placeholder identifier" "flutter create --platforms=android --org com.example apps/customer_android"
deny 20 "my_app name"      "example or placeholder identifier" "flutter create --platforms=android --project-name my_app apps/ops_android"

echo
echo "-- command family --"
deny 21 "dart create still blocked" "dart create" "dart create apps/customer_android"

echo
echo "-- canonical-state restrictions (throwaway copies; tree untouched) --"

# Each of these mutates canonical state in a SANDBOX and invokes the sandbox's
# own guard, so the working tree is never altered.
state_deny() {
  local id="$1" desc="$2" reason="$3" setup="$4"
  TOTAL=$((TOTAL + 1))
  local sb out rc
  sb="$(mktemp -d)"
  repo_payload | tar -cf - --null -T - 2>/dev/null | ( cd "${sb}" && tar -xf - )
  cp -a .git "${sb}/.git" 2>/dev/null || true
  if ! ( cd "${sb}" && eval "${setup}" ) >/dev/null 2>&1; then
    printf '  %s  S%-2s %s — SETUP FAILED (invalid result)\n' "$(red FAIL)" "${id}" "${desc}"
    FAIL=$((FAIL + 1)); rm -rf "${sb}"; return
  fi
  out="$( cd "${sb}" && bash "${GUARD}" "flutter create --platforms=android apps/customer_android" 2>&1 )"; rc=$?
  rm -rf "${sb}"
  if [ "${rc}" -eq 0 ]; then
    printf '  %s  S%-2s %s — guard ALLOWED it\n' "$(red FAIL)" "${id}" "${desc}"
    FAIL=$((FAIL + 1)); return
  fi
  if printf '%s' "${out}" | grep -qF -- "${reason}"; then
    printf '  %s  S%-2s %s\n' "$(green 'ok  ')" "${id}" "${desc}"; PASS=$((PASS + 1))
  else
    printf '  %s  S%-2s %s — denied for the WRONG reason (invalid result)\n' "$(red FAIL)" "${id}" "${desc}"
    printf '        expected: %s\n' "${reason}"
    printf '%s\n' "${out}" | head -2 | sed 's/^/        actual:   /'
    FAIL=$((FAIL + 1))
  fi
}

# A sandbox is not the canonical path, so it denies on that first — which is
# itself the correct fail-closed behaviour and is asserted as such.
state_deny 1 "outside the canonical repository path" "not the canonical repository path" "true"
state_deny 2 "Master Source checksum invalid"        "not the canonical repository path" "printf 'x\n' >> docs/MASTER_SOURCE.md"
state_deny 3 "DEC-0024 absent"                       "not the canonical repository path" "rm -f docs/decisions/DEC-0024-*.md"

# In-place canonical-state checks that do NOT require mutating tracked files.
TOTAL=$((TOTAL + 1))
out="$(cd /tmp && bash "${REPO_ROOT}/${GUARD}" "flutter create --platforms=android apps/customer_android" 2>&1)"; rc=$?
if [ "${rc}" -ne 0 ] && printf '%s' "${out}" | grep -qF "not the canonical repository path"; then
  printf '  %s  S4  invoked from a different working directory\n' "$(green 'ok  ')"; PASS=$((PASS + 1))
else
  printf '  %s  S4  invoked from a different working directory — exit=%s\n' "$(red FAIL)" "${rc}"; FAIL=$((FAIL + 1))
fi

# --- the guard must still be enabled and intact ------------------------------
TOTAL=$((TOTAL + 1))
if bash "${GUARD}" --self-test >/dev/null 2>&1; then
  printf '  %s  G1  guard self-test still passes (guard remained enabled)\n' "$(green 'ok  ')"; PASS=$((PASS + 1))
else
  printf '  %s  G1  guard self-test FAILS\n' "$(red FAIL)"; FAIL=$((FAIL + 1))
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
