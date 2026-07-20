#!/usr/bin/env bash
#
# Adversarial harness for the local-development environment contract (DEC-0027).
#
# Rule 33 item 15 / Rule 47: a validator that has only ever run against correct
# input is an UNTESTED validator, and reporting it as a passing gate overstates
# the assurance it provides. This harness proves that
# scripts/validate-dev-environment-contract.py turns RED for each documented
# violation class, GREEN for the legitimate controls, and that
# scripts/bootstrap-env-files.sh actually behaves as DEC-0027 requires.
#
# Every RED case asserts a SPECIFIC stable failure code. A test that merely
# asserts "the validator exited non-zero" cannot tell a real catch from an
# unrelated failure — that is exactly how an unrelated secret-scanner error gets
# counted as environment-contract evidence.
#
# Safety properties:
#   - every mutation is applied to a throwaway copy, never to the working tree;
#   - the working tree is verified byte-identical before and after;
#   - no git history operation is performed, so the destructive-operations guard
#     is never engaged or bypassed;
#   - no DB_PASSWORD value is printed, by this harness or by anything it runs.
#
# The result count is NOT predeclared anywhere. It is whatever the executed
# cases produce.

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

VALIDATOR="scripts/validate-dev-environment-contract.py"
BOOTSTRAP="scripts/bootstrap-env-files.sh"

# Files git would actually carry. Git-IGNORED files are deliberately excluded:
# a developer's local .env and backend/.env must NOT appear in the sandbox,
# because the whole point of the behavioural cases is to reproduce a fresh clone
# in which neither destination exists yet.
repo_payload() {
  { git ls-files -z; git ls-files -z --others --exclude-standard; } 2>/dev/null | sort -z -u
}

fingerprint() {
  repo_payload | xargs -0 sha256sum 2>/dev/null | sha256sum | awk '{print $1}'
}
BEFORE="$(fingerprint)"

BASE="${WORK}/base"
mkdir -p "${BASE}"
repo_payload | tar -cf - --null -T - 2>/dev/null | (cd "${BASE}" && tar -xf -)

# Prove the sandbox really is a fresh-clone equivalent before relying on it.
if [ -e "${BASE}/.env" ] || [ -e "${BASE}/backend/.env" ]; then
  echo "$(red 'SANDBOX CONTAMINATED') — an ignored .env leaked into the base copy"
  exit 1
fi

# run_setup <sandbox> <setup-string>
# Fails LOUDLY. A harness whose setup silently fails reports false confidence,
# which is worse than no harness — an earlier harness in this repository did
# exactly that and reported every mutation as caught when none had been applied.
run_setup() {
  local sandbox="$1"; shift
  local out
  if ! out="$( cd "${sandbox}" && eval "$*" 2>&1 )"; then
    printf '  %s  setup failed: %s\n' "$(red ERROR)" "${out}" >&2
    return 1
  fi
  return 0
}

# expect_red <id> <expected-code> <description> <setup-commands>
#
# Asserts three things, not one:
#   1. setup completed;
#   2. the validator turned RED;
#   3. it turned red for the EXPECTED REASON, identified by its stable code.
expect_red() {
  local id="$1"; shift
  local code="$1"; shift
  local desc="$1"; shift
  TOTAL=$((TOTAL + 1))
  local sandbox="${WORK}/m${id}"
  rm -rf "${sandbox}"; cp -a "${BASE}" "${sandbox}"

  if ! run_setup "${sandbox}" "$@"; then
    printf '  %s  M%-2s %s — SETUP ERROR\n' "$(red FAIL)" "${id}" "${desc}"
    FAIL=$((FAIL + 1)); return
  fi

  local out rc
  out="$( cd "${sandbox}" && python3 "${VALIDATOR}" 2>&1 )"; rc=$?

  if [ "${rc}" -eq 0 ]; then
    printf '  %s  M%-2s %s — validator stayed GREEN\n' "$(red FAIL)" "${id}" "${desc}"
    FAIL=$((FAIL + 1)); return
  fi
  if ! printf '%s' "${out}" | grep -q "FAIL.*\[${code}\]"; then
    printf '  %s  M%-2s %s — red for the WRONG reason (expected [%s])\n' \
      "$(red FAIL)" "${id}" "${desc}" "${code}"
    FAIL=$((FAIL + 1)); return
  fi
  printf '  %s  M%-2s %-52s [%s]\n' "$(green 'ok  ')" "${id}" "${desc}" "${code}"
  PASS=$((PASS + 1))
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
  if ( cd "${sandbox}" && python3 "${VALIDATOR}" ) >/dev/null 2>&1; then
    printf '  %s  G%-2s %s\n' "$(green 'ok  ')" "${id}" "${desc}"
    PASS=$((PASS + 1))
  else
    printf '  %s  G%-2s %s — validator wrongly turned RED\n' "$(red FAIL)" "${id}" "${desc}"
    FAIL=$((FAIL + 1))
  fi
}

