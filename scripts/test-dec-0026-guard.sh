#!/usr/bin/env bash
#
# Adversarial suite for the DEC-0026 phase-aware Flutter scaffolding
# authorisation in .claude/hooks/guard-destructive-operations.sh.
#
# WHY THE PREVIOUS REVISION WAS WRONG
# -----------------------------------
# It ran canonical-state cases inside a temporary directory copy. The guard
# requires the canonical repository PATH, so every one of those cases denied on
# "not the canonical repository path" before reaching the condition under test —
# 24 of 29 results were invalid, and the three allowed controls failed because
# docs/STATUS.md genuinely did not declare Step 3 IN PROGRESS at the time.
#
# Canonical-state cases now mutate the REAL file in place, invoke the guard, and
# restore immediately. Every case asserts the denial reason it intended, so a
# denial produced by an earlier unrelated condition is reported as an INVALID
# RESULT rather than counted as a security pass. The whole run is bracketed by a
# byte-identical fingerprint check, and an EXIT trap restores every mutated file
# even if the suite aborts.
#
# The guard is only ever INVOKED. No `flutter create` runs here.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

# The guard under test. Defaults to the canonical hook, but may be pointed at a
# DISPOSABLE PATCHED COPY via DEC0026_GUARD_UNDER_TEST so the amendment can be
# validated end-to-end before the owner ever applies it to the real file. The
# copy still resolves the canonical repository from git, so every canonical-state
# check exercises the real repository state rather than a stub.
GUARD="${DEC0026_GUARD_UNDER_TEST:-.claude/hooks/guard-destructive-operations.sh}"
APPROVED_CMD="flutter create --platforms=android apps/customer_android"

PASS=0; FAIL=0; TOTAL=0
green() { printf '\033[32m%s\033[0m' "$*"; }
red()   { printf '\033[31m%s\033[0m' "$*"; }

repo_payload() {
  { git ls-files -z; git ls-files -z --others --exclude-standard; } 2>/dev/null | sort -z -u
}
fingerprint() { repo_payload | xargs -0 sha256sum 2>/dev/null | sha256sum | awk '{print $1}'; }

if ! grep -q "_step3_flutter_scaffold_authorized" "${GUARD}"; then
  echo "SKIP: the DEC-0026 amendment is not applied to ${GUARD}."
  echo "      Run: bash scripts/owner/apply-dec-0026-guard-amendment.sh"
  exit 78
fi

BEFORE="$(fingerprint)"

# --- restoration safety net --------------------------------------------------
BACKUP_DIR="$(mktemp -d)"
declare -a MUTATED=()
restore_all() {
  local f
  for f in "${MUTATED[@]:-}"; do
    [ -n "${f}" ] || continue
    [ -f "${BACKUP_DIR}/$(basename "${f}").bak" ] || continue
    cp "${BACKUP_DIR}/$(basename "${f}").bak" "${f}"
  done
  rm -rf "${BACKUP_DIR}"
}
trap restore_all EXIT

stash_file() {
  local f="$1"
  cp "${f}" "${BACKUP_DIR}/$(basename "${f}").bak"
  MUTATED+=("${f}")
}
unstash_file() {
  local f="$1"
  cp "${BACKUP_DIR}/$(basename "${f}").bak" "${f}"
}

# allow <id> <desc> <command>
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

# deny <label> <id> <desc> <expected-reason> <command>
deny() {
  local label="$1" id="$2" desc="$3" reason="$4" cmd="$5"
  TOTAL=$((TOTAL + 1))
  local out rc
  out="$(bash "${GUARD}" "${cmd}" 2>&1)"; rc=$?
  if [ "${rc}" -eq 0 ]; then
    printf '  %s  %s%-2s %s — guard ALLOWED it\n' "$(red FAIL)" "${label}" "${id}" "${desc}"
    FAIL=$((FAIL + 1)); return
  fi
  if printf '%s' "${out}" | grep -qF -- "${reason}"; then
    printf '  %s  %s%-2s %s\n' "$(green 'ok  ')" "${label}" "${id}" "${desc}"; PASS=$((PASS + 1))
  else
    printf '  %s  %s%-2s %s — denied for the WRONG reason (INVALID RESULT)\n' "$(red FAIL)" "${label}" "${id}" "${desc}"
    printf '        expected: %s\n' "${reason}"
    printf '%s\n' "${out}" | grep -m1 "BLOCKED" | sed 's/^/        actual:   /'
    FAIL=$((FAIL + 1))
  fi
}

