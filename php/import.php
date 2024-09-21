<?php

require_once "locale.php";
require_once "db_pdo.php";
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title><?php echo sprintf(_('OpenFlights: %s'), _('Import')); ?></title>
    <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">

    <script type="text/javascript" src="/js/Gettext.min.js"></script>
    <script type="text/javascript" src="/js/apsearch.js"></script>
  </head>

  <body>
    <div id="contexthelp">

  <h1><?php echo _("Import"); ?></h1>
<?php
$uid = $_SESSION["uid"];
if (!$uid || empty($uid)) {
    die_nicely(_("Not logged in, aborting"));
}

require_once '../vendor/autoload.php';
include_once 'helper.php';

const POS_MAP = ["Window" => "W", "Middle" => "M", "Aisle" => "A", "" => ""];
const CLASS_MAP = ["Economy" => "Y", "Prem.Eco" => "P", "EconomyPlus" => "P", "Business" => "C", "First" => "F", "" => ""];
const REASON_MAP = ["Business" => "B", "Personal" => "L", "Crew" => "C", "Other" => "O", "" => ""];

/**
 * @param $element
 * @param $n int
 * @return string
 */
function nth_text($element, $n) {
    $xpath = new DOMXPath($element->ownerDocument);
    $item = $xpath->query('.//text()', $element)->item($n);
    if ($item !== null) {
        return nbsp_trim($item->textContent);
    }

    // Shouldn't be necessary in most cases, as we should be checking for the number of pieces we text,
    // as some are optional.
    // If the item ($n) we were looking for isn't there, just return an empty string.
    return '';
}

/**
 * @param $element
 * @return int
 */
function text_count($element) {
    $xpath = new DOMXPath($element->ownerDocument);
    return $xpath->query('.//text()', $element)->length;
}

/**
 * Trims UTF-8 NBSP.
 *
 * @param $string string
 * @return string
 */
function nbsp_trim($string) {
    return trim($string, "\xC2\xA0"); // UTF-8 NBSP
}

/**
 * Validate date field.
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $type string FM for FlightMemory date format
 * @param $date string Must be one of YYYY, MM-DD-YYYY (FM only), YYYY-MM-DD (CSV only), MM/DD/YYYY or DD.MM.YYYY
 * @return array [ Date, color ]
 */
function check_date($dbh, $type, $date) {
    if (strlen($date) === 4) {
        $date = "01.01." . $date;
    }
    if (strpos($date, "-") !== false) {
        if ($type == "FM") {
            $dateFormat = "%m-%d-%Y";
        } else {
            $dateFormat = "%Y-%m-%d";
        }
    } elseif (strpos($date, "/") !== false) {
        $dateFormat = "%m/%d/%Y";
    } else {
        $dateFormat = "%d.%m.%Y";
    }
    // TODO: Do we really need to do an SQL query to validate a date?
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
    return [$date, $color];
}

/**
 * Validate that this code/name match an airport.
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $code string IATA or ICAO code
 * @param $name string Airport name
 * @return array [ Airport ID, Code or location, color ]
 */
function check_airport($dbh, $code, $name) {
    $sql = "SELECT apid, city, country FROM airports WHERE ";
    switch (strlen($code)) {
        case 3:
            $params = [$code];
            $sql .= "iata = ?";
            break;

        case 4:
            $params = [$code];
            $sql .= "icao = ?";
            break;

        default:
            $params = [$name . '%'];
            $sql .= "name LIKE ?";
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
            $code .= "<br><small>{$dbrow["city"]},{$dbrow["country"]}</small>";
            $color = "#ddf";
    }
    return [$apid, $code, $color];
}

/**
 * Validate that this flight number and/or airline name are found in DB.
 * If flight number starts with an IATA code, match that (and double-check it against name).
 * Else match the first word of airline name.
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $flightNumber string Flight number
 * @param $airlineName string Airline name
 * @param $uid string User ID; unused
 * @param $history string If "yes", ignore codes and ignore errors
 * @return array [ Airline ID, airline name, color ]
 */
