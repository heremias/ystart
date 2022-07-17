mkdir sync
tar -xvf configs.tar -C /var/www/sync
cd drupal
drush cim --partial --source=/var/www/sync
drush updatedb -y
drush cr
drush uli
