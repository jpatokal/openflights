<?php

/**
 * Test-only stub for php/locale.php.
 *
 * The real php/locale.php starts a session and pulls in php/config.php and
 * php/db_pdo.php, which establish a database connection. The CLI test runner
 * must not do either of those things: it only needs the helper functions from
 * php/helper.php (format_apcode, format_airport, gcDistance, ...), and those
 * don't touch the database at load time.
 *
 * php/helper.php does `include_once 'locale.php'`. By prepending this stubs
 * directory to the include_path in test/server/config.php, that include
 * resolves here instead of to the real php/locale.php, so the DB connection is
 * never opened.
 *
 * The only symbol the helpers reference is the gettext `_()` alias; provide a
 * safe identity fallback in case the gettext extension has not registered it.
 */

if (!function_exists('_')) {
    function _($string) {
        return $string;
    }
}
