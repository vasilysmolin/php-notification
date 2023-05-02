FROM composer:2 AS composer
FROM php:8.1.1-fpm-alpine

WORKDIR /var/www/php/

COPY --from=composer /usr/bin/composer /usr/bin/composer

# Установка зависимостей
RUN apk update && apk add --no-cache \
        imagemagick \
        imagemagick-libs \
        imagemagick-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        libheif-dev \
        g++ \
        make \
        autoconf

# Установка расширения imagick с поддержкой формата heic
RUN pecl install imagick-3.7.0 \
    && docker-php-ext-enable imagick \
    && apk del imagemagick-dev \
    && rm -rf /tmp/* /var/cache/apk/*

RUN set -xe \
    && apk add --no-cache \
    $PHPIZE_DEPS \
    ffmpeg \
    libavif-dev \
    libwebp-dev \
    libzip-dev \
    libwebp-tools \
    freetype-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    gmp-dev \
    mysql-client \
    && docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    && docker-php-ext-configure zip \
    && docker-php-ext-configure gmp \
    && docker-php-ext-configure pdo_mysql \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install -j$(nproc) zip \
    && docker-php-ext-install -j$(nproc) exif \
    && docker-php-ext-install -j$(nproc) pdo_mysql \
    && docker-php-ext-install -j$(nproc) gmp \
    && pecl install pcov \
    vim \

RUN apk add --no-cache supervisor tzdata
ENV TZ Europe/Moscow
ARG DOCKER_ENV
RUN if [ "$DOCKER_ENV" = "local" ]; \
	then apk add --update linux-headers && pecl install xdebug && docker-php-ext-enable xdebug;  \
	else echo "$DOCKER_ENV"; \
	fi

COPY /images/php/php.ini /usr/local/etc/php/php.ini
COPY /images/php/fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf

COPY composer.json .
COPY composer.lock .
RUN if [ "$DOCKER_ENV" = "local" ]; \
	  then composer install --prefer-dist --no-scripts;\
	else  \
       composer install --no-dev --prefer-dist --no-scripts;\
	fi

COPY . .


