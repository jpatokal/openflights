<?php

require_once "../php/config.php";
require_once "../php/locale.php";
require_once "../php/db_pdo.php";
require_once "../php/helper.php";

$type = isset($_GET["new"]) ? "signup" : "settings";

if (isset($OF_ENABLE_SIGNUP) && !$OF_ENABLE_SIGNUP) {
  http_response_code(403);
  exit;
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title><?php echo sprintf(_('OpenFlights: %s'), ($type == "signup") ? _("Create new account") : _("Account settings")); ?></title>
    <link rel="stylesheet" href="/css/style_reset.min.css" type="text/css">
    <link rel="stylesheet" href="/css/signup.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale;?>/LC_MESSAGES/messages.po" />
    <link rel="icon" type="image/png" href="/img/icon_favicon.png"/>
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
        <h1>
          <a name="top"><?php echo ($type == "signup") ? _("Create new account") : _("Account settings"); ?><a/>
        </h1>

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
            <td colspan="3"><h2><?php echo _("Basic information"); ?></h2></td>
          </tr>
          <tr>
            <td class="key"><label for="username"><?php echo _("Username"); ?></label></td>
            <td class="value"><input type="text" id="username" name="username" size="20" onChange="changeName();"></td>
            <td class="value"><p><?php echo _("This will be used as the name of your profile."); ?></p>
            <span id="profileurl"></span></td>
          </tr>
          <tr>
            <td class="key"><label for="pw1"><?php echo _("Password"); ?></label></td>
            <td class="value"><input type="password" id="pw1" name="pw1" size="20"></td>
            <td class="value"><?php
              echo _("Pick something hard to guess, but easy to remember. Case-sensitive!")?>
            </td>
          </tr>
          <tr>
            <td class="key"><label for="pw2"><?php echo _("Password again"); ?></label></td>
            <td class="value"><input type="password" id="pw2" name="pw2" size="20"></td>
          </tr>
          <tr>
            <td class="key"><label for="email"><?php echo _("E-mail (optional)"); ?></label></td>
            <td class="value"><input type="text" id="email" name="email" size="20"></td>
            <td class="desc">
              <?php printf(
                  _("If you forget your password, we can mail you a new one to this address. We will <i>never</i> send you any other mail or share your private information, see <%s>privacy policy</a> for details."),
                  "a href='#' onClick='window.open(\"/help/privacy.php\", \"Help\", \"width=500,height=400,scrollbars=yes\")'"
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
        "SELECT elite, validity, email, name, guestpw, public, count, editor, units, startpane, locale
         FROM users
         WHERE uid = ?"
    );
    $sth->execute([$uid]);
    $settings = $sth->fetch();
    if (!$settings) {
        die(_("Could not load profile data"));
    }

    // https://github.com/jpatokal/openflights/issues/1279
    if (in_array($uid, (array)$OF_ADMIN_UID)) {
        $settings['elite'] = 'P';
    }
    ?>
          <tr>
            <td class="key"><nobr><label for="myurl"><?php echo _("Profile address"); ?></label></nobr></td>
            <td class="value"><input type="text" id="myurl" name="myurl" value="<?php
                echo "https://openflights.org/user/" . $settings["name"]
            ?>" style="border:none" size="40" READONLY>
              <input type="text" name="count" value="<?php
                printf(_("Viewed %s times"), $settings["count"]); ?>" style="border: none" READONLY>
            </td>
            <td class="desc"><?php
              echo _("The public address of your profile and how often it has been viewed."); ?>
            </td>
            <td class="value" rowspan=3><span id="eliteicon"></span>
              <input type="hidden" name="elite" value="<?php echo $settings["elite"] ?>">
              <input type="hidden" name="validity" value="<?php echo $settings["validity"] ?>">
            </td>
          </tr>
          <tr>
            <td class="key"><?php echo _("Banners"); ?></td>
            <td class="value" colspan=2><label for="banner_html"><?php echo _("Blog banner (HTML)"); ?></label><br>
              <textarea id="banner_html" name="banner_html" cols="60" rows="4" readonly><?php
                echo "<a href='https://openflights.org/user/" .
                  $settings["name"] .
                  "' target='_blank'><img src='https://openflights.org/banner/" .
                  $settings["name"] . ".png' width=400 height=70></a>"; ?>
              </textarea>
              <br>
              <label for="banner_phpbb"><?php echo _("Bulletin board banner (phpBB)"); ?></label><br>
              <textarea id="banner_phpbb" name="banner_phpbb" cols="60" rows="3" readonly><?php
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
            <td class="key"><label for="email"><?php echo _("E-mail (optional)"); ?></label>&nbsp;&nbsp;</td>
            <td class="value"><input type="text" id="email" name="email" value="<?php echo $settings["email"] ?>" size="20" />
            <td class="desc">
              <?php printf(
                _("If you forget your password, we can mail you a new one to this address. We will <i>never</i> send you any other mail or share your private information, see <%s>privacy policy</a> for details."),
                  "a href='#' onClick='window.open(\"/help/privacy.php\", \"Help\", \"width=500,height=400,scrollbars=yes\")'"
              ); ?>
            </td>
          </tr>
    <?php
}
?>
        <tr>
          <td colspan="4"><h2><?php echo _("Profile settings"); ?></h2>
