<?php
require_once "../php/locale.php";
require_once "../php/db_pdo.php";
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <title><?php echo sprintf(_('OpenFlights Help: %s'), _('Airline')); ?></title>
        <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
        <link rel="stylesheet" href="/openflights.css" type="text/css">
        <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
        <link rel="icon" type="image/png" href="/img/icon_favicon.png"/>
        <script type="text/javascript" src="/js/Gettext.min.js"></script>
        <script type="text/javascript" src="/js/functions.js"></script>
    </head>

    <body>
        <div id="contexthelp">
            <span style="float: right"><?php echo _("Language"); ?><br>
                <?php locale_pulldown($dbh, $locale); ?>
            </span>

            <h1><?php echo _('Context Help: Airline'); ?></h1>
            <?php echo _('<b>"Airline"</b> is the airline you used for this flight.'); ?><p><br>
            <?php echo _('There are three ways to find airlines:'); ?>
            <ol>
                <li><?php echo _("Enter the <b>airline name</b> or a part of it (e.g. \"Cathay Pacific\", \"Cathay\", \"Cath\") or the <b>airline code</b> (IATA \"CX\", ICAO \"CPA\") and wait for a second. A list of matching airlines will appear in the drop-down box. Alternatively, if you're sure you know the code, just immediately hit TAB or ENTER, and then OpenFlights will match it for you."); ?></li>
                <li><?php echo _('Enter a <b>flight number</b> like "CX123" into the <b>Flight #</b> box, and OpenFlights will automatically match "CX" to Cathay Pacific and fill in the airline for you.'); ?></li>
                <li><?php echo _("Click on <img src=\"/img/icon_airline.png\" height=17 width=17> to pop up a <b>search dialog</b>, where you can find the airline by name, country name or airline code, and even enter new airlines if they're missing from the database."); ?></li>
            </ol>

            <form>
                <input type="button" value="<?php echo _('Close'); ?>" onClick="window.close()">
            </form>

        </div>
    </body>
</html>
