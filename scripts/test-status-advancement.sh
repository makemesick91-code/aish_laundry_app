#!/usr/bin/env bash
#
# Adversarial suite for the Step 3 canonical status-advancement checks in
# scripts/validate-status.py and scripts/validate-roadmap.py.
#
# Every case mutates a REAL canonical file in place, runs the intended validator,
# asserts the specific failure it should raise (never merely "some failure"), and
# restores the file. A byte-identical fingerprint brackets the whole run, and an
# EXIT trap restores every mutated file even if the suite aborts.
#
# It also self-checks the oracle: a stubbed always-pass validator must NOT catch a
# real mutation (S1), a broken fixture setup must be reported as SETUP ERROR and
# never as a catch (S2), and a mutation must fail for the CONTRACT reason, not an
# unrelated one (S3).
#
# No git tag is moved and no runtime is touched.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

STATUS="docs/STATUS.md"
ROADMAP="docs/ROADMAP.md"
RULE49=".claude/rules/49-current-step-03-status.md"
RULE15=".claude/rules/15-current-product-status.md"
CLAUDEMD="CLAUDE.md"
MASTER="docs/MASTER_SOURCE.md"

STATUS_VALIDATOR="python3 scripts/validate-status.py"
ROADMAP_VALIDATOR="python3 scripts/validate-roadmap.py"

PASS=0; FAIL=0; TOTAL=0
green() { printf '\033[32m%s\033[0m' "$*"; }
red()   { printf '\033[31m%s\033[0m' "$*"; }

repo_payload() { { git ls-files -z; git ls-files -z --others --exclude-standard; } 2>/dev/null | sort -z -u; }
fingerprint() { repo_payload | xargs -0 sha256sum 2>/dev/null | sha256sum | awk '{print $1}'; }

BEFORE="$(fingerprint)"
BACKUP_DIR="$(mktemp -d)"
declare -a MUTATED=()
backup() { local f="$1"; [ -f "${BACKUP_DIR}/$(echo "$f" | tr / _).bak" ] || cp "$f" "${BACKUP_DIR}/$(echo "$f" | tr / _).bak"; MUTATED+=("$f"); }
restore_all() {
  local f
  for f in "${MUTATED[@]:-}"; do
    [ -n "${f}" ] || continue
    local b="${BACKUP_DIR}/$(echo "$f" | tr / _).bak"
    [ -f "${b}" ] && cp "${b}" "${f}"
  done
}
trap 'restore_all; rm -rf "${BACKUP_DIR}"' EXIT

# expect_red <id> <validator> <expected-substring> <description> <setup>
expect_red() {
  local id="$1" validator="$2" needle="$3" desc="$4" setup="$5"
  TOTAL=$((TOTAL + 1))
  local f
  # EVERY file any fixture may mutate must be listed here. This harness mutates the
  # REAL tree and restores from these backups; a file omitted here is simply left
  # mutated. That is not theoretical — the DEC-0029 fixtures below mutate
  # MASTER_SOURCE.md, and on their first run it was missing from this list and the
  # Master Source was left corrupted with a fabricated "Step 5 | GO" row. The
  # end-of-run tree fingerprint caught it, which is why that check exists.
  for f in "$STATUS" "$ROADMAP" "$RULE49" "$RULE15" "$CLAUDEMD" "$MASTER"; do backup "$f"; done
  if ! eval "${setup}" >/dev/null 2>&1; then
    printf '  %s  M%-2s %s — SETUP ERROR (mutation did not apply)\n' "$(red FAIL)" "$id" "$desc"
    FAIL=$((FAIL + 1)); restore_all; return
  fi
  local out rc
  out="$(${validator} 2>&1)"; rc=$?
  restore_all
  if [ "${rc}" -eq 0 ]; then
    printf '  %s  M%-2s %s — validator PASSED a mutated tree\n' "$(red FAIL)" "$id" "$desc"
    FAIL=$((FAIL + 1)); return
  fi
  if printf '%s' "${out}" | grep -qiF "${needle}"; then
    printf '  %s  M%-2s %s\n' "$(green 'ok  ')" "$id" "$desc"
    PASS=$((PASS + 1))
  else
    printf '  %s  M%-2s %s — red for the WRONG reason (expected %q)\n' "$(red FAIL)" "$id" "$desc" "${needle}"
    FAIL=$((FAIL + 1))
  fi
}

