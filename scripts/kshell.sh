kubectl get pods | grep 'ysite' | awk '{print $1}' > yid.txt
yid=$(<yid.txt)
kubectl exec --stdin --tty $yid -- /bin/bash