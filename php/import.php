<?php

require_once "locale.php";
require_once "db_pdo.php";
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>OpenFlights: <?php echo _("Import") ?></title>
    <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">

    <script type="text/javascript" src="/js/Gettext.js"></script>
    <script type="text/javascript" src="/js/apsearch.js"></script>
  </head>

  <body>
    <div id="contexthelp">

  <h1>OpenFlights: <?php echo _("Import") ?></h1>
<?php
$uid = $_SESSION["uid"];
if (!$uid || empty($uid)) {
    die_nicely(_("Not logged in, aborting"));
}

require_once '../vendor/autoload.php';
include_once 'helper.php';

$posMap = array("Window" => "W", "Middle" => "M", "Aisle" => "A", "" => "");
$classMap = array("Economy" => "Y", "Prem.Eco" => "P", "Business" => "C", "First" => "F", "" => "");
$reasonMap = array("Business" => "B", "Personal" => "L", "Crew" => "C", "Other" => "O", "" => "");

function nth_text($element, $n) {
    $xpath = new DOMXPath($element->ownerDocument);
    return nbsp_trim($xpath->query('.//text()', $element)->item($n)->textContent);
}

function text_count($element) {
    $xpath = new DOMXPath($element->ownerDocument);
    return $xpath->query('.//text()', $element)->length;
}

function nbsp_trim($string) {
    return trim($string, "\xC2\xA0"); // UTF-8 NBSP
}

/**
 * Validate date field
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $type string FM for FlightMemory date format
 * @param $date string Must be one of YYYY, MM-DD-YYYY (FM only), YYYY-MM-DD (CSV only), MM/DD/YYYY or DD.MM.YYYY
 * @return array Date and color
 */
function check_date($dbh, $type, $date) {
    if (strlen($date) == 4) {
        $date = "01.01." . $date;
    }
    if (strstr($date, "-")) {
        if ($type == "FM") {
            $dateFormat = "%m-%d-%Y";
        } else {
            $dateFormat = "%Y-%m-%d";
        }
    } elseif (strstr($date, "/")) {
        $dateFormat = "%m/%d/%Y";
    } else {
        $dateFormat = "%d.%m.%Y";
    }
    $sth = $dbh->prepare("SELECT STR_TO_DATE(?, ?)");
    $sth->execute([$date, $dateFormat]);
    $db_date = $sth->fetchColumn(0);
    if ($db_date == "") {
        $date = null;
        $color = "#faa";
    } else {
        $color = "#fff";
        $date = $db_date;
    }
    return array($date, $color);
}

/**
 * Validate that this code/name match an airport
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $code string IATA or ICAO code
 * @param $name string Airport name
 * @return array Airport ID, Code or location, and color
 */
function check_airport($dbh, $code, $name) {
    switch (strlen($code)) {
        case 3:
            $params = [$code];
            $sql = "select apid,city,country from airports where iata=?";
            break;

        case 4:
            $params = [$code];
            $sql = "select apid,city,country from airports where icao=?";
            break;

        default:
            $params = [$name . '%'];
            $sql = "select apid,city,country from airports where name like ?";
            break;
    }

    $sth = $dbh->prepare($sql);
    $sth->execute($params);
    switch ($sth->rowCount()) {
        // No match
        case 0:
            $apid = null;
            $color = "#faa";
            break;

        // Solitary match
        case 1:
            $apid = $sth->fetchColumn(0);
            $color = "#fff";
            break;

        // Multiple matches
        default:
            $dbrow = $sth->fetch();
            $apid = $dbrow["apid"];
            $code = $code . "<br><small>" . $dbrow["city"] . "," . $dbrow["country"] . "</small>";
            $color = "#ddf";
    }
    return array($apid, $code, $color);
}

/**
 * Validate that this flight number/airline name are found in DB
 * If flight number starts with an IATA code, match that (and double-check it against name)
 * Else match first word of airline name
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $number string Flight number
 * @param $airline string Airline name
 * @param $uid string User ID
 * @param $history string If "yes", ignore codes and ignore errors
 * @return array Airline ID, airline name, color
 */
