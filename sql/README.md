### Tools for users

- [`create.sql`](create.sql) - Create an empty OpenFlights database schema
- [`import-arm.sql`](import-arm.sql) - Import and sync a fresh copy of [`Airline Route Mapper (ARM) data`](http://arm.64hosts.com/AirlineRouteMapper.zip)
- [`load-data.sql`](load-data.sql) - Import [`airlines.dat`](../data/airlines.dat), [`airports.dat`](../data/airports.dat) and [`routes.dat`](../data/routes.dat) into the OpenFlights database
- [`load-other-airport-dbs.sql`](load-other-airport-dbs.sql) - Import DAFIF and OurAirports; useful but not needed by site

### Site internal scripts

- [`merge-airports.sql`](merge-airports.sql) - Called by [`merge-airports.sh`](../tools/merge-airports.sh) for manual merging for duplicate ICAO codes
- [`top10.sql`](top10.sql) - Generate nightly "Top 10" lists (`>data/top10.dat`)
- [`update-demo.sql`](update-demo.sql) - Update flights for "demo" user
