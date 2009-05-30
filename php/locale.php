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
?>
