git log -1 --pretty=%h > commit.txt
tag=$(<commit.txt)
docker build -t ylocal:$tag .
docker run -d --name DEV -p 8081:80 ylocal:$tag
docker ps | grep 'DEV' | awk '{print $1}' > environments/dev/.env
cid=$(<environments/dev/.env)

docker exec $cid /var/www/drupal -c "getuuid.sh" >> environments/dev/.env


tar -xvf configs.tar -C configs
cd configs
sed -in .sid 4p system.site.yml >> ../environments/stage/.env

tar cvf sync.tar *.*
docker cp sync.tar $cid:/var/www/sync.tar
docker exec $cid /bin/sh -c "/var/www/scripts/sync.sh"
