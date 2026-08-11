FROM php:8.3-fpm

# Install system dependencies and PHP extensions (MySQL & PostgreSQL)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application source code
COPY . /var/www

# Ensure required storage and cache directories exist with permissions before composer discovery
RUN mkdir -p storage/framework/views \
             storage/framework/cache/data \
             storage/framework/sessions \
             storage/logs \
             bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=php+

# Make entrypoint executable
RUN chmod +x /var/www/docker/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/var/www/docker/entrypoint.sh"]
