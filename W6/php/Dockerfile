# -------- этап 1: ставим зависимости --------
FROM composer:2 as vendor

COPY composer.json /app/
RUN composer install --no-dev --no-scripts --prefer-dist \
    --ignore-platform-req=ext-gd


# -------- этап 2: итоговый образ --------
FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
        libpq-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_pgsql

# копируем vendor из предыдущего слоя
COPY --from=vendor /app/vendor /var/www/html/vendor

COPY 000-default.conf /etc/apache2/sites-available/
RUN a2enmod rewrite

WORKDIR /var/www/html