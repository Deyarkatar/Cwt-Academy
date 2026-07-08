FROM php:8.4-fpm-alpine

# Install production dependencies
RUN apk add --no-cache \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    libxml2-dev \
    oniguruma-dev \
    linux-headers

# Install PHP extensions
# Keep build dependencies for the entire layer so pecl can compile redis,
# then purge them at the end to keep the image small.
RUN apk add --no-cache --virtual .php-build-deps $PHPIZE_DEPS linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    exif \
    pcntl \
    bcmath \
    gd \
    opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .php-build-deps

# Install Composer (pinned release for reproducible, auditable builds)
COPY --from=composer:2.8.5 /usr/bin/composer /usr/bin/composer

# Copy PHP production config
COPY docker/php/php.ini /usr/local/etc/php/php.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Set working directory
WORKDIR /var/www/html

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm"]
