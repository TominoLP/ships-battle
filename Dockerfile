# =========================================================
# Dockerfile for dev environment - PHP-FPM with Composer (docker compose up)
# =========================================================
FROM php:8.3-fpm

# 1. Install system dependencies as root
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev libzip-dev \
    sqlite3 libsqlite3-dev pkg-config \
    && docker-php-ext-install pdo pdo_sqlite mbstring exif pcntl bcmath gd zip

# 2. Install Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3. Set working directory
WORKDIR /var/www/html

# 4. Copy project files
COPY . .

# 5. Adjust ownership (match WSL user id 1000)
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data
RUN chown -R www-data:www-data /var/www/html

# 6. Switch to non-root user for runtime
USER www-data

# 7. Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader
