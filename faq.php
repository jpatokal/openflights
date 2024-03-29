<?php
require_once "./php/locale.php";
require_once "./php/db_pdo.php";
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title><?php echo sprintf(_('OpenFlights: %s'), _('FAQ')); ?></title>
    <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
    <link rel="icon" type="image/png" href="/img/icon_favicon.png"/>
    <script type="text/javascript" src="/js/Gettext.min.js"></script>
    <script type="text/javascript" src="/js/functions.js"></script>
    <!--#include virtual="/html/analytics.html" -->
  </head>

  <body>
    <div id="mainContainer">
      <div id="contexthelp">
          <span style="float: right"><label for="locale"><?php echo _("Language"); ?></label><br>
            <?php locale_pulldown($dbh, $locale); ?>
          </span>
        </div>
      <div id="sideBarContentWrapper">

    <div id="contentContainer">
      <div id="nonmap">

        <h1><?php echo _('Frequently Asked Questions (FAQ)'); ?></h1>

        <h2><?php echo _('General'); ?></h2>

        <h4><?php echo _('What is OpenFlights?'); ?></h4>

        <p><?php echo _("In a nutshell, it's a tool that lets you <b>map</b> your flights around the world, <b>search and filter</b> them in all sorts of interesting ways, calculate <b>statistics</b> automatically, and <b>share</b> the resulting maps with friends and the world. You can also check <b>airport route maps</b> for nearly every airport in the world and find out <b>what airlines fly where</b>."); ?></p>

        <table>
            <tr>
                <td style="padding: 10px">
                    <a href="https://openflights.org/blog/2009/06/26/route-mapping-the-world/">
                        <img src="/demo/openflights-route-sample-minitn.png" width="256" height="160"><br>
                        <?php echo _('Airport route map'); ?></a>
                </td>
                <td style="padding: 10px">
                    <a href="https://openflights.org/blog/2009/07/15/airline-route-maps-launched-metric-distances-available/">
                        <img src="/demo/openflights-airline-route-minitn.png" width="256" height="160"><br>
                        <?php echo _('Airline route map'); ?></a>
                </td>
                <td style="padding: 10px">
                    <a href="https://openflights.org/blog/2009/02/23/give-your-flights-a-spin-in-3d-on-google-earth/"><img src="/demo/openflights-googleearth-minitn.png" width="256" height="192"><br>
                        <?php echo _('Google Earth visualization'); ?></a>
                </td>
            </tr>
        </table>

        <h4><?php echo _('What can I do with OpenFlights?'); ?></h4>

        <p><?php echo _('Quite a few things:'); ?></p>
        <ul>
          <li><?php echo _("Track exactly how far you've flown and how much time you've spent sitting on a plane. (Quite a few of our members have been to the Moon and back, but nobody has yet reached Mars.)"); ?></li>
          <li><?php echo _("See at a glance where you've been and where you're going."); ?></li>
          <li><?php echo _('Rapidly search your flight history: now when did I go to the Bahamas, and on what airline?'); ?></li>
          <li><?php echo _('Share your flights and trips with friends.'); ?></li>
          <li><?php echo _('Check where you can fly to from any airport, or any pair of airports. (<a href="https://openflights.org/blog/2009/06/26/route-mapping-the-world/">Learn more</a>)'); ?></li>
          <li><?php echo _('Check where you can fly to with any airline. (<a href="https://openflights.org/blog/2009/07/15/airline-route-maps-launched-metric-distances-available/">Learn more</a>)'); ?></li>
          <li><?php echo _('Coordinate flights for conferences and other events. (<a href="https://openflights.org/blog/2009/01/10/interesting-uses-of-openflights/">Learn more</a>)'); ?></li>
          <li><?php echo _('View your flights in gorgeous 3D with Google Earth and other visualization apps. (<a href="https://openflights.org/blog/2009/02/23/give-your-flights-a-spin-in-3d-on-google-earth/">Learn more</a>)'); ?></li>
        </ul>

        <h4><?php echo _('Why is OpenFlights better than other similar services?'); ?></h4>

        <p><?php echo _("Well, as far as we know, there actually isn't anything <i>quite</i> like OpenFlights out there, but here are some things that separate us from the putative competition:"); ?></p>
        <ul>
          <li><?php echo _('OpenFlights has a <b>dynamic map</b>. You can pan, zoom, select, scroll and explore all you like!'); ?></li>
          <li><?php  echo _("OpenFlights is <b>user-friendly and efficient</b>. Everything's on the same page!"); ?></li>
          <li><?php  echo _('OpenFlights <b>makes searching really easy</b>. Point and click!'); ?></li>
          <li><?php echo _('OpenFlights has a <b>powerful filter</b>. Three clicks, and your map will show only Singapore Airlines flights in business class in 2007.'); ?></li>
          <li><?php echo _("OpenFlights <b>works in realtime</b>. Make any change, and you'll see it right then and there."); ?></li>
          <li><?php echo _('OpenFlights supports <b>"trips"</b> (<a href="/help/trip.php">read more</a>). You can join up any flights together into a trip, which you can then display on its own page and even share with friends.'); ?></li>
          <li><?php echo _("OpenFlights is <b>free in spirit</b>. We don't try to lock you in: it's easy to import your data <i>and</i> export copies for safekeeping."); ?></li>
          <li><?php echo _('OpenFlights is <b>free software</b>. All our source code is licensed under the <a href="https://www.gnu.org/licenses/agpl-3.0.html">GNU Affero General Public License</a> and can be downloaded from <a href="https://github.com/jpatokal/openflights/">GitHub</a>, so you can set up your own copy or just use the bits you like. <a href="data.php">Airport, airline and route data</a> is freely available as well.'); ?></li>
          <li><?php echo _('OpenFlights <b>can be used anywhere</b>. No need to install any programs, synchronize data or take care of backups, just point your browser to the site.'); ?></li>
        </ul>

        <h4><?php echo _('What browsers do you support?'); ?></h4>
        <p><?php echo _('Developed on Firefox and Chrome, seems to work for most (but not all) people on Internet Explorer and Safari as well. Unfortunately the site does <i>not</i> work well on tablets due to screen size limitations. If it works or breaks on anything else, please <a href="/about">let us know</a>, and please do include your exact browser version, operating system and screen size.'); ?></p>

        <h4><?php echo _('How much does it cost?'); ?></h4>
        <p><?php echo _("Using the website costs you <i>absolutely nothing</i>. However, our meager advertising revenue does not currently suffice even to pay for our bandwidth bills, so if you like the site, you're warmly encouraged to <a href=\"/donate\">donate and help keep the site running</a>. Donors receive \"Elite\" status with perks like no ads, password-protected private profiles, elite-level support, previews of upcoming features and more. See <a href=\"donate\">Donate</a> for more."); ?></p>

        <h4><?php echo _('Are you sure this thing will scale?'); ?></h4>
        <p><?php echo _('Yes! The heavy lifting of drawing the maps is handled by your browser, not any central server. The database is built to scale up and already has over 1,500,000 flights and routes loaded.'); ?></p>

        <h4><?php echo _('How do I start punching in my own flights?'); ?></h4>
        <p>J<?php echo _('Just click on <a href="/html/settings?new=yes">Sign up</a> and pick a username. Your account will be created instantly, no e-mail confirmation or other tiresome hassles needed.'); ?></p>

        <h2><?php echo _('Features and bugs'); ?></h2>

        <h4><?php echo _('Why do half the airports disappear when I scroll around from the Americas to Asia or vice versa?'); ?></h4>
        <p>T<?php echo _("This is a <a href=\"https://github.com/jpatokal/openflights/issues/2\">bug/missing feature</a> in the mapping system we're using. Sorry."); ?></p>

        <h4><?php echo _("Why aren't the flight paths smoothly curved, especially up near the poles"); ?></h4>
        <p><?php echo _('That would be hard to draw fast, so we cheat and chop them up, one segment per every 500 mi. Flights under 1000 mi are shown as straight lines.'); ?></p>
        <p><?php echo _('The distances for the statistics, though, are calculated correctly as <a href="https://en.wikipedia.org/wiki/Great-circle_distance">great circle distances</a>.'); ?></p>

        <h4><?php echo _('Are those flight time estimates accurate? Can I enter my own?'); ?></h4>

        <p><?php echo _('The site currently uses a really fancy formula to calculate them: "30 min plus 1 hour per every 500 miles". This seems to be a surprisingly good approximation for commercial flights (anything from 100 to 10000 mi).'); ?></p>
        <p><?php echo _('And yes, you can enter your own flight times, either as arrival and departure times, in which case OpenFlights will figure out the duration taking into account timezones and DST, or directly into the duration box. See <a target="_blank" href="/help/time.php">Help: Time</a> for details.'); ?></p>

        <h4><?php echo _('Why is my favorite airport/heliport/patch of grass missing?'); ?></h4>
        <p><?php echo _("If you can't find your airport right away, click on the <img src=\"/img/icon_plane-src.png\" height=17 width=17> or <img src=\"/img/icon_plane-dst.png\" height=17 width=17> icons to launch the advanced search. If you're looking for a decommissioned airport, you may need to use the ICAO code instead of the IATA code. For example, ATH points to Athens-Eleftherios Venizelos (LGAV), but you can still find Athens-Ellinikon as LGAT. If the main OpenFlights DB doesn't have it, try a search in the OurAirports database with the airport's name or ICAO code. If you find it in OurAirports, click <input type=\"button\" value=\"Load\" align=\"middle\"> to load its data, double-check that it all looks correct, and then <input type=\"button\" value=\"Save as new\" align=\"middle\"> to copy it over."); ?></p>
        <p><?php echo _("If you still can't find it, you can click on <input type=\"button\" value=\"Add new airport\" align=\"middle\"> while creating a new flight to add your own airport. You can also enter heliports, landing strips and other informal airports which do not have IATA or ICAO codes, just leave the IATA/ICAO fields blank and click \"OK\" when warned about it."); ?></p>

        <h4><?php echo _('Why is the route map missing the Rutungu Airlines flight from Mambo-Jambo to Bingo-Bongo?'); ?></h4>
        <p><?php echo _('Our route data is provided by <a href="http://arm.64hosts.com/">Airline Route Mapper</a>, please let them know your suggestions and corrections.'); ?></p>

        <h4><?php echo _('Your calendar only goes back to 1970, how do I enter an earlier flight?'); ?></h4>
        <p><?php echo _('Just enter the date manually instead of using the calendar widget.'); ?></p>

        <h4><?php echo _('How do I delete an airport/airline/trip that I no longer need?'); ?></h4>
        <p><?php echo _('For airports and airlines, just remove all flights using it, and it will stop appearing.'); ?></p>
        <p><?php echo _('To delete a trip, load it in the Trip editor and select Delete. Any flights still in that trip will be kept, they will just set as tripless.'); ?></p>

        <h4><?php echo _('How do I share my flight map with friends?'); ?></h4>
        <p><?php echo _('Point them to <b>https://openflights.org/user/<i>yourname</i></b>. You can also click on <input type="button" value="Settings" align="middle"> when logged in to find your address; this is particularly useful if your name has spaces or unusual characters.'); ?></p>
        <p><?php echo _('Example: <a href="https://openflights.org/user/jpatokal">https://openflights.org/user/jpatokal</a>'); ?></p>
        <p><?php echo _('If you have a blog or other website, or post to bulletin boards/forums, you can add an OpenFlights banner like this:'); ?></p>
        <p><?php echo _('<a href="https://openflights.org/user/jpatokal" target="_blank"><img src="/banner/jpatokal.png" width=400 height=70></a>'); ?></p>
        <p><?php echo _('To get pre-generated banner code for blogs (HTML format) and for bulletin boards (phpBB), just log into your account and click on <input type="button" value="Settings" align="middle">.'); ?></p>

        <h4><?php echo _('Can my friends also edit my flights?'); ?></h4>
        <p><?php echo _("Yes, <i>if</i> you give them your password. You'd better trust them though, since they can now change all your settings and even delete your flights. While the system does not <i>officially</i> support being logged in as the same user from many places at once, in practice this is <i>unlikely</i> to cause problems."); ?></p>

        <h4><?php echo _('Something went wrong when I imported from FlightMemory!'); ?></h4>
        <p><?php echo _('This most often happens with airlines, since FlightMemory and OpenFlights do not render all company names in the same way. OpenFlights also uses current airline data, not historical, so e.g. SN123 will be mapped to "Brussels Airlines", not "Sabena". To keep the old airline names exactly the way you had them on FlightMemory, check <b>Keep historical airline names?</b> when importing.'); ?></p>
        <p><?php echo _('The other common source of headaches is unofficial airports like heliports and landing strips, which do not have IATA/ICAO codes. These have to be entered manually into OpenFlights using "Add new airport" <i>before</i> you can import. Note that the matching is done based on the <i>first word of the airport name</i>, so make sure the one in your data matches the one you enter exactly.'); ?></p>
        <p><?php echo _("If anything else goes wrong during importing, or if a current airline is being mapped wrong, it's a bug and we'd like to know about it. <a href=\"/about\">Drop us a line</a>, tell us exactly what went wrong, and (this is important) <i>give the \"Tmpfile\" value from the top of the import page</i>, so we can replicate it."); ?></p>

        <h4><?php echo _('You really need to implement cool feature X, and fix terrible bug Y, and do it <i>right now</i>!'); ?></h4>
        <p><?php echo _("Please check the <a href=\"https://github.com/jpatokal/openflights/issues\">GitHub issue tracker</a> to see if they're already reported, and add them if not. But remember, this is open source, so the best way to get anything done is to do it yourself! Bug reports from people who have <a href=\"/donate\">donated to the site</a> also get priority."); ?></p>

        <h4><?php echo _("You said feature X was implemented/bug Y was fixed yesterday, but I don't see it!"); ?></h4>
        <p><?php echo _('Your browser probably has an old version still in memory. Please hit Control-F5 to force it to reload the entire page.'); ?></p>

        <h2><?php echo _('Technical jibber-jabber'); ?></h2>

        <h4><?php echo _('Did you really code that map all by yourself?'); ?></h4>
        <p><?php echo _('No sir, the credit goes to <a href="https://openlayers.org">OpenLayers</a>. We just added the fluff on top.'); ?></p>

        <h4><?php echo _('And the rest of it?'); ?></h4>
        <p><?php echo _('JavaScript frontend chock full of AJAX-y goodness served off Nginx running on Ubuntu Linux, plus PHP scripts on the backend shifting through reams of data pulled from MySQL.'); ?></p>

        <h4><?php echo _('Can I get a copy of your airport, airline or route data?'); ?></h4>
        <p><?php echo _('Yes! See <a href="/data">Airport, airline and route data</a> for free downloads and more information.'); ?></p>

        <h4><?php echo _('I want to export to/import from OpenFlights format. Where is your CSV specification?'); ?></h4>
        <p><?php echo _("Right here: <i><a href=\"/help/csv.php\">Help: CSV</a></i>. It's pretty straightforward, but <a href=\"/about\">let us know</a> if something is not working the way you expect."); ?></p>
        <p><?php echo _('One common problem: OpenFlights exports special characters in UTF-8 format, which is not automatically recognized by some versions of Excel. Use the <i>Text Import Wizard</i> and specify "Unicode (UTF-8)", and they will import nicely.'); ?></p>

        <h4><?php echo _('Can I use your images in my book/magazine article/annual report?'); ?></h4>
        <p><?php echo _('Yes, but commercial use requires a <a href="/data#license">license</a>, please <a href="/about">contact us</a> for details.'); ?></p>

        <h4><?php echo _('Can I have an anonymized dump of flight data from OpenFlights or a customized installation for my airline/airport/website/other company?'); ?></h4>
        <p><?php echo _("We'd be delighted to work out a deal for consulting work, just <a href=\"/about\">drop us a line</a>."); ?></p>

        <br><br>
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
