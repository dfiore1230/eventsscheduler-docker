#!/usr/bin/env sh
set -e

bootstrap_app() {
  local orig_dir
  orig_dir=$(pwd)
  cd /var/www/html

  mkdir -p storage bootstrap/cache database

  # Ensure .env exists
  if [ ! -f .env ]; then
    cp .env.example .env
  fi

  if [ "${USE_SQLITE:-0}" = "1" ]; then
    # Ensure sqlite database exists and configure .env accordingly
    if [ ! -f database/database.sqlite ]; then
      touch database/database.sqlite
    fi

    php -r '
      $path = ".env";
      $env = file_get_contents($path);
      $set = function($key, $value) use (&$env) {
        $pattern = "/^{$key}=.*/m";
        if (preg_match($pattern, $env)) {
          $env = preg_replace($pattern, "{$key}={$value}", $env, 1);
        } else {
          $env .= "\n{$key}={$value}";
        }
      };
      $set("DB_CONNECTION", "sqlite");
      $set("DB_DATABASE", "database/database.sqlite");
      file_put_contents($path, $env);
    ' || true
  fi

  if [ "${USE_SQLITE:-0}" != "1" ] && [ -n "$DB_HOST" ]; then
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

  # Clear any cached configuration so new environment changes (like switching
  # to SQLite) take effect before running artisan commands.
  php artisan config:clear || true

  if ! grep -q "^APP_KEY=base64:" .env || grep -q "^APP_KEY=\s*$" .env; then
    php artisan key:generate --force || true
  fi

  php artisan migrate --force

  php artisan storage:link || true

  chown -R www-data:www-data storage bootstrap/cache database || true
  cd "$orig_dir"
}
