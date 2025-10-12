#!/usr/bin/env sh
set -e

cd /var/www/html

# Ensure .env exists
[ -f .env ] || cp .env.example .env

# Ensure Composer dependencies when vendor volume is empty
mkdir -p vendor
vendor_lock="vendor/.installing-dependencies"
vendor_lock_timeout="${VENDOR_LOCK_TIMEOUT_SECONDS:-900}"

is_lock_stale() {
  [ ! -d "$vendor_lock" ] && return 1

  if [ -f "$vendor_lock/.timestamp" ]; then
    lock_created=$(cat "$vendor_lock/.timestamp" 2>/dev/null || printf '0')
  else
    lock_created=$(stat -c %Y "$vendor_lock" 2>/dev/null || printf '0')
  fi

  lock_created=${lock_created%%[^0-9]*}
  [ -z "$lock_created" ] && lock_created=0

  now=$(date +%s)
  [ "$lock_created" -eq 0 ] && return 0

  age=$((now - lock_created))
  [ "$age" -lt 0 ] && return 1

  [ "$age" -ge "$vendor_lock_timeout" ]
}

cleanup_vendor_lock() {
  if [ -d "$vendor_lock" ]; then
    rmdir "$vendor_lock" 2>/dev/null || rm -rf "$vendor_lock"
  fi
}

reset_vendor_directory() {
  # Remove any vendor contents that might have been left behind by a
  # previous failed install while keeping the lock directory in place.
  find vendor -mindepth 1 -maxdepth 1 ! -name "$(basename "$vendor_lock")" -exec rm -rf {} + 2>/dev/null || true
}

acquire_vendor_dependencies() {
  # Always ensure we clean up the lock if we are the ones that created it
  trap cleanup_vendor_lock EXIT INT TERM

  while [ ! -f vendor/autoload.php ]; do
    if mkdir "$vendor_lock" 2>/dev/null; then
      reset_vendor_directory
      date +%s >"$vendor_lock/.timestamp" 2>/dev/null || true
      if [ -d /opt/app-bootstrap/vendor ]; then
        echo "Populating vendor directory from image cache..."
        if ! cp -a /opt/app-bootstrap/vendor/. vendor/; then
          echo "Unable to copy cached vendor directory, will run composer install instead."
        fi
      fi

      if [ ! -f vendor/autoload.php ]; then
        echo "Installing Composer dependencies..."
        composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
      fi

      if [ -f vendor/autoload.php ]; then
        cleanup_vendor_lock
        trap - EXIT INT TERM
        return 0
      fi

      echo "Composer dependencies did not produce vendor/autoload.php. Resetting vendor directory and retrying..."
      reset_vendor_directory
      cleanup_vendor_lock
      sleep 2
      continue
    fi

    if [ -d "$vendor_lock" ]; then
      if is_lock_stale; then
        echo "Vendor dependency lock appears stale. Removing it so dependencies can be installed..."
        rm -rf "$vendor_lock"
        continue
      fi
      echo "Waiting for another container to finish installing Composer dependencies..."
    else
      echo "Unable to create vendor lock directory '$vendor_lock'. Check volume permissions." >&2
      exit 1
    fi

    sleep 2
  done

  trap - EXIT INT TERM
}

acquire_vendor_dependencies

if [ ! -f vendor/autoload.php ]; then
  cat >&2 <<'EOF'
ERROR: Unable to locate vendor/autoload.php after preparing Composer dependencies.
This usually means the bind-mounted vendor directory is not writable or the
dependency installation failed silently. Please verify the permissions of
./data/vendor on the host and rerun the container so dependencies can be
installed.
EOF
  exit 1
fi

# Wait for DB (best-effort)
if [ -n "$DB_HOST" ]; then
  echo "Waiting for DB at ${DB_HOST}:${DB_PORT:-3306}..."
  i=0
  while : ; do
    if php -r "try { new PDO('mysql:host=${DB_HOST};port=${DB_PORT:-3306}', '${DB_USERNAME:-eventschedule}', '${DB_PASSWORD:-change_me}'); } catch (Exception \$e) { exit(1); }"; then
      break
    fi
    i=$((i+1))
    if [ "$i" -ge 60 ]; then
      echo "DB wait timeout after 60s, continuing..."
      break
    fi
    sleep 1
  done
fi

# Ensure APP_KEY exists and is populated
ensure_app_key() {
  if ! grep -q "^APP_KEY=" .env; then
    printf '\nAPP_KEY=\n' >>.env
  fi

  current_key=$(grep "^APP_KEY=" .env | head -n1 | cut -d= -f2-)

  case "$current_key" in
    ""|"base64:")
      php artisan key:generate --force || true
      ;;
  esac
}

ensure_app_key

# Idempotent migrations
php artisan migrate --force

exec "$@"

