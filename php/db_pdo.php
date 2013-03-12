<?php
include 'config.php';

// Make the PDO and the legacy database drivers mutually exclusive.
if (isset($db)) {
    die('Multiple DB handlers instantiated; aborting.');
}

$dbh = null;
try {
    $dbh = new PDO("mysql:host=$host;dbname=$dbname", $user, $password, array(
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"
    ));
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
} catch(PDOException $e) {
    die('Error;Unable to connect to database: ' . $e->getMessage());
}
?>
