#!/usr/bin/env bash
#
# Canonical Step 3 verifier.
#
# Runs the REAL gates. There is no placeholder success anywhere in this file: every
# line below either executes a command whose exit status decides the result, or is
# reported as SKIPPED with a visible reason. A verifier that prints PASS without
# running anything is worse than no verifier, because it launders an unverified
# claim into an evidence pack.
#
# Requires:
#   - Flutter/Dart on PATH        (export PATH="$HOME/flutter/bin:$PATH")
#   - PHP 8.5 + composer
#   - development PostgreSQL and Redis running
#       bash scripts/start-dev-services.sh
#
# Optional (checks are SKIPPED, never silently passed, when absent):
#   - actionlint + shellcheck     workflow lint
#   - Android SDK platform 36     Android builds
#
# Exit 0 = every executed gate passed and nothing required was skipped.

set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}" || exit 2

PASS=0; FAIL=0; SKIP=0
FAILED_GATES=()
SKIPPED_GATES=()

g()  { printf '\033[32m%s\033[0m' "$*"; }
r()  { printf '\033[31m%s\033[0m' "$*"; }
y()  { printf '\033[33m%s\033[0m' "$*"; }
hdr(){ printf '\n\033[1m== %s ==\033[0m\n' "$*"; }

# gate <label> <command...>
# Exit 78 (EX_CONFIG) is the repository-wide convention for "this gate's
# PRECONDITION is not met in this environment", as distinct from "this gate
# failed". It is reported as SKIP and NEVER as PASS: a gate that could not run
# has verified nothing, and the summary says so by name.
GATE_SKIP_RC=78

gate() {
  local label="$1"; shift
  local rc=0
  "$@" >/dev/null 2>&1 || rc=$?
  if [ "${rc}" -eq 0 ]; then
    printf '  %s  %s\n' "$(g 'PASS')" "${label}"; PASS=$((PASS + 1))
  elif [ "${rc}" -eq "${GATE_SKIP_RC}" ]; then
    skip "${label}" "precondition not met in this environment (exit ${GATE_SKIP_RC})"
  else
    printf '  %s  %s\n' "$(r 'FAIL')" "${label}"; FAIL=$((FAIL + 1)); FAILED_GATES+=("${label}")
  fi
}

# skip <label> <reason>
skip() {
  printf '  %s  %s — %s\n' "$(y 'SKIP')" "$1" "$2"
  SKIP=$((SKIP + 1)); SKIPPED_GATES+=("$1 ($2)")
}

SHA="$(git rev-parse HEAD)"
echo "========================================================================"
echo "STEP 3 CANONICAL VERIFICATION"
echo "========================================================================"
echo "  commit    : ${SHA}"
echo "  branch    : $(git rev-parse --abbrev-ref HEAD)"
echo "  timestamp : $(date -u '+%Y-%m-%dT%H:%M:%SZ') (UTC)"

# ---------------------------------------------------------------------------
hdr "1. Governance"
gate "Master Source checksum"            bash -c 'cd docs && sha256sum -c MASTER_SOURCE.sha256'
gate "Master Source version and structure" python3 scripts/validate-master-source.py
gate "canonical status (machine + human)" python3 scripts/validate-status.py
gate "decision records"                  python3 scripts/validate-decisions.py
gate "required files"                    python3 scripts/validate-required-files.py
gate "rules traceability"                python3 scripts/validate-rules-traceability.py
gate "roadmap"                           python3 scripts/validate-roadmap.py
gate "pricing"                           python3 scripts/validate-pricing.py
gate "markdown links"                    python3 scripts/validate-markdown-links.py
gate "DEC-0024 present and ACCEPTED"     bash -c 'grep -qE "^\*\*Status:\*\* ACCEPTED" docs/decisions/DEC-0024-*.md'
gate "DEC-0026 present and ACCEPTED"     bash -c 'grep -qE "^\*\*Status:\*\* ACCEPTED" docs/decisions/DEC-0026-*.md'
gate "rules 36-49 present"               bash -c 'test "$(ls .claude/rules/ | sed -n "s/^\(3[6-9]\|4[0-9]\)-.*/x/p" | wc -l)" -eq 14'

