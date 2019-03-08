#!/bin/bash

ICINGAWEB2=http://localhost/icingaweb2

C=''
for i in "$@"; do
    case "$i" in
        *\'*)
            i=`printf "%s" "$i" | sed "s/'/'\"'\"'/g"`
            ;;
        *) : ;;
    esac
    C="$C '$i'"
done
printf "$C"

curl -X POST $ICINGAWEB2/eventtracker/push/msend -H "Content-Type: text/plain" --data-binary "$C"
