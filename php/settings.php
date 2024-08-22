<?php

include_once 'locale.php';
include_once 'db_pdo.php';

$type = $_POST["type"];
$name = $_POST["name"] ?? null;
$pw = $_POST["pw"];
$oldpw = $_POST["oldpw"] ?? null;
$oldlpw = $_POST["oldlpw"] ?? null;
$email = $_POST["email"];
$privacy = $_POST["privacy"];
$editor = $_POST["editor"];
$units = $_POST["units"];
$guestpw = $_POST["guestpw"] ?? null;
$startpane = $_POST["startpane"] ?? null;
$locale = $_POST["locale"]; // override any value in URL/session

// 0 error
// 1 new
// 2 edited
// 10 reset

// Create new user
switch ($type) {
    case "NEW":
        $sth = $dbh->prepare("SELECT * FROM users WHERE name = ?");
        $sth->execute([$name]);
        if ($sth->fetch()) {
            die("0;" . _("Sorry, that name is already taken, please try another."));
        }
        break;

    case "EDIT":
    case "RESET":
        $uid = $_SESSION["uid"];
        $name = $_SESSION["name"];
        if (!$uid || empty($uid)) {
            die("0;" . _("Your session has timed out, please log in again."));
        }

        if ($type == "RESET") {
            $sth = $dbh->prepare("DELETE FROM flights WHERE uid = ?");
            $sth->execute([$uid]);
            printf("10;" . _("Account reset, %s flights deleted."), $sth->rowCount());
            exit;
        }

        // EDIT
        if ($oldpw && $oldpw != "") {
            $sth = $dbh->prepare("SELECT * FROM users WHERE name = ? AND (password = ? OR password = ?)");
            $sth->execute([$name, $oldpw, $oldlpw]);
            if (!$sth->fetch()) {
                die("0;" . _("Sorry, current password is not correct."));
            }
        }
        break;

    default:
        die("0;" . sprintf(_("Unknown action %s"), htmlspecialchars($type)));
}

// Note: Password is actually an MD5 hash of pw and username
if ($type == "NEW") {
    $sth = $dbh->prepare(
        "INSERT INTO users (name, password, email, public, editor, locale, units) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $success = $sth->execute([$name, $pw, $email, $privacy, $editor, $locale, $units]);
} else {
    if (!$guestpw) {
        $guestpw = null;
    }
    $params = compact('email', 'privacy', 'editor', 'guestpw', 'startpane', 'locale', 'units', 'uid');
    // Only change password if old password matched and a new one was given
    if ($oldpw && $oldpw != "" && $pw && $pw != "") {
        $pwsql = "password = :pw, ";
        $params['pw'] = $pw;
    } else {
        $pwsql = "";
    }
    $sth = $dbh->prepare("
    UPDATE users
    SET $pwsql
        email = :email, public = :privacy, editor = :editor, guestpw = :guestpw,
        startpane = :startpane, locale = :locale, units = :units
    WHERE uid = :uid
");
    $success = $sth->execute($params);
}
if (!$success) {
    die("0;" . sprintf(_("Operation on user %s failed."), $name));
}

// In all cases, change locale and units to user selection
$_SESSION['locale'] = $locale;
$_SESSION['units'] = $units;

if ($type == "NEW") {
    printf("1;" . _("Successfully signed up, now logging in..."));

    // Log in the user
    $uid = $dbh->lastInsertId();
    $_SESSION['uid'] = $uid;
    $_SESSION['name'] = $name;
    $_SESSION['editor'] = $editor;
    $_SESSION['elite'] = '';
    $_SESSION['units'] = $units;
} else {
    printf("2;" . _("Settings changed successfully, returning..."));
}
