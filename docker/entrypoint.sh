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

# First run: seed configuration from the example and mint an app key.
if [ ! -f .env ]; then
    cp .env.example .env
fi
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# SQLite database lives on the persisted storage volume.
: "${DB_DATABASE:=/app/storage/app/food-diary.sqlite}"
export DB_DATABASE
touch "$DB_DATABASE"

php artisan migrate --force
php artisan config:cache

exec php artisan serve --host 0.0.0.0 --port 8000
