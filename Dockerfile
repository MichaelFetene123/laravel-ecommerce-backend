FROM php:8.3-cli-alpine

WORKDIR /var/www/html

# Install system dependencies & PHP extensions (including Redis and PCNTL)
RUN apk update && apk add --no-cache \
    curl \
    git \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    oniguruma-dev \
    icu-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath intl \
    && apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

EXPOSE 8000

# Start Laravel's built-in web server binding to all interfaces
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
