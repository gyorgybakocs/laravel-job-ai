#!/bin/sh
# Description: Install the official Laravel Vue starter kit

set -eu

cd /app

echo "================================================"
echo "--- INSTALL CI WORKER STARTED ---"
echo "--- ENV: ${ENV:-dev} ---"
echo "================================================"

echo "--- Checking Laravel core ---"
if [ ! -f "/app/artisan" ]; then
    echo "--- Installing the official Laravel Vue starter kit... ---"

    # The starter kit defines its own Laravel dependency; LARAVEL_VERSION
    # belongs to the old laravel/laravel installation and is not used here.
    # Migrations and frontend assets are handled by ci-migrate and ci-npm.
    composer create-project laravel/vue-starter-kit . --prefer-dist --no-interaction --no-scripts

    # Keep the starter kit defaults and append the project's overrides.
    # Preserve .env.example without copying local secrets into it.
    cp .env.example .env
    printf '\n' >> .env
    cat /.env.secrets >> .env
    printf '\n' >> .env
    cat /.env >> .env
    echo "--- Created .env with project settings ---"

    echo "--- Running package discovery ---"
    composer dump-autoload --no-interaction

    # Recent starter kit releases include this installer setup command.
    if php artisan list --raw | grep -q '^install:features'; then
        php artisan install:features --ansi --no-interaction
    fi

    echo "--- Generating app key ---"
    php artisan key:generate

    echo "--- Laravel Vue starter kit installation finished in /app ---"
else
    echo "--- Artisan found in /app. Ensuring Composer dependencies are installed. ---"
    composer install --no-interaction --prefer-dist
fi

echo "--- Resetting app ownership to host user ---"
chown -R 1000:1000 /app
echo "--- Setting storage permissions for PHP-FPM ---"
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "================================================"
echo "--- INSTALL CI WORKER FINISHED SUCCESSFULLY ---"
echo "================================================"
