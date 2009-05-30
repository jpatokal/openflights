<?php
session_start();

$locale = $_SESSION["locale"];
if(!$locale or empty($locale)) {
  if (isSet($_GET["lang"]))
    $locale = $_GET["lang"];
  else
    $locale = "en_US";
}
$locale .= ".utf8";
setlocale(LC_ALL, $locale);
bindtextdomain("messages", "../locale");
textdomain("messages");
?>
