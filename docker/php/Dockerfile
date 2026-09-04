# Development image: the PHP the tests run on, with Composer inside, so dependency resolution and the test run share
# one runtime. Built on demand by bin/php and bin/composer (bin/lib/image.sh); PHP_VERSION selects the base image.
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# intl and pcov: the same extensions the CI matrix has (setup-php: intl, pcov), so local test runs and coverage
# numbers match the CI ones (Punycode takes the ext-intl path when it is there).
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install intl \
    && pecl install pcov \
    && docker-php-ext-enable pcov
ENV COMPOSER_HOME=/tmp/composer
WORKDIR /app
