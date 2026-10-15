#!/bin/sh
# Creates .env from .env.example on first run and fills machine-specific values:
# host UID/GID (file ownership in bind mounts) and random Reverb credentials.
# APP_KEY is generated afterwards inside the container (php artisan key:generate).
set -eu

if [ -f .env ]; then
    echo ".env already exists, leaving it untouched."
    exit 0
fi

cp .env.example .env

random_hex() {
    od -An -N"$1" -tx1 /dev/urandom | tr -d ' \n'
}

set_var() {
    # Portable in-place edit (GNU and BSD sed).
    sed "s|^$1=.*|$1=$2|" .env > .env.tmp && mv .env.tmp .env
}

set_var HOST_UID "$(id -u)"
set_var HOST_GID "$(id -g)"
set_var REVERB_APP_ID "$(od -An -N3 -tu4 /dev/urandom | tr -d ' \n')"
set_var REVERB_APP_KEY "$(random_hex 10)"
set_var REVERB_APP_SECRET "$(random_hex 16)"

echo ".env created. Review host ports (APP_PORT, FORWARD_DB_PORT, VITE_PORT...) if they clash."
