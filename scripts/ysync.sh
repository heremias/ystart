kubectl get pods | grep 'ysite' | awk '{print $1}' > yid.txt
yid=$(<yid.txt)
kubectl cp $yid:/var/www/web.tar ~/ystart/web.tar