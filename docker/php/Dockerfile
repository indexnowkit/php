# Development image: the PHP the tests run on, with Composer inside, so dependency resolution and the test run share
# one runtime. Built on demand by bin/php and bin/composer (bin/lib/image.sh); PHP_VERSION selects the base image.
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && pecl install pcov \
    && docker-php-ext-enable pcov
ENV COMPOSER_HOME=/tmp/composer
WORKDIR /app