# state_deny <id> <desc> <file> <mutation-cmd> <expected-reason>
# Mutates a real canonical file, asserts the mutation actually landed, invokes
# the guard, then restores immediately.
state_deny() {
  local id="$1" desc="$2" file="$3" mutation="$4" reason="$5"
  TOTAL=$((TOTAL + 1))
  stash_file "${file}"
  if ! eval "${mutation}" >/dev/null 2>&1; then
    printf '  %s  S%-2s %s — SETUP FAILED (invalid result)\n' "$(red FAIL)" "${id}" "${desc}"
    unstash_file "${file}"; FAIL=$((FAIL + 1)); return
  fi
  if cmp -s "${file}" "${BACKUP_DIR}/$(basename "${file}").bak"; then
    printf '  %s  S%-2s %s — MUTATION DID NOT LAND (invalid result)\n' "$(red FAIL)" "${id}" "${desc}"
    unstash_file "${file}"; FAIL=$((FAIL + 1)); return
  fi
  local out rc
  out="$(bash "${GUARD}" "${APPROVED_CMD}" 2>&1)"; rc=$?
  unstash_file "${file}"
  if [ "${rc}" -eq 0 ]; then
    printf '  %s  S%-2s %s — guard ALLOWED it\n' "$(red FAIL)" "${id}" "${desc}"
    FAIL=$((FAIL + 1)); return
  fi
  if printf '%s' "${out}" | grep -qF -- "${reason}"; then
    printf '  %s  S%-2s %s\n' "$(green 'ok  ')" "${id}" "${desc}"; PASS=$((PASS + 1))
  else
    printf '  %s  S%-2s %s — denied for the WRONG reason (INVALID RESULT)\n' "$(red FAIL)" "${id}" "${desc}"
    printf '        expected: %s\n' "${reason}"
    printf '%s\n' "${out}" | grep -m1 "BLOCKED" | sed 's/^/        actual:   /'
    FAIL=$((FAIL + 1))
  fi
}

STATUS_MD="docs/STATUS.md"
DEC24="$(ls docs/decisions/DEC-0024-*.md | head -1)"
DEC26="$(ls docs/decisions/DEC-0026-*.md | head -1)"

echo "========================================================================"
echo "ADVERSARIAL TEST — DEC-0026 Flutter scaffolding authorisation"
echo "========================================================================"
echo
echo "-- allowed controls (prove authorisation can succeed at all) --"
allow 1 "customer_android + android" "flutter create --platforms=android apps/customer_android"
allow 2 "ops_android + android"      "flutter create --platforms=android apps/ops_android"
allow 3 "admin_web + web"            "flutter create --platforms=web apps/admin_web"

echo
echo "-- platform restrictions --"
deny D 1 "customer requesting web"      "!= approved 'android'"  "flutter create --platforms=web apps/customer_android"
deny D 2 "customer requesting ios"      "!= approved 'android'"  "flutter create --platforms=ios apps/customer_android"
deny D 3 "ops requesting web"           "!= approved 'android'"  "flutter create --platforms=web apps/ops_android"
deny D 4 "ops requesting ios"           "!= approved 'android'"  "flutter create --platforms=ios apps/ops_android"
deny D 5 "admin_web requesting android" "!= approved 'web'"      "flutter create --platforms=android apps/admin_web"
deny D 6 "admin_web requesting desktop" "!= approved 'web'"      "flutter create --platforms=linux,macos,windows apps/admin_web"
deny D 7 "multiple unrestricted"        "!= approved"            "flutter create --platforms=android,ios,web apps/customer_android"
deny D 8 "missing --platforms"          "--platforms is missing" "flutter create apps/customer_android"

