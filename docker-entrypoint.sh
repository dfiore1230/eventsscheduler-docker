#!/usr/bin/env sh
set -e

if [ "${INTERNAL_DB:-0}" = "1" ]; then
  . /usr/local/bin/internal-db.sh
  start_internal_db
  export DB_HOST="${DB_HOST:-127.0.0.1}"
  trap 'if [ -n "${INTERNAL_DB_PID:-}" ]; then kill "${INTERNAL_DB_PID}" >/dev/null 2>&1 || true; wait "${INTERNAL_DB_PID}" 2>/dev/null || true; fi' EXIT
fi

. /usr/local/bin/bootstrap.sh

bootstrap_app

exec "$@"
