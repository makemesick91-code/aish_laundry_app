#!/usr/bin/env bash
# THE canonical Step 0 verification entrypoint for Aish Laundry App.
#
# Runs every Step 0 gate, prints a summary table, and exits non-zero if ANY gate
# fails. Failures are never swallowed. Works from any current working directory:
# the repository root is resolved from this script's own location.
set -euo pipefail

SCRIPT_DIR="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$REPO_ROOT"

PY="${PYTHON:-python3}"

echo "########################################################################"
echo "# AISH LAUNDRY APP — STEP 0 VERIFICATION"
echo "# repo root : $REPO_ROOT"
echo "# python    : $($PY --version 2>&1)"
echo "# bash      : ${BASH_VERSION}"
echo "# started   : $(date -u '+%Y-%m-%dT%H:%M:%SZ')"
echo "########################################################################"

# gate-name:command
GATES=(
  "required-files:$PY $SCRIPT_DIR/validate-required-files.py"
  "master-source:$PY $SCRIPT_DIR/validate-master-source.py"
  "decisions:$PY $SCRIPT_DIR/validate-decisions.py"
  "roadmap:$PY $SCRIPT_DIR/validate-roadmap.py"
  "status:$PY $SCRIPT_DIR/validate-status.py"
  "pricing:$PY $SCRIPT_DIR/validate-pricing.py"
  "rules-traceability:$PY $SCRIPT_DIR/validate-rules-traceability.py"
  "no-runtime:$PY $SCRIPT_DIR/validate-no-runtime.py"
  "markdown-links:$PY $SCRIPT_DIR/validate-markdown-links.py"
  "secrets:bash $SCRIPT_DIR/validate-secrets.sh"
  "destructive-guard:bash $SCRIPT_DIR/test-destructive-guard.sh"
)

declare -a NAMES=()
declare -a RESULTS=()
declare -a CODES=()
OVERALL=0

for entry in "${GATES[@]}"; do
  name="${entry%%:*}"
  cmd="${entry#*:}"
  echo ""
  rc=0
  # shellcheck disable=SC2086
  $cmd || rc=$?
  NAMES+=("$name")
  CODES+=("$rc")
  if [ "$rc" -eq 0 ]; then
    RESULTS+=("PASS")
  else
    RESULTS+=("FAIL")
    OVERALL=1
  fi
done

echo ""
echo "########################################################################"
echo "# STEP 0 GATE SUMMARY"
echo "########################################################################"
printf '%-26s %-6s %s\n' "GATE" "RESULT" "EXIT"
printf '%-26s %-6s %s\n' "--------------------------" "------" "----"
for i in "${!NAMES[@]}"; do
  printf '%-26s %-6s %s\n' "${NAMES[$i]}" "${RESULTS[$i]}" "${CODES[$i]}"
done
echo "------------------------------------------------------------------------"

PASS_COUNT=0
for r in "${RESULTS[@]}"; do
  [ "$r" = "PASS" ] && PASS_COUNT=$((PASS_COUNT + 1))
done
echo "GATES PASSED: $PASS_COUNT / ${#NAMES[@]}"

if [ "$OVERALL" -ne 0 ]; then
  echo "STEP 0 VERIFICATION: FAIL"
  exit 1
fi
echo "STEP 0 VERIFICATION: PASS"
exit 0
