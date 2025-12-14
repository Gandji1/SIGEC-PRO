#!/usr/bin/env bash
set -e

cd /var/www

echo "🏁 Starting Laravel entrypoint..."

# Wait for database if needed
if [ "$DB_CONNECTION" = "mysql" ]; then
    echo "⏳ Waiting for MySQL database..."
    until nc -z mysql 3306; do
        echo "MySQL is unavailable - sleeping"
        sleep 1
    done
    echo "✅ MySQL is up - continuing"
fi

# Create .env file if it doesn't exist
if [ ! -f ".env" ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
fi

# Generate app key if not exists
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Generating application key..."
    php artisan key:generate --force
fi

# Clear and cache config for production
if [ "$APP_ENV" = "production" ]; then
    echo "🚀 Caching configuration for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Run migrations in development
if [ "$APP_ENV" = "local" ] || [ "$APP_DEBUG" = "true" ]; then
    echo "🗃️ Running database migrations..."
    php artisan migrate --force
fi

# Set proper permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "🚀 Starting Apache..."
exec apache2-foreground
