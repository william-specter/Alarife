#!/bin/sh
set -e

# Named volumes are created by Docker as root. Fix ownership once, then drop
# privileges for the actual application process.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache public 2>/dev/null || true
    exec su-exec www-data "$0" "$@"
fi

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    php artisan storage:link --force >/dev/null 2>&1 || true
    php artisan migrate --force
fi

if [ "${RUN_SEED:-0}" = "1" ]; then
    php artisan db:seed --force
fi

exec "$@"
