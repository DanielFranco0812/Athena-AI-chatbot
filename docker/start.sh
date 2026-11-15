#!/bin/sh
set -e
# Render/Railway give the port in $PORT
PORT="${PORT:-10000}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
# seed only an empty database
php artisan tinker --execute="exit(\App\Models\Plan::count() ? 0 : 1);" || php artisan db:seed --force

exec apache2-foreground
