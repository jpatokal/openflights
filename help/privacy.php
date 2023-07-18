<?php
require_once "../php/locale.php";
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <title><?php echo sprintf(_('OpenFlights: %s'), _('Privacy policy')); ?></title>
        <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
        <link rel="stylesheet" href="/openflights.css" type="text/css">
        <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
    </head>

    <body>
        <div id="contexthelp">
            <h1><?php echo sprintf(_('OpenFlights: %s'), _('Privacy policy')); ?></h1>
            <p><?php echo _("This document outlines OpenFlights' respect for your personal privacy as a user of the service."); ?></p>

            <ul>
                <li><?php echo _('Your flights will not be visible to other users, business partners or the public at large <i>unless</i> you opt to make them public.'); ?></li>
                <li><?php echo _('Your password or e-mail address will not be shared with other users, business partners or the public at large.'); ?></li>
                <li><?php echo _('OpenFlights may display, package, and provide for reuse your flights, as well as any airports or airlines added by you, in an anonymized format. (See <a href="/data.php">Databases</a> for details.)'); ?></li>
                <li><?php echo _('OpenFlights will comply with court orders to turn over your private information.'); ?></li>
                <li><?php echo _('OpenFlights uses third-party advertising companies to serve ads when you visit our website. These companies may use information (<i>not</i> including your name, address, email address, or telephone number) about your visits to this and other websites in order to provide advertisements about goods and services of interest to you. If you would like more information about this practice and to know your choices about not having this information used by these companies, <a href="https://policies.google.com/technologies/ads">click here</a>.'); ?></li>
            </ul>

            <form>
                <input type="button" value="Close'); ?>" onClick="javascript:window.close()">
            </form>

        </div>

    </body>
</html>
