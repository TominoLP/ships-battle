#!/bin/sh
set -e
cd /var/www/html
echo "[entrypoint] Booting Ships App..."

# --- NEW: kill dev-mode artifacts ---
rm -f public/hot || true
[ -f .env ] && sed -i '/^VITE_DEV_SERVER_URL=/d' .env || true

# --- Ensure runtime dirs (works even with storage volume) ---
mkdir -p \
  storage/framework/sessions \
  storage/framework/views \
  storage/framework/cache \
  storage/logs \
  storage/database \
  bootstrap/cache

# perms
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
[ -f storage/logs/laravel.log ] || : > storage/logs/laravel.log
chown www-data:www-data storage/logs/laravel.log 2>/dev/null || true

# --- SQLite bootstrap (if used) ---
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
  DB_PATH="${DB_DATABASE:-/var/www/html/storage/database/database.sqlite}"
  mkdir -p "$(dirname "$DB_PATH")"
  [ -f "$DB_PATH" ] || : > "$DB_PATH"
  chown www-data:www-data "$(dirname "$DB_PATH")" "$DB_PATH" 2>/dev/null || true
  chmod 664 "$DB_PATH" 2>/dev/null || true
fi

# --- NEW: require built Vite assets (fail fast) ---
if [ ! -f "public/build/manifest.json" ]; then
  echo "[entrypoint] ✘ No Vite manifest at public/build/manifest.json. Build assets in the image stage."
  exit 1
fi

# --- APP_KEY: only generate if missing AND .env is writable ---
if [ -z "${APP_KEY}" ] || [ "${APP_KEY}" = "base64:" ]; then
  if [ "${SKIP_KEY_GENERATE:-0}" = "1" ]; then
    echo "[entrypoint] SKIP_KEY_GENERATE=1 and APP_KEY missing — skipping."
  elif [ -w .env ]; then
    php artisan key:generate --force || true
  else
    echo "[entrypoint] WARNING: APP_KEY missing and .env is read-only; cannot write key."
  fi
else
  echo "[entrypoint] APP_KEY present."
fi

# --- Optional: Force HTTPS URLs (your proxy handles TLS) ---
if [ "${APP_FORCE_HTTPS:-false}" = "true" ]; then
  echo "[entrypoint] Forcing HTTPS URLs..."
  # Typically handled via TrustProxies; nothing to do here.
fi

# --- ensure storage symlink exists ---
php artisan storage:link || true

# --- Clear then cache (fresh, prod-friendly) ---
php artisan optimize:clear || true
php artisan config:cache   || true
php artisan route:cache    || true
php artisan view:cache     || true
php artisan event:cache    || true

# --- DB migrations (optional) ---
if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
  php artisan migrate --force || true
fi

echo "[entrypoint] Starting supervisor..."
exec "$@"
