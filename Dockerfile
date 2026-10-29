# syntax=docker/dockerfile:1.7
#
# One image for every process (web, horizon, scheduler, reverb). Targets:
#   dev   development image used by docker-compose.yml (code is bind-mounted)
#   prod  immutable production image (default target)
#
# Build:  docker build -t app:latest .
#         docker build --target dev -t app:dev .

ARG PHP_VERSION=8.4.27
ARG NODE_VERSION=22.22.2
ARG COMPOSER_VERSION=2.10.3
ARG EXTENSION_INSTALLER_VERSION=2.12.0

FROM composer:${COMPOSER_VERSION} AS composer-bin
FROM node:${NODE_VERSION}-bookworm-slim AS node-bin
FROM mlocati/php-extension-installer:${EXTENSION_INSTALLER_VERSION} AS extension-installer

#──────────────────────────────────────────────────────────────────────────────
# base: PHP runtime + extensions shared by every target
#──────────────────────────────────────────────────────────────────────────────
FROM php:${PHP_VERSION}-fpm-bookworm AS base

COPY --from=extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_pgsql pgsql redis intl zip opcache pcntl bcmath gd exif \
    && apt-get update \
    && apt-get install -y --no-install-recommends curl procps unzip ca-certificates tzdata \
    && rm -rf /var/lib/apt/lists/*

ENV PHP_FPM_MAX_CHILDREN=20

COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint
COPY --chmod=755 docker/healthcheck.sh /usr/local/bin/healthcheck

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]

#──────────────────────────────────────────────────────────────────────────────
# vendor: production PHP dependencies (cached layer keyed on composer.lock)
#──────────────────────────────────────────────────────────────────────────────
FROM base AS vendor

COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && rm -f bootstrap/cache/*.php

#──────────────────────────────────────────────────────────────────────────────
# frontend: Vite build. Needs PHP because Wayfinder and laravel-vue-i18n read
# the Laravel routes and lang files while bundling.
#──────────────────────────────────────────────────────────────────────────────
FROM base AS frontend

COPY --from=node-bin /usr/local/bin/node /usr/local/bin/node
COPY --from=node-bin /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY package.json package-lock.json .npmrc ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci --no-audit --no-fund

COPY --from=vendor /var/www/html ./
RUN npm run build:ssr

#──────────────────────────────────────────────────────────────────────────────
# dev: tooling for local development; source is bind-mounted at runtime
#──────────────────────────────────────────────────────────────────────────────
FROM base AS dev

ARG HOST_UID=1000
ARG HOST_GID=1000

RUN install-php-extensions xdebug \
    && apt-get update \
    && apt-get install -y --no-install-recommends git \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php-dev.ini "$PHP_INI_DIR/conf.d/zz-dev.ini"
COPY --from=composer-bin /usr/bin/composer /usr/bin/composer
COPY --from=node-bin /usr/local/bin/node /usr/local/bin/node
COPY --from=node-bin /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Files written from containers keep the host user's ownership.
RUN groupmod -o -g "${HOST_GID}" www-data \
    && usermod -o -u "${HOST_UID}" -g www-data www-data \
    && chown -R www-data:www-data /var/www

ENV XDEBUG_MODE=off \
    COMPOSER_HOME=/var/www/.composer \
    npm_config_cache=/var/www/.npm

USER www-data

CMD ["fpm"]

#──────────────────────────────────────────────────────────────────────────────
# prod: immutable runtime image (no compilers, no dev dependencies, no secrets)
#──────────────────────────────────────────────────────────────────────────────
FROM base AS prod

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx supervisor \
    && apt-get purge -y --auto-remove libc6-dev linux-libc-dev \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled /etc/nginx/conf.d/* \
    && mkdir -p /tmp/nginx /var/lib/nginx /var/log/nginx \
    && chown -R www-data:www-data /tmp/nginx /var/lib/nginx /var/log/nginx

COPY docker/php/php-prod.ini "$PHP_INI_DIR/conf.d/zz-prod.ini"
COPY docker/nginx/prod.conf /etc/nginx/nginx.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=vendor --chown=www-data:www-data /var/www/html ./
COPY --from=frontend --chown=www-data:www-data /var/www/html/public/build ./public/build
# Server-side rendering: the bundle plus the node binary that runs it (supervisord starts it).
COPY --from=frontend --chown=www-data:www-data /var/www/html/bootstrap/ssr ./bootstrap/ssr
COPY --from=node-bin /usr/local/bin/node /usr/local/bin/node

RUN rm -rf tests docker .github \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 CMD ["healthcheck"]

CMD ["web"]