function check_airline($dbh, $flightNumber, $airlineName, $uid, $history) {
    $code = substr($flightNumber, 0, 2);
    $isAlpha = preg_match('/[a-zA-Z0-9]{2}/', $code) && !preg_match('/\d{2}/', $code);
    if ($airlineName === "" && !$isAlpha) {
        return [
            -1,
            _("Unknown") . "<br><small>(" . _("was:") . " " . _("No airline") . ")</small>",
            "#ddf"
        ];
    }

    // Is it alphanumeric characters, but not all numeric characters? Then it's probably an airline code.
    if ($isAlpha && $history != "yes") {
        $params = [$code];
        $sql = "SELECT name, alias, alid FROM airlines WHERE iata = ? ORDER BY name";
    } else {
        $airlinepart = explode(' ', $airlineName);
        if ($airlinepart[0] == 'Air') {
            $part = 'Air ' . $airlinepart[1] . '%';
        } else {
            $part = $airlinepart[0] . '%';
        }
        $params = [$part, $part, $airlineName];
        $sql = <<<SQL
SELECT name, alias, alid FROM airlines
WHERE ((name LIKE ? OR alias LIKE ?) AND (iata != '')) OR (name = ?)
ORDER BY frequency DESC
SQL;
    }
    $sth = $dbh->prepare($sql);
    $sth->execute($params);

    // validate the airline/code against the DB
    switch ($sth->rowCount()) {
        // No match, add as new if we have a name for it, else return error
        case 0:
            if ($airlineName !== "") {
                $color = "#fdd";
                $alid = -2;
            } else {
                $color = "#faa";
                $alid = null;
            }
            break;

        // Solitary match
        case 1:
            $dbrow = $sth->fetch();
            if (
                $airlineName !== "" && (
                    strcasecmp($dbrow['name'], $airlineName) === 0 ||
                    strcasecmp($dbrow['alias'], $airlineName) === 0
                )
            ) {
                // Exact match
                $color = "#fff";
                $airlineName = $dbrow['name'];
                $alid = $dbrow['alid'];
            } elseif ($history == "yes") {
                // Not an exact match
                $color = "#fdd";
                $alid = -2;
            } else {
                $color = "#ddf";
                $airlineName = $dbrow['name'] . "<br><small>(" . _("was:") . " $airlineName)</small>";
                $alid = $dbrow['alid'];
            }
            break;

        // Many matches, default to first with a warning if we can't find an exact match
        default:
            $color = "#ddf";
            $first = true;
            foreach ($sth as $dbrow) {
                $isMatch = $airlineName !== "" && (
                    (strcasecmp($dbrow['name'], $airlineName) === 0) ||
                    (strcasecmp($dbrow['alias'], $airlineName) === 0)
                );
                if ($first || $isMatch) {
                    if ($isMatch) {
                        $color = "#fff";
                    }
                    if ($first) {
                        $first = false;
                    }
                    $newAirline = $dbrow['name'];
                    $alid = $dbrow['alid'];
                }
            }
            // No match and in historical mode? Add it as new
            if ($history == "yes" && $color == "#ddf") {
                $color = "#fdd";
                $alid = -2;
            } else {
                $airlineName = $newAirline;
            }
    }
    return [$alid, $airlineName, $color];
}

/**
 * Validate that this plane is in DB.
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $plane string Plane ID
 * @return array [ Plane ID, color ]
 */
function check_plane($dbh, $plane) {
    // If no plane set, return OK
    if (!$plane || $plane == "") {
        return [null, "#fff"];
    }

    $sql = "SELECT plid FROM planes WHERE name = ?";
    $sth = $dbh->prepare($sql);
    $sth->execute([$plane]);
    if ($sth->rowCount() === 1) {
        $plid = $sth->fetchColumn(0);
        $color = "#fff";
    } else {
        $plid = "-1"; // new plane
        $color = "#fdd";
    }
    return [$plid, $color];
}

/**
 * Validate that the importing user owns this trip.
 *
 * @param $dbh PDO|null OpenFlights DB handler
 * @param $uid string User ID
 * @param $trid string Trip ID
 * @return array Trip ID, color
 */
