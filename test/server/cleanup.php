<?php

include_once(dirname(__FILE__) . '/config.php');

// Not an actual test, just cleaning up
class DeleteFlightsTest extends WebTestCase {
    public function test() {
        global $settings;

        $dbh = db_connect();
        $sth = $dbh->prepare("DELETE FROM flights WHERE uid IN (SELECT uid FROM users WHERE name=?)");
        $sth->execute([$settings["name"]]);
        echo $sth->rowCount() . " flights deleted\n";
    }
}

// Not an actual test, just cleaning up
class DeleteAirportTest extends WebTestCase {
    public function test() {
        global $settings;

        $dbh = db_connect();
        $sth = $dbh->prepare("DELETE FROM airports WHERE uid IN (SELECT uid FROM users WHERE name=?)");
        $sth->execute([$settings["name"]]);
        echo $sth->rowCount() . " airports deleted\n";
    }
}

// Not an actual test, just cleaning up
class DeleteAirlinesTest extends WebTestCase {
    public function test() {
        global $settings;

        $dbh = db_connect();
        $sth = $dbh->prepare("DELETE FROM airlines WHERE uid IN (SELECT uid FROM users WHERE name=?)");
        $sth->execute([$settings["name"]]);
        echo $sth->rowCount() . " airline(s) deleted\n";
    }
}

// Not an actual test, just cleaning up
class DeleteUserTest extends WebTestCase {
    public function test() {
        global $settings;

        $dbh = db_connect();
        $sth = $dbh->prepare("DELETE FROM users WHERE name=?");
        $sth->execute([$settings["name"]]);
        echo $sth->rowCount() . " user deleted\n";
    }
}
