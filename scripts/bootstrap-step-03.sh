#!/usr/bin/env bash
#
# Aish Laundry App — Step 3 runtime bootstrap.
#
# Brings a FRESH CLONE to the pinned Step 3 toolchain, reproducibly, without
# mutating the host system destructively and without requiring sudo.
#
# This script is deliberately READ-MOSTLY and ADDITIVE:
#   - it verifies what is already present;
#   - it downloads the pinned Flutter SDK to a user-local path ONLY if absent;
#   - it verifies every download against a checksum published by the vendor;
#   - it NEVER deletes, overwrites, or downgrades an existing toolchain;
#   - it NEVER touches a production or remote environment.
#
# It fails closed: an unverifiable download is an error, not a warning.
#
# Pinned versions are defined in docs/runtime/TOOLCHAIN.md and enforced by
# scripts/validate-toolchain-locks.py. Changing a pin here without changing it
# there is drift, and the validator will reject it.

set -euo pipefail

# Resolve the repository root from this script's own location and work from
# there, so the script behaves identically however it was invoked. It previously
# relied on the caller's working directory being the repository root.
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

# ----------------------------------------------------------------------------
# Pinned toolchain (must agree with docs/runtime/TOOLCHAIN.md)
# ----------------------------------------------------------------------------
FLUTTER_VERSION="3.44.6"
DART_VERSION="3.12.2"
FLUTTER_SHA256="a6320fd72e9a2690c08e2a6a70874a30cb120dee7c78f49d2c628bd7c9e20525"
FLUTTER_ARCHIVE="flutter_linux_${FLUTTER_VERSION}-stable.tar.xz"
FLUTTER_URL="https://storage.googleapis.com/flutter_infra_release/releases/stable/linux/${FLUTTER_ARCHIVE}"
FLUTTER_HOME="${FLUTTER_HOME:-${HOME}/flutter}"

ANDROID_PLATFORM="36"
ANDROID_BUILD_TOOLS="28.0.3"

MIN_PHP="8.3"
PINNED_PHP="8.5.4"
PINNED_COMPOSER="2.10.1"
PINNED_POSTGRES="18.4"
PINNED_REDIS="8.2"

ok()   { printf '  \033[32mOK\033[0m    %s\n' "$*"; }
warn() { printf '  \033[33mWARN\033[0m  %s\n' "$*"; }
die()  { printf '  \033[31mFAIL\033[0m  %s\n' "$*" >&2; exit 1; }
hdr()  { printf '\n\033[1m== %s ==\033[0m\n' "$*"; }

FAILURES=0
note_fail() { printf '  \033[31mFAIL\033[0m  %s\n' "$*" >&2; FAILURES=$((FAILURES + 1)); }

# ----------------------------------------------------------------------------
hdr "Aish Laundry App — Step 3 runtime bootstrap"
echo "  Flutter ${FLUTTER_VERSION} / Dart ${DART_VERSION}"
echo "  Target: local development only. No remote environment is contacted."

# ----------------------------------------------------------------------------
hdr "1. Host prerequisites"

need_cmd() {
  if command -v "$1" >/dev/null 2>&1; then
    ok "$1 present — $("$1" --version 2>&1 | head -1)"
  else
    note_fail "$1 NOT FOUND — required for Step 3"
  fi
}

need_cmd php
need_cmd composer
need_cmd docker
need_cmd python3

if command -v php >/dev/null 2>&1; then
  php_ver="$(php -r 'echo PHP_VERSION;')"
  if [ "$(printf '%s\n%s\n' "$MIN_PHP" "$php_ver" | sort -V | head -1)" = "$MIN_PHP" ]; then
    ok "PHP ${php_ver} satisfies Laravel's php:^${MIN_PHP} (pinned ${PINNED_PHP})"
  else
    note_fail "PHP ${php_ver} is below the required ^${MIN_PHP}"
  fi
fi

if docker compose version >/dev/null 2>&1; then
  ok "docker compose present — $(docker compose version | head -1)"
else
  note_fail "docker compose plugin NOT FOUND"
fi

# ----------------------------------------------------------------------------
hdr "2. Flutter SDK (pinned ${FLUTTER_VERSION})"

install_flutter() {
  tmp="$(mktemp -d)"
  trap 'rm -rf "${tmp}"' RETURN
  echo "  downloading ${FLUTTER_ARCHIVE} ..."
  python3 - "$FLUTTER_URL" "${tmp}/${FLUTTER_ARCHIVE}" <<'PY'
import sys, urllib.request
urllib.request.urlretrieve(sys.argv[1], sys.argv[2])
PY
  actual="$(python3 - "${tmp}/${FLUTTER_ARCHIVE}" <<'PY'
import sys, hashlib
h = hashlib.sha256()
with open(sys.argv[1], 'rb') as fh:
    for chunk in iter(lambda: fh.read(1 << 20), b''):
        h.update(chunk)
print(h.hexdigest())
PY
)"
  if [ "${actual}" != "${FLUTTER_SHA256}" ]; then
    die "Flutter archive checksum MISMATCH
      expected ${FLUTTER_SHA256}
      actual   ${actual}
    Refusing to install an unverified SDK."
  fi
  ok "archive checksum verified against the official release manifest"
  mkdir -p "$(dirname "${FLUTTER_HOME}")"
  tar -xJf "${tmp}/${FLUTTER_ARCHIVE}" -C "$(dirname "${FLUTTER_HOME}")"
  ok "extracted to ${FLUTTER_HOME}"
}

