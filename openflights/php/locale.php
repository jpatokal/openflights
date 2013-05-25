<?php
session_start();
include 'config.php';

if($OF_USE_LOCALES) {
  if (isSet($_GET["lang"])) {
    $locale = $_GET["lang"];
    $_SESSION["locale"] = $locale;
  } else {
    $locale = $_SESSION["locale"];
    if(!$locale or empty($locale)) {
      $locale = "en_US";
    }
  }
  $locale .= ".utf8";
  setlocale(LC_ALL, $locale);

  if(substr_count($_SERVER['SCRIPT_NAME'], '/') == 1) {
    $path = ".";
  } else {
    $path = "..";
  }
  bindtextdomain("messages", $path . "/locale");
  textdomain("messages");
  header("Content-type: text/html; charset=utf-8");
} else {
  $locale = "en_US.utf8";

  function _($string) {
    return $string;
  }
}

//
// Generate select box (pulldown) with all known locales
// Box ID is "locale" and it triggers JS changeLocale() when selection is changed
// $db -- OpenFlights DB
// $locale -- currently selected locale
//
function locale_pulldown($db, $locale) {
  global $OF_USE_LOCALES;
  echo "<select id='locale' onChange='JavaScript:changeLocale()'>\n";
  if($OF_USE_LOCALES) {
    $sql = "SELECT * FROM locales ORDER BY name ASC";
    $result = mysql_query($sql, $db);
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $selected = ($row["locale"] . ".utf8" == $locale ? "SELECTED" : "");
      printf("<option value='%s' %s>%s (%s)</option>\n", $row["locale"], $selected, $row["name"], substr($row["locale"], 0, 2));
    }
  } else {
    echo "<option value='en_US' SELECTED>English</option>\n";
  }
  echo "</select>";
}
?>
