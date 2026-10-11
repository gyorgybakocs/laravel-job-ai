#!/bin/sh
# Description: NPM install + Vite production build

set -eu

cd /app

if [ ! -f vendor/autoload.php ]; then
    echo "--- Composer dependencies are missing. Run make ci-composer first. ---" >&2
    exit 1
fi

echo "================================================"
echo "--- NPM CI WORKER STARTED ---"
echo "--- ENV: $ENV ---"
echo "================================================"

echo "--- Running npm install ---"
npm install

echo "--- Running Vite production build ---"
npm run build

echo "================================================"
echo "--- NPM CI WORKER FINISHED SUCCESSFULLY ---"
echo "================================================"
