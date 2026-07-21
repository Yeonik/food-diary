#!/bin/sh
set -e

# Writable directories a fresh storage volume would be missing.
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/private/photos \
    storage/logs \
    bootstrap/cache

# First run: seed configuration from the example and mint an app key — but only
# when the platform is NOT injecting configuration itself. Railway (and similar)
# pass APP_KEY and the rest as real environment variables, which Laravel reads
# directly; fabricating a .env there would drag in the example's APP_ENV=local /
# APP_DEBUG=true (a debug error page leaks the API keys) and mint a fresh APP_KEY
# on every deploy (churning every session). The presence of APP_KEY in the real
# environment is the signal that the platform owns configuration.
if [ ! -f .env ] && [ -z "$APP_KEY" ]; then
    cp .env.example .env
fi
if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# SQLite database lives on the persisted storage volume.
: "${DB_DATABASE:=/app/storage/app/food-diary.sqlite}"
export DB_DATABASE
touch "$DB_DATABASE"

php artisan migrate --force
php artisan config:cache

# Railway (and similar) assign the listening port via $PORT; fall back to 8000
# for local docker compose, which publishes a fixed port.
exec php artisan serve --host 0.0.0.0 --port "${PORT:-8000}"
