<?php

include_once "../php/db_pdo.php";
include_once "../php/greatcircle.php";

// First, we load the background/base map.
// We assume that it's located in the same dir as the script.
// This can be any format. We will also allocate the color for the marker.
$im = imagecreatefrompng("map-2048.png");
if (!$im) {
    die("Image not found");
}

imagealphablending($im, true);
$yellow = imagecolorallocatealpha($im, 0x99, 0xEE, 0, 95);

// Next need to find the base image size.
// We need these variables to be able to scale the long/lat coordinates.
$scale_x = imagesx($im);
$scale_y = imagesy($im);

$stderr = fopen("php://stderr", "w");

/**
 * @param $lat float
 * @param $lon float
 * @param $width int
 * @param $height int
 * @return array
 */
function getlocationcoords($lat, $lon, $width, $height) {
    $x = (($lon + 180) * ($width / 360));
    $y = ((($lat * -1) + 90) * ($height / 180));
    return ["x" => round($x), "y" => round($y)];
}

$sql = <<<SQL
    SELECT DISTINCT s.x AS sx, s.y AS sy, d.x AS dx, d.y AS dy
    FROM routes AS r, airports AS s, airports AS d
    WHERE r.src_apid = s.apid AND r.dst_apid = d.apid
    GROUP BY s.apid, d.apid;
SQL;

// Now we convert the long/lat coordinates into screen coordinates
$count = 0;
foreach ($dbh->query($sql) as $row) {
    $count++;
    if ($count % 100 === 0) {
        fwrite($stderr, "$count ");
    }

    $from = ["x" => $row["sx"], "y" => $row["sy"]];
    $to = ["x" => $row["dx"], "y" => $row["dy"]];
    $distance = gcPointDistance($from, $to);

    if ($distance > GC_MIN) {
        // Plot great circle curve
        $points = gcPath($from, $to, $distance, false);
    } else {
        // Draw straight lines
        $points = straightPath($from, $to);
    }
    $oldPoint = null;
    foreach ($points as $loc) {
        if ($loc === null) {
            $oldPoint = null;
            continue;
        }
        $newPoint = getlocationcoords($loc["y"], $loc["x"], $scale_x, $scale_y);
        if ($oldPoint) {
            imageline($im, $oldPoint["x"], $oldPoint["y"], $newPoint["x"], $newPoint["y"], $yellow);
        }
        $oldPoint = $newPoint;
    }
}

// Return the map image. We are using a PNG format as it gives better final image quality than a JPG
header("Content-type: image/png");
imagepng($im);
imagedestroy($im);
fclose($stderr);
