#!/bin/sh
set -e

cd /var/www/html

# Install Composer dependencies into the vendor volume on first start
if [ ! -f "vendor/autoload.php" ]; then
    echo ">>> Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Generate APP_KEY once and persist it via the volume-mounted .env
# All other config arrives via docker-compose environment variables
if [ ! -f ".env" ]; then
    echo "APP_KEY=" > .env
    php artisan key:generate --no-interaction
    echo ">>> APP_KEY generated."
fi

echo ">>> Running migrations..."
php artisan migrate --force --no-interaction

echo ">>> Starting Laravel dev server on 0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
