<?php

use Github\Client;

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

// https://github.com/jpatokal/openflights/issues/1447
$ref = $_SERVER['HTTP_REFERER'];
if (strpos($ref, 'com.cathaypacific.iJourneyLite') !== FALSE) {
    die(
        json_encode(
            ["status" => 0, "message" => "Cathay Pacific iJourneyLite is not licensed to use this API.  Please contact info@openflights.org to resolve this."]
        )
    );
}

if ($action == "RECORD") {
    if (!$uid || empty($uid)) {
        json_error("Your session has timed out, please log in again.");
    }
    $addAirport = !$apid || $apid == "";

    // Check for potential duplicates (unless admin)
    $duplicates = [];
    if (!in_array($uid, (array)$OF_ADMIN_UID)) {
        $filters = [];
        $filterParams = [];
        if (!$addAirport) {
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

        // Column order to match $newEdit below
        $sql = "SELECT apid, name, city, country, iata, icao, x, y, elevation, timezone,
            dst, country_code, uid, tz_id, type, source
            FROM airports
            WHERE " . implode(" OR ", $filters);
        $sth = $dbh->prepare($sql);
        $sth->execute($filterParams);
        foreach ($sth->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['uid'] != $uid || $row['apid'] != $apid) {
                $duplicates[] = print_r($row, true);
            }
        }
    }

    if (empty($duplicates)) {
        // If no duplicates, attempt to insert/update as appropriate
        if ($addAirport) {
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

        $sth = $dbh->prepare($sql);
        $res = $sth->execute($params);

        if ($addAirport) {
            if ($res) {
                json_success(["apid" => $dbh->lastInsertId(), "message" => "New airport successfully added."]);
            } else {
                json_error("Adding new airport failed.");
            }
        } elseif ($res && $sth->rowCount() === 1) {
            json_success(["apid" => $apid, "message" => "Airport successfully edited."]);
        } else {
            json_error("Editing airport failed.");
        }

        // Not needed, but most IDE's won't know json_success()/json_error() call die()
        exit;
    }

    if (
        // Check for empty strings, or default values as per config.php.sample
        in_array($GITHUB_USER, [ "", "YOUR_USERNAME"]) ||
        in_array($GITHUB_ACCESS_TOKEN, ["", "YOUR_TOKEN"]) ||
        $GITHUB_REPO == ''
    ) {
        json_error("Cannot submit edit request to GitHub; please check config!");
    }

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
            'dst' => $dst,
        ],
        true
    );

    // Various messages in this block are explicitly not translatable, as are designed to be posted in English to GitHub
    $suffixFormat = '(%s/%s)';
    $suffix = sprintf($suffixFormat, $iata, $icao);
    $add = sprintf(
        "Add airport %s %s",
        $airport,
        $suffix
    );

    $update = sprintf(
        "Update airport %s %s",
        $airport,
        $suffix
    );

    $title = $addAirport ? $add : $update;

    $body = $addAirport
        ? "New airport addition suggestion submitted by `$name`:"
        : "New airport edit suggestion submitted by `$name`:";

    $body .= <<<TXT

```
$newEdit
```

TXT;

    if (count($duplicates)) {
        $existingData = print_r(implode("\n", $duplicates), true);
        $body .= <<<TXT
Existing, potentially conflicting airport information:

```
$existingData
```

TXT;
    }

    $body .= <<<TXT
Cross-check this on other sites with compatible licensing:

TXT;

    if ($icao != '') {
        $body .= <<<TXT

**ICAO:**
- **OurAirports:** https://ourairports.com/airports/$icao/pilot-info.html
- **Wikipedia:** https://www.google.com/search?q=wikipedia%20$icao%20airport&btnI

TXT;
    }

    if ($iata != '') {
        $body .= <<<TXT

**IATA:**
- **OurAirports:** https://ourairports.com/search?q=$iata
- **Wikipedia:** https://www.google.com/search?q=wikipedia%20$iata%20airport&btnI

TXT;
    }

    $htmlAirport = htmlspecialchars($airport);
    $body .= <<<TXT

