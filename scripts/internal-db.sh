#!/usr/bin/env sh
set -e

escape_identifier() {
  printf '%s' "$1" | sed 's/`/``/g'
}

escape_literal() {
  printf '%s' "$1" | sed "s/'/''/g"
}

start_internal_db() {
  local datadir="${DB_DATA_DIR:-/var/lib/mysql}"
  local run_dir="/run/mysqld"
  local port="${DB_PORT:-3306}"

  mkdir -p "$run_dir" "$datadir"
  chown -R mysql:mysql "$run_dir" "$datadir"

  if [ ! -d "$datadir/mysql" ]; then
    echo "Initializing internal MariaDB data directory..."
    mariadb-install-db --user=mysql --datadir="$datadir" --skip-test-db --auth-root-authentication-method=normal >/dev/null
  fi

  echo "Starting internal MariaDB server..."
  mariadbd --datadir="$datadir" --socket="$run_dir/mysqld.sock" --pid-file="$run_dir/mysqld.pid" --bind-address=127.0.0.1 --port="$port" --user=mysql &
  INTERNAL_DB_PID=$!

  echo "Waiting for internal MariaDB to accept connections..."
  for i in $(seq 1 60); do
    if mariadb-admin ping -h127.0.0.1 -P"$port" -uroot --socket="$run_dir/mysqld.sock" >/dev/null 2>&1; then
      break
    fi
    sleep 1
    if [ "$i" -eq 60 ]; then
      echo "Internal MariaDB failed to start within 60s" >&2
      exit 1
    fi
  done

  local db_name="${DB_DATABASE:-eventschedule}"
  local db_user="${DB_USERNAME:-eventschedule}"
  local db_pass="${DB_PASSWORD:-change_me}"

  local escaped_db_name="$(escape_identifier "$db_name")"
  local escaped_user="$(escape_literal "$db_user")"
  local escaped_pass="$(escape_literal "$db_pass")"

  cat <<SQL | mariadb -h127.0.0.1 -P"$port" -uroot --socket="$run_dir/mysqld.sock"
CREATE DATABASE IF NOT EXISTS \`$escaped_db_name\`;
CREATE USER IF NOT EXISTS '$escaped_user'@'%' IDENTIFIED BY '$escaped_pass';
ALTER USER '$escaped_user'@'%' IDENTIFIED BY '$escaped_pass';
GRANT ALL PRIVILEGES ON \`$escaped_db_name\`.* TO '$escaped_user'@'%';
FLUSH PRIVILEGES;
SQL
}
