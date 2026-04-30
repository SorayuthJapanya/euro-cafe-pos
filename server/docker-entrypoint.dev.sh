#!/bin/sh
set -e

cd /var/www/html

# Install composer dependencies into the vendor volume on first start
if [ ! -f "vendor/autoload.php" ]; then
    echo ">>> Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Bootstrap .env from the docker template and generate APP_KEY on first start
if [ ! -f ".env" ]; then
    echo ">>> Creating .env from .env.docker..."
    cp .env.docker .env
    php artisan key:generate --no-interaction
fi

# Run pending migrations
echo ">>> Running migrations..."
php artisan migrate --force --no-interaction

echo ">>> Starting Laravel dev server on 0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