<?php
if ($type == "signup") {
    echo _("You can easily change these later by clicking on <i>Settings</i>.");
} ?></td>
        </tr>
        <tr>
          <td class="key"><label for="locale"><?php echo _("Language"); ?></label></td>
          <td class="value"><?php locale_pulldown($dbh, $locale); ?></td>
        </tr>
        <tr>
            <td class="key"><?php echo _("Privacy"); ?></td>
            <td class="value">
              <input type="radio" id="privacyN" name="privacy" value="N" onClick="JavaScript:changePrivacy('N')" <?php
                condArrOut($settings, 'public', 'N', 'CHECKED');
                ?> ><label for="privacyN"><?php echo _("Private"); ?></label><br>
              <input type="radio" id="privacyY" name="privacy" value="Y" onClick="JavaScript:changePrivacy('Y')" <?php
                condArrOut($settings, 'public', 'Y', 'CHECKED');
                ?> ><label for="privacyY"><?php echo _("Public"); ?></label><br>
              <input type="radio" id="privacyO" name="privacy" value="O" onClick="JavaScript:changePrivacy('O')" <?php
                condArrOut($settings, 'public', '0', 'CHECKED');
                ?> ><label for="privacyO"><?php echo _("Open"); ?></label><br>
            </td>
            <td class="desc">
              <span id="privacyN" style="display: none">
                <?php printf(
                  _("<b>Private</b> profiles are visible only to you. <%s>Gold and Platinum</a> users can password-protect their private profiles, so only people who know the password can see them."),
                    'a href="/donate" target="_blank"'
                ); ?>
                </span>
                <span id="privacyY" style="display: inline"><?php
                  echo _("<b>Public</b> profiles let others see your flight map and general statistics, but flight details like exact dates and class of service are not revealed."); ?>
                </span>
                <span id="privacyO" style="display: none"><?php
                  echo _("<b>Open</b> profiles let others see, but not edit, your detailed flight data as well."); ?>
              </span>
            </td>
          </tr>
          <tr>
            <td class="key"><?php echo _("Flight editor"); ?></td>
            <td class="value">
              <input type="radio" id="editorB" name="editor" value="B" onClick="JavaScript:changeEditor('B')" <?php
                condArrOut($settings, 'editor', 'B', 'CHECKED');
                ?> ><label for="editorB"><?php echo _("Basic"); ?></label><br>
              <input type="radio" id="editorD" name="editor" value="D" onClick="JavaScript:changeEditor('D')"<?php
                condArrOut($settings, 'editor', 'D', 'CHECKED');
                ?> ><label for="editorD"><?php echo _("Detailed"); ?></label><br>
            </td>
            <td class="desc">
              <span id="basiceditor" style="display: <?php
                    condArrOut($settings, 'editor', 'B', 'inline', 'none'); ?>"><?php
                echo _("The <b>Basic</b> editor is quick and easy: from where, to where, the date and optionally the airline, up to four flights at a time. The fastest way to build up your map!"); ?>
              </span>
              <span id="detaileditor" style="display: <?php
                    condArrOut($settings, 'editor', 'D', 'inline', 'none'); ?>"><?php
                echo _("The <b>Detailed</b> editor lets you add class of service, seat numbers, plane models and registrations, freeform notes and much more. Perfect for aviation fans and planespotters."); ?>
              </span>
            </td>
          </tr>
          <tr>
            <td class="key"><?php echo _("Distances"); ?></td>
            <td class="value">
              <input type="radio" id="unitsM" name="units" value="M" <?php
                condArrOut($settings, 'units', 'M', 'CHECKED');
                ?> ><label for="unitsM"><?php echo _("Miles"); ?></label><br>
              <input type="radio" id="unitsK" name="units" value="K" <?php
                condArrOut($settings, 'units', 'K', 'CHECKED');
                ?> ><label for="unitsK"><?php echo _("Kilometers"); ?></label><br>
            </td>
            <td class="desc"><?php echo _("Preferred unit for flight distances"); ?></td>
          </tr>