hdr "2. Step 0-2 regressions and scope"
gate "Step 0 regression"                 bash scripts/verify-step-00.sh
gate "Step 1 regression"                 bash scripts/verify-step-01.sh
gate "Step 2 regression"                 bash scripts/verify-step-02.sh
gate "runtime scope classification"      python3 scripts/validate-runtime-scope.py
gate "public repository safety"          bash scripts/validate-public-repository-safety.sh
gate "secret and credential scan"        bash scripts/validate-secrets.sh
gate "first-party relationship analysis" python3 scripts/analyze-step-03-relationships.py

hdr "3. Guards and adversarial harnesses"
gate "destructive guard syntax"          bash -n .claude/hooks/guard-destructive-operations.sh
gate "destructive guard self-test"       bash .claude/hooks/guard-destructive-operations.sh --self-test
gate "DEC-0026 scaffolding suite"        bash scripts/test-dec-0026-guard.sh
gate "runtime-scope harness"             bash scripts/test-step-03-validators.sh
gate "runtime CI validator"              python3 scripts/validate-runtime-ci.py
gate "runtime CI adversarial suite"      bash scripts/test-runtime-ci-validator.sh
gate "toolchain locks"                   python3 scripts/validate-toolchain-locks.py
gate "dev environment contract"          python3 scripts/validate-dev-environment-contract.py
gate "dev environment adversarial suite" bash scripts/test-dev-environment-contract.sh

hdr "4. Backend (authoritative PostgreSQL)"
if ! (cd backend && php artisan --version >/dev/null 2>&1); then
  skip "backend gates" "Laravel could not boot; is backend/.env present?"
else
  gate "Laravel boots"                   bash -c 'cd backend && php artisan --version'
  if bash scripts/check-dev-services.sh >/dev/null 2>&1; then
    gate "PostgreSQL and Redis reachable" bash scripts/check-dev-services.sh
    gate "migrate:fresh --seed"          bash -c 'cd backend && php artisan migrate:fresh --seed'
    gate "migrate:rollback"              bash -c 'cd backend && php artisan migrate:rollback'
    gate "migrate re-apply"              bash -c 'cd backend && php artisan migrate'
    gate "schema scope (no Step 4+ table)" bash -c 'cd backend && set -a && . ./.env && set +a && php scripts/ci/assert-schema-scope.php'
    gate "backend suite (all)"           bash -c 'cd backend && php artisan migrate:fresh --seed >/dev/null && php artisan test'
  else
    skip "database-backed gates" "development PostgreSQL/Redis not running (scripts/start-dev-services.sh)"
  fi
fi

hdr "5. Flutter"
if ! command -v flutter >/dev/null 2>&1; then
  skip "Flutter gates" "flutter not on PATH (export PATH=\"\$HOME/flutter/bin:\$PATH\")"
