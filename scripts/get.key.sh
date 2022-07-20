get_key () {
    KEY=$1
    sed 's/"title"/\n"title"/g' | grep -w $KEY | awk -F',' '{print $0}'
}