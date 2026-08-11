#!/bin/sh
set -e

# Remove any cached config/routes/services from build phase
rm -f bootstrap/cache/*.php

# Hardcode PostgreSQL as the database connection driver for Render production
export DB_CONNECTION=pgsql
export CACHE_STORE="${CACHE_STORE:-file}"
export SESSION_DRIVER="${SESSION_DRIVER:-cookie}"

echo "Connecting strictly to PostgreSQL (DB_CONNECTION=pgsql)..."

# Ensure storage directories exist and are fully writable (777)
mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/logs bootstrap/cache database
chmod -R 777 storage bootstrap/cache database

# Ensure valid APP_KEY in base64 format
if [ -z "$APP_KEY" ] || ! echo "$APP_KEY" | grep -q "^base64:"; then
    echo "Generating valid base64 Laravel APP_KEY..."
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    echo "APP_KEY=${APP_KEY}" >> /var/www/.env
fi

# Clear stale configuration
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan cache:clear || true

# Run database setup & seeders on PostgreSQL
echo "Running database setup on PostgreSQL..."
php artisan db:wipe --force || true
php artisan migrate --force
php artisan db:seed --force || true
php artisan storage:link --force || true

# Start HTTP web server on port 8000
echo "Starting HTTP web server on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
