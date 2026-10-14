#!/usr/bin/env bash
# Install (or re-install) the site on an Ubuntu VPS next to whatever already runs there.
#
#   sudo bash deploy/install.sh                        # on the server's IP, port 8080
#   sudo DOMAIN=review.example.com bash deploy/install.sh   # on a domain (port 80, HTTPS offered)
#
# What it does, and nothing else:
#   - PHP 8.3 (php-fpm) with its own pool for this site, nginx, MySQL (each only if missing)
#   - a MySQL database + user for this site only (never touches other databases)
#   - the code in /var/www/projectsengineering, .env, tables, admin account
#   - one nginx site file for this site (other sites are left as they are)
# Run it again after a failure: every step checks first.
set -euo pipefail

APP_DIR=${APP_DIR:-/var/www/projectsengineering}
REPO=${REPO:-https://github.com/MaharatNET-hub/projectsengineering.git}
BRANCH=${BRANCH:-main}
DOMAIN=${DOMAIN:-}
PORT=${PORT:-8080}
DB_NAME=${DB_NAME:-projectsengineering}
DB_USER=${DB_USER:-projectsengineering}
POOL=projectsengineering

say()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m!! %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31mXX %s\033[0m\n' "$*"; exit 1; }

[ "$(id -u)" = 0 ] || die "Run as root: sudo bash deploy/install.sh"
command -v apt-get >/dev/null || die "This script is for Ubuntu/Debian."
export DEBIAN_FRONTEND=noninteractive

say "What already runs on this server (left untouched)"
ss -tlnp 2>/dev/null | awk 'NR==1 || /LISTEN/' || true
ls /etc/nginx/sites-enabled 2>/dev/null | sed 's/^/  nginx site: /' || true

# ---------------------------------------------------------------- packages
say "Packages: PHP 8.3, nginx, MySQL, git, unzip"
apt-get update -q
if ! apt-cache show php8.3-fpm >/dev/null 2>&1; then
  apt-get install -y -q software-properties-common
  add-apt-repository -y ppa:ondrej/php
  apt-get update -q
fi
apt-get install -y -q php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
  php8.3-bcmath php8.3-intl php8.3-gd git unzip curl ca-certificates openssl
fresh_nginx=0
if ! command -v nginx >/dev/null; then
  if ss -tln | grep -qE ':(80|443)\s'; then
    warn "Something other than nginx holds port 80/443; nginx will serve this site on port $PORT only."
  fi
  apt-get install -y -q nginx
  fresh_nginx=1
fi
if ! command -v mysql >/dev/null; then
  apt-get install -y -q mysql-server
fi
systemctl enable --now mysql 2>/dev/null || systemctl enable --now mariadb 2>/dev/null || true
if ! command -v composer >/dev/null; then
  curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --quiet
fi

# ---------------------------------------------------------------- code
say "Code in $APP_DIR ($BRANCH)"
if [ -d "$APP_DIR/.git" ]; then
  git -C "$APP_DIR" fetch -q origin "$BRANCH" && git -C "$APP_DIR" checkout -q "$BRANCH" && git -C "$APP_DIR" reset -q --hard "origin/$BRANCH"
else
  if ! git ls-remote "$REPO" >/dev/null 2>&1; then
    echo "The repository is private. Paste a GitHub token with read access (Settings → Developer settings → Fine-grained tokens):"
    read -rs TOKEN; echo
    CLONE_URL="https://x-access-token:${TOKEN}@${REPO#https://}"
  else
    CLONE_URL=$REPO
  fi
  git clone -q --branch "$BRANCH" "$CLONE_URL" "$APP_DIR"
  git -C "$APP_DIR" remote set-url origin "$REPO" # the token is not kept on the server
fi
cd "$APP_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress -q

# ---------------------------------------------------------------- database
say "MySQL database '$DB_NAME' for this site only"
envget() { grep -E "^$1=" .env 2>/dev/null | head -1 | cut -d= -f2- | sed 's/^"//;s/"$//'; }
DB_PASS=$(envget DB_PASSWORD || true)
[ -n "$DB_PASS" ] && [ "$(envget DB_CONNECTION)" = mysql ] || DB_PASS=$(openssl rand -hex 16)
MYSQL="mysql"
if ! mysql -e 'SELECT 1' >/dev/null 2>&1; then
  echo "MySQL asks for its root password (it is used once, not saved):"
  read -rs MYSQL_ROOT_PW; echo
  MYSQL="mysql -uroot -p$MYSQL_ROOT_PW"
fi
$MYSQL <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

# ---------------------------------------------------------------- .env
say "Settings (.env)"
[ -f .env ] || cp .env.example .env
setenv() { # key value: replace the line (commented or not), or add it
  local k=$1 v=$2
  if grep -qE "^#? ?$k=" .env; then sed -i -E "s|^#? ?$k=.*|$k=$v|" .env; else echo "$k=$v" >> .env; fi
}
if [ -n "$DOMAIN" ]; then URL="http://$DOMAIN"; else URL="http://$(curl -fsS4 --max-time 5 https://api.ipify.org || hostname -I | awk '{print $1}'):$PORT"; fi
setenv APP_ENV production
setenv APP_DEBUG false
setenv APP_URL "$URL"
setenv DB_CONNECTION mysql
setenv DB_HOST 127.0.0.1
setenv DB_PORT 3306
setenv DB_DATABASE "$DB_NAME"
setenv DB_USERNAME "$DB_USER"
setenv DB_PASSWORD "$DB_PASS"
setenv V2_MAX_UPLOAD_MB 100
if [ -z "$(envget V2_ADMIN_PASSWORD)" ]; then
  read -rp "Admin email for the dashboard [admin@example.com]: " ADMIN_EMAIL
  setenv V2_ADMIN_EMAIL "${ADMIN_EMAIL:-admin@example.com}"
  setenv V2_ADMIN_PASSWORD "$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-16)"
fi
[ -n "$(envget APP_KEY)" ] || php artisan key:generate --force -q

say "Tables and first content"
mkdir -p storage/app/v2 storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod 640 .env && chown root:www-data .env
sudo -u www-data php artisan v2:install
sudo -u www-data php artisan config:cache -q
sudo -u www-data php artisan view:cache -q

# ---------------------------------------------------------------- php-fpm: its own pool (limits for big PDFs, no effect on other sites)
say "PHP pool '$POOL'"
cat > /etc/php/8.3/fpm/pool.d/$POOL.conf <<CONF
[$POOL]
user = www-data
group = www-data
listen = /run/php/$POOL.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 8
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
php_admin_value[upload_max_filesize] = 16M
php_admin_value[post_max_size] = 16M
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 300
php_admin_flag[display_errors] = off
CONF
systemctl enable -q php8.3-fpm && systemctl restart php8.3-fpm

# ---------------------------------------------------------------- nginx: one site file
say "nginx site"
if [ -n "$DOMAIN" ]; then LISTEN=80; NAME=$DOMAIN; else LISTEN=$PORT; NAME=_; fi
cat > /etc/nginx/sites-available/projectsengineering <<CONF
server {
    listen $LISTEN;
    listen [::]:$LISTEN;
    server_name $NAME;
    root $APP_DIR/public;
    index index.php;
    client_max_body_size 16m;
    charset utf-8;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/$POOL.sock;
        fastcgi_read_timeout 300;
    }
    location ~ /\.(?!well-known) { deny all; }
    location = /favicon.ico { access_log off; log_not_found off; }
}
CONF
ln -sf /etc/nginx/sites-available/projectsengineering /etc/nginx/sites-enabled/projectsengineering
# nginx's own welcome page only when this script installed nginx (it would take port 80)
[ "$fresh_nginx" = 1 ] && rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable -q nginx && systemctl reload nginx 2>/dev/null || systemctl restart nginx
if command -v ufw >/dev/null && ufw status | grep -q active; then ufw allow "$LISTEN/tcp" >/dev/null; fi

if [ -n "$DOMAIN" ]; then
  read -rp "Turn on HTTPS for $DOMAIN with Let's Encrypt now? [y/N] " yn
  if [ "${yn,,}" = y ]; then
    apt-get install -y -q certbot python3-certbot-nginx
    certbot --nginx -d "$DOMAIN" --redirect --agree-tos -m "$(envget V2_ADMIN_EMAIL)" -n && setenv APP_URL "https://$DOMAIN" && sudo -u www-data php artisan config:cache -q
  fi
fi

say "Done"
echo "  Site:       $(envget APP_URL)/v2"
echo "  Dashboard:  $(envget APP_URL)/v2/admin"
echo "  Admin:      $(envget V2_ADMIN_EMAIL) / $(envget V2_ADMIN_PASSWORD)   (change it under My account)"
echo "  Database:   $DB_NAME (user $DB_USER, password in $APP_DIR/.env)"
echo "  Email:      set MAIL_* in $APP_DIR/.env, then: sudo bash deploy/update.sh"
echo "  Updates:    sudo bash $APP_DIR/deploy/update.sh"
