FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    librabbitmq-dev \
    libpq-dev

RUN docker-php-ext-install pdo_mysql pdo_pgsql

RUN pecl install redis amqp && docker-php-ext-enable redis amqp

COPY ./nginx/default.conf /etc/nginx/sites-available/default
COPY ./php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY ./entrypoint.sh /entrypoint.sh

WORKDIR /var/www
COPY . .

RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]