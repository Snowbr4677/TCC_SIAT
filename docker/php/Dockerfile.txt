FROM php:8.5-fpm-alpine

# Instalar dependências do sistema
RUN apk add --no-cache \
    bash \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    git

# Instalar extensões do PHP necessárias (pdo e opcache já vêm nativas no PHP 8.5)
RUN docker-php-ext-install mysqli pdo_mysql intl zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
