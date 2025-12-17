#!/usr/bin/env bash
set -e

cd /var/www

echo "🏁 Starting Laravel entrypoint..."

echo "📁 Ensuring storage and cache directories exist..."
mkdir -p storage \
    storage/framework \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

echo "🔐 Setting permissions on storage, database and cache..."
chown -R www-data:www-data storage bootstrap/cache database
chmod -R 775 storage bootstrap/cache database

# Create .env file if it doesn't exist
if [ ! -f ".env" ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
fi

# Generate app key if not exists
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

# Clear config cache first
echo "🧹 Clearing configuration cache..."
php artisan config:clear

# Cache config for production
if [ "$APP_ENV" = "production" ]; then
    echo "🚀 Caching configuration for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

echo "🚀 Starting Apache..."
exec apache2-foreground