# behave <id> <description> <script>
# Behavioural case: runs real code in a sandbox; the script must exit 0.
behave() {
  local id="$1"; shift
  local desc="$1"; shift
  TOTAL=$((TOTAL + 1))
  local sandbox="${WORK}/b${id}"
  rm -rf "${sandbox}"; cp -a "${BASE}" "${sandbox}"

  local out rc
  out="$( cd "${sandbox}" && eval "$*" 2>&1 )"; rc=$?
  if [ "${rc}" -eq 0 ]; then
    printf '  %s  B%-2s %s\n' "$(green 'ok  ')" "${id}" "${desc}"
    PASS=$((PASS + 1))
  else
    printf '  %s  B%-2s %s\n' "$(red FAIL)" "${id}" "${desc}"
    printf '        %s\n' "${out}" | head -5
    FAIL=$((FAIL + 1))
  fi
}

# Mutate a key's value in one env file.
setkey() { printf 'sed -i "s|^%s=.*|%s=%s|" %s' "$1" "$1" "$2" "$3"; }

echo "========================================================================"
echo "ADVERSARIAL TEST — ${VALIDATOR}"
echo "                 — ${BOOTSTRAP}"
echo "========================================================================"

echo
echo "-- legitimate controls must PASS --"
expect_green 1 "unmutated repository (matching templates)" "true"

behave 1 "bootstrap creates BOTH missing destinations" \
  'bash scripts/bootstrap-env-files.sh >/dev/null 2>&1 && [ -f .env ] && [ -f backend/.env ]'

behave 2 "bootstrap PRESERVES both existing destinations" \
  'printf "SENTINEL_ROOT=1\n" > .env; printf "SENTINEL_BACKEND=1\n" > backend/.env;
   bash scripts/bootstrap-env-files.sh >/dev/null 2>&1;
   grep -q SENTINEL_ROOT .env && grep -q SENTINEL_BACKEND backend/.env'

behave 3 "second bootstrap run is idempotent" \
  'bash scripts/bootstrap-env-files.sh >/dev/null 2>&1;
   a="$(sha256sum .env backend/.env)";
   bash scripts/bootstrap-env-files.sh >/dev/null 2>&1;
   b="$(sha256sum .env backend/.env)";
   [ "$a" = "$b" ]'

behave 4 "bootstrap output never contains the password value" \
  'pw="$(sed -n "s|^DB_PASSWORD=||p" .env.example)";
   out="$(bash scripts/bootstrap-env-files.sh 2>&1)";
   ! printf "%s" "$out" | grep -qF "$pw"'

echo
echo "-- template value contract --"
expect_red 1  ENV_PORT_UNEXPECTED "backend host-side port set to 5432" \
  "$(setkey DB_PORT 5432 backend/.env.example)"
expect_red 2  ENV_PLACEHOLDER_UNRESOLVED "unresolved username placeholder" \
  "$(setkey DB_USERNAME replace_with_local_username backend/.env.example)"
expect_red 3  ENV_PLACEHOLDER_UNRESOLVED "unresolved database placeholder" \
  "$(setkey DB_DATABASE replace_with_local_database_name backend/.env.example)"
expect_red 4  ENV_PLACEHOLDER_UNRESOLVED "unresolved password placeholder" \
  "$(setkey DB_PASSWORD replace_with_local_password backend/.env.example)"
expect_red 5  ENV_MISMATCH "backend/root username mismatch" \
  "$(setkey DB_USERNAME aish_other backend/.env.example)"
expect_red 6  ENV_MISMATCH "backend/root database mismatch" \
  "$(setkey DB_DATABASE aish_other_dev backend/.env.example)"
