docker ps | grep 'DEV' | awk '{print $1}' > environments/dev/.env.dev
docker ps | grep 'STAGE' | awk '{print $1}' > environments/stage/.env.stage
kubectl get pods | grep 'ysite' | awk '{print $1}' > environments/prod/.env.prod

dev=$(<environments/dev/.env.dev)
stage=$(<environments/stage/.env.stage)
prod=$(<environments/stage/.env.prod)

echo ($dev . "dev")
echo ($stage . "stage")
echo ($prod . "prod")

docker exec $dev /bin/sh -c "/var/www/getuuid.sh" > dev.uuid
docker exec $stage /bin/sh -c "/var/www/getuuid.sh" > stage.uuid
kubectl exec $prod -- /bin/sh -c "`./getuuid.sh`" > prod.uuid