FROM php:8.3-fpm-alpine

# Dépendances système
RUN apk add --no-cache \
    bash git curl zip unzip \
    icu-dev libzip-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev

# Extensions PHP (gd + zip requis par phpoffice/phpspreadsheet)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo pdo_mysql intl zip gd opcache

COPY docker/php/uploads.ini $PHP_INI_DIR/conf.d/uploads.ini
COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/zz-opcache.ini
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .

RUN mkdir -p var/cache var/log && chmod -R 777 var/

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public/"]
