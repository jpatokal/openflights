<?php

include_once 'locale.php';
include_once 'greatcircle.php';

/**
 * @note Must be a string or locale may turn this into a comma!
 */
const KM_PER_MILE = "1.609344";

/**
 * @deprecated use KM_PER_MILE const
 */
$KMPERMILE = KM_PER_MILE;

/**
 * Mapping of modes from shorthand
 *
 * @TODO: Internationalise? differing case messages exist
 *
 * @var array
 */
const MODES = [
    "F" => "Flight",
    "T" => "Train",
    "S" => "Ship",
    "R" => "Road trip",
];

/**
 * @deprecated use MODES constant
 */
$modes = MODES;

/**
 * Mapping of mode operators from shorthand
 *
 * @TODO: Internationalise?
 *
 * @var array
 */
const MODES_OPERATOR = [
    "F" => "airline",
    "T" => "railway",
    "S" => "shipping company",
    "R" => "road transport company",
];

/**
 * @deprecated use MODES_OPERATOR constant
 */
$modeOperators = MODES_OPERATOR;

/**
 * End with JSON-formatted data, localized message and a successful status.
 *
 * @param $data array
 */
function json_success($data) {
    $data["status"] = 1;
    $data["message"] = _($data["message"]);
    die(json_encode($data));
}

/**
 * Abort with a JSON-formatted localized error message.
 *
 * @param $msg string
 * @param $detail string
 */
function json_error($msg, $detail = '') {
    $ret = [
        "status" => 0,
        "message" => _($msg),
    ];
    // Only append $detail to message if it's not an empty string
    if ($detail !== '') {
        $ret["message"] .= " {$detail}";
    }
    die(json_encode($ret));
}

/**
 * Standard formatting of airport data.
 *
 * @param $row array associative array containing IATA and ICAO codes
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
 * Standard formatting of airport codes.
 *
 * @param $row array associative array containing IATA and ICAO codes
 * @return string
 */
function format_apcode($row) {
    return format_apcode2($row["iata"], $row["icao"]);
}

/**
 * @param $iata string IATA code
 * @param $icao string ICAO code
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
 * Standard formatting of airport names.
 *
 * @param $row array associative array containing name, city, country/code and IATA/ICAO codes
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
    if (strncasecmp(strtr($name, "-", " "), strtr($city, "-", " "), mb_strlen($city)) == 0) {
        $city = "";
    } else {
        $city .= "-";
    }
    if (mb_strlen($city . $name . $country) > 40) {
        $name = trim(mb_substr($city . $name, 0, 39 - mb_strlen($country))) . ".";
        $city = "";
    }

    return $city . $name . " (" . $iata . "), " . $country;
}

/**
 * Standard formatting of airline names.
 *
 * @param $row array associative array containing name, IATA/ICAO codes, and optionally the mode
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
 * Calculate (distance, duration) between two Airport IDs.
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $src_apid string Source Airport ID
 * @param $dst_apid string Destination Airport ID
 * @return array [ distance, duration ]
 */
function gcDistance($dbh, $src_apid, $dst_apid) {
    // Special case: loop flight to/from the same airport
    if ($src_apid == $dst_apid) {
        $dist = 0;
    } else {
        $sth = $dbh->prepare(
            "SELECT x, y FROM airports WHERE apid = ? OR apid = ?"
        );
        $sth->execute([$src_apid, $dst_apid]);

        if ($sth->rowCount() !== 2) {
            return [null, null];
        }

        $coord1 = $sth->fetch();
        $from = ['x' => $coord1["x"], 'y' => $coord1["y"]];
        $coord2 = $sth->fetch();
        $to = ['x' => $coord2["x"], 'y' => $coord2["y"]];

        $dist = gcPointDistance($from, $to);
    }
    $duration = gcDuration($dist);
    return [$dist, $duration];
}

/**
 * @param $dist float|int
 * @return string
 */
function gcDuration($dist) {
    $rawtime = floor(30 + ($dist / 500) * 60);
    return sprintf("%02d:%02d", floor($rawtime / 60), $rawtime % 60);
}

/**
 * Convert a filename (relative to the document root) to a relative URL with a date-based version string appended.
 *
 * @param $filename string|null Relative filename (e.g. "/js/foo.js")
 * @return string Relative filename with a version parameter (e.g. "/js/foo.js?version=20120102")
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
 * Hack to record X-Y and Y-X flights as same in DB.
 *
 * @param $src_apid string Source Airport ID
 * @param $dst_apid string Destination Airport ID
 * @return array
 */
function orderAirports($src_apid, $dst_apid) {
    if ($src_apid > $dst_apid) {
        return [$dst_apid, $src_apid, "Y"];
    }

    return [$src_apid, $dst_apid, "N"];
}

/**
 * if $cond then echo $value.
 *
 * @param $cond bool Whether to echo $value
 * @param $value string Value to be echo-ed
 */
function condOut($cond, $value) {
    if ($cond) {
        echo $value;
    }
}

/**
 * if $arr[$key] == $value then echo $true else echo $false.
 *
 * @param $arr array
 * @param $key string
 * @param $value mixed
 * @param $true string String to output when $arr[$key] == $value
 * @param $false string Default ''
 */
function condArrOut($arr, $key, $value, $true, $false = '') {
    echo $arr[$key] == $value
        ? $true
        : $false;
}

/**
 * @param $src mixed
 * @param $dst mixed
 * @param $flip bool Whether to swap $dst and $src
 * @return array
 */
function flip($src, $dst, $flip) {
    // if !$flip return [ $src, $dst ]
    // if $flip return [ $dst, $src ]
    return [
        $flip ? $dst : $src,
        $flip ? $src : $dst,
    ];
}
