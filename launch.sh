git log -1 --pretty=%h > commit.txt
tag=$(<commit.txt)
docker build -t ysite:$tag .
docker tag ysite:$tag ysite:latest
docker run -d --name SITE -p 8081:80 ysite:latest
docker ps | grep 'SITE' | awk '{print $1}' > sid.txt
sid=$(<sid.txt)
