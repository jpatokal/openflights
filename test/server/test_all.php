<?php
require_once('../../vendor/lastcraft/simpletest/unit_tester.php');
require_once('../../vendor/lastcraft/simpletest/reporter.php');
require_once('config.php');

print '<h1>OpenFlights PHP/SQL Test Suite</h1>';

// Helper functions
$test = &new TestSuite('Helper');
$test->addFile('helper.php');
$test->run(new HtmlReporter());

// Create user
$test = &new TestSuite('Signup/Settings');
$test->addFile('settings.php');
$test->run(new HtmlReporter());

$test = &new TestSuite('Login');
$test->addFile('login.php');
$test->run(new HtmlReporter());

// Add flights
$test = &new TestSuite('Submit/Flights');
$test->addFile('submit.php');
$test->run(new HtmlReporter());

// Trips
$test = &new TestSuite('Trips');
$test->addFile('trip.php');
$test->run(new HtmlReporter());

// Map
$test = &new TestSuite('Map');
$test->addFile('map.php');
$test->run(new HtmlReporter());

// "Analyze" stats
$test = &new TestSuite('Analyse (Stats)');
$test->addFile('stats.php');
$test->run(new HtmlReporter());

// Top 10 stats
$test = &new TestSuite('Top 10');
$test->addFile('top10.php');
$test->run(new HtmlReporter());

// Add airport
$test = &new TestSuite('Airport Search');
$test->addFile('apsearch.php');
$test->run(new HtmlReporter());

// Add airline
$test = &new TestSuite('Airline Search');
$test->addFile('alsearch.php');
$test->run(new HtmlReporter());

// Autocompletion results
$test = &new TestSuite('Autocomplete');
$test->addFile('autocomplete.php');
$test->run(new HtmlReporter());

// Route maps
$test = &new TestSuite('Routes');
$test->addFile('routes.php');
$test->run(new HtmlReporter());

// Reset password
$test = &new TestSuite('Reset password');
$test->addFile('resetpw.php');
$test->run(new HtmlReporter());

// ...and cleanup after all that
$test = &new TestSuite('Cleanup');
$test->addFile('cleanup.php');
$test->run(new HtmlReporter());

?>
