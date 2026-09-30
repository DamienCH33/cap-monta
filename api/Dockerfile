# API Cap Monta : Symfony servi par FrankenPHP (Caddy + PHP dans un seul binaire).
# Une seule image pour trois services Railway : l'API, le worker Messenger et la tâche
# planifiée. Seule la commande de démarrage change (railway.*.json).

FROM dunglas/frankenphp:1-php8.4 AS base

# gd : redimensionnement des photos (JPEG, PNG, WebP). exif : sens des photos de téléphone
# (sans elle, elles arrivent couchées). redis : cache du calendrier et verrous.
RUN install-php-extensions pdo_pgsql intl redis zip gd exif opcache apcu

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

# Dépendances d'abord : cette couche ne se reconstruit que si composer.lock change.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer run-script --no-dev post-install-cmd \
    && php bin/console cache:warmup \
    && mkdir -p public/media/photos var \
    && chown -R www-data:www-data var public/media

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-cap-monta.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

# Railway donne le port dans $PORT (Caddyfile).
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
