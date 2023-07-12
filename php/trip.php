<?php

include_once 'locale.php';
include_once 'db_pdo.php';

$type = $_POST["type"];
$trid = $_POST["trid"] ?? null;

if ($type != "NEW" && (!$trid || $trid == 0)) {
    die('0;Trip ID ' . $trid . ' invalid');
}

$uid = $_SESSION["uid"];
if (!$uid || empty($uid)) {
    die('0;' . _("Your session has timed out, please log in again."));
}

/**
 * @param $res bool
 * @param $name string
 */
function failIfFalse($res, $name) {
    if (!$res) {
        die('0;Operation on trip ' . $name . ' failed.');
    }
}

$name = $_POST["name"];
$url = $_POST["url"];
$privacy = $_POST["privacy"];

switch ($type) {
    case "NEW":
        // Create a new trip
        $sth = $dbh->prepare("INSERT INTO trips(name, url, public, uid) VALUES(?, ?, ?, ?)");
        $success = $sth->execute([$name, $url, $privacy, $uid]);
        break;

    case "EDIT":
        // Edit an existing trip
        $sth = $dbh->prepare("UPDATE trips SET name = ?, url = ?, public = ? WHERE uid = ? AND trid = ?");
        $success = $sth->execute([$name, $url, $privacy, $uid, $trid]);
        break;

    case "DELETE":
        // Assign flights with this trip id to null and then delete the trip
        $sth = $dbh->prepare("UPDATE flights SET trid = NULL WHERE trid = ? AND uid = ?");
        failIfFalse($sth->execute([$trid, $uid]), $name);

        $sth = $dbh->prepare("DELETE FROM trips WHERE trid = ? AND uid = ?");
        $success = $sth->execute([$trid, $uid]);
        break;

    default:
        die('0;Unknown operation ' . $type);
}

failIfFalse($success, $name);

if ($sth->rowCount() !== 1) {
    if ($type == "EDIT") {
        die("0;No updates were performed, was anything changed?");
    }
    // DELETE
    die("0;No matching trip found");
}

switch ($type) {
    case "NEW":
        $trid = $dbh->lastInsertId();
        printf("1;%s;" . _("Trip successfully created"), $trid);
        break;

    case "DELETE":
        printf("100;%s;" . _("Trip successfully deleted"), $trid);
        break;

    default:
        printf("2;%s;" . _("Trip successfully edited."), $trid);
        break;
}
