#!/usr/bin/env bash
set -e

export PORT="${PORT:-8080}"

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/http.d/default.conf

php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan package:discover --ansi
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
