<?php
require_once('simpletest/unit_tester.php');
require_once('simpletest/reporter.php');
require_once('config.php');

print '<h1>OpenFlights PHP/SQL Test Suite</h1>';

// Create user
$test = &new TestSuite('Signup');
$test->addTestFile('signup.php');
$test->run(new HtmlReporter());

$test = &new TestSuite('Login');
$test->addTestFile('login.php');
$test->run(new HtmlReporter());

// Add flights
$test = &new TestSuite('Submit/Flights');
$test->addTestFile('submit.php');
$test->run(new HtmlReporter());

// "Analyze" stats
$test = &new TestSuite('Analyse (Stats)');
$test->addTestFile('stats.php');
$test->run(new HtmlReporter());

// Top 10 stats
$test = &new TestSuite('Top 10');
$test->addTestFile('top10.php');
$test->run(new HtmlReporter());

// Add airport
$test = &new TestSuite('Airport Search');
$test->addTestFile('apsearch.php');
$test->run(new HtmlReporter());

// Autocompletion results
$test = &new TestSuite('Autocomplete');
$test->addTestFile('autocomplete.php');
$test->run(new HtmlReporter());

// ...and cleanup after all that
$test = &new TestSuite('Cleanup');
$test->addTestFile('cleanup.php');
$test->run(new HtmlReporter());

?>
