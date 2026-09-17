#!/bin/sh
set -eu

PORT="${PORT:-8000}"
export PORT

if [ -n "${RENDER_EXTERNAL_URL:-}" ] && [ -z "${APP_URL:-}" ]; then
  export APP_URL="$RENDER_EXTERNAL_URL"
fi

if [ -n "${DATABASE_URL:-}" ] && [ -z "${DB_URL:-}" ]; then
  export DB_URL="$DATABASE_URL"
fi

if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY is required. On Render, add it under Environment."
  echo "Generate one locally with: php artisan key:generate --show"
  exit 1
fi

printf 'Listen %s\n' "$PORT" > /etc/apache2/ports.conf
sed "s/__PORT__/${PORT}/g" /etc/apache2/sites-available/000-default.conf.template \
  > /etc/apache2/sites-available/000-default.conf

mkdir -p \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  storage/app/public \
  bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force
fi

if [ "${APP_ENV:-production}" != "local" ]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

exec apache2-foreground
