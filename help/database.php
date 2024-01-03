<?php
require_once "../php/locale.php";
require_once "../php/db_pdo.php";
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <title><?php echo sprintf(_('OpenFlights Help: %s'), _('Database')); ?></title>
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
            <h1><?php echo _('Context Help: Databases'); ?></h1>
            <p><?php echo _('OpenFlights airport searches can use three databases.'); ?></p>

            <ul>
                <li><?php echo _('<b>OpenFlights</b> is the default database, created by merging GAD and DAFIF and manually correcting countless errors. <i>Only airports found in OpenFlights searches can be used to enter your flights.</i>'); ?></li>
                <li><?php echo _('<b>OurAirports</b> is a public domain database from <a href="https://ourairports.com/data/">OurAirports</a> project, containing some 40,000 airports, airstrips and heliports around the world. While very complete, timezones are not included, and the ICAO code field can be ambiguous. All OA airports marked as having scheduled passenger service have already been imported into OpenFlights.'); ?></li>
                <li><?php echo _('<b>DAFIF</b> is the October 2006 cycle of the Digital Aeronautical Flight Information File managed by the United States <a href="https://www.nga.mil/">National Geospatial-Intelligence Agency</a>. The accuracy of DAFIF data is very high, but it does not include city names or IATA codes. (However, it does include FAA codes for US airports.) Unfortunately, DAFIF data after October 2006 is no longer available to the public.'); ?></li>
            </ul>

            <p><?php echo _('Data from OpenAirports and GAD can be preloaded into the search form for easy adding to the OpenFlights DB.'); ?></p>
            <p><?php echo sprintf(
                _('Want a copy of this data? See %s'),
                sprintf('<a href="/data.php">%s</a>.', sprintf(_('OpenFlights: %s'), _('Airport and airline data')))
            ); ?></p>

            <form>
                <input type="button" value="<?php echo _('Close'); ?>" onClick="window.close()">
            </form>

        </div>
    </body>
</html>
