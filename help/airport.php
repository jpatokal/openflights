<?php
require_once "../php/locale.php";
require_once "../php/db_pdo.php";
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <title><?php echo sprintf(_('OpenFlights Help: %s'), _('Airport')); ?></title>
        <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
        <link rel="stylesheet" href="/openflights.css" type="text/css">
        <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
        <link rel="icon" type="image/png" href="/img/icon_favicon.png"/>
        <script type="text/javascript" src="/js/functions.js"></script>
        <script type="text/javascript" src="/js/Gettext.min.js"></script>
    </head>

    <body>
        <div id="contexthelp">
            <span style="float: right"><label for="locale"><?php echo _("Language"); ?></label><br>
                <?php locale_pulldown($dbh, $locale); ?>
            </span>

            <h1><?php echo _('Context Help: Airports'); ?></h1>

            <?php echo _('<b>"From"</b> <img src="/img/icon_plane-src.png" height=17 width=17> is the airport your flight took off from.'); ?><p>

            <?php echo _('<b>"To"</b> <img src="/img/icon_plane-dst.png" height=17 width=17> is the airport your flight landed at.'); ?><p>

            <?php echo _('There are three ways to find airports:'); ?>
            <ol>
                <li><?php echo _('If you have <b>already flown to the airport</b>, you can click on its icon <img src="/img/icon_plane-17x17.png" height=17 width=17> in the map to select it.'); ?></li>
                <li><?php echo _("If not, enter the <b>city name</b> (e.g. \"Singapore\") or the <b>airport code</b> (IATA \"SIN\", ICAO \"WSSS\"), and a list of matching airports will appear in the drop-down box. If you're sure you know the airport code, just hit ENTER or TAB without waiting, and OpenFlights will match it for you."); ?></li>
                <li><?php echo _("Click on <img src=\"/img/icon_plane-src.png\" height=17 width=17> or <img src=\"/img/icon_plane-dst.png\" height=17 width=17> to pop up a <b>search dialog</b>, where you can find the airport by airport name, city name, country name or airport code, and even enter new ones if they're missing from the database."); ?></li>
            </ol>

            <?php echo _('To <b>reverse the flight direction</b>, click on the <b>Swap</b> button <img src="/img/swap-icon.png" height=17 width=17>.'); ?><br><p>

            <form>
                <input type="button" value="Close'); ?>" onClick="window.close()">
            </form>

        </div>
    </body>
</html>
