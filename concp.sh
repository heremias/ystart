cd drupal
drush cr
drush cex -y
cd /var/www/drupal/web/sites/default/files/config*/sync/
tar cvf configs.tar *.*
mv configs.tar /var/www/drupal

