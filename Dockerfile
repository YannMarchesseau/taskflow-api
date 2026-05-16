FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libicu-dev \
    && docker-php-ext-install intl pdo_pgsql opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN APP_ENV=prod composer install --no-dev --optimize-autoloader --no-scripts

EXPOSE 10000

CMD php bin/console doctrine:schema:update --force && php -S 0.0.0.0:10000 -t public
