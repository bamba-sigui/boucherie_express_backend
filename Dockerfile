FROM php:8.4-fpm

LABEL maintainer="Boucherie Express"

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libcurl4-openssl-dev \
    libzip-dev \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    libonig-dev \
    supervisor \
    && docker-php-ext-install -j$(nproc) pdo_mysql mysqli zip gd intl \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY . /var/www/html

RUN chmod +x /usr/local/bin/start-container

EXPOSE 80

ENTRYPOINT ["start-container"]