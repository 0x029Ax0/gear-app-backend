#!/bin/sh
set -eu

if [ "${GEAR_PROCESS:-web}" = "queue" ]; then
    exec php artisan queue:work database \
        --queue="${DB_QUEUE:-default}" \
        --sleep="${QUEUE_SLEEP:-3}" \
        --tries="${QUEUE_TRIES:-3}" \
        --timeout="${QUEUE_TIMEOUT:-90}" \
        --backoff="${QUEUE_BACKOFF:-5}" \
        --no-ansi
fi

exec apache2-foreground
