<?php

require_once "../php/locale.php";
require_once "../php/db_pdo.php";
require_once "../php/functions.php";

$type = isset($_GET["new"]) ? "signup" : "settings";

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
<title>OpenFlights: <?php
if ($type == "signup") {
    echo _("Create new account");
} else {
    echo _("Account settings");
}
?></title>
    <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
    <link rel="stylesheet" href="/css/signup.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale;?>/LC_MESSAGES/messages.po" />
    <script type="text/javascript" src="/js/md5.min.js"></script>
    <script type="text/javascript" src="/js/functions.js"></script>
    <script type="text/javascript" src="/js/Gettext.min.js"></script>
    <script type="text/javascript" src="/js/settings.js"></script>
    <?php include "../html/analytics.html"; ?>
  </head>

  <body>
    <div id="mainContainer">
      <div id="sideBarContentWrapper">

    <div id="contentContainer">
      <div id="nonmap">

        <form name="signupform" method="POST" action="/">
  <a name="top"><h1>OpenFlights: <?php echo ($type == "signup") ? _("Create new account") : _("Account settings"); ?>
      </h1></a>

          <div id="miniresultbox"></div>

          <table>
<?php
if ($type == "signup") {
    $settings = array(
        "public" => "Y",
        "units" => "M",
        "editor" => "B"
    );
    ?>
          <tr>
                <td colspan="3"><h2><?php echo _("Basic information") ?></h2></td>
          </tr>
          <tr>
            <td class="key"><?php echo _("Username") ?></td>
            <td class="value"><input type="text" name="username" size="20" onChange="changeName();"></td>
            <td class="value"><p><?php echo _("This will be used as the name of your profile.") ?></p>
              <span id="profileurl"></span></td>
          </tr>
          <tr>
              <td class="key"><?php echo _("Password") ?></td>
              <td class="value"><input type="password" name="pw1" size="20"></td>
              <td class="value"><?php
                  echo _("Pick something hard to guess, but easy to remember. Case-sensitive!")?>
              </td>
          </tr>
          <tr>
              <td class="key"><?php echo _("Password again") ?></td>
              <td class="value"><input type="password" name="pw2" size="20"></td>
          </tr>
          <tr>
              <td class="key"><?php echo _("E-mail (optional)") ?>&nbsp;&nbsp;</td>
              <td class="value"><input type="text" name="email" size="20"></td>
              <td class="desc">
              <?php printf(
                  _("If you forget your password, we can mail you a new one to this address. We will <i>never</i> send you any other mail or share your private information, see <%s>privacy policy</a> for details."),
                  "a href='#' onClick='window.open(\"/help/privacy.html\", \"Help\", \"width=500,height=400,scrollbars=yes\")'"
              ); ?>
              </td>
          </tr>
    <?php
} else {
    $uid = $_SESSION["uid"] ?? null;
    if ( !$uid || empty($uid)) {
        die(_("Your session has timed out, please log in again."));
    }
    $sth = $dbh->prepare(
        "SELECT elite, validity, email, name, guestpw, public, count, editor, units, startpane, locale FROM users WHERE uid=?"
    );
    $sth->execute([$uid]);
    $settings = $sth->fetch();
    if (!$settings) {
        die(_("Could not load profile data"));
    }
    ?>
          <tr>
            <td class="key"><nobr><?php echo _("Profile address") ?></nobr></td>
            <td class="value"><input type="text" name="myurl" value="<?php
                echo "https://openflights.org/user/" . $settings["name"]
            ?>" style="border:none" size="40" READONLY>
                <input type="text" name="count" value="<?php
                    printf(_("Viewed %s times"), $settings["count"]) ?>" style="border: none" READONLY>
            </td>
            <td class="desc"><?php
                echo _("The public address of your profile and how often it has been viewed.") ?>
            </td>
            <td class="value" rowspan=3><span id="eliteicon"></span>
                <input type="hidden" name="elite" value="<?php echo $settings["elite"] ?>">
                <input type="hidden" name="validity" value="<?php echo $settings["validity"] ?>">
            </td>
          </tr>
          <tr>
              <td class="key"><?php echo _("Banners") ?></td>
              <td class="value" colspan=2><?php echo _("Blog banner (HTML)") ?><br>
                  <textarea name="banner_html" cols="60" rows="4" readonly><?php
                    echo "<a href='https://openflights.org/user/" .
                        $settings["name"] .
                        "' target='_blank'><img src='https://openflights.org/banner/" .
                        $settings["name"] . ".png' width=400 height=70></a>"; ?>
                  </textarea><br>
           <?php echo _("Bulletin board banner (phpBB)") ?><br>
              <textarea name="banner_phpbb" cols="60" rows="3" readonly><?php
                echo "[url=https://openflights.org/user/" .
                    $settings["name"] .
                    "]\n[img]https://openflights.org/banner/" .
                    $settings["name"] .
                    ".png[/img][/url]";
                ?></textarea><br>
              <span id="banner_img"><?php
                  echo "<img src='/banner/" . $settings["name"] . ".png' width=400 height=70>"?>
              </span>
              </td>
          </tr>
          <tr>
            <td class="key"><?php echo _("E-mail (optional)") ?>&nbsp;&nbsp;</td>
            <td class="value"><input type="text" name="email" value="<?php echo $settings["email"] ?>" size="20" />
            <td class="desc">
              <?php printf(
                _("If you forget your password, we can mail you a new one to this address. We will <i>never</i> send you any other mail or share your private information, see <%s>privacy policy</a> for details."),
                  "a href='#' onClick='window.open(\"/help/privacy.html\", \"Help\", \"width=500,height=400,scrollbars=yes\")'"
              ); ?>
            </td>
          </tr>
    <?php
}
?>
        <tr>
            <td colspan="4"><h2><?php echo _("Profile settings") ?></h2>
