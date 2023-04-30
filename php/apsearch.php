<?php

require_once '../vendor/autoload.php';
require_once '../php/locale.php';
require_once '../php/db_pdo.php';
require_once '../php/helper.php';

header("Content-type: text/html");

$airport = $_POST["name"];
$iata = $_POST["iata"];
$icao = $_POST["icao"];
$city = $_POST["city"];
$country = $_POST["country"];
$code = $_POST["code"];
$myX = $_POST["x"];
$myY = $_POST["y"];
$elevation = $_POST["elevation"];
$tz = $_POST["timezone"];
$dst = $_POST["dst"];
$dbname = $_POST["db"];
$iatafilter = $_POST["iatafilter"];
$offset = intval($_POST["offset"]);
$action = $_POST["action"];
$apid = $_POST["apid"];

$uid = $_SESSION["uid"] ?? null;

if ($action == "RECORD") {
    if (!$uid || empty($uid)) {
        json_error("Your session has timed out, please log in again.");
    }

  // Check for potential duplicates (unless admin)
    $duplicates = array();
    if ($uid != $OF_ADMIN_UID) {
        $filters = array();
        $filterParams = array();
        if ($apid && $apid != "") {
            $filters[] = "apid=?";
            $filterParams[] = $apid;
        }
        if ($iata != "") {
            $filters[] = " iata=?";
            $filterParams[] = $iata;
        }
        if ($icao != "") {
            $filters[] = " icao=?";
            $filterParams[] = $icao;
        }

        $sql = "SELECT * FROM airports WHERE " . implode(" OR ", $filters);
        $sth = $dbh->prepare($sql);
        $sth->execute($filterParams);
        while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
            if ($row['uid'] != $uid || $row['apid'] != $apid) {
                $duplicates[] = print_r($row, true);
            }
        }
    }

    if (!$apid || $apid == "") {
        $sql = "INSERT INTO airports(name,city,country,iata,icao,x,y,elevation,timezone,dst,uid) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $airport,
            $city,
            $country,
            $iata == "" ? null : $iata,
            $icao == "" ? null : $icao,
            $myX,
            $myY,
            $elevation,
            $tz,
            $dst,
            $uid
        ];
    } else {
        // Editing an existing airport
        $sql = "UPDATE airports SET name=?, city=?, country=?, iata=?, icao=?, x=?, y=?, elevation=?, timezone=?, dst=? WHERE apid=?";
        $params = [
            $airport,
            $city,
            $country,
            $iata == "" ? null : $iata,
            $icao == "" ? null : $icao,
            $myX,
            $myY,
            $elevation,
            $tz,
            $dst,
            $apid
        ];
    }

    if (empty($duplicates)) {
        $sth = $dbh->prepare($sql);
        $sth->execute($params) or json_error("Adding new airport failed.");
        if (! $apid || $apid == "") {
            json_success(array("apid" => $dbh->lastInsertId(), "message" => "New airport successfully added."));
        } else {
            if ($sth->rowCount() == 1) {
                json_success(array("apid" => $apid, "message" => "Airport successfully edited."));
            } else {
                json_error("Editing airport failed.");
            }
        }
    } else {
        $name = $_SESSION['name'];
        $newEdit = print_r(
            [
                'apid' => $apid,
                'name' => $airport,
                'city' => $city,
                'country' => $country,
                'iata' => $iata,
                'icao' => $icao,
                'x' => $myX,
                'y' => $myY,
                'elevation' => $elevation,
                'timezone' => $tz,
                'dst' => $dst
            ],
            true
        );
        $existingData = print_r(implode("\n", $duplicates), true);
        $subject = sprintf("Update airport %s (%s/%s)", $airport, $iata, $icao);
        $body = <<<TXT
New airport edit suggestion submitted by $name:

```
$newEdit;
```

Existing, potentially conflicting airport information:

```
$existingData;
```

Cross-check this edit on other sites with compatible licensing:
- OurAirports: https://ourairports.com/airports/$icao/pilot-info.html
- Wikipedia: https://www.google.com/search?q=wikipedia%20$icao%20airport&btnI
TXT;
        if (isset($_POST["unittest"])) {
            echo $subject . "\n\n" . $body;
            exit;
        }
        $identifier = ($icao == "") ? $iata : $icao;
        $github = new \Github\Client();
        $github->authenticate($GITHUB_ACCESS_TOKEN, null, Github\AuthMethod::ACCESS_TOKEN);

        $issues = $github->api('search')->issues("repo:$GITHUB_USER/$GITHUB_REPO in:title $identifier");
        if (count($issues['items']) > 0) {
            // Existing issue, add comment
            $issue_number = $issues['items'][0]['number'];
            $result = $github->api('issue')->comments()->create(
                $GITHUB_USER,
                $GITHUB_REPO,
                $issue_number,
                array('body' => $body)
            );
        } else {
            // New issue
            $result = $github->api('issue')->create(
                $GITHUB_USER,
                $GITHUB_REPO,
                array('title' => $subject, 'body' => $body, 'labels' => array('airport'))
            );
            $issue_number = $result['number'];
        }
        if (true) {
            $message = "Edit submitted for review on Github: Issue {$issue_number}, {$result['html_url']}";
            json_success(array("apid" => $apid, "message" => $message));
        } else {
            json_error("Could not submit edit for review, please contact <a href='/about'>support</a>.");
        }
    }
    exit;
}

