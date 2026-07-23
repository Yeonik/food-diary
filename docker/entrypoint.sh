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

# Back up the database before migrating it.
#
# This entrypoint runs `migrate --force` unattended on every deploy, and the
# database file is the only copy of the diary. Laravel does not wrap SQLite
# migrations in a transaction (only the Postgres and SQL Server grammars declare
# schema transactions), so a migration that fails halfway leaves a partly changed
# schema that `migrate:rollback` cannot honestly undo — and changing a column or
# an index on SQLite is a full table rebuild, not an in-place edit. For a file
# database the one dependable undo is the file.
#
# The copy is written beside the database — `dirname "$DB_DATABASE"` — so it
# lands on whatever volume holds the database itself, whatever that volume is
# mounted as. Never into the image's own filesystem, which a redeploy discards.
# It is under storage/, which is not the document root, so it is not reachable
# over HTTP.
backup_database() {
    backup_dir="$(dirname "$DB_DATABASE")/backups"
    mkdir -p "$backup_dir"

    # One backup per day, and the first one of the day wins. If a migration
    # fails and the platform restarts the container, the retry must not copy the
    # half-migrated database over the good copy taken minutes earlier.
    backup="$backup_dir/$(basename "$DB_DATABASE").$(date -u +%Y-%m-%d)"

    if [ -e "$backup" ]; then
        echo "Pre-migration backup for today already exists, keeping it: $backup"
        return
    fi

    # Copy to a temporary name and rename into place, so an interrupted copy
    # cannot be mistaken later for a complete backup.
    cp "$DB_DATABASE" "$backup.part"
    mv "$backup.part" "$backup"
    echo "Database backed up before migrating: $backup"

    # A week of history. Small for a personal diary; the volume holds photos and
    # sessions too, so this does not grow without a bound. Rotation is a tidy-up,
    # not a safeguard — it must never be the reason a deploy fails.
    ls -1t "$backup_dir/$(basename "$DB_DATABASE")".????-??-?? 2>/dev/null \
        | tail -n +8 | xargs -r rm -f || true
}

# Nothing to protect on the very first deploy: `touch` above just created the
# file. Otherwise back up only when there is actually something to migrate, so
# an ordinary restart costs nothing. `migrate:status --pending=1` exits non-zero
# when migrations are pending; it also exits non-zero if it cannot read the
# database at all, and backing up in that case is the safe direction.
if [ -s "$DB_DATABASE" ]; then
    if php artisan migrate:status --pending=1 >/dev/null 2>&1; then
        echo "No pending migrations, skipping the pre-migration backup."
    else
        backup_database
    fi
fi

php artisan migrate --force
php artisan config:cache

# Railway (and similar) assign the listening port via $PORT; fall back to 8000
# for local docker compose, which publishes a fixed port.
exec php artisan serve --host 0.0.0.0 --port "${PORT:-8000}"
