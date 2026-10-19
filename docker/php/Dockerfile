FROM php:8.5-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    $PHPIZE_DEPS \
    zip \
    unzip \
    supervisor \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd sockets \
    && pecl install redis \
    && pecl install xdebug-3.5.1 \
    && docker-php-ext-enable redis xdebug \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Xdebug: PHPStorm host'ta 9003 dinler; container host.docker.internal üzerinden bağlanır
COPY xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Supervisord config dosyasını kopyala
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 9000

CMD ["php-fpm"]
