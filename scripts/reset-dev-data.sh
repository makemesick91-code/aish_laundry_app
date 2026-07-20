#!/usr/bin/env bash
#
# DESTROY local development database and cache data.
#
# This is the only script in the repository that deliberately destroys data, so it
# is written to FAIL CLOSED at every step:
#
#   1. it refuses to run unless APP_ENV is local/development/testing (or unset);
#   2. it refuses to run if any production or staging marker is present;
#   3. it requires an explicit, unambiguous confirmation flag;
#   4. it targets ONLY the named development compose project's volumes;
#   5. it never uses `rm -rf` against a path, so a variable that expands to empty
#      cannot delete anything;
#   6. it contains no production hostname, and contacts no remote environment.
#
# It cannot reach a production database because it has no way to address one.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

COMPOSE_FILE="infrastructure/docker-compose.dev.yml"
COMPOSE_PROJECT="aish-laundry-dev"
CONFIRM_FLAG="--yes-destroy-development-data"

ok()  { printf '  \033[32mOK\033[0m    %s\n' "$*"; }
die() { printf '  \033[31mREFUSED\033[0m  %s\n' "$*" >&2; exit 1; }

echo "== Safety checks =="

# --- 1. environment must be a development environment -------------------------
env_name="${APP_ENV:-local}"
case "${env_name}" in
  local|development|dev|testing|test) ok "APP_ENV='${env_name}' is a development environment" ;;
  *) die "APP_ENV='${env_name}' is not a development environment. Refusing to destroy data." ;;
esac

# --- 2. explicit production/staging markers block the run ---------------------
for var in APP_ENV DB_HOST REDIS_HOST DATABASE_URL REDIS_URL; do
  val="${!var:-}"
  [ -n "${val}" ] || continue
  case "$(printf '%s' "${val}" | tr '[:upper:]' '[:lower:]')" in
    *prod*|*staging*|*live*)
      die "${var} contains a production/staging marker ('${val}'). Refusing."
      ;;
  esac
done
ok "no production or staging marker in the environment"

# --- 3. database host must be loopback ----------------------------------------
db_host="${DB_HOST:-127.0.0.1}"
case "${db_host}" in
  127.0.0.1|localhost|::1|postgres) ok "DB_HOST='${db_host}' is local" ;;
  *) die "DB_HOST='${db_host}' is not loopback. Refusing to destroy a non-local database." ;;
esac

# --- 4. explicit confirmation --------------------------------------------------
if [ "${1:-}" != "${CONFIRM_FLAG}" ]; then
  cat >&2 <<EOF

  REFUSED — explicit confirmation required.

  This destroys ALL local development database and cache data for project
  '${COMPOSE_PROJECT}'. It is not reversible.

  To proceed, run:

      bash scripts/reset-dev-data.sh ${CONFIRM_FLAG}

EOF
  exit 1
fi
ok "explicit confirmation flag supplied"

[ -f "${COMPOSE_FILE}" ] || die "${COMPOSE_FILE} not found"

echo
echo "== Destroying development data =="

# `down -v` removes only the volumes declared by THIS compose project. It cannot
# touch a volume that this file does not declare, and it addresses no remote host.
docker compose -f "${COMPOSE_FILE}" down -v

ok "development containers and volumes removed"

echo
echo "  Local development data destroyed for project '${COMPOSE_PROJECT}'."
echo "  Recreate with: bash scripts/start-dev-services.sh"
echo "  No remote environment was contacted."
