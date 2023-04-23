<?php session_start(); ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <title>OpenFlights: Donations and Elite Levels</title>
        <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
        <link rel="stylesheet" href="/openflights.css" type="text/css">
        <link rel="icon" type="image/png" href="/img/icon_favicon.png"/>
    </head>

  <body>
    <div id="mainContainer">
      <div id="sideBarContentWrapper">

    <div id="contentContainer">
      <div id="nonmap">

        <h1>Donations and Elite Levels</h1>

        <p>True to our name, we at OpenFlights believe in open access &mdash; the functionality on our website, and the source code behind it, is free to all. However, hosting the site costs real money, and the ads alone aren't enough to pay our bills. Donations to help support the site are thus <b>very</b> much appreciated, and in addition to an Elite-level star on your profile and bragging rights, donors receive a few extra goodies.</p>

        <h4>OpenFlights Elite levels</h4>

        <table>
          <tr class="donate">
          <td class="donate"><b>Cyan</b><br>
            <img src="/img/icon-warning.png" width=34 height=34>
          </td>
          <td class="donate2">
            Access to entire website and all functions; however, your pages may contain banner and full-page advertising. If you add <b>over 100 flights</b> or set your profile to <b>Private</b>, we figure you're using a little more than your fair share of site resources, so your profile will be tagged with a warning icon and you will receive reminders to consider donating when you log in.</td>
          <td class="donate"><i>Free</i></td>
        </tr>
        <tr class="donate">
          <td class="donate"><b>Silver</b><br>
            <img src="/img/silver-star.png" width=34 height=34 alt="Silver star">
          </td>
          <td class="donate2">
            The cheapest way to soothe your conscience.
            <ul>
              <li>No reminders or full-page ads when logging in.</li>
              <li>No warning icon on your profile.</li>
            </ul>
          </td><td class="donate">
            <b>US$12/year</b>
          </td><td class="donate">
            <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
            <input type="hidden" name="cmd" value="_s-xclick">
            <input type="hidden" name="hosted_button_id" value="3X88LMPUUXD26">
            <input type="hidden" name="on0" value="Username">
            <input type="hidden" name="os0" value="<?php echo $_SESSION["name"]; ?>">
            <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_buynowCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
            <img alt="" border="0" src="https://www.paypalobjects.com/en_GB/i/scr/pixel.gif" width="1" height="1">
            </form>
          </td>
        </tr>
        <tr>
          <td class="donate"><b>Gold</b><br>
            <img src="/img/gold-star.png" width=34 height=34 alt="Gold star">
          </td>
          <td class="donate2">
            This is where the action is. All Silver features, plus
            <ul>
              <li>Banner ads replaced with panel of your choice (Help, Top 10, Analyze).</li>
              <li>Password-protect your private profile, so you can share it with friends.</li>
              <li>Dedicated mail address for support.</li>
            </ul>
          </td><td class="donate">
            <b>US$24/year</b>
          </td>
          <td class="donate">
            <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
            <input type="hidden" name="cmd" value="_s-xclick">
            <input type="hidden" name="hosted_button_id" value="CF6B9GXAHY4ML">
            <input type="hidden" name="on0" value="Username">
            <input type="hidden" name="os0" value="<?php echo $_SESSION["name"]; ?>">
            <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_buynowCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
            <img alt="" border="0" src="https://www.paypalobjects.com/en_GB/i/scr/pixel.gif" width="1" height="1">
            </form>
          </td>
        </tr>
        <tr>
          <td class="donate"><b>Platinum</b><br>
            <img src="/img/platinum-star.png" width=34 height=34>
          </td>
          <td class="donate2">
            For connoisseurs of the finer things in life.  All Gold features, plus
            <ul>
              <li>Priority handling for bug reports and feature requests.</li>
              <li>Access to future versions before public release.</li>
            </ul>
          </td><td class="donate">
            <b>US$48/year</b>
          </td><td class="donate">
            <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
            <input type="hidden" name="cmd" value="_s-xclick">
            <input type="hidden" name="hosted_button_id" value="TST2ERUAQDU4G">
            <input type="hidden" name="on0" value="Username">
            <input type="hidden" name="os0" value="<?php echo $_SESSION["name"]; ?>">
            <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_buynowCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
            <img alt="" border="0" src="https://www.paypalobjects.com/en_GB/i/scr/pixel.gif" width="1" height="1">
            </form>
        </td>
        </tr>
        </table>

<?php
$uid = $_SESSION["uid"];
$logged_in = $uid && !empty($uid);
if($logged_in) {
?>
        <p>You are logged in <b><?php echo $_SESSION["name"]; ?></b>.
<?php } else { ?>
        <p><b>Please <a href="/">log in</a> before donating!</b>
<?php } ?>
        Our payments are processed through PayPal, but you do <i>not</i> need a PayPal account: Visa, MasterCard, American Express and Discover cards are also accepted. Please allow one business day for your elite status to be activated.</p>

        <h4>Limited time offer</h4>

        <p>Submit OpenFlights to any website that sends over 1000 people our way, or any newspaper or magazine indexed on Google News, and we'll either give you a Silver status for a year or bump you up a level if you're already an elite member. Send your claim and the link as proof to us via <a href="/contact.html">Contact</a>, and we'll get back to you.</p>

        <h4>Obligatory disclaimer</h4>

        <p>We have no intention of alienating our fans, but for the record: OpenFlights reserves the right to adjust elite level privileges in any way and grant or revoke elite level privileges for any user at any time for any reason. No refunds are possible, even if OpenFlights is temporarily not accessible or goes permanently offline.</p>
      </div>
    </div>

    <div id="sideBar">
        <!--#include virtual="/sidebar.html" -->
        <!--#include virtual="/html/ad-sidebar.html" -->
    </div>

      </div> <!-- end sidebarwrapper -->
    </div> <!-- end mainContainer -->

</body>
</html>
