FROM wordpress:fpm-alpine

RUN apk add $PHPIZE_DEPS
RUN pecl install xhprof-2.3.5
RUN docker-php-ext-enable xhprof
