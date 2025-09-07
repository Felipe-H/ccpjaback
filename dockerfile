FROM php:8.2-cli
RUN apt-get update \
  && apt-get install -y git unzip libpq-dev \
  && docker-php-ext-install pdo pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

RUN chown -R www-data:www-data storage bootstrap/cache

ENV PORT=8000
COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh
CMD ["/docker-entrypoint.sh"]
