#!/usr/bin/env bash
# Public-repository safety scan for Aish Laundry App.
#
# This repository is PUBLIC (AMENDMENT-0001, DEC-0016). Every file is
# world-readable and permanently so; deletion is not remediation. This scan looks
# for material that must never be committed: real personal data, credentials,
# tokens, production configuration, dumps, and backups.
#
# Exit 0 = clean. Exit 1 = at least one finding. Fails closed.
set -uo pipefail

SCRIPT_DIR="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$REPO_ROOT"

echo "========================================================================"
echo "VALIDATOR: public-repository-safety"
echo "========================================================================"

PASS=0
FAIL=0

ok()   { PASS=$((PASS + 1)); echo "PASS  $1"; }
bad()  { FAIL=$((FAIL + 1)); echo "FAIL  $1"; }
info() { echo "      $1"; }

# Files to scan: everything git tracks or would track, excluding this scanner and
# the guard's own test fixtures, which deliberately contain dangerous-looking
# strings in order to prove the guard blocks them.
mapfile -t FILES < <(
  git ls-files --cached --others --exclude-standard 2>/dev/null \
    | grep -vE '^(scripts/validate-public-repository-safety\.sh|scripts/validate-secrets\.sh|scripts/test-destructive-guard\.sh|\.claude/hooks/guard-destructive-operations\.sh)$' \
    || true
)

if [ "${#FILES[@]}" -eq 0 ]; then
  bad "found files to scan"
  echo "SUMMARY [public-repository-safety]: ${PASS} passed, ${FAIL} failed"
  echo "RESULT: FAIL (public-repository-safety)"
  exit 1
fi
info "scanning ${#FILES[@]} tracked files"

# ---------------------------------------------------------------------------
# check <label> <extended-regex> [allow-regex]
#
# Reports FAIL when the pattern matches a line that the allow-regex does not
# excuse. The allow-regex exists because governance documents must be able to
# *describe* a forbidden thing in order to forbid it.
# ---------------------------------------------------------------------------
check() {
  local label="$1" pattern="$2" allow="${3:-}"
  local hits
  hits="$(grep -InE "$pattern" -- "${FILES[@]}" 2>/dev/null || true)"
  if [ -n "$allow" ] && [ -n "$hits" ]; then
    hits="$(printf '%s\n' "$hits" | grep -vE "$allow" || true)"
  fi
  if [ -n "$hits" ]; then
    bad "$label"
    printf '%s\n' "$hits" | head -8 | while IFS= read -r line; do
      info "${line:0:180}"
    done
  else
    ok "$label"
  fi
}

# --- Indonesian mobile numbers that look real -------------------------------
# Fictional examples must be obviously fake (X placeholders or 0000 blocks).
check "no real-looking Indonesian mobile number" \
  '(\+62|\b62|\b0)8[1-9][0-9]{1}[- ]?[0-9]{3,4}[- ]?[0-9]{3,5}\b' \
  'X{2,}|0000|1234|NNNN|9999|contoh|example|fiktif|fictional|placeholder'

# --- private keys and certificates ------------------------------------------
check "no private key block" \
  'BEGIN[ A-Z]*PRIVATE KEY|BEGIN OPENSSH PRIVATE KEY|BEGIN PGP PRIVATE'

# --- provider and cloud credentials -----------------------------------------
check "no AWS access key id" 'AKIA[0-9A-Z]{16}'
check "no Google API key" 'AIza[0-9A-Za-z_-]{35}'
check "no Slack token" 'xox[abprs]-[0-9A-Za-z-]{10,}'
check "no GitHub token" 'gh[pousr]_[0-9A-Za-z]{36,}'
check "no Stripe secret key" 'sk_live_[0-9A-Za-z]{16,}'
check "no JSON Web Token" 'eyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.'

# --- assigned secrets --------------------------------------------------------
# Matches an assignment whose value is a long opaque literal. Documentation that
# merely names a variable, or uses an obvious placeholder, is allowed.
check "no assigned secret literal" \
  '(password|passwd|secret|api[_-]?key|access[_-]?token|client[_-]?secret|private[_-]?key)[[:space:]]*[:=][[:space:]]*["'"'"'][A-Za-z0-9/+_-]{16,}["'"'"']' \
  'X{4,}|<[^>]+>|\$\{|placeholder|example|contoh|fictional|fiktif|redact|your[_-]|CHANGE|TODO|\.\.\.'

# --- environment and production configuration -------------------------------
check "no database connection string with credentials" \
  '(postgres|postgresql|mysql|mongodb|redis)://[A-Za-z0-9._%-]+:[^@[:space:]/]{6,}@' \
  'X{4,}|<[^>]+>|user:pass|USER:PASS|example|contoh|placeholder|redact'

# --- committed .env / dump / backup files -----------------------------------
ENV_FILES="$(printf '%s\n' "${FILES[@]}" | grep -E '(^|/)\.env(\..+)?$' || true)"
if [ -n "$ENV_FILES" ]; then
  bad "no .env file is committed"
  printf '%s\n' "$ENV_FILES" | head -5 | while IFS= read -r f; do info "$f"; done
else
  ok "no .env file is committed"
fi

DUMP_FILES="$(printf '%s\n' "${FILES[@]}" | grep -iE '\.(sql|dump|bak|sqlite3?|db)$' || true)"
if [ -n "$DUMP_FILES" ]; then
  bad "no database dump or backup is committed"
  printf '%s\n' "$DUMP_FILES" | head -5 | while IFS= read -r f; do info "$f"; done
else
  ok "no database dump or backup is committed"
fi

# --- honesty: the repository is never described as private ------------------
# A line that *forbids* or *corrects* the claim necessarily contains the claim's
# words. Those are the correct form and must not be flagged; only an unqualified
# assertion is a finding.
# Case-insensitive: an earlier revision matched only lowercase, so a claim at the
# start of a sentence — "This repository is private." — passed undetected. The
# adversarial harness caught that.
PRIVATE_CLAIM="$(
  grep -InEi '(this|the)[[:space:]]+(repository|repo)[[:space:]]+(is|remains|stays)[[:space:]]+private' \
    -- "${FILES[@]}" 2>/dev/null \
    | grep -viE 'never|not |no longer|would be|canonical desired|rather than|instead of|were|had been|desired visibility|claim\w*|assert\w*|describ\w*|PUBLIC|AMENDMENT|DEC-0016|violat\w*|forbid\w*|prohibit\w*|correct\w*|false|wrong|must|fixture|sentence' \
    || true
)"
if [ -n "$PRIVATE_CLAIM" ]; then
  bad "the repository is never described as private"
  printf '%s\n' "$PRIVATE_CLAIM" | head -5 | while IFS= read -r line; do
    info "${line:0:180}"
  done
else
  ok "the repository is never described as private"
fi

# --- the deviation is recorded, not normalised -------------------------------
if grep -qE 'canonical desired' docs/MASTER_SOURCE.md 2>/dev/null \
   && [ -f docs/decisions/DEC-0016-public-repository-visibility-accepted-deviation.md ]; then
  ok "PUBLIC visibility is recorded as an accepted deviation (DEC-0016)"
else
  bad "PUBLIC visibility is recorded as an accepted deviation (DEC-0016)"
fi

echo "------------------------------------------------------------------------"
TOTAL=$((PASS + FAIL))
echo "SUMMARY [public-repository-safety]: ${PASS}/${TOTAL} checks passed, ${FAIL} failed"
if [ "$FAIL" -ne 0 ]; then
  echo "RESULT: FAIL (public-repository-safety)"
  exit 1
fi
echo "RESULT: PASS (public-repository-safety)"
exit 0
