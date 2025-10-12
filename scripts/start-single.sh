#!/usr/bin/env sh
set -e

# Default to sqlite when using the single-container stack unless explicitly disabled
if [ -z "$USE_SQLITE" ]; then
  export USE_SQLITE=1
fi

. /usr/local/bin/bootstrap.sh

bootstrap_app

exec /usr/bin/supervisord -c /etc/supervisord.conf
