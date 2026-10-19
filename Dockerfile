FROM composer:2 AS composer-bin

FROM php:8.3-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libsqlite3-dev unzip git \
    && docker-php-ext-install -j"$(nproc)" gd pdo_sqlite \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod headers

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage \
    && chown -R www-data:www-data storage \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride None' \
        '    Require all granted' \
        '</Directory>' > /etc/apache2/conf-available/formlab.conf \
    && a2enconf formlab \
    && sed -ri 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/hide-version.conf \
    && a2enconf hide-version \
    && printf "expose_php=Off\n" > /usr/local/etc/php/conf.d/hide-php.ini

EXPOSE 80
