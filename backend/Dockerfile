FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev unzip \
    && docker-php-ext-install pdo_mysql mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

COPY . .

RUN sed -ri 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' \
    >> /etc/apache2/apache2.conf \
    && mkdir -p storage/logs storage/sessions \
    && chown -R www-data:www-data storage

EXPOSE 80

CMD ["apache2-foreground"]
