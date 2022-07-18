mkdir sync
tar -xvf sync.tar -C /var/www/sync
cd drupal
drush cim --partial --source=/var/www/sync
drush updatedb:status
drush updatedb -y
drush cr
drush uli
