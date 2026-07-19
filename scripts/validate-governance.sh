#!/usr/bin/env bash
# Orchestrate the governance-document validators.
# Runs every validator even if an earlier one fails, then reports a summary and
# exits non-zero if ANY validator failed.
set -euo pipefail

SCRIPT_DIR="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$REPO_ROOT"

PY="${PYTHON:-python3}"

VALIDATORS=(
  "required-files:$PY $SCRIPT_DIR/validate-required-files.py"
  "master-source:$PY $SCRIPT_DIR/validate-master-source.py"
  "decisions:$PY $SCRIPT_DIR/validate-decisions.py"
  "roadmap:$PY $SCRIPT_DIR/validate-roadmap.py"
  "status:$PY $SCRIPT_DIR/validate-status.py"
  "pricing:$PY $SCRIPT_DIR/validate-pricing.py"
  "rules-traceability:$PY $SCRIPT_DIR/validate-rules-traceability.py"
)

declare -a NAMES=()
declare -a RESULTS=()
OVERALL=0

for entry in "${VALIDATORS[@]}"; do
  name="${entry%%:*}"
  cmd="${entry#*:}"
  echo ""
  rc=0
  # shellcheck disable=SC2086
  $cmd || rc=$?
  NAMES+=("$name")
  if [ "$rc" -eq 0 ]; then
    RESULTS+=("PASS")
  else
    RESULTS+=("FAIL")
    OVERALL=1
  fi
done

echo ""
echo "========================================================================"
echo "GOVERNANCE VALIDATION SUMMARY"
echo "========================================================================"
printf '%-28s %s\n' "GATE" "RESULT"
printf '%-28s %s\n' "----" "------"
for i in "${!NAMES[@]}"; do
  printf '%-28s %s\n' "${NAMES[$i]}" "${RESULTS[$i]}"
done
echo "------------------------------------------------------------------------"

if [ "$OVERALL" -ne 0 ]; then
  echo "GOVERNANCE RESULT: FAIL"
  exit 1
fi
echo "GOVERNANCE RESULT: PASS"
exit 0
