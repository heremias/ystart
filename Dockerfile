FROM ghcr.io/heremias/y-base-y-master:latest

RUN chmod 777 -R /var/www/drupal/web/sites/default/files
COPY mods.sh /var/www
COPY drushcp.sh /var/www
COPY dsync.sh /var/www
COPY getuuid.sh /var/www
COPY configs.tar /var/www
COPY settings.php /var/www

RUN chmod 777 mods.sh
RUN chmod 777 drushcp.sh
RUN chmod 777 dsync.sh
RUN chmod 777 getuuid.sh

RUN ./mods.sh

#EXPOSE 80
