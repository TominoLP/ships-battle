#!/bin/sh
set -e
cd /var/www/html
echo "[entrypoint] Booting Ships App..."

# Ensure SQLite exists
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  DB_PATH="${DB_DATABASE:-/var/www/html/storage/database/database.sqlite}"
  mkdir -p "$(dirname "$DB_PATH")"
  [ -f "$DB_PATH" ] || touch "$DB_PATH"
  chown www-data:www-data "$(dirname "$DB_PATH")" "$DB_PATH" 2>/dev/null || true
  chmod 664 "$DB_PATH" 2>/dev/null || true
fi

# Generate APP_KEY if missing
if [ -z "${APP_KEY}" ] || [ "${APP_KEY}" = "base64:" ]; then
  php artisan key:generate --force || true
fi

php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
  php artisan migrate --force || true
fi

echo "[entrypoint] Starting supervisor..."
exec "$@"