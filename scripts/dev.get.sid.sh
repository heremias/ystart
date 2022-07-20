CID=$1
#sh ~/ystart/scripts/dev.get.cid.sh $YNUM> ~/ystart/scripts/vars/cid
#echo $cid
#cid=$(<~/ystart/scripts/vars/cid)
docker exec -i $CID /bin/sh < ~/ystart/scripts/get.uuid.sh