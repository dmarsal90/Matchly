#!/bin/sh
# Role-aware health check shared by every container built from the image.
set -eu

role="$(cat /tmp/container-role 2>/dev/null || echo web)"

port_open() {
    php -r "exit(@fsockopen('127.0.0.1', $1) ? 0 : 1);"
}

case "$role" in
    web)       curl -fsS -o /dev/null http://127.0.0.1:8080/up ;;
    fpm)       port_open 9000 ;;
    horizon)   php artisan horizon:status | grep -q running ;;
    reverb)    port_open "${REVERB_SERVER_PORT:-8080}" ;;
    vite)      port_open "${VITE_PORT:-5173}" ;;
    scheduler|worker) pgrep -f "artisan" >/dev/null ;;
    *)         exit 0 ;;
esac
