#!/bin/sh
# Host side of the e2e server: runs `e2e.sh serve` in a one-off container of
# the dev stack and removes it when Playwright stops the server.
set -eu

cd "$(dirname "$0")/../.."

port="${E2E_PORT:-8001}"
name="${COMPOSE_PROJECT_NAME:-jobs-platform}-e2e-server"

cleanup() {
    docker rm -f "$name" >/dev/null 2>&1 || true
}

cleanup
trap 'cleanup; exit 0' INT TERM
trap cleanup EXIT

docker compose run --rm --no-deps --name "$name" -p "$port:$port" \
    -e E2E_PORT="$port" app sh docker/scripts/e2e.sh serve &
wait $!
