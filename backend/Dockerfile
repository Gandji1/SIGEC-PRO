# ========================
# Stage 1 — Build stage
# ========================
FROM php:8.3-apache AS build

ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip curl libpng-dev libonig-dev libxml2-dev libzip-dev zip gnupg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Node.js LTS
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Set working dir
WORKDIR /var/www

# Apache setup
ENV APACHE_DOCUMENT_ROOT=/var/www/public
RUN a2enmod rewrite \
 && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
 && sed -ri 's!Directory /var/www/!Directory ${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Add custom Laravel vhost if you have it
COPY docker/apache/laravel.conf /etc/apache2/sites-available/laravel.conf
RUN a2dissite 000-default && a2ensite laravel



# Copy app source
COPY . .

# Copy Composer manifests first (for cache)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Use production environment for asset build
ENV APP_ENV=production
ENV NODE_ENV=production




# ========================
# Stage 2 — Production stage
# ========================
FROM php:8.3-apache

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    libpng-dev libonig-dev libxml2-dev libzip-dev zip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

COPY php.ini "$PHP_INI_DIR/php.ini"

# Apache setup
ENV APACHE_DOCUMENT_ROOT=/var/www/public
RUN a2enmod rewrite \
 && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
 && sed -ri 's!Directory /var/www/!Directory ${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

COPY docker/apache/laravel.conf /etc/apache2/sites-available/laravel.conf
RUN a2dissite 000-default && a2ensite laravel

# HTTPS proxy fix for Traefik
RUN a2enmod headers \
 && printf "<IfModule mod_headers.c>\n    SetEnvIf X-Forwarded-Proto \"https\" HTTPS=on\n</IfModule>\n" > /etc/apache2/conf-available/forwarded-https.conf \
 && a2enconf forwarded-https

# Fix Apache warning
RUN printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf && a2enconf servername

# Copy built app
COPY --from=build /var/www /var/www

# Permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

CMD ["bash", "/usr/local/bin/entrypoint.sh"]
