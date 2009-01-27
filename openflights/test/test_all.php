<?php
require_once('simpletest/unit_tester.php');
require_once('simpletest/reporter.php');
require_once('config.php');

print '<h1>OpenFlights PHP/SQL Test Suite</h1>';

$test = &new TestSuite('Signup');
$test->addTestFile('signup.php');
$test->run(new HtmlReporter());

$test = &new TestSuite('Login');
$test->addTestFile('login.php');
$test->run(new HtmlReporter());

$test = &new TestSuite('Airport Search');
$test->addTestFile('apsearch.php');
$test->run(new HtmlReporter());

$test = &new TestSuite('Cleanup');
$test->addTestFile('cleanup.php');
$test->run(new HtmlReporter());

?>
