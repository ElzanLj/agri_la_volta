# Local development only. Production runs on ordinary shared hosting (no Docker).
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && sed -ri '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# The document root stays at the repository root on purpose: it reproduces a shared
# hosting account where the document root cannot point to public/, so the root
# .htaccess fallback is exercised during development.
