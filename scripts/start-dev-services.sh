#!/usr/bin/env bash
#
# Start LOCAL DEVELOPMENT PostgreSQL and Redis, and wait until both are genuinely
# accepting connections — not merely "container created".
#
# Development only. Contacts no remote environment.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

COMPOSE_FILE="infrastructure/docker-compose.dev.yml"

ok()  { printf '  \033[32mOK\033[0m    %s\n' "$*"; }
die() { printf '  \033[31mFAIL\033[0m  %s\n' "$*" >&2; exit 1; }

[ -f "${COMPOSE_FILE}" ] || die "${COMPOSE_FILE} not found"

command -v docker >/dev/null 2>&1 || die "docker not found"
docker compose version >/dev/null 2>&1 || die "docker compose plugin not found"

echo "== Validating compose configuration =="
docker compose -f "${COMPOSE_FILE}" config >/dev/null || die "compose config is invalid"
ok "compose configuration valid"

echo "== Starting development services =="
docker compose -f "${COMPOSE_FILE}" up -d

echo "== Waiting for health =="
deadline=$(( SECONDS + 120 ))
for svc in postgres redis; do
  cid="$(docker compose -f "${COMPOSE_FILE}" ps -q "${svc}")"
  [ -n "${cid}" ] || die "${svc} container was not created"
  while :; do
    state="$(docker inspect -f '{{.State.Health.Status}}' "${cid}" 2>/dev/null || echo unknown)"
    case "${state}" in
      healthy) ok "${svc} healthy"; break ;;
      unhealthy) die "${svc} reported unhealthy" ;;
    esac
    [ "${SECONDS}" -lt "${deadline}" ] || die "${svc} did not become healthy in time (last state: ${state})"
    sleep 2
  done
done

echo
echo "Development services are up. Verify connectivity with:"
echo "    bash scripts/check-dev-services.sh"
echo
echo "These are LOCAL DEVELOPMENT services bound to 127.0.0.1 with fake credentials."
echo "No production or staging environment was contacted."
