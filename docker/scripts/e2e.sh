#!/bin/sh
# End-to-end test server. Runs wherever PHP and the code are (the dev "app"
# container locally, the runner in CI) against its own database, so a test run
# never touches development data.
#   reset   create the e2e database if needed, migrate fresh and seed it
#   serve   start the application on E2E_PORT (default 8001)
#   artisan run an Artisan command against the e2e database (used by specs)
set -eu

cd "$(dirname "$0")/../.."

# Values a test run must not share with development. Real environment
# variables win over .env, so exporting them here is enough.
export APP_ENV=e2e
export APP_DEBUG=true
export APP_URL="http://localhost:${E2E_PORT:-8001}"

# "reset" wipes the database: never let it point at the development one.
if [ "${E2E_DB_DATABASE:-app_e2e}" = "${DB_DATABASE:-}" ]; then
    echo "e2e: E2E_DB_DATABASE must differ from DB_DATABASE (${DB_DATABASE})" >&2
    exit 1
fi
export DB_DATABASE="${E2E_DB_DATABASE:-app_e2e}"
export QUEUE_CONNECTION=sync
export BROADCAST_CONNECTION="${E2E_BROADCAST_CONNECTION:-log}"
export CACHE_STORE=array
export SESSION_DRIVER=file
export SESSION_ENCRYPT=false
export MAIL_MAILER="${E2E_MAIL_MAILER:-smtp}"
export AI_ENABLED=false
export GITHUB_API_TOKEN=
export SECURITY_CSP_ENABLED=false
export LOG_CHANNEL=stderr
export LOG_LEVEL=warning
export PULSE_ENABLED=false
export TELESCOPE_ENABLED=false
export NIGHTWATCH_ENABLED=false

create_database() {
    [ "${DB_CONNECTION:-pgsql}" = "pgsql" ] || return 0

    php -r '
        $dsn = sprintf("pgsql:host=%s;port=%s;dbname=postgres",
            getenv("DB_HOST") ?: "postgres", getenv("DB_PORT") ?: "5432");
        $pdo = new PDO($dsn, getenv("DB_USERNAME") ?: "", getenv("DB_PASSWORD") ?: "");
        $name = getenv("DB_DATABASE");
        $exists = $pdo->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
        $exists->execute([$name]);
        if (! $exists->fetchColumn()) {
            $pdo->exec(sprintf("CREATE DATABASE \"%s\"", str_replace("\"", "", $name)));
        }
    '
}

case "${1:-}" in
    reset)
        create_database
        php artisan optimize:clear >/dev/null
        php artisan migrate:fresh --seed --seeder='Database\Seeders\E2eSeeder' --force --no-interaction
        ;;
    serve)
        # Several workers: Inertia pages fire parallel requests (polling, prefetch).
        export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
        exec php artisan serve --host=0.0.0.0 --port="${E2E_PORT:-8001}" --no-reload
        ;;
    artisan)
        shift
        exec php artisan "$@"
        ;;
    *)
        echo "usage: $0 reset|serve|artisan <command>" >&2
        exit 64
        ;;
esac
