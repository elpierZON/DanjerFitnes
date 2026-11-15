# DANJER FITNESS - imagen de producción para la EC2 (Docker Compose)
# Dos imágenes desde un mismo archivo:
#   - target "app": PHP-FPM con Laravel (también la usan worker y scheduler)
#   - target "web": nginx con los archivos estáticos de public/

# ---------- PHP-FPM + Laravel ----------
FROM php:8.2-fpm AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring zip bcmath opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-danjer.ini

WORKDIR /var/www/html

# Primero solo las dependencias, para aprovechar la caché de Docker
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Luego el código de la aplicación
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]

# ---------- nginx ----------
FROM nginx:1.27-alpine AS web

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public

EXPOSE 80
