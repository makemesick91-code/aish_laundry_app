#!/usr/bin/env bash
# THE canonical Step 1 verification entrypoint for Aish Laundry App.
#
# Runs every Step 0 governance gate that remains in force, plus every Step 1
# gate, prints a summary table, and exits non-zero if ANY gate fails. Failures
# are never swallowed and never downgraded to warnings.
#
# Step 1 is documentation only. Nothing here builds, deploys, or tests an
# application, because no application exists. Application CI is NOT APPLICABLE.
set -uo pipefail

SCRIPT_DIR="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$REPO_ROOT"

PY="${PYTHON:-python3}"

echo "########################################################################"
echo "# AISH LAUNDRY APP — STEP 1 VERIFICATION"
echo "# repo root : $REPO_ROOT"
echo "# python    : $($PY --version 2>&1)"
echo "# bash      : ${BASH_VERSION}"
echo "# git sha   : $(git rev-parse HEAD 2>/dev/null || echo 'unavailable')"
echo "# branch    : $(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo 'unavailable')"
echo "# started   : $(date -u '+%Y-%m-%dT%H:%M:%SZ')"
echo "########################################################################"

# gate-name:command
GATES=(
  # --- Step 0 governance gates, still in force ---
  "required-files:$PY $SCRIPT_DIR/validate-required-files.py"
  "master-source:$PY $SCRIPT_DIR/validate-master-source.py"
  "decisions:$PY $SCRIPT_DIR/validate-decisions.py"
  "roadmap:$PY $SCRIPT_DIR/validate-roadmap.py"
  "status:$PY $SCRIPT_DIR/validate-status.py"
  "pricing:$PY $SCRIPT_DIR/validate-pricing.py"
  "rules-traceability:$PY $SCRIPT_DIR/validate-rules-traceability.py"
  # DEC-0024: run against CURRENT main this suite checks runtime SCOPE, not
  # absence. Absence for this step remains proven against its immutable GO tag
  # by the `classify` job, which executes validate-no-runtime.py on the tagged
  # tree itself. validate-no-runtime.py is retained unchanged for that purpose.
  "runtime-scope:$PY $SCRIPT_DIR/validate-runtime-scope.py"
  "markdown-links:$PY $SCRIPT_DIR/validate-markdown-links.py"
  "secrets:bash $SCRIPT_DIR/validate-secrets.sh"
  "destructive-guard:bash $SCRIPT_DIR/test-destructive-guard.sh"

  # --- Step 1 product gates ---
  "product-requirements:$PY $SCRIPT_DIR/validate-product-requirements.py"
  "requirement-ids:$PY $SCRIPT_DIR/validate-requirement-ids.py"
  "personas:$PY $SCRIPT_DIR/validate-personas.py"
  "use-cases:$PY $SCRIPT_DIR/validate-use-cases.py"

  # --- Step 1 domain gates ---
  "domain-glossary:$PY $SCRIPT_DIR/validate-domain-glossary.py"
  "bounded-contexts:$PY $SCRIPT_DIR/validate-bounded-contexts.py"
  "aggregates:$PY $SCRIPT_DIR/validate-aggregates.py"
  "domain-invariants:$PY $SCRIPT_DIR/validate-domain-invariants.py"
  "domain-events:$PY $SCRIPT_DIR/validate-domain-events.py"
  "state-machines:$PY $SCRIPT_DIR/validate-state-machines.py"
  "tenant-boundaries:$PY $SCRIPT_DIR/validate-tenant-boundaries.py"
  "money-rules:$PY $SCRIPT_DIR/validate-money-rules.py"
  "tracking-rules:$PY $SCRIPT_DIR/validate-tracking-rules.py"
  "delivery-rules:$PY $SCRIPT_DIR/validate-delivery-rules.py"
  "unclaimed-laundry:$PY $SCRIPT_DIR/validate-unclaimed-laundry-rules.py"

  # --- Step 1 security and quality gates ---
  "threat-model:$PY $SCRIPT_DIR/validate-threat-model.py"
  "data-classification:$PY $SCRIPT_DIR/validate-data-classification.py"
  "acceptance-criteria:$PY $SCRIPT_DIR/validate-acceptance-criteria.py"
  "step-01-traceability:$PY $SCRIPT_DIR/validate-step-01-traceability.py"

  # --- Step 1 structural and safety gates ---
  "mermaid-blocks:$PY $SCRIPT_DIR/validate-mermaid-blocks.py"
  "public-repo-safety:bash $SCRIPT_DIR/validate-public-repository-safety.sh"
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
echo "# STEP 1 GATE SUMMARY"
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
echo ""
echo "Scope note: Step 1 is documentation only. These are governance validators."
echo "There are no application unit, widget, integration, or end-to-end tests,"
echo "because no application exists. Application CI is NOT APPLICABLE."

if [ "$OVERALL" -ne 0 ]; then
  echo "STEP 1 VERIFICATION: FAIL"
  exit 1
fi
echo "STEP 1 VERIFICATION: PASS"
exit 0
