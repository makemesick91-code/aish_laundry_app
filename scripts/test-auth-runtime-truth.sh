#!/usr/bin/env bash
#
# Adversarial harness for scripts/validate-auth-runtime-truth.py (SEC-01).
#
# A validator that has only ever run against a correct tree is an untested
# validator, and reporting it as a passing gate overstates the assurance it
# provides (Rule 47, Rule 33).
#
# EVERY MUTATION IS APPLIED TO A DISPOSABLE COPY. An earlier harness in this
# repository mutated in place, and its fixtures tripped a different guard so that
# mutations appeared caught when the mutation had never run. Copying removes that
# whole class of false assurance.
#
# EVERY MUTATION PROVES IT HAPPENED. Each fixture asserts the sandbox actually
# changed before running the validator. A mutation that silently failed to apply
# would otherwise produce a "caught" result from a tree nobody modified — which
# is exactly the defect DEC-0024's superseded 31/31 figure came from.
set -uo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VALIDATOR="scripts/validate-auth-runtime-truth.py"
SANDBOX="$(mktemp -d)"
trap 'rm -rf "$SANDBOX"' EXIT

BEFORE="$(cd "$REPO" && git status --porcelain | sha256sum)"

pass=0
fail=0

reset_sandbox() {
  rm -rf "${SANDBOX:?}/repo"
  mkdir -p "$SANDBOX/repo"
  tar -C "$REPO" \
      --exclude='build' --exclude='.dart_tool' --exclude='__pycache__' \
      -cf - apps packages scripts docs | tar -C "$SANDBOX/repo" -xf -
}

# assert_mutated <name> — the sandbox must differ from the pristine copy.
assert_mutated() {
  local name="$1" file="$2" marker="$3"
  if grep -qF "$marker" "$SANDBOX/repo/$file" 2>/dev/null; then
    return 0
  fi
  echo "FAIL  mutation DID NOT APPLY: $name (marker absent from $file)"
  fail=$((fail + 1))
  return 1
}

# assert_removed <name> — the marker must be GONE from the sandbox.
assert_removed() {
  local name="$1" file="$2" marker="$3"
  if [ -f "$SANDBOX/repo/$file" ] && grep -qF "$marker" "$SANDBOX/repo/$file"; then
    echo "FAIL  mutation DID NOT APPLY: $name (marker still present in $file)"
    fail=$((fail + 1))
    return 1
  fi
  return 0
}

expect_fail() {
  local name="$1"
  if python3 "$SANDBOX/repo/$VALIDATOR" >/dev/null 2>&1; then
    echo "FAIL  mutation NOT caught: $name"
    fail=$((fail + 1))
  else
    echo "PASS  mutation caught: $name"
    pass=$((pass + 1))
  fi
}

expect_pass() {
  local name="$1"
  if python3 "$SANDBOX/repo/$VALIDATOR" >/dev/null 2>&1; then
    echo "PASS  legitimate case accepted: $name"
    pass=$((pass + 1))
  else
    echo "FAIL  legitimate case REJECTED: $name"
    fail=$((fail + 1))
  fi
}

echo "========================================================================"
echo "ADVERSARIAL: auth-runtime-truth validator"
echo "========================================================================"

# Control. Without this every result below is noise.
reset_sandbox
expect_pass "control: the unmutated tree"

# 1. STALE CURRENT BLOCKER PROSE. The original SEC-01 defect: a sentence that
#    was true when written, left standing as a current claim.
reset_sandbox
python3 - "$SANDBOX/repo" <<'PY'
import pathlib, sys
p = pathlib.Path(sys.argv[1]) / 'docs/STATUS.md'
s = p.read_text()
s = s.replace(
    '#### What is true now',
    '#### What is true now\n\nThere is no concrete AuthService in the workspace.\n',
    1,
)
p.write_text(s)
PY
assert_mutated "stale blocker prose" "docs/STATUS.md" "no concrete AuthService in the workspace" \
  && expect_fail "stale CURRENT prose saying no concrete AuthService exists"

# 2. CURRENT STATUS FLIPPED TO ABSENT in the table.
reset_sandbox
python3 - "$SANDBOX/repo" <<'PY'
import pathlib, sys, re
p = pathlib.Path(sys.argv[1]) / 'docs/STATUS.md'
s = p.read_text()
s = re.sub(
    r'\| Client↔API end-to-end session \|[^|]*\|',
    '| Client↔API end-to-end session | ABSENT |',
    s,
    count=1,
)
p.write_text(s)
PY
assert_mutated "status flipped to ABSENT" "docs/STATUS.md" "end-to-end session | ABSENT" \
  && expect_fail "the current status table marks the session ABSENT"

