This test suite validates PHP calls and MySQL database content.

### Instructions

0. Install Composer from https://getcomposer.org/download/
1. `php composer.phar install` (installs PHPUnit and friends into `vendor/`)
2. Configure `config.php` (server URL, database credentials, test data)
3. Start the OpenFlights server and database (e.g. `docker-compose up`)
4. Run the suite:
   `php test_all.php` — or, equivalently,
   `vendor/bin/phpunit --configuration test/server/phpunit.xml`

NOTE: Many cases are dependent on each other and on database state. The suite
runs the files in a fixed order (`phpunit.xml`), so run the full suite rather
than individual files. `settings.php` runs first (creating the test user);
`cleanup.php` runs last.

Additional, standalone test files (`planes.php`, `flights.php`, `import.php`)
are not part of the main suite. They can be run individually, e.g.:

    vendor/bin/phpunit --configuration test/server/phpunit.xml test/server/planes.php
