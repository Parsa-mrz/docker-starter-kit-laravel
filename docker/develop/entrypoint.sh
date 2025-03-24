#!/bin/sh
set -e

echo "Waiting for Database to be ready..."
while ! nc -z "$DB_HOST" "$DB_PORT"; do
  sleep 1
done
echo "Database is ready!"

composer dump-autoload --optimize

php artisan cache:clear
php artisan config:clear
php artisan optimize:clear

# Run migrations and seed database
php artisan migrate --force
php artisan db:seed --force

# Create storage link (ignore if it already exists)
php artisan storage:link || true

# Start PHP server and Vite dev server in the background
echo "Starting PHP development server..."
php artisan serve --host=0.0.0.0 --port=8000 &

echo "Starting Vite development server..."
npm run dev -- --host 0.0.0.0 &

# Keep container running
wait