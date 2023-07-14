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
$tableName = $_POST["db"];
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
    $duplicates = [];
    if (!in_array($uid, (array)$OF_ADMIN_UID)) {
        $filters = [];
        $filterParams = [];
        if ($apid && $apid != "") {
            $filters[] = "apid = ?";
            $filterParams[] = $apid;
        }
        if ($iata != "") {
            $filters[] = "iata = ?";
            $filterParams[] = $iata;
        }
        if ($icao != "") {
            $filters[] = "icao = ?";
            $filterParams[] = $icao;
        }

        $sql = "SELECT * FROM airports WHERE " . implode(" OR ", $filters);
        $sth = $dbh->prepare($sql);
        $sth->execute($filterParams);
        foreach ($sth->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['uid'] != $uid || $row['apid'] != $apid) {
                $duplicates[] = print_r($row, true);
            }
        }
    }

    if (!$apid || $apid == "") {
        $sql = <<<SQL
            INSERT INTO airports(name, city, country, iata, icao, x, y, elevation, timezone, dst, uid)
            VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
SQL;
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
        $sql = <<<SQL
            UPDATE airports
            SET name = ?, city = ?, country = ?, iata = ?, icao = ?, x = ?, y = ?, elevation = ?, timezone = ?, dst = ?
            WHERE apid = ?
SQL;
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
        if (!$sth->execute($params)) {
            json_error("Adding new airport failed.");
        }
        if (!$apid || $apid == "") {
            json_success(["apid" => $dbh->lastInsertId(), "message" => "New airport successfully added."]);
        } elseif ($sth->rowCount() === 1) {
            json_success(["apid" => $apid, "message" => "Airport successfully edited."]);
        } else {
            json_error("Editing airport failed.");
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
        $github = new Client();
        try {
            $github->authenticate($GITHUB_ACCESS_TOKEN, null, Github\AuthMethod::ACCESS_TOKEN);

            $issues = $github->api('search')->issues("repo:$GITHUB_USER/$GITHUB_REPO is:issue in:title $identifier");

            if (count($issues['items']) > 0) {
                // Existing issue, add comment
                $issueNumber = $issues['items'][0]['number'];
                // https://docs.github.com/en/rest/issues/comments?apiVersion=2022-11-28#create-an-issue-comment
                $result = $github->api('issue')->comments()->create(
                    $GITHUB_USER,
                    $GITHUB_REPO,
                    $issueNumber,
                    ['body' => $body]
                );
            } else {
                // New issue
                // https://docs.github.com/en/rest/issues/issues?apiVersion=2022-11-28#create-an-issue
                $result = $github->api('issue')->create(
                    $GITHUB_USER,
                    $GITHUB_REPO,
                    ['title' => $subject, 'body' => $body, 'labels' => ['airport']]
                );
                $issueNumber = $result['number'];
            }

            $message = "Edit submitted for review on Github: Issue {$issueNumber}, {$result['html_url']}";
            json_success(["apid" => $apid, "message" => $message]);
        } catch (GitHub\Exception\RuntimeException $ex) {
            // $ex->code === 401 is Unauthorised
            // Probably not localised...
            json_error($ex->getMessage());
            // json_error(_("Could not submit edit for review, please contact <a href='/about'>support</a>."));
        }
    }
    exit;
}

if ($tableName != "airports" || $tableName != "airports_dafif" || $tableName != "airports_oa") {
    $tableName = "airports";
}

$filters = [];
$filterParams = [];

if ($action == "LOAD") {
    // Single-airport fetch
    $filters[] = "apid = ?";
    $filterParams[] = $apid;
    $offset = 0;
} else {
    // Real search, build filter
    if ($airport) {
        $filters[] = "name LIKE ?";
        $filterParams[] = "%$airport%";
    }
    if ($iata) {
        $filters[] = "iata = ?";
        $filterParams[] = $iata;
    }
    if ($icao) {
        $filters[] = "icao = ?";
        $filterParams[] = $icao;
    }
    if ($city) {
        $filters[] =  "city LIKE ?";
        $filterParams[] = $city . '%';
    }
    if ($country != "ALL") {
        if ($tableName == "airports_dafif" || $tableName == "airports_oa") {
            if ($code) {
                $filters[] =  "code = ?";
                $filterParams[] = $code;
            }
        } elseif ($country) {
            $filters[] = "country = ?";
            $filterParams[] = $country;
        }
    }

    // Disable this filter for DAFIF (no IATA data)
    if ($iatafilter != "false" && $tableName != "airports_dafif") {
        $filters[] = "iata != ''";
        $filters[] = "iata != 'N/A'";
    }
}

$sql = "SELECT * FROM $tableName WHERE " . implode(" AND ", $filters);

// Check result count
$sth = $dbh->prepare(str_replace("*", "COUNT(*)", $sql));
if (!$sth->execute($filterParams)) {
    json_error("Operation $action failed.");
}
$row = $sth->fetch();
if ($row) {
    $max = $row[0];
}
if ($max == 0) {
    json_error('No airports matching your query exist.');
}

if (!$offset || !is_int($offset)) {
    $offset = 0;
}

$response = ["status" => 1, "offset" => $offset, "max" => $max];

// Fetch airport data
$sql .= " ORDER BY name LIMIT 10 OFFSET $offset";
$sth = $dbh->prepare($sql);
if (!$sth->execute($filterParams)) {
    die(
        json_encode(
            ["status" => 0, "message" => "Operation $action failed."]
        )
    );
}

$rows = $sth->fetchAll(PDO::FETCH_ASSOC);

$isAdmin = in_array($uid, (array)$OF_ADMIN_UID);

foreach ($rows as &$row) {
    if ($tableName == "airports_dafif" || $tableName == "airports_oa") {
        $row["country"] = $row["code"];
    }
    if ($row["uid"] || $isAdmin) {
        if ($row["uid"] == $uid || $isAdmin) {
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
unset($row);
$response['airports'] = $rows;
print json_encode($response);

