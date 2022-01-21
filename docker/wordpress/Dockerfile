FROM wordpress:fpm-alpine

ENV TIDEWAYS_VERSION 5.0.4

ADD https://github.com/tideways/php-xhprof-extension/archive/refs/tags/v$TIDEWAYS_VERSION.tar.gz .

RUN apk add $PHPIZE_DEPS \
    && mkdir xhprof \
    && tar -xzf v$TIDEWAYS_VERSION.tar.gz -C xhprof --strip-components=1 \
    && rm v$TIDEWAYS_VERSION.tar.gz \
    && ( \
        cd xhprof \
        && phpize \
        && ./configure \
        && make -j "$(nproc)" \
        && make install \
    ) \
    && rm -r xhprof \
    && docker-php-ext-enable tideways_xhprof \
    && docker-php-ext-install pdo pdo_mysql
