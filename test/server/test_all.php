<?php

// Convenience wrapper that runs the full OpenFlights server test suite via
// PHPUnit. Equivalent to:
//
//     vendor/bin/phpunit --configuration test/server/phpunit.xml

$root = dirname(__FILE__) . '/../..';
$command = escapeshellarg($root . '/vendor/bin/phpunit')
    . ' --configuration ' . escapeshellarg(dirname(__FILE__) . '/phpunit.xml');

passthru($command, $exitCode);
exit($exitCode);
