#!/bin/bash

echo "Starting Queue Worker..."
echo "========================"

echo "Starting queue worker with 3 tries..."
php artisan queue:work --tries=3 --timeout=60 --memory=128
