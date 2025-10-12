#!/usr/bin/env sh
set -e

cd /var/www/html

# Ensure .env exists
[ -f .env ] || cp .env.example .env

# Ensure Composer dependencies when vendor volume is empty
mkdir -p vendor
vendor_lock="vendor/.installing-dependencies"

cleanup_vendor_lock() {
  if [ -d "$vendor_lock" ]; then
    rmdir "$vendor_lock" 2>/dev/null || rm -rf "$vendor_lock"
  fi
}

acquire_vendor_dependencies() {
  # Always ensure we clean up the lock if we are the ones that created it
  trap cleanup_vendor_lock EXIT INT TERM

  while [ ! -f vendor/autoload.php ]; do
    if mkdir "$vendor_lock" 2>/dev/null; then
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

      cleanup_vendor_lock
      trap - EXIT INT TERM
      return 0
    fi

    if [ -d "$vendor_lock" ]; then
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

# Ensure APP_KEY
if ! grep -q "^APP_KEY=base64:" .env || grep -q "^APP_KEY=\s*$" .env; then
  php artisan key:generate --force || true
fi

# Idempotent migrations
php artisan migrate --force

exec "$@"

