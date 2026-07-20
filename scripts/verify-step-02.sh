#!/usr/bin/env bash
# THE canonical Step 2 verification entrypoint for Aish Laundry App.
#
# Runs every Step 0 and Step 1 governance gate that remains in force, plus
# every Step 2 gate, prints a summary table, and exits non-zero if ANY gate
# fails. Failures are never swallowed and never downgraded to warnings.
#
# Step 2 is documentation only. Nothing here builds, deploys, or tests an
# application, because no application exists. There are no unit, widget,
# integration, or end-to-end tests, and none may be claimed. Application CI is
# NOT APPLICABLE.
set -uo pipefail

SCRIPT_DIR="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
cd "$REPO_ROOT"

PY="${PYTHON:-python3}"

echo "########################################################################"
echo "# AISH LAUNDRY APP — STEP 2 VERIFICATION"
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
  "runtime-scope:$PY $SCRIPT_DIR/validate-runtime-scope.py"
  "markdown-links:$PY $SCRIPT_DIR/validate-markdown-links.py"
  "secrets:bash $SCRIPT_DIR/validate-secrets.sh"
  "destructive-guard:bash $SCRIPT_DIR/test-destructive-guard.sh"

  # --- Step 1 product gates, still in force ---
  "product-requirements:$PY $SCRIPT_DIR/validate-product-requirements.py"
  "requirement-ids:$PY $SCRIPT_DIR/validate-requirement-ids.py"
  "personas:$PY $SCRIPT_DIR/validate-personas.py"
  "use-cases:$PY $SCRIPT_DIR/validate-use-cases.py"

  # --- Step 1 domain gates, still in force ---
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

  # --- Step 1 security and quality gates, still in force ---
  "threat-model:$PY $SCRIPT_DIR/validate-threat-model.py"
  "data-classification:$PY $SCRIPT_DIR/validate-data-classification.py"
  "acceptance-criteria:$PY $SCRIPT_DIR/validate-acceptance-criteria.py"
  "step-01-traceability:$PY $SCRIPT_DIR/validate-step-01-traceability.py"

  # --- Step 2 design system gates ---
  "design-required-files:$PY $SCRIPT_DIR/validate-design-required-files.py"
  "design-tokens:$PY $SCRIPT_DIR/validate-design-tokens.py"
  "token-references:$PY $SCRIPT_DIR/validate-token-references.py"
  "colour-contrast:$PY $SCRIPT_DIR/validate-color-contrast.py"
  "typography:$PY $SCRIPT_DIR/validate-typography.py"
  "breakpoints:$PY $SCRIPT_DIR/validate-breakpoints.py"
  "component-catalog:$PY $SCRIPT_DIR/validate-component-catalog.py"
  "component-states:$PY $SCRIPT_DIR/validate-component-states.py"

  # --- Step 2 UX foundation gates ---
  "screen-inventory:$PY $SCRIPT_DIR/validate-screen-inventory.py"
  "journeys:$PY $SCRIPT_DIR/validate-journeys.py"
  "navigation:$PY $SCRIPT_DIR/validate-navigation.py"
  "ux-states:$PY $SCRIPT_DIR/validate-ux-states.py"
  "content-glossary:$PY $SCRIPT_DIR/validate-content-glossary.py"
  "wireframes:$PY $SCRIPT_DIR/validate-wireframes.py"

  # --- Step 2 accessibility, privacy, and traceability gates ---
  "accessibility:$PY $SCRIPT_DIR/validate-accessibility.py"
  "privacy-ux:$PY $SCRIPT_DIR/validate-privacy-ux.py"
  "design-threat-review:$PY $SCRIPT_DIR/validate-design-threat-review.py"
  "ux-classification:$PY $SCRIPT_DIR/validate-ux-requirement-classification.py"
  "design-traceability:$PY $SCRIPT_DIR/validate-design-traceability.py"
  "step-02-rules:$PY $SCRIPT_DIR/validate-step-02-rules.py"

  # --- Step 2 structural and safety gates ---
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

# ---------------------------------------------------------------------------
# Tag immutability. Only checked when a remote is reachable; a missing remote
# makes this UNVERIFIED, never a silent pass.
# ---------------------------------------------------------------------------
echo ""
echo "========================================================================"
echo "VALIDATOR: released tag immutability"
echo "========================================================================"
TAG_RC=0
STEP00_TAG="aish-laundry-step-00-master-source-governance-v1.0.0-go"
STEP00_COMMIT="8494bc8543b9301351da6055337832597f1f2d9f"
STEP01_TAG="aish-laundry-step-01-product-requirement-domain-model-v1.2.0-go"
STEP01_COMMIT="4eadbc73f8bacdc9cd2acfcc62280ac932116089"

check_tag() {
  local tag="$1" expected="$2"
  local kind actual
  kind="$(git cat-file -t "$tag" 2>/dev/null || echo missing)"
  if [ "$kind" != "tag" ]; then
    echo "FAIL  $tag is an annotated tag (found: $kind)"
    return 1
  fi
  actual="$(git rev-parse "${tag}^{}" 2>/dev/null || echo unknown)"
  if [ "$actual" != "$expected" ]; then
    echo "FAIL  $tag still points at $expected (found: $actual)"
    return 1
  fi
  echo "PASS  $tag is annotated and unmoved ($expected)"
  return 0
}

if git rev-parse --git-dir >/dev/null 2>&1; then
  check_tag "$STEP00_TAG" "$STEP00_COMMIT" || TAG_RC=1
  check_tag "$STEP01_TAG" "$STEP01_COMMIT" || TAG_RC=1
else
  echo "      UNVERIFIED — not a git repository; tag immutability not checked"
  TAG_RC=1
fi

NAMES+=("tag-immutability")
CODES+=("$TAG_RC")
if [ "$TAG_RC" -eq 0 ]; then
  RESULTS+=("PASS")
else
  RESULTS+=("FAIL")
  OVERALL=1
fi

echo ""
echo "########################################################################"
echo "# STEP 2 GATE SUMMARY"
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
echo "Scope note: Step 2 is documentation only. These are governance validators."
echo "There are no application unit, widget, integration, or end-to-end tests,"
echo "because no application exists. Application CI is NOT APPLICABLE."
echo "A passing gate proves a document satisfies a rule. It never proves a"
echo "feature works, a screen renders, or an accessibility criterion was met."

if [ "$OVERALL" -ne 0 ]; then
  echo "STEP 2 VERIFICATION: FAIL"
  exit 1
fi
echo "STEP 2 VERIFICATION: PASS"
exit 0
