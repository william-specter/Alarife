#!/bin/sh
set -e

# Named volumes are created by Docker as root. Fix ownership once, then drop
# privileges for the actual application process.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache public /data /config 2>/dev/null || true
    exec gosu www-data "$0" "$@"
fi

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    php artisan storage:link --force >/dev/null 2>&1 || true

    attempt=1
    until php artisan migrate --force; do
        if [ "$attempt" -ge 30 ]; then
            echo "Database did not become available in time." >&2
            exit 1
        fi
        attempt=$((attempt + 1))
        sleep 2
    done
fi

if [ "${RUN_SEED:-0}" = "1" ]; then
    php artisan db:seed --force
fi

exec "$@"
