# Multi-stage Dockerfile für Symfony Production
FROM php:8.2-fpm-alpine AS base

# System-Abhängigkeiten installieren
RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    nginx \
    supervisor \
    && docker-php-ext-install \
    intl \
    pdo_mysql \
    zip \
    opcache

# OPcache Konfiguration für Production
RUN { \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.enable_cli=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# PHP Production Konfiguration
RUN { \
    echo 'memory_limit=256M'; \
    echo 'upload_max_filesize=10M'; \
    echo 'post_max_size=10M'; \
    echo 'max_execution_time=60'; \
    echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/production.ini

# Composer installieren
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ===== Builder Stage =====
FROM base AS builder

# Composer Dependencies installieren (ohne dev)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Anwendungs-Code kopieren
COPY . .

# Autoloader und Symfony-Scripts ausführen
RUN composer dump-autoload --optimize --no-dev \
    && composer run-script post-install-cmd --no-interaction || true

# ImportMap Dependencies installieren und Assets kompilieren
RUN php bin/console importmap:install --env=prod \
    && php bin/console asset-map:compile --env=prod \
    && php bin/console cache:clear --env=prod \
    && php bin/console cache:warmup --env=prod

# Berechtigungen setzen
RUN chown -R www-data:www-data var/ public/

# ===== Production Stage =====
FROM base AS production

# Nginx Konfiguration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Supervisor Konfiguration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Anwendung von Builder kopieren
COPY --from=builder --chown=www-data:www-data /var/www/html /var/www/html

# Health-Check Script
COPY docker/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/healthcheck.sh

# Port für Nginx
EXPOSE 80

# Supervisor startet Nginx und PHP-FPM
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD /usr/local/bin/healthcheck.sh
