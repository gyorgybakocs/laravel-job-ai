#!/bin/sh
# Description: Composer install/update + permissions

cd /app

echo "================================================"
echo "--- COMPOSER CI WORKER STARTED ---"
echo "--- ENV: $ENV ---"
echo "================================================"

echo "------------------------------------------------"
echo "--- STARTING COMPOSER TASK ---"
echo "------------------------------------------------"

if [ ! -d "vendor" ]; then
    echo "--- Vendor directory missing. Running composer install ---"
    composer install --no-interaction
else
    echo "--- Vendor directory exists. Running composer update ---"
    composer update --no-interaction
fi

echo "--- RESETTING APP OWNERSHIP TO HOST USER ---"
chown -R 1000:1000 /app
echo "--- SETTING FILE PERMISSIONS ---"
echo "--- chown www-data:www-data on storage and bootstrap/cache ---"
chown -R www-data:www-data storage bootstrap/cache
echo "--- chmod 775 on storage and bootstrap/cache ---"
chmod -R 775 storage bootstrap/cache

echo "------------------------------------------------"
echo "--- COMPOSER TASK FINISHED ---"
echo "------------------------------------------------"

echo "================================================"
echo "--- COMPOSER CI WORKER FINISHED SUCCESSFULLY ---"
echo "================================================"