# 3. PRODUCTION WIRING REMOVED while the prose still claims completion.
#    The opposite direction, and the one that bites if somebody unwires it.
reset_sandbox
python3 - "$SANDBOX/repo" <<'PY'
import pathlib, sys, re
root = pathlib.Path(sys.argv[1])
for app in ('ops_android', 'customer_android', 'admin_web'):
    for dart in (root / 'apps' / app / 'lib').rglob('*.dart'):
        text = dart.read_text()
        if 'authServiceProvider' in text and 'Provider<AuthService>' in text:
            dart.write_text(re.sub(
                r'(authServiceProvider\s*=\s*Provider<AuthService>\(\s*\n?\s*\(ref\)\s*=>\s*)',
                r"\1throw UnimplementedError('unwired'), // MUTATED\n      //",
                text,
                count=1,
            ))
PY
if grep -rqF "MUTATED" "$SANDBOX/repo/apps"; then
  expect_fail "production wiring removed while STATUS still claims completion"
else
  echo "FAIL  mutation DID NOT APPLY: production wiring removal"
  fail=$((fail + 1))
fi

# 4. DEC-0032 REMOVED. The decision that authorises the corrective claim.
reset_sandbox
rm -f "$SANDBOX"/repo/docs/decisions/DEC-0032-*.md
assert_removed "DEC-0032 removed" "docs/decisions" "DEC-0032" 2>/dev/null
if ls "$SANDBOX"/repo/docs/decisions/DEC-0032-*.md >/dev/null 2>&1; then
  echo "FAIL  mutation DID NOT APPLY: DEC-0032 removal"
  fail=$((fail + 1))
else
  expect_fail "DEC-0032 removed while STATUS claims the correction landed"
fi

# 5. DEC-0032 MADE NON-ACCEPTED. Present, but no longer authorising anything.
reset_sandbox
python3 - "$SANDBOX/repo" <<'PY'
import pathlib, sys
d = pathlib.Path(sys.argv[1]) / 'docs/decisions'
for path in d.glob('DEC-0032-*.md'):
    s = path.read_text()
    path.write_text(s.replace('**Status:** ACCEPTED', '**Status:** PROPOSED', 1))
PY
if grep -qF "**Status:** PROPOSED" "$SANDBOX"/repo/docs/decisions/DEC-0032-*.md; then
  expect_fail "DEC-0032 downgraded to PROPOSED"
else
  echo "FAIL  mutation DID NOT APPLY: DEC-0032 status downgrade"
  fail=$((fail + 1))
fi

# 6. THE HISTORICAL SECTION DELETED. Rule 01 requires the corrected claim to
#    say the earlier one was wrong; a correction with its history removed reads
#    as something that was always true.
reset_sandbox
python3 - "$SANDBOX/repo" <<'PY'
import pathlib, sys, re
p = pathlib.Path(sys.argv[1]) / 'docs/STATUS.md'
s = p.read_text()
s = re.sub(r'#### What was wrong \(historical, corrected\)', '#### Background', s, count=1)
p.write_text(s)
PY
assert_mutated "historical heading removed" "docs/STATUS.md" "#### Background" \
  && expect_fail "the historical account is no longer marked as history"

# --- LEGITIMATE CASES. A validator that fails these is unusable, because the
# --- repository would have to delete its own honest history to pass.
reset_sandbox
expect_pass "valid HISTORICAL wording (the defect described in the past tense)"

# 7. Valid current wording, REPHRASED. The validator must not depend on one
#    exact sentence, or every future edit becomes a governance incident.
reset_sandbox
python3 - "$SANDBOX/repo" <<'PY'
import pathlib, sys
p = pathlib.Path(sys.argv[1]) / 'docs/STATUS.md'
s = p.read_text()
s = s.replace(
    'all three canonical Flutter surfaces resolve a concrete',
    'every canonical Flutter surface now resolves a real, concrete',
    1,
)
p.write_text(s)
PY
assert_mutated "current wording rephrased" "docs/STATUS.md" "every canonical Flutter surface now resolves" \
  && expect_pass "valid CURRENT wording, rephrased"

AFTER="$(cd "$REPO" && git status --porcelain | sha256sum)"
if [ "$BEFORE" = "$AFTER" ]; then
  echo "PASS  the working tree is unchanged by this harness"
  pass=$((pass + 1))
else
  echo "FAIL  the working tree was modified by this harness"
  fail=$((fail + 1))
fi

echo "------------------------------------------------------------------------"
echo "SUMMARY [auth-runtime-truth]: $pass/$((pass + fail)) expectations met, $fail failed"
if [ "$fail" -ne 0 ]; then
  echo "RESULT: FAIL (auth-runtime-truth)"
  exit 1
fi
echo "RESULT: PASS (auth-runtime-truth)"
