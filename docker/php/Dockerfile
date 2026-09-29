FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

# System packages: poppler-utils provides /usr/bin/pdftotext for spatie/pdf-to-text.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip poppler-utils \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions: pgsql (pgvector), mysql, sqlite (tests use :memory:), plus Laravel essentials.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql pdo_mysql pdo_sqlite zip intl bcmath pcntl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Match www-data to the host user so bind-mounted files stay writable, and re-own
# its HOME (/var/www) so composer's cache and psysh (tinker) config can be written.
# vendor/ is pre-created so the named volume mounted there inherits www-data ownership.
RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g www-data www-data \
    && mkdir -p /var/www/html/vendor \
    && chown -R www-data:www-data /var/www \
    && git config --system --add safe.directory /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-docwise.ini
COPY docker/php/entrypoint.sh /usr/local/bin/docwise-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/docwise-entrypoint && chmod +x /usr/local/bin/docwise-entrypoint

WORKDIR /var/www/html
USER www-data

ENTRYPOINT ["docwise-entrypoint"]
CMD ["php-fpm"]
