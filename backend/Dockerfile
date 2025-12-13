FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    postgresql-dev \
    curl \
    git \
    zip \
    unzip \
    composer \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    redis \
    bcmath \
    ctype \
    fileinfo \
    json \
    mbstring \
    tokenizer \
    xml

# Configure PHP
RUN echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini

WORKDIR /app

# Copy project files
COPY . .

# Install Composer dependencies
RUN composer install --no-interaction --optimize-autoloader

# Set permissions
RUN chmod -R 755 storage bootstrap/cache

# Expose port
EXPOSE 8000

CMD ["php-fpm"]
