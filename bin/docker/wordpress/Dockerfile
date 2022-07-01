FROM wordpress:fpm-alpine

ARG TIDEWAYS_VERSION
ARG WPCLI_VERSION

ADD https://github.com/tideways/php-xhprof-extension/archive/refs/tags/v$TIDEWAYS_VERSION.tar.gz .
ADD https://github.com/wp-cli/wp-cli/releases/download/v$WPCLI_VERSION/wp-cli-$WPCLI_VERSION.phar .
ADD https://raw.githubusercontent.com/WPTT/theme-unit-test/master/themeunittestdata.wordpress.xml /var/www/themeunittestdata.wordpress.xml

RUN apk add $PHPIZE_DEPS \
    && mkdir -p /tmp/xhprof \
    && tar -xzf v$TIDEWAYS_VERSION.tar.gz -C /tmp/xhprof --strip-components=1 \
    && rm v$TIDEWAYS_VERSION.tar.gz \
    && docker-php-ext-configure /tmp/xhprof \
    && docker-php-ext-install /tmp/xhprof \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -r /tmp/xhprof

RUN chmod +x wp-cli-$WPCLI_VERSION.phar \
    && mv wp-cli-$WPCLI_VERSION.phar /usr/bin/wp
