<?php
require_once "../php/locale.php";
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <title>OpenFlights Help: Time</title>
        <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
        <link rel="stylesheet" href="/openflights.css" type="text/css">
        <link rel="stylesheet" href="/css/help.css" type="text/css">
        <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
    </head>

    <body>
    <div id="contexthelp">

        <h1><?php echo _('Context Help: Time'); ?></h1>

        <p><?php echo _('OpenFlights tries to understand <b>local time</b>, so you can enter local departure and arrival times and have the actual flight duration computed automatically. If you enter only a departure time, OpenFlights will estimate the flight duration based on the length of the flight and suggest an arrival time.'); ?></p>

        <p><?php echo _('To see the time zones and DST status of current airports when adding or editing flights, hover your mouse over the <img src="/img/icon_clock.png" alt="clock" width=16 height=16> icon.'); ?></p>

        <h2><?php echo _('Consistency'); ?></h2>
        <p><?php echo _('Internal consistency between date, time, flight duration and flight distance is enforced as follows:'); ?></p>

        <p><table class="time">
            <tr>
                <td class="head" rowspan=2><?php echo _('Changing this...&nbsp'); ?></td>
                <td class="head" colspan=3><?php echo _('recalculates these'); ?></td>
            </tr>
            <tr>
                <td class="key"><?php echo _('Arrival time'); ?></td>
                <td class="key"><?php echo _('Duration'); ?></td>
                <td class="key"><?php echo _('Distance'); ?></td>
            <tr>
                <td class="key"><?php echo _('Airport'); ?></td>
                <td class="yes"><?php echo _('Yes'); ?></td>
                <td class="yes"><?php echo _('Yes'); ?></td>
                <td class="yes"><?php echo _('Yes'); ?></td>
            </tr>
            <tr>
                <td class="key"><?php echo _('Arrival time'); ?></td>
                <td></td>
                <td class="yes"><?php echo _('Yes'); ?></td>
                <td class="no"><?php echo _('No'); ?></td>
            </tr>
            <tr>
                <td class="key"><?php echo _('Duration'); ?></td>
                <td class="yes"><?php echo _('Yes'); ?></td>
                <td></td>
                <td class="no"><?php echo _('No'); ?></td>
            </tr>
            <tr>
                <td class="key"><?php echo _('Distance'); ?></td>
                <td class="no"><?php echo _('No'); ?></td>
                <td class="no"><?php echo _('No'); ?></td>
                <td></td>
            </tr>
            <tr>
                <td class="key"><?php echo _('Date'); ?></td>
                <td class="yes"><?php echo _('Yes'); ?></td>
                <td class="no"><?php echo _('No'); ?></td>
                <td class="no"><?php echo _('No'); ?></td>
            </tr>
            <tr>
                <td class="key"><?php echo _('Departure time'); ?></td>
                <td class="yes"><?php echo _('Yes'); ?></td>
                <td class="no"><?php echo _('No'); ?></td>
                <td class="no"><?php echo _('No'); ?></td>
            </tr>
        </table></p>

        <p><ul>
            <li><?php echo _('The date and departure time are never changed automatically.'); ?></li>
            <li><?php echo _('Arrival time is only computed if departure time is known and the user has not entered it manually before entering airports.'); ?></li>
            <li><?php echo _('If arrival time is blanked, it is re-estimated based on the current duration.'); ?></li>
            <li><?php echo _('If duration is blanked, it is re-estimated based on the current distance.'); ?></li>
        </ul></p>

        <h2><?php echo _('Time zones'); ?></h2>

        <p><?php echo _('OpenFlights uses UTC offsets as time zones, so UTC+8 (Singapore) is recorded as "+8" and UTC-5 (New York) as "-5". Time zone data for OpenFlights was obtained from <a href="https://web.archive.org/web/20150407192111/http://www.earthtools.org/webservices.htm">EarthTools</a>.'); ?></p>

        <h2><?php echo _('Daylight Savings Time (DST)'); ?></h2>

        <p><?php echo _('When active, Daylight Savings Time (DST), or "summer time", adds one to the normal timezone, so e.g. New York, normally UTC-5, becomes UTC-4 while DST is active. OpenFlights currently understands the following types of DST:'); ?></p>
        <ul>
            <li><?php echo _('<b>European</b>: Starts on the last Sunday of March, ends on the last Sunday of October. Used in all European countries (except Iceland), as well as Greenland, Lebanon, Russia and Tunisia. Jordan and Syria are <i>almost</i> the same, starting and ending on Friday instead of Sunday. European DST is also used to (crudely) approximate Iranian DST, although they actually use an entirely different calendar.'); ?></li>
            <li><?php echo _('<b>US/Canada</b>: Starts on the second Sunday of March, ends on the first Sunday of November. Used in the United States (except Arizona, Hawaii and island territories) and Canada (with convoluted exceptions).'); ?></li>
            <li><?php echo _('<b>South American</b>: Starts on the third Sunday of October, ends on the third Sunday of March. Used, with some variance in the exact dates, in Argentina, Chile, Mexico, Paraguay, Uruguay as well as the African states of Namibia and Mauritius.'); ?></li>
            <li><?php echo _('<b>Australia</b>: Starts on the first Sunday of October, ends on the first Sunday of April. <i>Not</i> used in Queensland and the Northern Territory.'); ?></li>
            <li><?php echo _('<b>New Zealand</b>: Starts on the last Sunday of September, ends on the first Sunday of April.'); ?></li>
            <li><?php echo _('<b>None</b>: DST not observed.'); ?></li>
            <li><?php echo _('<b>Unknown</b>: DST status not known. The same as "None".'); ?></li>
        </ul>

        <p><?php echo _('The rules for DST change constantly and not all airports are up-to-date or marked correctly. Please <a href="/contact.html">contact</a> the OpenFlights team if you find any errors.'); ?></p>

        <h2><?php echo _('Examples'); ?></h2>

        <p><?php echo _('A flight in April departs Singapore (SIN) at 20:00 and arrives in Chennai (MAA) at 21:30. Singapore is UTC+8, Chennai is UTC+5.5. Flight duration is thus (21:30-20:00) - (05:30-08:00) = 1:30 - (-2:30) = <b>4:00</b>.'); ?></p>

        <p><?php echo _('A flight in June departs Newark (EWR) at 23:00 and arrives in Singapore (SIN) at 07:40 + 2 days. Singapore is UTC+8, New York is UTC-4 (DST). Flight duration is thus (07:40+48:00)-23:00 - (-04:00-08:00) = -32:40 - -(12:00) = <b>20:40</b>.'); ?></p>

        <form>
            <input type="button" value="<?php echo _('Close'); ?>" onClick="window.close()">
        </form>

        </div>
    </body>
</html>
