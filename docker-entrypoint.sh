#!/usr/bin/env sh
set -e

cd /var/www/html

# Ensure .env exists
[ -f .env ] || cp .env.example .env

# Ensure Composer dependencies when vendor volume is empty
if [ ! -f vendor/autoload.php ]; then
  if [ -d /opt/app-bootstrap/vendor ]; then
    echo "Populating vendor directory from image cache..."
    mkdir -p vendor
    if ! cp -a /opt/app-bootstrap/vendor/. vendor/; then
      echo "Unable to copy cached vendor directory, will run composer install instead."
    fi
  fi
fi

if [ ! -f vendor/autoload.php ]; then
  echo "Installing Composer dependencies..."
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
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

