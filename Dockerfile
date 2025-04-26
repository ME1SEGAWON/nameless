# Stage 1: Build dengan Composer
FROM composer:2.6 AS builder

WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader

# Stage 2: Runtime dengan PHP + Apache
FROM php:8.2-apache

# Install dependency sistem
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Konfigurasi Apache
RUN a2enmod rewrite
COPY --from=builder /app /var/www/html
COPY .docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Set permission folder
RUN chown -R www-data:www-data /var/www/html/storage \
    /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage \
    /var/www/html/bootstrap/cache

# Environment variables (override dengan docker-compose)
WORKDIR /var/www/html
EXPOSE 80

# Script startup (migrasi database + jalankan server)
COPY .docker/entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]
