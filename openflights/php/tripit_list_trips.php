<?php
require_once("locale.php");
require_once("db_pdo.php");
require_once("tripit_common.php");
require_once("helper.php");

$DEBUG = false;

$uid = $_SESSION["uid"];
if (!$uid or empty($uid)) {
  print _("Not logged in, aborting");
  exit();
}

// This page requires that we're linked to a TripIt account already.
$tripit_tokens = require_tripit_link($dbh, $uid);

$oauth_cred = new OAuthConsumerCredential($tripit_app_id, $tripit_app_secret, $tripit_tokens["token"], $tripit_tokens["secret"]);
$tripit = new TripIt($oauth_cred, $tripit_api_url);

// Figure out if user wants past or future trips.
$wants_future_trips = isset($_REQUEST["future"]) ? $_REQUEST["future"] : null;
// Check that the parameter is valid.  If not, default to past.
if ($wants_future_trips == null || !is_numeric($wants_future_trips)) {
  $wants_future_trips = 0;
}

// Page number, in case we have more trips than can be returned in a single TripIt call.
$tripit_page_number = isset($_REQUEST["page"]) ? $_REQUEST["page"] : null;
if ($tripit_page_number < 2 || $tripit_page_number > 10000) {
  $tripit_page_number = 1;
}

// For future trips, include all objects.  For past trips, do not include all objects.
$trips = $tripit->list_trip(array(
  'traveler' => 'true',
  'include_objects' => 'true',
  'past' => $wants_future_trips ? 'false' : 'true',
  'page_num' => $tripit_page_number,
));
if (!isset($trips) or !isset($trips->Trip)) {
  handle_tripit_response($tripit->response);
  error_log("TripIt error for user $uid: " . $tripit->response);
  die(_("Could not connect to TripIt.  Please try again later."));
}

# Get the list of trips, sorted by start date, oldest to newest.
$trip_index_by_date = array();
for ($i = 0; $i < count($trips->Trip); $i++) {
  array_push($trip_index_by_date, $i);
}
usort($trip_index_by_date, function($a, $b) {
  global $trips;
  date_default_timezone_set('America/Los_Angeles');
  $a_date = new DateTime($trips->Trip[$a]->start_date . " 00:00:00");
  $b_date = new DateTime($trips->Trip[$b]->start_date . " 00:00:00");
  if ($a_date < $b_date)
    return -1;
  if ($a_date > $b_date)
    return 1;
  return 0;
});

# If it's a past trip, sort from newest to oldest.
if(!$wants_future_trips) {
  $trip_index_by_date = array_reverse($trip_index_by_date);
}

# Build Trip ID to segment/ticket mapping
$all_trip_segments = array();
foreach ($trips->AirObject as $ticket) {
  foreach ($ticket->Segment as $segment) {
    if (!isset($all_trip_segments["$ticket->trip_id"])) {
      $all_trip_segments["$ticket->trip_id"] = array();
    }
    # Add the name of traveler on this segment.
    $segment->addChild("pax", generate_pax_string($ticket->Traveler));
    array_push($all_trip_segments["$ticket->trip_id"], $segment);
  }
}
foreach (array_keys($all_trip_segments) as $trip) {
  usort($all_trip_segments["$trip"], function($a, $b) {
    $a_date = tripit_date_to_datetime($a->StartDateTime);
    $b_date = tripit_date_to_datetime($b->StartDateTime);
    if ($a_date < $b_date)
      return -1;
    if ($a_date > $b_date)
      return 1;
    return 0;
  });
}

/**
 * Display links to pick between future vs. past trips.
 * @param $future int 0 for past, 1 for future
 */
function show_past_future_selector($future) {
  print _("Show") . " ";
  if ($future) {
    print '<a href="?future=0">' . _("past trips") . '</a> ';
    print '<b>' . _("future trips") . '</b>';
  } else {
    print '<b>' . _("past trips") . '</b> ';
    print '<a href="?future=1">' . _("future trips") . '</a>';
  }
  print "<br>\n";
}

