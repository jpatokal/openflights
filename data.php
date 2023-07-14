<?php
require_once "./php/locale.php";

function payPalOutput($short = true) {
?>
    <table>
        <tr>
            <td><p>
            <?php
            $msgs = [
                $short
                    ? _('Creating and maintaining this database has required and continues to require an <i>immense amount</i> of work.')
                    : _('Creating and maintaining this database has required and continues to require an <i>immense amount</i> of work, which is why it would cost you <i>over one thousand dollars</i> to buy it from a commercial supplier.'),
                _('We need your support to keep this database up-to-date: just click on the PayPal link to the right (Visa, MasterCard, American Express and Discover also accepted).'),
                _('We suggest <b>US$50</b>, but any amount at all is welcome, and you may use the data for free if you feel that you are unable to pay.'),
                _('If you do donate, please specify in the comments if you would like a itemized receipt for business expense or tax purposes.'),
            ];

            echo implode(" ", $msgs);
            ?>
            </p></td>
            <td>
                <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
                    <input type="hidden" name="cmd" value="_s-xclick">
                    <input type="hidden" name="hosted_button_id" value="S9DFF2L4BA56L">
                    <table>
                        <tr>
                            <td>
                                <input type="hidden" name="on0" value="<?php
                                    echo _('Support OpenFlights'); ?>"><?php echo _('Support OpenFlights'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <select name="os0">
                                    <option value="<?php echo _('Small Donation');
                                    ?>"><?php echo _('Small Donation $20.00 USD'); ?></option>
                                    <option value="<?php echo _('Donation');
                                    ?>" selected><?php echo _('Donation $50.00 USD'); ?></option>
                                    <option value="<?php echo _('Combo Donation');
                                    ?>"><?php echo _('Combo Donation $100.00 USD'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <input type="hidden" name="currency_code" value="USD">
                    <input type="image" src="https://www.paypalobjects.com/en_GB/SG/i/btn/btn_buynowCC_LG.gif" border="0" name="submit" alt="PayPal — The safer, easier way to pay online.">
                    <img alt="" border="0" src="https://www.paypalobjects.com/en_GB/i/scr/pixel.gif" width="1" height="1">
                </form>

            </td>
        </tr>
    </table>

    <p><?php echo _('The GitHub copy is only a sporadically updated static snapshot of the live OpenFlights database (see <a href="https://github.com/jpatokal/openflights/commits/master/data/airports.dat">revision log</a>). If you would like an up-to-the-minute copy, or you would like your data filtered by any information available to us (eg. number of routes at the airport), do not hesitate to <a href="/about">contact us</a>.'); ?><p>
    <?php
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title><?php echo sprintf(_('OpenFlights: %s'), _('Airport and airline data')); ?></title>
    <meta name="description" content="Free airport and airline data with IATA, ICAO, latitude, longitude, elevation, timezone, DST information">
    <meta name="keywords" content="airport,airline,data,database,iata,icao,latitude,longitude,elevation,coordinates,timezone,dafif,free">
    <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
    <link rel="stylesheet" href="/css/help.css" type="text/css">
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

        <h1><?php echo _('Airport, airline and route data'); ?></h1>

        <?php echo _('Navigation: '); ?><a href="#airport"><?php echo _('Airport'); ?></a> | <a href="#airline"><?php echo _('Airline'); ?></a> | <a href="#route"><?php echo _('Route'); ?></a> | <a href="#plane"><?php echo _('Plane'); ?></a> | <a href="#country"><?php echo _('Country'); ?></a> | <a href="#schedule"><?php echo _('Schedule'); ?></a> | <a href="#other"><?php echo _('Other'); ?></a> | <a href="#license"><?php echo _('License'); ?></a>

        <a name="airport"></a>
        <h2><?php echo _('Airport database'); ?></h2>

        <p><center><a href="/demo/openflights-apdb-2048.png"><img src="/demo/openflights-apdb.png" width="512" height="256"></a><br><?php echo _('(click to enlarge)'); ?></center></p>

        <p><?php echo _('As of January 2017, the OpenFlights Airports Database contains <b>over 10,000</b> airports, train stations and ferry terminals spanning the globe, as shown in the map above. Each entry contains the following information:'); ?></p>

        <table>
            <tr>
              <td class="head"><?php echo _('Airport ID'); ?></td>
                <td class="data"><?php echo _('Unique OpenFlights identifier for this airport.'); ?></td>
            </tr>
            <tr>
              <td class="head"<?php echo _('Name'); ?>'); ?></td>
              <td class="data"><?php echo _('Name of airport. May or may not contain the <b>City</b> name.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('City'); ?></td>
              <td class="data"><?php echo _('Main city served by airport. May be spelled differently from <b>Name</b>.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Country'); ?></td>
              <td class="data"><?php echo _('Country or territory where airport is located. See <a href="#country">Countries</a> to cross-reference to ISO 3166-1 codes.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('IATA'); ?></td>
              <td class="data"><?php echo _('3-letter IATA code. Null if not assigned/unknown.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('ICAO'); ?></td>
              <td class="data"><?php echo _('4-letter ICAO code.<br>
                Null if not assigned.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Latitude'); ?></td>
              <td class="data"><?php echo _('Decimal degrees, usually to six significant digits. Negative is South, positive is North.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Longitude'); ?></td>
              <td class="data"><?php echo _('Decimal degrees, usually to six significant digits. Negative is West, positive is East.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Altitude'); ?></td>
              <td class="data"><?php echo _('In feet.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Timezone'); ?></td>
              <td class="data"><?php echo _('Hours offset from UTC. Fractional hours are expressed as decimals, eg. India is 5.5.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('DST'); ?></td>
              <td class="data"><?php echo _('Daylight savings time. One of E (Europe), A (US/Canada), S (South America), O (Australia), Z (New Zealand), N (None) or U (Unknown). <i>See also: <a target="_blank" href="help/time.php">Help: Time</a></i>'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Tz database time zone'); ?></td>
              <td class="data"><?php echo _('Timezone in <a href="https://en.wikipedia.org/wiki/Tz_database">"tz" (Olson) format</a>, eg. "America/Los_Angeles".'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Type'); ?></td>
              <td class="data"><?php echo _('Type of the airport. Value "airport" for air terminals, "station" for train stations, "port" for ferry terminals and "unknown" if not known. <i>In airports.csv, only type=airport is included.</i>'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Source'); ?></td>
              <td class="data"><?php echo _('Source of this data. "OurAirports" for data sourced from <a href="https://ourairports.com/data/">OurAirports</a>, "Legacy" for old data not matched to OurAirports (mostly DAFIF), "User" for unverified user contributions. <i>In airports.csv, only source=OurAirports is included.</i>'); ?></td>
            </tr>
        </table>

        <p><?php echo _('The data is UTF-8 encoded.'); ?></p>

        <p><?php echo _('<i>Note</i>: Rules for daylight savings time change from year to year and from country to country. The current data is an approximation for 2009, built on a country level. Most airports in DST-less regions in countries that generally observe DST (eg. AL, HI in the USA, NT, QL in Australia, parts of Canada) are marked incorrectly.'); ?></p>

        <h4><?php echo _('Sample entries'); ?></h4>
        <pre>507,"London Heathrow Airport","London","United Kingdom","LHR","EGLL",51.4706,-0.461941,83,0,"E","Europe/London","airport","OurAirports"
26,"Kugaaruk Airport","Pelly Bay","Canada","YBB","CYBB",68.534401,-89.808098,56,-7,"A","America/Edmonton","airport","OurAirports"
3127,"Pokhara Airport","Pokhara","Nepal","PKR","VNPK",28.200899124145508,83.98210144042969,2712,5.75,"N","Asia/Katmandu","airport","OurAirports"
8810,"Hamburg Hbf","Hamburg","Germany","ZMB",\N,53.552776,10.006683,30,1,"E","Europe/Berlin","station","User"
</pre>

        <p style="font-size: 1.5em; padding: 10px; background-color: #ccffcc;"><?php echo _('Try it out: <a target="_blank" href="html/apsearch">Airport Search</a> (new window)'); ?></p>

        <p><?php echo _('<i>Note</i>: The Airport Search window above is a part of <a href="https://openflights.org">OpenFlights</a>. You will not be able to add or edit airports unless you are logged in.'); ?></p>

        <h4><?php echo _('Download'); ?></h4>

        <p><?php echo _('To download the current data dump from <a href="https://github.com/jpatokal/openflights">GitHub</a> as a very straightforward CSV (comma-separated value) file, suitable for use in spreadsheets etc., simply click below:'); ?></p>

        <p style="font-size: 1.5em; padding: 10px; background-color: #ccffcc;"><?php echo _('Download: <a href="https://raw.githubusercontent.com/jpatokal/openflights/master/data/airports.dat">airports.dat</a> (Airports only, high quality)'); ?></p>

        <p style="font-size: 1.5em; padding: 10px; background-color: #ccffcc;"><?php echo _('Download: <a href="https://raw.githubusercontent.com/jpatokal/openflights/master/data/airports-extended.dat">airports-extended.dat</a> (Airports, train stations and ferry terminals, including user contributions)'); ?></p>

        <?php payPalOutput(); ?>

        <p><?php echo _("If you'd like an even more thorough database, with extensive coverage of airstrips, heliports and other places of less interest for commercial airline frequent flyers, do check out <a href=\"https://ourairports.com\">OurAirports</a>, whose public domain database covers over 40,000 places to fly from."); ?><p>

        <a name="airline"></a>
        <h2><?php echo _('Airline database'); ?></h2>

        <p><?php echo _('As of January 2012, the OpenFlights Airlines Database contains <b>5888</b> airlines. Each entry contains the following information:'); ?></p>

        <table>
            <tr>
              <td class="head"><?php echo _('Airline ID'); ?></td>
              <td class="data"><?php echo _('Unique OpenFlights identifier for this airline.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Name'); ?></td>
              <td class="data"><?php echo _('Name of the airline.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Alias'); ?></td>
              <td class="data"><?php echo _('Alias of the airline. For example, All Nippon Airways is commonly known as "ANA". '); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('IATA'); ?></td>
              <td class="data"><?php echo _('2-letter IATA code, if available.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('ICAO'); ?></td>
              <td class="data"><?php echo _('3-letter ICAO code, if available.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Callsign'); ?></td>
              <td class="data"><?php echo _('Airline callsign.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Country'); ?></td>
              <td class="data"><?php echo _('Country or territory where airport is located. See <a href="#country">Countries</a> to cross-reference to ISO 3166-1 codes.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Active'); ?></td>
              <td class="data"><?php echo _('"Y" if the airline is or has until recently been operational, "N" if it is defunct. This field is <i>not</i> reliable: in particular, major airlines that stopped flying long ago, but have not had their IATA code reassigned (eg. Ansett/AN), will incorrectly show as "Y".'); ?></td>
            </tr>
        </table>

        <p><?php echo _('The data is UTF-8 encoded. The special value <b>\N</b> is used for "NULL" to indicate that no value is available, and is understood automatically by MySQL if imported.'); ?></p>
        <p><?php echo _('<i>Notes</i>: Airlines with null codes/callsigns/countries generally represent user-added airlines. Since the data is intended primarily for current flights, defunct IATA codes are generally not included. For example, "Sabena" is not listed with a SN IATA code, since "SN" is presently used by its successor Brussels Airlines.'); ?></p>

        <h4><?php echo _('Sample entries'); ?></h4>
        <pre>324,"All Nippon Airways","ANA All Nippon Airways","NH","ANA","ALL NIPPON","Japan","Y"
412,"Aerolineas Argentinas",\N,"AR","ARG","ARGENTINA","Argentina","Y"
413,"Arrowhead Airways",\N,"","ARH","ARROWHEAD","United States","N"</pre>

        <p style="font-size: 1.5em; padding: 10px; background-color: #ccffcc;"><?php echo _('Try it out: <a target="_blank" href="html/alsearch">Airline Search</a> (new window)'); ?></p>

        <p><?php echo _('<i>Note</i>: The Airline Search window above is a part of <a href="https://openflights.org">OpenFlights</a>. You will not be able to view, add or edit airline details unless you are logged in.'); ?></p>

        <h4><?php echo _('Download'); ?></h4>

        <p><?php echo _('To download the current data dump from <a href="https://github.com/jpatokal/openflights/">GitHub</a> as a very straightforward CSV (comma-separated value) file, suitable for use in spreadsheets etc, simply click below:'); ?></p>

        <p style="font-size: 1.5em; padding: 10px; background-color: #ccffcc;"><?php echo _('Download: <a href="https://raw.githubusercontent.com/jpatokal/openflights/master/data/airlines.dat">airlines.dat</a> (~400 KB)'); ?></p>

        <?php payPalOutput(); ?>

        <a name="route"></a>
        <h2><?php echo _('Route database'); ?></h2>

        <p><center><a href="/demo/openflights-routedb-2048.png"><img src="/demo/openflights-routedb.png" width="512" height="256"></a><br>(click to enlarge)</center>'); ?></p>

        <p style="font-size: 1.5em; padding: 10px; background-color: #ffcccc;"><?php echo _('Warning: The third-party that OpenFlights uses for route data ceased providing updates in June 2014. The current data is of historical value only.'); ?></p>

        <p><?php echo _('As of June 2014, the OpenFlights/Airline Route Mapper Route Database contains <b>67663</b> routes between <b>3321</b> airports on <b>548</b> airlines spanning the globe, as shown in the map above. Each entry contains the following information:'); ?></p>

        <table>
            <tr>
              <td class="head"><?php echo _('Airline'); ?></td>
              <td class="data"><?php echo _('2-letter (IATA) or 3-letter (ICAO) code of the airline.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Airline ID'); ?></td>
              <td class="data"><?php echo _('Unique OpenFlights identifier for airline (see <a href="#airline">Airline</a>).'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Source airport'); ?></td>
              <td class="data"><?php echo _('3-letter (IATA) or 4-letter (ICAO) code of the source airport.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Source airport ID'); ?></td>
              <td class="data"><?php echo _('Unique OpenFlights identifier for source airport (see <a href="#airport">Airport</a>)'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Destination airport'); ?></td>
              <td class="data"><?php echo _('3-letter (IATA) or 4-letter (ICAO) code of the destination airport.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Destination airport ID'); ?></td>
              <td class="data"><?php echo _('Unique OpenFlights identifier for destination airport (see <a href="#airport">Airport</a>)'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Codeshare'); ?></td>
              <td class="data"><?php echo _('"Y" if this flight is a codeshare (that is, not operated by <i>Airline</i>, but another carrier), empty otherwise.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Stops'); ?></td>
              <td class="data"><?php echo _('Number of stops on this flight ("0" for direct)'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('Equipment'); ?></td>
              <td class="data"><?php echo _('3-letter codes for plane type(s) generally used on this flight, separated by spaces'); ?></td>
            </tr>
        </table>

        <p><?php echo _('The data is UTF-8 encoded. The special value <b>\N</b> is used for "NULL" to indicate that no value is available, and is understood automatically by MySQL if imported.'); ?></p>

        <i><?php echo _('Notes'); ?></i>:
        <ul>
          <li><?php echo _('Routes are directional: if an airline operates services from A to B and from B to A, both A-B and B-A are listed separately.'); ?></li>
          <li> echo _('Routes where one carrier operates both its own and codeshare flights are listed only once.'); ?></li>
        </ul>

        <h4> echo _('Sample entries'); ?></h4>
        <pre>BA,1355,SIN,3316,LHR,507,,0,744 777
BA,1355,SIN,3316,MEL,3339,Y,0,744
TOM,5013,ACE,1055,BFS,465,,0,320
</pre>

        <p><?php echo _("Route maps for airports and airlines can be viewed by <a href=\"https://openflights.org/blog/2009/07/15/airline-route-maps-launched-metric-distances-available/\">searching for their names or code in the website's Search
        box</a>; alternatively, check out the <a href=\"/html/route-maps\">alphabetical list of all covered airports and airlines</a>."); ?></p>

        <h4><?php echo _('Download'); ?></h4>

        <p><?php echo _('To download the current data dump from <a href="https://github.com/jpatokal/openflights">GitHub</a> as a comma-delimited file, suitable for use in spreadsheets etc, simply click below:'); ?></p>

        <p style="font-size: 1.5em; padding: 10px; background-color: #ccffcc;"><?php echo _('Download: <a href="https://raw.githubusercontent.com/jpatokal/openflights/master/data/routes.dat">routes.dat</a> (~2 MB)'); ?></p>

        <?php payPalOutput(); ?>

        <a name="plane"></a>
        <h2><?php echo _('Plane database'); ?></h2>

        <p><?php echo _('The OpenFlights plane database contains a curated selection of <b>173</b> passenger aircraft with IATA and/or ICAO codes, covering the
        vast majority of flights operated today and commonly used in flight schedules and reservation systems. Each entry contains the following information:'); ?></p>

        <table>
            <tr>
              <td class="head"><?php echo _('Name'); ?></td>
              <td class="data"><?php echo _('Full name of the aircraft.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('IATA code'); ?></td>
                <td class="data"><?php echo _('Unique three-letter IATA identifier for the aircraft.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('ICAO code'); ?></td>
              <td class="data"><?php echo _('Unique four-letter ICAO identifier for the aircraft.'); ?></td>
            </tr>
        </table>

        <p><?php echo _('The data is UTF-8 encoded. The special value <b>\N</b> is used for "NULL" to indicate that no value is available, and is understood automatically by MySQL if imported.'); ?></p>

        <i><?php echo _('Notes'); ?></i>:
        <ul>
          <li><?php echo _('Aircraft with IATA but without ICAO codes are generally aircraft classes: for example, IATA "747" can be any type of Boeing 747, whereas
            IATA "744"/ICAO "B744" is specifically a Boeing 747-400.'); ?>
          </li>
        </ul>

        <h4><?php echo _('Sample entries'); ?></h4>
        <pre>"Boeing 787","787",\N
"Boeing 787-10","78J","B78X"
"Boeing 787-8","788","B788"
</pre>

        <h4><?php echo _('Download'); ?></h4>

        <p><?php echo _('To download the current data dump from <a href="https://github.com/jpatokal/openflights">GitHub</a> as a comma-delimited file, suitable for use in spreadsheets etc, simply click below:'); ?></p>

        <p style="font-size: 1.5em; padding: 10px; background-color: #ccffcc;"><?php echo _('Download: <a href="https://raw.githubusercontent.com/jpatokal/openflights/master/data/planes.dat">planes.dat</a> (~5 KB)'); ?></p>

        <a name="country"></a>
        <h2>C<?php echo _('ountry database'); ?></h2>

        <p><?php echo _('he OpenFlights country database contains a list of <a href="https://en.wikipedia.org/wiki/ISO_3166-1">ISO 3166-1 country codes</a>,
            which can be used to look up the human-readable country names for the codes used in the Airline and Airport tables. Each entry contains the following information:'); ?></p>

        <table>
            <tr>
              <td class="head"><?php echo _('name'); ?></td>
              <td class="data"><?php echo _('Full name of the country or territory.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('iso_code'); ?></td>
              <td class="data"><?php echo _('Unique two-letter ISO 3166-1 code for the country or territory.'); ?></td>
            </tr>
            <tr>
              <td class="head"><?php echo _('dafif_code'); ?></td>
              <td class="data"><?php echo _('FIPS country codes as used in DAFIF.Obsolete and primarily of historical interested.'); ?></td>
            </tr>
        </table>

        <p><?php echo _('The data is UTF-8 encoded. The special value <b>\N</b> is used for "NULL" to indicate that no value is available, and is understood automatically by MySQL if imported.'); ?></p>

        <i><?php echo _('Notes'); ?></i>:
        <ul>
          <li><?php echo _('Some entries have DAFIF codes, but not ISO codes. These are primarily uninhabited islands without airports, and can be ignored for most purposes.'); ?></li>
        </ul>

        <h4> echo _('Sample entries'); ?></h4>
        <pre>"Australia","AU","AS"
"Ashmore and Cartier Islands",\N,"AT"
</pre>

        <h4><?php echo _('Download'); ?></h4>

        <p><?php echo _('To download the current data dump from <a href="https://github.com/jpatokal/openflights">GitHub</a> as a comma-delimited file, suitable for use in spreadsheets etc, simply click below:'); ?></p>

        <p style="font-size: 1.5em; padding: 10px; background-color: #ccffcc;"><?php echo _('Download: <a href="https://raw.githubusercontent.com/jpatokal/openflights/master/data/countries.dat">countries.dat</a> (~5 KB)'); ?></p>

        <a name="schedule"></a>
        <h2><?php echo _('Schedules (Timetables)'); ?></h2>

        <p><?php echo _('OpenFlights is currently considering launching an airline schedule (timetable) data service. Please register your interest by <a href="https://docs.google.com/forms/d/1KadfmOED90LHtUEOxA2oYW9nrN9ibqkz0oRVVogxM8A/viewform">filling out this short survey</a>.'); ?></p>

        <a name="other"></a>
        <h2><?php echo _('Other'); ?></h2>

        <p><?php echo _('OpenFlights can, on request, create customized versions of the images you see above. Options include higher resolution (suitable for printing), different backgrounds, filtering to highlight an airport or airline, etc. Please <a href="/about">contact us</a> for a quote.'); ?></p>

        <p><?php echo _('OpenFlights offers only airport, airline and route data; we do <em>not</em> have any other data available. We also do not have historical data older than 2009.'); ?></p>
        <p><?php echo _('Some alternative commercial sources of data include:'); ?></p>
        <ul>
          <li><a href="https://www.cleartrip.com/api/docs/air-api/">Cleartrip</a><?php echo _(': airfares'); ?></li>
          <li><a href="https://flightaware.com/commercial/aeroapi/">FlightAware</a><?php echo _(': flight tracking'); ?></li>
          <li><a href="https://developer.flightstats.com/">FlightStats</a><?php echo _(': schedules, flight tracking, historical data'); ?></li>
          <li><a href="https://flightwise.com/">FlightWise</a><?php echo _(': flight tracking'); ?></li>
        </ul>
        <p><?php echo _('If you or a website you know of offers any of these for free, please <a href="/about">let us know</a>!'); ?></p>

        <a name="license"></a>
        <h2><?php echo _('Licensing and disclaimer'); ?></h2>

        <p><?php echo _('The OpenFlights Airport, Airline, Plane and Route Databases are made available under the <a href="https://opendatacommons.org/licenses/odbl/1-0/">Open Database License</a>. Any rights in individual contents of the database are licensed under the <a href="http://opendatacommons.org/licenses/dbcl/1.0/">Database Contents License</a>. In short, these mean that you are welcome to use the data as you wish, if and only if you <i>both</i> acknowledge the source <i>and</i> and license any derived works made available to the public with a free license as well.'); ?></p>

        <p><?php echo _('Licenses for commercial use of data or derived images are available on request. Most simple cases, for example use of an image within a book or other printed publication, are granted for a flat fee of US$100. Please <a href="/about">contact us</a> for details.'); ?></p>

        <p><?php echo _('Airport data derived <a href="https://ourairports.com">OurAirports</a> and DAFIF, as well as route data from <a href="http://arm.64hosts.com/">Airline Route Mapper</a>, is in the public domain. Airline and plane data derived from Wikipedia may be subject to the <a href="http://www.gnu.org/copyleft/fdl.html">GNU Free Documentation License</a>. Whether these databases pass the <a href="https://en.wikipedia.org/wiki/Threshold_of_originality">threshold of originality</a> and are thus copyrightable in the United States is an open question, and OpenFlights does not assert the validity or lack thereof of such a claim.'); ?></p>

        <p><?php echo _('<b>This data is not suitable for navigation.</b> OpenFlights does not assume any responsibility whatsoever for its accuracy, and consequently assumes no liability whatsoever for results obtained or loss or damage incurred as a result of application of the data. OpenFlights expressly disclaims all warranties, expressed or implied, including but not limited to implied warranties of merchantability and fitness for any particular purpose.'); ?></p>

        <p><?php echo _('Any corrections will be <a href="/about">gratefully received</a>.'); ?>'); ?></p>

        <h2><?php echo _('Sources'); ?></h2>

        <p><?php echo _('Airport base data was generated by from DAFIF (October 2006 cycle) and <a href="https://ourairports.com">OurAirports</a>, plus timezone information from <a href="https://web.archive.org/web/20150407192035/http://www.earthtools.org/">EarthTools</a>. All DST information added manually. Significant revisions and additions made by the users of OpenFlights.'); ?>'); ?></p>

        <p><?php echo _("Airline data was extracted directly from Wikipedia's gargantuan <a href=\"https://en.wikipedia.org/en/List_of_airlines\">List of airlines</a>.'); ?>
        Plane data from <a href=\"https://en.wikipedia.org/wiki/List_of_ICAO_aircraft_type_designators\">List of ICAO aircraft type designators</a>.'); ?>
        Significant revisions and additions made by the users of OpenFlights."); ?></p>

        <p><?php echo _('Route data is maintained by and imported directly from <a href="http://arm.64hosts.com/">Airline Route Mapper</a>. Duplicate entry removal and cross-referencing to airport/airline IDs by OpenFlights.'); ?></p>

        <p><?php echo _('<i>See also: <a target="_blank" href="help/database.php">Help: Database</a>'); ?></i></p>

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
