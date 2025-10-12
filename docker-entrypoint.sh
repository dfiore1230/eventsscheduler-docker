#!/usr/bin/env sh
set -e

. /usr/local/bin/bootstrap.sh

bootstrap_app

exec "$@"
