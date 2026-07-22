#!/usr/bin/env bash
#
# Adversarial harness for scripts/validate-production-composition.py.
#
# A validator that has only ever run against a correct tree is an untested
# validator, and reporting it as a passing gate overstates the assurance it
# provides (Rule 47, Rule 33). This harness proves the guard FAILS on each way
# the defect has actually appeared, and on the ways it plausibly could.
#
# Every mutation is applied to a DISPOSABLE COPY of the tree, never to the
# working tree. That is deliberate: an earlier harness in this repository
# mutated in place, and its fixtures tripped a different guard so that mutations
# appeared caught when the mutation had in fact never run. Copying removes that
# whole class of false assurance, and the working tree is verified untouched at
# the end regardless.
set -uo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VALIDATOR="scripts/validate-production-composition.py"
SANDBOX="$(mktemp -d)"
trap 'rm -rf "$SANDBOX"' EXIT

BEFORE="$(cd "$REPO" && git status --porcelain | sha256sum)"

pass=0
fail=0

# Rebuild a pristine copy of everything the validator reads.
#
# Only sources are copied. `build/` and `.dart_tool/` are excluded deliberately:
# they hold release APKs and compiled artefacts worth tens of megabytes that the
# validator never reads, and copying them once exhausted the sandbox quota and
# made every mutation report a false failure.
reset_sandbox() {
  rm -rf "${SANDBOX:?}/repo"
  mkdir -p "$SANDBOX/repo"
  tar -C "$REPO" \
      --exclude='build' --exclude='.dart_tool' --exclude='__pycache__' \
      -cf - apps scripts | tar -C "$SANDBOX/repo" -xf -
}

# expect_fail <name> — the validator MUST exit non-zero after the mutation.
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

echo "========================================================================"
echo "ADVERSARIAL: production-composition guard"
echo "========================================================================"

# Control: the unmutated copy must PASS, otherwise every later result is noise.
reset_sandbox
if python3 "$SANDBOX/repo/$VALIDATOR" >/dev/null 2>&1; then
  echo "PASS  control: an unmutated tree passes"
  pass=$((pass + 1))
else
  echo "FAIL  control: an unmutated tree does NOT pass — results below are meaningless"
  fail=$((fail + 1))
fi

# 1. MasterDataRepository not wired — the defect found during Step 4.
reset_sandbox
python3 - "$SANDBOX/repo" <<'PY'
import pathlib, sys, re
p = pathlib.Path(sys.argv[1]) / 'apps/ops_android/lib/src/master_data/master_data_providers.dart'
s = p.read_text()
s = re.sub(r'\(ref\) => MasterDataRepository\(ref\.watch\(apiClientProvider\)\),',
           "(ref) => throw UnimplementedError('must be overridden'),", s)
p.write_text(s)
PY
expect_fail "MasterDataRepository is not wired (ops_android)"

# 2. Same defect on the web surface.
reset_sandbox
python3 - "$SANDBOX/repo" <<'PY'
import pathlib, sys, re
p = pathlib.Path(sys.argv[1]) / 'apps/admin_web/lib/src/master_data/master_data_screens.dart'
s = p.read_text()
s = re.sub(r'\(ref\) => MasterDataRepository\(ref\.watch\(apiClientProvider\)\),',
           "(ref) => throw UnimplementedError('must be overridden'),", s)
p.write_text(s)
PY
expect_fail "MasterDataRepository is not wired (admin_web)"

# 3. AuthService not wired — the original DEC-0032 defect.
reset_sandbox
python3 - "$SANDBOX/repo" <<'PY'
import pathlib, sys, re
p = pathlib.Path(sys.argv[1]) / 'apps/ops_android/lib/src/app.dart'
s = p.read_text()
s = re.sub(r'\(ref\) => ref\.watch\(authRuntimeProvider\)\.service,',
           "(ref) => throw UnimplementedError('must be overridden'),", s)
p.write_text(s)
PY
expect_fail "AuthService is not wired (ops_android)"

# 4. ApiClient missing — every repository would then have nothing to talk over.
reset_sandbox
python3 - "$SANDBOX/repo" <<'PY'
import pathlib, sys, re
p = pathlib.Path(sys.argv[1]) / 'apps/ops_android/lib/src/app.dart'
s = p.read_text()
s = re.sub(r'\(ref\) => ref\.watch\(authRuntimeProvider\)\.client,',
           "(ref) => throw StateError('no client configured'),", s)
p.write_text(s)
PY
expect_fail "ApiClient provider is missing (ops_android)"

# 5. Any OTHER critical screen dependency left test-only. This is the general
#    case: the guard must not be a hard-coded list of the two names already
#    known to have failed.
reset_sandbox
python3 - "$SANDBOX/repo" <<'PY'
import pathlib, sys
p = pathlib.Path(sys.argv[1]) / 'apps/ops_android/lib/src/master_data/master_data_providers.dart'
s = p.read_text()
s += """

/// A future screen dependency somebody wired only in tests.
final Provider<Object> counterPrinterProvider = Provider<Object>(
  (ref) => throw UnsupportedError('counterPrinterProvider must be overridden.'),
);
"""
p.write_text(s)
PY
expect_fail "an unrelated new dependency is left test-only"

# 5b. The SAME defect in the other idiomatic spelling: type inferred from the
#     initializer rather than annotated on the variable. The first version of the
#     validator was blind to this and passed it, found by independent review.
#     Mutation 5 proved generality over NAMES; this proves it over DECLARATION FORM.
reset_sandbox
python3 - "$SANDBOX/repo" <<'PY'
import pathlib, sys
p = pathlib.Path(sys.argv[1]) / 'apps/ops_android/lib/src/master_data/master_data_providers.dart'
s = p.read_text()
s += (
    "\n\nfinal counterPrinterProvider = Provider<Object>(\n"
    "  (ref) => throw UnimplementedError('counterPrinterProvider must be overridden.'),\n"
    ");\n"
)
p.write_text(s)
PY
expect_fail "a throwing provider declared with an INFERRED type"

# 6. The production entry point stops overriding a throwing provider — the
#    environment case, which is legitimate ONLY because main supplies it.
reset_sandbox
python3 - "$SANDBOX/repo" <<'PY'
import pathlib, sys
p = pathlib.Path(sys.argv[1]) / 'apps/ops_android/lib/main.dart'
s = p.read_text().replace('overrides: [environmentProvider.overrideWithValue(env)],', 'overrides: const [],')
p.write_text(s)
PY
expect_fail "main.dart stops overriding a throwing provider"

# The working tree must be exactly as it was found.
AFTER="$(cd "$REPO" && git status --porcelain | sha256sum)"
if [ "$BEFORE" = "$AFTER" ]; then
  echo "PASS  the working tree is unchanged by this harness"
  pass=$((pass + 1))
else
  echo "FAIL  the working tree was modified by this harness"
  fail=$((fail + 1))
fi

echo "------------------------------------------------------------------------"
echo "SUMMARY [production-composition-guard]: $pass/$((pass + fail)) expectations met, $fail failed"
if [ "$fail" -ne 0 ]; then
  echo "RESULT: FAIL (production-composition-guard)"
  exit 1
fi
echo "RESULT: PASS (production-composition-guard)"
