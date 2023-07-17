#!/bin/sh

# Docker-specific database privileging setup to allow password-less access
# from the local Docker network.
mysql -u root <<EOF
DROP USER IF EXISTS openflights;
CREATE USER openflights@'172.16.0.0/255.240.0.0';
GRANT ALL PRIVILEGES ON flightdb2.* TO openflights@'172.16.0.0/255.240.0.0';
EOF

# We mount the source code under /openflights in docker-compose to gain
# access to the data/ files referenced by load-data.sql.
cd /openflights

# Load the data from the .dat files in data/ into the DB.
mysql -u root -D flightdb2 --local-infile=1 <sql/load-data.sql

# Create the demo user with name = "demo", uid = 1 and an invalid password.
# The demo account is designed to be updated with `sql/update-demo.sql`.
#
# `openflights.js` and `map.php` use "demo" as a special name. The UI will
# prevent you from logging into this account even if you provide correct
# credentials.
#
# uid = 1 follows `config.php`'s default value for `$OF_DEMO_UID`. Unlike
# the name, this value can be changed and the database would have to be
# updated to match.
#
# Since the UI blocks login anyway, we set a password that will never match
# to completely disable API logins for good measure.
mysql -u root -D flightdb2 <<SQL
INSERT INTO users (name, password, uid)
VALUES ('demo', 'not-an-md5sum'), 1)
SQL