function check_trip($dbh, $uid, $trid = "") {
    // If no trip set, return OK
    if (!$trid || $trid == "" || $dbh === null) {
        return [null, "#fff"];
    }

    $sql = "SELECT uid FROM trips WHERE trid = ?";
    $sth = $dbh->prepare($sql);
    $sth->execute([$trid]);
    if (($sth->rowCount() === 1) && $uid == $sth->fetchColumn(0)) {
        $color = "#fff";
    } else {
        $color = "#faa";
    }
    return [$trid, $color];
}

function die_nicely($msg) {
    print $msg . "<br><br>"
        . "<input type='button' value='" . _("Upload again") . "' title='"
        . _("Cancel this import and return to file upload page") . "' onClick='history.back(-1)'>";
    exit;
}

$uploadDir = $_SERVER["DOCUMENT_ROOT"] . '/import/';

$action = $_POST["action"];
switch ($action) {
    case _("Upload"):
        $uploadFile = $uploadDir . basename($_FILES['userfile']['tmp_name']);
        if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadFile)) {
            echo "<b>" . _("Upload successful. Parsing...") . "</b><br><h4>" . _("Results") . "</h4>";
            flush();
            print "Tmpfile <tt>" . basename($_FILES['userfile']['tmp_name']) . "</tt><br>"; // DEBUG
        } else {
            die_nicely("<b>" . _("Upload failed!") . "</b>");
        }
        break;

    case _("Import"):
        $remove_these = [' ','`','"','\'','\\','/'];
        $filename = $_POST["tmpfile"];
        $uploadFile = $uploadDir . str_replace($remove_these, '', $filename);
        if (!file_exists($uploadFile)) {
            die_nicely(sprintf(_("File %s not found"), $uploadFile));
        }
        print "<h4>" . _("Importing...") . "</h4>";
        print "Tmpfile <tt>" . $filename . "</tt><br>"; // DEBUG
        flush();
        break;

    default:
        die_nicely(sprintf(_("Unknown action %s"), htmlspecialchars($action)));
}

$fileType = $_POST["fileType"];
$history = $_POST["historyMode"] ?? null;
$status = "";
$idNote = false;

