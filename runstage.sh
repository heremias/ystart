docker run -d --name STAGE -p 8080:80 ghcr.io/heremias/sid-y-master:latest
docker ps | grep 'STAGE' | awk '{print $1}' > stid.txt
stid=$(<stid.txt)
docker exec $stid /bin/sh -c "/var/www/dsync.sh"
