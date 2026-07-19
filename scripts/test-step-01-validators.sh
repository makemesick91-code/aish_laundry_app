#!/usr/bin/env bash
# Adversarial test harness for the Step 1 validators.
#
# A validator that has never failed has never been verified. This harness copies
# the repository into a scratch directory, deliberately breaks one thing at a
# time, and asserts that the corresponding validator FAILS. If a validator passes
# a mutation it was supposed to catch, that validator is useless and this harness
# exits non-zero.
#
# The working tree is NEVER mutated. Every mutation happens inside a disposable
# copy under $TMPDIR, and the copy is removed on exit.
set -uo pipefail

SCRIPT_DIR="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"

PY="${PYTHON:-python3}"
SANDBOX="$(mktemp -d "${TMPDIR:-/tmp}/aish-step01-adversarial.XXXXXX")"
trap 'rm -rf "$SANDBOX"' EXIT

echo "========================================================================"
echo "ADVERSARIAL VALIDATOR TESTS — STEP 1"
echo "========================================================================"
echo "repo    : $REPO_ROOT"
echo "sandbox : $SANDBOX"
echo ""
echo "The working tree is not modified. All mutations occur in the sandbox copy."
echo ""

PASS=0
FAIL=0

# Rebuild a pristine copy of the material the validators read.
reset_sandbox() {
  rm -rf "$SANDBOX/work"
  mkdir -p "$SANDBOX/work"
  cp -r "$REPO_ROOT/scripts" "$SANDBOX/work/"
  cp -r "$REPO_ROOT/docs" "$SANDBOX/work/"
  cp -r "$REPO_ROOT/.claude" "$SANDBOX/work/" 2>/dev/null || true
  # git metadata is not copied; validators that shell out to git fall back to a
  # filesystem walk, which is the intended degraded path.
}

# expect_fail <label> <validator-relative-path> <mutation-command>
#
# Runs the mutation inside the sandbox, then asserts the validator exits
# non-zero. A zero exit means the validator did not catch the defect.
expect_fail() {
  local label="$1" validator="$2" mutation="$3"
  reset_sandbox
  ( cd "$SANDBOX/work" && eval "$mutation" ) >/dev/null 2>&1
  local rc=0
  ( cd "$SANDBOX/work" && eval "$validator" ) >/dev/null 2>&1 || rc=$?
  if [ "$rc" -ne 0 ]; then
    PASS=$((PASS + 1))
    printf 'PASS  caught: %s (exit %s)\n' "$label" "$rc"
  else
    FAIL=$((FAIL + 1))
    printf 'FAIL  NOT caught: %s (validator exited 0)\n' "$label"
  fi
}

# --- control: the unmutated sandbox copy must pass -------------------------
reset_sandbox
CONTROL_RC=0
( cd "$SANDBOX/work" && $PY scripts/validate-requirement-ids.py ) >/dev/null 2>&1 || CONTROL_RC=$?
if [ "$CONTROL_RC" -eq 0 ]; then
  PASS=$((PASS + 1))
  echo "PASS  control: unmutated sandbox copy passes requirement-ids"
else
  FAIL=$((FAIL + 1))
  echo "FAIL  control: unmutated sandbox copy should pass (exit $CONTROL_RC)"
fi

echo ""
echo "--- mutations ---"

# 1. Duplicate requirement ID.
expect_fail "duplicate requirement ID" \
  "$PY scripts/validate-requirement-ids.py" \
  "printf '\n| \`FR-001\` | duplicated on purpose |\n' >> docs/product/PRODUCT_REQUIREMENTS.md"

# 2. Pricing altered.
expect_fail "pricing figure altered (Rp79.000 -> Rp89.000)" \
  "$PY scripts/validate-pricing.py" \
  "grep -rl 'Rp79.000' docs | xargs -r sed -i 's/Rp79\.000/Rp89.000/g'"

