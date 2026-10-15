#!/usr/bin/env bash
# Bring the site on the VPS to the latest main: code, packages, new tables, caches. Data and files stay.
#   sudo bash /var/www/projectsengineering/deploy/update.sh
set -euo pipefail
APP_DIR=${APP_DIR:-/var/www/projectsengineering}
BRANCH=${BRANCH:-main}
[ "$(id -u)" = 0 ] || { echo "Run as root: sudo bash deploy/update.sh"; exit 1; }
cd "$APP_DIR"

if ! git fetch -q origin "$BRANCH" 2>/dev/null; then
  echo "The repository is private. Paste a GitHub token with read access:"
  read -rs TOKEN; echo
  git fetch -q "https://x-access-token:${TOKEN}@github.com/MaharatNET-hub/projectsengineering.git" "$BRANCH:refs/remotes/origin/$BRANCH"
fi
git reset -q --hard "origin/$BRANCH"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress -q
chown -R www-data:www-data storage bootstrap/cache
sudo -u www-data php artisan v2:install          # new tables, if any
sudo -u www-data php artisan db:seed --class='Database\Seeders\V2Seeder' --force -q   # admin from .env, category engineers and specs
sudo -u www-data php artisan config:cache -q
sudo -u www-data php artisan view:cache -q
systemctl reload php8.3-fpm
echo "Updated to $(git log -1 --format='%h %s')"
