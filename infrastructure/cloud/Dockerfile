# syntax=docker/dockerfile:1
FROM composer:2.8.12@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c AS composer
FROM php:8.4.14-cli-alpine3.22@sha256:69ee19e1bee51ad7d5b5e44ceaa317cc7904b22211de4fb0c85284ffec123341
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN --mount=type=secret,id=proxy_ca,required=true \
    SSL_CERT_FILE=/run/secrets/proxy_ca apk add --no-cache git unzip coreutils \
    && SSL_CERT_FILE=/run/secrets/proxy_ca apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install -j2 pcntl \
    && apk del .build-deps \
    && ln -s /bin/mv /usr/bin/mv
WORKDIR /workspace/work/LaravelSetupUtility
