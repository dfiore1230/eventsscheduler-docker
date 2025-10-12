#!/usr/bin/env sh
set -e

bootstrap_app() {
  local orig_dir
  orig_dir=$(pwd)
  cd /var/www/html

  mkdir -p \
    storage \
    storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache \
    database

  if [ ! -f storage/gradients.json ] && [ -f .docker/storage-seeds/gradients.json ]; then
    cp .docker/storage-seeds/gradients.json storage/gradients.json
  fi

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
    db_ready=0
    while : ; do
      if php -r "try { new PDO('mysql:host=${DB_HOST};port=${DB_PORT:-3306}', '${DB_USERNAME:-eventschedule}', '${DB_PASSWORD:-change_me}'); } catch (Exception \$e) { exit(1); }"; then
        db_ready=1
        break
      fi
      i=$((i+1))
      if [ "$i" -ge 60 ]; then
        break
      fi
      sleep 1
    done

    if [ "$db_ready" -ne 1 ]; then
      echo "Failed to connect to the database after 60s." >&2
      if command -v getent >/dev/null 2>&1 && ! getent hosts "$DB_HOST" >/dev/null 2>&1; then
        echo "The hostname '${DB_HOST}' could not be resolved. Ensure DB_HOST points to your database service or container." >&2
      else
        echo "The hostname '${DB_HOST}' resolves, but the service is unreachable. Verify the database container is running and accepting connections." >&2
      fi
      exit 1
    fi
  fi

  if [ "${USE_SQLITE:-0}" != "1" ]; then
    php -r '
      $path = ".env";
      if (!file_exists($path)) {
        return;
      }

      $env = file_get_contents($path);
      $set = function($key, $value) use (&$env) {
        $pattern = "/^" . preg_quote($key, "/") . "=.*/m";
        $escapedValue = addcslashes($value, "\\\\\n\r");
        if (preg_match($pattern, $env)) {
          $env = preg_replace($pattern, $key . "=" . $escapedValue, $env, 1);
        } else {
          $env .= "\n" . $key . "=" . $escapedValue;
        }
      };

      foreach (["DB_CONNECTION", "DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"] as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== "") {
          $set($key, $value);
        }
      }

      file_put_contents($path, $env);
    ' || true
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
