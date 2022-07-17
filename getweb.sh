docker ps | grep 'SITE' | awk '{print $1}' > cid.txt
cid=$(<cid.txt)
docker exec $cid /bin/sh -c "/var/www/webcp.sh"
docker cp $cid:/var/www/drupal/web.tar ~/ystart/web.tar