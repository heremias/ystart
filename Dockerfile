FROM ghcr.io/heremias/y-base-y-master:latest

RUN chmod 777 -R /var/www/drupal/web/sites/default/files
COPY scripts /var/www

COPY load.sh /var/www
COPY concp.sh /var/www
COPY dsync.sh /var/www
COPY getuuid.sh /var/www
COPY configs.tar /var/www
COPY settings.php /var/www
COPY webcp.sh /var/www
RUN chmod -R 777 /var/www/scripts/
RUN chmod 777 webcp.sh
RUN chmod 777 load.sh
RUN chmod 777 concp.sh
RUN chmod 777 dsync.sh
RUN chmod 777 getuuid.sh

RUN ./load.sh

#EXPOSE 80
