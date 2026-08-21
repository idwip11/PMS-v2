FROM php:8.3-cli-alpine

RUN apk add --no-cache icu-dev libzip-dev freetype-dev libjpeg-turbo-dev libpng-dev unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl mysqli pdo_mysql zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
COPY docker/php.ini /usr/local/etc/php/conf.d/homiq.ini

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p writable/cache writable/logs writable/session writable/uploads writable/debugbar \
    && chown -R www-data:www-data writable \
    && chmod -R ug+rwX writable

EXPOSE 80

CMD ["php", "-S", "0.0.0.0:80", "-t", "public", "docker/router.php"]
