#!/bin/bash
set -e

APP_DIR="/var/www/html/crmfinity-ai"
VENV_PYTHON="$APP_DIR/venv/bin/python3"

echo "==> Pulling latest code..."
git -C "$APP_DIR" pull origin master

echo "==> Installing PHP dependencies..."
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-dev --optimize-autoloader --working-dir="$APP_DIR"

echo "==> Installing Node dependencies & building assets..."
npm --prefix "$APP_DIR" ci
npm --prefix "$APP_DIR" run build

echo "==> Running migrations..."
php "$APP_DIR/artisan" migrate --force

echo "==> Clearing & caching config/routes/views..."
php "$APP_DIR/artisan" config:cache
php "$APP_DIR/artisan" route:cache
php "$APP_DIR/artisan" view:cache

echo "==> Setting permissions..."
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

echo "==> Restarting queue worker..."
supervisorctl restart crmfinity-worker:*

echo "==> Done. Deploy complete."
