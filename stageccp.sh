docker ps | grep 'STAGE' | awk '{print $1}' > cid.txt
stid=$(<stid.txt)
docker exec $cid /bin/sh -c "/var/www/concp.sh"
docker cp $cid:/var/www/drupal/configs.tar ~/ystart/configs.tar