else
  gate "Flutter version matches the pin"  bash -c '[ "$(flutter --version | head -1 | awk "{print \$2}")" = "3.44.6" ]'
  gate "Dart version matches the pin"     bash -c 'dart --version 2>&1 | grep -q "3\.12\.2"'
  gate "dart format"                      dart format --output=none --set-exit-if-changed .
  gate "flutter analyze"                  flutter analyze
  gate "design token determinism"         python3 scripts/generate-design-tokens.py --check
  gate "pure-Dart package boundary"       bash -c '! grep -rql "package:flutter/" packages/core/lib packages/domain/lib'
  gate "application identifiers"          bash -c 'grep -q "id.aishtech.laundry.customer" apps/customer_android/android/app/build.gradle.kts && grep -q "id.aishtech.laundry.ops" apps/ops_android/android/app/build.gradle.kts'
  gate "no example namespace in source"   bash -c '! grep -rq "com\.example" apps/ packages/ --exclude-dir=build --exclude-dir=.dart_tool --exclude-dir=.gradle'
  gate "no signing material committed"    bash -c '! git ls-files | grep -qE "\.(jks|keystore)$|key\.properties"'
  gate "Flutter tests (all suites)"       bash -c 'f=0; for d in packages/* apps/*; do [ -d "$d/test" ] || continue; ( cd "$d" && flutter test >/dev/null 2>&1 ) || f=$((f+1)); done; [ "$f" -eq 0 ]'

  if [ -d "${ANDROID_SDK_ROOT:-${ANDROID_HOME:-/nonexistent}}/platforms/android-36" ]; then
    gate "Customer Android debug build"   bash -c 'cd apps/customer_android && flutter build apk --debug'
    gate "Ops Android debug build"        bash -c 'cd apps/ops_android && flutter build apk --debug'
  else
    skip "Android builds" "Android SDK platform 36 not installed"
  fi
  gate "Admin Web release build"          bash -c 'cd apps/admin_web && flutter build web --release'
  if [ -d apps/admin_web/build/web ]; then
    gate "Web output security scan"       python3 scripts/scan-web-build.py apps/admin_web/build/web
  else
    skip "Web output security scan" "no web build present"
  fi
fi

hdr "6. CI workflow hygiene"
if command -v actionlint >/dev/null 2>&1; then
  if command -v shellcheck >/dev/null 2>&1; then
    # actionlint takes FILES, not a directory: passing the directory produced
    # "is a directory" and a failed gate that had nothing to do with the
    # workflows themselves.
    mapfile -t _wf < <(find .github/workflows -maxdepth 1 -name '*.yml' | sort)
    gate "actionlint + shellcheck"        actionlint "${_wf[@]}"
  else
    skip "actionlint shell checks" "shellcheck absent — actionlint SILENTLY skips them"
  fi
else
  skip "workflow lint" "actionlint not installed"
fi
gate "no build artefact is committable"  bash -c '! git ls-files --others --exclude-standard | grep -qE "\.apk$|/build/|\.dart_tool"'
gate "working tree clean"                bash -c '[ "$(git status --porcelain | wc -l)" -eq 0 ]'

# ---------------------------------------------------------------------------
echo
# Stable, greppable summary for the DEC-0027 environment contract. Derived from
# the gate results above, never printed unconditionally: a summary that says PASS
# whatever happened is exactly the placeholder success this verifier refuses.
env_state="PASS"; boot_state="PASS"
for f in ${FAILED_GATES[@]+"${FAILED_GATES[@]}"}; do
  case "${f}" in
    "dev environment contract")          env_state="FAIL"; boot_state="FAIL" ;;
    "dev environment adversarial suite") env_state="FAIL"; boot_state="FAIL" ;;
  esac
done
echo "DEV ENVIRONMENT CONTRACT:"
echo "${env_state}"
echo
echo "BOOTSTRAP ENVIRONMENT PATH:"
echo "${boot_state}"

echo
echo "========================================================================"
printf 'STEP 3 VERIFICATION: %d passed, %d failed, %d skipped\n' "${PASS}" "${FAIL}" "${SKIP}"
if [ "${#FAILED_GATES[@]}" -gt 0 ]; then
  echo "FAILED:"
  for f in "${FAILED_GATES[@]}"; do echo "  - ${f}"; done
fi
if [ "${#SKIPPED_GATES[@]}" -gt 0 ]; then
  echo "SKIPPED (NOT verified — do not report these as passing):"
  for s in "${SKIPPED_GATES[@]}"; do echo "  - ${s}"; done
fi
echo "  commit: ${SHA}"
echo "========================================================================"
[ "${FAIL}" -eq 0 ] || exit 1
[ "${SKIP}" -eq 0 ] || exit 2