<?php
if ($type == "signup") {
    echo _("You can easily change these later by clicking on <i>Settings</i>.");
} ?></td>
        </tr>
        <tr>
            <td class="key"><?php echo _("Language"); ?></td>
            <td class="value"><?php locale_pulldown($dbh, $locale); ?></td>
        </tr>
        <tr>
            <td class="key"><?php echo _("Privacy") ?></td>
            <td class="value">
                <input type="radio" name="privacy" value="N" onClick="JavaScript:changePrivacy('N')" <?php
                    condOut($settings, 'public', 'N', 'CHECKED');
                    echo ">" . _("Private");
                ?><br>
                <input type="radio" name="privacy" value="Y" onClick="JavaScript:changePrivacy('Y')" <?php
                    condOut($settings, 'public', 'Y', 'CHECKED');
                    echo ">" . _("Public");
                ?><br>
                <input type="radio" name="privacy" value="O" onClick="JavaScript:changePrivacy('O')" <?php
                    condOut($settings, 'public', '0', 'CHECKED');
                    echo ">" . _("Open");
                ?><br>
            </td>
            <td class="desc">
                <span id="privacyN" style="display: none">
                <?php printf(
                    _("<b>Private</b> profiles are visible only to you. <%s>Gold and Platinum</a> users can password-protect their private profiles, so only people who know the password can see them."),
                    'a href="/donate" target="_blank"'
                ); ?>
                </span>
                <span id="privacyY" style="display: inline"><?php
                    echo _("<b>Public</b> profiles let others see your flight map and general statistics, but flight details like exact dates and class of service are not revealed.") ?>
                </span>
                <span id="privacyO" style="display: none"><?php
                    echo _("<b>Open</b> profiles let others see, but not edit, your detailed flight data as well.") ?>
                </span>
            </td>
          </tr>
          <tr>
            <td class="key"><?php echo _("Flight editor") ?></td>
            <td class="value">
                <input type="radio" name="editor" value="B" onClick="JavaScript:changeEditor('B')" <?php
                    condOut($settings, 'editor', 'B', 'CHECKED');
                    echo ">" . _("Basic"); ?><br>
                <input type="radio" name="editor" value="D" onClick="JavaScript:changeEditor('D')"<?php
                    condOut($settings, 'editor', 'D', 'CHECKED');
                    echo ">" . _("Detailed"); ?><br>
            </td>
            <td class="desc">
                <span id="basiceditor" style="display: <?php
                    condOut($settings, 'editor', 'B', 'inline', 'none'); ?>"><?php
                    echo _("The <B>Basic</b> editor is quick and easy: from where, to where, the date and optionally the airline, up to four flights at a time. The fastest way to build up your map!") ?>
                </span>
                <span id="detaileditor" style="display: <?php
                    condOut($settings, 'editor', 'D', 'inline', 'none'); ?>"><?php
                    echo _("The <B>Detailed</b> editor lets you add class of service, seat numbers, plane models and registrations, freeform notes and much more. Perfect for aviation fans and planespotters.") ?>
                </span>
            </td>
          </tr>
          <tr>
            <td class="key"><?php echo _("Distances") ?></td>
            <td class="value">
              <input type="radio" name="units" value="M" <?php
                condOut($settings, 'units', 'M', 'CHECKED'); echo ">" . _("Miles") ?><br>
              <input type="radio" name="units" value="K" <?php
                condOut($settings, 'units', 'K', 'CHECKED'); echo ">" . _("Kilometers") ?><br>
            </td>
            <td class="desc"><?php echo _("Preferred unit for flight distances") ?></td>
          </tr>