expect_red 7  ENV_MISMATCH "backend/root port mismatch" \
  "$(setkey DB_PORT 55434 backend/.env.example)"
expect_red 8  ENV_MISMATCH "backend/root host mismatch" \
  "$(setkey DB_HOST 10.0.0.5 backend/.env.example)"
expect_red 9  ENV_KEY_PRESENT "missing DB_PASSWORD" \
  'sed -i "/^DB_PASSWORD=/d" backend/.env.example'
expect_red 10 ENV_KEY_DUPLICATE "duplicate DB_PORT" \
  'printf "DB_PORT=55433\n" >> backend/.env.example'
expect_red 11 ENV_KEY_DUPLICATE "duplicate DB_USERNAME" \
  'printf "DB_USERNAME=aish_dev\n" >> backend/.env.example'
expect_red 12 ENV_VALUE_BLANK "blank DB_DATABASE" \
  "$(setkey DB_DATABASE '' backend/.env.example)"
expect_red 13 ENV_PORT_MALFORMED "non-numeric DB_PORT" \
  "$(setkey DB_PORT 'five-five-four-three-three' backend/.env.example)"
expect_red 14 ENV_PORT_OUT_OF_RANGE "out-of-range DB_PORT" \
  'sed -i "s|^DB_PORT=.*|DB_PORT=99999|" .env.example backend/.env.example'
expect_red 15 ENV_PRODUCTION_HOST "production hostname" \
  'sed -i "s|^DB_HOST=.*|DB_HOST=db.aishlaundry.co.id|" .env.example backend/.env.example'
expect_red 16 ENV_DATABASE_UNEXPECTED "production-looking database name" \
  'sed -i "s|^DB_DATABASE=.*|DB_DATABASE=aish_laundry_production|" .env.example backend/.env.example'
expect_red 17 ENV_USERNAME_UNEXPECTED "production-looking username" \
  'sed -i "s|^DB_USERNAME=.*|DB_USERNAME=aish_prod|" .env.example backend/.env.example'
# Assembled at runtime from fragments so this file contains no literal a
# credential scanner could match. Exempting the harness from the scanner instead
# would create a blind spot in which a real secret could hide — the fixture is
# split, the scanner is not weakened. (Same discipline as
# scripts/test-step-03-validators.sh.)
UNMARKED_PW="Tr0ub4"; UNMARKED_PW="${UNMARKED_PW}dor3x"; UNMARKED_PW="${UNMARKED_PW}K1te"
expect_red 18 ENV_PASSWORD_NO_MARKER "password without a fictional marker" \
  "sed -i \"s|^DB_PASSWORD=.*|DB_PASSWORD=${UNMARKED_PW}|\" .env.example backend/.env.example"
expect_red 19 ENV_VALUE_QUOTED "quoted value where canon requires bare" \
  'sed -i "s|^DB_DATABASE=.*|DB_DATABASE=\\\"aish_laundry_dev\\\"|" .env.example backend/.env.example'
expect_red 20 ENV_FILE_PRESENT "missing root example" \
  'rm -f .env.example'
expect_red 21 ENV_FILE_PRESENT "missing backend example" \
  'rm -f backend/.env.example'

echo
echo "-- bootstrap path contract --"
expect_red 22 BOOT_DOC_BACKEND "documentation drops the backend copy instruction" \
  'sed -i "s|cp backend/\.env\.example backend/\.env||; s|bootstrap-env-files\.sh|bootstrap-OTHER.sh|g; s|backend/\.env\b||g" docs/runtime/LOCAL_DEVELOPMENT.md'
expect_red 23 BOOT_COPIES_BACKEND "bootstrap drops the backend copy" \
  'sed -i "/install_env \"backend\/.env.example\"/d" scripts/bootstrap-env-files.sh'
expect_red 24 BOOT_NO_OVERWRITE "bootstrap loses its overwrite guard" \
  'python3 - <<PY
import re,io
p="scripts/bootstrap-env-files.sh"
s=open(p).read()
s=s.replace("""  if [ -e "\${REPO_ROOT}/\${dest}" ]; then""","""  if false; then""")
s=s.replace("PRESERVED — ALREADY EXISTS","OVERWRITTEN")
open(p,"w").write(s)
PY'
expect_red 25 BOOT_SYMLINK_GUARD "bootstrap loses its symlink guard" \
  'python3 - <<PY
