cd config
rm -rf *.*
cd -
tar -xvf config.tar -C ~/nhd/config
docker ps | grep 'SITE' | awk '{print $1}' > cid.txt
cid=$(<cid.txt)
docker exec $cid /bin/sh -c "/var/www/drupal/getuuid.sh" > uuid.txt
sed -i .bak -n 1p uuid.txt
sed -ri -e 's!'\''system.site:uuid'\'': !!g' uuid.txt
cd config
sed -n 4p system.site.yml > uid.txt
sed -ri -e 's!uuid: !!g' uid.txt
cd ../
mv uuid.txt config/uuid.txt
cd config
uuid=$(<uuid.txt)
uid=$(<uid.txt)
if test $uuid = $uid
then
  echo "Same $uid"
  else echo "Not same"
fi
echo "Diff $uuid"
sed -i .bak "4s/$uid/$uuid/" ~/nhd/config/system.site.yml
tar cvf config.tar *.*
mv config.tar ../
cd ../
docker cp ~/nhd/config.tar $cid:/var/www/config.tar