**Name:**
- **OurAirports:** https://ourairports.com/search?q=$htmlAirport
- **Wikipedia:** https://www.google.com/search?q=wikipedia%20$htmlAirport%20airport&btnI
TXT;


    if (isset($_POST["unittest"])) {
        echo $title . "\n\n" . $body;
        exit;
    }


    $searchStrings = [
        // Try the exact subject that would be used if we were to create a new issue.
        // This could be a title indicating an update or a request for addition.
        $title,
        // Try the other message (add if looking to update, or update if we're looking to add)
        $addAirport ? $update : $add,
        // Try the "($iata/$icao)" string in existing titles...
        $suffix,
        // Try just "($iata/)"
        sprintf($suffixFormat, $iata, ""),
        // Try just "(/$icao)"
        sprintf($suffixFormat, "", $icao),
    ];

    // Disabled for now; they likely cause false positives.
    // Then last but not least, try for the exact codes in any string
    // These possibly shouldn't be used, but we shall see...
    // Unfortunately still could result in a false positive...
    // Which was the original issue - https://github.com/jpatokal/openflights/issues/1318
    // if ($icao != "") {
    //    $searchStrings[] = $icao;
    // }
    // if ($iata != "") {
    //    $searchStrings[] = $iata;
    // }

    $github = new Client();
    try {
        $github->authenticate($GITHUB_ACCESS_TOKEN, null, Github\AuthMethod::ACCESS_TOKEN);

        // This would previously leave comments on closed issues...
        // This could be because the newer is a dupe and may have been closed...
        // Or it was declined...
        // Maybe nice to be able to cross-reference them one day...
        $issues = [];
        foreach ($searchStrings as $searchText) {
            // Search for issues with the airport label first, then try without
            // (such as old tasks that are not correctly labelled tasks...)
            foreach (['label:airport', ''] as $l) {
                // https://docs.github.com/en/rest/search/search?apiVersion=2022-11-28#search-issues-and-pull-requests
                $issues = $github->api('search')->issues(
                    trim(implode(
                        " ",
                        [
                            "repo:$GITHUB_USER/$GITHUB_REPO",
                            "is:issue",
                            "is:open",
                            "in:title \"$searchText\"",
                            $l
                        ]
                    ))
                );

                if (count($issues['items']) > 0) {
                    // Found something, probably should use it as the closest possible match...
                    break;
                }
            }
        }

        // Duplicate issue merging would be nice.
        // But this will only be the results for one specific string in the task title,
        // so doesn't necessarily help much until ...
        // We could also print the list of other potentially related issue numbers too into the comment,
        // which may include closed tasks (which could be "previous updates" etc.).
        // I think I'm overthinking this now.
        // Though that could be useful, for sure.
        if (count($issues['items']) > 0) {
            $issueNumber = $issues['items'][0]['number'];

            // Existing issue, add comment
            // https://docs.github.com/en/rest/issues/comments?apiVersion=2022-11-28#create-an-issue-comment
            $result = $github->api('issue')->comments()->create(
                $GITHUB_USER,
                $GITHUB_REPO,
                $issueNumber,
                [
                    'body' => sprintf("**Title:** %s\n\n%s", $title, $body)
                ]
            );
            $hasLabel = false;
            foreach ($issues['items'][0]['labels'] as $l) {
                if ($l['name'] === 'airport') {
                    $hasLabel = true;
                    break;
                }
            }
            if (!$hasLabel) {
                $github->api('issue')->labels()->add(
                    $GITHUB_USER,
                    $GITHUB_REPO,
                    $issueNumber,
                    'airport'
                );
            }

            // Potentially need to pass things through as parameters
            $message = $addAirport
                ? _("Comment left on a GitHub Issue %s to request the addition of a new Airport; %s")
                : _("Comment left on a GitHub Issue %s to request an edit to an existing Airport; %s");
        } else {
            // New issue
            // https://docs.github.com/en/rest/issues/issues?apiVersion=2022-11-28#create-an-issue
            $result = $github->api('issue')->create(
                $GITHUB_USER,
                $GITHUB_REPO,
                ['title' => $title, 'body' => $body, 'labels' => ['airport']]
            );
            $issueNumber = $result['number'];
            $message = $addAirport
                ? _("GitHub Issue %s created to request the addition of a new Airport; %s")
                : _("GitHub Issue %s created to request an edit to an existing Airport; %s");
        }

        // TODO: Make a hyperlink when not using the alert() box.
        json_success(["apid" => $apid, "message" => sprintf($message, $issueNumber, $result['html_url'])]);
    } catch (GitHub\Exception\RuntimeException $ex) {
        // $ex->code === 401 is Unauthorized

        json_error(
            _("Could not submit edit for review, please contact <a href='/about'>support</a>.")
            // Probably not going to be localized...
            . "\n\n" . $ex->getMessage()
        );
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

foreach ($rows as $row) {
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
    $response['airports'][] = $row;
}
print json_encode($response);
