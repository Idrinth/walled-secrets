FROM php:7.4-apache

LABEL "de.idrinth.maintainer"="Björn 'Idrinth' Büttner"

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
RUN apt-get update && apt-get install curl -y
RUN apt-get install libcurl4-openssl-dev
RUN docker-php-ext-install pdo_mysql
RUN a2enmod rewrite

COPY --chown=www-data:www-data src /var/www/src
COPY --chown=www-data:www-data public /var/www/html
COPY --chown=www-data:www-data resources /var/www/resources
COPY --chown=www-data:www-data templates /var/www/templates
COPY --chown=www-data:www-data vendor /var/www/vendor
COPY --chown=www-data:www-data sessions /var/www/sessions

VOLUME /var/www/keys

RUN touch /var/www/.env
RUN chown www-data:www-data /var/www/.env

CMD ["apache2-foreground"]