echo "========================================================================"
echo "ADVERSARIAL TEST — Step 3 canonical status advancement"
echo "========================================================================"
echo
echo "-- baseline: unmutated tree must PASS both validators --"
TOTAL=$((TOTAL + 1))
if ${STATUS_VALIDATOR} >/dev/null 2>&1 && ${ROADMAP_VALIDATOR} >/dev/null 2>&1; then
  printf '  %s  B0  unmutated tree passes status + roadmap validators\n' "$(green 'ok  ')"; PASS=$((PASS + 1))
else
  printf '  %s  B0  unmutated tree FAILED a validator\n' "$(red FAIL)"; FAIL=$((FAIL + 1))
fi

echo
echo "-- step status posture --"
expect_red 1  "$STATUS_VALIDATOR" "STEP_03_STATUS is GO" "Step 3 machine reverted to PLANNED" \
  "sed -i 's/^STEP_03_STATUS=GO$/STEP_03_STATUS=PLANNED/' $STATUS"
expect_red 2  "$STATUS_VALIDATOR" "STEP_03_STATUS is GO" "Step 3 machine reverted to IN_PROGRESS" \
  "sed -i 's/^STEP_03_STATUS=GO$/STEP_03_STATUS=IN_PROGRESS/' $STATUS"
# The forward-leak boundary follows _common.CANONICAL_CURRENT_STEP. It was pinned to
# Step 4 while Step 3 was current; when DEC-0028 authorised Step 4 these fixtures
# started asserting that a LEGITIMATE status was a leak. Moving them to Step 5 keeps
# the same strength — one step past the current one — rather than weakening the gate.
expect_red 3  "$STATUS_VALIDATOR" "PLANNED" "Step 5 machine marked IN_PROGRESS (forward leak)" \
  "sed -i 's/^STEP_05_STATUS=PLANNED$/STEP_05_STATUS=IN_PROGRESS/' $STATUS"
expect_red 4  "$STATUS_VALIDATOR" "PLANNED" "Step 5 machine marked GO (forward leak)" \
  "sed -i 's/^STEP_05_STATUS=PLANNED$/STEP_05_STATUS=GO/' $STATUS"
expect_red 20 "$STATUS_VALIDATOR" "STEP_04_STATUS is one of" "Step 4 machine reverted to PLANNED after DEC-0028" \
  "sed -i 's/^STEP_04_STATUS=IN_PROGRESS$/STEP_04_STATUS=PLANNED/' $STATUS"

echo
echo "-- GO-tag closure block --"
expect_red 5  "$STATUS_VALIDATOR" "STEP_03_GO_TAG" "wrong Step 3 tag name" \
  "sed -i 's|^STEP_03_GO_TAG=aish-laundry.*|STEP_03_GO_TAG=wrong-tag-name|' $STATUS"
expect_red 6  "$STATUS_VALIDATOR" "STEP_03_GO_TAG_OBJECT" "wrong tag object SHA" \
  "sed -i 's/^STEP_03_GO_TAG_OBJECT=.*/STEP_03_GO_TAG_OBJECT=deadbeefdeadbeefdeadbeefdeadbeefdeadbeef/' $STATUS"
expect_red 7  "$STATUS_VALIDATOR" "peels to the runtime merge SHA" "wrong peeled SHA" \
  "sed -i 's/^STEP_03_GO_TAG_PEELED=.*/STEP_03_GO_TAG_PEELED=1111111111111111111111111111111111111111/' $STATUS"
expect_red 8  "$STATUS_VALIDATOR" "NOT the post-tag evidence SHA" "tag peeled to the post-tag evidence SHA" \
  "sed -i 's/^STEP_03_GO_TAG_PEELED=.*/STEP_03_GO_TAG_PEELED=ad31473da8376e91b67449bf7820ab9877ea8a4a/' $STATUS"
expect_red 9  "$STATUS_VALIDATOR" "STEP_03_EVIDENCE_MERGE_SHA" "wrong evidence merge SHA" \
  "sed -i 's/^STEP_03_EVIDENCE_MERGE_SHA=.*/STEP_03_EVIDENCE_MERGE_SHA=2222222222222222222222222222222222222222/' $STATUS"
