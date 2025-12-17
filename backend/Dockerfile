# Dockerfile for Laravel 11 / PHP 8.2 (Apache)
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update \
    && apt-get install -y \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        zip \
        netcat-openbsd \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite

# Install PHP extensions required by Laravel and your stack
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        mbstring \
        zip \
        exif \
        pcntl \
        bcmath \
        gd

# Copy custom php.ini if present
COPY php.ini /usr/local/etc/php/php.ini

# Set workdir
WORKDIR /var/www

# Copy application code
COPY . /var/www

# Copy Apache vhost
COPY docker/apache/laravel.conf /etc/apache2/sites-available/000-default.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/laravel-entrypoint
RUN chmod +x /usr/local/bin/laravel-entrypoint

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHP dependencies (optimize for production by default)
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

EXPOSE 80

ENTRYPOINT ["laravel-entrypoint"]
