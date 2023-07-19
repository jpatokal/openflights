<?php

require_once "./php/locale.php";
require_once "./php/db_pdo.php";

function payPalOutput($buttonId){
    ?>
    <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
    <input type="hidden" name="cmd" value="_s-xclick">
    <input type="hidden" name="hosted_button_id" value="<?php echo $buttonId; ?>">
    <input type="hidden" name="on0" value="Username">
    <input type="hidden" name="os0" value="<?php echo $_SESSION["name"] ?? ''; ?>">
    <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_buynowCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
    <img alt="" border="0" src="https://www.paypalobjects.com/en_GB/i/scr/pixel.gif" width="1" height="1">
    </form>
    <?php
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <title><?php echo sprintf(_('OpenFlights: %s'), _('Donations and Elite Levels')); ?></title>
        <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
        <link rel="stylesheet" href="/openflights.css" type="text/css">
        <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale; ?>/LC_MESSAGES/messages.po" />
        <link rel="icon" type="image/png" href="/img/icon_favicon.png"/>
        <script type="text/javascript" src="/js/Gettext.min.js"></script>
        <script type="text/javascript" src="/js/functions.js"></script>
    </head>

    <body>
    <div id="mainContainer">
        <div id="contexthelp">
            <span style="float: right"><?php echo _("Language"); ?><br>
                <?php locale_pulldown($dbh, $locale); ?>
            </span>
        </div>

    <div id="sideBarContentWrapper">

    <div id="contentContainer">
      <div id="nonmap">

        <h1><?php echo _('Donations and Elite Levels'); ?></h1>

        <p><?php echo _("True to our name, we at OpenFlights believe in open access &mdash; the functionality on our website, and the source code behind it, is free to all. However, hosting the site costs real money, and the ads alone aren't enough to pay our bills. Donations to help support the site are thus <b>very</b> much appreciated, and in addition to an Elite-level star on your profile and bragging rights, donors receive a few extra goodies."); ?></p>

        <h4><?php echo _('OpenFlights Elite levels'); ?></h4>

        <table>
          <tr class="donate">
          <td class="donate"><b><?php echo _('Cyan'); ?></b><br>
            <img src="/img/icon-warning.png" alt="<?php echo _('Warning'); ?>" width=34 height=34>
          </td>
          <td class="donate2">
            <?php echo _("Access to entire website and all functions; however, your pages may contain banner and full-page advertising. If you add <b>over 100 flights</b> or set your profile to <b>Private</b>, we figure you're using a little more than your fair share of site resources, so your profile will be tagged with a warning icon and you will receive reminders to consider donating when you log in."); ?>
          </td>
          <td class="donate"><i><?php echo _('Free'); ?></i></td>
        </tr>
        <tr class="donate">
          <td class="donate"><b><?php echo _('Silver'); ?></b><br>
            <img src="/img/silver-star.png" alt="<?php echo _('Silver star'); ?>" width=34 height=34>
          </td>
          <td class="donate2">
            <?php echo _('The cheapest way to soothe your conscience.'); ?>
            <ul>
              <li><?php echo _('No reminders or full-page ads when logging in.'); ?></li>
              <li><?php echo _('No warning icon on your profile.'); ?></li>
            </ul>
          </td><td class="donate">
            <b><?php echo _('US$12/year'); ?></b>
          </td><td class="donate">
            <?php payPalOutput('3X88LMPUUXD26'); ?>
          </td>
        </tr>
        <tr>
          <td class="donate"><b><?php echo _('Gold'); ?></b><br>
            <img src="/img/gold-star.png" alt="<?php echo _('Gold star'); ?>" width=34 height=34>
          </td>
          <td class="donate2">
            <?php echo _('This is where the action is. All Silver features, plus'); ?>
            <ul>
              <li><?php echo _('Banner ads replaced with panel of your choice (Help, Top 10, Analyze).'); ?></li>
              <li><?php echo _('Password-protect your private profile, so you can share it with friends.'); ?></li>
              <li><?php echo _('Dedicated mail address for support.'); ?></li>
            </ul>
          </td><td class="donate">
            <b><?php echo _('US$24/year'); ?></b>
          </td>
          <td class="donate">
            <?php payPalOutput('CF6B9GXAHY4ML'); ?>
          </td>
        </tr>
        <tr>
          <td class="donate"><b><?php echo _('Platinum'); ?></b><br>
            <img src="/img/platinum-star.png" alt="<?php echo _('Platinum star'); ?>" width=34 height=34>
          </td>
          <td class="donate2">
              <?php echo _('For connoisseurs of the finer things in life. All Gold features, plus'); ?>
            <ul>
              <li><?php echo _('Priority handling for bug reports and feature requests.'); ?></li>
              <li><?php echo _('Access to future versions before public release.'); ?></li>
            </ul>
          </td><td class="donate">
            <b><?php echo _('US$48/year'); ?></b>
          </td><td class="donate">
            <?php payPalOutput('TST2ERUAQDU4G'); ?>
        </td>
        </tr>
        </table>

<?php
$uid = $_SESSION["uid"] ?? false;
$logged_in = $uid && !empty($uid);
if ($logged_in) {
    echo '<p>' . sprintf(_('You are logged in as <b>%s</b>.'), $_SESSION["name"]) . '</p>';
} else {
    echo '<p><b>' . _('Please <a href="/">log in</a> before donating!') . '</b></p>';
} ?>
          <p><?php echo _('Our payments are processed through PayPal, but you do <i>not</i> need a PayPal account: Visa, MasterCard, American Express and Discover cards are also accepted. Please allow one business day for your elite status to be activated.'); ?></p>

        <h4><?php echo _('Limited time offer'); ?></h4>

        <p><?php echo _("Submit OpenFlights to any website that sends over 1000 people our way, or any newspaper or magazine indexed on Google News, and we'll either give you a Silver status for a year or bump you up a level if you're already an elite member. Send your claim and the link as proof to us via <a href=\"/contact.html\">Contact</a>, and we'll get back to you."); ?></p>

        <h4><?php echo _('Obligatory disclaimer'); ?></h4>

        <p><?php echo _('We have no intention of alienating our fans, but for the record: OpenFlights reserves the right to adjust elite level privileges in any way and grant or revoke elite level privileges for any user at any time for any reason. No refunds are possible, even if OpenFlights is temporarily not accessible or goes permanently offline.'); ?></p>
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
