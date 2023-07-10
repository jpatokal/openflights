This test suite validates PHP calls and MySQL database content.

### Instructions

0. Install Composer from https://getcomposer.org/download/
1. `php composer.phar install`
2. Configure `config.php`
3. Run `php settings.php` to populate test user
4. Then run eg. `php login.php` to test login cases, _or_
5. Load `/test/server/test_all.php` in a browser to test all.

NOTE: Many cases are dependent on each other. Generally, run settings.php first, all cases afterward, and cleanup.php last.
