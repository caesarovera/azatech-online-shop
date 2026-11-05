# syntax=docker/dockerfile:1.7
#
# Multi-stage build untuk API Laravel.
#   Stage 1 (vendor) : install dependency Composer (tanpa dev) — tool build tidak ikut ke image akhir.
#   Stage 2 (runtime): PHP-FPM + Nginx (serversideup/php), berjalan sebagai user www-data (BUKAN root).
# Image yang sama dipakai untuk 3 peran: api (default CMD), worker (queue:work), scheduler (schedule:work).

ARG PHP_VERSION=8.4

########## Stage 1: dependency Composer ##########
FROM composer:2 AS vendor
WORKDIR /app

# Copy file lock dulu → layer ini di-cache selama dependency tidak berubah.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

########## Stage 2: runtime ##########
FROM serversideup/php:${PHP_VERSION}-fpm-nginx AS runtime

ENV PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    NGINX_WEBROOT=/var/www/html/public \
    HEALTHCHECK_PATH=/api/health \
    NGINX_LISTEN_IP_PROTOCOL=ipv4 \
    LOG_CHANNEL=json \
    APP_ENV=production \
    APP_DEBUG=false

WORKDIR /var/www/html

COPY --chown=www-data:www-data --from=vendor /app /var/www/html
COPY --chmod=755 docker/app-entrypoint.sh /usr/local/bin/app-entrypoint

# Jalan sebagai user non-root (sudah default di image, ditulis eksplisit agar jelas saat review).
USER www-data

RUN php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache storage/framework/views storage/logs storage/app/private

EXPOSE 8080
ENTRYPOINT ["app-entrypoint"]
CMD ["/init"]
