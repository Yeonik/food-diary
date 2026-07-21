# A single self-contained image that builds the app and serves it. SQLite means
# no database service is needed — `docker compose up` gives a working diary.
FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip \
        libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd exif pdo_sqlite zip bcmath \
    && rm -rf /var/lib/apt/lists/*

# Stock CLI memory_limit (128M) is not enough for PHPStan; raise it so the
# documented `vendor/bin/phpstan analyse` runs without extra flags.
RUN printf "memory_limit=512M\nupload_max_filesize=16M\npost_max_size=20M\n" \
        > /usr/local/etc/php/conf.d/food-diary.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app
COPY . /app

RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
    && chmod +x docker/entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["docker/entrypoint.sh"]
