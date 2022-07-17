docker ps | grep 'STAGE' | awk '{print $1}' > wid.txt
wid=$(<wid.txt)
docker exec $wid /bin/sh -c "/var/www/webcp.sh"
docker cp $wid:/var/www/web.tar ~/ystart/web.tar
tar -xvf web.tar -C web