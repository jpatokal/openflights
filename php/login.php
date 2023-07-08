<?php

include_once 'locale.php';
include_once 'db_pdo.php';

$name = $_POST["name"];
// pw is hashed from lowercased username, legacypw is not
$pw = $_POST["pw"];
$legacypw = $_POST["lpw"];
$challenge = $_POST["challenge"];

if ($challenge && $challenge != $_SESSION["challenge"]) {
    $row = array("status" => 0, "message" => "Session expired. Please <a href='/'>refresh</a> and try again.");
    die(json_encode($row));
} else {
    $challenge = $_SESSION["challenge"];
}

// Log in user
if ($name) {
    // CHAP: Use random challenge key in addition to password
    // user_pw == MD5(challenge, db_pw)
    $sth = $dbh->prepare("
    SELECT uid, name, email, editor, elite, units, locale
    FROM users
    WHERE
      name = :name
      AND (
        :pw = MD5(CONCAT(:challenge, password))
        OR :legacypw = MD5(CONCAT(:challenge, password))
      )
  ");
    $sth->execute(compact('name', 'challenge', 'pw', 'legacypw'));
    $row = $sth->fetch();
    if ($row) {
        $uid = $row["uid"];
        $_SESSION['uid'] = $uid;
        $_SESSION['name'] = $row["name"];
        $_SESSION['email'] = $row["email"];
        $_SESSION['editor'] = $row["editor"];
        $_SESSION['elite'] = $row["elite"];
        $_SESSION['units'] = $row["units"];
        if ($row["locale"] != "en_US" && $_SESSION['locale'] != $row["locale"]) {
            $row['status'] = 2; // force reload, so UI is changed into user's language
        } else {
            $row['status'] = 1;
        }
        $_SESSION['locale'] = $row["locale"];
    } else {
        $message = sprintf(
            _("Login failed. <%s>Create account</a> or <%s>reset password</a>?"),
            "a href='/html/settings?new=yes'",
            "a href='#' onclick='JavaScript:help(\"resetpw\")'"
        );
        $row = array("status" => 0, "message" => $message);
    }
    print json_encode($row);
}