<?php if ($type == "settings") { ?>
          <tr class="gold">
            <td class="key"><?php echo _("Guest password") ?></td>
            <td class="value"><input type="password" name="guestpw" size="20" DISABLED></td>
            <td class="desc" colspan=2>
                <a href="/donate" target="_blank"><img src="/img/gold-star-mini.png" alt="Gold star" title="<?php
                    echo _("Gold Elite feature") ?>" height=17 width=17></a> <?php
                    echo _("Password protect your Private profile, so only friends and family can see it.") ?>
            </td>
          </tr>
          <tr class="gold">
            <td class="key"><?php echo _("Default view") ?></td>
            <td class="value">
                <input type="radio" name="startpane" value="H" DISABLED <?php
                    condOut($settings, 'startpane', 'H', 'CHECKED');
                    echo ">" . _("Help"); ?><br>
                <input type="radio" name="startpane" value="A" DISABLED <?php
                    condOut($settings, 'startpane', 'A', 'CHECKED');
                    echo ">" . _("Analyze"); ?><br>
                <input type="radio" name="startpane" value="T" DISABLED <?php
                    condOut($settings, 'startpane', 'T', 'CHECKED');
                    echo ">" . _("Top 10"); ?>
            </td>
            <td class="desc" colspan=2>
                <a href="/donate" target="_blank"><img src="/img/gold-star-mini.png" alt="Gold star" title="<?php
                    echo _("Gold Elite feature"); ?>" height=17 width=17></a> <?php
                    echo _("Display a screen of your choice instead of banner ads."); ?>
            </td>
          </tr>
          <tr>
             <td colspan="4"><h2><?php echo _("Manage flights") ?></h2></td>
          </tr>
          <tr>
            <td></td>
            <td class="value">
              <input type='button' value='<?php echo _("Backup to CSV") ?>' onClick='javascript:backupFlights()'>
              <input type='button' value='<?php echo _("Delete all flights") ?>' onClick='javascript:validate("RESET")'>
            </td>
            <td class="desc" colspan=2><?php
                printf(
                    _("<%s>CSV</a> files can be opened and edited with spreadsheets like Excel."),
                    "a href='/help/csv.html'"
                ); ?>
            </td>
          </tr>
          <tr>
            <td colspan="4"><h2><?php echo _("Change password") ?></h2></td>
          </tr>
          <tr>
            <td class="key"><?php echo _("Current password") ?></td>
            <td class="value"><input type="password" name="oldpw" size="20">
            <input type="hidden" name="username" value="<?php echo $_SESSION['name']?>"></td>
            <td class="desc" colspan=2><?php echo _("You only need to enter this if changing your password.") ?></td>
          </tr>
          <tr>
            <td class="key"><?php echo _("New password") ?></td>
            <td class="value"><input type="password" name="pw1" size="20"></td>
          </tr>
          <tr>
            <td class="key"><?php echo _("New password again") ?>&nbsp;</td>
            <td class="value"><input type="password" name="pw2" size="20"></td>
          </tr>
          <tr>
            <td class="key"></td>
            <td class="value">
              <input type="button" value="<?php echo _("Save changes") ?>" onClick="validate('EDIT')">
              <input type="button" value="<?php echo _("Cancel") ?>" onClick="location.href = '/'">
            </td>
          </tr>
<?php } else { ?>
          <tr>
            <td class="key"></td>
            <td class="value">
              <input type="button" value="<?php echo _("Sign me up!") ?>" onClick="validate('NEW')">
              <small><A href="/"><?php echo _("Cancel") ?></a></small>
            </td>
          </tr>
<?php } ?>
          </table>
        </form>
      </div>
    </div>

    <div id="sideBar">
<?php include '../sidebar.html';
include 'ad-sidebar.html';
?>
    </div>

      </div> <!-- end sidebarwrapper -->
    </div> <!-- end mainContainer -->

  </body>
</html>
