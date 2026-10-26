#!/usr/bin/env bash
# Ship the latest code from GitHub to the VPS. Run as root on the server:
#   bash /var/www/draft-duel/deploy/deploy.sh
set -euo pipefail

APP=/var/www/draft-duel
BRANCH="${BRANCH:-main}"
as_web() { sudo -u www-data -H env HOME=/tmp COMPOSER_HOME=/tmp/composer "$@"; }
cd "$APP"

as_web php artisan down --retry=15 || true
trap 'as_web php artisan up' EXIT

as_web git pull --ff-only origin "$BRANCH"
as_web composer install --no-dev --optimize-autoloader --no-interaction
as_web npm ci --no-audit --no-fund
as_web npm run build
as_web php artisan migrate --force
as_web php artisan optimize

supervisorctl restart draft-duel-reverb
systemctl reload php8.3-fpm
echo "Deployed $(as_web git rev-parse --short HEAD)"
