FROM php:8.3-cli

WORKDIR /app

# System deps + PHP extensions commonly required by Symfony
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libpq-dev \
    && docker-php-ext-install intl opcache zip pdo_pgsql sockets \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