function check_airline($dbh, $number, $airline, $uid, $history) {
    $code = substr($number, 0, 2);
    $isAlpha = preg_match('/[a-zA-Z0-9]{2}/', $code) && ! preg_match('/[0-9]{2}/', $code);
    if ($airline == "" && ! $isAlpha) {
        $airline = _("Unknown") . "<br><small>(" . _("was:") . " " . _("No airline") . ")</small>";
        $color = "#ddf";
        $alid = -1;
    } else {
        // is alphanumeric, but not all numeric? then it's probably an airline code
        if ($isAlpha && $history != "yes") {
            $params = [$code];
            $sql = "select name,alias,alid from airlines where iata=? order by name";
        } else {
            $airlinepart = explode(' ', $airline);
            if ($airlinepart[0] == 'Air') {
                $part = 'Air ' . $airlinepart[1] . '%';
            } else {
                $part = $airlinepart[0] . '%';
            }
            $params = [$part, $part, $airline];
            $sql = "select name,alias,alid from airlines where ((name like ? or alias like ?) and (iata != '')) or (name = ?) order by frequency desc;";
        }
        $sth = $dbh->prepare($sql);
        $sth->execute($params);

        // validate the airline/code against the DB
        switch ($sth->rowCount()) {
            // No match, add as new if we have a name for it, else return error
            case "0":
                if ($airline != "") {
                    $color = "#fdd";
                    $alid = -2;
                } else {
                    $color = "#faa";
                    $alid = null;
                }
                break;

            // Solitary match
            case "1":
                $dbrow = $sth->fetch();
                if ($airline != "" && (strcasecmp($dbrow['name'], $airline) == 0 || strcasecmp($dbrow['alias'], $airline) == 0)) {
                    // Exact match
                    $color = "#fff";
                    $airline = $dbrow['name'];
                    $alid = $dbrow['alid'];
                } else {
                    // Not an exact match
                    if ($history == "yes") {
                        $color = "#fdd";
                        $alid = -2;
                    } else {
                        $color = "#ddf";
                        $airline = $dbrow['name'] . "<br><small>(" . _("was:") . " " . $airline . ")</small>";
                        $alid = $dbrow['alid'];
                    }
                }
                break;

            // Many matches, default to first with a warning if we can't find an exact match
            default:
                $color = "#ddf";
                $first = true;
                foreach ($sth as $dbrow) {
                    $isMatch = $airline != "" && ((strcasecmp($dbrow['name'], $airline) == 0) ||
                    (strcasecmp($dbrow['alias'], $airline) == 0));
                    if ($first || $isMatch) {
                        if ($isMatch) {
                            $color = "#fff";
                        }
                        if ($first) {
                            $first = false;
                        }
                        $new_airline = $dbrow['name'];
                        $alid = $dbrow['alid'];
                    }
                }
                // No match and in historical mode? Add it as new
                if ($history == "yes" && $color == "#ddf") {
                    $color = "#fdd";
                    $alid = -2;
                } else {
                    $airline = $new_airline;
                }
        }
    }
    return array($alid, $airline, $color);
}

/**
 * Validate that this plane is in DB
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $plane string Plane ID
 * @return array Plaie ID, color
 */
function check_plane($dbh, $plane) {
    // If no plane set, return OK
    if (!$plane || $plane == "") {
        return array(null, "#fff");
    }

    $sql = "select plid from planes where name=?";
    $sth = $dbh->prepare($sql);
    $sth->execute([$plane]);
    if ($sth->rowCount() == 1) {
        $plid = $sth->fetchColumn(0);
        $color = "#fff";
    } else {
        $plid = "-1"; // new plane
        $color = "#fdd";
    }
    return array($plid, $color);
}

/**
 * Validate that the importing user owns this trip
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $uid string User ID
 * @param $trid string Trip ID
 * @return array Trip ID, color
 */
function check_trip($dbh, $uid, $trid) {
    // If no trip set, return OK
    if (!$trid || $trid == "") {
        return array(null, "#fff");
    }

    $sql = "select uid from trips where trid=?";
    $sth = $dbh->prepare($sql);
    $sth->execute([$trid]);
    if ($sth->rowCount() == 1) {
        if ($uid == $sth->fetchColumn(0)) {
            $color = "#fff";
        } else {
            $color = "#faa";
        }
    } else {
        $color = "#faa";
    }
    return array($trid, $color);
}

function die_nicely($msg) {
    print $msg . "<br><br>";
    print "<INPUT type='button' value='" . _("Upload again")
        . "' title='" . _("Cancel this import and return to file upload page") . "' onClick='history.back(-1)'>";
    exit;
}

$uploaddir = $_SERVER["DOCUMENT_ROOT"] . '/import/';

