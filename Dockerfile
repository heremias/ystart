FROM ghcr.io/heremias/y-base-y-stage:8.1-apache

RUN chmod 777 -R /var/www/drupal/web/sites/default/files
ADD /scripts /var/www
COPY ./scripts/ /var/www/scripts

COPY configs.tar /var/www
COPY settings.php /var/www

RUN ./scripts/load.sh

#EXPOSE 80