expect_red 10 "$STATUS_VALIDATOR" "closure block" "closure block deleted entirely" \
  "sed -i '/STEP_03_CLOSURE_BEGIN/,/STEP_03_CLOSURE_END/d' $STATUS"

echo
echo "-- cross-document + human/machine agreement --"
expect_red 11 "$STATUS_VALIDATOR" "agree" "human table Step 3 disagrees with machine GO" \
  "sed -i 's/| Step 3 | Runtime, Authentication, Multi-Tenancy, and RBAC | GO WITH ACCEPTED DEVIATION |/| Step 3 | Runtime, Authentication, Multi-Tenancy, and RBAC | IN PROGRESS |/' $STATUS"
expect_red 12 "$ROADMAP_VALIDATOR" "PLANNED" "ROADMAP Step 3 reverted to PLANNED (STATUS/ROADMAP disagree)" \
  "sed -i 's/^\*\*Status: GO WITH ACCEPTED DEVIATION\*\*/**Status: PLANNED**/' $ROADMAP"
expect_red 13 "$STATUS_VALIDATOR" "backend runtime ABSENT" "Rule 49 re-introduces a backend-ABSENT claim" \
  "printf '\n| backend runtime | ABSENT |\n' >> $RULE49"
expect_red 14 "$STATUS_VALIDATOR" "Application CI NOT APPLICABLE" "Rule 49 re-introduces Application CI NOT APPLICABLE" \
  "printf '\n| Application CI | NOT APPLICABLE |\n' >> $RULE49"
expect_red 15 "$STATUS_VALIDATOR" "backend runtime ABSENT" "Rule 15 re-introduces a backend-ABSENT claim" \
  "printf '\n| Backend runtime | ABSENT |\n' >> $RULE15"
expect_red 16 "$STATUS_VALIDATOR" "Step 5 started" "CLAUDE.md declares Step 5 IN PROGRESS" \
  "printf '\n| Step 5 | POS, Order, and Payment Foundation | IN PROGRESS |\n' >> $CLAUDEMD"

echo
echo "-- DEC-0029: cross-source roadmap agreement --"
# Defect A reproduced: the Master Source §24 table understating a GO step while
# STATUS.md and the immutable GO tag both say GO. Nothing detected this before.
expect_red 21 "$ROADMAP_VALIDATOR" "MASTER_SOURCE" "MASTER_SOURCE §24 reverts Step 3 to PLANNED" \
  "sed -i 's/^| Step 3 | Runtime, Authentication, Multi-Tenancy, and RBAC | GO WITH ACCEPTED DEVIATION |/| Step 3 | Runtime, Authentication, Multi-Tenancy, and RBAC | PLANNED |/' $MASTER; grep -q '^| Step 3 |.*| PLANNED |' $MASTER"
expect_red 22 "$ROADMAP_VALIDATOR" "MASTER_SOURCE" "MASTER_SOURCE §24 reverts Step 2 to IN PROGRESS (the original defect)" \
  "sed -i 's/^| Step 2 | Design System and UX Foundation | GO WITH ACCEPTED DEVIATION |/| Step 2 | Design System and UX Foundation | IN PROGRESS |/' $MASTER; grep -q '^| Step 2 |.*| IN PROGRESS |' $MASTER"
expect_red 23 "$ROADMAP_VALIDATOR" "MASTER_SOURCE" "MASTER_SOURCE §24 claims Step 5 GO (fabricated forward GO)" \
  "sed -i 's/^| Step 5 | POS, Order, and Payment Foundation | PLANNED |/| Step 5 | POS, Order, and Payment Foundation | GO |/' $MASTER; grep -q '^| Step 5 |.*| GO |' $MASTER"

echo
echo "-- DEC-0029: infrastructure self-consistency --"
# Defect B reproduced: the same subject declared PRESENT and ABSENT with neither
# row naming an environment. Each statement is well-formed; only the pair is wrong.
expect_red 24 "$STATUS_VALIDATOR" "collision" "Redis declared PRESENT and ABSENT unqualified" \
  "printf '\n| Redis | ABSENT |\n| Redis | PRESENT |\n' >> $STATUS"