/**
 * Print out a list of links to select the desired page of TripIt trips.
 * @param $cur_page int Page we're currently on
 * @param $max_page int Total number of pages
 */
function show_page_numbers($cur_page, $max_page) {
  global $wants_future_trips;

  if ($cur_page > 1) {
    print "<a href='?future=$wants_future_trips&page=" . ($cur_page - 1) . "'><b>" . _("Previous Page") . "</b></a>\n";
  } else {
    print _("Page") . " ";
  }
  for ($i = 1; $i <= $max_page; $i++) {
    if ($cur_page == $i) {
      print "<b>$i</b> ";
    } else {
      print "<a href='?future=$wants_future_trips&page=$i'>$i</a>\n";
    }
  }
  if ($cur_page < $max_page) {
    print "<a href='?future=$wants_future_trips&page=" . ($cur_page + 1) . "'><b>" . _("Next Page") . "</b></a>\n";
  }
  print "<br>\n";
}

/**
 * Convert a TripIt DateTime object into a PHP DateTime object
 * @param $tripit_date
 * @return DateTime representation
 */
function tripit_date_to_datetime($tripit_date) {
  $date_str = $tripit_date->date . ' ' . $tripit_date->time . ' GMT' . $tripit_date->utc_offset;
  $dt = new DateTime($date_str);
  try {
    $timezone = new DateTimeZone($tripit_date->timezone);
    $dt->setTimezone($timezone);
  } catch (Exception $e) {
    error_log("Couldn't parse TripIt timezone of " . $tripit_date->timezone . "; going with default.");
    ;
  }
  return new DateTime($date_str);
}

function generate_pax_string($travelers) {
  $pax = array();
  foreach ($travelers as $traveler) {
    array_push($pax, $traveler->first_name . ' ' . $traveler->last_name);
  }
  return join(", ", $pax);
}

function resolve_airport($iata_code) {
  global $dbh;
  try {
    $sth = $dbh->prepare("SELECT * FROM airports WHERE iata=?");
    $sth->execute(array($iata_code));
    if ($sth->rowCount()) {
      $result = $sth->fetch();
      return array("id" => $result["apid"], "name" => htmlentities(format_airport($result)));
    } else {
      return null;
    }
  } catch (PDOException $e) {
    die(_("Database error."));
  }
}

function resolve_airline($iata_code) {
  global $dbh;
  try {
    $sth = $dbh->prepare("SELECT * FROM airlines WHERE iata=?");
    $sth->execute(array($iata_code));
    if ($sth->rowCount()) {
      $result = $sth->fetch();
      return array("id" => $result["alid"], "name" => htmlentities(format_airline($result)));
    } else {
      return null;
    }
  } catch (PDOException $e) {
    die(_("Database error."));
  }
}

/**
 * Try and guess the class of a given flight.
 * @param $class Class name
 * @return array Array of CHECKED values, keyed by single-letter class Y, P, C, or F.
 */
