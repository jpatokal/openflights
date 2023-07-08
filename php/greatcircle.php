<?php

const PI = 3.1415926;

/**
 * in miles
 */
const EARTH_RADIUS = 3958.75;

/**
 * in miles
 */
const EARTH_CIRCUMFERENCE = 24900;

/**
 * in miles
 */
const MOON_DISTANCE = 238857;

/**
 * in miles
 */
const MARS_DISTANCE = 34649589;

/**
 * factor to convert degrees to radians (PI/180)
 */
const DEG2RAD =  0.01745329252;

/**
 * factor to convert radians to degrees
 */
const RAD2DEG = 57.29577951308;

/**
 * draw segment every GC_STEP mi
 */
const GC_STEP = 100;

/**
 * trigger GC paths once distance is greater than this
 */
const GC_MIN = 1000;

/**
 * compute ascent path every ASCENT_STEP miles
 */
const ASCENT_STEP = 1;

/**
 * meters per ASCENT_STEP (from sea level)
 */
const ASCENT_SPEED = 100;

/**
 * cruising altitude in meters
 */
const CRUISE_ALTITUDE = 10000;

/**
 * number of steps needed for the landing curve (integral of y(n)=y(n-1)+n?)
 */
const MAGIC_NUMBER = 19;

/**
 * Compute great circle distance from $from to $to
 *
 * @param $from array
 * @param $to array
 * @return false|float|int
 */
function gcPointDistance($from, $to) {
    $lon1 = $from["x"];
    $lat1 = $from["y"];
    $lon2 = $to["x"];
    $lat2 = $to["y"];

    // Eliminate one trivial case...
    if ($lon1 == $lon2 && $lat1 == $lat2) {
        return 0;
    }

    $rad = (PI / 180.0);
    $lon1 = (float)$lon1 * $rad;
    $lat1 = (float)$lat1 * $rad;
    $lon2 = (float)$lon2 * $rad;
    $lat2 = (float)$lat2 * $rad;

    $theta = $lon2 - $lon1;
    $dist = acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($theta));
    if ($dist < 0) {
        $dist += PI;
    }
    return floor($dist * 6371.2 * 0.621);
}

/**
 * Compute great circle bearing from point $from towards point $to
 *
 * @param $from array
 * @param $to array
 * @return float|int
 */
function gcBearingTo($from, $to) {
    $x1 = $from["x"] * DEG2RAD;
    $y1 = $from["y"] * DEG2RAD;
    $x2 = $to["x"] * DEG2RAD;
    $y2 = $to["y"] * DEG2RAD;

    $a = cos($y2) * sin($x2 - $x1);
    $b = cos($y1) * sin($y2) - sin($y1) * cos($y2) * cos($x2 - $x1);
    if (($a == 0) && ($b == 0)) {
        return 0;
    }
    if ($b == 0) {
        if ($a < 0) {
            return 270;
        }
        return 90;
    }

    if ($b < 0) {
        $adjust = PI;
    } elseif ($a < 0) {
        $adjust = 2 * PI;
    } else {
        $adjust = 0;
    }
    return (atan($a / $b) + $adjust) * RAD2DEG;
}

/**
 * Compute great circle waypoint "distance" miles away from $from in direction $bearing
 *
 * @param $from array
 * @param $distance
 * @param $bearing
 * @return array
 */
function gcWaypoint($from, $distance, $bearing) {
    // Math.* trig functions require angles to be in radians
    $x = $from["x"] * DEG2RAD;
    $y = $from["y"] * DEG2RAD;
    $radBearing = $bearing * DEG2RAD;

    // Convert arc distance to radians
    $d = $distance / EARTH_RADIUS;

    // Modified based on http://williams.best.vwh.net/avform.htm
    $lat = asin(
        sin($y) * cos($d) + cos($y) * sin($d) * cos($radBearing)
    );
    $lon = atan2(
        sin($radBearing) * sin($d) * cos($y),
        cos($d) - sin($y) * sin($lat)
    );
    $x = ($x + $lon) * RAD2DEG;
    $y = $lat * RAD2DEG;
    return array("x" => $x, "y" => $y);
}

/**
 * @param $startPoint array
 * @param $endPoint array
 * @return array
 */
