FROM wordpress:fpm-alpine

RUN apk add $PHPIZE_DEPS \
    && pecl install xhprof-2.3.5 \
    && docker-php-ext-enable xhprof \
    && docker-php-ext-install pdo pdo_mysql
