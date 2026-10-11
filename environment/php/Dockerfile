ARG APP_DOCKER_IMAGE
FROM ${APP_DOCKER_IMAGE} AS php-base

RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    icu-dev

RUN docker-php-ext-install pdo_pgsql bcmath gd zip intl

RUN apk add --no-cache --virtual .redis-build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .redis-build-deps
    
# --- DEV Stage ---
FROM php-base AS php-dev

RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug

WORKDIR /app

