docker run -d --name STAGE -p 8083:80 ghcr.io/heremias/sid-y-test:latest
docker ps | grep 'STAGE' | awk '{print $1}' > environments/stage/.env
cid=$(<environments/stage/.env)
docker exec $cid /bin/sh -c "/var/www/dsync.sh"
