source testx.sh
sh ./testx.sh | myVar=$(</dev/stdin)
echo $myVar