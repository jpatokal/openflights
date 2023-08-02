### Tools for users

- [`create.sql`](create.sql) - Create empty OpenFlights database schema
- [`import-arm.sql`](import-arm.sql) - Import and sync a fresh copy of Airline Route Mapper (ARM) data
- [`load-data.sql`](load-data.sql) - Import airlines.dat, airports.dat and routes.dat into OpenFlights database
- [`load-other-airport-dbs.sql`](load-other-airport-dbs.sql) - Import DAFIF and OurAirports, useful but not needed by site

### Site internal scripts

- [`elite-levels.sql`](elite-levels.sql) - Generate table of elite users
- [`merge-airports.sql`](merge-airports.sql) - Called by tools/merge-airports.sh for manual merging for duplicate ICAO codes
- [`top10.sql`](top10.sql) - Generate nightly Top 10 lists (`>data/top10.dat`)
- [`update-demo.sql`](update-demo.sql) - Update flights for "demo" user
