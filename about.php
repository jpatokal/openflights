<?php
require_once "php/git.php";
require_once "./php/locale.php";
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <title><?php echo sprintf(_('OpenFlights: %s'), _('About')); ?></title>
    <meta name="version" content="<?php echo Git::getCurrentCommitID() ?? "unavailable"; ?>">
    <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
    <link rel="icon" type="image/png" href="/img/icon_favicon.png"/>
    <!--#include virtual="/html/analytics.html" -->
</head>

<body>
    <div id="mainContainer">
    <div id="sideBarContentWrapper">

    <div id="contentContainer">
    <div id="nonmap">

        <h1><?php echo _('About'); ?></h1>

        <p><?php
            echo _("OpenFlights is a tool that lets you map your flights around the world, search and filter them in all sorts of interesting ways, calculate statistics automatically, and share your flights and trips with friends and the entire world (if you wish). It's also the name of the open-source project to build the tool. Read more about it in the <a href=\"faq\">FAQ</a>.");
        ?></p>

        <p><?php
            echo _("Having completed its Alpha, Beta and Gamma test phases, OpenFlights is now in its Delta phase: all core functionality is implemented, but development on new features continues. If you'd like to be informed about major updates to the site, subscribe to <a href=\"https://blog.openflights.org\">our blog</a> or follow us on your favorite social networking/microblogging site:");
        ?></p>

        <p>
        <ul>
            <li><a href="https://twitter.com/openflights">Twitter</a></li>
            <li><a href="https://www.facebook.com/pages/OpenFlightsorg/35055397017">Facebook</a>
        </ul>
        </p>

        <h2><?php echo _('Contact'); ?></h2>

        <p><?php
            echo _('<a href="https://github.com/jpatokal/openflights/issues">Bug reports</a> and <a href="https://github.com/jpatokal/openflights/issues">feature requests</a> should be filed on <a href="https://github.com/jpatokal/openflights">GitHub</a>.');
        ?></p>

        <p><?php
            echo _('To get in touch with the team behind OpenFlights, send mail to <i>info at openflights dot org</i>.');
        ?></p>

        <h2><?php echo _('Site statistics'); ?></h2>
        <pre><small>
            <!--#include virtual="/data/top10.dat" -->
        </small></pre>

        <h2><?php echo _('Recent changes'); ?></h2>

        <p><?php
            echo _('See <a href="https://github.com/jpatokal/openflights/commits/master">recent commits on Github</a>.');
        ?></p>

        <h2><?php echo _('Credits'); ?></h2>

        <p><?php echo _('Open-source packages used to create this software include:'); ?>
            <a href="https://openlayers.org">OpenLayers</a>,
            <a href="https://github.com/kraaden/autocomplete"><tt>kraaden/autocomplete</tt></a>,
            <a href="https://github.com/pvorb/node-md5"><tt>pvorb/node-md5</tt></a>,
            <a href="https://yoast.com/articles/sortable-table/">Sortable Table</a>,
            <a href="http://www.garrett.nildram.co.uk/calendar/scw.htm">Simple Calendar Widget</a>,
            <a href="http://www.movable-type.co.uk/scripts/latlong.html">Movable Type Scripts (Great Circle)</a>,
            <a href="https://pajhome.org.uk/crypt/md5/">MD5</a>.
            <a href="https://github.com/rubobaquero/phpquery">phpQuery</a>.
        </p>

        <p><?php
            echo _("Map tiles come from <a href='https://carto.com/'>CartoDB</a>, <a href='http://maps.stamen.com/'>Stamen</a> and <a href='https://www.mapbox.com/'>Mapbox</a>.");
        ?></p>

        <p><?php echo _("Map data is provided by <a href='https://www.openstreetmap.org'>OSM</a>."); ?></p>

        <p><?php
            echo _("Special thanks to the OpenFlights beta testing team of <a href=\"https://flyertalk.com\">FlyerTalk</a> users <b>FCYTravis</b>, <b>marc</b>, <b>sbm12</b> and <b>trsqr</b>.");
        ?></p>

    </div>
    </div>

    <div id="sideBar">
        <!--#include virtual="/sidebar.php" -->
        <!--#include virtual="/html/ad-sidebar.html" -->
    </div>

    </div> <!-- end sidebarwrapper -->
    </div> <!-- end mainContainer -->

</body>
</html>
