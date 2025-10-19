#!/usr/bin/env bash
set -e

cd /var/www/html

# If using SQLite, ensure the file exists and is writable
if [ -n "${DB_CONNECTION}" ] && [ "${DB_CONNECTION}" = "sqlite" ]; then
  DB_PATH="${DB_DATABASE:-/var/www/html/storage/database/database.sqlite}"
  mkdir -p "$(dirname "$DB_PATH")"
  if [ ! -f "$DB_PATH" ]; then
    touch "$DB_PATH"
  fi
  chown -R www-data:www-data "$(dirname "$DB_PATH")"
  chmod 664 "$DB_PATH" || true
fi

# Generate key if missing
if [ -z "$APP_KEY" ]; then
  echo "No APP_KEY provided, generating..."
  php artisan key:generate --force
fi

# Optimize caches (safe without config cache during build)
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan optimize

# Optional: run migrations automatically in prod (remove if you prefer manual)
if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
  php artisan migrate --force || true
fi

exec "$@"