<?php if ($type == "settings") { ?>
          <tr class="gold">
            <td class="key"><label for="guestpw"><?php echo _("Guest password"); ?></label></td>
            <td class="value"><input type="password" id="guestpw" name="guestpw" size="20" DISABLED></td>
            <td class="desc" colspan=2>
              <a href="/donate" target="_blank"><img src="/img/gold-star-mini.png" alt="Gold star" title="<?php
                echo _("Gold Elite feature"); ?>" height=17 width=17></a> <?php
                echo _("Password protect your Private profile, so only friends and family can see it."); ?>
            </td>
          </tr>
          <tr class="gold">
            <td class="key"><?php echo _("Default view"); ?></td>
            <td class="value">
              <input type="radio" id="startpaneH" name="startpane" value="H" DISABLED <?php
                condArrOut($settings, 'startpane', 'H', 'CHECKED');
                ?> ><label for="startpaneH"><?php echo _("Help"); ?></label><br>
              <input type="radio" id="startpaneA" name="startpane" value="A" DISABLED <?php
                condArrOut($settings, 'startpane', 'A', 'CHECKED');
                ?> ><label for="startpaneA"><?php echo _("Analyze"); ?></label><br>
              <input type="radio" id="startpaneT" name="startpane" value="T" DISABLED <?php
                condArrOut($settings, 'startpane', 'T', 'CHECKED');
                ?> ><label for="startpaneT"><?php echo _("Top 10"); ?></label><br>
            </td>
            <td class="desc" colspan=2>
              <a href="/donate" target="_blank"><img src="/img/gold-star-mini.png" alt="Gold star" title="<?php
                echo _("Gold Elite feature"); ?>" height=17 width=17></a> <?php
                echo _("Display a screen of your choice instead of banner ads."); ?>
            </td>
          </tr>
          <tr>
             <td colspan="4"><h2><?php echo _("Manage flights"); ?></h2></td>
          </tr>
          <tr>
            <td></td>
            <td class="value">
              <input type='button' value='<?php echo _("Backup to CSV"); ?>' onClick='javascript:backupFlights()'>
              <input type='button' value='<?php echo _("Delete all flights"); ?>' onClick='javascript:validate("RESET")'>
            </td>
            <td class="desc" colspan=2><?php
                printf(
                    _("<%s>CSV</a> files can be opened and edited with spreadsheets like Excel."),
                    "a href='/help/csv.php'"
                ); ?>
            </td>
          </tr>
          <tr>
            <td colspan="4"><h2><?php echo _("Change password"); ?></h2></td>
          </tr>
          <tr>
            <td class="key"><label for="oldpw"><?php echo _("Current password"); ?></label></td>
            <td class="value"><input type="password" id="oldpw" name="oldpw" size="20">
            <input type="hidden" name="username" value="<?php echo $_SESSION['name']?>"></td>
            <td class="desc" colspan=2><?php echo _("You only need to enter this if changing your password."); ?></td>
          </tr>
          <tr>
            <td class="key"><label for="pw1"><?php echo _("New password"); ?></label></td>
            <td class="value"><input type="password" id="pw1" name="pw1" size="20"></td>
          </tr>
          <tr>
            <td class="key"><label for="pw2"><?php echo _("New password again"); ?></label>&nbsp;</td>
            <td class="value"><input type="password" id="pw2" name="pw2" size="20"></td>
          </tr>
          <tr>
            <td class="key"></td>
            <td class="value">
              <input type="button" value="<?php echo _("Save changes"); ?>" onClick="validate('EDIT')">
              <input type="button" value="<?php echo _("Cancel"); ?>" onClick="location.href = '/'">
            </td>
          </tr>
<?php } else { ?>
          <tr>
            <td class="key"></td>
            <td class="value">
              <input type="button" value="<?php echo _("Sign me up!"); ?>" onClick="validate('NEW')">
              <small><A href="/"><?php echo _("Cancel"); ?></a></small>
            </td>
          </tr>
<?php } ?>
          </table>
        </form>
      </div>
    </div>

    <div id="sideBar">
<?php
include_once '../sidebar.php';
include_once 'ad-sidebar.html';
?>
    </div>

      </div> <!-- end sidebarwrapper -->
    </div> <!-- end mainContainer -->

  </body>
</html>
