#!/bin/sh
# Description: Laravel database migrations + optimize:clear

cd /app

echo "================================================"
echo "--- MIGRATE CI WORKER STARTED ---"
echo "--- ENV: $ENV ---"
echo "================================================"

echo "------------------------------------------------"
echo "--- STARTING DATABASE MIGRATION TASK ---"
echo "------------------------------------------------"

php artisan migrate --force

echo "--- RUNNING LARAVEL OPTIMIZATION ---"
php artisan optimize:clear

echo "------------------------------------------------"
echo "--- DATABASE MIGRATION TASK FINISHED ---"
echo "------------------------------------------------"

echo "================================================"
echo "--- MIGRATE CI WORKER FINISHED SUCCESSFULLY ---"
echo "================================================"
