#!/bin/sh
# Deploys (or rolls back to) an image tag on the VPS, from the directory that
# holds docker-compose.prod.yml and .env. Called by .github/workflows/deploy.yml
# over SSH, or by hand:
#   APP_TAG=sha-<commit> sh deploy/vps/deploy.sh
#   sh deploy/vps/deploy.sh --rollback        # back to the previous tag
set -eu

cd "$(dirname "$0")/../.."
compose="docker compose -f docker-compose.prod.yml"

current="$(sed -n 's/^APP_TAG=//p' .env | tail -n 1)"
if [ "${1:-}" = "--rollback" ]; then
    APP_TAG="$(cat .previous-tag 2>/dev/null || true)"
    [ -n "$APP_TAG" ] || { echo "deploy: no previous tag recorded" >&2; exit 1; }
fi
tag="${APP_TAG:?Set APP_TAG (sha-<commit> or latest)}"

echo "deploy: ${current:-none} -> $tag"

# Pull first: if the tag does not exist nothing is touched.
APP_TAG="$tag" $compose pull web horizon scheduler reverb

# Record the tag in .env so a plain "docker compose up" keeps running it.
if grep -q '^APP_TAG=' .env; then
    sed -i "s/^APP_TAG=.*/APP_TAG=$tag/" .env
else
    printf '\nAPP_TAG=%s\n' "$tag" >> .env
fi
[ -n "$current" ] && [ "$current" != "$tag" ] && echo "$current" > .previous-tag

# web runs the new migrations on start: take a dump first.
if [ -n "$($compose ps -q backup 2>/dev/null)" ]; then
    $compose exec -T backup sh /usr/local/bin/backup.sh now || echo "deploy: warning, pre-deploy backup failed" >&2
fi

# web migrates on start; workers only start once web is healthy again.
$compose up -d --wait --remove-orphans web
$compose up -d --wait --remove-orphans

docker image prune -f >/dev/null
echo "deploy: running $tag"
