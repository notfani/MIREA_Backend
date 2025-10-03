FROM php:8.2-apache

# Системные зависимости для pdo_pgsql и сборки PECL redis
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
       libpq-dev \
       autoconf \
       build-essential \
       pkg-config \
       ca-certificates \
       curl \
       tar \
       gzip \
       unzip \
    ; \
    update-ca-certificates; \
    docker-php-ext-install pdo pdo_pgsql; \
    curl -fsSL https://pecl.php.net/get/redis-6.0.2.tgz -o /tmp/redis.tgz || curl -fsSL https://pecl.php.net/get/redis-6.2.0.tgz -o /tmp/redis.tgz; \
    pecl install /tmp/redis.tgz; \
    docker-php-ext-enable redis; \
    a2enmod rewrite headers; \
    printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/override.conf; \
    a2enconf override; \
    apt-get purge -y --auto-remove autoconf build-essential pkg-config curl; \
    rm -rf /var/lib/apt/lists/* /tmp/pear /tmp/redis.tgz

COPY src /var/www/html
