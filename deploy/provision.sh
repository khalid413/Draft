#!/usr/bin/env bash
# One-time setup of Draft Duel on a fresh Ubuntu 24.04 VPS. Run as root:
#   DOMAIN=draft.example.com EMAIL=you@example.com bash provision.sh
# The domain's DNS A record must already point at this server (Let's Encrypt checks it).
set -euo pipefail

: "${DOMAIN:?Set DOMAIN, e.g. DOMAIN=draft.example.com}"
: "${EMAIL:?Set EMAIL for certificate expiry notices}"
REPO="${REPO:-https://github.com/khalid413/Draft.git}"
BRANCH="${BRANCH:-main}"
APP=/var/www/draft-duel
as_web() { sudo -u www-data -H env HOME=/tmp COMPOSER_HOME=/tmp/composer "$@"; }

echo "==> Installing packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y nginx supervisor git unzip curl openssl sqlite3 certbot python3-certbot-nginx \
    php8.3-fpm php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
    php8.3-bcmath php8.3-intl composer nodejs npm

echo "==> Fetching the code into $APP"
if [ ! -d "$APP/.git" ]; then
    install -d -o www-data -g www-data "$APP"
    as_web git clone --branch "$BRANCH" "$REPO" "$APP"
fi
cd "$APP"

echo "==> Writing .env (kept if it already exists)"
if [ ! -f .env ]; then
    sed "s/draft.example.com/$DOMAIN/g" deploy/env.production.example > .env
    sed -i "s/^REVERB_APP_KEY=.*/REVERB_APP_KEY=$(openssl rand -hex 16)/" .env
    sed -i "s/^REVERB_APP_SECRET=.*/REVERB_APP_SECRET=$(openssl rand -hex 32)/" .env
    chown www-data:www-data .env
    chmod 640 .env
fi

echo "==> Installing dependencies and building assets"
as_web composer install --no-dev --optimize-autoloader --no-interaction
as_web npm ci --no-audit --no-fund
as_web npm run build
grep -q '^APP_KEY=base64' .env || as_web php artisan key:generate --force

echo "==> Preparing the database"
[ -f database/database.sqlite ] || as_web touch database/database.sqlite
as_web php artisan migrate --force
as_web php artisan db:seed --force
as_web php artisan optimize

echo "==> Configuring Nginx"
sed "s/draft.example.com/$DOMAIN/g" deploy/nginx.conf > /etc/nginx/sites-available/draft-duel
ln -sf /etc/nginx/sites-available/draft-duel /etc/nginx/sites-enabled/draft-duel
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

echo "==> Starting Reverb under Supervisor"
cp deploy/supervisor-reverb.conf /etc/supervisor/conf.d/draft-duel-reverb.conf
supervisorctl reread
supervisorctl update
supervisorctl restart draft-duel-reverb

echo "==> Requesting the HTTPS certificate"
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect

echo "==> Firewall: SSH, HTTP and HTTPS only"
if command -v ufw >/dev/null; then
    ufw allow OpenSSH
    ufw allow 'Nginx Full'
    ufw --force enable
fi

echo
echo "Done: https://$DOMAIN"
echo "Create the admin account with:"
echo "  cd $APP && sudo -u www-data php artisan draft:admin"
