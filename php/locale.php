<?php

session_set_cookie_params(['samesite' => 'Strict']);
session_start();

include_once 'config.php';
include_once 'db_pdo.php';

if ($OF_USE_LOCALES) {
    $locale = null;
    if (isset($_GET["lang"])) {
        $lang = $_GET["lang"];

        // We're accepting a random lang parameter... We should probably check it actually exists!
        if (locale_exists($dbh, $lang)) {
            $locale = $lang;
        }
    } elseif (($_SESSION["locale"] ?? null) !== null) {
        // If it's already in the session and not null... it's probably ok...
        $locale = $_SESSION["locale"];
    }

    if ($locale === null) {
        // https://github.com/jpatokal/openflights/issues/1322
        // If we've not already got a locale set (from $_SESSION or $_GET),
        // see if we can use the HTTP ACCEPT_LANGUAGE header value
        // TODO: We don't attempt any of the fallbacks...
        $acceptLang = Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);

        // We know we can do en_US, so don't do a DB lookup
        if ($acceptLang === "en_US" || locale_exists($dbh, $acceptLang)) {
            $locale = $acceptLang;
        }
    }

    // Push whatever locale we've worked out back into the session
    $_SESSION["locale"] = $locale;

    $locale .= ".utf8";
    setlocale(LC_ALL, $locale);

    if (substr_count($_SERVER['SCRIPT_NAME'], '/') == 1) {
        $path = ".";
    } else {
        $path = "..";
    }
    bindtextdomain("messages", $path . "/locale");
    textdomain("messages");
} else {
    $locale = "en_US.utf8";

    // This probably isn't necessary, and the function should just be removed.
    // But in modern packaged PHP (Debian and Ubuntu, at least), php-common includes
    // gettext (though, potentially not enabled by default).
    // Composer checks PHP extensions install time, but not run time.
    // Composer requires ext-gettext to be installed anyway, but of course,
    // it could be technically ignored by `--ignore-platform-reqs`...
    if (!function_exists('_')) {
        function _($string) {
            return $string;
        }
    }
}
header("Content-type: text/html; charset=utf-8");

/**
 * Check if we support a locale...
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $locale string locale string
 * @return bool
 */
function locale_exists($dbh, $locale) {
    $sth = $dbh->prepare("SELECT * FROM locales WHERE locale = ?");
    $sth->execute([$locale]);
    return $sth->rowCount() === 1;
}

/**
 * Generate select box (pulldown) with all known locales
 * Box ID is "locale" and it triggers JS changeLocale() when selection is changed
 *
 * @param $dbh PDO OpenFlights DB handler
 * @param $locale string Currently selected locale
 */
function locale_pulldown($dbh, $locale) {
    global $OF_USE_LOCALES;
    echo "<select id='locale' onChange='JavaScript:changeLocale()'>\n";
    if ($OF_USE_LOCALES) {
        $sql = "SELECT * FROM locales ORDER BY name ASC";
        foreach ($dbh->query($sql) as $row) {
            $selected = $row["locale"] . ".utf8" == $locale ? "SELECTED" : "";
            printf(
                "<option value='%s' %s>%s (%s)</option>\n",
                $row["locale"],
                $selected,
                $row["name"],
                substr($row["locale"], 0, 2)
            );
        }
    } else {
        echo "<option value='en_US' SELECTED>English</option>\n";
    }
    echo "</select>";
}
