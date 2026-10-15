#!/bin/sh
# Daily PostgreSQL dump (runs in the "backup" service of docker-compose.prod.yml).
#   BACKUP_AT          time of day, HH:MM in the container's time zone (UTC)
#   BACKUP_KEEP_DAYS   dumps older than this are deleted
# "backup.sh now" takes one dump and exits (deploy.sh runs it before migrating).
# Restore (dumps are root-only on the host, so read them through the container):
#   docker compose -f docker-compose.prod.yml exec -T backup gunzip -c /backups/<file>.sql.gz \
#     | docker compose -f docker-compose.prod.yml exec -T postgres psql -U "$DB_USERNAME" -d "$DB_DATABASE"
set -eu

# Dumps hold personal data: readable by the owner only.
umask 077

at="${BACKUP_AT:-03:30}"
keep="${BACKUP_KEEP_DAYS:-14}"

dump() {
    file="/backups/${PGDATABASE}-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
    if pg_dump --no-owner --no-privileges | gzip -9 > "$file.tmp"; then
        mv "$file.tmp" "$file"
        echo "backup: wrote $file ($(du -h "$file" | cut -f1))"
    else
        rm -f "$file.tmp"
        echo "backup: pg_dump failed" >&2
    fi
    find /backups -name '*.sql.gz' -mtime +"$keep" -print -delete | sed 's/^/backup: deleted /'
}

seconds_until() {
    now=$(date -u +%s)
    target=$(date -u -d "$(date -u +%Y-%m-%d) $1" +%s 2>/dev/null || date -u -D '%Y-%m-%d %H:%M' -d "$(date -u +%Y-%m-%d) $1" +%s)
    [ "$target" -le "$now" ] && target=$((target + 86400))
    echo $((target - now))
}

if [ "${1:-}" = "now" ]; then
    dump
    exit 0
fi

echo "backup: daily at $at UTC, keeping $keep days"
while true; do
    sleep "$(seconds_until "$at")"
    dump
done
