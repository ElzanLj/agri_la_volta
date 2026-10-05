# Local development only. Production runs on ordinary shared hosting (no Docker).
FROM php:8.2-apache

# unzip/git: Composer needs them to install dev tools (PHPUnit). Not needed in production.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && sed -ri '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# The document root stays at the repository root on purpose: it reproduces a shared
# hosting account where the document root cannot point to public/, so the root
# .htaccess fallback is exercised during development.