if [ -d "${FLUTTER_HOME}" ]; then
  export PATH="${FLUTTER_HOME}/bin:${PATH}"
  have="$(flutter --version 2>/dev/null | head -1 | awk '{print $2}' || echo unknown)"
  if [ "${have}" = "${FLUTTER_VERSION}" ]; then
    ok "Flutter ${have} already installed at ${FLUTTER_HOME}"
  else
    warn "Flutter at ${FLUTTER_HOME} reports ${have}, pinned is ${FLUTTER_VERSION}"
    warn "NOT overwriting an existing SDK. Resolve deliberately, then re-run."
    FAILURES=$((FAILURES + 1))
  fi
else
  install_flutter
  export PATH="${FLUTTER_HOME}/bin:${PATH}"
fi

if command -v flutter >/dev/null 2>&1; then
  # Telemetry off by default: this is a governance repository and the toolchain
  # should not report usage to a third party as a side effect of bootstrapping.
  flutter --disable-analytics >/dev/null 2>&1 || true
  dart --disable-analytics    >/dev/null 2>&1 || true
  ok "Flutter and Dart analytics reporting disabled"
fi

# ----------------------------------------------------------------------------
hdr "3. Android SDK"

if [ -n "${ANDROID_HOME:-}" ]; then
  sdk_root="${ANDROID_HOME}"
elif [ -d "${HOME}/Android/Sdk" ]; then
  sdk_root="${HOME}/Android/Sdk"
else
  sdk_root=""
fi

if [ -z "${sdk_root}" ]; then
  warn "Android SDK not found — Android builds unavailable (Web still works)"
elif [ -d "${sdk_root}/platforms/android-${ANDROID_PLATFORM}" ]; then
  ok "Android platform ${ANDROID_PLATFORM} present"
else
  warn "Android platform ${ANDROID_PLATFORM} MISSING at ${sdk_root}"
  warn "Flutter ${FLUTTER_VERSION} requires Android SDK ${ANDROID_PLATFORM} + BuildTools ${ANDROID_BUILD_TOOLS}"
  warn "Install additively with:"
  warn "  sdkmanager 'platforms;android-${ANDROID_PLATFORM}' 'build-tools;${ANDROID_BUILD_TOOLS}'"
  warn "Until then, NO Android build result may be claimed."
fi

# ----------------------------------------------------------------------------
hdr "4. Development services (PostgreSQL ${PINNED_POSTGRES}, Redis ${PINNED_REDIS})"

compose_file="infrastructure/docker-compose.dev.yml"
if [ -f "${compose_file}" ]; then
  ok "${compose_file} present"
  echo "  start with:  bash scripts/start-dev-services.sh"
else
  warn "${compose_file} not yet present (introduced later in Step 3 Phase A)"
fi

# ----------------------------------------------------------------------------
hdr "5. Local environment files (DEC-0027)"

# Delegated to scripts/bootstrap-env-files.sh so the behaviour can be exercised
# in isolation by the adversarial harness without running the whole toolchain
# bootstrap. Both .env and backend/.env are created ONLY when absent; an existing
# file is always preserved.
#
# This step is why a fresh clone can now reach the database at all: Laravel reads
# backend/.env, and the documented path previously created only the root .env.
if bash "${REPO_ROOT:-.}/scripts/bootstrap-env-files.sh"; then
  ok "local environment files present (created where absent, never overwritten)"
else
  note_fail "environment bootstrap FAILED — run: bash scripts/bootstrap-env-files.sh"
fi

# ----------------------------------------------------------------------------
hdr "6. Toolchain lock verification"

if python3 scripts/validate-toolchain-locks.py >/dev/null 2>&1; then
  ok "toolchain locks consistent across all documented surfaces"
else
  note_fail "toolchain lock validation FAILED — run: python3 scripts/validate-toolchain-locks.py"
fi

# ----------------------------------------------------------------------------
hdr "Result"

if [ "${FAILURES}" -gt 0 ]; then
  printf '\033[31m  %d bootstrap check(s) FAILED.\033[0m\n' "${FAILURES}"
  echo "  The environment is NOT ready. Nothing may be claimed as verified."
  exit 1
fi

cat <<EOF
  Bootstrap checks passed.

  Add Flutter to your PATH for this shell:

      export PATH="${FLUTTER_HOME}/bin:\$PATH"

  This script verified the toolchain. It did NOT build, test, or deploy
  anything, and no build, test, or deployment result is claimed.
EOF