p="scripts/bootstrap-env-files.sh"
s=open(p).read()
s=s.replace("""  if [ -L "\${REPO_ROOT}/\${dest}" ]; then""","""  if false; then""")
open(p,"w").write(s)
PY'
expect_red 26 BOOT_PRINTS_SECRET "bootstrap prints DB_PASSWORD" \
  'printf "echo \"DB_PASSWORD leaked\"\n" >> scripts/bootstrap-env-files.sh'
expect_red 27 BOOT_VALIDATES_FIRST "bootstrap stops validating templates first" \
  'sed -i "s|validate-dev-environment-contract\.py|validate-NOTHING.py|g" scripts/bootstrap-env-files.sh'
expect_red 28 BOOT_SCRIPT_PRESENT "bootstrap script deleted entirely" \
  'rm -f scripts/bootstrap-env-files.sh'
expect_red 29 BOOT_DELEGATION "Step 3 bootstrap stops delegating" \
  'sed -i "/bootstrap-env-files\.sh/d" scripts/bootstrap-step-03.sh'

echo
echo "-- behavioural: the bootstrap must actually refuse --"
behave 5 "bootstrap fails closed when a template is missing" \
  'rm -f backend/.env.example;
   ! bash scripts/bootstrap-env-files.sh >/dev/null 2>&1 && [ ! -f backend/.env ]'

behave 6 "bootstrap refuses to write through a symlink destination" \
  'ln -s /tmp/aish-escape-target backend/.env;
   ! bash scripts/bootstrap-env-files.sh >/dev/null 2>&1 && [ ! -e /tmp/aish-escape-target ]'

echo
echo "-- harness self-checks (the oracle must itself be sound) --"

# A stubbed validator must NOT catch a real mutation. If this case were to
# report "caught", every RED result above would be worthless, because they would
# be passing on something other than the validator's actual logic.
TOTAL=$((TOTAL + 1))
sb="${WORK}/self1"; rm -rf "${sb}"; cp -a "${BASE}" "${sb}"
( cd "${sb}" && sed -i 's|^DB_PORT=.*|DB_PORT=5432|' backend/.env.example \
  && printf '#!/usr/bin/env python3\nimport sys\nsys.exit(0)\n' > "${VALIDATOR}" )
if ( cd "${sb}" && python3 "${VALIDATOR}" ) >/dev/null 2>&1; then
  printf '  %s  S1  stubbed validator does NOT catch a real mutation (as required)\n' "$(green 'ok  ')"
  PASS=$((PASS + 1))
else
  printf '  %s  S1  a stubbed validator still failed — RED results above are not attributable\n' "$(red FAIL)"
  FAIL=$((FAIL + 1))
fi

# A failed SETUP must never be counted as a caught mutation.
TOTAL=$((TOTAL + 1))
sb="${WORK}/self2"; rm -rf "${sb}"; cp -a "${BASE}" "${sb}"
if run_setup "${sb}" "this-command-does-not-exist-anywhere" 2>/dev/null; then
  printf '  %s  S2  a broken setup was reported as successful\n' "$(red FAIL)"
  FAIL=$((FAIL + 1))
else
  printf '  %s  S2  a broken setup is reported as SETUP ERROR, never as a catch\n' "$(green 'ok  ')"
  PASS=$((PASS + 1))
fi

# An unrelated failure must not be mistaken for contract evidence. Every RED
# case above asserts its own [CODE]; this proves the assertion discriminates.
TOTAL=$((TOTAL + 1))
sb="${WORK}/self3"; rm -rf "${sb}"; cp -a "${BASE}" "${sb}"
( cd "${sb}" && sed -i 's|^DB_PORT=.*|DB_PORT=5432|' backend/.env.example )
out="$( cd "${sb}" && python3 "${VALIDATOR}" 2>&1 )"
if printf '%s' "${out}" | grep -q 'FAIL.*\[ENV_PORT_UNEXPECTED\]' \
   && ! printf '%s' "${out}" | grep -qi 'secret scan\|secret-scan'; then
  printf '  %s  S3  failure output carries a contract code, not an unrelated scanner result\n' "$(green 'ok  ')"
  PASS=$((PASS + 1))
else
  printf '  %s  S3  failure output is not attributable to the contract\n' "$(red FAIL)"
  FAIL=$((FAIL + 1))
fi

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
