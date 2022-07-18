git log -1 --pretty=%h > commit.txt
tag=$(<commit.txt)
docker build -t ylocal:$tag .
docker tag ylocal:$tag ylocal:latest
docker run -d --name DEV -p 8081:80 ylocal:latest
docker ps | grep 'DEV' | awk '{print $1}' > environments/dev/.env
cid=$(<environments/dev/.env)

docker exec $cid /bin/sh -c "/var/www/scripts/getuuid.sh" >> environments/test/.env
sid=$( tail -n 1 environments/test/.env )

tar -xvf configs.tar -C configs
cd configs

sed -in .bak 4p system.site.yml >> ../environments/dev/.env
uuid=$( tail -n 1 ../environments/dev/.env )
sed -i .bak "4s/uuid: $uuid/$sid/" system.site.yml
tar cvf sync.tar *.*
docker cp sync.tar $cid:/var/www/sync.tar
docker exec $cid /bin/sh -c "/var/www/sync.sh"
