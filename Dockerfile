# syntax=docker/dockerfile:1

# ============================================================
# Stage 1: PHP dependencies (Composer)
# ============================================================
FROM composer:2 AS composer
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts --no-interaction

COPY . .
RUN php artisan package:discover --ansi

# ============================================================
# Stage 2: Runtime (PHP-FPM + Alpine)
# ============================================================
FROM php:8.3-fpm-alpine AS app

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_pgsql pgsql mbstring intl opcache curl xml dom

WORKDIR /var/www

COPY . .

COPY --from=composer /app/vendor ./vendor
COPY --from=composer /app/bootstrap/cache ./bootstrap/cache

RUN mkdir -p storage/framework/views storage/framework/cache \
        storage/framework/sessions storage/framework/testing \
        storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && cp -r storage /opt/storage-skel

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]