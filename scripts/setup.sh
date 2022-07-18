git log -1 --pretty=%h > commit.txt
tag=$(<commit.txt)
docker build -t ylocal:$tag .
docker tag ylocal:$tag ylocal:latest
docker run -d --name DEV -p 8081:80 ylocal:latest
docker ps | grep 'DEV' | awk '{print $1}' > environments/dev/.env
cid=$(<environments/dev/.env)
docker exec $cid /bin/sh -c "/var/www/dsync.sh"