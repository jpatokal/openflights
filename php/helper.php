<?php

/**
 * @deprecated use KM_PER_MILE const
 */
$KMPERMILE = "1.609344"; // must be a string or locale may turn this into a comma!
const KM_PER_MILE = "1.609344";

$modes = array ("F" => "Flight", "T" => "Train", "S" => "Ship", "R" => "Road trip");
$modeOperators = array ("F" => "airline", "T" => "railway", "S" => "shipping company", "R" => "road transport company");

/**
 * End with JSON-formatted data, localized message and a successful status
 * @param $data array
 */
function json_success($data) {
    $data["status"] = 1;
    $data["message"] = _($data["message"]);
    die(json_encode($data));
}

/**
 * Abort with a JSON-formatted localized error message
 * @param $msg string
 * @param $detail string
 */
function json_error($msg, $detail = '') {
    die(json_encode(array("status" => 0, "message" => _($msg) . ' ' . $detail)));
}

/**
 * Standard formatting of airport data
 * @param $row array associative array containing iata, icao
 * @return string " code : apid : x : y : timezone : dstrule "
 */
function format_apdata($row) {
    return sprintf(
        "%s:%s:%s:%s:%s:%s",
        format_apcode($row),
        $row["apid"],
        $row["x"],
        $row["y"],
        $row["timezone"],
        $row["dst"]
    );
}

/**
 * // Standard formatting of airport codes
 * @param $row array associative array containing iata, icao
 * @return string
 */
function format_apcode($row) {
    return format_apcode2($row["iata"], $row["icao"]);
}

/**
 * @param $iata string
 * @param $icao string
 * @return string
 */
function format_apcode2($iata, $icao) {
    $code = $iata;
    if (!$code || $code == "N/A") {
        $code = $icao;
        if (!$code) {
            $code = "Priv";
        }
    }
    return $code;
}

/**
 * Standard formatting of airport names
 * @param $row associative array containing name, city, country/code and iata/icao
 * @return string
 */
function format_airport($row) {
    $name = $row["name"];
    $city = $row["city"];
    $country = $row["country"];
    if (array_key_exists("code", $row)) {
        $country = $row['code']; // hack for DAFIF
    }
    $iata = format_apcode($row);

  // Foobar-Foobar Intl into Foobar Intl
  // Foo-bar-Foo Bar Intl into Foo Bar Intl
    if (strncasecmp(strtr($name, "-", " "), strtr($city, "-", " "), strlen($city)) == 0) {
        $city = "";
    } else {
        $city .= "-";
    }
    if (strlen($city . $name . $country) > 40) {
        $name = trim(substr($city . $name, 0, 39 - strlen($country))) . ".";
        $city = "";
    }

    return $city . $name . " (" . $iata . "), " . $country;
}

/**
 * Standard formatting of airline names
 *
 * @param $row array associative array containing name, iata, icao and (optionally) mode
 * @return string
 */
function format_airline($row) {
    $mode = $row["mode"];
    if ($mode && $mode != "F") {
        // Not an airline
        return $row["name"];
    }

    return $row["name"] . " (" . format_alcode($row["iata"], $row["icao"], $row["mode"]) . ")";
}

/**
 * @param $iata string
 * @param $icao string
 * @param $mode string
 * @return string
 */
function format_alcode($iata, $icao, $mode) {
    if ($mode && $mode != "F") {
        return "";
    }
    if ($iata && $iata != "") {
        return $iata;
    }
    if ($icao && $icao != "") {
        return $icao;
    }

    return "Priv";
}

/**
 * Calculate (distance, duration) between two airport IDs
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $src_apid string Source APID
 * @param $dst_apid string Destination APID
 * @return array Distance, duration
 */
function gcDistance($dbh, $src_apid, $dst_apid) {
    // Special case: loop flight to/from same airport
    if ($src_apid == $dst_apid) {
        $dist = 0;
    } else {
        $sql = "SELECT x,y FROM airports WHERE apid=$src_apid OR apid=$dst_apid";

        // Handle both OO and procedural-style database handles, depending on what type we've got.
        $sth = $dbh->prepare($sql);
        $sth->execute();
        if ($sth->rowCount() !== 2) {
            return array(null, null);
        }
        $coord1 = $sth->fetch();
        $lon1 = $coord1["x"];
        $lat1 = $coord1["y"];
        $coord2 = $sth->fetch();
        $lon2 = $coord2["x"];
        $lat2 = $coord2["y"];

        // TODO: Use gcPointDistance from greatcircle.php
        $pi = 3.1415926;
        $rad = ($pi / 180.0);
        $lon1 = (float)$lon1 * $rad;
        $lat1 = (float)$lat1 * $rad;
        $lon2 = (float)$lon2 * $rad;
        $lat2 = (float)$lat2 * $rad;

        $theta = $lon2 - $lon1;
        $dist = acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($theta));
        if ($dist < 0) {
            $dist += $pi;
        }
        $dist = floor($dist * 6371.2 * 0.621);
    }
    $duration = gcDuration($dist);
    return array($dist, $duration);
}

/**
 * @param $dist
 * @return string
 */
function gcDuration($dist) {
    $rawtime = floor(30 + ($dist / 500) * 60);
    return sprintf("%02d:%02d", floor($rawtime / 60), $rawtime % 60);
}

/**
 * Convert a filename (relative to the document root) to a relative URL with a date-based version string appended.
 * @param $filename string|null Relative filename (e.g. "/js/foo.js")
 * @return string Relative filename with version (e.g. "/js/foo.js?version=20120102")
 * @throws Exception Invalid input
 */
function fileUrlWithDate($filename) {
    if ($filename === null || empty($filename) || strlen($filename) < 1) {
        throw new Exception("fileUrlWithDate requires a valid filename.");
    }
    # Make sure there is a leading slash.
    if (substr($filename, 0, 1) != '/') {
        $filename = '/' . $filename;
    }

    $fullPath = $_SERVER["DOCUMENT_ROOT"] . $filename;

    if (!file_exists($fullPath)) {
        throw new Exception("$fullPath does not exist; can't get URL with date.");
    }
    return $filename . '?version=' . gmdate("Ymd", filemtime($fullPath));
}

/**
 * Hack to record X-Y and Y-X flights as same in DB
 * @param $src_apid
 * @param $dst_apid
 * @return array
 */
function orderAirports($src_apid, $dst_apid) {
    if ($src_apid > $dst_apid) {
        return array($dst_apid, $src_apid, "Y");
    }

    return array($src_apid, $dst_apid, "N");
}
