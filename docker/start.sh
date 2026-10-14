#!/bin/bash
set -e

PORT="${PORT:-8080}"
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf || true
sed -i "s/\*:8080/*:${PORT}/" /etc/apache2/sites-available/000-default.conf || true

a2dismod mpm_event 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true
a2enmod rewrite 2>/dev/null || true

if [ -z "$APP_KEY" ]; then
  echo "APP_KEY is missing"
fi

# Railway MySQL variables → Laravel
if [ -n "$MYSQLHOST" ]; then
  export DB_CONNECTION=mysql
  export DB_HOST="$MYSQLHOST"
  export DB_PORT="${MYSQLPORT:-3306}"
  export DB_DATABASE="${MYSQLDATABASE:-railway}"
  export DB_USERNAME="${MYSQLUSER:-root}"
  export DB_PASSWORD="${MYSQLPASSWORD:-}"
fi

if [ -n "$MYSQL_HOST" ]; then
  export DB_CONNECTION=mysql
  export DB_HOST="$MYSQL_HOST"
  export DB_PORT="${MYSQL_PORT:-3306}"
  export DB_DATABASE="${MYSQL_DATABASE:-railway}"
  export DB_USERNAME="${MYSQL_USER:-root}"
  export DB_PASSWORD="${MYSQL_PASSWORD:-}"
fi

# Prefer explicit DB_* if already set by Railway references
if [ -n "$DB_HOST" ]; then
  export DB_CONNECTION=mysql
fi

php artisan config:clear || true
php artisan storage:link --force 2>/dev/null || php artisan storage:link || true
php artisan migrate --force || true
php artisan db:seed --force || true

exec apache2-foreground
