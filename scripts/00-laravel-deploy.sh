#!/usr/bin/env bash
echo "PHP Version"
echo php -v
echo "Running composer"
composer self-update 2.1.14
composer dump-autoload
composer global require hirak/prestissimo
composer install --no-dev --working-dir=/var/www/html
composer update --no-scripts

echo "generating application key..."
php artisan key:generate --show

echo "Caching config..."
php artisan config:cache

echo "Caching routes..."
php artisan route:cache

echo "Running migrations..."
php artisan migrate --force
