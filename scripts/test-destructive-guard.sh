#!/usr/bin/env bash
# Exercise .claude/hooks/guard-destructive-operations.sh independently of its
# own self-test: dangerous commands must be blocked (exit 2), safe commands
# must be allowed (exit 0).
set -euo pipefail

SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
REPO_ROOT="$(dirname "$(dirname "$SCRIPT_PATH")")"
cd "$REPO_ROOT"

HOOK="$REPO_ROOT/.claude/hooks/guard-destructive-operations.sh"

TITLE="destructive-guard"
PASSED=0
FAILED=0

echo "========================================================================"
echo "VALIDATOR: $TITLE"
echo "========================================================================"

pass() { PASSED=$((PASSED + 1)); echo "PASS  $1"; }
fail() { FAILED=$((FAILED + 1)); echo "FAIL  $1"; }

finish() {
  echo "------------------------------------------------------------------------"
  local total=$((PASSED + FAILED))
  echo "SUMMARY [$TITLE]: $PASSED/$total checks passed, $FAILED failed"
  if [ "$FAILED" -ne 0 ]; then
    echo "RESULT: FAIL ($TITLE)"
    exit 1
  fi
  echo "RESULT: PASS ($TITLE)"
  exit 0
}

# --- existence / permissions -------------------------------------------------
if [ ! -f "$HOOK" ]; then
  fail "hook file exists: .claude/hooks/guard-destructive-operations.sh"
  finish
fi
pass "hook file exists: .claude/hooks/guard-destructive-operations.sh"

if [ -x "$HOOK" ]; then
  pass "hook file is executable"
else
  fail "hook file is executable (run: chmod 755 $HOOK)"
fi

# --- the hook's own self-test ------------------------------------------------
SELFTEST_OUT=""
SELFTEST_RC=0
SELFTEST_OUT="$("$HOOK" --self-test 2>&1)" || SELFTEST_RC=$?
if [ "$SELFTEST_RC" -eq 0 ]; then
  pass "hook --self-test exits 0"
else
  fail "hook --self-test exits 0 (got $SELFTEST_RC)"
  printf '%s\n' "$SELFTEST_OUT" | tail -30 | sed 's/^/      /'
fi

# --- independent behavioural assertions --------------------------------------
# The hook is a Claude Code PreToolUse hook: it reads a JSON event on stdin and
# exits 2 to BLOCK. Both the JSON form and a bare-argument form are attempted so
# the test does not depend on one calling convention.
probe() {
  # probe <command-string> -> prints the observed exit code
  local cmd="$1" rc=0
  local json
  json="$(printf '{"tool_name":"Bash","tool_input":{"command":%s}}' \
    "$(printf '%s' "$cmd" | sed 's/\\/\\\\/g; s/"/\\"/g; s/^/"/; s/$/"/')")"
  printf '%s' "$json" | "$HOOK" >/dev/null 2>&1 || rc=$?
  if [ "$rc" -eq 0 ]; then
    # Try the bare-argument convention before concluding "allowed".
    local rc2=0
    "$HOOK" "$cmd" >/dev/null 2>&1 || rc2=$?
    if [ "$rc2" -ne 0 ]; then rc=$rc2; fi
  fi
  printf '%s' "$rc"
}

DANGEROUS=(
  "rm -rf /"
  "rm -rf ~"
  "git push --force origin main"
  "git reset --hard HEAD~5"
  "git clean -fdx"
  "flutter create ."
  "composer create-project laravel/laravel backend"
  "chmod -R 777 /"
)

SAFE=(
  "git status"
  "ls -la"
  "python3 scripts/validate-required-files.py"
  "git log --oneline -n 5"
  "cat README.md"
)

for cmd in "${DANGEROUS[@]}"; do
  rc="$(probe "$cmd")"
  if [ "$rc" -eq 2 ]; then
    pass "blocked (exit 2) as expected: $cmd"
  else
    fail "expected block with exit 2, got exit $rc: $cmd"
  fi
done

for cmd in "${SAFE[@]}"; do
  rc="$(probe "$cmd")"
  if [ "$rc" -eq 0 ]; then
    pass "allowed (exit 0) as expected: $cmd"
  else
    fail "expected allow with exit 0, got exit $rc: $cmd"
  fi
done

finish