$action = $_POST["action"];
switch ($action) {
    case _("Upload"):
        $uploadFile = $uploaddir . basename($_FILES['userfile']['tmp_name']);
        if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadFile)) {
            echo "<b>" . _("Upload successful.  Parsing...") . "</b><br><h4>" . _("Results") . "</h4>";
            flush();
            print "Tmpfile " . basename($_FILES['userfile']['tmp_name']) . "<br>"; // DEBUG
        } else {
            die_nicely("<b>" . _("Upload failed!") . "</b>");
        }
        break;

    case _("Import"):
        $remove_these = array(' ','`','"','\'','\\','/');
        $filename = $_POST["tmpfile"];
        $uploadFile = $uploaddir . str_replace($remove_these, '', $filename);
        if (! file_exists($uploadFile)) {
            die_nicely("File $uploadFile not found");
        }
        print "<H4>" . _("Importing...") . "</H4>";
        print "Tmpfile " . $filename . "<br>"; // DEBUG
        flush();
        break;

    default:
        die_nicely("Unknown action $action");
}

$fileType = $_POST["fileType"];
$history = $_POST["historyMode"] ?? null;
$status = "";
$id_note = false;

switch ($fileType) {
    case "FM":
        // Parse it
        $html = phpQuery::newDocumentFileHTML($uploadFile, 'ISO-8859-1');

        if ($html['title']->text() != "FlightMemory - FlightData") {
            die_nicely(_("Sorry, the file $uploadFile does not appear contain FlightMemory FlightData."));
        }

        // Table with padded cells has the data
        $rows = pq('table[cellspacing=2] tr[valign=top]')->elements;
        break;

    case "CSV":
        if ($action == _("Upload") && pathinfo($_FILES["userfile"]["name"], PATHINFO_EXTENSION) !== "csv") {
            die_nicely(_("Sorry, the filename must end in '.csv'."));
        }

        $csvFile = file($uploadFile);
        if (!$csvFile) {
            die_nicely(_("Unable to open CSV file."));
        }

        // Convert whole file into giant array
        $rows = array_map('str_getcsv', $csvFile);

        break;

    default:
        die_nicely(_("Unknown file type $fileType"));
}

if ($action == _("Upload")) {
    print "<table style='border-spacing: 3'><tr>";
    print "<th>ID</th><th colspan=2>" . _("Date") . "</th><th>" . _("Flight") . "</th><th>" . _("From") .
        "</th><th>" . _("To") . "</th><th>" . _("Miles") . "</th><th>" . _("Time") . "</th><th>" .
        _("Plane") . "</th><th>" . _("Reg") . "</th>";
    print "<th>" . _("Seat") . "</th><th>" . _("Class") . "</th><th>" . _("Type") . "</th><th>" . _("Reason") .
        "</th><th>" . _("Trip") . "</th><th>" . _("Comment") . "</th></tr>";
}

