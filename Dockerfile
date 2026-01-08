# syntax=docker/dockerfile:1.6

FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json ./
RUN composer install --no-dev --no-progress --prefer-dist --no-interaction --no-scripts

COPY . .
RUN composer install --no-dev --no-progress --prefer-dist --no-interaction --no-scripts
RUN composer dump-autoload --optimize

FROM php:8.2-apache AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install intl pdo_pgsql zip opcache \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN a2enmod rewrite \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY --from=vendor /app ./

RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

ENV APP_ENV=prod
ENV APP_DEBUG=0

EXPOSE 80
CMD ["apache2-foreground"]
