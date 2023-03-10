FROM php:7.4-apache

LABEL "de.idrinth.maintainer"="Björn 'Idrinth' Büttner"

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
RUN apt-get update && apt-get install curl libcurl4-openssl-dev cron -y
RUN docker-php-ext-install pdo_mysql
RUN a2enmod rewrite
RUN apt-get purge $PHPIZE_DEPS php-pear -y && apt autoremove -y
RUN rm -rf /var/lib/apt/lists/*
RUN echo "ServerSignature off\nServerTokens Prod" >> /etc/apache2/apache2.conf
RUN echo "expose_php = off" >> /usr/local/etc/php/php.ini

COPY --chown=www-data:www-data src /var/www/src
COPY --chown=www-data:www-data public /var/www/html
COPY --chown=www-data:www-data resources /var/www/resources
COPY --chown=www-data:www-data templates /var/www/templates
COPY --chown=www-data:www-data vendor /var/www/vendor
COPY --chown=www-data:www-data sessions /var/www/sessions

COPY setup/crontab /etc/cron.d/walled-secrets
RUN chmod 0644 /etc/cron.d/walled-secrets
RUN crontab /etc/cron.d/walled-secrets
CMD cron

VOLUME /var/www/keys

RUN touch /var/www/.env
RUN chown www-data:www-data /var/www/.env

CMD ["apache2-foreground"]