$count = 0;
foreach ($rows as $row) {
    switch ($fileType) {
        case "FM":
            $row = pq($row);
            $cols = $row['> td, th']->elements;
            $id = pq($cols[0])->text();

            // Read and validate date field
            //  <td class="liste_rot"><nobr>10-05-2009</nobr><br>06:10<br>17:35 -1</td>
            $src_date = nth_text($cols[1], 0);
            $src_time = nth_text($cols[1], 1);
            if (strlen($src_time) < 4) {
                // a stray -1 or +1 is not a time
                $src_time = null;
            }
            list($src_date, $date_bgcolor) = check_date($dbh, $fileType, $src_date);

            $src_iata = $cols[2]->textContent;
            $dst_iata = $cols[4]->textContent;

            // <td class="liste"><b>Country</b><br>Town<br>Airport Blah Blah</td>
            //                                             ^^^^^^^ target
            $src_name = reset(preg_split('/[ \/<]/', nth_text($cols[3], 2)));
            $dst_name = reset(preg_split('/[ \/<]/', nth_text($cols[5], 2)));

            list($src_apid, $src_iata, $src_bgcolor) = check_airport($dbh, $src_iata, $src_name);
            list($dst_apid, $dst_iata, $dst_bgcolor) = check_airport($dbh, $dst_iata, $dst_name);

            // <th class="liste_gross" align="right">
            //   <table border="0" cellspacing="0" cellpadding="0">
            //     <tr><td align="right">429&nbsp;</td><td>mi</td></tr>
            //     <tr><td align="right">1:27&nbsp;</td><td>h</td></tr></table></th>
            $cells = $row['table td']->elements;
            $distance = $cells[0]->textContent;
            $distance = str_replace(',', '', nbsp_trim($distance));
            $dist_unit = $cells[1]->textContent;
            if ($dist_unit == "km") {
                // km to mi
                $distance = round($distance / KM_PER_MILE);
            }
            $duration = nbsp_trim($cells[2]->textContent);

            // <td>Airline<br>number</td>
            $airline = nth_text($cols[6], 0);
            $number = nth_text($cols[6], 1);
            list($alid, $airline, $airline_bgcolor) = check_airline($dbh, $number, $airline, $uid, $history);

            // Load plane model (plid)
            // <TD class=liste>Boeing 737-600<BR>LN-RCW<BR>Yngvar Viking</TD>
            $plane = nth_text($cols[7], 0);
            $reg = nth_text($cols[7], 1);
            if (text_count($cols[7]) > 2) {
                $reg .= " " . nth_text($cols[7], 2);
            }
            if ($plane != "") {
                list($plid, $plane_bgcolor) = check_plane($dbh, $plane);
            } else {
                $plid = null;
                $plane_bgcolor = "#fff";
            }

            // <td class="liste">12A/Window<br><small>Economy<br>Passenger<br>Business</small></td>
            // 2nd field may be blank, so we count fields and offset 1 if it's there
            $seat = nth_text($cols[8], 0);
            list($seatnumber, $seatpos) = explode('/', $seat);
            if (text_count($cols[8]) == 4) {
                $seatclass = nth_text($cols[8], 1);
                $offset = 1;
            } else {
                $seatclass = "Economy";
                $offset = 0;
            }
            $seattype = nth_text($cols[8], 1 + $offset);
            $seatreason = nth_text($cols[8], 2 + $offset);

            // <td class="liste_rot"><span title="Comment: 2.5-hr delay due to tire puncture">Com</span><br> ...
            $comment = pq($cols[9])->find('span')->attr('title');
            if ($comment && substr($comment, 0, 9) === "Comment: ") {
                $comment = trim(substr($comment, 9));
            }
            break; // case FM

        case "CSV":
            $count++;
            if ($count == 1) {
                // Skip header row
                break;
            }
            $id = $count - 1;
            // 0 Date Time, 1 From, 2 To,3 Flight_Number, 4 Airline_Code, 5 Distance, 6 Duration,
            // 7 Seat, 8 Seat_Type, 9 Class, 10 Reason, 11 Plane, 12 Registration, 13 Trip, 14 Note,
            // 15 From_Code, 16 To_Code, 17 Airline_Code, 18 Plane_Code

            $datetime = explode(' ', $row[0]);
            list($src_date, $date_bgcolor) = check_date($dbh, $fileType, $datetime[0]);
            $src_time = $datetime[1] ?? "";

            $src_iata = $row[1];
            $src_apid = $row[15];
            if ($src_apid) {
                $src_iata = "<small>ID $src_apid</small>";
                $src_bgcolor = "#fff";
                $id_note = true;
            } else {
                list($src_apid, $src_iata, $src_bgcolor) = check_airport($dbh, $src_iata, $src_iata);
            }
            $dst_iata = $row[2];
            $dst_apid = $row[16];
            if ($dst_apid) {
                $dst_iata = "<small>ID $dst_apid</small>";
                $dst_bgcolor = "#fff";
                $id_note = true;
            } else {
                list($dst_apid, $dst_iata, $dst_bgcolor) = check_airport($dbh, $dst_iata, $dst_iata);
            }
            $number = $row[3];
            $airline = $row[4];
            $alid = $row[17];
            if ($alid) {
                $airline = "<small>ID $alid</small>";
                $airline_bgcolor = "#fff";
                $id_note = true;
            } else {
                list($alid, $airline, $airline_bgcolor) = check_airline($dbh, $number, $airline, $uid, $history);
            }
            $plane = $row[11];
            $plid = $row[18];
            if ($plid) {
                $plane = "<small>ID $plid</small>";
                $plane_bgcolor = "#fff";
                $id_note = true;
            } else {
                list($plid, $plane_bgcolor) = check_plane($dbh, $plane);
            }

            $distance = $row[5];
            $duration = $row[6];
            $seatnumber = $row[7];
            $seatpos = array_search($row[8], $posMap);
            $seatclass = array_search($row[9], $classMap);
            if ($row[9] == "B") {
                $seatclass = "Business";
            } // fix for typo in pre-0.3 versions of spec
            $seattype = ""; // This field is not present in CSVs
            $seatreason = array_search($row[10], $reasonMap);
            $reg = $row[12];
            list($trid, $trip_bgcolor) = check_trip($dbh, $uid, $row[13]);
            $comment = $row[14];
            break;
    }

    // Skip first row for CSV
    if ($fileType == "CSV" && $count == 1) {
        continue;
    }

    //Check if parsing succeeded and tag fatal errors if not
    if (!$src_date) {
        $status = "disabled";
        $fatal = "date";
    }
    if (!$src_apid || !$dst_apid) {
        $status = "disabled";
        $fatal = "airport";
    } else {
        if ($duration == "" || $distance == "") {
            list($gc_distance, $gc_duration) = gcDistance($dbh, $src_apid, $dst_apid);
        }

        $duration_bgcolor = "#fff";
        $dist_bgcolor = "#fff";

        if ($duration == "") {
            $duration  = $gc_duration;
            $duration_bgcolor = "#ddf";
        }

        if ($distance == "") {
            $distance  = $gc_distance;
            $dist_bgcolor = "#ddf";
        }
    }
    if (!$alid) {
        $status = "disabled";
        $fatal = "airline";
    }
    if ($trid && $trip_bgcolor != "#fff") {
        $status = "disabled";
        $fatal = "trip";
    }

    switch ($action) {
        case _("Upload"):
            #    break;
            printf(
                "<tr><td>%s</td><td style='background-color: %s'>%s</td><td>%s</td><td style='background-color: %s'>%s %s</td><td style='background-color: %s'>%s</td><td style='background-color: %s'>%s</td><td style='background-color: %s'>%s</td><td style='background-color: %s'>%s</td><td style='background-color: %s'>%s</td><td>%s</td><td>%s %s</td><td>%s</td><td>%s</td><td>%s</td><td style='background-color: %s'>%s</td><td>%s</td></tr>\n",
                $id,
                $date_bgcolor,
                $src_date,
                $src_time,
                $airline_bgcolor,
                $airline,
                $number,
                $src_bgcolor,
                $src_iata,
                $dst_bgcolor,
                $dst_iata,
                $dist_bgcolor,
                $distance,
                $duration_bgcolor,
                $duration,
                $plane_bgcolor,
                $plane,
                $reg,
                $seatnumber,
                $seatpos,
                $seatclass,
                $seattype,
                $seatreason,
                $trip_bgcolor,
                $trid,
                $comment
            );
            break;

        case _("Import"):
            // Do we need a new plane?
            if ($plid == -1) {
                $sql = "INSERT INTO planes(name) VALUES(?)";
                $sth = $dbh->prepare($sql);
                if (!$sth->execute([$plane])) {
                    die('0;Adding new plane failed.');
                }
                $plid = $dbh->lastInsertId();
                print "Plane:" . $plane . " ";
            }

            // Do we need a new airline?
            if ($alid == -2) {
                // Last-ditch effort to check through non-IATA airlines
                $sql = "SELECT alid FROM airlines WHERE name=? OR alias=?";
                $sth = $dbh->prepare($sql);
                $sth->execute([$airline, $airline]);
                $dbrow = $sth->fetch();
                if ($dbrow) {
                    // Found it
                    $alid = $dbrow["alid"];
                } else {
                    $sql = "INSERT INTO airlines(name, uid) VALUES(?, ?)";
                    $sth = $dbh->prepare($sql);
                    if (!$sth->execute([$airline, $uid])) {
                        die('0;Adding new airline failed.');
                    }
                    $alid = $dbh->lastInsertId();
                    print "Airline:" . $airline . " ";
                }
            }

            // Hack to record X-Y and Y-X flights as same in DB
            if ($src_apid > $dst_apid) {
                $tmp = $src_apid;
                $src_apid = $dst_apid;
                $dst_apid = $tmp;
                $opp = "Y";
            } else {
                $opp = "N";
            }

            // And now the flight
            $sql = "INSERT INTO flights(uid, src_apid, src_date, src_time, dst_apid, duration, distance, registration, code, seat, seat_type, class, reason, note, plid, alid, trid, upd_time, opp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            $params = [
                $uid,
                $src_apid,
                $src_date,
                $src_time != "" ? $src_time : null,
                $dst_apid,
                $duration,
                $distance,
                $reg,
                $number,
                $seatnumber,
                substr($seatpos, 0, 1),
                $classMap[$seatclass],
                $reasonMap[$seatreason],
                $comment,
                $plid ? $plid : null,
                $alid,
                $trid ? $trid : null,
                $opp
            ];
            $sth = $dbh->prepare($sql);
            if (!$sth->execute($params)) {
                die('0;Importing flight failed.');
            }
            print $id . " ";
            break;
    }
}