if ($dbname != "airports" || $dbname != "airports_dafif" || $dbname != "airports_oa") {
    $dbname = "airports";
}
$sql = "SELECT * FROM $dbname WHERE ";
$params = [];

if ($action == "LOAD") {
    // Single-airport fetch
    $sql .= " apid=?";
    $params[] = $apid;
    $offset = 0;
} else {
  // Real search, build filter
    if ($airport) {
        $sql .= " name LIKE ? AND";
        $params[] = "%$airport%";
    }
    if ($iata) {
        $sql .= " iata=? AND";
        $params[] = $iata;
    }
    if ($icao) {
        $sql .= " icao=? AND";
        $params[] = $icao;
    }
    if ($city) {
        $sql .= " city LIKE ? AND";
        $params[] = $city . '%';
    }
    if ($country != "ALL") {
        if ($dbname == "airports_dafif" || $dbname == "airports_oa") {
            if ($code) {
                $sql .= " code=? AND";
                $params[] = $code;
            }
        } else {
            if ($country) {
                $sql .= " country=? AND";
                $params[] = $country;
            }
        }
    }

    // Disable this filter for DAFIF (no IATA data)
    if ($iatafilter == "false" || $dbname == "airports_dafif") {
        $sql .= " 1=1"; // dummy
    } else {
        $sql .= " iata != '' AND iata != 'N/A'";
    }
}

if (!$offset || !is_int($offset)) {
    $offset = 0;
}

// Check result count
$sql2 = str_replace("*", "COUNT(*)", $sql);
$sth = $dbh->prepare($sql2);
if (!$sth->execute($params)) {
    // TODO: $param is undefined; use $action ?
    json_error('Operation ' . $param . ' failed.');
}
if ($row = $sth->fetch()) {
    $max = $row[0];
}
if ($max == 0) {
    json_error('No airports matching your query exist.');
}
$response = array("status" => 1, "offset" => $offset, "max" => $max);

// Fetch airport data
$sql .= " ORDER BY name LIMIT 10 OFFSET $offset";
$sth = $dbh->prepare($sql);
if (!$sth->execute($params)) {
    die(json_encode(array("status" => 0, "message" => 'Operation ' . $param . ' failed.')));
}
while ($rows[] = $sth->fetch(PDO::FETCH_ASSOC));
array_pop($rows);
foreach ($rows as &$row) {
    if ($dbname == "airports_dafif" || $dbname == "airports_oa") {
        $row["country"] = $row["code"];
    }
    if ($row["uid"] || $uid == $OF_ADMIN_UID) {
        if ($row["uid"] == $uid || $uid == $OF_ADMIN_UID) {
            $row["ap_uid"] = "own"; // editable
        } else {
            $row["ap_uid"] = "user"; // added by another user
        }
    } else {
        $row["ap_uid"] = null; // in DB
    }
    $row["ap_name"] = format_airport($row);
    unset($row["uid"]);
}
$response['airports'] = $rows;
print json_encode($response);
