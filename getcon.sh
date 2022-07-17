docker ps | grep 'SITE' | awk '{print $1}' > cid.txt
cid=$(<cid.txt)
docker exec $cid /bin/sh -c "/var/www/concp.sh"
docker cp $cid:/var/www/drupal/configs.tar ~/ystart/configs.tar
