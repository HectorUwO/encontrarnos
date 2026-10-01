# syntax=docker/dockerfile:1

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# tsc resuelve ziggy-js desde vendor/tightenco/ziggy, por eso se copia el vendor.
FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
# El lockfile se generó con npm 11.6; versiones posteriores lo ven desactualizado.
RUN npm install -g npm@11.6.2 && npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

FROM serversideup/php:8.3-fpm-nginx AS app
USER root
RUN install-php-extensions gd intl exif bcmath pdo_mysql pdo_sqlite zip
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=false \
    SSL_MODE=off \
    PHP_POST_MAX_SIZE=12M \
    PHP_UPLOAD_MAX_FILE_SIZE=10M
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
RUN php artisan package:discover --ansi