function detect_class($class) {
  global $DEBUG;
  # From http://en.wikipedia.org/wiki/IATA_class_codes
  # We'll ony map F and C; everything else we'll assume is Y.
  $class_codes = array(
    "R" => "F",
    "P" => "F",
    "F" => "F",
    "A" => "F",

    "J" => "C",
    "C" => "C",
    "D" => "C",
    "I" => "C",
    "Z" => "C",
  );

  # Initialize all to empty.
  $class_arr = array(
    "Y" => "",
    "P" => "",
    "C" => "",
    "F" => "",
  );

  # Skip all detection and assume Economy if the class is empty.
  if (empty($class)) {
    if ($DEBUG)
      error_log("detect_class: class empty, default economy");
    $class_arr["Y"] = " CHECKED";
    return $class_arr;
  }

  if (stristr($class, "econ") || stristr($class, "coach")) {
    # Economy
    # Check to see if it's Premium
    if (stristr($class, "prem")) {
      if ($DEBUG)
        error_log("detect_class: $class => Premium Economy via string match");
      $class_arr["P"] = " CHECKED";
    } else {
      if ($DEBUG)
        error_log("detect_class: $class => Economy via string match");
      $class_arr["Y"] = " CHECKED";
    }
  } elseif (stristr($class, "bus") && !stristr($class, "first")) {
    # Business
    # UA's "BusinessFirst" is generally considered to be F and not C.
    if ($DEBUG)
      error_log("detect_class: $class => Business via string match");
    $class_arr["C"] = " CHECKED";
  } elseif (stristr($class, "first")) {
    # First
    if ($DEBUG)
      error_log("detect_class: $class => First via string match");
    $class_arr["F"] = " CHECKED";
  } elseif (strlen($class) == 1) {
    # Maybe it's a class code.
    $class = strtoupper($class);
    if (array_key_exists($class, $class_codes)) {
      if ($DEBUG)
        error_log("detect_class: $class => $class_codes[$class] via class code");
      $class_arr[$class_codes[$class]] = " CHECKED";
    } else {
      if ($DEBUG)
        error_log("detect_class: $class => Y via other class code");
      $class_arr["Y"] = " CHECKED";
    }
  } else {
    # No clue.  Assume Economy.
    if ($DEBUG)
      error_log("detect_class: $class => Y via default guess");
    $class_arr["Y"] = " CHECKED";
  }

  return $class_arr;
}

/**
 * Check to see if this user already has this segment imported.
 * @param $date DateTime
 * @param $from int Source airport ID
 * @param $to int Destination airport ID
 * @param $flight_number string Flight number (e.g. UA451)
 */
function is_duplicate_segment($date, $from, $to, $flight_number) {
  global $dbh, $DEBUG;
  list($src_apid, $dst_apid, $opp) = orderAirports($from, $to);
  $mysql_date = $date->format('Y-m-d');
  $sth = $dbh->prepare("SELECT COUNT(*) as NUM FROM flights WHERE uid=? AND src_date=? AND src_apid=? AND dst_apid=? AND opp=? AND code=?");
  $sth->execute(array($_SESSION["uid"], $mysql_date, $src_apid, $dst_apid, $opp, $flight_number));
  $result = $sth->fetch();
  if ($DEBUG) {
    error_log("is_duplicate_segment: $mysql_date $from-$to on $flight_number for user $_SESSION[uid] has duplicate status: " . $result[0]);
  }
  return $result[0] != 0;
}

/**
 * Display a single trip.
 *
 * @param $trip SimpleXMLElement Single Trip object from TripIt
 */
function display_trip($trip) {
  global $all_trip_segments;
  ?>
<div class="trip_header">
  <div class="import_all" id="import_all_<?php echo htmlentities($trip->id) ?>"></div>
  <h2>
    <a style="text-decoration: none" target="_blank"
       href="http://www.tripit.com<?php echo htmlentities($trip->relative_url) ?>"><?php echo htmlentities($trip->display_name) ?></a>
  </h2>
</div>
<?php
  $valid_segments = array();
  foreach ($all_trip_segments["$trip->id"] as $segment) {
    $valid_segment = display_segment($segment);
    // Save valid segments to be used with "Import All"
    if($valid_segment) {
      array_push($valid_segments, $segment->id);
    }
  }

  // If we had some valid segments, add button to display all.
  if (sizeof($valid_segments) > 0) {
    $segment_string = "'" . implode("','", $valid_segments) . "'";
    echo '<script type="text/javascript">addImportAllButton("' . _("Import All") . '", ' . $trip->id . ', "' . $segment_string . '")</script>';
  }
}

/**
 * Display a single flight segment, along with buttons to save it.
 * @param $segment SimpleXMLElement Single segment object from TripIt
 * @return boolean true if segment is importable, false otherwise.
 */
