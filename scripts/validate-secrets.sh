#!/usr/bin/env bash
# Scan tracked files for credential material.
#
# Self-exclusion: every pattern below is ASSEMBLED from fragments at runtime, so
# this file's own source text never contains a literal credential pattern and
# cannot match itself. The scanner additionally skips its own path.
set -euo pipefail

SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
SCRIPT_DIR="$(dirname "$SCRIPT_PATH")"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$REPO_ROOT"

TITLE="secrets"
PASSED=0
FAILED=0

echo "========================================================================"
echo "VALIDATOR: $TITLE"
echo "========================================================================"

pass() { PASSED=$((PASSED + 1)); echo "PASS  $1"; }
fail() { FAILED=$((FAILED + 1)); echo "FAIL  $1"; }

# ---------------------------------------------------------------------------
# Build the file list (tracked + untracked-but-not-ignored), excluding .git
# and this scanner itself.
# ---------------------------------------------------------------------------
FILELIST="$(mktemp)"
trap 'rm -f "$FILELIST"' EXIT

if git rev-parse --git-dir >/dev/null 2>&1; then
  git ls-files --cached --others --exclude-standard >"$FILELIST"
else
  find . -path ./.git -prune -o -type f -print | sed 's|^\./||' >"$FILELIST"
fi

SELF_REL="${SCRIPT_PATH#"$REPO_ROOT"/}"
SCAN_LIST="$(mktemp)"
trap 'rm -f "$FILELIST" "$SCAN_LIST"' EXIT
grep -v -x -F "$SELF_REL" "$FILELIST" >"$SCAN_LIST" || true

FILE_COUNT="$(wc -l <"$SCAN_LIST" | tr -d ' ')"
echo "      scanning $FILE_COUNT files (excluding $SELF_REL)"

# ---------------------------------------------------------------------------
# Assembled patterns. Fragments are concatenated so the literal never appears.
# ---------------------------------------------------------------------------
P_PRIVATE_KEY="-----BE""GIN [A-Z ]*PRIVATE KEY-----"
P_AWS_KEY="\b(AK""IA|AS""IA|AB""IA|AC""CA)[0-9A-Z]{16}\b"
P_AWS_SECRET="aws_secret_access""_key[[:space:]]*[=:][[:space:]]*[A-Za-z0-9/+=]{40}"
P_GITHUB_TOKEN="\b(gh""p|gh""o|gh""s|gh""u|gh""r)_[A-Za-z0-9]{36,255}\b"
P_GITHUB_PAT="\bgithub""_pat""_[A-Za-z0-9_]{60,}\b"
P_SLACK_TOKEN="\bxo""x[abprs]-[A-Za-z0-9-]{10,}\b"
P_SLACK_HOOK="hooks\.slack\.com/services/T[A-Za-z0-9]+/B[A-Za-z0-9]+/[A-Za-z0-9]+"
P_GENERIC="(pass""word|pass""wd|se""cret|api[_-]?k""ey|access[_-]?to""ken|client[_-]?se""cret)[[:space:]]*[=:][[:space:]]*[\"']?[A-Za-z0-9/+_.-]{8,}"
P_PRIVATE_KEY_PEM="PRIVATE KEY BLOCK"

# Placeholder / documentation values that are NOT credentials.
#
# SECURITY NOTE (finding C4): this filter must be applied ONLY to the matched
# content, never to the whole "path:line:content" grep record. Filtering the
# full record let the FILE PATH silence a real finding -- a file named
# docs/never.md or docs/examples.md became a credential free-fire zone while
# the required secret-scan check stayed green. On a PUBLIC repository that is a
# live credential-exfiltration path.
#
# The word list is also deliberately narrow. Generic prose words that previously
# appeared here (never, example, TODO, masked, hashed, tidak, jangan, dilarang,
# tanpa) match ordinary Indonesian and English documentation sentences, so a real
# token sitting on a line that merely mentioned one of them was suppressed.
# Only unambiguous placeholder markers are kept.
PLACEHOLDER_FILTER='CHANGEME|CHANGE_ME|change_me|REDACTED|redacted|<[^>]*>|\$\{|\$\(|env\(|xxxx|XXXX|placeholder|PLACEHOLDER|your[_-]|YOUR[_-]|dummy|DUMMY|\*\*\*|NOT_SET|secrets\.'

# Strip the "path:line:" prefix and test the remaining content only.
filter_placeholders() {
  local line content
  while IFS= read -r line; do
    [ -n "$line" ] || continue
    content="${line#*:}"      # drop path
    content="${content#*:}"   # drop line number
    if ! printf '%s' "$content" | grep -qE "$PLACEHOLDER_FILTER"; then
      printf '%s\n' "$line"
    fi
  done
}

scan_pattern() {
  local label="$1" pattern="$2"
  local hits
  hits="$(xargs -r -a "$SCAN_LIST" -d '\n' \
    grep -n -I -H -E --no-messages -e "$pattern" 2>/dev/null || true)"
  if [ -n "$hits" ]; then
    hits="$(printf '%s\n' "$hits" | filter_placeholders || true)"
  fi
  if [ -n "$hits" ]; then
    fail "credential pattern detected: $label"
    printf '%s\n' "$hits" | head -20 | sed 's/^/      /'
  else
    pass "no match for credential pattern: $label"
  fi
}

scan_pattern "private key block" "$P_PRIVATE_KEY"
scan_pattern "PGP/private key armor" "$P_PRIVATE_KEY_PEM"
scan_pattern "AWS access key id" "$P_AWS_KEY"
scan_pattern "AWS secret access key" "$P_AWS_SECRET"
scan_pattern "GitHub token" "$P_GITHUB_TOKEN"
scan_pattern "GitHub fine-grained PAT" "$P_GITHUB_PAT"
scan_pattern "Slack token" "$P_SLACK_TOKEN"
scan_pattern "Slack incoming webhook" "$P_SLACK_HOOK"
scan_pattern "generic credential assignment" "$P_GENERIC"

# ---------------------------------------------------------------------------
# Forbidden credential FILES.
# ---------------------------------------------------------------------------
# `.env.example` is a committed TEMPLATE containing only placeholders, and
# .gitignore whitelists it explicitly (`!.env.example`) while ignoring every real
# `.env`. The exemption is FILENAME-ONLY: the file's CONTENT is still scanned by
# every credential pattern above, so a real secret placed inside it is still
# caught. Exempting the content as well would turn the template into a
# credential free-fire zone on a PUBLIC repository.
FORBIDDEN_FILES="$(grep -E '(^|/)(\.env($|\.)|id_rsa($|\.)|id_dsa($|\.)|id_ecdsa($|\.)|id_ed25519($|\.)|.*\.pem$|.*\.p12$|.*\.pfx$|.*\.keystore$|.*\.jks$|credentials\.json$|service-account.*\.json$)' \
  "$SCAN_LIST" | grep -v -E '(^|/)\.env\.example$' || true)"
if [ -n "$FORBIDDEN_FILES" ]; then
  fail "credential file(s) present in the repository"
  printf '%s\n' "$FORBIDDEN_FILES" | sed 's/^/      /'
else
  pass "no .env, id_rsa, .pem, keystore, or service-account file is tracked"
fi

# ---------------------------------------------------------------------------
echo "------------------------------------------------------------------------"
TOTAL=$((PASSED + FAILED))
echo "SUMMARY [$TITLE]: $PASSED/$TOTAL checks passed, $FAILED failed"
if [ "$FAILED" -ne 0 ]; then
  echo "RESULT: FAIL ($TITLE)"
  exit 1
fi
echo "RESULT: PASS ($TITLE)"
exit 0
