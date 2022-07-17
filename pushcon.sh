cd configs
rm -rf *.*
cd -
tar -xvf configs.tar -C ~/ystart/configs
docker ps | grep 'SITE' | awk '{print $1}' > cid.txt
cid=$(<cid.txt)
docker exec $cid /bin/sh -c "/var/www/drupal/getuuid.sh" > uuid.txt
sed -i .bak -n 1p uuid.txt
sed -ri -e 's!'\''system.site:uuid'\'': !!g' uuid.txt
cd config
sed -n 4p system.site.yml > uid.txt
sed -ri -e 's!uuid: !!g' uid.txt
cd ../
mv uuid.txt configs/uuid.txt
cd configs
uuid=$(<uuid.txt)
uid=$(<uid.txt)
if test $uuid = $uid
then
  echo "Same $uid"
  else echo "Not same"
fi
echo "Diff $uuid"
sed -i .bak "4s/$uid/$uuid/" ~/start/configs/system.site.yml
tar cvf configs.tar *.*
mv configs.tar ../
cd ../
docker cp ~/ystart/configs.tar $cid:/var/www/configs.tar
