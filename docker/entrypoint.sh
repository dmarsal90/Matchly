#!/bin/sh
# One image, many processes: the first argument selects the role.
#   web        nginx + php-fpm (production image), runs migrations
#   fpm        php-fpm only (development), runs migrations
#   horizon    queue workers supervised by Horizon
#   worker     plain queue:work (when Horizon is not wanted)
#   scheduler  Laravel scheduler (schedule:work)
#   reverb     websocket server
#   vite       Vite dev server with HMR (development)
# Anything else is executed as-is (e.g. "php artisan tinker", "sh").
set -eu

role="${1:-web}"
echo "$role" > /tmp/container-role

cd /var/www/html

log() {
    printf '[entrypoint:%s] %s\n' "$role" "$*"
}

is_local() {
    [ "${APP_ENV:-production}" = "local" ]
}

ensure_storage() {
    mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
}

ensure_dependencies() {
    if is_local && [ ! -f vendor/autoload.php ]; then
        log "vendor/ missing, running composer install"
        composer install --no-interaction --prefer-dist
    fi
}

wait_for_database() {
    [ "${DB_CONNECTION:-pgsql}" = "sqlite" ] && return 0

    log "waiting for ${DB_CONNECTION:-pgsql} at ${DB_HOST:-postgres}:${DB_PORT:-5432}"
    php -r '
        $dsn = sprintf("%s:host=%s;port=%s;dbname=%s",
            getenv("DB_CONNECTION") ?: "pgsql", getenv("DB_HOST") ?: "postgres",
            getenv("DB_PORT") ?: "5432", getenv("DB_DATABASE") ?: "app");
        for ($i = 1; $i <= 60; $i++) {
            try { new PDO($dsn, getenv("DB_USERNAME") ?: "", getenv("DB_PASSWORD") ?: ""); exit(0); }
            catch (Throwable $e) { fwrite(STDERR, "  attempt $i: {$e->getMessage()}\n"); sleep(2); }
        }
        exit(1);
    '
}

optimize() {
    if is_local; then
        php artisan optimize:clear >/dev/null
    else
        # config, routes, views and events
        php artisan optimize
    fi
}

migrate() {
    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        php artisan migrate --force --isolated
    fi
    php artisan storage:link >/dev/null 2>&1 || true
}

ensure_storage

case "$role" in
    web)
        wait_for_database
        migrate
        optimize
        exec supervisord -c /etc/supervisor/supervisord.conf
        ;;
    fpm)
        ensure_dependencies
        wait_for_database
        migrate
        optimize
        exec php-fpm --nodaemonize
        ;;
    horizon)
        wait_for_database
        optimize
        exec php artisan horizon
        ;;
    worker)
        wait_for_database
        optimize
        exec php artisan queue:work --queue="${WORKER_QUEUES:-high,notifications,default,ingestion,ai,matching,maintenance}" \
            --tries=3 --backoff=30 --max-time=3600 --sleep=3
        ;;
    scheduler)
        wait_for_database
        optimize
        exec php artisan schedule:work
        ;;
    reverb)
        optimize
        exec php artisan reverb:start --host="${REVERB_SERVER_HOST:-0.0.0.0}" --port="${REVERB_SERVER_PORT:-8080}"
        ;;
    vite)
        if [ ! -d node_modules ]; then
            log "node_modules/ missing, running npm ci"
            npm ci --no-audit --no-fund
        fi
        exec npm run dev
        ;;
    *)
        exec "$@"
        ;;
esac
