#!/usr/bin/env bash
#
# Stop LOCAL DEVELOPMENT PostgreSQL and Redis.
#
# Stops containers only. Data volumes are PRESERVED — destroying data is the job of
# scripts/reset-dev-data.sh, which requires explicit confirmation. A stop command
# that silently destroyed data would be a trap.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

COMPOSE_FILE="infrastructure/docker-compose.dev.yml"
[ -f "${COMPOSE_FILE}" ] || { echo "  FAIL  ${COMPOSE_FILE} not found" >&2; exit 1; }

docker compose -f "${COMPOSE_FILE}" down
echo
echo "  Development services stopped. Data volumes PRESERVED."
echo "  To destroy development data deliberately: bash scripts/reset-dev-data.sh --yes-destroy-development-data"