function straightPath($startPoint, $endPoint) {
    // Do we cross the dateline?  If yes, then flip endPoint across it
    if (abs($startPoint["x"] - $endPoint["x"]) > 180) {
        if ($startPoint["x"] < $endPoint["x"]) {
            $endPoint["x"] -= 360;
        } else {
            $endPoint["x"] += 360;
        }
    }
    return array($startPoint, $endPoint);
}

/**
 * If $threed = true, plot path in three dimensions (x,y,z), else only two (x,y)
 *
 * @param $startPoint array
 * @param $endPoint array
 * @param $distance int|float|false|null
 * @param $threed bool
 * @return array
 */
function gcPath($startPoint, $endPoint, $distance, $threed) {
    $pointList = array(
        $startPoint
    );
    $wayPoint = $startPoint;
    $distance = $distance ?? gcPointDistance($startPoint, $endPoint);
    $elevation = 0;

    if ($threed) {
        $elevation = $startPoint["z"];
        $delta = 1; // Ascending

        // Calculate distance at which to start descent
        $ascentSpeed = (CRUISE_ALTITUDE - $startPoint["z"]) / ASCENT_SPEED;
        $descentSpeed = (CRUISE_ALTITUDE - $endPoint["z"]) / ASCENT_SPEED;
        $descentPoint = $distance - (MAGIC_NUMBER * ASCENT_STEP);
        if ($descentPoint < $distance / 2) {
            $descentPoint = $distance / 2;
        }
    }
    $d = 0;

    // And... action!
    while ($d + 1 < $distance) {
        /*printf("%s of %s: from %s,%s now at %s,%s step %s bearing %s target %s,%s\n",
            $d,
            $distance,
            $startPoint["x"],
            $startPoint["y"],
            $step,
            $bearing,
            $wayPoint["x"],
            $wayPoint["y"],
            $endPoint["x"],
            $endPoint["y"]
        );*/

        // Cruising, but increase step resolution near the poles
        if ($threed && $delta != 0) {
            $step = ASCENT_STEP;
        } elseif (abs($wayPoint["y"]) > 60) {
            $step = GC_STEP / 2;
        } else {
            $step = GC_STEP;
        }

        if ($threed) {
            // Ascending
            if ($delta > 0) {
                if ($elevation < (CRUISE_ALTITUDE - $startPoint["z"]) / 2 + $startPoint["z"]) {
                    $delta += $ascentSpeed;
                } else {
                    $delta -= $ascentSpeed;
                }
                if ($elevation >= CRUISE_ALTITUDE || $delta < 0) {
                    $elevation = CRUISE_ALTITUDE;
                    $delta = 0;
                }
            }

            // Descending
            if ($d + $step >= $descentPoint) {
                if ($d >= $descentPoint) {
                    if ($elevation > (CRUISE_ALTITUDE - $endPoint["z"]) / 2 + $endPoint["z"]) {
                        $delta -= $descentSpeed;
                    } else {
                        $delta += $descentSpeed;
                    }
                    $step = ASCENT_STEP;
                } else {
                    // Prepare for descent!
                    $step = $descentPoint - $d;
                }
                if ($elevation < $endPoint["z"]) {
                    $delta = 0;
                    $elevation = $endPoint["z"];
                }
            }
            $elevation += $delta;
        }

        $bearing = gcBearingTo($wayPoint, $endPoint); // degrees, clockwise from 0 deg at north
        $wayPoint = gcWaypoint($wayPoint, $step, $bearing);
        if ($threed) {
            $wayPoint["z"] = $elevation;
        }

        if ($wayPoint["x"] > -180 && $wayPoint["x"] < 180) {
            $pointList[] = $wayPoint;
        } else {
            // Flip paths crossing the edge of the map
            if ($wayPoint["x"] < -180 && $bearing > 180) {
                $pointList[] = array("x" => -180, "y" => $wayPoint["y"], "z" => $elevation);
                $wayPoint["x"] += 360;
                $pointList[] = null; // break mark
                $pointList[] = array("x" => 180, "y" => $wayPoint["y"], "z" => $elevation);
            }
            if ($wayPoint["x"] > 180 && $bearing < 180) {
                $pointList[] = array("x" => 180, "y" => $wayPoint["y"], "z" => $elevation);
                $wayPoint["x"] -= 360;
                $pointList[] = null; // break mark
                $pointList[] = array("x" => -180, "y" => $wayPoint["y"], "z" => $elevation);
            }
        }

        $d = gcPointDistance($startPoint, $wayPoint);
    }
    $pointList[] = $endPoint;
    return $pointList;
}
