<?php
require_once "../php/locale.php";
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <title>OpenFlights Help: Trips</title>
        <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
        <link rel="stylesheet" href="/openflights.css" type="text/css">
        <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
    </head>

    <body>
        <div id="contexthelp">

            <h1><?php echo _('Context Help: Trips'); ?></h1>

            <p><?php echo _('<b>Trips</b> are an OpenFlights feature for grouping together multiple flights across many airlines. Trips can be filtered and viewed separately in the statistics, and you can also share trips with others. You can even link your website, blog, FlyerTalk trip report etc. to your OpenFlights trip.'); ?></p>

            <p><?php echo _('<i>Example</i>: You went on a trip all around Europe, and now you want to share a map that shows <i>only</i> your European flights with your friends.'); ?></p>
            <ol>
                <li><?php echo _('Select <b>New flight</b> and switch to <b>Detailed</b> mode.'); ?></li>
                <li><?php echo _('Enter the first flight and click on <img src="/img/icon_add.png" height=17 width=17> next to <b>Trip</b> to create your new trip.'); ?></li>
                <li><?php echo _('Your new trip now appears in the <b>Trip</b> pull down. Add the flight.'); ?></li>
                <li><?php echo _('Add your other flights. As long as your new trip is selected in the pull down, they will be added to the trip.'); ?></li>
                <li><?php echo _("That's it! You can now choose your trip in the <b>Filter</b>, or click on <img src=\"/img/icon_edit.png\" height=17 width=17> to see the trip address for sharing with others."); ?></li>
            </ol>

            <form>
                <input type="button" value="<?php echo _('Close'); ?>" onClick="window.close()">
            </form>

        </div>
    </body>
</html>