echo
echo "-- target restrictions --"
deny D 9  "unapproved fourth app"  "not one of the three approved" "flutter create --platforms=android apps/fourth_app"
deny D 10 "package directory"      "not one of the three approved" "flutter create --platforms=android packages/core"
deny D 11 "repository root"        "not one of the three approved" "flutter create --platforms=android ."
deny D 12 "path traversal"         "path traversal"                "flutter create --platforms=android apps/../../elsewhere"
deny D 13 "absolute target"        "repository-relative"           "flutter create --platforms=android /tmp/elsewhere"
deny D 14 "no target"              "no target path given"          "flutter create --platforms=android"

echo
echo "-- option restrictions --"
deny D 15 "overwrite"    "destructive or overwrite option"    "flutter create --overwrite --platforms=android apps/customer_android"
deny D 16 "force"        "destructive or overwrite option"    "flutter create --force --platforms=android apps/ops_android"
deny D 17 "deploy"       "publishing, deployment, or signing"  "flutter create --platforms=web --deploy apps/admin_web"
deny D 18 "keystore"     "publishing, deployment, or signing"  "flutter create --platforms=android --keystore=x apps/ops_android"

echo
echo "-- identifier restrictions --"
deny D 19 "com.example org" "example or placeholder identifier" "flutter create --platforms=android --org com.example apps/customer_android"
deny D 20 "my_app name"     "example or placeholder identifier" "flutter create --platforms=android --project-name my_app apps/ops_android"

echo
echo "-- command family --"
deny D 21 "dart create still blocked" "dart create" "dart create apps/customer_android"

echo
echo "-- canonical-state restrictions (real files, mutated then restored) --"
state_deny 1 "Step 3 not IN_PROGRESS" "${STATUS_MD}" \
  "sed -i 's/^STEP_03_STATUS=IN_PROGRESS$/STEP_03_STATUS=PLANNED/' ${STATUS_MD}" \
  "STEP_03_STATUS is 'PLANNED', expected IN_PROGRESS"

state_deny 2 "a Step 4-14 entry advanced" "${STATUS_MD}" \
  "sed -i 's/^STEP_07_STATUS=PLANNED$/STEP_07_STATUS=IN_PROGRESS/' ${STATUS_MD}" \
  "STEP_07_STATUS is 'IN_PROGRESS', expected PLANNED"

state_deny 3 "duplicate canonical state key" "${STATUS_MD}" \
  "sed -i '0,/^STEP_03_STATUS=IN_PROGRESS$/s//STEP_03_STATUS=IN_PROGRESS\nSTEP_03_STATUS=IN_PROGRESS/' ${STATUS_MD}" \
  "duplicate canonical state key"

state_deny 4 "duplicate canonical state block" "${STATUS_MD}" \
  "printf '\n<!-- CANONICAL_STEP_STATE_BEGIN -->\nSTEP_03_STATUS=IN_PROGRESS\n<!-- CANONICAL_STEP_STATE_END -->\n' >> ${STATUS_MD}" \
  "must appear exactly once"

state_deny 5 "canonical state block removed" "${STATUS_MD}" \
  "sed -i '/^<!-- CANONICAL_STEP_STATE_BEGIN -->$/d' ${STATUS_MD}" \
  "must appear exactly once"

state_deny 6 "a Step 4-14 key missing" "${STATUS_MD}" \
  "sed -i '/^STEP_11_STATUS=PLANNED$/d' ${STATUS_MD}" \
  "STEP_11_STATUS is missing"

state_deny 7 "DEC-0024 not ACCEPTED" "${DEC24}" \
  "sed -i 's/^\*\*Status:\*\* ACCEPTED$/**Status:** PROPOSED/' '${DEC24}'" \
  "DEC-0024 status is 'PROPOSED'"

