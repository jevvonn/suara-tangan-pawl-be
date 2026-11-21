FROM dunglas/frankenphp:1-php8.4-bookworm

# Install PHP extensions required by Laravel (adjust pdo_mysql/pdo_pgsql as needed)
RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    zip \
    opcache

# Copy Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application files
COPY . .

# Install PHP dependencies (remove --no-dev for local development)
RUN composer install --no-interaction --optimize-autoloader --prefer-dist

# Link public/storage -> storage/app/public inside the container, so uploaded
# images are served at APP_URL/storage/... (the host's Windows link is not copied).
RUN php artisan storage:link

# Ensure storage and cache directories are writable
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Disable automatic HTTPS in local Docker environments and serve on port 80
ENV SERVER_NAME=:80

EXPOSE 80
