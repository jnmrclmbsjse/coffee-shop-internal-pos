# PHP 8.5 (matches the generated composer.lock) + Node 20 + Composer.
FROM php:8.5-fpm

# System deps
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions (intl fixes the host Filament blocker; pdo_pgsql for Postgres)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions intl pdo_pgsql pgsql bcmath gd zip exif opcache pcntl

# Node 20 LTS (for Vite / Filament asset builds)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Composer 2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dev-friendly PHP settings
COPY docker/app/php.dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

WORKDIR /var/www/html

# Default CMD (php-fpm) is inherited from the base image, listening on :9000.
