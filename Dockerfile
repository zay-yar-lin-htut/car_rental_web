# Use an official PHP image with Apache
FROM php:8.2-apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy composer and install dependencies
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . .

# RUN composer install --no-dev --optimize-autoloader

# Run the Laravel API scaffolding setup (the php artisan install:api command)
# RUN php artisan install:api

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copy virtual host config (optional for Laravel pretty URLs)
COPY ./vhost.conf /etc/apache2/sites-available/000-default.conf

# Expose port
EXPOSE 80
