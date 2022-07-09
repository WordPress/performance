FROM php:8-fpm-alpine

ARG LIVEPROFUI_VERSION

ENV AGGREGATOR_CONFIG_PATH=/var/www/html/src/config/aggregator_services.yaml

WORKDIR /var/www/html

ADD https://github.com/badoo/liveprof-ui/archive/refs/tags/$LIVEPROFUI_VERSION.tar.gz .
ADD services.yml .

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php \
    && rm composer-setup.php \
    && mv composer.phar /usr/bin/composer
    
RUN apk add $PHPIZE_DEPS \
    && pecl install xdebug \
    && echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.ini \
    && docker-php-ext-install pdo pdo_mysql

RUN tar -xzf $LIVEPROFUI_VERSION.tar.gz --strip-components=1 \
    && rm $LIVEPROFUI_VERSION.tar.gz \
    && cp services.yml src/config/aggregator_services.yaml \
    && composer install

EXPOSE 80

CMD [ "php", "-t", "/var/www/html/src/www", "-S", "0.0.0.0:80", "/var/www/html/src/www/router.php" ]
