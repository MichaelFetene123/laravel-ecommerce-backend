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
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath intl opcache \
    && apk del $PHPIZE_DEPS

# Configure PHP & OPcache for CLI server performance on Docker volumes
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.save_comments=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "memory_limit=512M" > /usr/local/etc/php/conf.d/custom-php.ini \
    && echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/custom-php.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/custom-php.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

EXPOSE 8000

# Start Laravel's built-in web server binding to all interfaces
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
