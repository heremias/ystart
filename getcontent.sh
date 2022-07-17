docker ps | grep 'SITE' | awk '{print $1}' > cid.txt
cid=$(<cid.txt)
docker cp $cid:/var/www/content.tar ~/ystart/content.tar
