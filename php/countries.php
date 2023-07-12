<?php

session_start();
include_once 'db_pdo.php';

// List of all countries
$sql = "SELECT iso_code AS code, name FROM countries ORDER BY name";
$rows = [];
foreach ($dbh->query($sql) as $row) {
    $rows[] = sprintf("%s;%s", $row["code"], $row["name"]);
}
echo implode("\n", $rows);
