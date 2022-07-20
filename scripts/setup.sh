source ./get.key.sh
sh ./get.branches.sh
git log -1 --pretty=%h > commit.txt
tag=$(<commit.txt)
echo -n "Y Number: "
read ynumber
cat branches.json | get_key $ynumber
docker build -t ylocal:$tag .
docker run -d --name $ynumber-DEV -p 8081:80 ylocal:$tag
cid=$(. ./dev.get.cid.sh $ynumber)
#sh ./dev.get.cid.sh $ynumber > vars/cid
#cid=$(<vars/cid)

tar -xf configs.tar -C ../configs
source ~/ystart/scripts/dev.get.sync.sh

source ~/ystart/scripts/dev.get.sid.sh $cid
sed -in .sid 4p system.site.yml >> ../environments/stage/.env

#tar cvf sync.tar *.*
#docker cp sync.tar $cid:/var/www/sync.tar
#docker exec $cid /bin/sh -c "/var/www/scripts/sync.sh"
echo $ynumber $cid $uuid 
