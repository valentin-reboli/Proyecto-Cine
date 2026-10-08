# Todo en un solo servicio: Caddy sirve el Angular en $PORT y reenvia /api y /docs
# a Laravel, que corre adentro del mismo contenedor en el puerto 8787.

FROM node:22-bookworm-slim AS front
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ .
RUN npx ng build --configuration production

FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

FROM php:8.4-cli-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=caddy:2-alpine /usr/bin/caddy /usr/bin/caddy

WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY backend/ .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

COPY frontend/Caddyfile /etc/caddy/Caddyfile
COPY --from=front /app/dist/cine-concordia/browser /srv

ENV BACKEND_URL=http://127.0.0.1:8787
CMD ["sh", "-c", "caddy run --config /etc/caddy/Caddyfile --adapter caddyfile & PORT=8787 exec sh docker/start.sh"]
