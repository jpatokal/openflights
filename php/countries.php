<?php

session_start();
include 'db_pdo.php';

// List of all countries
$sql = "SELECT iso_code AS code, name FROM countries ORDER BY name";
$first = true;
foreach ($dbh->query($sql) as $row) {
    if ($first) {
        $first = false;
    } else {
        printf("\n");
    }
    printf("%s;%s", $row["code"], $row["name"]);
}
