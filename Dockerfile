FROM ghcr.io/heremias/y-base-y-master:latest

RUN chmod 777 -R /var/www/drupal/web/sites/default/files
ADD /scripts /var/www
COPY scripts/* /var/www/scripts

COPY load.sh /var/www
RUN chmod 777 load.sh
COPY concp.sh /var/www
COPY dsync.sh /var/www
COPY getuuid.sh /var/www
COPY configs.tar /var/www
COPY settings.php /var/www
COPY webcp.sh /var/www

RUN chmod 777 webcp.sh
RUN chmod 777 concp.sh
RUN chmod 777 dsync.sh
RUN chmod 777 getuuid.sh

RUN ./load.sh

#EXPOSE 80
