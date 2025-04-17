##
## docker build -t primeimg/php-websocket-client .
## docker push primeimg/php-websocket-client
##

## Stage 1 - Composer install
FROM composer:latest AS composer

RUN mkdir -p /app
COPY ./composer.* /app
RUN cd /app && composer install --prefer-dist --no-scripts --no-progress --no-interaction -o

## Stage 2 - PHP application
FROM php:8.4-cli

RUN mkdir -p /app
COPY ./ /app
COPY --from=composer /app/vendor /app/vendor

CMD ["/usr/local/bin/php", "/app/bin/demo.php"]