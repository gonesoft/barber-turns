FROM php:8.3-apache

# Enable required Apache modules and install PHP extensions.
RUN a2enmod rewrite \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-configure zip \
    && docker-php-ext-install zip \
    && docker-php-ext-install mysqli \
    && docker-php-ext-enable mysqli \
    && rm -rf /var/lib/apt/lists/*

# Copy Apache vhost and PHP configuration
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html
