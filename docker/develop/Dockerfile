FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev git unzip curl libicu-dev zlib1g-dev libzip-dev netcat-openbsd nodejs npm \
    && docker-php-ext-install pdo_pgsql intl zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html \
    && git config --global --add safe.directory /var/www/html

# Explicitly copy the production .env to the root
COPY ./docker/develop/.env.devlop /var/www/html/.env

# Fix npm permission issue
RUN mkdir -p /var/www/.npm && chown -R www-data:www-data /var/www/.npm

# Install dependencies as www-data
USER www-data
RUN composer install

# Switch back to root for final setup
USER root
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy and set up entrypoint
COPY ./docker/develop/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose ports for PHP artisan serve and Vite
EXPOSE 8000 5173

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]