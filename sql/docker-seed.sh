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
mysql --local-infile=1 -u root -D flightdb2 <sql/load-data.sql
