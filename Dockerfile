FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN APP_ENV=prod composer install --no-dev --optimize-autoloader --no-scripts

EXPOSE 10000

CMD php bin/console doctrine:migrations:migrate --no-interaction && php -S 0.0.0.0:10000 -t public
