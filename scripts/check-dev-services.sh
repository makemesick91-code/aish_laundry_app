#!/usr/bin/env bash
#
# PROVE that development PostgreSQL and Redis actually accept connections.
#
# "The container is running" is not connectivity. This script executes a real query
# and a real PING, and prints the engine versions it actually observed. Nothing here
# is claimed unless a command produced it.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

COMPOSE_FILE="infrastructure/docker-compose.dev.yml"

ok()  { printf '  \033[32mOK\033[0m    %s\n' "$*"; }
die() { printf '  \033[31mFAIL\033[0m  %s\n' "$*" >&2; exit 1; }

PG_USER="aish_dev"
PG_DB="aish_laundry_dev"

echo "== PostgreSQL =="
pg_cid="$(docker compose -f "${COMPOSE_FILE}" ps -q postgres || true)"
[ -n "${pg_cid}" ] || die "postgres container not running — run scripts/start-dev-services.sh"

version="$(docker exec "${pg_cid}" psql -U "${PG_USER}" -d "${PG_DB}" -tAc 'SHOW server_version;' 2>/dev/null)" \
  || die "PostgreSQL refused the connection"
ok "connection accepted"
ok "server_version = ${version}"

roundtrip="$(docker exec "${pg_cid}" psql -U "${PG_USER}" -d "${PG_DB}" -tAc 'SELECT 1+1;' 2>/dev/null)"
[ "${roundtrip}" = "2" ] || die "PostgreSQL query round-trip failed (got: ${roundtrip})"
ok "query round-trip returned ${roundtrip}"

echo "== Redis =="
rd_cid="$(docker compose -f "${COMPOSE_FILE}" ps -q redis || true)"
[ -n "${rd_cid}" ] || die "redis container not running — run scripts/start-dev-services.sh"

pong="$(docker exec "${rd_cid}" redis-cli PING 2>/dev/null || true)"
[ "${pong}" = "PONG" ] || die "Redis did not answer PING (got: ${pong})"
ok "PING answered ${pong}"

rversion="$(docker exec "${rd_cid}" redis-cli INFO server 2>/dev/null | sed -n 's/^redis_version:／*//p;s/^redis_version://p' | tr -d '\r' | head -1)"
ok "redis_version = ${rversion}"

# Prove a real write/read cycle, then clean up after ourselves.
docker exec "${rd_cid}" redis-cli SET aish:devcheck ok >/dev/null
val="$(docker exec "${rd_cid}" redis-cli GET aish:devcheck | tr -d '\r')"
[ "${val}" = "ok" ] || die "Redis write/read cycle failed (got: ${val})"
docker exec "${rd_cid}" redis-cli DEL aish:devcheck >/dev/null
ok "write/read/delete cycle succeeded"

echo
echo "Both development services verified by executed commands."
echo "This proves CONNECTIVITY only. No application, migration, or test was run here."
