FROM composer:2 AS composer
FROM php:8.4-cli-alpine
RUN apk add --no-cache git unzip icu-dev oniguruma-dev libpq-dev postgresql17-client sqlite-dev libxml2-dev libjpeg-turbo-dev libpng-dev libwebp-dev libzip-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j2 bcmath intl mbstring pdo_pgsql pdo_mysql pdo_sqlite pcntl gd zip exif dom xml xmlreader xmlwriter simplexml
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
ENV COMPOSER_HOME=/tmp/composer
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]