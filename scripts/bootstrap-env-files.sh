#!/usr/bin/env bash
#
# Aish Laundry App — local development environment file bootstrap.
#
# Creates the two IGNORED environment files a working local checkout needs,
# from their committed templates, and never overwrites either one.
#
#   .env          from  .env.example          (shared local infrastructure values)
#   backend/.env  from  backend/.env.example  (the file Laravel actually reads)
#
# Why this script exists (DEC-0027): the documented setup previously instructed
# only the ROOT copy. Laravel reads backend/.env, so a fresh clone followed
# exactly as documented produced no backend/.env at all and the backend could
# not authenticate to PostgreSQL. The defect was invisible on the maintainer's
# host because an ignored backend/.env already existed there — local success
# built on a pre-existing ignored file is not fresh-clone evidence.
#
# Safety properties:
#   - it NEVER overwrites an existing destination;
#   - it validates both templates BEFORE copying anything;
#   - it refuses to write through a symlink or outside the repository;
#   - it prints file STATUS only, never file CONTENT, and never DB_PASSWORD;
#   - it contacts no remote, staging, or production environment;
#   - it deletes nothing.
#
# Idempotent: running it twice is safe and the second run preserves both files.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

ok()   { printf '  \033[32mOK\033[0m    %s\n' "$*"; }
die()  { printf '  \033[31mFAIL\033[0m  %s\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# 1. Validate the templates BEFORE copying.
#
# Copying an invalid template just moves the defect into an ignored file where
# nothing will ever check it again. Fail closed instead.
# ---------------------------------------------------------------------------
if ! python3 "${REPO_ROOT}/scripts/validate-dev-environment-contract.py" --templates-only >/dev/null 2>&1; then
  printf '  \033[31mFAIL\033[0m  environment templates are invalid\n' >&2
  python3 "${REPO_ROOT}/scripts/validate-dev-environment-contract.py" --templates-only >&2 || true
  exit 1
fi
ok "environment templates validated against the canonical contract"

# ---------------------------------------------------------------------------
# 2. Copy each missing destination.
# ---------------------------------------------------------------------------
# install_env <template> <destination> <label>
install_env() {
  local template="$1" dest="$2" label="$3"

  [ -f "${REPO_ROOT}/${template}" ] || die "${label}: template missing — ${template}"

  # A symlink destination could redirect the write anywhere on the filesystem,
  # including outside the repository. Refuse rather than follow it.
  if [ -L "${REPO_ROOT}/${dest}" ]; then
    die "${label}: ${dest} is a symlink — refusing to write through it"
  fi

  if [ -e "${REPO_ROOT}/${dest}" ]; then
    printf '  %s:\n  PRESERVED — ALREADY EXISTS\n' "${label}"
    return 0
  fi

  # The destination must resolve inside the repository.
  local dest_dir resolved
  dest_dir="$(cd "$(dirname "${REPO_ROOT}/${dest}")" && pwd -P)"
  resolved="${dest_dir}/$(basename "${dest}")"
  case "${resolved}" in
    "$(cd "${REPO_ROOT}" && pwd -P)"/*) : ;;
    *) die "${label}: ${dest} resolves outside the repository — refusing" ;;
  esac

  cp "${REPO_ROOT}/${template}" "${resolved}"
  # Best effort only: an environment file should not be world-readable where the
  # platform supports it. No portability claim is made beyond that.
  chmod 600 "${resolved}" 2>/dev/null || true
  printf '  %s:\n  CREATED FROM CANONICAL EXAMPLE\n' "${label}"
}

echo
install_env ".env.example"         ".env"         "ROOT ENV"
install_env "backend/.env.example" "backend/.env" "BACKEND ENV"

echo
echo "  Both files are git-ignored and are never committed. The committed"
echo "  templates carry fictional, local-only values; production and staging"
echo "  credentials are managed separately and are out of scope here."
echo
echo "  Next: php artisan key:generate (run inside backend/) if APP_KEY is empty."
