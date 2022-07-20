#source scripts/devsid.sh
#sh scripts/devsid.sh > ~/ystart/scripts/vars/sid
sid=$(<~/ystart/scripts/vars/sid)
sh ./scripts/sync.sh
sh ./scripts/devuuid.sh > ~/ystart/scripts/vars/uuid
uuid=$(<~/ystart/scripts/vars/uuid)
echo $cid $sid $uuid