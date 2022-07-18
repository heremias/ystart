git log -1 --pretty=%h > commit.txt
tag=$(<commit.txt)
docker build -t ystage:$tag .
docker tag ystage:$tag ystage:latest
docker run -d --name TEST -p 8082:80 ystage:latest
docker ps | grep 'TEST' | awk '{print $1}' > environments/test/.env
cid=$(<environments/test/.env)
docker exec $cid /bin/sh -c "/var/www/dsync.sh"