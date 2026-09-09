# syntax=docker/dockerfile:1

# ============================================================
# Stage 1: PHP dependencies (Composer)
# ============================================================
FROM composer:2 AS composer
WORKDIR /app

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts --no-interaction

COPY . .
RUN php artisan package:discover --ansi

# ============================================================
# Stage 2: Runtime (PHP-FPM + Debian)
# ============================================================
FROM php:8.3-fpm AS app

# Install the extensions the app needs that are not already bundled in the
# official PHP image (mbstring/opcache/curl/xml/dom ship preinstalled).
RUN --mount=type=cache,target=/var/cache/apt \
    apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libicu-dev \
    && docker-php-ext-install pdo_pgsql pgsql intl \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && printf 'apc.enable_cli=1\n' > /usr/local/etc/php/conf.d/zz-apcu.ini \
    && rm -rf /var/lib/apt/lists/*

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