switch ($fileType) {
    case "FM":
        // Parse it
        $html = phpQuery::newDocumentFileHTML($uploadFile, 'ISO-8859-1');

        if ($html['title']->text() != "FlightMemory - FlightData") {
            die_nicely(
                sprintf(_("Sorry, the file %s does not appear to contain FlightMemory FlightData."), htmlspecialchars($uploadFile))
            );
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

        // Convert the whole file into a giant array
        $rows = array_map('str_getcsv', $csvFile);
        break;

    default:
        die_nicely(sprintf(_("Unknown file type %s"), htmlspecialchars($fileType)));
}

if ($action == _("Upload")) {
    // TODO: probably should be 3px or 3em...
    printf(
"<table style='border-spacing: 3'>
<tr>
    <th></th>
    <th colspan='2'>%s</th>
    <th>%s</th>
    <th>%s</th>
    <th>%s</th>
    <th>%s</th>
    <th>%s</th>
    <th>%s</th>
    <th>%s</th>
    <th colspan='2'>%s</th>
    <th>%s</th>
    <th>%s</th>
    <th>%s</th>
    <th>%s</th>
    <th>%s</th>
</tr>",
        _("Date"),
        _("Flight"),
        _("From"),
        _("To"),
        _("Miles"),
        _("Time"),
        _("Plane"),
        _("Reg"),
        _("Seat"),
        _("Class"),
        _("Type"),
        _("Reason"),
        _("Trip"),
        _("Comment")
    );
}

$fatal = [
    'airport' => false,
    'airline' => false,
    'date' => false,
    'trip' => false,
];

$count = 0;
foreach ($rows as $row) {
    switch ($fileType) {
        case "FM":
            $row = pq($row);
            $cols = $row['> td, th']->elements;
            $id = pq($cols[0])->text();

            // Read and validate date field
            //  <td class="liste_rot"><nobr>10-05-2009</nobr><br>06:10<br>17:35 -1</td>
            $src_time = nth_text($cols[1], 1);
            if (strlen($src_time) < 4) {
                // a stray -1 or +1 is not a time
                $src_time = null;
            }
            [$src_date, $date_bgcolor] = check_date($dbh, $fileType, nth_text($cols[1], 0));

            $src_iata = $cols[2]->textContent;
            $dst_iata = $cols[4]->textContent;

            // Try and grab the "Airport Name" for the source and destination airports.
            // Check if there are 3 articles of plain text in the <td>.
            // If there are, try and grab the third; it can then be used in a LIKE search to help find the airport
            //
            // preg_split is looking for names with multiple (not hyphenated) words (separated by " ", "/" or "<"),
            // or alternate names, such as:
            //  - "National/Zaventem" -> "National"
            //  - "Bole International" -> "Bole"
            //  - "Berlin Brandenburg, Willy Brandt" -> "Berlin"
            // And then taking the first "word" (element 0)

            // <td class="liste"><b>City/Town</b><br>Country<br>Airport Blah Blah</td>
            //                                                     ^^^^^^^ target
            // <td class="liste"><b>City/Town</b><br>Country</td>

            $src_name = text_count($cols[3]) === 3
                ? preg_split('/[ \/<]/', nth_text($cols[3], 2))[0]
                : "";

            $dst_name = text_count($cols[5]) === 3
                ? preg_split('/[ \/<]/', nth_text($cols[5], 2))[0]
                : "";

            [$src_apid, $src_iata, $src_bgcolor] = check_airport($dbh, $src_iata, $src_name);
            [$dst_apid, $dst_iata, $dst_bgcolor] = check_airport($dbh, $dst_iata, $dst_name);

            // <th class="liste_gross" align="right">
            //   <table border="0" cellspacing="0" cellpadding="0">
            //     <tr><td align="right">429&nbsp;</td><td>mi</td></tr>
            //     <tr><td align="right">1:27&nbsp;</td><td>h</td></tr></table></th>
            $cells = $row['table td']->elements;
            $distance = str_replace(',', '', nbsp_trim($cells[0]->textContent));
            $dist_unit = $cells[1]->textContent;
            if ($dist_unit == "km") {
                // km to mi
                $distance = round($distance / KM_PER_MILE);
            }
            $duration = nbsp_trim($cells[2]->textContent);

            // <td>Airline<br>number</td>
            $flightNumber = nth_text($cols[6], 1);
            [$alid, $airline, $airline_bgcolor] = check_airline(
                $dbh,
                $flightNumber,
                nth_text($cols[6], 0),
                $uid,
                $history
            );

            // Load plane model (plid)
            // <td class="liste">Boeing 737-600<br>LN-RCW<br>Yngvar Viking</td>
            // <td class="liste_rot">Airbus A319-100</td>
            $plane = nth_text($cols[7], 0);
            $planeRegistration = '';
            // See if the text has a registration, but it's optional
            if (text_count($cols[7]) > 1) {
                $planeRegistration = nth_text($cols[7], 1);
                // We also have a "name"
                if (text_count($cols[7]) > 2) {
                    $planeRegistration .= " " . nth_text($cols[7], 2);
                }
            }

            // If no plane found, it'll return the defaults.
            [$plid, $plane_bgcolor] = check_plane($dbh, $plane);

            // <td class="liste">12A/Window<br><small>Economy<br>Passenger<br>Business</small></td>
            // 2nd field may be blank, so we count fields and offset 1 if it's there
            $seat = nth_text($cols[8], 0);
            [$seatNumber, $seatPos] = explode('/', $seat);
            if (text_count($cols[8]) === 4) {
                $seatClass = nth_text($cols[8], 1);
                $offset = 1;
            } else {
                $seatClass = "Economy";
                $offset = 0;
            }

            $seatType = nth_text($cols[8], 1 + $offset);
            $seatReason = nth_text($cols[8], 2 + $offset);

            // <td class="liste_rot"><span title="Comment: 2.5-hr delay due to tire puncture">Com</span><br> ...
            $comment = pq($cols[9])->find('span')->attr('title');
            if ($comment && strpos($comment, "Comment: ") === 0) {
                $comment = trim(substr($comment, 9));
            }

            // FM imports don't have a trip, so this will use fallback values
            [$trid, $trip_bgcolor] = check_trip(null, "");
            break; // case FM

        case "CSV":
            $count++;
            // Skip header row
            if ($count === 1) {
                continue 2;
            }

            $id = $count - 1;

            [
                // 0 - Date Time
                $datetime,
                // 1 - From; Source Airport IATA code
                $src_iata,
                // 2 - To; Destination Airport IATA code
                $dst_iata,
                // 3 - Flight_Number
                $flightNumber,
                // 4 - Airline
                $airline,
                // 5 - Distance
                $distance,
                // 6 - Duration
                $duration,
                // 7 - Seat
                $seatNumber,
                // 8 - Seat_Type
                $seatPos,
                // 9 - Class
                $seatClass,
                // 10 - Reason (for Flight; Work/Leisure/Crew)
                $seatReason,
                // 11 - Plane
                $plane,
                // 12 - Registration
                $planeRegistration,
                // 13 - Trip
                $trid,
                // 14 - Note
                $comment,
                // 15 - From_OID - OpenFlights Airport ID for Source Airport
                $src_apid,
                // 16 - To_OID - OpenFlights Airport ID for Destination Airport
                $dst_apid,
                // 17 - Airline_OID - OpenFlights Airline ID
                $alid,
                // 18 - Plane_OID - OpenFlights Plane ID
                $plid,
            ] = $row;

            $datetime = explode(' ', $datetime);
            [$src_date, $date_bgcolor] = check_date($dbh, $fileType, $datetime[0]);
            $src_time = $datetime[1] ?? "";

            // Prefer OpenFlight ID if set for relevant rows
            if ($src_apid) {
                $src_iata = "<small>" . sprintf(_('ID %s'), $src_apid) . "</small>";
                $src_bgcolor = "#fff";
                $idNote = true;
            } else {
                [$src_apid, $src_iata, $src_bgcolor] = check_airport($dbh, $src_iata, $src_iata);
            }

            if ($dst_apid) {
                $dst_iata = "<small>" . sprintf(_('ID %s'), $dst_apid) . "</small>";
                $dst_bgcolor = "#fff";
                $idNote = true;
            } else {
                [$dst_apid, $dst_iata, $dst_bgcolor] = check_airport($dbh, $dst_iata, $dst_iata);
            }

            if ($alid) {
                $airline = "<small>" . sprintf(_('ID %s'), $alid) . "</small>";
                $airline_bgcolor = "#fff";
                $idNote = true;
            } else {
                [$alid, $airline, $airline_bgcolor] = check_airline($dbh, $flightNumber, $airline, $uid, $history);
            }

            if ($plid) {
                $plane = "<small>" . sprintf(_('ID %s'), $plid) . "</small>";
                $plane_bgcolor = "#fff";
                $idNote = true;
            } else {
                [$plid, $plane_bgcolor] = check_plane($dbh, $plane);
            }

            // Get code from mapping
            $seatPos = array_search($seatPos, POS_MAP);

            // fix for typo in pre-0.3 versions of spec
            if ($seatClass == "B") {
                $seatClass = "Business";
            } else {
                // Get code from mapping
                $seatClass = array_search($seatClass, CLASS_MAP);
            }

            $seatType = ""; // This field is not present in CSVs; Passenger
            // Get code from mapping
            $seatReason = array_search($seatReason, REASON_MAP);
            [$trid, $trip_bgcolor] = check_trip($dbh, $uid, $trid);
            break;
    }

    // Check if parsing succeeded and tag fatal errors if not
    if (!$src_date) {
        $status = "disabled";
        $fatal["date"] = true;
    }
    if (!$src_apid || !$dst_apid) {
        $status = "disabled";
        $fatal["airport"] = true;
    } else {
        $duration_bgcolor = "#fff";
        $dist_bgcolor = "#fff";

        if ($duration == "" || $distance == "") {
            [$gc_distance, $gc_duration] = gcDistance($dbh, $src_apid, $dst_apid);

            if ($duration == "") {
                $duration = $gc_duration;
                $duration_bgcolor = "#ddf";
            }

            if ($distance == "") {
                $distance = $gc_distance;
                $dist_bgcolor = "#ddf";
            }
        }
    }
    if (!$alid) {
        $status = "disabled";
        $fatal["airline"] = true;
    }
    if ($trid && $trip_bgcolor != "#fff") {
        $status = "disabled";
        $fatal["trip"] = true;
    }

    switch ($action) {
        case _("Upload"):
            printf(
                "<tr>
    <td>%s</td>
    <td style='background-color: %s'>%s</td>
    <td>%s</td>
    <td style='background-color: %s'>%s %s</td>
    <td style='background-color: %s'>%s</td>
    <td style='background-color: %s'>%s</td>
    <td style='background-color: %s'>%s</td>
    <td style='background-color: %s'>%s</td>
    <td style='background-color: %s'>%s</td>
    <td>%s</td>
    <td>%s</td>
    <td>%s</td>
    <td>%s</td>
    <td>%s</td>
    <td>%s</td>
    <td style='background-color: %s'>%s</td>
    <td>%s</td>
</tr>
",
                $id,
                $date_bgcolor,
                $src_date,
                $src_time,
                $airline_bgcolor,
                $airline,
                $flightNumber,
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
                $planeRegistration,
                $seatNumber,
                $seatPos,
                $seatClass,
                $seatType,
                $seatReason,
                $trip_bgcolor,
                $trid,
                $comment
            );
            break;

        case _("Import"):
            // TODO: stop using a magic number; swap to const
            // Do we need a new plane?
            if ($plid == -1) {
                $sql = "INSERT INTO planes(name) VALUES(?)";
                $sth = $dbh->prepare($sql);
                if (!$sth->execute([$plane])) {
                    die('0;' .  _('Adding new plane failed.'));
                }
                $plid = $dbh->lastInsertId();
                // TODO: localise?
                print "Plane:" . $plane . " ";
            }

            // TODO: stop using a magic number; swap to const
            // Do we need a new airline?
            if ($alid == -2) {
                // Last-ditch effort to check through non-IATA airlines
                $sql = "SELECT alid FROM airlines WHERE name = ? OR alias = ?";
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
                        die('0;' .  _('Adding new airline failed.'));
                    }
                    $alid = $dbh->lastInsertId();
                    // TODO: localise?
                    print "Airline:" . $airline . " ";
                }
            }

            // Hack to record X-Y and Y-X flights as same in DB
            $flip = ($src_apid > $dst_apid);
            [$src_apid, $dst_apid] = flip($src_apid, $dst_apid, $flip);
            $opp = $flip ? "Y" : "N";

            // And now the flight
            $sql = <<<SQL
                INSERT INTO flights(uid, src_apid, src_date, src_time, dst_apid, duration, distance,
                    registration, code, seat, seat_type, class, reason, note, plid, alid, trid, upd_time, opp)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
SQL;
            $params = [
                $uid,
                $src_apid,
                $src_date,
                $src_time != "" ? $src_time : null,
                $dst_apid,
                $duration,
                $distance,
                $planeRegistration,
                $flightNumber,
                $seatNumber,
                POS_MAP[$seatPos],
                CLASS_MAP[$seatClass],
                REASON_MAP[$seatReason],
                $comment,
                $plid ?: null,
                $alid,
                $trid ?: null,
                $opp
            ];
            $sth = $dbh->prepare($sql);
            if (!$sth->execute($params)) {
                die('0;' .  _('Importing flight failed.'));
            }
            print $id . " ";
            break;
    }
}

