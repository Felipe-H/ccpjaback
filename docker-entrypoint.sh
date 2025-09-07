set -e

php artisan key:generate --force || true
php artisan storage:link || true
php artisan migrate --force || true
php artisan optimize

exec php -S 0.0.0.0:$PORT -t public
