FROM php:8.3-fpm-alpine

# Add official PHP extension installer helper
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Install essential CLI tools and PHP extensions
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    && install-php-extensions pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 9000
CMD ["php-fpm"]