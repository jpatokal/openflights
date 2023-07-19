<?php
require_once "../php/locale.php";
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <title>OpenFlights Help: CSV</title>
        <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
        <link rel="stylesheet" href="/openflights.css" type="text/css">
        <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
        <link rel="icon" type="image/png" href="/img/icon_favicon.png"/>
    </head>

    <body>
        <div id="contexthelp">

        <h1><?php echo _('Context Help: Comma-Separated Value (CSV) Format'); ?></h1>

        <p><?php echo _('This document defines <b>Revision 0.41</b> of the comma-separated value (CSV) format natively exported and imported by OpenFlights.'); ?></p>

        <p><?php echo _('OpenFlights CSV follows <a href="https://datatracker.ietf.org/doc/html/rfc4180#page-2">RFC 4180</a>.'); ?></p>
        <ul>
            <li><?php echo _('The character set encoding is <b>UTF-8</b>.'); ?></li>
            <li><?php echo _('The field separator is comma (<b>,</b> 0x2C), the row separator is CRLF (<b>\r\n</b> 0x0D 0x0A) and the field delimiter is double quote (<b>"</b> 0x22).'); ?></li>
            <li><?php echo _('The first line of any OpenFlights CSV file is considered a header and ignored. This line starts with a Unicode Byte Order Mark (U+FEFF), represented in UTF-8 as 0xEF 0xBB 0xBF. (Old OpenFlights exports incorrectly used the UTF-16 BOM 0xFE OxFF.)'); ?></li>
            <li><?php echo _('All fields may be left empty <i>unless</i> listed as <font color="red">Mandatory</font> below.'); ?></li>
        </ul>

        <h2><?php echo _('Field definition'); ?></h2>

        <dl>
            <dt><?php echo _('1. Date'); ?></dt>
            <dd><?php echo _('Date of flight, in YYYY-MM-DD (dash-separated, <i>preferred format</i>), MM/DD/YYYY (slash-separated), DD.MM.YYYY (dot-separated) or YYYY (year-only) format. The date <i>may</i> optionally also contain the departure time of the flight in the format HH:MM[:SS], separated from the date by a space. Seconds are optional, but presently ignored. <font color="red">Mandatory (date part only)</font>.'); ?></dd>
            <dt><?php echo _('2. From'); ?></dt>
            <dd><?php echo _('IATA or ICAO code of source airport. <font color="red">Mandatory unless "From_OID" is provided.</font><'); ?>/dd>
            <dt><?php echo _('3. To'); ?></dt>
            <dd><?php echo _('IATA or ICAO code of destination airport. <font color="red">Mandatory unless "To_OID" is provided.</font>'); ?></dd>
            <dt><?php echo _('4. Flight_Number'); ?></dt>
            <dd><?php echo _('Flight number. <font color="blue">If first two characters are an airline code (two alphanumeric digits), the code is used to override "Airline".</font>'); ?></dd>
            <dt><?php echo _('5. Airline'); ?></dt>
            <dd><?php echo _('Full name of airline name. Airline codes are not recognized. <font color="blue">May be overridden by "Flight_Number", defaults to Unknown if not available.</font>'); ?></dd>
            <dt><?php echo _('6. Distance'); ?></dt>
            <dd><?php echo _('Distance of flight, in miles. <font color="blue">Calculated automatically if not provided.'); ?></font></dd>
            <dt><?php echo _('7. Duration'); ?></dt>
            <dd><?php echo _('Duration of flight, in the format HH:MM. <font color="blue">Estimated automatically if not provided.'); ?></font></dd>
            <dt><?php echo _('8. Seat'); ?></dt>
            <dd><?php echo _('Seat number.'); ?></dd>
            <dt><?php echo _('9. Seat_Type'); ?></dt>
            <dd><?php echo _('Seat type: one of "W" for Window, "A" for Aisle or "M" for Middle.'); ?></dd>
            <dt><?php echo _('10. Class'); ?></dt>
            <dd><?php echo _('Class: one of "F" for First, "C" for Business, "P" for Premium Economy, "Y" for Economy.'); ?></dd>
            <dt><?php echo _('11. Reason'); ?></dt>
            <dd><?php echo _('Reason for flight: one of "B" for Business, "L" for Personal, "C" for Crew or "O" for Other.'); ?></dd>
            <dt><?php echo _('12. Plane'); ?></dt>
            <dd><?php echo _('Plane type.'); ?></dd>
            <dt><?php echo _('13. Registration'); ?></dt>
            <dd><?php echo _('Plane registration number.'); ?></dd>
            <dt><?php echo _('14. Trip'); ?></dt>
            <dd><?php echo _('Internal OpenFlights Trip ID. <font color="blue">Optional, but must exist and belong to the user importing the data if specified.</font>'); ?></dd>
            <dt><?php echo _('15. Note'); ?></dt>
            <dd><?php echo _('User-entered note or comment about flight.'); ?></dd>
        </dl>
        <?php echo _('The final four fields contain internal OpenFlights IDs (OID) for ensuring accurate imports. They are generated automatically when exporting, but should <i>not</i> be provided if creating or importing your own data, or altering the airline, airport or plane fields of exported data.'); ?>
        <dl>
            <dt><?php echo _('6. From_OID'); ?></dt>
            <dd><?php echo _('Internal OpenFlights airport ID. <font color="blue">Overrides "From" if provided.</font>'); ?></dd>
            <dt><?php echo _('17. To_OID'); ?></dt>
            <dd><?php echo _('Internal OpenFlights airport ID. <font color="blue">Overrides "To" if provided.</font>'); ?></dd>
            <dt><?php echo _('18. Airline_OID'); ?></dt>
            <dd><?php echo _('Internal OpenFlights airline ID. <font color="blue">Overrides "Airline" if provided.</font>'); ?></dd>
            <dt><?php echo _('19. Plane_OID'); ?></dt>
            <dd><?php echo _('Internal OpenFlights plane ID. <font color="blue">Overrides "Plane" if provided.</font>'); ?></dd>
        </dl>

        <h2><?php echo _('Airline matching'); ?></h2>
        <p><?php echo _('Airlines are matched in the following order:'); ?></p>
        <ol>
            <li><?php echo _('"Airline_OID" as internal identifier'); ?></li>
            <li><?php echo _('First two characters of "Flight_Number" as IATA code, if alphanumeric <i>and</i> not numeric (AB, A1, 1A are OK, but 11 is not)'); ?></li>
            <li><?php echo _('"Airline" as name or alias'); ?></li>
        </ol>
        <p><?php echo _('Note that the airline code (IATA/ICAO) must be supplied in "Flight_Number", <i>not</i> "Airline".'); ?></p>
        <ul>
            <li><?php echo _('If multiple matches are found, "Airline" is used to determine the best fit.'); ?></li>
            <li><?php echo _('If "Airline" is defined but no matches are found, "Airline" is added as new.'); ?></li>
            <li><?php echo _('If "Flight Number" defines an unrecognized IATA code, import fails.'); ?></li>
            <li><?php echo _('If neither "Airline" nor "Flight_Number" are defined, "Airline" is set to Unknown.'); ?></li>
        </ul>

        <h2><?php echo _('Sample'); ?></h2>
        <p><?php echo _('The following sample shows the maximal, recommended, and three minimal data sets for importing a flight (airline and flight number, airline only, and neither airline nor flight number).'); ?></p>
        <div style="overflow: auto">
            <pre>
            Date,From,To,Flight_Number,Airline,Distance,Duration,Seat,Seat_Type,Class,Reason,Plane,Registration,Trip,Note,From_OID,To_OID,Airline_OID,Plane_OID
            2008-01-18 10:30:10,DXB,RUH,SV559,Saudi Arabian Airlines,542,01:35,40C,A,Y,B,Boeing 777,HZ-AKH,16,"First, to Saudi!",2188,2082,4533,3
            2008-01-18 10:30,DXB,RUH,SV559,Saudi Arabian Airlines,542,01:35,40C,A,Y,B,Boeing 777,HZ-AKH,16,"First, to Saudi!"
            2008-01-18,DXB,RUH,SV559
            2008-01-18,DXB,RUH,,Saudi Arabian Airlines
            2008-01-18,DXB,RUH
            </pre>
        </div>

        <p><?php echo _('<i>Note</i>: The first line of any CSV file is always ignored, the field key is provided only for your own reference.'); ?></p>

        <h2><?php echo _('Revision history'); ?></h2>
        <dl>
            <dt><?php echo _('0.42 &mdash; Nov 7, 2010'); ?></dt>
            <dd><?php echo _('Fix representation of byte-order mark in UTF-8.'); ?></dd>

            <dt><?php echo _('0.41 &mdash; Jul 21, 2009'); ?></dt>
            <dd><?php echo _('Clarified encoding.'); ?></dd>

            <dt><?php echo _('0.4 &mdash; Feb 1, 2009'); ?></dt>
            <dd><?php echo _('Date field now allows an optional time element.'); ?></dd>

            <dt><?php echo _('0.3 &mdash; Dec 22, 2008'); ?></dt>
            <dd><?php echo _('Corrected business class specifier to "C", although "B" is still accepted. Support for American (DD/MM/YYYY) and European (DD.MM.YYYY) dates added.'); ?></dd>

            <dt><?php echo _('0.2 &mdash; Dec 2, 2008'); ?></dt>
            <dd><?php echo _('Specifying airline no longer mandatory. Clarified field names and airline matching logic.'); ?></dd>

            <dt><?php echo _('0.1 &mdash; Nov 30, 2008'); ?></dt>
            <dd><?php echo _('Original release.'); ?></dd>
        </dl>

        <form>
            <input type="button" value="<?php echo _('Close'); ?>" onClick="window.close()">
        </form>

        </div>
    </body>
</html>
