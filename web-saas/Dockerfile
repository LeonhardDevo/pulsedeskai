FROM node:22-alpine AS frontend-builder
WORKDIR /build
COPY package.json package-lock.json* ./
RUN npm install --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npx vite build

FROM php:8.3-cli

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libsqlite3-dev \
    libzip-dev \
    curl \
    && docker-php-ext-install pdo pdo_pgsql pdo_sqlite zip bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-scripts

COPY . .
COPY --from=frontend-builder /build/public/build ./public/build
RUN cp .env.example .env && php artisan key:generate --force

EXPOSE 8000

CMD ["sh", "-c", "php artisan migrate --seed --force && php artisan serve --host=0.0.0.0 --port=8000"]