function display_segment($segment) {
  // FIXME - Calculating this here seems wrong; we already implement most of this in JavaScript already, but
  // it's all specific to our input form.
  $start_time = tripit_date_to_datetime($segment->StartDateTime);
  $end_time = tripit_date_to_datetime($segment->EndDateTime);
  $delta_minutes = ($end_time->getTimestamp() - $start_time->getTimestamp()) / 60;
  $duration = sprintf("%02d:%02d", floor($delta_minutes / 60), $delta_minutes % 60);

  $start_date = $start_time->format('Y-m-d');
  $start_hm = $start_time->format('G:i');
  $end_hm = $end_time->format('G:i');

  $src_ap = resolve_airport($segment->start_airport_code);
  $dst_ap = resolve_airport($segment->end_airport_code);

  # Prefer the operating airline over the marketing airline.
  if (!empty($segment->operating_airline_code) && !empty($segment->operating_flight_number)) {
    $flight_num = htmlentities(strtoupper($segment->operating_airline_code) . $segment->operating_flight_number);
    $airline = resolve_airline($segment->operating_airline_code);
  } else {
    $flight_num = htmlentities(strtoupper($segment->marketing_airline_code) . $segment->marketing_flight_number);
    $airline = resolve_airline($segment->marketing_airline_code);
  }

  $is_duplicate = is_duplicate_segment($start_time, $src_ap["id"], $dst_ap["id"], $flight_num);

  $aircraft = htmlentities($segment->aircraft_display_name);

  # Try to do some smart class detection.
  $classes = detect_class($segment->service_class);

  # Make sure we have the bare minimum amount of data to do an import.
  $is_valid = false;
  if($src_ap != null && $dst_ap != null && isset($start_date)) {
    $is_valid = true;
  }
  ?>
<div class="segment" id="segment<?php echo $segment->id ?>">
  <form id="import<?php echo $segment->id ?>">
    <input type="hidden" name="param" value="ADD">
    <input type="hidden" name="mode" value="F">

    <div class="segment-left-cell">
      <?php echo _("Passenger") ?>: <?php echo htmlentities($segment->pax) ?>
      <br>

      <?php echo _("Date") ?>:
      <!-- Even thought we display the start and end time, we only store the start date, start time, and duration. -->
      <?php echo $start_date ?> &rarr;
      <?php echo $start_hm ?> &rarr;
      <?php echo $end_hm ?>
      <input type="hidden" name="src_date" value="<?php echo $start_date ?>">
      <input type="hidden" name="src_time" value="<?php echo $start_hm ?>">
      <input type="hidden" name="duration" value="<?php echo $duration ?>">
      <br>

      <?php echo _("From") ?>: <?php echo htmlentities($src_ap["name"]) ?>
      <input type="hidden" name="src_apid" value="<?php echo $src_ap["id"] ?>">
      <br>

      <?php echo _("To") ?>: <?php echo htmlentities($dst_ap["name"]) ?>
      <input type="hidden" name="dst_apid" value="<?php echo $dst_ap["id"] ?>">
      <br>

      <?php echo _("Nr.") ?> <?php echo $flight_num ?> - <?php echo $airline["name"] ?>
      <input type="hidden" name="number" value="<?php echo $flight_num ?>"/>
      <input type="hidden" name="alid" value="<?php echo $airline["id"] ?>"/>
    </div>
    <div class="segment-right-cell">
      <?php if ($is_duplicate) { ?>
      <input type="button" value="Import" disabled>
      <?php } else { ?>
      <input type="button" onclick='importFlight(<?php echo $segment->id?>);' value="Import">
      <?php } ?>
      <span id="input_status<?php echo $segment->id ?>"></span>
      <br>

      <?php # TODO: Figure out what to do with trip. ?>
      <!--td><?php echo _("Trip") ?><a href="#help" onclick='JavaScript:help("trip")'><img src="/img/icon_help.png" title="Help: What is a trip?" height=11 width=10></a></td>
      <td width=""><span id="input_trip_select"></span> <img src="/img/icon_add.png" title="<?php echo _("Add new trip") ?>" height=17 width=17 onclick='JavaScript:editTrip("ADD")'/><img src="/img/icon_edit.png" title="<?php echo _("Edit this trip") ?>" height=17 width=17 onclick='JavaScript:editTrip("EDIT")'/></td-->

      <?php echo _("Plane") ?>: <?php echo $aircraft ?>
      <input type="hidden" id="plane" name="plane" value="<?php echo $aircraft ?>"/>
      <br>

      <?php echo _("Class") ?>&nbsp;
      <input type="radio" id="class_Y" name="class" value="Y"<?php echo $classes["Y"] ?>><label
        for="class_Y"><?php echo _("Economy") ?></label>
      <input type="radio" id="class_P" name="class" value="P"<?php echo $classes["P"] ?>><label
        for="class_P"><?php echo _("Premium Eco.") ?></label>
      <input type="radio" id="class_C" name="class" value="C"<?php echo $classes["C"] ?>><label
        for="class_C"><?php echo _("Business") ?></label>
      <input type="radio" id="class_F" name="class" value="F"<?php echo $classes["F"] ?>><label
        for="class_F"><?php echo _("First") ?></label>
      <br>

      <?php echo _("Reason") ?>&nbsp;
      <input type="radio" id="reason_B" name="reason" value="B" CHECKED><label
        for="reason_B"><?php echo _("Work") ?></label>
      <input type="radio" id="reason_L" name="reason" value="L"><label for="reason_L"><?php echo _("Leisure") ?></label>
      <input type="radio" id="reason_C" name="reason" value="C"><label for="reason_C"><?php echo _("Crew") ?></label>
      <input type="radio" id="reason_O" name="reason" value="O"><label for="reason_O"><?php echo _("Other") ?></label>
    </div>
  </form>
  <hr class="segment-separator"/>
</div>
<?php

  // Do error and duplicate checking.
  if(!$is_valid) {
    // If we don't have the bare minimum amount of data, prevent import.
    echo '<script type="text/javascript">markSegmentInvalid(' . $segment->id . ')</script>';
    // Log an error.  I suspect this will happen if we're having trouble parsing TripIt data.
    error_log("display_segment: segment " . $segment->id . " for user " . $_SESSION['uid'] . " was missing minimum import data");
    return false;
  } elseif ($is_duplicate) {
    // If we've already imported this segment, grey it out at load time.
    echo '<script type="text/javascript">markSegmentImported(' . $segment->id . ')</script>';
    return false;
  } else {
    return true;
  }
}

