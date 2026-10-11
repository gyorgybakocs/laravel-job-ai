#!/bin/sh
# Description: Apply compatible NPM audit fixes and report findings without blocking CI

cd /app

echo "================================================"
echo "--- NPM CI WORKER STARTED ---"
echo "--- ENV: $ENV ---"
echo "================================================"

echo "------------------------------------------------"
echo "--- STARTING NPM TASK ---"
echo "------------------------------------------------"

echo "--- RUNNING NPM AUDIT ---"
npm audit fix

echo "------------------------------------------------"
echo "--- NPM TASK FINISHED ---"
echo "------------------------------------------------"

echo "================================================"
echo "--- NPM CI WORKER FINISHED SUCCESSFULLY ---"
echo "================================================"