state_deny 8 "DEC-0026 not ACCEPTED" "${DEC26}" \
  "sed -i 's/^\*\*Status:\*\* ACCEPTED$/**Status:** SUPERSEDED/' '${DEC26}'" \
  "DEC-0026 status is 'SUPERSEDED'"

state_deny 9 "DEC-0026 duplicate status field" "${DEC26}" \
  "sed -i '0,/^\*\*Status:\*\* ACCEPTED$/s//**Status:** ACCEPTED\n**Status:** ACCEPTED/' '${DEC26}'" \
  "formal status fields, expected exactly 1"

state_deny 10 "Master Source version wrong" "docs/MASTER_SOURCE.md" \
  "sed -i 's/\*\*Document version: 1.4.0\*\*/**Document version: 1.3.0**/' docs/MASTER_SOURCE.md" \
  "Master Source version is '1.3.0'"

state_deny 11 "Master Source checksum invalid" "docs/MASTER_SOURCE.md" \
  "printf '\n<!-- drift -->\n' >> docs/MASTER_SOURCE.md" \
  "checksum does not validate"

echo
echo "-- execution context --"
TOTAL=$((TOTAL + 1))
# GUARD may be relative (canonical hook) or absolute (disposable copy under test),
# so resolve it once rather than concatenating REPO_ROOT onto an absolute path.
GUARD_ABS="$(cd "$(dirname "${GUARD}")" && pwd)/$(basename "${GUARD}")"
out="$(cd /tmp && bash "${GUARD_ABS}" "${APPROVED_CMD}" 2>&1)"; rc=$?
# Policy: execution must occur in the canonical repository context. Invoked from
# /tmp the guard resolves a different (or no) git toplevel, so it MUST deny. The
# expected reason is the canonical-path/repository-context reason, not an allow.
if [ "${rc}" -ne 0 ] && printf '%s' "${out}" | grep -qE "not the canonical repository path|not inside a git repository"; then
  printf '  %s  X1  invoked outside the canonical repository is denied\n' "$(green 'ok  ')"; PASS=$((PASS + 1))
else
  printf '  %s  X1  invoked outside the canonical repository — exit=%s\n' "$(red FAIL)" "${rc}"
  printf '%s\n' "${out}" | head -2 | sed 's/^/        /'
  FAIL=$((FAIL + 1))
fi

echo
echo "-- guard integrity --"
TOTAL=$((TOTAL + 1))
if bash "${GUARD}" --self-test 2>&1 | grep -q "SELF-TEST PASS"; then
  printf '  %s  G1  guard self-test still passes (guard remained enabled)\n' "$(green 'ok  ')"; PASS=$((PASS + 1))
else
  printf '  %s  G1  guard self-test FAILS\n' "$(red FAIL)"; FAIL=$((FAIL + 1))
fi

TOTAL=$((TOTAL + 1))
if grep -q "^_step3_flutter_scaffold_authorized()" "${GUARD}"; then
  printf '  %s  G2  authorisation function is defined (undefined-function regression)\n' "$(green 'ok  ')"; PASS=$((PASS + 1))
else
  printf '  %s  G2  authorisation function is NOT defined\n' "$(red FAIL)"; FAIL=$((FAIL + 1))
fi

# --- restoration ------------------------------------------------------------
restore_all
trap - EXIT
AFTER="$(fingerprint)"

echo
echo "========================================================================"
if [ "${BEFORE}" != "${AFTER}" ]; then
  echo "$(red 'WORKING TREE WAS NOT RESTORED') — refusing to report success"
  echo "  before: ${BEFORE}"
  echo "  after : ${AFTER}"
  exit 1
fi
echo "working tree byte-identical before and after: ${BEFORE:0:16}…"
printf 'RESULT: %d/%d expectations met, %d failed\n' "${PASS}" "${TOTAL}" "${FAIL}"
echo "========================================================================"
[ "${FAIL}" -eq 0 ] || exit 1
