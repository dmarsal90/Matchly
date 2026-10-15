#!/bin/sh
# Writes a production .env from deploy/env.production.example with fresh random
# secrets. Never overwrites an existing file: rotating APP_KEY or the database
# password by accident would lock you out of encrypted data and the database.
#
#   APP_DOMAIN=staging.example.com ACME_EMAIL=you@example.com sh deploy/oracle/make-env.sh .env
#
# Optional: RESEND_API_KEY, MAIL_FROM_ADDRESS, ANTHROPIC_API_KEY, HORIZON_ADMIN_EMAILS
set -eu

target="${1:?usage: make-env.sh <path to .env>}"
example="$(dirname "$0")/../env.production.example"
domain="${APP_DOMAIN:?Set APP_DOMAIN (the host name that points to this server)}"
email="${ACME_EMAIL:?Set ACME_EMAIL (contact e-mail for the HTTPS certificate)}"

if [ -e "$target" ]; then
    echo "make-env: $target already exists, leaving it untouched" >&2
    exit 0
fi

secret() { openssl rand -hex "$1"; }

umask 077
cp "$example" "$target"

# Replace KEY=... (or append it). Values here never contain spaces or quotes.
set_var() {
    grep -v "^$1=" "$target" > "$target.tmp" || true
    printf '%s=%s\n' "$1" "$2" >> "$target.tmp"
    mv "$target.tmp" "$target"
}

set_var APP_KEY "base64:$(openssl rand -base64 32)"
set_var APP_URL "https://$domain"
set_var APP_DOMAIN "$domain"
set_var ACME_EMAIL "$email"
set_var APP_TAG latest

set_var DB_PASSWORD "$(secret 24)"
set_var REDIS_PASSWORD "$(secret 24)"
set_var REVERB_APP_ID "$(od -An -N4 -tu4 /dev/urandom | tr -d ' ')"
set_var REVERB_APP_KEY "$(secret 10)"
set_var REVERB_APP_SECRET "$(secret 20)"

# Browsers reach Reverb through Caddy on the same domain.
set_var REVERB_PUBLIC_HOST "$domain"
set_var REVERB_PUBLIC_PORT 443
set_var REVERB_PUBLIC_SCHEME https
set_var SECURITY_CSP_CONNECT_SRC "wss://$domain"

set_var HORIZON_ADMIN_EMAILS "${HORIZON_ADMIN_EMAILS:-$email}"

# Without a mail provider, e-mails go to the log (storage/logs) instead of failing.
if [ -n "${RESEND_API_KEY:-}" ]; then
    set_var MAIL_MAILER resend
    set_var RESEND_API_KEY "$RESEND_API_KEY"
    set_var MAIL_FROM_ADDRESS "${MAIL_FROM_ADDRESS:-hello@$domain}"
else
    set_var MAIL_MAILER log
fi

if [ -n "${ANTHROPIC_API_KEY:-}" ]; then
    set_var ANTHROPIC_API_KEY "$ANTHROPIC_API_KEY"
else
    set_var AI_ENABLED false
fi

# Staging: the cheap side of every choice.
set_var LOG_LEVEL info
set_var BACKUP_KEEP_DAYS 7

echo "make-env: wrote $target (mode 600)"