#### Start display ####
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
  <title>OpenFlights: <?php echo _("TripIt") ?></title>
    <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
    <link rel="stylesheet" href="<?php echo fileUrlWithDate("/openflights.css") ?>" type="text/css">
    <link rel="stylesheet" href="<?php echo fileUrlWithDate("/css/tripit.css") ?>" type="text/css">
    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
    <script type="text/javascript" src="<?php echo fileUrlWithDate("/js/jquery.blockUI.js") ?>"></script>
    <script type="text/javascript" src="<?php echo fileUrlWithDate("/js/tripit.js") ?>"></script>
  </head>

  <body>
    <div id="contexthelp">
      <h1>OpenFlights: <?php echo _("TripIt Trips") ?></h1>
      <span style="float: right"><INPUT type='button' value='<?php echo _("Close") ?>' onClick='javascript:parent.opener.refresh(true); window.close();'></span>

<?php
      # Show past/future selector
      show_past_future_selector($wants_future_trips);

      # Show some page numbers
      if (isset($trips->max_page)) {
        show_page_numbers($tripit_page_number, intval($trips->max_page));
      }

      # Print it out, trip by trip.
      foreach ($trip_index_by_date as $trip_index) {
        $trip = $trips->Trip[$trip_index];
        display_trip($trip);
      }

      # See if there are more pages to be shown.
      if (isset($trips->max_page) && $tripit_page_number < intval($trips->max_page)) {
        $next_page = $tripit_page_number + 1;
        print <<<NEXT_PAGE
<a href="?future=$wants_future_trips&page=$next_page"><b>Next Page</b></a>
NEXT_PAGE;
      }
?>
    </div>
  </body>
</html>