FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        git \
        procps \
        unzip \
        libzip-dev \
        libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath zip opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY infrastructure/docker/backend/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY infrastructure/docker/backend/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
COPY apps/backend /var/www/html
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader \
    && rm -rf /root/.composer

RUN mkdir -p storage/app/private/backups storage/app/private/documents storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
