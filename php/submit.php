<?php

include_once 'locale.php';
$uid = $_SESSION["uid"];
if (!$uid || empty($uid)) {
    printf("Not logged in, aborting");
    exit;
}

include_once 'helper.php';
include_once 'db_pdo.php';

$duration = $_POST["duration"];
$distance = $_POST["distance"];
$number = $_POST["number"];
$seat = $_POST["seat"];
$seat_type = $_POST["type"];
$class = $_POST["class"];
$reason = $_POST["reason"];
$registration = $_POST["registration"];
$trid = $_POST["trid"];
$fid = $_POST["fid"];
$mode = $_POST["mode"];
$note = stripslashes($_POST["note"]);
$param = $_POST["param"];
$multi = $_POST["multi"] ?? false;

if (!$mode || $mode == "") {
    $mode = "F";
}
# Nuke any stray tabs or spaces
if ($number) {
    $number = trim($number);
}
if ($registration) {
    $registration = trim($registration);
}
if ($seat) {
    $seat = trim($seat);
}

if (!isset($_POST["src_time"]) || empty($_POST["src_time"])) {
    $src_time = null;
} else {
    $src_time = $_POST["src_time"];
    # MySQL interprets 1000 as 00:10:00, so we force it to 100000 => 10:00:00
    if (strpos($src_time, ":") === false) {
        $src_time .= "00";
    }
}

// Compatibility with the existing openflights.js, which sets trid to the string NULL.
if ($trid == "NULL") {
    $trid = null;
}

// Validate user-entered information
$plid = null;
if ($param == "ADD" || $param == "EDIT") {
    $plane = trim($_POST["plane"]);

    // New planes can be created on the fly
    if (!empty($plane)) {
        $sql = "SELECT plid FROM planes WHERE name=? limit 1";
        $sth = $dbh->prepare($sql);
        $sth->execute(array($plane));
        $row = $sth->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // Match found, take its plid
            $plid = $row["plid"];
        } else {
            // No match, create new entry
            $sql = "INSERT INTO planes(name, public) VALUES(?, 'N')";
            $sth = $dbh->prepare($sql);
            if (!$sth->execute(array($plane))) {
                die('0;Adding new plane failed');
            }
            $plid = $dbh->lastInsertId();
        }
    }
}

$num_added = 0;
switch ($param) {
    case "ADD":
        // Can add multiple flights or just one
        if ($multi) {
            for ($idx = 0; $idx < $multi; $idx++) {
                $rows[$idx] = $idx + 1;
            }
        } else {
            $rows = array("");
        }
        # 8 columns per "row" in this string; non-bound variables in the last line.
        $sql = <<<QUERY
    INSERT INTO flights(
      uid, src_apid, src_date, src_time, dst_apid, duration, distance, registration,
      code, seat, seat_type, class, reason, note, plid, alid,
      trid, opp, mode,
      upd_time)
    VALUES(
      ?, ?, ?, ?, ?, ?, ?, ?,
      ?, ?, ?, ?, ?, ?, ?, ?,
      ?, ?, ?,
      NOW())
QUERY;
        $sth = $dbh->prepare($sql);
        foreach ($rows as $idx) {
            $src_date = $_POST["src_date" . $idx];
            $src_apid = $_POST["src_apid" . $idx];
            $dst_apid = $_POST["dst_apid" . $idx];
            $alid = trim($_POST["alid" . $idx]);
            if ($alid == 0) {
                // this should not be necessary, but just in case...
                $alid = -1;
            }

            // If either the distance or duration is missing, try to calculate it by airports.
            if (!$_POST["duration"] || !$_POST["distance"]) {
                list($calc_distance, $calc_duration) = gcDistance($dbh, $src_apid, $dst_apid);
                if (!$_POST["duration"]) {
                    $duration = $calc_duration;
                }
                if (!$_POST["distance"]) {
                    $distance = $calc_distance;
                }
            }

            list($src_apid, $dst_apid, $opp) = orderAirports($src_apid, $dst_apid);

            if ($idx != "" && $idx != "1") {
                $sql .= ",";
            }
            $success = $sth->execute(
                array(
                    $uid, $src_apid, $src_date, $src_time, $dst_apid, $duration, $distance, $registration,
                    $number, $seat, $seat_type, $class, $reason, $note, $plid, $alid,
                    $trid, $opp, $mode
                )
            );
            if (!$success) {
                # PDO will print a warning with the error.
                error_log("Could not insert flight for user $uid.");
                die('0;Database error when executing query.');
            }
            $num_added++;
        }
        break;

    case "EDIT":
        $src_date = $_POST["src_date"];
        $src_apid = $_POST["src_apid"];
        $dst_apid = $_POST["dst_apid"];
        $alid = trim($_POST["alid"]); // IE adds some whitespace crud to this!?
        if ($alid == 0) {
            // this should not be necessary, but just in case...
            $alid = -1;
        }
        list($src_apid, $dst_apid, $opp) = orderAirports($src_apid, $dst_apid);
    # 6 parameters per row
        $sql = <<<QUERY
UPDATE flights
SET src_apid=?, src_date=?, src_time=?, dst_apid=?, duration=?, distance=?,
    registration=?, code=?, seat=?, seat_type=?, class=?, reason=?,
    note=?, plid=?, alid=?, trid=?, opp=?, mode=?,
    upd_time=NOW()
WHERE fid=? AND uid=?
QUERY;
        $sth = $dbh->prepare($sql);
        $success = $sth->execute(
            array(
                $src_apid, $src_date, $src_time, $dst_apid, $duration, $distance,
                $registration, $number, $seat, $seat_type, $class, $reason, $note,
                $plid, $alid, $trid, $opp, $mode,
                $fid, $uid
            )
        );
        if (!$success) {
            # PDO will print a warning with the error.
            error_log("Could not insert flight for user $uid.");
            die('0;Database error when executing query.');
        }
        break;

    case "DELETE":
        // Check uid to prevent an evil logged-in hacker from deleting somebody else's flight
        $sql = "DELETE FROM flights WHERE uid=? AND fid=?";
        $sth = $dbh->prepare($sql);
        $success = $sth->execute(array($uid, $fid));
        if (!$success) {
            # PDO will print a warning with the error.
            error_log("Could not insert flight for user $uid.");
            die('0;Database error when executing query.');
        }
        break;

    default:
        die('0;Unknown operation ' . $param);
}

switch ($param) {
    case "DELETE":
        $code = 100;
        $msg = MODES[$mode] . " deleted.";
        break;

    case "ADD":
        $code = 1;
        if ($num_added == 1) {
            $msg = _("Added.");
        } else {
            $msg = sprintf(_("%s flights added."), $num_added);
        }
        break;

    case "EDIT":
        $code = 2;
        $msg = _("Edited.");
        break;
}

print $code . ";" . $msg;
