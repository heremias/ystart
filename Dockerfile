FROM ghcr.io/heremias/y-base-y-stage:latest

RUN chmod 777 -R /var/www/drupal/web/sites/default/files
ADD /scripts /var/www
COPY ./scripts/ /var/www/scripts
RUN mv /var/www/scripts/cex.sh drupal
RUN chmod 777 /var/www/drupal/cex.sh
COPY configs.tar /var/www
COPY settings.php /var/www

#RUN ./scripts/load.sh

#EXPOSE 80
