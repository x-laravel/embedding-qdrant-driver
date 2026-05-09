ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli-bookworm

# Install system dependencies and PHP SQLite extension
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

WORKDIR /app
