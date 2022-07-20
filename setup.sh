git log -1 --pretty=%h > commit.txt
tag=$(<commit.txt)
docker build -t ylocal:$tag .
docker run -d --name DEV -p 8081:80 ylocal:$tag
docker ps | grep 'DEV' | awk '{print $1}' > environments/dev/cid
cid=$(<environments/dev/cid)

docker exec $cid /bin/sh -c "./var/www/drupal/getuuid.sh" > environments/dev/sid
sid=$(<environments/dev/sid)

tar -xvf configs.tar -C configs

cd configs
parse_yaml () {
   local prefix=$2
   local s='[[:space:]]*' w='[a-zA-Z0-9_]*' fs=$(echo @|tr @ '\034')
   sed -ne "s|^\($s\):|\1|" \
        -e "s|^\($s\)\($w\)$s:$s[\"']\(.*\)[\"']$s\$|\1$fs\2$fs\3|p" \
        -e "s|^\($s\)\($w\)$s:$s\(.*\)$s\$|\1$fs\2$fs\3|p"  $1 |
   awk -F$fs '{
      indent = length($1)/2;
      vname[indent] = $2;
      for (i in vname) {if (i > indent) {delete vname[i]}}
      if (length($3) > 0) {
         vn=""; for (i=0; i<indent; i++) {vn=(vn)(vname[i])("_")}
         printf("%s%s%s=\"%s\"\n", "'$prefix'",vn, $2, $3);
      }
   }'
}

eval $(parse_yaml system.site.yml)
echo $uuid > ../environments/dev/uuid
uuid==$(<../environments/dev/uuid)