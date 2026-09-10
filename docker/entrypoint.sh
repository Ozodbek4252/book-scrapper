#!/bin/sh
#
# Prepares the Laravel application, then hands over to the container command.
#
# Environment flags:
#   RUN_MIGRATIONS=true   run `artisan migrate --force` before starting (default: false)
#   APP_ENV=production    build the config/route/view caches on boot
#
set -e

APP_ROOT=/var/www/html
APP_USER=www-data

cd "$APP_ROOT"

artisan() {
    su-exec "$APP_USER" php artisan "$@"
}

log() {
    echo "[entrypoint] $*"
}

# Reads a setting from the real environment, falling back to .env and then to
# a default. Compose does not export .env into the container, because real
# environment variables would override phpunit.xml during `artisan test`.
setting() {
    _key="$1"
    _default="$2"
    _value=$(printenv "$_key" 2>/dev/null) || _value=""

    if [ -z "$_value" ] && [ -f .env ]; then
        _value=$(sed -n "s/^[[:space:]]*${_key}[[:space:]]*=[[:space:]]*//p" .env | head -n 1 | tr -d '\r' | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/")
    fi

    if [ -z "$_value" ]; then
        _value="$_default"
    fi

    printf '%s' "$_value"
}

# The dev image bind-mounts the source tree, which may arrive without vendor/.
if [ ! -f vendor/autoload.php ] && command -v composer >/dev/null 2>&1; then
    log "vendor/ is missing, installing composer dependencies"
    su-exec "$APP_USER" composer install --no-interaction --prefer-dist
fi

# Laravel expects these directories to exist and be writable.
mkdir -p \
    bootstrap/cache \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs
chown -R "$APP_USER:$APP_USER" storage bootstrap/cache

if [ -z "$(setting APP_KEY '')" ]; then
    if [ -f .env ]; then
        log "generating APP_KEY"
        artisan key:generate --force --no-interaction
    else
        log "WARNING: APP_KEY is not set and there is no .env file"
    fi
fi

# Compose already gates startup on the MySQL healthcheck, but this also covers
# `docker run` and a database that restarts under a running app.
wait_for_database() {
    _attempt=1
    _max=30

    while ! artisan db:show --quiet >/dev/null 2>&1; do
        if [ "$_attempt" -ge "$_max" ]; then
            log "database still unreachable after $_max attempts, giving up"
            return 1
        fi
        if [ "$_attempt" = 1 ]; then
            log "waiting for the database"
        fi
        _attempt=$((_attempt + 1))
        sleep 2
    done
}

if [ "$(setting DB_CONNECTION mysql)" != "sqlite" ]; then
    wait_for_database
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    log "running migrations"
    artisan migrate --force --no-interaction
fi

if [ "$(setting APP_ENV local)" = "production" ]; then
    log "caching config, routes, views and events"
    artisan package:discover --no-interaction
    artisan optimize --no-interaction
fi

exec "$@"
