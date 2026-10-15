#!/bin/sh
# Prepares a fresh Ubuntu 22.04/24.04 server (Oracle Cloud Always Free Ampere A1
# or any other) to run Matchly with docker-compose.prod.yml. Idempotent: running
# it again only fills in what is missing. Guide: docs/oracle-free-staging.md
#
#   curl -fsSL https://raw.githubusercontent.com/dmarsal90/matchly/main/deploy/oracle/bootstrap.sh -o bootstrap.sh
#   APP_DOMAIN=matchly.duckdns.org ACME_EMAIL=you@example.com sh bootstrap.sh
#
# Required: APP_DOMAIN, ACME_EMAIL.   Optional: RESEND_API_KEY, ANTHROPIC_API_KEY,
# HORIZON_ADMIN_EMAILS, INSTALL_DIR (/opt/matchly), REPO_URL, APP_TAG (latest),
# SKIP_DNS_CHECK=1.
set -eu

: "${APP_DOMAIN:?Set APP_DOMAIN (e.g. matchly.duckdns.org)}"
: "${ACME_EMAIL:?Set ACME_EMAIL}"
INSTALL_DIR="${INSTALL_DIR:-/opt/matchly}"
REPO_URL="${REPO_URL:-https://github.com/dmarsal90/matchly.git}"
APP_TAG="${APP_TAG:-latest}"

log() { printf '\n==> %s\n' "$*"; }
die() { printf 'bootstrap: %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -ne 0 ] || die "run it as a normal user with sudo (ubuntu), not as root: the deploy key will belong to that user"
command -v sudo >/dev/null || die "sudo is required"
. /etc/os-release
[ "${ID:-}" = "ubuntu" ] || die "this script is written for Ubuntu (found ${ID:-unknown})"
echo "server: $(uname -m), $(nproc) CPU, $(free -m | awk '/^Mem:/ {print $2}') MB RAM"

# Let's Encrypt validates the domain from the internet: a wrong record would burn
# attempts against its rate limits before anything works.
if [ "${SKIP_DNS_CHECK:-0}" != "1" ]; then
    log "Checking that $APP_DOMAIN points to this server"
    public_ip="$(curl -fsS --max-time 10 https://api.ipify.org || true)"
    resolved="$(getent ahostsv4 "$APP_DOMAIN" | awk 'NR==1 {print $1}')"
    [ -n "$public_ip" ] || die "could not find this server's public IP (SKIP_DNS_CHECK=1 to skip)"
    [ "$resolved" = "$public_ip" ] || die "$APP_DOMAIN resolves to '${resolved:-nothing}', this server is $public_ip. Fix the DNS record and wait a minute."
fi

log "Swap (Composer, PHP and PostgreSQL peak together at deploy time)"
if ! swapon --show | grep -q .; then
    sudo fallocate -l 2G /swapfile
    sudo chmod 600 /swapfile
    sudo mkswap /swapfile >/dev/null
    sudo swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab >/dev/null
fi

log "Docker"
if ! command -v docker >/dev/null; then
    sudo apt-get update -qq
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq ca-certificates curl git openssl
    sudo install -m 0755 -d /etc/apt/keyrings
    sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $VERSION_CODENAME stable" \
        | sudo tee /etc/apt/sources.list.d/docker.list >/dev/null
    sudo apt-get update -qq
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-compose-plugin
fi
sudo systemctl enable --now docker >/dev/null 2>&1
sudo usermod -aG docker "$USER"

# Oracle's Ubuntu images ship iptables rules that reject everything but SSH,
# on top of the cloud network rules you open in the console.
log "Firewall: ports 80 and 443"
open_port() { # proto port
    sudo iptables -C INPUT -p "$1" --dport "$2" -j ACCEPT 2>/dev/null \
        || sudo iptables -I INPUT 6 -p "$1" --dport "$2" -j ACCEPT
}
open_port tcp 80
open_port tcp 443
open_port udp 443
if ! dpkg -s iptables-persistent >/dev/null 2>&1; then
    echo iptables-persistent iptables-persistent/autosave_v4 boolean true | sudo debconf-set-selections
    echo iptables-persistent iptables-persistent/autosave_v6 boolean true | sudo debconf-set-selections
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq iptables-persistent
fi
sudo netfilter-persistent save >/dev/null

log "Code in $INSTALL_DIR"
sudo mkdir -p "$INSTALL_DIR"
sudo chown "$USER:$USER" "$INSTALL_DIR"
if [ -d "$INSTALL_DIR/.git" ]; then
    git -C "$INSTALL_DIR" pull --ff-only -q
else
    git clone -q --depth 1 "$REPO_URL" "$INSTALL_DIR"
fi

log "Configuration"
(cd "$INSTALL_DIR" && sh deploy/oracle/make-env.sh .env)

log "First deploy ($APP_TAG)"
# The docker group only applies to new sessions: run this one inside it.
sg docker -c "cd '$INSTALL_DIR' && APP_TAG='$APP_TAG' sh deploy/vps/deploy.sh"

cat <<DONE

Matchly is up: https://$APP_DOMAIN   (health: https://$APP_DOMAIN/up)

Next:
  1. Register on the site, then make yourself admin (--verify: no mail provider yet):
       cd $INSTALL_DIR && docker compose -f docker-compose.prod.yml exec web php artisan users:admin you@example.com --verify
  2. Add the GitHub 'staging' environment settings so every merge to main deploys here
     (docs/oracle-free-staging.md, step 7).
  3. Copy $INSTALL_DIR/backups somewhere else now and then: a backup on the same disk is not enough.
DONE