# 3. H+7 stage removed from the reminder ladder.
expect_fail "H+7 reminder stage removed" \
  "$PY scripts/validate-unclaimed-laundry-rules.py" \
  "sed -i 's/H+7/H_SEVEN_REMOVED/g' docs/domain/UNCLAIMED_LAUNDRY_DOMAIN.md docs/state-machines/UNCLAIMED_LAUNDRY_STATE_MACHINE.md"

# 4. Tenant isolation rule removed.
expect_fail "tenant boundary document emptied" \
  "$PY scripts/validate-tenant-boundaries.py" \
  "printf '# Tenant Boundaries\n\nnothing here\n' > docs/domain/TENANT_BOUNDARIES.md"

# 5. Money changed to floating point.
expect_fail "integer Rupiah rule removed from the corpus" \
  "$PY scripts/validate-money-rules.py" \
  "grep -rl 'integer Rupiah' docs | xargs -r sed -i 's/integer Rupiah/floating point Rupiah/g'"

# 6. Tracking token becomes the order number.
expect_fail "tracking token described as the order number" \
  "$PY scripts/validate-tracking-rules.py" \
  "printf '\n\nThe token is the order number.\n' >> docs/domain/TRACKING_DOMAIN.md"

# 7. Order state machine loses a canonical status.
expect_fail "canonical order status removed (READY_FOR_PICKUP)" \
  "$PY scripts/validate-state-machines.py" \
  "sed -i 's/READY_FOR_PICKUP/READY_STATUS_REMOVED/g' docs/state-machines/ORDER_STATE_MACHINE.md"

# 8. A HIGH threat loses its mitigation.
expect_fail "CRITICAL/HIGH threat stripped of its mitigation" \
  "$PY scripts/validate-threat-model.py" \
  "sed -i 's/- \*\*Prevention:\*\*/- **Removed:**/g; s/- \*\*Detection:\*\*/- **Gone:**/g; s/[Mm]itigat/REDACTED/g; s/[Pp]revent/REDACTED/g; s/[Cc]ontrol/REDACTED/g' docs/security/INITIAL_THREAT_MODEL.md"

# 9. A runtime manifest appears.
expect_fail "pubspec.yaml added (Step 1 scope breach)" \
  "$PY scripts/validate-no-runtime.py" \
  "printf 'name: aish\n' > pubspec.yaml"

# --- credential-shaped fixtures -------------------------------------------
#
# These are assembled at RUNTIME from fragments rather than written literally.
# A literal credential-shaped string in this file would itself be a finding: the
# repository is public and its own secret scanners quite correctly flagged the
# earlier literal version. The fixtures below are meaningless fragments until
# concatenated inside the sandbox, so nothing key-shaped is ever committed.
AWS_FIXTURE="AKIA$(printf 'IOSFODNN7')EXAMPLE"
PHONE_FIXTURE="+62$(printf '81')2$(printf '5567')8891"

# 10. A secret fixture appears.
expect_fail "AWS access key committed" \
  "bash scripts/validate-public-repository-safety.sh" \
  "git init -q . 2>/dev/null; printf 'aws_key = \"%s\"\n' '$AWS_FIXTURE' > docs/product/leak.md"

# 11. A real-looking customer phone number appears.
expect_fail "real-looking Indonesian phone number committed" \
  "bash scripts/validate-public-repository-safety.sh" \
  "git init -q . 2>/dev/null; printf 'Customer: %s\n' '$PHONE_FIXTURE' > docs/product/contact.md"

# 12. The repository is described as private.
#
# Assembled at runtime for the same reason as the credential fixtures above: the
# literal sentence is itself the thing the scanner exists to find, so writing it
# out here would make this file a finding.
PRIVATE_CLAIM_FIXTURE="This repository $(printf 'is') $(printf 'private')."
expect_fail "repository described as private" \
  "bash scripts/validate-public-repository-safety.sh" \
  "git init -q . 2>/dev/null; printf '%s\n' '$PRIVATE_CLAIM_FIXTURE' > docs/product/visibility.md"

