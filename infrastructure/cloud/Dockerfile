# syntax=docker/dockerfile:1
FROM composer:2.11.1@sha256:8de2184c6de224f4689e8f80ed5f5edaf0030b6b8b7839a22a2bcb6722ac87d0 AS composer
FROM php:8.4.14-cli-alpine3.22@sha256:69ee19e1bee51ad7d5b5e44ceaa317cc7904b22211de4fb0c85284ffec123341
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN --mount=type=secret,id=proxy_ca,required=true \
    SSL_CERT_FILE=/run/secrets/proxy_ca apk add --no-cache git unzip coreutils \
    && SSL_CERT_FILE=/run/secrets/proxy_ca apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install -j2 pcntl \
    && apk del .build-deps \
    && ln -s /bin/mv /usr/bin/mv
WORKDIR /workspace/work/LaravelSetupUtility