if ($action == _("Upload")) {
    ?>
</table>

    <h4><?php echo _("Key to results") ?></h4>

<table style='border-spacing: 3'>
 <tr>
    <th><?php echo _("Color") ?></th><th><?php echo _("Meaning") ?></th>
 </tr><tr style='background-color: #fff'>
    <td><?php echo _("None") ?></td><td><?php echo _("Exact match") ?></td>
 </tr><tr style='background-color: #ddf'>
    <td><?php echo _("Info") ?></td><td><?php echo _("Probable match, please verify") ?></tr><tr style='background-color: #fdd'>
    <td><?php echo _("Warning") ?></td><td><?php echo _("No matches, will be added as new") ?></td>
 </tr><tr style='background-color: #faa'>
    <td><?php echo _("Error") ?></td><td><?php echo _("No matches, please correct and reupload") ?></td>
 </tr>
</table><br>

<form name="importform" action="/php/import.php" method="post">

    <?php
    if ($id_note == true) {
        print "<font color=blue>" . _("Note: This CSV file contains OpenFlights IDs in columns 15-18.  These IDs will override the values of any manual changes made to the airport, airline and/or plane columns.") . "</font><br>";
    }
    if ($history == "yes") {
        print "<font color=blue>" . ("Note: You have selected historical airline mode.  All airline names have been preserved exactly as is.") . "</font><br>";
    }

    if ($status == "disabled") {
        print "<font color=red>" . _("Error") . ": ";
        switch ($fatal) {
            case "airport":
                print _("Your flight data includes unrecognized airports.  Please add them to the database and try again. ");
                print "<INPUT type='button' value='" . _("Add new airport") . "' onClick='javascript:window.open(\"/html/apsearch\", \"Airport\", \"width=500,height=580,scrollbars=yes\")'>";
                break;

            case "airline":
                print _("Your flight data includes unrecognized airlines.  This usually means that the airline code in the flight number was not found, and an airline name was not specified.  Please fix or remove the airline code and try again. ");
                break;

            case "date":
                print _("Some date fields could not be parsed.  Please change them to use any of these formats: YYYY-MM-DD, DD.MM.YYYY, MM/DD/YYYY, or YYYY only.  Note that DD/MM/YYYY is <b>not</b> accepted.");
                break;

            case "trip":
                print _("Your flight data includes trip IDs which are either undefined or do not belong to you.  Please check the trip IDs.");
                break;
        }
        print "</font><br><br>";
    } else {
        print _("<b>Parsing completed successfully.</b> You are now ready to import these flights into your OpenFlights. (Minor issues can be corrected afterwards in the flight editor.)") . "<br><br>";
    }
    print "<INPUT type='hidden' name='tmpfile' value='". basename($_FILES['userfile']['tmp_name']) . "'>";
    print "<INPUT type='hidden' name='fileType' value='$fileType'>";
    print "<INPUT type='hidden' name='historyMode' value='$history'>";
    print "<INPUT type='submit' name='action' title='" . _("Add these flights to your OpenFlights") . "' value='" . _("Import") . "' " . $status . ">";
    ?>

<INPUT type="button" value="<?php echo _("Upload again") ?>" title="<?php _("Cancel this import and return to file upload page") ?>" onClick="JavaScript:history.go(-1)">

<INPUT type="button" value="<?php echo _("Cancel") ?>" onClick="window.close()">

    <?php
}
if ($action == _("Import")) {
    print "<BR><H4>" . _("Flights successfully imported.") . "</H4><BR>";
    print "<INPUT type='button' value='" . _("Import more flights") . "' onClick='javascript:window.location=\"/html/import\"'>";
    print "<INPUT type='button' value='" . _("Close") . "' onClick='javascript:parent.opener.refresh(true); window.close();'>";
}

?>
      </form>
    </div>
  </body>
</html>