# 13. A later Step is marked IN PROGRESS.
expect_fail "Step 2 marked IN PROGRESS (forward scope leak)" \
  "$PY scripts/validate-status.py" \
  "sed -i 's/| Step 2 | Design System and UX Foundation | PLANNED |/| Step 2 | Design System and UX Foundation | IN PROGRESS |/' docs/STATUS.md"

# 14. A feature is marked IMPLEMENTED.
expect_fail "a feature marked IMPLEMENTED" \
  "$PY scripts/validate-status.py" \
  "sed -i '0,/| Payment, refund, and void | NOT IMPLEMENTED |/s//| Payment, refund, and void | IMPLEMENTED |/' docs/STATUS.md"

# 15. An unclosed code fence.
expect_fail "unclosed markdown code fence" \
  "$PY scripts/validate-mermaid-blocks.py" \
  "printf '\n\`\`\`mermaid\ngraph TD\n  A-->B\n' >> docs/domain/CONTEXT_MAP.md"

# 16. An acceptance criterion cites a requirement that does not exist.
expect_fail "acceptance criterion cites an undefined requirement" \
  "$PY scripts/validate-step-01-traceability.py" \
  "printf '\n- Requirements: \`FR-999\`\n' >> docs/quality/ACCEPTANCE_CRITERIA.md"

# 17. Automatic disposal proposed.
expect_fail "automatic disposal of laundry proposed" \
  "$PY scripts/validate-unclaimed-laundry-rules.py" \
  "printf '\n\nAfter 60 days the system will automatically sell the laundry to recover costs.\n' >> docs/domain/UNCLAIMED_LAUNDRY_DOMAIN.md"

# 18. Route optimization claimed.
expect_fail "route optimization claimed" \
  "$PY scripts/validate-delivery-rules.py" \
  "printf '\n\nThe system computes the optimal route for every courier.\n' >> docs/domain/PICKUP_DELIVERY_DOMAIN.md"

# 19. Master Source checksum forged.
expect_fail "Master Source checksum hand-edited" \
  "$PY scripts/validate-master-source.py" \
  "printf '0000000000000000000000000000000000000000000000000000000000000000  MASTER_SOURCE.md\n' > docs/MASTER_SOURCE.sha256"

# 20. Master Source version bumped in the header only.
#
# Version-agnostic on purpose: an earlier revision of this test hard-coded the
# then-current version, so when the document moved on the sed matched nothing,
# no mutation was applied, and the validator "failed to catch" a defect that was
# never introduced. A test that silently stops testing is worse than no test.
expect_fail "Master Source version bumped in header but not footer" \
  "$PY scripts/validate-master-source.py" \
  "sed -i -E '0,/^\*\*Document version: [0-9]+\.[0-9]+\.[0-9]+\*\*/s//**Document version: 9.9.9**/' docs/MASTER_SOURCE.md; grep -q 'Document version: 9.9.9' docs/MASTER_SOURCE.md || exit 1; (cd docs && sha256sum MASTER_SOURCE.md > MASTER_SOURCE.sha256)"

# 21. A physical database schema leaks into the conceptual domain model.
expect_fail "SQL CREATE TABLE leaked into the domain model" \
  "$PY scripts/validate-aggregates.py" \
  "printf '\n\nCREATE TABLE orders (id VARCHAR(36));\n' >> docs/domain/AGGREGATE_CATALOG.md"

# 22. A persona is removed.
expect_fail "a mandatory persona removed" \
  "$PY scripts/validate-personas.py" \
  "sed -i 's/External Local Courier/Removed Persona/g' docs/product/PERSONAS.md"

echo ""
echo "------------------------------------------------------------------------"
TOTAL=$((PASS + FAIL))
echo "ADVERSARIAL RESULTS: ${PASS}/${TOTAL} mutations correctly caught"
if [ "$FAIL" -ne 0 ]; then
  echo "RESULT: FAIL — at least one validator did not catch the defect it exists to catch."
  exit 1
fi
echo "RESULT: PASS — every mutation was caught by the validator responsible for it."
exit 0
