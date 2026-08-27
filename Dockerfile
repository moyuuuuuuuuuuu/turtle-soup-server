FROM composer:2.8 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts \
    --ignore-platform-req=ext-gd \
    --ignore-platform-req=ext-redis

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction

FROM php:8.2.29-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd pcntl pdo_mysql zip \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && php -r 'foreach (["gd", "redis"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: {$extension}\n"); exit(1); } }' \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

WORKDIR /app
COPY --from=vendor /app /app

RUN mkdir -p runtime public/uploads \
    && chown -R www-data:www-data runtime public/uploads

EXPOSE 8787 8790
HEALTHCHECK --interval=30s --timeout=5s --retries=5 CMD php -r '$s=@fsockopen("127.0.0.1",8787,$e,$m,2); exit($s ? 0 : 1);'

CMD ["php", "webman", "start"]
