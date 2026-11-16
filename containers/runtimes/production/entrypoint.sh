#!/usr/bin/env bash
set -euo pipefail

# Renumber the "docker" user to the host user's PUID/PGID, then drop to it.
# The image is built with 1000:1000; this lets one prebuilt image write
# correctly owned files on hosts where the user isn't 1000.
if [ "$(id -u)" = '0' ]; then
    PUID=${PUID:-1000}
    PGID=${PGID:-1000}

    if [ "$(id -g docker)" != "${PGID}" ]; then
        groupmod -o -g "${PGID}" docker
    fi

    if [ "$(id -u docker)" != "${PUID}" ]; then
        usermod -o -u "${PUID}" docker
    fi

    for dir in /app/storage /app/bootstrap/cache; do
        if [ -d "${dir}" ] && [ "$(stat -c '%u:%g' "${dir}")" != "${PUID}:${PGID}" ]; then
            chown -R docker:docker "${dir}"
        fi
    done

    exec gosu docker "$0" "$@"
fi

APP_COMMAND=${APP_COMMAND:-'/usr/bin/bash'}

log() {
    echo "[$1] $2"
}

if [ ! -f "/app/.env" ]; then
    log "ERROR" "Missing /app/.env. Pods reads it from the pods-env secret; install.sh creates it."
    exit 1
fi

if ! grep -q '^APP_KEY=.' /app/.env; then
    log "ERROR" "APP_KEY is missing from /app/.env. Add it to the pods-env secret."
    exit 1
fi

# The storage volume starts as a copy of the image's storage folder, but
# make sure the folders Laravel and Pods write to exist.
mkdir -p /app/storage/app/private/ssh /app/storage/framework/cache/data \
    /app/storage/framework/sessions /app/storage/framework/views /app/storage/logs

# The database lives on the storage volume (DB_DATABASE in .env).
DATABASE="$(grep -E '^DB_DATABASE=' /app/.env | tail -n 1 | cut -d= -f2- | tr -d '"')"

if [ -n "${DATABASE}" ] && [ ! -f "${DATABASE}" ]; then
    log "INFO" "Creating the SQLite database..."
    touch "${DATABASE}"
fi

if [ "${APP_OPTIMIZE:-true}" = "true" ]; then
    log "INFO" "Optimizing..."
    ${PHP_CLI} optimize
fi

# Only the web server migrates, before it starts. The scheduler shares this
# image and entrypoint.
if [[ "${APP_COMMAND}" == *octane:frankenphp* ]]; then
    log "INFO" "Migrating the database..."
    ${PHP_CLI} migrate --force --no-interaction
    ${PHP_CLI} db:seed --force --no-interaction --class='Database\Seeders\PermissionSeeder'
fi

log "INFO" "Starting..."
exec ${APP_COMMAND}
