<?php
session_start();

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
bindtextdomain("messages", "../locale");
textdomain("messages");

//
// Generate select box (pulldown) with all known locales
// Box ID is "locale" and it triggers JS changeLocale() when selection is changed
// $db -- OpenFlights DB
// $locale -- currently selected locale
//
function locale_pulldown($db, $locale) {
  echo "<select id='locale' onChange='JavaScript:changeLocale()'";
  $sql = "SELECT * FROM locales ORDER BY name ASC";
  $result = mysql_query($sql, $db);
  while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $selected = ($row["locale"] . ".utf8" == $locale ? "SELECTED" : "");
    printf("<option value='%s' %s>%s (%s)</option>\n", $row["locale"], $selected, $row["name"], $row["locale"]);
  }
  echo "</select>";
}

?>
