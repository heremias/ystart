mkdir sync
tar -xvf config.tar -C /var/www/sync
cd drupal
drush cim --partial --source=/var/www/sync
