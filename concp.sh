cd drupal
drush cr
drush cex -y
cd /var/www/drupal/web/sites/default/files/config*/sync/
tar cvf configs.tar *.*
mv configs.tar /var/www/drupal
cd /var/www/drupal/web/sites/default/files/content
tar cvf content.tar *.*
mv content.tar /var/www/content

