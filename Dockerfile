# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

# ---------------------------------------------------------------------------
# base - PHP runtime shared by the dev, vendor and production stages.
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        zip \
    && apk add --no-cache fcgi su-exec \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# ---------------------------------------------------------------------------
# vendor - production-only PHP dependencies and an optimized autoloader.
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

# Install first from the lock file alone so this layer is cached across
# source changes.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize

# ---------------------------------------------------------------------------
# assets - Vite build output.
# ---------------------------------------------------------------------------
FROM node:${NODE_VERSION}-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# ---------------------------------------------------------------------------
# dev - php-fpm for local development. Source arrives via a bind mount.
# ---------------------------------------------------------------------------
FROM base AS dev

# Match the container user to the host user so bind-mounted files stay
# writable on Linux. The defaults are Alpine's own www-data ids.
ARG UID=82
ARG GID=82
RUN if [ "${UID}" != "82" ] || [ "${GID}" != "82" ]; then \
        apk add --no-cache shadow \
        && groupmod -o -g "${GID}" www-data \
        && usermod -o -u "${UID}" -g "${GID}" www-data \
        && apk del shadow; \
    fi

RUN install-php-extensions xdebug

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# production - php-fpm with the application, vendor and assets baked in.
# ---------------------------------------------------------------------------
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false

COPY docker/php/php.prod.ini /usr/local/etc/php/conf.d/zz-prod.ini

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public ./public

RUN mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    # `artisan storage:link` needs a writable public/ and a booted app,
    # neither guaranteed here — this is the same relative link it creates.
    && ln -sf ../storage/app/public public/storage

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD SCRIPT_NAME=/fpm-ping SCRIPT_FILENAME=/fpm-ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# web - nginx serving the built public/ directory in production.
# ---------------------------------------------------------------------------
FROM nginx:stable-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=production /var/www/html/public /var/www/html/public

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget --quiet --tries=1 --spider http://127.0.0.1/up || exit 1

EXPOSE 80