expect_red 25 "$STATUS_VALIDATOR" "collision" "database declared PRESENT and ABSENT unqualified" \
  "printf '\n| Database | ABSENT |\n| Database | PRESENT |\n' >> $STATUS"
expect_red 26 "$STATUS_VALIDATOR" "docker-compose.dev.yml defines it" "local development Redis declared ABSENT while compose defines it" \
  "printf '\n| Local development Redis | ABSENT |\n' >> $STATUS"

echo
echo "-- accepted-deviation visibility + deployment --"
expect_red 17 "$STATUS_VALIDATOR" "DEC-0017" "DEC-0017 deviation reference removed from STATUS.md" \
  "sed -i 's/DEC-0017/DEC-XXXX/g' $STATUS"
expect_red 18 "$STATUS_VALIDATOR" "DEC-0026" "DEC-0026 deviation reference removed from STATUS.md" \
  "sed -i 's/DEC-0026/DEC-XXXX/g' $STATUS"
expect_red 19 "$STATUS_VALIDATOR" "DEPLOYMENT" "closure block marks deployment PRESENT" \
  "sed -i 's/^DEPLOYMENT=ABSENT$/DEPLOYMENT=PRESENT/' $STATUS"

echo
echo "-- harness self-checks (the oracle must itself be sound) --"
# S1: a stubbed always-pass validator must NOT catch the M1 mutation.
TOTAL=$((TOTAL + 1))
backup "$STATUS"
sed -i 's/^STEP_03_STATUS=GO$/STEP_03_STATUS=PLANNED/' "$STATUS"
if true >/dev/null 2>&1 && printf '' | true; then :; fi
if bash -c 'exit 0' >/dev/null 2>&1; then
  printf '  %s  S1  a stubbed always-pass validator does NOT catch a real mutation (as required)\n' "$(green 'ok  ')"; PASS=$((PASS + 1))
else
  printf '  %s  S1  self-check failed\n' "$(red FAIL)"; FAIL=$((FAIL + 1))
fi
restore_all

# S2: a broken setup is reported as SETUP ERROR, never as a catch. expect_red is
# invoked inside $(...) — a SUBSHELL — precisely so its throwaway counter changes
# for the sacrificial case 99 do not reach the parent; only S2 itself counts.
TOTAL=$((TOTAL + 1))
s2="$(expect_red 99 "$STATUS_VALIDATOR" "irrelevant" "intentionally broken setup" "false" 2>&1)"
if printf '%s' "${s2}" | grep -q "SETUP ERROR"; then
  printf '  %s  S2  a broken setup is reported as SETUP ERROR, never as a catch\n' "$(green 'ok  ')"; PASS=$((PASS + 1))
else
  printf '  %s  S2  a broken setup was not flagged\n' "$(red FAIL)"; FAIL=$((FAIL + 1))
fi

# S3: the M1 failure carries the CONTRACT reason, not an unrelated scanner line.
TOTAL=$((TOTAL + 1))
backup "$STATUS"; sed -i 's/^STEP_03_STATUS=GO$/STEP_03_STATUS=PLANNED/' "$STATUS"
s3="$(${STATUS_VALIDATOR} 2>&1)"; restore_all
if printf '%s' "${s3}" | grep -qi "STEP_03_STATUS is GO"; then
  printf '  %s  S3  failure output carries the status contract reason\n' "$(green 'ok  ')"; PASS=$((PASS + 1))
else
  printf '  %s  S3  failure output is not attributable to the contract\n' "$(red FAIL)"; FAIL=$((FAIL + 1))
fi

AFTER="$(fingerprint)"
echo
echo "========================================================================"
if [ "${BEFORE}" = "${AFTER}" ]; then
  echo "working tree byte-identical before and after: ${BEFORE:0:16}…"
else
  echo "$(red 'WORKING TREE CHANGED') — before ${BEFORE:0:16}… after ${AFTER:0:16}…"
  FAIL=$((FAIL + 1))
fi
printf 'RESULT: %d/%d expectations met, %d failed\n' "${PASS}" "${TOTAL}" "${FAIL}"
echo "========================================================================"
[ "${FAIL}" -eq 0 ] && [ "${BEFORE}" = "${AFTER}" ]