if ($action == _("Upload")) {
    ?>
</table>

    <h4><?php echo _("Key to results"); ?></h4>

<table style='border-spacing: 3'>
  <tr>
    <th><?php echo _("Color"); ?></th><th><?php echo _("Meaning"); ?></th>
  </tr>
  <tr style='background-color: #fff'>
    <td><?php echo _("None"); ?></td><td><?php echo _("Exact match"); ?></td>
  </tr>
  <tr style='background-color: #ddf'>
    <td><?php echo _("Info"); ?></td><td><?php echo _("Probable match, please verify"); ?>
  </tr>
  <tr style='background-color: #fdd'>
    <td><?php echo _("Warning"); ?></td><td><?php echo _("No matches, will be added as new"); ?></td>
  </tr>
  <tr style='background-color: #faa'>
    <td><?php echo _("Error"); ?></td><td><?php echo _("No matches, please correct and reupload"); ?></td>
  </tr>
</table><br>

<form name="importform" action="/php/import.php" method="post">

    <?php
    if ($idNote == true) {
        print "<font color=blue>" .
            _("Note: This CSV file contains OpenFlights IDs in one or more of the columns numbered 15-18 (15 Source Airport ID, 16 Destination Airport ID, 17 Airline ID, 18 Plane ID). These IDs will override the values of any manual changes made to the airport, airline and/or plane columns.") .
            "</font><br><br>";
    }
    if ($history == "yes") {
        print "<font color=blue>" .
            _("Note: You have selected historical airline mode. All airline names have been preserved exactly as is.") .
            "</font><br><br>";
    }

    if ($status === "disabled") {
        // TODO: separate : is not i18n friendly
        print "<font color=red>" . _("Error") . ": ";

        $errors = [];
        if ($fatal["airport"]) {
            $errors[] = _("Your flight data includes unrecognized airports. Please add them to the database and try again.") .
                "&nbsp;<input type='button' value='" . _("Add new airport") .
                "'onClick='javascript:window.open(\"/html/apsearch\", \"Airport\", \"width=500,height=580,scrollbars=yes\")'>";
        }

        if ($fatal["airline"]) {
            $errors[] = _("Your flight data includes unrecognized airlines. This usually means that the airline code in the flight number was not found, and an airline name was not specified. Please fix or remove the airline code and try again.");
        }

        if ($fatal["date"]) {
            $errors[] = _("Some date fields could not be parsed. Please change them to use any of these formats: YYYY-MM-DD, DD.MM.YYYY, MM/DD/YYYY, or YYYY only. Note that DD/MM/YYYY is <b>not</b> accepted.");
        }

        if ($fatal["trip"]) {
            $errors[] = _("Your flight data includes trip IDs which are either undefined or do not belong to you. Please check the trip IDs.");
        }
        print implode("\n<br><br>\n", $errors);
        print "</font><br><br>";
    } else {
        print _(
            "<b>Parsing completed successfully.</b> You are now ready to import these flights into your OpenFlights. (Minor issues can be corrected afterwards in the flight editor.)"
        ) . "<br><br>";
    }
    print "<input type='hidden' name='tmpfile' value='" . basename($_FILES['userfile']['tmp_name']) . "'>";
    print "<input type='hidden' name='fileType' value='$fileType'>";
    print "<input type='hidden' name='historyMode' value='$history'>";
    print "<input type='submit' name='action' title='" . _("Add these flights to your OpenFlights list?") . "' value='" .
        _("Import") . "' " . $status . ">";
    ?>

<input type="button" value="<?php echo _("Upload again"); ?>" title="<?php
    _("Cancel this import and return to file upload page"); ?>" onClick="JavaScript:history.go(-1)">

<input type="button" value="<?php echo _("Cancel"); ?>" onClick="window.close()">

    <?php
}
if ($action == _("Import")) {
    print "<br><h4>" . _("Flights successfully imported.") . "</h4><br>";
    print "<input type='button' value='" .
        _("Import more flights") . "' onClick='javascript:window.location=\"/html/import\"'>";
    print "<input type='button' value='" . _("Close") .
        "' onClick='javascript:parent.opener.refresh(true); window.close();'>";
}

?>
      </form>
    </div>
  </body>
</html>
