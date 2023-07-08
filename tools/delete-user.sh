#!/bin/bash
#
# Delete user and all their flights: data/delete-user.sh UID
#
source sql/mysql.sh
uid=$1
$MYSQL -e "select * from users where uid=$uid" flightdb2
read -p "You're about to delete user $uid completely. Proceed (y/N)? " input
if [ $input = "y" ]; then
  $MYSQL -e "set @uid = \"$uid\"; source sql/delete-user.sql;" flightdb2
  exit
else
  echo "Aborted."
fi
